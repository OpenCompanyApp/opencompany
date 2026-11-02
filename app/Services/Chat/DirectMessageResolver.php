<?php

namespace App\Services\Chat;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Resolves the durable DirectMessage metadata for one-to-one chat channels.
 *
 * DM channels are the visible conversation containers, while direct_messages is
 * the canonical pair metadata used by agent dispatch and some legacy APIs.
 * This service owns the bridge between the two so controllers do not silently
 * create orphan DM channels or ignore recoverable metadata drift.
 */
class DirectMessageResolver
{
    /**
     * Find or create a workspace-scoped DM between two users.
     *
     * If an older channel exists with the right two members but no
     * direct_messages row, it is repaired instead of creating a duplicate
     * conversation. The repair path is intentionally restricted to exact
     * two-member channels so group/private channels cannot be mistaken for DMs.
     */
    public function getOrCreate(string $userAId, string $userBId): DirectMessage
    {
        // Invariant: participant validation runs before any lookup or repair,
        // so a rejected pair can never touch (or create) channels/metadata.
        if ($userAId === $userBId) {
            throw new DirectMessageParticipantRejected(
                'A direct message needs two different participants.',
                DirectMessageParticipantRejected::REASON_SELF,
            );
        }

        $this->assertWorkspaceParticipant($userAId);
        $this->assertWorkspaceParticipant($userBId);

        $existing = $this->findForParticipants($userAId, $userBId);

        if ($existing !== null) {
            if ($existing->channel) {
                $this->ensureMembers($existing->channel, [$userAId, $userBId]);
            }

            return $existing;
        }

        $channel = $this->findRepairableChannel($userAId, $userBId) ?? $this->createChannel([$userAId, $userBId]);
        $this->ensureMembers($channel, [$userAId, $userBId]);

        return $this->createMetadata($channel, $userAId, $userBId);
    }

    /**
     * Open (find or create) the DM between the authenticated actor and a target.
     *
     * This is the entry point for user-facing transports: the actor is always
     * one of the two participants (never two arbitrary ids from the request),
     * the pair must be distinct, and both must belong to the active workspace.
     *
     * @throws DirectMessageParticipantRejected
     */
    public function openForActor(User $actor, string $targetUserId): DirectMessage
    {
        return $this->getOrCreate((string) $actor->id, $targetUserId);
    }

    /**
     * Resolve the agent recipient for a human-authored message in a DM channel.
     *
     * Normal DMs use direct_messages. When that row is missing, we repair only
     * the narrow browser-observed failure mode: a workspace DM channel with
     * exactly the sender and one agent member. Ambiguous membership is logged
     * and left untouched to avoid dispatching a message to the wrong agent.
     */
    public function resolveAgentDmForMessage(Channel $channel, Message $message): ?DirectMessage
    {
        $existing = DirectMessage::query()
            ->where('channel_id', $channel->id)
            ->where('workspace_id', $channel->workspace_id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $members = $this->membersForChannel($channel);
        $sender = $members->firstWhere('id', $message->author_id);
        $agents = $members->filter(fn (User $user): bool => $user->type === 'agent')->values();

        if ($channel->type !== 'dm' || $members->count() !== 2 || ! $sender || $sender->type !== 'human' || $agents->count() !== 1) {
            Log::warning('Skipping agent response for orphan or ambiguous DM channel.', [
                'channel_id' => $channel->id,
                'author_id' => $message->author_id,
                'member_count' => $members->count(),
                'agent_count' => $agents->count(),
                'workspace_id' => $channel->workspace_id,
            ]);

            return null;
        }

        /** @var User $agent */
        $agent = $agents->first();

        return $this->createMetadata($channel, $message->author_id, $agent->id);
    }

    public function findForParticipants(string $userAId, string $userBId): ?DirectMessage
    {
        $participantKey = $this->participantKey($userAId, $userBId);
        $existing = DirectMessage::query()
            ->where('workspace_id', workspace()->id)
            ->where('participant_key', $participantKey)
            ->latest('updated_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // Repair a pre-scope row only through a channel proven to belong to
        // the active workspace. Never fall back to an unscoped global pair.
        // This also covers imports/tests where model events were disabled and
        // therefore could not fill the derived columns at creation time.
        $legacy = DirectMessage::query()
            ->whereHas('channel', fn ($query) => $query->where('workspace_id', workspace()->id))
            ->where(function ($query) use ($userAId, $userBId): void {
                $query->where(function ($ordered) use ($userAId, $userBId): void {
                    $ordered->where('user1_id', $userAId)->where('user2_id', $userBId);
                })->orWhere(function ($reversed) use ($userAId, $userBId): void {
                    $reversed->where('user1_id', $userBId)->where('user2_id', $userAId);
                });
            })
            ->latest('updated_at')
            ->first();

        if ($legacy === null) {
            return null;
        }

        $legacy->update([
            'workspace_id' => workspace()->id,
            'participant_key' => $participantKey,
        ]);

        return $legacy;
    }

    /**
     * @param  list<string>  $userIds
     */
    private function createChannel(array $userIds): Channel
    {
        $channel = Channel::query()->create([
            'id' => Str::uuid()->toString(),
            'name' => 'DM',
            'type' => 'dm',
            'workspace_id' => workspace()->id,
        ]);

        $this->ensureMembers($channel, $userIds);

        return $channel;
    }

    private function createMetadata(Channel $channel, string $userAId, string $userBId): DirectMessage
    {
        if ($channel->workspace_id !== workspace()->id) {
            throw new \DomainException('Direct message channel is outside the active workspace.');
        }
        $this->assertWorkspaceParticipant($userAId);
        $this->assertWorkspaceParticipant($userBId);

        try {
            return DirectMessage::query()->create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $channel->workspace_id,
                'participant_key' => $this->participantKey($userAId, $userBId),
                'user1_id' => $userAId,
                'user2_id' => $userBId,
                'channel_id' => $channel->id,
                'last_message_at' => $channel->last_message_at ?? now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $existing = $this->findForParticipants($userAId, $userBId);

            if ($existing !== null) {
                return $existing;
            }

            throw $e;
        }
    }

    private function findRepairableChannel(string $userAId, string $userBId): ?Channel
    {
        return Channel::forWorkspace()
            ->where('type', 'dm')
            ->whereDoesntHave('messages', fn ($query) => $query->whereNotIn('author_id', [$userAId, $userBId]))
            ->whereHas('users', fn ($query) => $query->where('users.id', $userAId))
            ->whereHas('users', fn ($query) => $query->where('users.id', $userBId))
            ->with('users')
            ->orderByDesc('updated_at')
            ->get()
            ->first(function (Channel $channel) use ($userAId, $userBId): bool {
                $memberIds = $channel->users->pluck('id')->sort()->values()->all();
                $expected = collect([$userAId, $userBId])->sort()->values()->all();

                return $memberIds === $expected;
            });
    }

    /**
     * Canonical ordering makes the workspace pair unique even when concurrent
     * callers supply the participants in opposite orders.
     *
     * @return array{string, string}
     */
    private function participantKey(string $userAId, string $userBId): string
    {
        $pair = [$userAId, $userBId];
        sort($pair, SORT_STRING);

        return implode(':', $pair);
    }

    private function assertWorkspaceParticipant(string $userId): void
    {
        $workspaceId = workspace()->id;
        $belongs = User::query()
            ->whereKey($userId)
            ->where(function ($query) use ($workspaceId): void {
                $query->where(function ($agent) use ($workspaceId): void {
                    $agent->where('type', 'agent')->where('workspace_id', $workspaceId);
                })->orWhere(function ($human) use ($workspaceId): void {
                    $human->where('type', 'human')
                        ->whereHas('workspaces', fn ($membership) => $membership->where('workspaces.id', $workspaceId));
                });
            })
            ->exists();

        if (! $belongs) {
            throw new DirectMessageParticipantRejected(
                'Direct message participant is outside the active workspace.',
                DirectMessageParticipantRejected::REASON_OUTSIDE_WORKSPACE,
            );
        }
    }

    /**
     * @param  list<string>  $userIds
     */
    private function ensureMembers(Channel $channel, array $userIds): void
    {
        foreach (array_values(array_unique($userIds)) as $userId) {
            ChannelMember::query()->firstOrCreate(
                ['channel_id' => $channel->id, 'user_id' => $userId],
                ['role' => 'member', 'joined_at' => now()],
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function membersForChannel(Channel $channel): Collection
    {
        return $channel->users()
            ->where(function ($query): void {
                $query->where('users.type', 'human')
                    ->orWhere('users.type', 'agent');
            })
            ->get();
    }
}

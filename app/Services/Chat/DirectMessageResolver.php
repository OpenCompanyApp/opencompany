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
        $existing = $this->findForParticipants($userAId, $userBId)
            ?? $this->findGlobalPairForLegacyUniqueConstraint($userAId, $userBId);

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
        return DirectMessage::query()
            ->whereHas('channel', fn ($query) => $query
                ->where('workspace_id', workspace()->id)
                ->where('type', 'dm'))
            ->where(function ($outer) use ($userAId, $userBId): void {
                $outer->where(function ($query) use ($userAId, $userBId): void {
                    $query->where('user1_id', $userAId)->where('user2_id', $userBId);
                })->orWhere(function ($query) use ($userAId, $userBId): void {
                    $query->where('user1_id', $userBId)->where('user2_id', $userAId);
                });
            })
            ->latest('updated_at')
            ->first();
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
        try {
            return DirectMessage::query()->create([
                'id' => Str::uuid()->toString(),
                'user1_id' => $userAId,
                'user2_id' => $userBId,
                'channel_id' => $channel->id,
                'last_message_at' => $channel->last_message_at ?? now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $existing = $this->findForParticipants($userAId, $userBId)
                ?? $this->findGlobalPairForLegacyUniqueConstraint($userAId, $userBId);

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

    private function findGlobalPairForLegacyUniqueConstraint(string $userAId, string $userBId): ?DirectMessage
    {
        $dm = DirectMessage::query()
            ->with('channel')
            ->where(function ($outer) use ($userAId, $userBId): void {
                $outer->where(function ($query) use ($userAId, $userBId): void {
                    $query->where('user1_id', $userAId)->where('user2_id', $userBId);
                })->orWhere(function ($query) use ($userAId, $userBId): void {
                    $query->where('user1_id', $userBId)->where('user2_id', $userAId);
                });
            })
            ->latest('updated_at')
            ->first();

        if ($dm && $dm->channel?->workspace_id !== workspace()->id) {
            Log::warning('Using globally unique direct message pair outside current workspace scope.', [
                'direct_message_id' => $dm->id,
                'channel_id' => $dm->channel_id,
                'channel_workspace_id' => $dm->channel?->workspace_id,
                'current_workspace_id' => workspace()->id,
                'user_a_id' => $userAId,
                'user_b_id' => $userBId,
            ]);
        }

        return $dm;
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

<?php

namespace App\Http\Controllers\Api;

use App\Domain\Authorization\Application\AuthorizationScopes;
use App\Domain\Authorization\Application\AuthorizationService;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Message;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Channel API: listing, reading, creating and membership.
 *
 * Authorization (GIN-1198, GIN-1146): every read and write asks
 * AuthorizationService (`channel.read`, `channel.create`, `channel.join`,
 * `channel.leave`, `channel.manage_members`) before any query result or
 * side effect leaves this controller. Lists use
 * AuthorizationScopes::readableChannels(), the query form of the same rule.
 * Public channels are open to workspace members; private channels and DMs
 * need membership; external bridges need membership or workspace admin.
 * Unreadable channels are a 404, like missing ones.
 */
class ChannelController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly AuthorizationScopes $scopes,
    ) {}

    /**
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public function index(Request $request)
    {
        // Invariant: only channels the actor may read are listed, so names,
        // members and latest-message previews never leak.
        $query = $this->scopes->readableChannels($request->user(), workspace())
            ->with(['users', 'creator', 'latestMessage.author']);

        // Exclude routine channels from chat listing
        $query->whereNotIn('id', function ($q) {
            $q->select('channel_id')
              ->from('routines')
              ->whereNotNull('channel_id');
        });

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($provider = $request->query('provider')) {
            $query->where('external_provider', $provider);
        }

        $channels = $query->orderBy('updated_at', 'desc')->get();

        return $channels->map(function ($channel) {
            $channelArray = $channel->toArray();
            $channelArray['members'] = $channel->users->toArray();
            unset($channelArray['users']);

            // Include latest message preview for sidebar
            /** @var Message|null $latestMessage */
            $latestMessage = $channel->latestMessage;
            if ($latestMessage) {
                $channelArray['latest_message'] = [
                    'id' => $latestMessage->id,
                    'content' => $latestMessage->content,
                    'author' => $latestMessage->author ? [
                        'id' => $latestMessage->author->id,
                        'name' => $latestMessage->author->name,
                        'type' => $latestMessage->author->type,
                    ] : null,
                    'timestamp' => $latestMessage->timestamp ?? $latestMessage->created_at,
                ];
            }

            return $channelArray;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, string $id)
    {
        $channel = Channel::forWorkspace()->with(['users', 'creator'])->findOrFail($id);
        $this->authorization->authorize($request->user(), 'channel.read', $channel);

        $channelArray = $channel->toArray();
        $channelArray['members'] = $channel->users->toArray();
        unset($channelArray['users']);
        return $channelArray;
    }

    /**
     * Create a public or private channel. The authenticated user is always
     * the creator and channel admin (a client-supplied creatorId is ignored);
     * every other member must be a member or agent of this workspace. DMs are
     * created through the DM endpoints, external channels by bridges.
     *
     * @return array<string, mixed>|JsonResponse
     */
    public function store(Request $request)
    {
        $actor = $request->user();
        $this->authorization->authorize($actor, 'channel.create', workspace());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'sometimes|string|in:public,private',
            'description' => 'nullable|string',
            'memberIds' => 'sometimes|array',
            'memberIds.*' => 'string',
        ]);

        $memberIds = array_values(array_unique(array_diff($validated['memberIds'] ?? [], [(string) $actor->id])));
        $outside = array_diff($memberIds, $this->workspaceParticipantIds($memberIds));
        if ($outside !== []) {
            return response()->json(['message' => 'Every member must belong to this workspace.', 'error' => 'target_not_in_workspace'], 422);
        }

        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'public',
            'description' => $validated['description'] ?? null,
            'creator_id' => $actor->id,
        ]);

        ChannelMember::create(['channel_id' => $channel->id, 'user_id' => $actor->id, 'role' => 'admin']);
        foreach ($memberIds as $memberId) {
            ChannelMember::create(['channel_id' => $channel->id, 'user_id' => $memberId, 'role' => 'member']);
        }

        $channel->load(['users', 'creator']);
        $channelArray = $channel->toArray();
        $channelArray['members'] = $channel->users->toArray();
        unset($channelArray['users']);

        return $channelArray;
    }

    /**
     * Add a member. Adding yourself to a public channel is `channel.join`;
     * adding anyone else needs `channel.manage_members` (channel admin or
     * workspace moderator) and a target inside this workspace.
     */
    public function addMember(Request $request, string $channelId): ChannelMember
    {
        $channel = Channel::forWorkspace()->findOrFail($channelId);
        $actor = $request->user();
        $userId = (string) $request->input('userId', '');

        if ($userId === (string) $actor->id) {
            $this->authorization->authorize($actor, 'channel.join', $channel);
        } else {
            $this->authorization->authorize($actor, 'channel.manage_members', $channel, [
                'target_in_workspace' => $userId !== '' && $this->workspaceParticipantIds([$userId]) === [$userId],
                'target_user_id' => $userId,
            ]);
        }

        $member = ChannelMember::firstOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $userId],
            ['role' => 'member'],
        );

        return $member->load('user');
    }

    /** Leaving is `channel.leave`; removing someone else is `channel.manage_members`. */
    public function removeMember(Request $request, string $channelId, string $userId): JsonResponse
    {
        $channel = Channel::forWorkspace()->findOrFail($channelId);
        $actor = $request->user();

        $userId === (string) $actor->id
            ? $this->authorization->authorize($actor, 'channel.leave', $channel)
            : $this->authorization->authorize($actor, 'channel.manage_members', $channel, ['target_user_id' => $userId]);

        ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function markRead(Request $request, string $channelId): JsonResponse
    {
        $channel = Channel::forWorkspace()->findOrFail($channelId);
        $this->authorization->authorize($request->user(), 'channel.read', $channel);

        ChannelMember::where('channel_id', $channelId)
            ->where('user_id', auth()->id())
            ->update(['last_read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function typing(Request $request, string $channelId): JsonResponse
    {
        $channel = Channel::forWorkspace()->findOrFail($channelId);
        $this->authorization->authorize($request->user(), 'channel.read', $channel);

        return response()->json([
            'channelId' => $channelId,
            'userId' => auth()->id(),
            'userName' => auth()->user()->name,
            'isTyping' => $request->input('isTyping'),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Message>
     */
    public function pinned(Request $request, string $channelId)
    {
        $channel = Channel::forWorkspace()->findOrFail($channelId);
        $this->authorization->authorize($request->user(), 'channel.read', $channel);

        return Message::where('channel_id', $channelId)
            ->where('is_pinned', true)
            ->with(['author', 'reactions.user', 'attachments'])
            ->orderBy('pinned_at', 'desc')
            ->get();
    }

    /**
     * Which of $userIds are members (humans) or agents of this workspace.
     *
     * @param  list<string>  $userIds
     * @return list<string>
     */
    private function workspaceParticipantIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $workspaceId = workspace()->id;
        $humans = WorkspaceMember::query()->where('workspace_id', $workspaceId)->whereIn('user_id', $userIds)->pluck('user_id');
        $agents = User::query()->where('type', 'agent')->where('workspace_id', $workspaceId)->whereIn('id', $userIds)->pluck('id');

        return array_values(array_intersect($userIds, $humans->merge($agents)->map(fn ($id) => (string) $id)->all()));
    }
}

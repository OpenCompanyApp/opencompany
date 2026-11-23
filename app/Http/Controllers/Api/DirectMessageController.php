<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Services\Chat\DirectMessageParticipantRejected;
use App\Services\Chat\DirectMessageResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Direct message listing and creation for the authenticated user.
 *
 * Authorization (GIN-1146): the authenticated user is always one of the two
 * participants. A client-supplied `userId` (index) or participant pair
 * (store) can no longer name other people's conversations: listing uses the
 * session user, show/markRead need participation (otherwise 404), and
 * creation goes through DirectMessageResolver::openForActor(), which rejects
 * self-DMs and targets outside the workspace before creating anything.
 */
class DirectMessageController extends Controller
{
    public function __construct(private readonly DirectMessageResolver $directMessages) {}

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DirectMessage>
     */
    public function index(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        // Invariant: always the authenticated user's own DMs, whatever
        // `userId` the client sends.
        $userId = (string) $request->user()->id;

        return DirectMessage::with(['user1', 'user2', 'channel'])
            ->whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->where(function ($q) use ($userId) {
                $q->where('user1_id', $userId)->orWhere('user2_id', $userId);
            })
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    public function show(Request $request, string $id): DirectMessage
    {
        return $this->participantDm($request, $id)->load(['user1', 'user2', 'channel']);
    }

    /**
     * Open (find or create) a DM between the authenticated user and the other
     * id in the pair. A pair that does not include the authenticated user is
     * refused: nobody opens conversations on behalf of other people.
     */
    public function store(Request $request): DirectMessage|JsonResponse
    {
        $actorId = (string) $request->user()->id;
        $user1Id = (string) $request->input('user1Id', '');
        $user2Id = (string) $request->input('user2Id', '');

        if (! in_array($actorId, [$user1Id, $user2Id], true)) {
            return response()->json([
                'message' => 'You can only open direct messages you take part in.',
                'error' => 'dm_actor_not_participant',
            ], 403);
        }
        $targetId = $user1Id === $actorId ? $user2Id : $user1Id;

        try {
            $dm = $this->directMessages->openForActor($request->user(), $targetId);
        } catch (DirectMessageParticipantRejected $rejected) {
            return $rejected->reason === DirectMessageParticipantRejected::REASON_SELF
                ? response()->json(['message' => $rejected->getMessage(), 'error' => $rejected->reason], 422)
                : response()->json(['message' => 'User not found.', 'error' => $rejected->reason], 404);
        }

        return $dm->load(['user1', 'user2', 'channel']);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $dm = $this->participantDm($request, $id);

        ChannelMember::where('channel_id', $dm->channel_id)
            ->where('user_id', $request->user()->id)
            ->update(['last_read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /** A DM of this workspace the authenticated user takes part in, or 404. */
    private function participantDm(Request $request, string $id): DirectMessage
    {
        $userId = (string) $request->user()->id;

        return DirectMessage::whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->where(fn ($q) => $q->where('user1_id', $userId)->orWhere('user2_id', $userId))
            ->findOrFail($id);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $dms = DirectMessage::whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->where(function ($q) use ($userId) {
                $q->where('user1_id', $userId)->orWhere('user2_id', $userId);
            })
            ->get();

        $totalUnread = 0;

        foreach ($dms as $dm) {
            $member = ChannelMember::where('channel_id', $dm->channel_id)
                ->where('user_id', $userId)
                ->first();

            if ($member) {
                $unreadCount = Message::where('channel_id', $dm->channel_id)
                    ->where('author_id', '!=', $userId)
                    ->where(function ($query) use ($member) {
                        $query->where('created_at', '>', $member->last_read_at ?? '1970-01-01');
                    })
                    ->count();

                $totalUnread += $unreadCount;
            }
        }

        return response()->json(['count' => $totalUnread]);
    }
}

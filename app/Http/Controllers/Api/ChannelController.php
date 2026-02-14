<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChannelController extends Controller
{
    /**
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public function index(Request $request)
    {
        $query = Channel::with(['users', 'creator', 'latestMessage.author']);

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
    public function show(string $id)
    {
        $channel = Channel::with(['users', 'creator'])->findOrFail($id);

        $channelArray = $channel->toArray();
        $channelArray['members'] = $channel->users->toArray();
        unset($channelArray['users']);
        return $channelArray;
    }

    /**
     * @return array<string, mixed>
     */
    public function store(Request $request)
    {
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'name' => $request->input('name'),
            'type' => $request->input('type', 'channel'),
            'description' => $request->input('description'),
            'creator_id' => $request->input('creatorId'),
        ]);

        // Add creator as member
        if ($request->input('creatorId')) {
            ChannelMember::create([
                'channel_id' => $channel->id,
                'user_id' => $request->input('creatorId'),
                'role' => 'admin',
            ]);
        }

        // Add additional members
        if ($request->input('memberIds')) {
            foreach ($request->input('memberIds') as $memberId) {
                if ($memberId !== $request->input('creatorId')) {
                    ChannelMember::create([
                        'channel_id' => $channel->id,
                        'user_id' => $memberId,
                        'role' => 'member',
                    ]);
                }
            }
        }

        $channel->load(['users', 'creator']);
        $channelArray = $channel->toArray();
        $channelArray['members'] = $channel->users->toArray();
        unset($channelArray['users']);
        return $channelArray;
    }

    public function addMember(Request $request, string $channelId): ChannelMember
    {
        $member = ChannelMember::create([
            'channel_id' => $channelId,
            'user_id' => $request->input('userId'),
            'role' => 'member',
        ]);

        return $member->load('user');
    }

    public function removeMember(string $channelId, string $userId): JsonResponse
    {
        ChannelMember::where('channel_id', $channelId)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function markRead(Request $request, string $channelId): JsonResponse
    {
        $userId = $request->input('userId');

        ChannelMember::where('channel_id', $channelId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function typing(Request $request, string $channelId): JsonResponse
    {
        // This will be handled by broadcasting events
        // For now, just return success
        return response()->json([
            'channelId' => $channelId,
            'userId' => $request->input('userId'),
            'userName' => $request->input('userName'),
            'isTyping' => $request->input('isTyping'),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Message>
     */
    public function pinned(string $channelId)
    {
        return Message::where('channel_id', $channelId)
            ->where('is_pinned', true)
            ->with(['author', 'reactions.user', 'attachments'])
            ->orderBy('pinned_at', 'desc')
            ->get();
    }
}

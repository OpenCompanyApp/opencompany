<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChannelController extends Controller
{
    public function index()
    {
        $channels = Channel::with(['users', 'creator'])
            ->orderBy('updated_at', 'desc')
            ->get();

        // Map members to return users directly instead of channel_members pivot
        return $channels->map(function ($channel) {
            $channelArray = $channel->toArray();
            $channelArray['members'] = $channel->users->toArray();
            unset($channelArray['users']);
            return $channelArray;
        });
    }

    public function show(string $id)
    {
        $channel = Channel::with(['users', 'creator'])->findOrFail($id);

        $channelArray = $channel->toArray();
        $channelArray['members'] = $channel->users->toArray();
        unset($channelArray['users']);
        return $channelArray;
    }

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

    public function addMember(Request $request, string $channelId)
    {
        $member = ChannelMember::create([
            'channel_id' => $channelId,
            'user_id' => $request->input('userId'),
            'role' => 'member',
        ]);

        return $member->load('user');
    }

    public function removeMember(string $channelId, string $userId)
    {
        ChannelMember::where('channel_id', $channelId)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function markRead(Request $request, string $channelId)
    {
        $userId = $request->input('userId');

        ChannelMember::where('channel_id', $channelId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function typing(Request $request, string $channelId)
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

    public function pinned(string $channelId)
    {
        return Message::where('channel_id', $channelId)
            ->where('is_pinned', true)
            ->with(['author', 'reactions.user', 'attachments'])
            ->orderBy('pinned_at', 'desc')
            ->get();
    }
}

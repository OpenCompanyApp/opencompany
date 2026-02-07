<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DirectMessageController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->input('userId');

        return DirectMessage::with(['user1', 'user2', 'channel'])
            ->where('user1_id', $userId)
            ->orWhere('user2_id', $userId)
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    public function show(string $id)
    {
        return DirectMessage::with(['user1', 'user2', 'channel'])->findOrFail($id);
    }

    public function store(Request $request)
    {
        $user1Id = $request->input('user1Id');
        $user2Id = $request->input('user2Id');

        // Check if DM already exists
        $existingDm = DirectMessage::where(function ($query) use ($user1Id, $user2Id) {
            $query->where('user1_id', $user1Id)->where('user2_id', $user2Id);
        })->orWhere(function ($query) use ($user1Id, $user2Id) {
            $query->where('user1_id', $user2Id)->where('user2_id', $user1Id);
        })->first();

        if ($existingDm) {
            return $existingDm->load(['user1', 'user2', 'channel']);
        }

        // Create a channel for the DM
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'name' => 'DM',
            'type' => 'dm',
        ]);

        // Add both users as members
        foreach ([$user1Id, $user2Id] as $userId) {
            ChannelMember::create([
                'id' => Str::uuid()->toString(),
                'channel_id' => $channel->id,
                'user_id' => $userId,
                'role' => 'member',
            ]);
        }

        // Create the DM record
        $dm = DirectMessage::create([
            'id' => Str::uuid()->toString(),
            'user1_id' => $user1Id,
            'user2_id' => $user2Id,
            'channel_id' => $channel->id,
        ]);

        return $dm->load(['user1', 'user2', 'channel']);
    }

    public function markRead(Request $request, string $id)
    {
        $dm = DirectMessage::findOrFail($id);
        $userId = $request->input('userId');

        ChannelMember::where('channel_id', $dm->channel_id)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function unreadCount(Request $request)
    {
        $userId = $request->input('userId');

        $dms = DirectMessage::where('user1_id', $userId)
            ->orWhere('user2_id', $userId)
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

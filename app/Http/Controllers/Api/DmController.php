<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Jobs\AgentRespondJob;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DmController extends Controller
{
    /**
     * Get or create a DM conversation with a user.
     */
    public function show(string $userId): \Illuminate\Http\JsonResponse
    {
        $currentUserId = auth()->id();

        // Find or create the DM conversation (scoped to workspace)
        $dm = DirectMessage::whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->where(function ($outer) use ($currentUserId, $userId) {
                $outer->where(function ($q) use ($currentUserId, $userId) {
                    $q->where('user1_id', $currentUserId)->where('user2_id', $userId);
                })->orWhere(function ($q) use ($currentUserId, $userId) {
                    $q->where('user1_id', $userId)->where('user2_id', $currentUserId);
                });
            })->first();

        if (!$dm) {
            // Create a new DM conversation
            $channel = Channel::create([
                'id' => Str::uuid()->toString(),
                'name' => 'DM',
                'type' => 'dm',
                'workspace_id' => workspace()->id,
            ]);

            foreach ([$currentUserId, $userId] as $memberId) {
                ChannelMember::create([
                    'id' => Str::uuid()->toString(),
                    'channel_id' => $channel->id,
                    'user_id' => $memberId,
                    'role' => 'member',
                ]);
            }

            $dm = DirectMessage::create([
                'id' => Str::uuid()->toString(),
                'user1_id' => $currentUserId,
                'user2_id' => $userId,
                'channel_id' => $channel->id,
            ]);
        }

        $otherUser = User::find($userId);

        // Get messages for this conversation
        $messages = Message::with('author')
            ->where('channel_id', $dm->channel_id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (Message $msg) {
                /** @var \App\Models\User $author */
                $author = $msg->author;
                return [
                    'id' => $msg->id,
                    'content' => $msg->content,
                    'author' => [
                        'id' => $author->id,
                        'name' => $author->name,
                        'avatar' => $author->avatar,
                        'type' => $author->type,
                        'agentType' => $author->agent_type,
                        'status' => $author->status,
                    ],
                    'timestamp' => $msg->created_at->toISOString(),
                ];
            });

        return response()->json([
            'id' => $dm->id,
            'channelId' => $dm->channel_id,
            'otherUser' => [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
                'avatar' => $otherUser->avatar,
                'type' => $otherUser->type,
                'agentType' => $otherUser->agent_type,
                'status' => $otherUser->status,
            ],
            'messages' => $messages,
            'createdAt' => $dm->created_at->toISOString(),
        ]);
    }

    /**
     * Send a message in a DM conversation.
     */
    public function store(Request $request, string $userId): \Illuminate\Http\JsonResponse
    {
        $currentUserId = auth()->id();
        $currentUser = auth()->user();

        // Find the DM conversation (scoped to workspace)
        $dm = DirectMessage::whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->where(function ($outer) use ($currentUserId, $userId) {
                $outer->where(function ($q) use ($currentUserId, $userId) {
                    $q->where('user1_id', $currentUserId)->where('user2_id', $userId);
                })->orWhere(function ($q) use ($currentUserId, $userId) {
                    $q->where('user1_id', $userId)->where('user2_id', $currentUserId);
                });
            })->first();

        if (!$dm) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Create the message
        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $request->input('content'),
            'channel_id' => $dm->channel_id,
            'author_id' => $currentUserId,
            'timestamp' => now(),
        ]);

        // Update timestamps
        Channel::where('id', $dm->channel_id)->update(['last_message_at' => now()]);
        $dm->update(['last_message_at' => now()]);

        // Broadcast user's message
        broadcast(new MessageSent($message))->toOthers();

        // Build response with user's message
        $response = [
            'userMessage' => [
                'id' => $message->id,
                'content' => $message->content,
                'author' => [
                    'id' => $currentUser->id,
                    'name' => $currentUser->name,
                    'avatar' => $currentUser->avatar,
                    'type' => $currentUser->type,
                    'agentType' => $currentUser->agent_type,
                    'status' => $currentUser->status,
                ],
                'timestamp' => $message->created_at->toISOString(),
            ],
            'agentMessage' => null,
        ];

        // Check if the other user is an agent and dispatch async response
        $otherUser = User::find($userId);
        if ($otherUser?->type === 'agent') {
            $task = Task::createPending($message, $otherUser, $dm->channel_id);
            AgentRespondJob::dispatch($message, $otherUser, $dm->channel_id, $task->id);
        }

        return response()->json($response);
    }

    /**
     * Mark a DM conversation as read.
     */
    public function markRead(string $userId): \Illuminate\Http\JsonResponse
    {
        $currentUserId = auth()->id();

        $dm = DirectMessage::whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->where(function ($outer) use ($currentUserId, $userId) {
                $outer->where(function ($q) use ($currentUserId, $userId) {
                    $q->where('user1_id', $currentUserId)->where('user2_id', $userId);
                })->orWhere(function ($q) use ($currentUserId, $userId) {
                    $q->where('user1_id', $userId)->where('user2_id', $currentUserId);
                });
            })->first();

        if ($dm) {
            ChannelMember::where('channel_id', $dm->channel_id)
                ->where('user_id', $currentUserId)
                ->update(['last_read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

}

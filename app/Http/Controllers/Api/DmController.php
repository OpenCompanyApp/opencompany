<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DmController extends Controller
{
    /**
     * Get or create a DM conversation with a user.
     */
    public function show(string $userId)
    {
        $currentUserId = 'h1'; // In production, use auth()->id()

        // Find or create the DM conversation
        $dm = DirectMessage::where(function ($query) use ($currentUserId, $userId) {
            $query->where('user1_id', $currentUserId)->where('user2_id', $userId);
        })->orWhere(function ($query) use ($currentUserId, $userId) {
            $query->where('user1_id', $userId)->where('user2_id', $currentUserId);
        })->first();

        if (!$dm) {
            // Create a new DM conversation
            $channel = Channel::create([
                'id' => Str::uuid()->toString(),
                'name' => 'DM',
                'type' => 'direct',
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
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'content' => $msg->content,
                'author' => [
                    'id' => $msg->author->id,
                    'name' => $msg->author->name,
                    'avatar' => $msg->author->avatar,
                    'type' => $msg->author->type,
                    'agentType' => $msg->author->agent_type,
                    'status' => $msg->author->status,
                ],
                'timestamp' => $msg->created_at->toISOString(),
            ]);

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
    public function store(Request $request, string $userId)
    {
        $currentUserId = 'h1'; // In production, use auth()->id()
        $currentUser = User::find($currentUserId);

        // Find the DM conversation
        $dm = DirectMessage::where(function ($query) use ($currentUserId, $userId) {
            $query->where('user1_id', $currentUserId)->where('user2_id', $userId);
        })->orWhere(function ($query) use ($currentUserId, $userId) {
            $query->where('user1_id', $userId)->where('user2_id', $currentUserId);
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

        // Check if the other user is an agent and respond
        $otherUser = User::find($userId);
        if ($otherUser->type === 'agent') {
            $agentMessage = $this->generateAgentResponse($dm, $otherUser, $message);
            if ($agentMessage) {
                $response['agentMessage'] = [
                    'id' => $agentMessage->id,
                    'content' => $agentMessage->content,
                    'author' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar' => $otherUser->avatar,
                        'type' => $otherUser->type,
                        'agentType' => $otherUser->agent_type,
                        'status' => $otherUser->status,
                    ],
                    'timestamp' => $agentMessage->created_at->toISOString(),
                ];
            }
        }

        return response()->json($response);
    }

    /**
     * Mark a DM conversation as read.
     */
    public function markRead(string $userId)
    {
        $currentUserId = 'h1'; // In production, use auth()->id()

        $dm = DirectMessage::where(function ($query) use ($currentUserId, $userId) {
            $query->where('user1_id', $currentUserId)->where('user2_id', $userId);
        })->orWhere(function ($query) use ($currentUserId, $userId) {
            $query->where('user1_id', $userId)->where('user2_id', $currentUserId);
        })->first();

        if ($dm) {
            ChannelMember::where('channel_id', $dm->channel_id)
                ->where('user_id', $currentUserId)
                ->update(['last_read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Generate agent response for DM messages.
     */
    private function generateAgentResponse(DirectMessage $dm, User $agent, Message $userMessage): ?Message
    {
        try {
            // Generate agent response
            $responseText = app(AgentChatService::class)
                ->respond($agent, $dm->channel_id, $userMessage->content);

            // Create agent's response message
            $agentMessage = Message::create([
                'id' => Str::uuid()->toString(),
                'content' => $responseText,
                'channel_id' => $dm->channel_id,
                'author_id' => $agent->id,
                'timestamp' => now(),
            ]);

            // Update timestamps
            Channel::where('id', $dm->channel_id)->update(['last_message_at' => now()]);
            $dm->update(['last_message_at' => now()]);

            // Broadcast agent's response
            broadcast(new MessageSent($agentMessage));

            return $agentMessage;
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::error('Agent response failed: ' . $e->getMessage());
            return null;
        }
    }
}

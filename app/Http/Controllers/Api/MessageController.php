<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Jobs\AgentRespondJob;
use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageReaction;
use App\Models\User;
use App\Services\AgentChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $query = Message::with(['author', 'reactions.user', 'attachments', 'replyTo.author']);

        if ($request->has('channelId')) {
            $query->where('channel_id', $request->input('channelId'));
        }

        $limit = $request->input('limit', 50);

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function store(Request $request)
    {
        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $request->input('content'),
            'channel_id' => $request->input('channelId'),
            'author_id' => $request->input('authorId'),
            'reply_to_id' => $request->input('replyToId'),
            'timestamp' => now(),
        ]);

        // Attach any pre-uploaded attachments
        if ($request->input('attachmentIds')) {
            MessageAttachment::whereIn('id', $request->input('attachmentIds'))
                ->update(['message_id' => $message->id]);
        }

        // Update channel's last_message_at
        Channel::where('id', $request->input('channelId'))
            ->update(['last_message_at' => now()]);

        // Broadcast user's message
        broadcast(new MessageSent($message))->toOthers();

        // Check if this is a DM with an agent and respond
        $this->handleAgentResponse($message);

        // Check for @mentions of agents in non-DM channels
        $this->handleMentionedAgents($message);

        return $message->load(['author', 'reactions.user', 'attachments', 'replyTo.author']);
    }

    private function handleAgentResponse(Message $message): void
    {
        // Check if this is a DM channel
        $channel = Channel::find($message->channel_id);
        if (!$channel || $channel->type !== 'dm') {
            return;
        }

        // Find the DirectMessage record
        $dm = DirectMessage::where('channel_id', $message->channel_id)->first();
        if (!$dm) {
            return;
        }

        // Determine the other participant
        $otherUserId = $dm->user1_id === $message->author_id
            ? $dm->user2_id
            : $dm->user1_id;

        $otherUser = User::find($otherUserId);

        // Check if other user is an agent
        if (!$otherUser || $otherUser->type !== 'agent') {
            return;
        }

        // Dispatch async agent response via Laravel AI SDK
        if (config('app.agent_async', true)) {
            AgentRespondJob::dispatch($message, $otherUser, $message->channel_id);
            return;
        }

        // Sync fallback for development (uses old AgentChatService)
        $responseText = app(AgentChatService::class)
            ->respond($otherUser, $message->channel_id, $message->content);

        $agentMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $responseText,
            'channel_id' => $message->channel_id,
            'author_id' => $otherUser->id,
            'timestamp' => now(),
        ]);

        Channel::where('id', $message->channel_id)
            ->update(['last_message_at' => now()]);

        $dm->update(['last_message_at' => now()]);

        broadcast(new MessageSent($agentMessage));
    }

    private function handleMentionedAgents(Message $message): void
    {
        $channel = Channel::find($message->channel_id);
        if (!$channel || $channel->type === 'dm') {
            return; // DMs are handled by handleAgentResponse
        }

        // Find all agents and check if their name is @mentioned
        $agents = User::where('type', 'agent')->get();

        foreach ($agents as $agent) {
            if ($agent->id === $message->author_id) {
                continue; // Don't respond to own messages
            }

            // Match @AgentName (case-insensitive)
            if (preg_match('/@' . preg_quote($agent->name, '/') . '\b/i', $message->content)) {
                AgentRespondJob::dispatch($message, $agent, $message->channel_id);
            }
        }
    }

    public function destroy(string $id)
    {
        Message::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function addReaction(Request $request, string $messageId)
    {
        $reaction = MessageReaction::create([
            'id' => Str::uuid()->toString(),
            'message_id' => $messageId,
            'user_id' => $request->input('userId'),
            'emoji' => $request->input('emoji'),
        ]);

        return $reaction->load('user');
    }

    public function removeReaction(string $messageId, string $reactionId)
    {
        MessageReaction::where('id', $reactionId)
            ->where('message_id', $messageId)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function thread(string $messageId)
    {
        $parentMessage = Message::with(['author', 'reactions.user', 'attachments'])
            ->findOrFail($messageId);

        $replies = Message::with(['author', 'reactions.user', 'attachments'])
            ->where('reply_to_id', $messageId)
            ->orderBy('created_at', 'asc')
            ->get();

        return [
            'parentMessage' => $parentMessage,
            'replies' => $replies,
        ];
    }

    public function pin(Request $request, string $messageId)
    {
        $message = Message::findOrFail($messageId);
        $message->update([
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by_id' => $request->input('userId'),
        ]);

        return $message->load(['author', 'reactions.user', 'attachments']);
    }

    public function uploadAttachment(Request $request)
    {
        $file = $request->file('file');
        $path = $file->store('message-attachments', 'public');

        $attachment = MessageAttachment::create([
            'id' => Str::uuid()->toString(),
            'channel_id' => $request->input('channelId'),
            'uploader_id' => $request->input('uploaderId'),
            'name' => $file->getClientOriginalName(),
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'url' => '/storage/' . $path,
        ]);

        return $attachment;
    }
}

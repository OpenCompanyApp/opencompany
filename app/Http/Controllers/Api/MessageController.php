<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Jobs\AgentRespondJob;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageReaction;
use App\Models\User;
use App\Services\AgentChatService;
use App\Services\Memory\ConversationCompactionService;
use App\Services\Memory\MemoryFlushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    /**
     * @return Collection<int, Message>
     */
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

    public function store(Request $request): Message
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

    /**
     * Manually trigger conversation compaction for a channel.
     */
    public function compact(string $channelId): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $agents = $this->resolveChannelAgents($channel);

        if ($agents->isEmpty()) {
            return response()->json(['message' => 'No agents in this channel.'], 422);
        }

        $compactor = app(ConversationCompactionService::class);
        $flusher = app(MemoryFlushService::class);
        $results = [];

        foreach ($agents as $agent) {
            // Flush important memories to daily logs before compacting
            try {
                $flusher->flush($channelId, $agent);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Memory flush before compact failed', [
                    'error' => $e->getMessage(),
                    'agent' => $agent->name,
                ]);
            }

            $summary = $compactor->compact($channelId, $agent);
            if ($summary) {
                $results[] = [
                    'agent' => $agent->name,
                    'messages_summarized' => $summary->messages_summarized,
                    'tokens_before' => $summary->tokens_before,
                    'tokens_after' => $summary->tokens_after,
                    'compaction_count' => $summary->compaction_count,
                    'summary_preview' => Str::limit($summary->summary, 200),
                ];
            }
        }

        if (empty($results)) {
            return response()->json(['message' => 'Nothing to compact (fewer than 5 messages).']);
        }

        return response()->json([
            'message' => 'Compaction complete.',
            'results' => $results,
        ]);
    }

    /**
     * Resolve agent users that participate in a channel.
     *
     * @return Collection<int, User>
     */
    private function resolveChannelAgents(Channel $channel): Collection
    {
        if ($channel->type === 'dm') {
            $dm = DirectMessage::where('channel_id', $channel->id)->first();
            if (!$dm) {
                return collect();
            }

            return User::where('type', 'agent')
                ->whereIn('id', [$dm->user1_id, $dm->user2_id])
                ->get();
        }

        // For external, public, agent channels: find all agent members
        $agentIds = ChannelMember::where('channel_id', $channel->id)
            ->pluck('user_id');

        return User::where('type', 'agent')
            ->whereIn('id', $agentIds)
            ->get();
    }

    public function destroy(string $id): JsonResponse
    {
        Message::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function addReaction(Request $request, string $messageId): MessageReaction
    {
        $reaction = MessageReaction::create([
            'id' => Str::uuid()->toString(),
            'message_id' => $messageId,
            'user_id' => $request->input('userId'),
            'emoji' => $request->input('emoji'),
        ]);

        return $reaction->load('user');
    }

    public function removeReaction(string $messageId, string $reactionId): JsonResponse
    {
        MessageReaction::where('id', $reactionId)
            ->where('message_id', $messageId)
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array{parentMessage: Message, replies: \Illuminate\Database\Eloquent\Collection<int, Message>}
     */
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

    public function pin(Request $request, string $messageId): Message
    {
        $message = Message::findOrFail($messageId);
        $message->update([
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by_id' => $request->input('userId'),
        ]);

        return $message->load(['author', 'reactions.user', 'attachments']);
    }

    public function uploadAttachment(Request $request): MessageAttachment
    {
        $file = $request->file('file');
        $workspaceId = workspace()->id;
        $path = $file->store("{$workspaceId}/message-attachments", 'public');

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

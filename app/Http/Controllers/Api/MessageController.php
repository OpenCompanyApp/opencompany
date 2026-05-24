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
use App\Models\Task;
use App\Models\User;
use App\Services\AgentChatService;
use App\Services\Memory\ConversationCompactionService;
use App\Services\Memory\MemoryFlushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * API surface for channel messages and message-adjacent actions.
 *
 * Message creation is not just persistence: it may broadcast to other clients,
 * trigger agent jobs, update channel timestamps, attach uploaded files, and
 * optionally compact agent memory. Keep those side effects visible here.
 */
class MessageController extends Controller
{
    /**
     * @return Collection<int, Message>
     */
    public function index(Request $request)
    {
        $query = Message::with(['author', 'reactions.user', 'attachments', 'replyTo.author', 'approvalRequest.requester', 'approvalRequest.respondedBy']);

        if ($request->has('channelId')) {
            // Validate channel ownership before returning messages. The message
            // query itself is channel-scoped, but this makes the workspace check
            // explicit and keeps missing/foreign channels indistinguishable.
            Channel::forWorkspace()->findOrFail($request->input('channelId'));
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
        // Message validation currently happens at route/client level; this
        // endpoint assumes channelId/content are present and then performs the
        // runtime side effects below.
        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $request->input('content'),
            'channel_id' => $request->input('channelId'),
            'author_id' => auth()->id(),
            'reply_to_id' => $request->input('replyToId'),
            'timestamp' => now(),
        ]);

        // Pre-uploaded attachments are created before the message exists. Link
        // them after insert so they become part of the same chat payload.
        if ($request->input('attachmentIds')) {
            MessageAttachment::whereIn('id', $request->input('attachmentIds'))
                ->update(['message_id' => $message->id]);
        }

        // Update channel's last_message_at
        Channel::where('id', $request->input('channelId'))
            ->update(['last_message_at' => now()]);

        // Broadcast user's message
        broadcast(new MessageSent($message))->toOthers();

        // Agent triggering is intentionally after broadcast so the user's own
        // message is visible before any queued response starts.
        $this->handleAgentResponse($message);

        // Check for @mentions of agents in non-DM channels
        $this->handleMentionedAgents($message);

        return $message->load(['author', 'reactions.user', 'attachments', 'replyTo.author', 'approvalRequest.requester', 'approvalRequest.respondedBy']);
    }

    private function handleAgentResponse(Message $message): void
    {
        // Only DMs auto-trigger the other participant. Mentions in group-style
        // channels are handled separately below.
        $channel = Channel::find($message->channel_id);
        if (! $channel || $channel->type !== 'dm') {
            return;
        }

        // DirectMessage identifies the other participant; channel membership
        // alone is not enough because old DMs can include bookkeeping members.
        $dm = DirectMessage::where('channel_id', $message->channel_id)->first();
        if (! $dm) {
            return;
        }

        // Determine the other participant
        $otherUserId = $dm->user1_id === $message->author_id
            ? $dm->user2_id
            : $dm->user1_id;

        $otherUser = User::find($otherUserId);

        // Check if other user is an agent
        if (! $otherUser || $otherUser->type !== 'agent') {
            return;
        }

        // Production path: queue the agent job and create a pending task so UI,
        // retries, and runtime events all have a stable task record.
        if (config('app.agent_async', true)) {
            $task = Task::createPending($message, $otherUser, $message->channel_id);
            AgentRespondJob::dispatch($message, $otherUser, $message->channel_id, $task->id);

            return;
        }

        // Synchronous fallback exists only for local/dev setups that disable
        // async agents. It bypasses the newer AgentRun task pipeline.
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
        if (! $channel || $channel->type === 'dm') {
            return; // DMs are handled by handleAgentResponse
        }

        // Mentions are name-based and workspace-scoped. Agents never respond to
        // their own message to avoid immediate self-trigger loops.
        $agents = User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->get();

        foreach ($agents as $agent) {
            if ($agent->id === $message->author_id) {
                continue; // Don't respond to own messages
            }

            // Match @AgentName case-insensitively. This is intentionally simple
            // until the UI provides durable mention IDs.
            if (preg_match('/@'.preg_quote($agent->name, '/').'\b/i', $message->content)) {
                $task = Task::createPending($message, $agent, $message->channel_id);
                AgentRespondJob::dispatch($message, $agent, $message->channel_id, $task->id);
            }
        }
    }

    /**
     * Manually trigger conversation compaction for a channel.
     */
    public function compact(string $channelId): JsonResponse
    {
        $channel = Channel::forWorkspace()->findOrFail($channelId);
        $agents = $this->resolveChannelAgents($channel);

        if ($agents->isEmpty()) {
            return response()->json(['message' => 'No agents in this channel.'], 422);
        }

        $compactor = app(ConversationCompactionService::class);
        $flusher = app(MemoryFlushService::class);
        $results = [];

        foreach ($agents as $agent) {
            // Manual compaction first gives each agent one flush opportunity so
            // durable facts can be saved before older transcript is summarized.
            try {
                $flusher->flush($channelId, $agent);
            } catch (\Throwable $e) {
                Log::warning('Memory flush before compact failed', [
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
            if (! $dm) {
                return collect();
            }

            return User::where('type', 'agent')
                ->whereIn('id', [$dm->user1_id, $dm->user2_id])
                ->get();
        }

        // For external, public, and agent channels, compact each agent that is a
        // channel member. This keeps summaries agent-specific.
        $agentIds = ChannelMember::where('channel_id', $channel->id)
            ->pluck('user_id');

        return User::where('type', 'agent')
            ->whereIn('id', $agentIds)
            ->get();
    }

    public function destroy(string $id): JsonResponse
    {
        Message::whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->findOrFail($id)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function addReaction(Request $request, string $messageId): MessageReaction
    {
        Message::whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->findOrFail($messageId);

        $reaction = MessageReaction::create([
            'id' => Str::uuid()->toString(),
            'message_id' => $messageId,
            'user_id' => auth()->id(),
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
        $parentMessage = Message::whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->with(['author', 'reactions.user', 'attachments'])
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
        $message = Message::whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->findOrFail($messageId);
        $message->update([
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by_id' => auth()->id(),
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
            'uploader_id' => auth()->id(),
            'name' => $file->getClientOriginalName(),
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'url' => '/storage/'.$path,
        ]);

        return $attachment;
    }
}

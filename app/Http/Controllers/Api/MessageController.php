<?php

namespace App\Http\Controllers\Api;

use App\Domain\Authorization\Application\AuthorizationFacts;
use App\Domain\Authorization\Application\AuthorizationScopes;
use App\Domain\Authorization\Application\AuthorizationService;
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
use App\Services\Chat\DirectMessageResolver;
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
 *
 * Authorization (GIN-1198, GIN-1146): every endpoint asks
 * AuthorizationService before any query result, broadcast, write or queued
 * job: `channel.read` for reads, `channel.post` for posting, reacting,
 * pinning, attachments and compaction, `message.delete` and
 * `message.reaction.remove` for removals (own items, or channel
 * moderators outside DMs). Messages of channels the actor cannot read are a
 * 404, like missing ones.
 */
class MessageController extends Controller
{
    public function __construct(
        private readonly DirectMessageResolver $directMessages,
        private readonly AuthorizationService $authorization,
        private readonly AuthorizationScopes $scopes,
    ) {}

    /**
     * @return Collection<int, Message>
     */
    public function index(Request $request)
    {
        $query = Message::with(['author', 'reactions.user', 'attachments', 'replyTo.author', 'approvalRequest.requester', 'approvalRequest.respondedBy']);

        if ($request->has('channelId')) {
            // Workspace scope first (missing and foreign channels are the
            // same 404), then the actor's read access.
            $channel = Channel::forWorkspace()->findOrFail($request->input('channelId'));
            $this->authorization->authorize($request->user(), 'channel.read', $channel);
            $query->where('channel_id', $channel->id);
        } else {
            // Invariant: without a channel the query is still limited to
            // channels of this workspace the actor may read.
            $query->whereIn('channel_id', $this->scopes->readableChannels($request->user(), workspace())->select('channels.id'));
        }

        $limit = min(max((int) $request->input('limit', 50), 1), 200);

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function store(Request $request): Message
    {
        $channel = Channel::forWorkspace()->findOrFail($request->input('channelId'));

        // Invariant: authorize before any write, broadcast or agent job.
        $this->authorization->authorize($request->user(), 'channel.post', $channel);

        // A reply must point at a message of the same channel.
        $replyToId = $request->input('replyToId');
        if ($replyToId !== null && ! Message::where('channel_id', $channel->id)->whereKey($replyToId)->exists()) {
            abort(422, 'The message replied to is not in this channel.');
        }

        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $request->input('content'),
            'channel_id' => $channel->id,
            'author_id' => auth()->id(),
            'reply_to_id' => $replyToId,
            'timestamp' => now(),
        ]);

        // Pre-uploaded attachments are created before the message exists. Link
        // them after insert so they become part of the same chat payload.
        // Only the actor's own, still unlinked uploads for this channel can be
        // claimed, so a forged id cannot move someone else's file.
        if ($request->input('attachmentIds')) {
            MessageAttachment::whereIn('id', (array) $request->input('attachmentIds'))
                ->whereNull('message_id')
                ->where('uploader_id', auth()->id())
                ->where(fn ($q) => $q->whereNull('channel_id')->orWhere('channel_id', $channel->id))
                ->update(['message_id' => $message->id]);
        }

        // Update channel's last_message_at
        $channel->update(['last_message_at' => now()]);

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
        $channel = Channel::forWorkspace()->find($message->channel_id);
        if (! $channel || $channel->type !== 'dm') {
            return;
        }

        // DirectMessage identifies the other participant. The resolver can
        // repair the browser-observed orphan DM case, but only when membership
        // is unambiguous and workspace-scoped.
        $dm = $this->directMessages->resolveAgentDmForMessage($channel, $message);
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
    public function compact(Request $request, string $channelId): JsonResponse
    {
        $channel = Channel::forWorkspace()->findOrFail($channelId);
        $this->authorization->authorize($request->user(), 'channel.post', $channel);
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

    public function destroy(Request $request, string $id): JsonResponse
    {
        $message = $this->workspaceMessage($id);
        $this->authorization->authorize($request->user(), 'message.delete', $message);
        $message->delete();

        return response()->json(['success' => true]);
    }

    public function addReaction(Request $request, string $messageId): MessageReaction
    {
        $message = $this->workspaceMessage($messageId);
        $this->authorization->authorize($request->user(), 'channel.post', $message->channel);

        $reaction = MessageReaction::create([
            'id' => Str::uuid()->toString(),
            'message_id' => $message->id,
            'user_id' => auth()->id(),
            'emoji' => $request->input('emoji'),
        ]);

        return $reaction->load('user');
    }

    public function removeReaction(Request $request, string $messageId, string $reactionId): JsonResponse
    {
        $message = $this->workspaceMessage($messageId);
        $reaction = MessageReaction::where('message_id', $message->id)->findOrFail($reactionId);
        $this->authorization->authorize(
            $request->user(),
            'message.reaction.remove',
            app(AuthorizationFacts::class)->reaction($reaction, $message->channel),
        );
        $reaction->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array{parentMessage: Message, replies: \Illuminate\Database\Eloquent\Collection<int, Message>}
     */
    public function thread(Request $request, string $messageId)
    {
        $parentMessage = $this->workspaceMessage($messageId);
        $this->authorization->authorize($request->user(), 'channel.read', $parentMessage->channel);
        $parentMessage->load(['author', 'reactions.user', 'attachments']);

        $replies = Message::with(['author', 'reactions.user', 'attachments'])
            ->where('reply_to_id', $parentMessage->id)
            ->where('channel_id', $parentMessage->channel_id)
            ->orderBy('created_at', 'asc')
            ->get();

        return [
            'parentMessage' => $parentMessage,
            'replies' => $replies,
        ];
    }

    public function pin(Request $request, string $messageId): Message
    {
        $message = $this->workspaceMessage($messageId);
        $this->authorization->authorize($request->user(), 'channel.post', $message->channel);
        $message->update([
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by_id' => auth()->id(),
        ]);

        return $message->load(['author', 'reactions.user', 'attachments']);
    }

    /** A message of a channel in the current workspace, or 404. */
    private function workspaceMessage(string $id): Message
    {
        return Message::whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
            ->with('channel')
            ->findOrFail($id);
    }

    public function uploadAttachment(Request $request): MessageAttachment
    {
        $request->validate(['file' => 'required|file', 'channelId' => 'required|string']);
        $channel = Channel::forWorkspace()->findOrFail($request->input('channelId'));
        $this->authorization->authorize($request->user(), 'channel.post', $channel);

        $file = $request->file('file');
        $workspaceId = workspace()->id;
        $path = $file->store("{$workspaceId}/message-attachments", 'public');

        $attachment = MessageAttachment::create([
            'id' => Str::uuid()->toString(),
            'channel_id' => $channel->id,
            'uploader_id' => auth()->id(),
            'name' => $file->getClientOriginalName(),
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'url' => '/storage/'.$path,
        ]);

        return $attachment;
    }
}

<?php

namespace App\Agents\Conversations;

use App\Jobs\CompactConversationJob;
use App\Models\ConversationSummary;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\Memory\ConversationCompactionService;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

class ChannelConversationLoader
{
    public function __construct(
        private ConversationCompactionService $compactionService,
        private AgentPermissionService $permissionService,
    ) {}

    /**
     * Load recent messages from a channel as SDK message objects.
     *
     * Loads all messages after the last compaction point (or all messages
     * if no compaction has occurred). Compaction manages the token budget —
     * no artificial message count limits are applied here.
     *
     * @return iterable<UserMessage|AssistantMessage>
     */
    public function load(string $channelId, User $agent, ?string $systemPrompt = null): iterable
    {
        // Verify agent can access this channel
        $access = $this->permissionService->canAccessChannel($agent, $channelId);
        if (!$access['allowed']) {
            return [];
        }

        $sdkMessages = [];

        // 1. Check for existing conversation summary
        $summary = ConversationSummary::where('channel_id', $channelId)
            ->where('agent_id', $agent->id)
            ->first();

        if ($summary && !empty($summary->summary)) {
            $sdkMessages[] = new UserMessage(
                "[Conversation Summary — {$summary->messages_summarized} prior messages, "
                . "{$summary->compaction_count} compaction(s)]\n\n{$summary->summary}"
            );
        }

        // 2. Load messages after last summarized message (or all if no summary)
        $query = Message::where('channel_id', $channelId)
            ->orderBy('created_at', 'asc');

        if ($summary?->last_message_id) {
            $lastMsg = Message::find($summary->last_message_id);
            if ($lastMsg) {
                $query->where('created_at', '>', $lastMsg->created_at);
            }
        }

        foreach ($query->get() as $message) {
            if (empty($message->content)) {
                continue;
            }

            if ($message->author_id === $agent->id) {
                $sdkMessages[] = new AssistantMessage($message->content);
            } else {
                /** @var User|null $author */
                $author = $message->author;
                $authorName = $author->name ?? 'User';
                $sdkMessages[] = new UserMessage("[{$authorName}]: {$message->content}");
            }
        }

        // 3. Check if compaction is needed (dispatch async)
        if ($this->compactionService->needsCompaction($channelId, $agent, $sdkMessages, $systemPrompt)) {
            CompactConversationJob::dispatch($channelId, $agent);
        }

        return $sdkMessages;
    }
}

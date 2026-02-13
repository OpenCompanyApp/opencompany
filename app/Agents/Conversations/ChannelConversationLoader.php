<?php

namespace App\Agents\Conversations;

use App\Agents\Providers\DynamicProviderResolver;
use App\Jobs\CompactConversationJob;
use App\Models\ConversationSummary;
use App\Models\Message;
use App\Models\User;
use App\Services\Memory\ConversationCompactionService;
use App\Services\Memory\ModelContextRegistry;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

class ChannelConversationLoader
{
    /**
     * Absolute minimum and maximum messages to load regardless of context window.
     */
    private const MIN_MESSAGES = 10;
    private const MAX_MESSAGES = 100;

    /**
     * Average tokens per message (used for dynamic limit calculation).
     */
    private const AVG_TOKENS_PER_MESSAGE = 300;

    public function __construct(
        private ConversationCompactionService $compactionService,
        private ModelContextRegistry $contextRegistry,
        private DynamicProviderResolver $providerResolver,
    ) {}

    /**
     * Load recent messages from a channel as SDK message objects.
     *
     * @return iterable<UserMessage|AssistantMessage>
     */
    public function load(string $channelId, User $agent, ?string $systemPrompt = null): iterable
    {
        $limit = $this->resolveLimit($agent);
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

        // 2. Load messages (after last summarized message if applicable)
        $query = Message::where('channel_id', $channelId)
            ->orderBy('created_at', 'desc')
            ->take($limit);

        if ($summary?->last_message_id) {
            $lastMsg = Message::find($summary->last_message_id);
            if ($lastMsg) {
                $query->where('created_at', '>', $lastMsg->created_at);
            }
        }

        $messages = $query->get()->reverse()->values();

        foreach ($messages as $message) {
            if (empty($message->content)) {
                continue;
            }

            if ($message->author_id === $agent->id) {
                $sdkMessages[] = new AssistantMessage($message->content);
            } else {
                // Prefix with author name for multi-user context
                /** @var User|null $author */
                $author = $message->author;
                $authorName = $author->name ?? 'User';
                $content = "[{$authorName}]: {$message->content}";
                $sdkMessages[] = new UserMessage($content);
            }
        }

        // 3. Check if compaction is needed (dispatch async)
        if ($this->compactionService->needsCompaction($channelId, $agent, $sdkMessages, $systemPrompt)) {
            CompactConversationJob::dispatch($channelId, $agent);
        }

        return $sdkMessages;
    }

    /**
     * Calculate dynamic message limit based on the agent's model context window.
     */
    private function resolveLimit(User $agent): int
    {
        try {
            $resolved = $this->providerResolver->resolve($agent);
            $contextWindow = $this->contextRegistry->getContextWindow($resolved['model']);
        } catch (\Throwable) {
            return self::MIN_MESSAGES;
        }

        // Use 60% of context window for messages, estimate avg tokens per message
        $maxByContext = (int) floor(($contextWindow * 0.6) / self::AVG_TOKENS_PER_MESSAGE);

        return min(max($maxByContext, self::MIN_MESSAGES), self::MAX_MESSAGES);
    }
}

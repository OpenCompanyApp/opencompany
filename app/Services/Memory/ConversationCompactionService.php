<?php

namespace App\Services\Memory;

use App\Agents\Providers\DynamicProviderResolver;
use App\Models\AppSetting;
use App\Models\ConversationSummary;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Prism\Prism\Facades\Prism;

class ConversationCompactionService
{
    public function __construct(
        private ModelContextRegistry $contextRegistry,
        private DynamicProviderResolver $providerResolver,
    ) {}

    /**
     * Check if compaction is needed for a channel/agent pair.
     *
     * @param iterable<mixed> $messages
     */
    public function needsCompaction(string $channelId, User $agent, iterable $messages, ?string $systemPrompt = null): bool
    {
        $enabled = AppSetting::getValue('memory_compaction_enabled')
            ?? config('memory.compaction.enabled', true);
        if (!$enabled) {
            return false;
        }

        try {
            $resolved = $this->providerResolver->resolve($agent);
            $contextWindow = $this->contextRegistry->getContextWindow($resolved['model']);
        } catch (\Throwable) {
            return false;
        }

        $systemTokens = $systemPrompt
            ? $this->estimateTokenCount($systemPrompt)
            : config('memory.compaction.system_prompt_fallback_reserve', 10_000);
        $outputReserve = config('memory.compaction.output_reserve', 4_096);
        $available = $contextWindow - $systemTokens - $outputReserve;

        if ($available <= 0) {
            return false;
        }

        $messageTokens = $this->estimateMessagesTokens($messages);
        $safetyMargin = config('memory.compaction.safety_margin', 1.2);
        $threshold = $available * config('memory.compaction.threshold_ratio', 0.75);

        return ($messageTokens * $safetyMargin) > $threshold;
    }

    /**
     * Perform compaction: summarize older messages, return the updated summary.
     */
    public function compact(string $channelId, User $agent): ?ConversationSummary
    {
        $existing = ConversationSummary::where('channel_id', $channelId)
            ->where('agent_id', $agent->id)
            ->first();

        // Only load messages after the previous compaction point
        $query = Message::where('channel_id', $channelId)
            ->orderBy('created_at', 'asc');

        if ($existing?->last_message_id) {
            $lastMsg = Message::find($existing->last_message_id);
            if ($lastMsg) {
                $query->where('created_at', '>', $lastMsg->created_at);
            }
        }

        $messages = $query->get();

        if ($messages->count() < 5) {
            return null;
        }

        $keepRecentTokens = config('memory.compaction.keep_recent_tokens', 20_000);
        $minKeep = config('memory.compaction.min_keep_messages', 3);

        // Walk from newest to oldest, accumulating tokens until budget is exceeded
        $keptTokens = 0;
        $splitIndex = 0;
        for ($i = $messages->count() - 1; $i >= 0; $i--) {
            $msgTokens = $this->estimateTokenCount($messages[$i]->content ?? '');
            if ($keptTokens + $msgTokens > $keepRecentTokens
                && ($messages->count() - $i - 1) >= $minKeep) {
                $splitIndex = $i + 1;
                break;
            }
            $keptTokens += $msgTokens;
        }

        if ($splitIndex <= 0) {
            return null;
        }

        $toSummarize = $messages->slice(0, $splitIndex)->values();
        $previousSummary = $existing->summary ?? '';

        // Build SDK messages for summarization
        $sdkMessages = [];
        foreach ($toSummarize as $msg) {
            if (empty($msg->content)) {
                continue;
            }
            if ($msg->author_id === $agent->id) {
                $sdkMessages[] = new AssistantMessage($msg->content);
            } else {
                $authorName = $msg->author->name ?? 'User';
                $sdkMessages[] = new UserMessage("[{$authorName}]: {$msg->content}");
            }
        }

        $summaryText = $this->summarize($sdkMessages, $previousSummary);
        $tokensBefore = $this->estimateMessagesTokens($sdkMessages);

        $summary = ConversationSummary::updateOrCreate(
            ['channel_id' => $channelId, 'agent_id' => $agent->id],
            ['workspace_id' => $agent->workspace_id ?? workspace()->id,
                'summary' => $summaryText,
                'tokens_before' => $tokensBefore,
                'tokens_after' => $this->estimateTokenCount($summaryText),
                'compaction_count' => ($existing->compaction_count ?? 0) + 1,
                'flush_count' => 0, // Reset for new compaction cycle
                'messages_summarized' => ($existing->messages_summarized ?? 0) + count($sdkMessages),
                'last_message_id' => $toSummarize->last()->id ?? $existing->last_message_id,
            ]
        );

        Log::info('Conversation compacted', [
            'channel_id' => $channelId,
            'agent' => $agent->name,
            'messages_summarized' => count($sdkMessages),
            'tokens_before' => $tokensBefore,
            'tokens_after' => $summary->tokens_after,
            'compaction_count' => $summary->compaction_count,
        ]);

        return $summary;
    }

    /**
     * Summarize messages using an LLM call.
     *
     * @param array<int, AssistantMessage|UserMessage> $messages
     */
    private function summarize(array $messages, string $previousSummary): string
    {
        $prompt = "You are summarizing a conversation for an AI agent's context window.\n\n";

        if ($previousSummary) {
            $prompt .= "Previous summary of even older messages:\n{$previousSummary}\n\n";
        }

        $prompt .= "Messages to summarize:\n";
        foreach ($messages as $msg) {
            $role = $msg instanceof AssistantMessage ? 'assistant' : 'user';
            $content = $msg->content ?? '';
            $prompt .= "[{$role}]: {$content}\n";
        }

        $prompt .= "\nCreate a concise summary that captures:\n";
        $prompt .= "- Key topics discussed\n- Decisions made\n- Action items\n- Important context\n";
        $prompt .= "- User preferences expressed\n\n";
        $prompt .= "Be factual and specific. Preserve names, dates, and technical details.";

        [$provider, $model] = AppSetting::resolveProviderModel(
            'memory_summary_model', 'memory.compaction.summary_model'
        );

        try {
            $workspace = app('currentWorkspace');
            if ($workspace) {
                $this->providerResolver->setWorkspaceId($workspace->id);
            }
            $resolved = $this->providerResolver->resolveFromParts($provider, $model);

            $response = Prism::text()
                ->using($resolved['provider'], $resolved['model'])
                ->withMaxTokens(config('memory.compaction.summary_max_tokens', 2_000))
                ->withPrompt($prompt)
                ->asText();

            return $response->text;
        } catch (\Throwable $e) {
            Log::error('Conversation summarization failed', ['error' => $e->getMessage()]);
            return $previousSummary ?: '[Summary generation failed]';
        }
    }

    /**
     * Estimate token count for a collection of SDK messages.
     *
     * @param iterable<mixed> $messages
     */
    private function estimateMessagesTokens(iterable $messages): int
    {
        $total = 0;
        foreach ($messages as $msg) {
            $content = $msg->content ?? '';
            $total += $this->estimateTokenCount($content);
        }
        return $total;
    }

    /**
     * Estimate token count for a string.
     */
    public function estimateTokenCount(string $text): int
    {
        return TokenEstimator::estimate($text);
    }
}

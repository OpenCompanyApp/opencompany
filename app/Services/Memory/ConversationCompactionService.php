<?php

namespace App\Services\Memory;

use App\Agents\Providers\DynamicProviderResolver;
use App\Models\AppSetting;
use App\Models\ConversationSummary;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Prism\Prism\Facades\Prism;

class ConversationCompactionService
{
    public function __construct(
        private ContextBudget $contextBudget,
        private DynamicProviderResolver $providerResolver,
        private CompactionMemoryExtractor $memoryExtractor,
        private AgentDocumentService $documentService,
        private DocumentIndexingService $documentIndexingService,
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
            if ($this->isCircuitOpen($channelId, $agent)) {
                return false;
            }

            $budget = $this->contextBudget->snapshotForAgent($agent, $messages, $systemPrompt);
        } catch (\Throwable) {
            return false;
        }

        return (bool) $budget['is_above_compaction'];
    }

    /**
     * Perform compaction: summarize older messages, return the updated summary.
     */
    public function compact(string $channelId, User $agent): ?ConversationSummary
    {
        $existing = ConversationSummary::where('channel_id', $channelId)
            ->where('agent_id', $agent->id)
            ->first();

        if ($existing?->compaction_circuit_open_until?->isFuture()) {
            Log::warning('Skipping compaction while circuit is open', [
                'channel_id' => $channelId,
                'agent' => $agent->name,
                'open_until' => $existing->compaction_circuit_open_until?->toIso8601String(),
            ]);

            return null;
        }

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

        $plan = $this->buildPlan($messages);
        if ($plan === null) {
            return null;
        }

        $previousSummary = $existing->summary ?? '';

        // Build SDK messages for summarization
        $sdkMessages = [];
        foreach ($plan->messagesToSummarize as $msg) {
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

        try {
            $summaryText = $this->summarize($sdkMessages, $previousSummary, $plan);
        } catch (\Throwable $e) {
            $this->recordFailure($channelId, $agent, $existing, $e);

            Log::error('Conversation summarization failed', [
                'channel_id' => $channelId,
                'agent' => $agent->name,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $tokensBefore = $plan->tokensToSummarize;

        $summary = ConversationSummary::updateOrCreate(
            ['channel_id' => $channelId, 'agent_id' => $agent->id],
            ['workspace_id' => $agent->workspace_id ?? workspace()->id,
                'summary' => $summaryText,
                'tokens_before' => $tokensBefore,
                'tokens_after' => $this->estimateTokenCount($summaryText),
                'compaction_count' => ($existing->compaction_count ?? 0) + 1,
                'flush_count' => 0, // Reset for new compaction cycle
                'compaction_failure_count' => 0,
                'last_compaction_failed_at' => null,
                'compaction_circuit_open_until' => null,
                'last_compaction_error' => null,
                'messages_summarized' => ($existing->messages_summarized ?? 0) + count($sdkMessages),
                'last_message_id' => $plan->lastSummarizedMessageId() ?? $existing->last_message_id,
            ]
        );

        $this->extractDurableMemories($agent, $summaryText);

        Log::info('Conversation compacted', [
            'channel_id' => $channelId,
            'agent' => $agent->name,
            'messages_summarized' => count($sdkMessages),
            'tokens_before' => $tokensBefore,
            'tokens_after' => $summary->tokens_after,
            'compaction_count' => $summary->compaction_count,
            'split_index' => $plan->splitIndex,
            'tokens_kept' => $plan->tokensToKeep,
        ]);

        return $summary;
    }

    /**
     * Summarize messages using an LLM call.
     *
     * @param array<int, AssistantMessage|UserMessage> $messages
     */
    private function summarize(array $messages, string $previousSummary, CompactionPlan $plan): string
    {
        $prompt = "You are summarizing older OpenCompany conversation history for later retrieval.\n\n";

        if ($previousSummary) {
            $prompt .= "Previous summary of even older messages:\n{$previousSummary}\n\n";
        }

        $prompt .= "Compaction plan:\n";
        $prompt .= "- Messages being summarized: {$plan->messagesToSummarize->count()}\n";
        $prompt .= "- Messages kept verbatim after the split: {$plan->messagesToKeep->count()}\n";
        $prompt .= "- Tokens kept verbatim: {$plan->tokensToKeep}\n\n";

        $prompt .= "Messages to summarize:\n";
        foreach ($messages as $msg) {
            $role = $msg instanceof AssistantMessage ? 'assistant' : 'user';
            $content = $msg->content ?? '';
            $prompt .= "[{$role}]: {$content}\n";
        }

        $prompt .= "\nReturn markdown with these exact headings:\n";
        $prompt .= "## Objectives\n## Decisions\n## Open Work\n## Durable Facts\n## References\n\n";
        $prompt .= "Rules:\n";
        $prompt .= "- Use short bullet lists under every heading.\n";
        $prompt .= "- Include names, dates, tool outputs, IDs, and file paths when they matter.\n";
        $prompt .= "- Put reusable preferences, standing decisions, and durable facts under Durable Facts.\n";
        $prompt .= "- If a section has nothing important, write a single bullet: - none\n";
        $prompt .= "- Do not invent anything.\n";

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
            throw $e;
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Message>  $messages
     */
    private function buildPlan(\Illuminate\Support\Collection $messages): ?CompactionPlan
    {
        $keepRecentTokens = (int) config('memory.compaction.keep_recent_tokens', 20_000);
        $minKeep = (int) config('memory.compaction.min_keep_messages', 3);
        $keptTokens = 0;
        $splitIndex = 0;

        for ($i = $messages->count() - 1; $i >= 0; $i--) {
            $msgTokens = $this->estimateTokenCount((string) ($messages[$i]->content ?? ''));

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
        $toKeep = $messages->slice($splitIndex)->values();

        return new CompactionPlan(
            messagesToSummarize: $toSummarize,
            messagesToKeep: $toKeep,
            splitIndex: $splitIndex,
            tokensToSummarize: $this->estimateMessagesTokens($toSummarize),
            tokensToKeep: $this->estimateMessagesTokens($toKeep),
        );
    }

    private function isCircuitOpen(string $channelId, User $agent): bool
    {
        $summary = ConversationSummary::where('channel_id', $channelId)
            ->where('agent_id', $agent->id)
            ->first();

        return $summary?->compaction_circuit_open_until?->isFuture() ?? false;
    }

    private function recordFailure(string $channelId, User $agent, ?ConversationSummary $existing, \Throwable $error): void
    {
        $failureCount = ($existing?->compaction_failure_count ?? 0) + 1;
        $tripAfter = (int) config('memory.compaction.circuit_breaker.after_failures', 3);
        $cooldownMinutes = (int) config('memory.compaction.circuit_breaker.cooldown_minutes', 30);

        ConversationSummary::updateOrCreate(
            ['channel_id' => $channelId, 'agent_id' => $agent->id],
            [
                'workspace_id' => $agent->workspace_id ?? workspace()->id,
                'summary' => $existing->summary ?? '',
                'tokens_before' => $existing->tokens_before ?? 0,
                'tokens_after' => $existing->tokens_after ?? 0,
                'compaction_count' => $existing->compaction_count ?? 0,
                'flush_count' => $existing->flush_count ?? 0,
                'messages_summarized' => $existing->messages_summarized ?? 0,
                'last_message_id' => $existing?->last_message_id,
                'compaction_failure_count' => $failureCount,
                'last_compaction_failed_at' => now(),
                'compaction_circuit_open_until' => $failureCount >= $tripAfter
                    ? now()->addMinutes($cooldownMinutes)
                    : null,
                'last_compaction_error' => Str::limit($error->getMessage(), 4_000),
            ]
        );
    }

    private function extractDurableMemories(User $agent, string $summary): void
    {
        if (! config('memory.compaction.memory_extraction.enabled', true)) {
            return;
        }

        $items = array_slice(
            $this->memoryExtractor->extract($summary),
            0,
            (int) config('memory.compaction.memory_extraction.max_items', 8),
        );

        if ($items === []) {
            return;
        }

        $entry = "### [compaction] " . now()->format('H:i') . "\n\n";
        $entry .= implode("\n", array_map(
            fn (string $item): string => "- {$item}",
            $items,
        ));

        try {
            $document = $this->documentService->createMemoryLog($agent, $entry);

            if ($document !== null) {
                $this->documentIndexingService->index($document, 'memory', $agent->id);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to persist extracted compaction memories', [
                'agent' => $agent->name,
                'error' => $e->getMessage(),
            ]);
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

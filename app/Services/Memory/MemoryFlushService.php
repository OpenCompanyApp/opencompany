<?php

namespace App\Services\Memory;

use App\Agents\OpenCompanyAgent;
use App\Agents\Providers\DynamicProviderResolver;
use App\Models\ConversationSummary;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MemoryFlushService
{
    public function __construct(
        private ConversationCompactionService $compactionService,
        private ModelContextRegistry $contextRegistry,
        private DynamicProviderResolver $providerResolver,
    ) {}

    /**
     * Check if a memory flush should be triggered.
     *
     * Flush happens when we're within the "soft zone" — close to the compaction
     * threshold but not yet exceeding it — AND we haven't already flushed for
     * this compaction cycle.
     *
     * @param  iterable<mixed>  $messages
     */
    public function shouldFlush(string $channelId, User $agent, iterable $messages, ?string $systemPrompt = null): bool
    {
        if (! config('memory.memory_flush.enabled', true)) {
            return false;
        }

        // Check if we've already flushed this cycle
        $summary = ConversationSummary::where('channel_id', $channelId)
            ->where('agent_id', $agent->id)
            ->first();

        $maxFlushes = config('memory.memory_flush.max_flushes_per_cycle', 1);
        if ($summary && $summary->flush_count >= $maxFlushes) {
            return false;
        }

        // Resolve model context window
        try {
            $resolved = $this->providerResolver->resolve($agent);
            $contextWindow = $this->contextRegistry->getContextWindow($resolved['model']);
        } catch (\Throwable) {
            return false;
        }

        // Calculate available context (same logic as compaction)
        $systemTokens = $systemPrompt
            ? $this->compactionService->estimateTokenCount($systemPrompt)
            : config('memory.compaction.system_prompt_fallback_reserve', 10_000);
        $outputReserve = config('memory.compaction.output_reserve', 4_096);
        $available = $contextWindow - $systemTokens - $outputReserve;

        if ($available <= 0) {
            return false;
        }

        // Estimate message tokens with safety margin
        $messageTokens = 0;
        foreach ($messages as $msg) {
            $content = $msg->content ?? '';
            $messageTokens += $this->compactionService->estimateTokenCount($content);
        }

        $safetyMargin = config('memory.compaction.safety_margin', 1.2);
        $adjustedTokens = (int) ($messageTokens * $safetyMargin);
        $compactionThreshold = (int) ($available * config('memory.compaction.threshold_ratio', 0.75));
        $softThresholdTokens = config('memory.memory_flush.soft_threshold_tokens', 4000);
        $softZoneStart = $compactionThreshold - $softThresholdTokens;

        // Flush when context exceeds the soft zone (including when compaction is already needed)
        return $adjustedTokens > $softZoneStart;
    }

    /**
     * Execute a silent memory flush.
     *
     * Runs the agent with a flush prompt instructing it to save important
     * memories before compaction. The agent's text response is discarded —
     * only tool calls (save_memory) produce side effects.
     */
    public function flush(string $channelId, User $agent): void
    {
        $agentInstance = OpenCompanyAgent::for($agent, $channelId);
        $agentInstance->prompt($this->buildFlushPrompt());

        // Increment flush count (create summary record if needed)
        $summary = ConversationSummary::firstOrCreate(
            ['channel_id' => $channelId, 'agent_id' => $agent->id],
            ['summary' => '']
        );
        $summary->increment('flush_count');

        Log::info('Memory flush completed', [
            'agent' => $agent->name,
            'channel' => $channelId,
        ]);
    }

    /**
     * Build the prompt that instructs the agent to save important memories.
     */
    private function buildFlushPrompt(): string
    {
        return <<<'PROMPT'
Pre-compaction memory flush. Your conversation context is about to be compacted (older messages will be summarized and compressed).

Review the conversation for durable context worth preserving. Use save_memory (target: "log") to save important observations, decisions, preferences, or learnings to your daily log before they are compressed.

Only save to target: "core" if you discovered truly high-value permanent facts (user preferences, key decisions) that should always be in your system prompt.

If nothing needs saving, respond with exactly: [FLUSH_COMPLETE]
PROMPT;
    }
}

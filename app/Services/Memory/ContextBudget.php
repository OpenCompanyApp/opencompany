<?php

namespace App\Services\Memory;

use App\Agents\Providers\DynamicProviderResolver;
use App\Models\User;

class ContextBudget
{
    public function __construct(
        private ModelContextRegistry $contextRegistry,
        private DynamicProviderResolver $providerResolver,
    ) {}

    /**
     * @param  iterable<mixed>  $messages
     * @return array<string, int|float|bool|string>
     */
    public function snapshotForAgent(User $agent, iterable $messages, ?string $systemPrompt = null): array
    {
        $resolved = $this->providerResolver->resolve($agent);

        return $this->snapshot(
            $resolved['provider'],
            $resolved['model'],
            $this->estimateMessagesTokens($messages),
            $systemPrompt,
        );
    }

    /**
     * @return array<string, int|float|bool|string>
     */
    public function snapshot(
        string $provider,
        string $model,
        int $messageTokens,
        ?string $systemPrompt = null,
    ): array {
        $contextWindow = $this->contextRegistry->getContextWindow($model, $provider);
        $systemTokens = $systemPrompt !== null
            ? TokenEstimator::estimate($systemPrompt)
            : config('memory.compaction.system_prompt_fallback_reserve', 10_000);
        $outputReserve = (int) config('memory.compaction.output_reserve', 4_096);
        $effectiveWindow = max(1, $contextWindow - $systemTokens - $outputReserve);
        $safetyMargin = (float) config('memory.compaction.safety_margin', 1.2);
        $adjustedMessageTokens = (int) ceil($messageTokens * $safetyMargin);
        $warningThreshold = (int) floor($effectiveWindow * (float) config('memory.budget.warning_ratio', 0.65));
        $compactionThreshold = (int) floor($effectiveWindow * (float) config('memory.compaction.threshold_ratio', 0.75));
        $flushThreshold = max(1, $compactionThreshold - (int) config('memory.memory_flush.soft_threshold_tokens', 4_000));
        $blockingThreshold = max(
            $compactionThreshold,
            $effectiveWindow - (int) config('memory.budget.blocking_margin_tokens', 1_024),
        );
        $percentLeft = max(0, (int) round((($effectiveWindow - $adjustedMessageTokens) / $effectiveWindow) * 100));

        return [
            'provider' => $provider,
            'model' => $model,
            'context_window' => $contextWindow,
            'system_tokens' => $systemTokens,
            'output_reserve' => $outputReserve,
            'effective_window' => $effectiveWindow,
            'raw_message_tokens' => $messageTokens,
            'adjusted_message_tokens' => $adjustedMessageTokens,
            'safety_margin' => $safetyMargin,
            'warning_threshold' => $warningThreshold,
            'flush_threshold' => $flushThreshold,
            'compaction_threshold' => $compactionThreshold,
            'blocking_threshold' => $blockingThreshold,
            'percent_left' => $percentLeft,
            'is_above_warning' => $adjustedMessageTokens >= $warningThreshold,
            'is_above_flush' => $adjustedMessageTokens >= $flushThreshold,
            'is_above_compaction' => $adjustedMessageTokens > $compactionThreshold,
            'is_at_blocking_limit' => $adjustedMessageTokens >= $blockingThreshold,
        ];
    }

    /**
     * @param  iterable<mixed>  $messages
     */
    public function estimateMessagesTokens(iterable $messages): int
    {
        $total = 0;

        foreach ($messages as $message) {
            $total += TokenEstimator::estimate((string) ($message->content ?? ''));
        }

        return $total;
    }
}

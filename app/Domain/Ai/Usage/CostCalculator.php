<?php

namespace App\Domain\Ai\Usage;

use App\Domain\Ai\Catalog\AiCatalog;
use Laravel\Ai\Responses\Data\Usage;

/**
 * Estimates model costs from catalog pricing snapshots.
 *
 * Provider billing remains authoritative. Estimates are used when a provider
 * does not return actual charges or while delayed billing reconciliation is
 * pending.
 */
class CostCalculator
{
    public function __construct(private AiCatalog $catalog) {}

    public function estimate(string $provider, string $model, Usage $usage): ?float
    {
        $pricing = $this->catalog->model($provider, $model)?->pricing;
        if ($pricing === null || $pricing->kind !== 'paid') {
            return $pricing?->kind === 'free' || $pricing?->kind === 'included' ? 0.0 : null;
        }

        if ($pricing->inputPerMillion === null || $pricing->outputPerMillion === null) {
            return null;
        }

        $cacheRead = $usage->cacheReadInputTokens;
        $cacheWrite = $usage->cacheWriteInputTokens;
        $regularInput = max(0, $usage->promptTokens - $cacheRead - $cacheWrite);

        $inputCost = $regularInput * $pricing->inputPerMillion / 1_000_000;
        $outputCost = $usage->completionTokens * $pricing->outputPerMillion / 1_000_000;
        $cacheReadCost = $cacheRead * ($pricing->cacheReadPerMillion ?? $pricing->inputPerMillion) / 1_000_000;
        $cacheWriteCost = $cacheWrite * ($pricing->cacheWritePerMillion ?? $pricing->inputPerMillion) / 1_000_000;

        return round($inputCost + $outputCost + $cacheReadCost + $cacheWriteCost, 8);
    }
}

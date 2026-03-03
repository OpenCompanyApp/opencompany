<?php

namespace App\Support;

use Carbon\Carbon;
use Laravel\Ai\Responses\AgentResponse;

class TokenMetrics
{
    /**
     * Build a standard token metrics array from an agent response.
     *
     * Used by AgentRespondJob, ExecuteAgentTaskJob, and RunAutomationJob
     * to compute consistent usage stats for task results.
     *
     * @return array<string, mixed>
     */
    public static function fromResponse(
        AgentResponse $response,
        ?Carbon $startedAt = null,
        ?Carbon $completedAt = null,
    ): array {
        $generationTimeMs = $startedAt && $completedAt
            ? $startedAt->diffInMilliseconds($completedAt)
            : null;

        $completionTokens = $response->usage->completionTokens;

        $tokensPerSecond = ($generationTimeMs && $generationTimeMs > 0 && $completionTokens > 0)
            ? round($completionTokens / ($generationTimeMs / 1000), 1)
            : null;

        $lastStep = $response->steps->last();

        return [
            'prompt_tokens' => $lastStep?->usage->promptTokens ?? $response->usage->promptTokens,
            'completion_tokens' => $lastStep?->usage->completionTokens ?? $response->usage->completionTokens,
            'total_prompt_tokens' => $response->usage->promptTokens,
            'total_completion_tokens' => $completionTokens,
            'cache_read_tokens' => $response->usage->cacheReadInputTokens,
            'cache_write_tokens' => $response->usage->cacheWriteInputTokens,
            'tool_calls_count' => collect($response->steps)->sum(fn ($step) => count($step->toolCalls)),
            'generation_time_ms' => $generationTimeMs,
            'tokens_per_second' => $tokensPerSecond,
        ];
    }
}

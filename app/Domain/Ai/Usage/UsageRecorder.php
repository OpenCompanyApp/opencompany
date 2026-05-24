<?php

namespace App\Domain\Ai\Usage;

use App\Jobs\ReconcileOpenRouterUsage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Responses\AgentResponse;

/**
 * Persists LLM usage into the normalized accounting ledger.
 *
 * Recording is intentionally best-effort for completed user workflows: failures
 * here must not retry or undo an already delivered agent response.
 */
class UsageRecorder
{
    public function __construct(
        private CostCalculator $costs,
        private OpenRouterGenerationStore $openRouterGenerations,
    ) {}

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function record(
        AgentResponse $response,
        Task $task,
        ?User $agent,
        string $purpose,
        ?string $requestedProvider = null,
        ?string $requestedModel = null,
        array $metrics = [],
        string $status = 'completed',
    ): ?LlmUsageEvent {
        if (! Schema::hasTable('llm_usage_events')) {
            return null;
        }

        try {
            $resolvedProvider = $response->meta->provider ?: $requestedProvider;
            $resolvedModel = $response->meta->model ?: $requestedModel;
            $openRouter = $resolvedProvider === 'openrouter' || $requestedProvider === 'openrouter'
                ? $this->openRouterGenerations->pullLatest()
                : null;
            $actualCost = $this->openRouterCost($openRouter);
            $estimatedCost = $resolvedProvider && $resolvedModel
                ? $this->costs->estimate($resolvedProvider, $resolvedModel, $response->usage)
                : null;

            $event = LlmUsageEvent::create([
                'workspace_id' => $task->workspace_id,
                'agent_id' => $agent?->id,
                'task_id' => $task->id,
                'purpose' => $purpose,
                'status' => $status,
                'requested_provider' => $requestedProvider,
                'requested_model' => $requestedModel,
                'resolved_provider' => $resolvedProvider,
                'resolved_model' => $resolvedModel,
                'provider_generation_id' => is_array($openRouter) ? (string) ($openRouter['id'] ?? '') ?: null : null,
                'prompt_tokens' => $response->usage->promptTokens,
                'completion_tokens' => $response->usage->completionTokens,
                'cache_read_tokens' => $response->usage->cacheReadInputTokens,
                'cache_write_tokens' => $response->usage->cacheWriteInputTokens,
                'reasoning_tokens' => $response->usage->reasoningTokens,
                'tool_calls_count' => (int) ($metrics['tool_calls_count'] ?? collect($response->steps)->sum(fn ($step) => count($step->toolCalls))),
                'generation_time_ms' => $metrics['generation_time_ms'] ?? null,
                'estimated_cost_usd' => $estimatedCost,
                'actual_cost_usd' => $actualCost,
                'cost_source' => $actualCost !== null ? 'openrouter_response' : ($estimatedCost !== null ? 'catalog_estimate' : 'unknown'),
                'raw_usage' => [
                    'usage' => $response->usage->toArray(),
                    'meta' => ['provider' => $response->meta->provider, 'model' => $response->meta->model],
                    'openrouter' => $openRouter,
                ],
                'occurred_at' => now(),
            ]);

            if ($event->resolved_provider === 'openrouter' && $event->provider_generation_id) {
                ReconcileOpenRouterUsage::dispatch($event->id)->delay(now()->addMinute());
            }

            return $event;
        } catch (\Throwable $e) {
            Log::warning('Failed to record LLM usage event', [
                'task' => $task->id,
                'provider' => $requestedProvider,
                'model' => $requestedModel,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function openRouterCost(?array $payload): ?float
    {
        if ($payload === null) {
            return null;
        }

        $cost = data_get($payload, 'usage.cost')
            ?? data_get($payload, 'usage.total_cost')
            ?? data_get($payload, 'cost');

        return is_numeric($cost) ? round((float) $cost, 8) : null;
    }
}

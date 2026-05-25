<?php

namespace App\Jobs;

use App\Domain\Ai\Usage\LlmUsageEvent;
use App\Models\IntegrationSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Refreshes one OpenRouter usage event with provider-reported billing details.
 *
 * Some OpenRouter charges are finalized after completion or vary by routed
 * upstream provider. This job treats OpenRouter's generation endpoint as the
 * authoritative source when a generation ID is available.
 */
class ReconcileOpenRouterUsage implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $usageEventId) {}

    public function handle(): void
    {
        $event = LlmUsageEvent::query()->find($this->usageEventId);
        if (! $event || $event->resolved_provider !== 'openrouter' || ! $event->provider_generation_id) {
            return;
        }

        $apiKey = IntegrationSetting::query()
            ->where('workspace_id', $event->workspace_id)
            ->where('integration_id', 'openrouter')
            ->where('enabled', true)
            ->default()
            ->first()?->getConfigValue('api_key')
            ?: config('ai.providers.openrouter.key')
            ?: config('ai.providers.openrouter.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return;
        }

        $payload = Http::baseUrl(config('ai.providers.openrouter.url', 'https://openrouter.ai/api/v1'))
            ->withToken($apiKey)
            ->timeout(20)
            ->throw()
            ->get('generation', ['id' => $event->provider_generation_id])
            ->json();

        $cost = data_get($payload, 'data.total_cost')
            ?? data_get($payload, 'data.usage.cost')
            ?? data_get($payload, 'total_cost')
            ?? data_get($payload, 'usage.cost');

        if (is_numeric($cost)) {
            $event->update([
                'actual_cost_usd' => round((float) $cost, 8),
                'cost_source' => 'openrouter_generation',
                'raw_usage' => array_merge($event->raw_usage ?? [], ['openrouter_generation' => $payload]),
            ]);
        }
    }
}

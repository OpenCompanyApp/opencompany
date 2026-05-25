<?php

namespace App\Http\Controllers\Api;

use App\Models\IntegrationWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public receiver for workspace-created integration webhooks.
 *
 * This endpoint is intentionally narrow: it verifies the per-webhook secret and
 * records receipt diagnostics. Downstream fan-out to agents, channels, or tasks
 * should be added as an explicit processor rather than hidden in this public
 * trust boundary.
 */
class IncomingIntegrationWebhookController
{
    public function __invoke(Request $request, string $webhook): Response
    {
        $record = IntegrationWebhook::query()->find($webhook);
        if (! $record || ! $record->enabled || ! $this->secretMatches($record, $request)) {
            return new Response('Unauthorized', 401);
        }

        app()->instance('currentWorkspace', $record->workspace);

        $record->forceFill([
            'last_triggered_at' => now(),
            'call_count' => $record->call_count + 1,
            'last_payload' => $request->json()->all() ?: $request->all(),
        ])->save();

        return new Response('', 202);
    }

    private function secretMatches(IntegrationWebhook $webhook, Request $request): bool
    {
        $secret = $request->header('X-Webhook-Secret') ?? $request->query('secret');

        return is_string($secret) && hash_equals($webhook->secret, $secret);
    }
}

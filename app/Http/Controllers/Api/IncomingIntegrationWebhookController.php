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
 *
 * Secret transport contract: the secret is accepted ONLY in a header, either
 * `X-Webhook-Secret: <secret>` or `Authorization: Bearer <secret>`. The old
 * `?secret=` query string is rejected on purpose: URLs land in reverse-proxy
 * access logs, Telescope request entries, and referrers, so query secrets
 * leak. Do not re-add a query fallback for convenience.
 *
 * Rate limiting is applied at the route (`throttle:integration-webhooks`).
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

    /**
     * Constant-time comparison of the presented header secret with the
     * stored one. An empty stored or presented secret never matches, so a
     * misconfigured webhook cannot become an unauthenticated endpoint.
     */
    private function secretMatches(IntegrationWebhook $webhook, Request $request): bool
    {
        $expected = $webhook->secret;
        $presented = $request->header('X-Webhook-Secret');
        if (! is_string($presented) || $presented === '') {
            $presented = $request->bearerToken();
        }

        return is_string($expected) && $expected !== ''
            && is_string($presented) && $presented !== ''
            && hash_equals($expected, $presented);
    }
}

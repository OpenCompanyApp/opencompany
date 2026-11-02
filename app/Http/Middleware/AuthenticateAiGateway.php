<?php

namespace App\Http\Middleware;

use App\Models\AiGatewayApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates OpenCompany AI Gateway requests with workspace API keys.
 *
 * Successful authentication binds the key's workspace as currentWorkspace so
 * provider resolution, model exposure, integration credentials, and usage
 * logging remain scoped exactly like first-party OpenCompany runtime calls.
 *
 * Edge hardening owned here:
 * - Per-IP auth-failure guard (config rate_limits.ai_gateway.auth_failures_per_minute).
 *   Too many missing/invalid/expired keys from one IP get 429 before any key
 *   lookup, which blunts key guessing and cheap DB-hammering.
 * - resolveKey() memoizes the bearer-token lookup on the request so the
 *   `throttle:ai-gateway` limiter (AppServiceProvider) can key by API key id
 *   without a second query. Laravel's middleware priority runs ThrottleRequests
 *   *before* this middleware, so the limiter cannot rely on handle() having
 *   run first.
 *
 * Not owned here: per-key request budgets (the named limiter) and gateway
 * protocol handling (Api\AiGateway controllers).
 */
class AuthenticateAiGateway
{
    /** Request attribute memo: ['hash' => sha256(token), 'key' => ?AiGatewayApiKey]. */
    public const KEY_ATTRIBUTE = 'opencompany.ai_gateway_key';

    public function handle(Request $request, Closure $next): Response
    {
        $failureKey = $this->failureKey($request);
        $maxFailures = max(1, (int) config('rate_limits.ai_gateway.auth_failures_per_minute', 20));

        // Invariant: the failure guard runs before any token lookup, so a
        // blocked IP cannot keep probing keys (valid ones included) until the
        // decay window passes.
        if (RateLimiter::tooManyAttempts($failureKey, $maxFailures)) {
            return $this->tooManyFailures(RateLimiter::availableIn($failureKey));
        }

        $token = $request->bearerToken();

        if (! $token) {
            return $this->failed($failureKey, 'Missing API key. Provide a Bearer token in the Authorization header.');
        }

        $key = self::resolveKey($request);

        if (! $key) {
            return $this->failed($failureKey, 'Invalid API key.');
        }

        if ($key->isExpired()) {
            return $this->failed($failureKey, 'API key has expired.');
        }

        if ($key->workspace) {
            app()->instance('currentWorkspace', $key->workspace);
        }

        $key->timestamps = false;
        $key->update(['last_used_at' => now()]);
        $key->timestamps = true;

        return $next($request);
    }

    /**
     * Resolve (and memoize on the request) the gateway key for its bearer token.
     *
     * Returns null when no bearer token is present or no key matches. Expiry
     * is *not* checked here: callers that authorize must call isExpired().
     * The limiter only uses the id to pick a bucket. The memo is a
     * server-side request attribute, so clients cannot inject it. It is
     * bound to the token hash, so a reused Request with a different token
     * never returns a stale key.
     */
    public static function resolveKey(Request $request): ?AiGatewayApiKey
    {
        $token = $request->bearerToken();
        if (! is_string($token) || $token === '') {
            return null;
        }

        $tokenHash = hash('sha256', $token);
        $memo = $request->attributes->get(self::KEY_ATTRIBUTE);
        if (is_array($memo) && hash_equals($memo['hash'], $tokenHash)) {
            return $memo['key'];
        }

        $key = AiGatewayApiKey::findByPlainText($token);
        $request->attributes->set(self::KEY_ATTRIBUTE, ['hash' => $tokenHash, 'key' => $key]);

        return $key;
    }

    private function failureKey(Request $request): string
    {
        return 'ai-gateway-auth-failures:'.$request->ip();
    }

    /**
     * Record one failed authentication for the caller's IP and return the 401.
     * Successful requests never clear the counter, so interleaving a valid key
     * cannot reset a guessing run.
     */
    private function failed(string $failureKey, string $message): Response
    {
        RateLimiter::hit($failureKey, 60);

        return $this->unauthorized($message);
    }

    private function tooManyFailures(int $retryAfter): Response
    {
        return response()->json([
            'error' => [
                'message' => 'Too many failed authentication attempts. Retry later.',
                'type' => 'rate_limit_error',
                'code' => 'rate_limit_exceeded',
            ],
        ], 429, ['Retry-After' => (string) max(1, $retryAfter)]);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'error' => [
                'message' => $message,
                'type' => 'invalid_request_error',
                'code' => 'invalid_api_key',
            ],
        ], 401);
    }
}

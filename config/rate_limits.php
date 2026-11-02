<?php

/*
|--------------------------------------------------------------------------
| Edge Rate Limits
|--------------------------------------------------------------------------
|
| Budgets for the named limiters registered in AppServiceProvider
| (`RateLimiter::for(...)`) and referenced from routes/api.php as
| `throttle:<name>`. Routes must never carry inline magic numbers: operators
| tune these through env vars without touching code.
|
| Every value is "requests per minute" for one limiter key. Keys are chosen
| per surface (see AppServiceProvider::configureRateLimiting()):
|
| - api                   authenticated user id, falling back to client IP
| - chat_webhooks         client IP + adapter (telegram, slack, ...)
| - integration_webhooks  client IP + webhook id
| - telegram_mini_app     client IP + claimed Telegram user, plus an IP ceiling
| - ai_gateway            gateway API key id, falling back to client IP
|
| IP-keyed limits are only meaningful when TRUSTED_PROXIES is set correctly
| behind a reverse proxy (see config/trustedproxy.php). Otherwise every
| client collapses into the proxy's single IP bucket.
|
*/

return [

    // First-party SPA/API traffic. The Vue app polls and fans out several
    // requests per page, so the per-user budget is deliberately roomy; the
    // guest budget only covers the few unauthenticated api routes
    // (invitation acceptance, failed-auth probes).
    'api' => [
        'per_minute' => (int) env('RATE_LIMIT_API_PER_MINUTE', 600),
        'guest_per_minute' => (int) env('RATE_LIMIT_API_GUEST_PER_MINUTE', 60),
    ],

    // Chat provider webhooks (POST /api/webhooks/chat/{adapter}). Telegram
    // delivers bursts from a small pool of shared IPs for *every* bot on this
    // instance, so this must stay generous. A 429 makes Telegram retry later
    // rather than drop the update, but sustained throttling still delays chat.
    'chat_webhooks' => [
        'per_minute' => (int) env('RATE_LIMIT_CHAT_WEBHOOKS_PER_MINUTE', 2400),
    ],

    // Workspace-created incoming integration webhooks (POST /api/webhooks/{id}).
    'integration_webhooks' => [
        'per_minute' => (int) env('RATE_LIMIT_INTEGRATION_WEBHOOKS_PER_MINUTE', 300),
    ],

    // Telegram Mini App data endpoints. The Telegram user id is read from
    // *unverified* init_data at limiter time, so it only narrows the bucket
    // within one IP; the per-IP ceiling is the actual abuse control.
    'telegram_mini_app' => [
        'per_user_per_minute' => (int) env('RATE_LIMIT_TELEGRAM_MINI_APP_PER_USER_PER_MINUTE', 120),
        'per_ip_per_minute' => (int) env('RATE_LIMIT_TELEGRAM_MINI_APP_PER_IP_PER_MINUTE', 600),
    ],

    // OpenAI-compatible AI Gateway. Each request can fan out into paid
    // provider calls, so the per-key budget is the cost guard.
    // auth_failures_per_minute is enforced inside AuthenticateAiGateway and
    // blocks key-guessing from one IP before any key lookup happens.
    'ai_gateway' => [
        'per_minute' => (int) env('RATE_LIMIT_AI_GATEWAY_PER_MINUTE', 120),
        'guest_per_minute' => (int) env('RATE_LIMIT_AI_GATEWAY_GUEST_PER_MINUTE', 60),
        'auth_failures_per_minute' => (int) env('RATE_LIMIT_AI_GATEWAY_AUTH_FAILURES_PER_MINUTE', 20),
    ],

];

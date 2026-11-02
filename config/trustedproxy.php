<?php

/*
|--------------------------------------------------------------------------
| Trusted Proxies
|--------------------------------------------------------------------------
|
| Laravel's TrustProxies middleware reads `trustedproxy.proxies` at request
| time when no list was hard-wired in bootstrap/app.php. We deliberately keep
| it here, not in bootstrap/app.php: the middleware callback in bootstrap runs
| before .env and config are loaded, and env() returns null under
| `config:cache`.
|
| Security contract:
| - Unset or empty TRUSTED_PROXIES trusts no proxy. X-Forwarded-For/-Proto/
|   -Host headers are ignored, and $request->ip() is the TCP peer. This is the
|   safe default: an untrusted client must not be able to spoof its IP and
|   evade IP-keyed rate limits or audit trails.
| - A comma-separated list (IPs or CIDRs, e.g. "10.0.0.0/8,172.16.0.0/12")
|   trusts only those peers. Use this behind Caddy/Traefik/nginx/a load
|   balancer.
| - "*" trusts whatever peer connects directly. Only set it when the app is
|   unreachable except through the proxy (e.g. a private Docker network).
|
| Breadcrumb for future agents: do not reintroduce `trustProxies(at: '*')` in
| bootstrap/app.php. That made every deployment trust spoofed forwarding
| headers by default.
|
*/

$trusted = trim((string) env('TRUSTED_PROXIES', ''));

return [

    // '' => [] (trust nothing). Laravel treats null as "auto-trust on
    // Forge/Vapor/Cloud hosts", so we return an explicit empty array to keep
    // the default deterministic. '*' is passed through verbatim. Lists stay a
    // string; the framework explodes them on commas.
    'proxies' => $trusted === '' ? [] : $trusted,

];

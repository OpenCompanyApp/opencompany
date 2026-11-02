<?php

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Invariant: no hard-wired proxy list here. TrustProxies (global
        // middleware) falls back to config('trustedproxy.proxies') at request
        // time, which is env-driven via TRUSTED_PROXIES and defaults to
        // trusting no proxy. This callback runs before config is loaded, so
        // an env()/config() lookup here would be wrong under config:cache.
        // Never restore `trustProxies(at: '*')`: it lets any client spoof
        // X-Forwarded-For and dodge IP-keyed rate limits.

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ], replace: [
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \App\Http\Middleware\ValidateCsrfToken::class,
        ]);

        $middleware->statefulApi();

        $middleware->alias([
            'resolve.workspace' => \App\Http\Middleware\ResolveWorkspace::class,
            'workspace.admin' => \App\Http\Middleware\EnsureWorkspaceAdmin::class,
        ]);

        RedirectIfAuthenticated::redirectUsing(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

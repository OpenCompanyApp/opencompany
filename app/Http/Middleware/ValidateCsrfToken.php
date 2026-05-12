<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as Middleware;
use Illuminate\Support\Str;

/**
 * CSRF middleware with a local-only ngrok login escape hatch.
 *
 * External providers and mobile devices sometimes hit the local app through an
 * ngrok hostname. In local development only, allow login POSTs from that host
 * while still issuing the XSRF cookie for subsequent Inertia requests.
 */
class ValidateCsrfToken extends Middleware
{
    public function handle($request, Closure $next)
    {
        if ($this->shouldSkipLocalNgrokLogin($request)) {
            return tap($next($request), function ($response) use ($request) {
                if ($this->shouldAddXsrfTokenCookie()) {
                    $this->addCookieToResponse($request, $response);
                }
            });
        }

        return parent::handle($request, $next);
    }

    private function shouldSkipLocalNgrokLogin($request): bool
    {
        if (! app()->isLocal() || ! $request->isMethod('POST') || ! $request->is('login')) {
            return false;
        }

        // Check both direct and forwarded hosts because ngrok traffic may arrive
        // behind a local proxy while app.url still points at opencompany.test.
        $hosts = array_filter([
            $request->getHost(),
            $request->headers->get('x-forwarded-host'),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ]);

        return collect($hosts)->contains(
            fn (string $host): bool => Str::endsWith($host, '.ngrok-free.dev')
        );
    }
}

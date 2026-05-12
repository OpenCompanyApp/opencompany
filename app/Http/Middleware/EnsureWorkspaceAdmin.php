<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects workspace administration routes.
 *
 * ResolveWorkspace must already have bound currentWorkspace; this middleware
 * only checks that the authenticated user is an admin member of that workspace.
 */
class EnsureWorkspaceAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $workspace = app('currentWorkspace');

        if (! $user || ! $workspace || ! $user->isWorkspaceAdmin($workspace)) {
            // API routes should receive a JSON 403, while browser routes can use
            // Laravel's normal abort handling.
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Admin access required.'], 403);
            }

            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}

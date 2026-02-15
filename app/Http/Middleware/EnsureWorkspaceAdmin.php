<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $workspace = app('currentWorkspace');

        if (! $user || ! $workspace || ! $user->isWorkspaceAdmin($workspace)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Admin access required.'], 403);
            }

            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}

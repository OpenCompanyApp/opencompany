<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        if ($user->isAgent()) {
            return $next($request);
        }

        $workspace = $this->resolve($request, $user);

        if (! $workspace) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No workspace found.'], 404);
            }

            return redirect()->route('setup');
        }

        // Verify membership
        if (! $user->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Not a member of this workspace.'], 403);
            }

            abort(403, 'Not a member of this workspace.');
        }

        // Bind workspace to the container
        app()->instance('currentWorkspace', $workspace);

        // Store in session for API calls that don't have the slug
        session(['current_workspace_id' => $workspace->id]);

        return $next($request);
    }

    protected function resolve(Request $request, $user): ?Workspace
    {
        // 1. URL path parameter (web routes)
        if ($slug = $request->route('workspace_slug')) {
            return Workspace::where('slug', $slug)->first();
        }

        // 2. X-Workspace-Id header (external API consumers)
        if ($headerId = $request->header('X-Workspace-Id')) {
            return Workspace::find($headerId);
        }

        // 3. Session (Inertia API calls)
        if ($sessionId = session('current_workspace_id')) {
            $ws = Workspace::find($sessionId);
            if ($ws) {
                return $ws;
            }
        }

        // 4. User's first workspace (fallback)
        return $user->workspaces()->first();
    }
}

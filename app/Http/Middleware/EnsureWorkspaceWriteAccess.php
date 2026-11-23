<?php

namespace App\Http\Middleware;

use App\Domain\Authorization\Application\AuthorizationService;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only enforcement for the `viewer` role (GIN-1197 fixed roles).
 *
 * Every state-changing API request (anything but GET, HEAD, OPTIONS) in a
 * workspace needs `workspace.write`, decided by AuthorizationService; a
 * viewer gets a 403 `role_read_only` before any controller runs. Endpoint
 * rules (channel membership, document sharing, agent management) still apply
 * on top: this guard only removes writes for read-only roles, it never
 * grants anything.
 *
 * Exempt: per-user read state and presence (marking a channel or DM read,
 * typing indicators, notification read flags, own presence). They change
 * only the caller's own view, not workspace content.
 *
 * Runs after ResolveWorkspace. Agents never authenticate here as API
 * sessions; they act through governed tools.
 */
class EnsureWorkspaceWriteAccess
{
    /** Route URIs (relative to /api) exempt from the write guard. */
    private const READ_STATE_ROUTES = [
        'api/channels/{id}/read',
        'api/channels/{id}/typing',
        'api/direct-messages/{id}/read',
        'api/dm/{userId}/read',
        'api/notifications/{id}',
        'api/notifications/mark-all-read',
        'api/users/{id}/presence',
    ];

    public function __construct(private readonly AuthorizationService $authorization) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;

        if ($request->isMethodSafe() || $user === null || $user->isAgent() || ! $workspace instanceof Workspace
            || in_array($request->route()?->uri(), self::READ_STATE_ROUTES, true)) {
            return $next($request);
        }

        $decision = $this->authorization->decide($user, 'workspace.write', $workspace, [
            'method' => $request->method(),
            'route' => (string) $request->route()?->uri(),
        ]);
        if (! $decision->allowed()) {
            return response()->json(['message' => $decision->reason, 'error' => $decision->reasonCode], 403);
        }

        return $next($request);
    }
}

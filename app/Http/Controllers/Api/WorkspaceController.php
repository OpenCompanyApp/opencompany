<?php

namespace App\Http\Controllers\Api;

use App\Domain\Collaboration\Application\ProvisionWorkspace;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Workspace setup and profile API.
 *
 * Creating a workspace establishes the initial tenant boundary: owner
 * membership, system automation agent, and the default general channel are
 * created together so subsequent middleware can bind currentWorkspace.
 */
class WorkspaceController extends Controller
{
    /**
     * Create a new workspace. Used during first-time setup and "create workspace" flow.
     */
    public function store(Request $request, ProvisionWorkspace $workspaces): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|alpha_dash|unique:workspaces,slug',
            'icon' => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:100',
        ]);

        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->create(
            owner: $user,
            name: $validated['name'],
            slug: $validated['slug'],
            icon: $validated['icon'] ?? 'ph:buildings',
            color: $validated['color'] ?? 'neutral',
        );

        // Store the new workspace in session so the next request binds it via
        // ResolveWorkspace without requiring manual workspace switching.
        session(['current_workspace_id' => $workspace->id]);

        return response()->json([
            'id' => $workspace->id,
            'slug' => $workspace->slug,
        ], 201);
    }

    public function show(): JsonResponse
    {
        $workspace = workspace();

        return response()->json([
            'workspace' => $workspace->load('owner'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $workspace = workspace();

        // Only admins can update (enforced by workspace.admin middleware on PATCH,
        // but we also check here for safety if route middleware changes)
        if (! $request->user()->isWorkspaceAdmin($workspace)) {
            return response()->json(['message' => 'Admin access required.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|alpha_dash|unique:workspaces,slug,'.$workspace->id,
            'icon' => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:100',
        ]);

        $workspace->update($validated);

        return response()->json(['workspace' => $workspace->fresh()]);
    }
}

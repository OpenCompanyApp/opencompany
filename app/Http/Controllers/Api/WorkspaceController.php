<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    /**
     * Create a new workspace. Used during first-time setup and "create workspace" flow.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|alpha_dash|unique:workspaces,slug',
            'icon' => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:100',
        ]);

        $user = $request->user();

        $workspace = Workspace::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'icon' => $validated['icon'] ?? 'ph:buildings',
            'color' => $validated['color'] ?? 'neutral',
            'owner_id' => $user->id,
        ]);

        // Add creator as admin
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        // Create system user for this workspace
        $systemUser = User::create([
            'id' => 'sys-'.Str::random(8),
            'name' => 'Automation',
            'type' => 'agent',
            'agent_type' => 'system',
            'status' => 'idle',
            'presence' => 'online',
            'workspace_id' => $workspace->id,
        ]);

        // Create #general channel
        $general = Channel::create([
            'id' => Str::uuid()->toString(),
            'name' => 'general',
            'type' => 'public',
            'description' => 'General discussion and announcements',
            'creator_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);

        // Add creator to #general
        ChannelMember::create([
            'channel_id' => $general->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);

        // Store workspace in session
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

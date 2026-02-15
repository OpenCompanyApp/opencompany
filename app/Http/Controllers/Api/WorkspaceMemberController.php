<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceMemberController extends Controller
{
    public function index(): JsonResponse
    {
        $workspace = workspace();

        $members = $workspace->members()
            ->withPivot('role', 'id')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->pivot->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'role' => $user->pivot->role,
            ]);

        $invitations = $workspace->invitations()
            ->whereNull('accepted_at')
            ->with('inviter:id,name')
            ->get();

        return response()->json([
            'members' => $members,
            'invitations' => $invitations,
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'role' => 'sometimes|in:admin,member',
        ]);

        $workspace = workspace();

        // Check if already a member
        if ($workspace->members()->where('email', $validated['email'])->exists()) {
            return response()->json(['message' => 'User is already a member.'], 422);
        }

        // Check for existing pending invite
        $existing = $workspace->invitations()
            ->where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'An invitation is already pending for this email.'], 422);
        }

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => $validated['email'],
            'role' => $validated['role'] ?? 'member',
            'token' => Str::random(64),
            'inviter_id' => $request->user()->id,
        ]);

        // TODO: Send invitation email

        return response()->json(['invitation' => $invitation], 201);
    }

    public function updateRole(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:admin,member',
        ]);

        $member = WorkspaceMember::where('workspace_id', workspace()->id)
            ->findOrFail($id);

        // Prevent demoting the workspace owner
        if ($member->user_id === workspace()->owner_id && $validated['role'] !== 'admin') {
            return response()->json(['message' => 'Cannot change the workspace owner\'s role.'], 422);
        }

        $member->update(['role' => $validated['role']]);

        return response()->json(['member' => $member]);
    }

    public function remove(string $id): JsonResponse
    {
        $member = WorkspaceMember::where('workspace_id', workspace()->id)
            ->findOrFail($id);

        // Prevent removing the workspace owner
        if ($member->user_id === workspace()->owner_id) {
            return response()->json(['message' => 'Cannot remove the workspace owner.'], 422);
        }

        $member->delete();

        return response()->json(null, 204);
    }

    public function resendInvite(string $id): JsonResponse
    {
        $invitation = WorkspaceInvitation::where('workspace_id', workspace()->id)
            ->whereNull('accepted_at')
            ->findOrFail($id);

        // TODO: Re-send invitation email

        return response()->json(['message' => 'Invitation resent.']);
    }

    public function cancelInvite(string $id): JsonResponse
    {
        $invitation = WorkspaceInvitation::where('workspace_id', workspace()->id)
            ->whereNull('accepted_at')
            ->findOrFail($id);

        $invitation->delete();

        return response()->json(null, 204);
    }
}

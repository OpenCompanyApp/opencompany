<?php

namespace App\Http\Controllers\Api;

use App\Domain\Authorization\Application\AuthorizationService;
use App\Http\Controllers\Controller;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

/**
 * Lists workspace members and (behind EnsureWorkspaceAdmin) invites, re-roles
 * and removes them in the active workspace.
 *
 * Roles are validated against WorkspaceMember::ROLES. The `approver` role is
 * a member who may additionally resolve approval requests; the decision itself
 * lives in ApprovalResolutionPolicy, not here. `viewer` is read-only.
 *
 * Authorization: `member.invite`, `member.update_role` and `member.remove`
 * are decided by AuthorizationService (owner protection lives in the engine:
 * the owner is never removed or demoted). Role changes and removals are
 * recorded in the authorization ledger with the before and after role.
 */
class WorkspaceMemberController extends Controller
{
    public function __construct(private readonly AuthorizationService $authorization) {}

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
            // Role allow-list is owned by WorkspaceMember::ROLES (admin, approver, member, viewer).
            'role' => ['sometimes', Rule::in(WorkspaceMember::ROLES)],
        ]);

        $workspace = workspace();
        $this->authorization->authorize($request->user(), 'member.invite', $workspace, ['role' => $validated['role'] ?? 'member']);

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
            'role' => ['required', Rule::in(WorkspaceMember::ROLES)],
        ]);

        $member = WorkspaceMember::where('workspace_id', workspace()->id)
            ->findOrFail($id);

        // Owner protection is an engine rule (`owner_protected`); the 422
        // keeps the response the settings UI already handles.
        $decision = $this->authorization->decide($request->user(), 'member.update_role', $member, ['role' => $validated['role']]);
        if ($decision->reasonCode === 'owner_protected') {
            return response()->json(['message' => 'Cannot change the workspace owner\'s role.', 'error' => 'owner_protected'], 422);
        }
        if (! $decision->allowed()) {
            throw new \App\Domain\Authorization\Application\AuthorizationDenied('member.update_role', $decision);
        }

        $before = $member->role;
        $member->update(['role' => $validated['role']]);
        $this->authorization->recordChange($request->user(), workspace(), 'member.role.change', ['type' => 'member', 'id' => (string) $member->id], [
            'user_id' => (string) $member->user_id,
            'before' => $before,
            'after' => $validated['role'],
        ]);

        return response()->json(['member' => $member]);
    }

    public function remove(Request $request, string $id): JsonResponse
    {
        $member = WorkspaceMember::where('workspace_id', workspace()->id)
            ->findOrFail($id);

        $decision = $this->authorization->decide($request->user(), 'member.remove', $member);
        if ($decision->reasonCode === 'owner_protected') {
            return response()->json(['message' => 'Cannot remove the workspace owner.', 'error' => 'owner_protected'], 422);
        }
        if (! $decision->allowed()) {
            throw new \App\Domain\Authorization\Application\AuthorizationDenied('member.remove', $decision);
        }

        $member->delete();
        $this->authorization->recordChange($request->user(), workspace(), 'member.remove', ['type' => 'member', 'id' => (string) $member->id], [
            'user_id' => (string) $member->user_id,
            'before' => $member->role,
        ]);

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

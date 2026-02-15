<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class InvitationController extends Controller
{
    /**
     * Accept an invitation. Handles both logged-in users and new account creation.
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = WorkspaceInvitation::where('token', $token)
            ->with('workspace')
            ->firstOrFail();

        if ($invitation->accepted_at) {
            return response()->json(['message' => 'Invitation has already been accepted.'], 422);
        }

        $user = $request->user();

        // If no logged-in user, create account from request data
        if (! $user) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);

            // Check if user with this email already exists
            $existing = User::where('email', $invitation->email)->first();
            if ($existing) {
                return response()->json([
                    'message' => 'An account with this email already exists. Please log in first.',
                ], 422);
            }

            $user = User::create([
                'id' => 'h'.Str::random(8),
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
                'type' => 'human',
                'email_verified_at' => now(),
            ]);

            app(\App\Services\HumanAvatarService::class)->generate($user);

            Auth::login($user);
        }

        // Check if already a member
        $alreadyMember = WorkspaceMember::where('workspace_id', $invitation->workspace_id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $alreadyMember) {
            WorkspaceMember::create([
                'workspace_id' => $invitation->workspace_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
            ]);
        }

        // Mark invitation as accepted
        $invitation->update(['accepted_at' => now()]);

        // Store workspace in session
        session(['current_workspace_id' => $invitation->workspace_id]);

        return response()->json([
            'workspace' => $invitation->workspace,
        ]);
    }
}

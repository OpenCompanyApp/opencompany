<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class SetupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $isLoggedIn = Auth::check();

        // If already logged in, only validate workspace name
        if ($isLoggedIn) {
            $request->validate([
                'workspace_name' => 'required|string|max:255',
            ]);
            $user = $request->user();
        } else {
            // Guard: only allow if no human users exist yet
            if (User::where('type', 'human')->exists()) {
                abort(403, 'Setup has already been completed.');
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'workspace_name' => 'required|string|max:255',
            ]);

            $user = User::create([
                'id' => 'h'.Str::random(8),
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'type' => 'human',
            ]);

            app(\App\Services\HumanAvatarService::class)->generate($user);

            event(new Registered($user));

            Auth::login($user);
        }

        // Create workspace
        $slug = Str::slug($request->workspace_name);

        $workspace = Workspace::create([
            'name' => $request->workspace_name,
            'slug' => $slug,
            'icon' => 'ph:buildings',
            'color' => 'neutral',
            'owner_id' => $user->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        // Create system user for this workspace
        User::create([
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

        ChannelMember::create([
            'channel_id' => $general->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);

        session(['current_workspace_id' => $workspace->id]);

        return redirect("/w/{$workspace->slug}");
    }
}

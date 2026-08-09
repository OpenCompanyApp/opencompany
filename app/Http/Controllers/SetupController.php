<?php

namespace App\Http\Controllers;

use App\Domain\Collaboration\Application\ProvisionWorkspace;
use App\Models\User;
use App\Services\HumanAvatarService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class SetupController extends Controller
{
    public function store(Request $request, ProvisionWorkspace $workspaces): RedirectResponse
    {
        $isLoggedIn = Auth::check();

        // If already logged in, only validate workspace name
        if ($isLoggedIn) {
            $request->validate([
                'workspace_name' => 'required|string|max:255',
            ]);
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
        }

        $slug = Str::slug($request->workspace_name);
        [$user, $workspace] = DB::transaction(function () use ($isLoggedIn, $request, $slug, $workspaces): array {
            /** @var User $user */
            $user = $isLoggedIn
                ? $request->user()
                : User::create([
                    'id' => 'h'.Str::random(8),
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => $request->password,
                    'type' => 'human',
                ]);

            $workspace = $workspaces->create(
                owner: $user,
                name: $request->workspace_name,
                slug: $slug,
            );

            return [$user, $workspace];
        });

        if (! $isLoggedIn) {
            // Non-database side effects run only after the complete tenant
            // record set commits successfully.
            app(HumanAvatarService::class)->generate($user);
            event(new Registered($user));
            Auth::login($user);
        }

        session(['current_workspace_id' => $workspace->id]);

        return redirect("/w/{$workspace->slug}");
    }
}

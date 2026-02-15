<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     * Only allowed during first-time setup (no workspaces exist yet).
     */
    public function create(): Response
    {
        // If any workspace exists, registration is invite-only — redirect to login
        if (Workspace::exists()) {
            return Inertia::render('Auth/Login', [
                'status' => 'Registration is invite-only. Please ask an admin for an invitation.',
            ]);
        }

        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     * Only allowed during first-time setup or from the setup page.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Only allow registration during first-time setup (no workspaces)
        // or when explicitly coming from the setup flow
        if (Workspace::exists() && ! $request->boolean('_setup')) {
            abort(403, 'Registration is invite-only.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'id' => 'h'.Str::random(8),
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password, // Hashed by model cast
            'type' => 'human',
        ]);

        app(\App\Services\HumanAvatarService::class)->generate($user);

        event(new Registered($user));

        Auth::login($user);

        // During setup, redirect to setup page to create workspace
        if ($request->boolean('_setup') || ! Workspace::exists()) {
            return redirect()->route('setup');
        }

        return redirect(route('home', absolute: false));
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_shows_login_when_workspace_exists(): void
    {
        // TestCase creates a workspace, so Workspace::exists() is true.
        // The controller renders the Login page with a status message instead of the Register page.
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_registration_is_forbidden_when_workspace_exists(): void
    {
        // With a workspace present, direct registration (without _setup flag) is forbidden.
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(403);
    }

    public function test_registration_screen_renders_when_no_workspaces_exist(): void
    {
        // Remove the workspace created by TestCase so registration is allowed.
        Workspace::query()->delete();

        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_new_users_can_register_during_setup(): void
    {
        // Remove all workspaces so the first-time setup flow is allowed.
        Workspace::query()->delete();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        // With no workspace, the controller redirects to the setup route.
        $response->assertRedirect(route('setup', absolute: false));
    }
}

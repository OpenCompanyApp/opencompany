<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Protects the authenticated landing contract after the Dashboard page was
 * deleted (docs/PRODUCT_DIRECTION.md "Nuclear decisions"): chat is the
 * workspace home, and the legacy workspace root and /dashboard URLs must keep
 * redirecting there so bookmarks never 404.
 */
class WorkspaceLandingRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_authenticated_member_to_workspace_chat(): void
    {
        $user = User::factory()->create(['type' => 'human']);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect("/w/{$this->workspace->slug}/chat");
    }

    public function test_legacy_workspace_root_and_dashboard_urls_redirect_to_chat(): void
    {
        $user = User::factory()->create(['type' => 'human']);
        $slug = $this->workspace->slug;

        // Old bookmarks: the bare workspace root used to render the Dashboard page.
        $this->actingAs($user)
            ->get("/w/{$slug}")
            ->assertRedirect("/w/{$slug}/chat");

        $this->actingAs($user)
            ->get("/w/{$slug}/dashboard")
            ->assertRedirect("/w/{$slug}/chat");
    }

    public function test_workload_page_is_gone(): void
    {
        $user = User::factory()->create(['type' => 'human']);

        $this->actingAs($user)
            ->get("/w/{$this->workspace->slug}/workload")
            ->assertNotFound();
    }
}

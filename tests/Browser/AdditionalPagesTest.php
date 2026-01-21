<?php

namespace Tests\Browser;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AdditionalPagesTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that the activity page displays.
     */
    public function test_activity_page_displays(): void
    {
        $user = User::factory()->create();
        Activity::factory()->count(5)->create(['actor_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/activity')
                ->assertSee('Activity');
        });
    }

    /**
     * Test that the settings page displays.
     */
    public function test_settings_page_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/settings')
                ->assertSee('Settings');
        });
    }

    /**
     * Test that the workload page displays.
     */
    public function test_workload_page_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/workload')
                ->assertSee('Workload');
        });
    }

    /**
     * Test that the organization page displays.
     */
    public function test_org_page_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/org')
                ->assertSee('Organization');
        });
    }

    /**
     * Test that the credits page displays.
     */
    public function test_credits_page_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/credits')
                ->assertSee('Credits');
        });
    }

    /**
     * Test that the automation page displays.
     */
    public function test_automation_page_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/automation')
                ->assertSee('Automation');
        });
    }

    /**
     * Test that welcome page is accessible without authentication.
     */
    public function test_welcome_page_public(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/welcome')
                ->assertPathIs('/welcome')
                // The welcome page should have login/register links
                ->assertSee('Log in');
        });
    }

    /**
     * Test that protected routes redirect to login.
     */
    public function test_protected_routes_redirect(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertPathIs('/login');

            $browser->visit('/chat')
                ->assertPathIs('/login');

            $browser->visit('/tasks')
                ->assertPathIs('/login');

            $browser->visit('/docs')
                ->assertPathIs('/login');

            $browser->visit('/approvals')
                ->assertPathIs('/login');

            $browser->visit('/settings')
                ->assertPathIs('/login');
        });
    }
}

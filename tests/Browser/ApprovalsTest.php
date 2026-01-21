<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ApprovalsTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that the approvals page displays.
     */
    public function test_approvals_page_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/approvals')
                ->pause(2000)
                ->assertSee('Approvals');
        });
    }

    /**
     * Test that description text displays.
     */
    public function test_page_description_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/approvals')
                ->pause(2000)
                ->assertSee('Review and manage pending requests from agents');
        });
    }

    /**
     * Test filter buttons are present.
     */
    public function test_filter_buttons_present(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/approvals')
                ->pause(2000)
                ->assertSee('All')
                ->assertSee('Pending')
                ->assertSee('Approved')
                ->assertSee('Rejected');
        });
    }

    /**
     * Test navigation to approvals from sidebar.
     */
    public function test_navigate_to_approvals_from_sidebar(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->clickLink('Approvals')
                ->pause(1000)
                ->assertPathIs('/approvals');
        });
    }

    /**
     * Test empty state displays when no pending approvals.
     */
    public function test_empty_state_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/approvals')
                ->pause(2000)
                // Default filter is 'pending', empty state should show
                ->assertSee('All caught up!');
        });
    }
}

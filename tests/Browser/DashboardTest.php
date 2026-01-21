<?php

namespace Tests\Browser;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DashboardTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that the dashboard page displays correctly.
     */
    public function test_dashboard_page_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->assertSee('Dashboard')
                ->assertSee('Welcome back');
        });
    }

    /**
     * Test that quick actions section displays.
     */
    public function test_quick_actions_display(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->assertSee('Quick Actions')
                ->assertSee('New Channel')
                ->assertSee('Spawn Agent')
                ->assertSee('Create Task')
                ->assertSee('New Document');
        });
    }

    /**
     * Test clicking New Channel quick action navigates to chat.
     */
    public function test_quick_action_new_channel(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->press('New Channel')
                ->pause(1500)
                ->assertPathIs('/chat');
        });
    }

    /**
     * Test clicking Create Task quick action navigates to tasks.
     */
    public function test_quick_action_create_task(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->press('Create Task')
                ->pause(1500)
                ->assertPathIs('/tasks');
        });
    }

    /**
     * Test clicking New Document quick action navigates to docs.
     */
    public function test_quick_action_new_document(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->assertSee('New Document');

            // Scroll down to make "New Document" button visible
            $browser->script('window.scrollBy(0, 300)');
            $browser->pause(500);

            // Simulate a proper mouse click event with mousedown, mouseup, and click
            $browser->script("
                const buttons = document.querySelectorAll('button');
                for (const btn of buttons) {
                    if (btn.textContent.includes('New Document') && btn.textContent.includes('Write')) {
                        const mouseDown = new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window });
                        const mouseUp = new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window });
                        const clickEvt = new MouseEvent('click', { bubbles: true, cancelable: true, view: window });
                        btn.dispatchEvent(mouseDown);
                        btn.dispatchEvent(mouseUp);
                        btn.dispatchEvent(clickEvt);
                        break;
                    }
                }
            ");

            $browser->pause(2000)
                ->assertPathIs('/docs');
        });
    }

    /**
     * Test that working agents section displays.
     */
    public function test_working_agents_section_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                // The component title is "Working Now" by default
                ->assertSee('Working Now');
        });
    }

    /**
     * Test that stats overview displays.
     */
    public function test_stats_overview_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(3000); // Give more time for API data to load

            // Scroll to the top to ensure stats are visible
            $browser->script('window.scrollTo(0, 0)');

            $browser->pause(500)
                ->assertSee('Agents Online')
                ->assertSee('Tasks Completed')
                ->assertSee('Messages');
        });
    }

    /**
     * Test that activity feed section displays.
     */
    public function test_activity_feed_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->assertSee('Recent Activity');
        });
    }

    /**
     * Test pending approvals banner displays when approvals exist.
     */
    public function test_pending_approvals_banner(): void
    {
        $user = User::factory()->create();
        $agent = User::factory()->agent()->create();

        ApprovalRequest::factory()->pending()->create([
            'requester_id' => $agent->id,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(3000)
                // The banner shows "X pending" when there are pending approvals
                ->assertSee('pending');
        });
    }

    /**
     * Test spawn agent modal opens.
     */
    public function test_spawn_agent_modal(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->assertSee('Spawn Agent');

            // Simulate a proper mouse click event with mousedown, mouseup, and click
            $browser->script("
                const buttons = document.querySelectorAll('button');
                for (const btn of buttons) {
                    if (btn.textContent.includes('Spawn Agent') && btn.textContent.includes('Deploy')) {
                        const mouseDown = new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window });
                        const mouseUp = new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window });
                        const clickEvt = new MouseEvent('click', { bubbles: true, cancelable: true, view: window });
                        btn.dispatchEvent(mouseDown);
                        btn.dispatchEvent(mouseUp);
                        btn.dispatchEvent(clickEvt);
                        break;
                    }
                }
            ");

            $browser->pause(1500)
                // Modal shows agent configuration form - check for visible content
                ->assertSee('Agent Name')
                ->assertSee('Behavior Mode');
        });
    }
}

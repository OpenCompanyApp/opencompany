<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class NavigationTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that sidebar navigation links are visible.
     */
    public function test_sidebar_navigation_links(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->assertSee('Dashboard')
                ->assertSee('Chat')
                ->assertSee('Tasks')
                ->assertSee('Activity')
                ->assertSee('Approvals')
                ->assertSee('Docs');
        });
    }

    /**
     * Test navigate to dashboard.
     */
    public function test_navigate_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/chat')
                ->pause(2000)
                ->clickLink('Dashboard')
                ->pause(1000)
                ->assertPathIs('/');
        });
    }

    /**
     * Test navigate to chat.
     */
    public function test_navigate_to_chat(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->clickLink('Chat')
                ->pause(1000)
                ->assertPathIs('/chat');
        });
    }

    /**
     * Test navigate to tasks.
     */
    public function test_navigate_to_tasks(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->clickLink('Tasks')
                ->pause(1000)
                ->assertPathIs('/tasks');
        });
    }

    /**
     * Test navigate to docs.
     */
    public function test_navigate_to_docs(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->clickLink('Docs')
                ->pause(1000)
                ->assertPathIs('/docs');
        });
    }

    /**
     * Test navigate to activity.
     */
    public function test_navigate_to_activity(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->clickLink('Activity')
                ->pause(1000)
                ->assertPathIs('/activity');
        });
    }

    /**
     * Test navigate to approvals.
     */
    public function test_navigate_to_approvals(): void
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
}

<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ChatTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that the chat page displays.
     */
    public function test_chat_page_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/chat')
                ->pause(2000)
                // Check for the chat page structure
                ->assertSee('Channels');
        });
    }

    /**
     * Test that empty state shows when no channel selected.
     */
    public function test_empty_state_when_no_channel(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/chat')
                ->pause(2000)
                // Should see the channels header
                ->assertSee('Channels')
                // And a way to browse channels
                ->assertSee('Browse all channels');
        });
    }

    /**
     * Test navigation to chat from sidebar.
     */
    public function test_navigate_to_chat_from_sidebar(): void
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
     * Test that the chat page is accessible.
     */
    public function test_chat_page_is_accessible(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/chat')
                ->assertPathIs('/chat')
                ->pause(2000)
                // Page should load without errors - check for main content
                ->assertSee('Channels');
        });
    }
}

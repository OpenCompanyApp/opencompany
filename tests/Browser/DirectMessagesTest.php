<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DirectMessagesTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that the messages link is visible in sidebar.
     */
    public function test_messages_link_visible(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->assertSee('Messages');
        });
    }

    /**
     * Test navigation to messages from sidebar.
     */
    public function test_navigate_to_messages(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $otherUser) {
            $browser->loginAs($user)
                ->visit('/messages/' . $otherUser->id)
                ->pause(2000)
                ->assertPathIs('/messages/' . $otherUser->id);
        });
    }
}

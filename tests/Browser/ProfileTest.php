<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProfileTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that the profile edit page displays.
     */
    public function test_profile_edit_page_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile')
                ->pause(2000)
                ->assertSee('Profile');
        });
    }

    /**
     * Test that name input is present.
     */
    public function test_name_input_present(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile')
                ->pause(2000)
                ->assertPresent('#name')
                ->assertInputValue('#name', 'Test User');
        });
    }

    /**
     * Test that email input is present.
     */
    public function test_email_input_present(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile')
                ->pause(2000)
                ->assertPresent('#email')
                ->assertInputValue('#email', 'test@example.com');
        });
    }

    /**
     * Test profile page is accessible.
     */
    public function test_profile_page_is_accessible(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile')
                ->assertPathIs('/profile')
                ->pause(2000)
                ->assertSee('Profile');
        });
    }
}

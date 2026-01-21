<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DocumentsTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that the docs page displays.
     */
    public function test_docs_page_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/docs')
                ->pause(2000)
                ->assertSee('Documents');
        });
    }

    /**
     * Test that search input is present.
     */
    public function test_search_input_present(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/docs')
                ->pause(2000)
                // The search placeholder text
                ->assertInputValue('input[placeholder="Search docs..."]', '');
        });
    }

    /**
     * Test navigation to docs from sidebar.
     */
    public function test_navigate_to_docs_from_sidebar(): void
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
     * Test docs page is accessible.
     */
    public function test_docs_page_is_accessible(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/docs')
                ->assertPathIs('/docs')
                ->pause(2000)
                ->assertSee('Documents');
        });
    }
}

<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TasksTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that the tasks page displays.
     */
    public function test_tasks_page_displays(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/tasks')
                ->pause(2000)
                ->assertSee('Tasks');
        });
    }

    /**
     * Test that task board columns display.
     */
    public function test_task_board_columns_display(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/tasks')
                ->pause(2000)
                ->assertSee('Backlog')
                ->assertSee('In Progress')
                ->assertSee('Done');
        });
    }

    /**
     * Test New Task button is present.
     */
    public function test_new_task_button_present(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/tasks')
                ->pause(2000)
                ->assertSee('New Task');
        });
    }

    /**
     * Test empty state shows when no tasks.
     */
    public function test_empty_state_when_no_tasks(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/tasks')
                ->pause(2000)
                ->assertSee('No tasks yet');
        });
    }

    /**
     * Test navigation to tasks from sidebar.
     */
    public function test_navigate_to_tasks_from_sidebar(): void
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
     * Test tasks page is accessible.
     */
    public function test_tasks_page_is_accessible(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/tasks')
                ->assertPathIs('/tasks')
                ->pause(2000)
                ->assertSee('Tasks');
        });
    }

    /**
     * Test search input is present.
     */
    public function test_search_input_present(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/tasks')
                ->pause(2000)
                // The search placeholder text
                ->assertInputValue('input[placeholder="Search tasks..."]', '');
        });
    }
}

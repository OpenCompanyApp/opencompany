<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ScreenshotTest extends DuskTestCase
{
    public function test_screenshot_all_pages(): void
    {
        $this->browse(function (Browser $browser) {
            $user = User::factory()->create();

            $browser->loginAs($user)
                ->visit('/dashboard')
                ->pause(1000)
                ->screenshot('01_dashboard')

                ->visit('/chat')
                ->pause(1000)
                ->screenshot('02_chat')

                ->visit('/tasks')
                ->pause(1000)
                ->screenshot('03_tasks')

                ->visit('/docs')
                ->pause(1000)
                ->screenshot('04_docs')

                ->visit('/activity')
                ->pause(1000)
                ->screenshot('05_activity')

                ->visit('/approvals')
                ->pause(1000)
                ->screenshot('06_approvals')

                ->visit('/settings')
                ->pause(1000)
                ->screenshot('07_settings')

                ->visit('/workload')
                ->pause(1000)
                ->screenshot('08_workload')

                ->visit('/org')
                ->pause(1000)
                ->screenshot('09_org')

                ->visit('/credits')
                ->pause(1000)
                ->screenshot('10_credits')

                ->visit('/automation')
                ->pause(1000)
                ->screenshot('11_automation')

                ->visit('/profile')
                ->pause(1000)
                ->screenshot('12_profile')

                ->visit('/messages')
                ->pause(1000)
                ->screenshot('13_messages');

            $user->delete();
        });
    }
}

<?php

namespace Tests\Browser;

use App\Models\IntegrationSetting;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class IntegrationsUiTest extends DuskTestCase
{

    public function test_aircall_runtime_package_can_be_configured_from_integrations_page(): void
    {
        [$user, $workspace] = $this->createAdminMember();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/w/browser/integrations')
                ->waitForText('Aircall', 15)
                ->assertDontSee('Catalog unavailable');

            $aircallCardText = $browser->script(<<<'JS'
                const card = document.querySelector('[data-test="integration-card"][data-integration-id="aircall"]');
                return card ? card.innerText : '';
            JS)[0] ?? '';

            $this->assertStringContainsString('Aircall', $aircallCardText);
            $this->assertStringNotContainsString('package not installed', strtolower($aircallCardText));

            $browser
                ->click('[data-test="integration-card"][data-integration-id="aircall"] [data-test="integration-card-action"]')
                ->waitFor('[data-test="dynamic-config-form"]', 10)
                ->assertSee('Configure Aircall')
                ->assertPresent('[data-test="integration-field-api_id"]')
                ->assertPresent('[data-test="integration-field-api_token"]')
                ->assertPresent('[data-test="integration-field-access_token"]')
                ->assertPresent('[data-test="integration-field-url"]');

            $browser->script(<<<'JS'
                for (const [selector, value] of [
                    ['[data-test="integration-field-api_id"]', 'browser-api-id'],
                    ['[data-test="integration-field-api_token"]', 'browser-api-token'],
                ]) {
                    const input = document.querySelector(selector);
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            JS);

            $browser
                ->click('[data-test="save-integration-config"]')
                ->waitUntilMissing('[data-test="dynamic-config-form"]', 10);
        });

        $setting = IntegrationSetting::query()
            ->where('workspace_id', $workspace->id)
            ->where('integration_id', 'aircall')
            ->where('account_alias', '')
            ->first();

        $this->assertNotNull($setting);
        $this->assertTrue($setting->enabled);
        $this->assertSame('browser-api-id', $setting->getConfigValue('api_id'));
        $this->assertSame('browser-api-token', $setting->getConfigValue('api_token'));
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createAdminMember(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'name' => 'Browser Workspace',
            'slug' => 'browser',
            'owner_id' => $user->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        return [$user, $workspace];
    }
}

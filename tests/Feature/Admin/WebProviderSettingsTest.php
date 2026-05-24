<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\SettingController;
use App\Models\AppSetting;
use App\Models\IntegrationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class WebProviderSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_provider_cards_are_available_in_integration_catalog(): void
    {
        $available = IntegrationSetting::getAvailableIntegrations();

        $this->assertArrayHasKey('web.tavily', $available);
        $this->assertSame('web-providers', $available['web.tavily']['category']);
        $this->assertArrayHasKey('web.zai', $available);
        $this->assertSame('web-providers', $available['web.zai']['category']);
    }

    public function test_web_settings_category_can_be_updated_with_known_keys_only(): void
    {
        $request = Request::create('/api/settings', 'PATCH', [
            'category' => 'web',
            'settings' => [
                'web_search_default_provider' => 'zai',
                'web_fetch_allow_external' => true,
                'unknown_key' => 'ignored',
            ],
        ]);

        app(SettingController::class)->update($request);

        $this->assertSame('zai', AppSetting::getValue('web_search_default_provider'));
        $this->assertTrue(AppSetting::getValue('web_fetch_allow_external'));
        $this->assertNull(AppSetting::getValue('unknown_key'));
    }

    public function test_web_configure_command_updates_settings_and_credentials(): void
    {
        $this->artisan('web:configure', [
            '--workspace' => $this->workspace->id,
            '--search-provider' => 'zai',
            '--fetch-provider' => 'direct',
            '--allow-external-fetch' => 'true',
            '--provider' => 'tavily',
            '--api-key' => 'stored-key',
        ])->assertSuccessful();

        $this->assertSame('zai', AppSetting::getValue('web_search_default_provider'));
        $this->assertTrue(AppSetting::getValue('web_fetch_allow_external'));
        $this->assertSame('stored-key', IntegrationSetting::query()
            ->where('workspace_id', $this->workspace->id)
            ->where('integration_id', 'web.tavily')
            ->first()
            ?->getConfigValue('api_key'));
    }
}

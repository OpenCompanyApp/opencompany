<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\User;
use App\Services\Integrations\IntegrationConfigResolver;
use App\Services\Integrations\IntegrationDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Protects the agent-facing integration tools that configure OpenCompany's
 * app-owned Telegram chat runtime.
 */
class TelegramWorkspaceToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_catalog_and_config_resolver_use_app_owned_telegram_descriptor(): void
    {
        $resolver = app(IntegrationConfigResolver::class);

        [$payload, $status] = $resolver->update(
            HttpRequest::create('/', 'POST', [
                'api_key' => 'telegram-token',
                'enabled' => true,
            ]),
            'telegram',
        );

        $this->assertSame(200, $status);
        $this->assertTrue($payload['configured']);

        $setting = IntegrationSetting::where('integration_id', 'telegram')->firstOrFail();
        $this->assertTrue($setting->hasValidConfig());
        $this->assertSame('telegram-token', $setting->getConfigValue('api_key'));
        $this->assertNull($setting->getConfigValue('access_token'));

        $config = $resolver->show('telegram');
        $schemaKeys = collect($config['configSchema'])->pluck('key')->all();
        $this->assertContains('api_key', $schemaKeys);
        $this->assertNotContains('access_token', $schemaKeys);

        $telegramEntries = collect(app(IntegrationDirectory::class)->all())
            ->filter(fn (array $entry) => ($entry['configId'] ?? null) === 'telegram')
            ->values();

        $this->assertCount(1, $telegramEntries);
        $this->assertSame('chat', $telegramEntries[0]['entryType']);
        $this->assertSame('static_config', $telegramEntries[0]['source']);
    }

}

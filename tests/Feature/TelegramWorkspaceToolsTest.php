<?php

namespace Tests\Feature;

use App\Agents\Tools\Workspace\GetIntegrationConfig;
use App\Agents\Tools\Workspace\GetIntegrationSetup;
use App\Agents\Tools\Workspace\TestIntegrationConnection;
use App\Agents\Tools\Workspace\UpdateIntegrationConfig;
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

    public function test_workspace_tools_use_app_owned_telegram_config_shape(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'workspace_id' => $this->workspace->id,
        ]);

        $update = new UpdateIntegrationConfig($agent);
        $response = $update->handle(new Request([
            'integrationId' => 'telegram',
            'api_key' => 'telegram-token',
            'defaultAgentId' => $agent->id,
            'allowedTelegramUsers' => ['111', '222'],
            'enabled' => true,
        ]));

        $setting = IntegrationSetting::where('integration_id', 'telegram')->firstOrFail();
        $this->assertStringContainsString("Integration 'Telegram' updated", $response);
        $this->assertSame('telegram-token', $setting->getConfigValue('api_key'));
        $this->assertNull($setting->getConfigValue('access_token'));
        $this->assertSame(['111', '222'], $setting->getConfigValue('allowed_telegram_users'));

        $config = json_decode((new GetIntegrationConfig)->handle(new Request([
            'integrationId' => 'telegram',
        ])), true);

        $this->assertSame('telegram', $config['id']);
        $this->assertSame($agent->id, $config['defaultAgentId']);
        $this->assertSame(['111', '222'], $config['allowedTelegramUsers']);
    }

    public function test_connection_tool_tests_opencompany_telegram_bot_token_not_package_provider(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'workspace_id' => $this->workspace->id,
        ]);

        IntegrationSetting::create([
            'id' => 'telegram-setting',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'telegram',
            'enabled' => true,
            'config' => ['api_key' => 'telegram-token'],
        ]);

        Http::fake([
            'https://api.telegram.org/bottelegram-token/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'first_name' => 'OpenCompany',
                    'username' => 'OC1212BOT',
                ],
            ]),
        ]);

        $response = (new TestIntegrationConnection($agent))->handle(new Request([
            'integrationId' => 'telegram',
        ]));

        $this->assertSame('Telegram connection OK. Bot: OpenCompany (@OC1212BOT)', $response);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/bottelegram-token/getMe');
    }

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

    public function test_setup_tool_describes_opencompany_owned_telegram_runtime(): void
    {
        config(['app.url' => 'https://example.ngrok-free.dev']);

        $agent = User::factory()->create([
            'type' => 'agent',
            'workspace_id' => $this->workspace->id,
        ]);

        $setup = json_decode((new GetIntegrationSetup($agent))->handle(new Request([
            'integrationId' => 'telegram',
        ])), true);

        $fieldKeys = collect($setup['fields'])->pluck('key')->all();

        $this->assertSame('telegram', $setup['id']);
        $this->assertSame('opencompany_chat', $setup['runtime']);
        $this->assertSame('chat', $setup['domain']);
        $this->assertSame('https://example.ngrok-free.dev/api/webhooks/chat/telegram', $setup['webhookUrl']);
        $this->assertContains('api_key', $fieldKeys);
        $this->assertContains('allowed_telegram_users', $fieldKeys);
        $this->assertNotContains('access_token', $fieldKeys);
        $this->assertNotContains('allowed_users', $fieldKeys);
    }
}

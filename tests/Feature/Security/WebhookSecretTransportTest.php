<?php

namespace Tests\Feature\Security;

use App\Models\IntegrationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Protects how public webhook endpoints accept shared secrets.
 *
 * Regressions covered:
 * - The Telegram chat webhook compared its secret with `===` and did not
 *   guard empty values. It must use constant-time comparison and never let
 *   an empty header or an unconfigured secret resolve a workspace.
 * - Generic chat adapters accept secrets only in headers: `?secret=` leaks
 *   into proxy access logs and Telescope.
 */
class WebhookSecretTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_secret_mismatch_is_rejected(): void
    {
        Queue::fake();
        $this->telegramSetting('telegram-secret');

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'not-the-secret')
            ->postJson('/api/webhooks/chat/telegram', ['update_id' => 1])
            ->assertUnauthorized();

        // Missing header must not fall through to any workspace either.
        $this->postJson('/api/webhooks/chat/telegram', ['update_id' => 2])
            ->assertUnauthorized();
    }

    public function test_telegram_secret_match_is_accepted(): void
    {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        $this->telegramSetting('telegram-secret');

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'telegram-secret')
            ->postJson('/api/webhooks/chat/telegram', ['update_id' => 3])
            ->assertOk();
    }

    // A bot whose webhook_secret was never configured must not be claimable
    // by any header value, including the empty string.
    public function test_telegram_setting_without_secret_never_matches(): void
    {
        Queue::fake();
        $this->telegramSetting('');

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', '')
            ->postJson('/api/webhooks/chat/telegram', ['update_id' => 4])
            ->assertUnauthorized();
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'anything')
            ->postJson('/api/webhooks/chat/telegram', ['update_id' => 5])
            ->assertUnauthorized();
    }

    // Generic chat adapters accept their shared secret only in a header.
    public function test_generic_chat_adapter_rejects_query_string_secret(): void
    {
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'google_chat',
            'account_alias' => '',
            'config' => ['webhook_secret' => 'gchat-secret'],
            'enabled' => true,
            'is_default' => true,
        ]);

        $this->postJson('/api/webhooks/chat/google_chat?secret=gchat-secret', [])
            ->assertUnauthorized();
    }

    private function telegramSetting(string $secret): IntegrationSetting
    {
        return IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'telegram',
            'account_alias' => '',
            'config' => [
                'api_key' => 'telegram-token',
                'webhook_secret' => $secret,
                'bot_user_id' => '999',
                'bot_username' => 'OpenCompanyBot',
            ],
            'enabled' => true,
            'is_default' => true,
        ]);
    }
}

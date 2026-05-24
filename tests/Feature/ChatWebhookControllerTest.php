<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Protects workspace resolution for unauthenticated external chat webhooks.
 */
class ChatWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_slack_team_id_alone_cannot_bind_a_workspace(): void
    {
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'slack',
            'account_alias' => '',
            'config' => [
                'team_id' => 'T123',
                'signing_secret' => 'slack-secret',
                'api_key' => 'xoxb-token',
            ],
            'enabled' => true,
            'is_default' => true,
        ]);

        $this->postJson('/api/webhooks/chat/slack', ['team_id' => 'T123'])
            ->assertUnauthorized();
    }

    public function test_discord_application_id_alone_cannot_bind_a_workspace(): void
    {
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'discord',
            'account_alias' => '',
            'config' => [
                'application_id' => 'app-123',
                'public_key' => str_repeat('a', 64),
                'api_key' => 'bot-token',
            ],
            'enabled' => true,
            'is_default' => true,
        ]);

        $this->postJson('/api/webhooks/chat/discord', ['application_id' => 'app-123'])
            ->assertUnauthorized();
    }

    public function test_teams_recipient_id_alone_cannot_bind_a_workspace(): void
    {
        IntegrationSetting::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'teams',
            'account_alias' => '',
            'config' => [
                'app_id' => 'teams-app',
                'app_password' => 'teams-secret',
            ],
            'enabled' => true,
            'is_default' => true,
        ]);

        $this->postJson('/api/webhooks/chat/teams', ['recipient' => ['id' => 'teams-app']])
            ->assertUnauthorized();
    }
}

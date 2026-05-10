<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationMultiAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_static_integration_config_is_saved_per_account_alias(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/integrations/slack/config', [
            'bot_token' => 'xoxb-default',
            'signing_secret' => 'default-secret',
            'enabled' => true,
        ])->assertOk();

        $this->actingAs($user)->putJson('/api/integrations/slack/config', [
            'account' => 'ops',
            'bot_token' => 'xoxb-ops',
            'signing_secret' => 'ops-secret',
            'enabled' => true,
        ])->assertOk();

        $default = IntegrationSetting::forWorkspace()
            ->where('integration_id', 'slack')
            ->where('account_alias', '')
            ->firstOrFail();

        $ops = IntegrationSetting::forWorkspace()
            ->where('integration_id', 'slack')
            ->where('account_alias', 'ops')
            ->firstOrFail();

        $this->assertSame('xoxb-default', $default->getConfigValue('bot_token'));
        $this->assertSame('xoxb-ops', $ops->getConfigValue('bot_token'));
        $this->assertTrue($default->is_default);
        $this->assertFalse($ops->is_default);
    }

    public function test_account_list_and_default_selection_use_aliases(): void
    {
        $user = User::factory()->create();

        IntegrationSetting::create([
            'id' => 'default-slack',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'slack',
            'account_alias' => '',
            'config' => ['api_key' => 'xoxb-default', 'signing_secret' => 'default-secret'],
            'enabled' => true,
            'is_default' => true,
        ]);

        IntegrationSetting::create([
            'id' => 'ops-slack',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'slack',
            'account_alias' => 'ops',
            'config' => ['api_key' => 'xoxb-ops', 'signing_secret' => 'ops-secret'],
            'enabled' => true,
            'is_default' => false,
        ]);

        $this->actingAs($user)
            ->postJson('/api/integrations/slack/accounts/ops/default')
            ->assertOk();

        $response = $this->actingAs($user)->getJson('/api/integrations/slack/accounts');

        $response->assertOk();
        $accounts = collect($response->json('accounts'));

        $this->assertTrue($accounts->firstWhere('alias', 'ops')['is_default']);
        $this->assertFalse($accounts->firstWhere('alias', '')['is_default']);
    }
}

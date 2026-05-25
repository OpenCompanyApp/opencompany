<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OpenCompany\Integrations\Google\GoogleClient;
use Tests\TestCase;

class IntegrationOAuthAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_oauth_stores_tokens_on_selected_account_alias(): void
    {
        $user = User::factory()->create();

        IntegrationSetting::create([
            'id' => 'default-google-calendar',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'google-calendar',
            'account_alias' => '',
            'config' => ['client_id' => 'client-default', 'client_secret' => 'secret-default'],
            'enabled' => true,
            'is_default' => true,
        ]);

        IntegrationSetting::create([
            'id' => 'ops-google-calendar',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'google-calendar',
            'account_alias' => 'ops',
            'config' => ['client_id' => 'client-ops', 'client_secret' => 'secret-ops'],
            'enabled' => true,
            'is_default' => false,
        ]);

        $authorize = $this->actingAs($user)
            ->get('/api/integrations/google/oauth/authorize?service=google_calendar&account=ops');

        $authorize->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        $this->assertSame('ops', session('google_oauth_account_alias'));
        $state = session('google_oauth_state');

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-ops',
                'refresh_token' => 'refresh-ops',
                'expires_in' => 3600,
            ]),
            'https://www.googleapis.com/oauth2/v2/userinfo' => Http::response([
                'email' => 'ops@example.com',
            ]),
        ]);

        $this->actingAs($user)
            ->get('/api/integrations/google/oauth/callback?state='.$state.'&code=auth-code')
            ->assertRedirect('/w/test/settings?tab=integrations');

        $default = IntegrationSetting::forWorkspace()
            ->where('integration_id', 'google-calendar')
            ->where('account_alias', '')
            ->firstOrFail();

        $ops = IntegrationSetting::forWorkspace()
            ->where('integration_id', 'google-calendar')
            ->where('account_alias', 'ops')
            ->firstOrFail();

        $this->assertNull($default->getConfigValue('access_token'));
        $this->assertSame('access-ops', $ops->getConfigValue('access_token'));
        $this->assertSame('refresh-ops', $ops->getConfigValue('refresh_token'));
        $this->assertSame('ops@example.com', $ops->getConfigValue('connected_email'));
    }

    public function test_ticktick_oauth_stores_tokens_on_selected_account_alias(): void
    {
        $user = User::factory()->create();

        IntegrationSetting::create([
            'id' => 'default-ticktick',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'ticktick',
            'account_alias' => '',
            'config' => ['client_id' => 'client-default', 'client_secret' => 'secret-default'],
            'enabled' => true,
            'is_default' => true,
        ]);

        IntegrationSetting::create([
            'id' => 'ops-ticktick',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'ticktick',
            'account_alias' => 'ops',
            'config' => ['client_id' => 'client-ops', 'client_secret' => 'secret-ops'],
            'enabled' => true,
            'is_default' => false,
        ]);

        $authorize = $this->actingAs($user)
            ->get('/api/integrations/ticktick/oauth/authorize?account=ops');

        $authorize->assertRedirectContains('https://ticktick.com/oauth/authorize');
        $this->assertSame('ops', session('ticktick_oauth_account_alias'));
        $state = session('ticktick_oauth_state');

        Http::fake([
            'https://ticktick.com/oauth/token' => Http::response([
                'access_token' => 'access-ops',
            ]),
        ]);

        $this->actingAs($user)
            ->get('/api/integrations/ticktick/oauth/callback?state='.$state.'&code=auth-code')
            ->assertRedirect('/w/test/settings?tab=integrations');

        $default = IntegrationSetting::forWorkspace()
            ->where('integration_id', 'ticktick')
            ->where('account_alias', '')
            ->firstOrFail();

        $ops = IntegrationSetting::forWorkspace()
            ->where('integration_id', 'ticktick')
            ->where('account_alias', 'ops')
            ->firstOrFail();

        $this->assertNull($default->getConfigValue('access_token'));
        $this->assertSame('access-ops', $ops->getConfigValue('access_token'));
    }

    public function test_google_token_refresh_persists_to_selected_account_alias(): void
    {
        IntegrationSetting::create([
            'id' => 'default-google-calendar',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'google-calendar',
            'account_alias' => '',
            'config' => ['access_token' => 'default-old'],
            'enabled' => true,
            'is_default' => true,
        ]);

        IntegrationSetting::create([
            'id' => 'ops-google-calendar',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'google-calendar',
            'account_alias' => 'ops',
            'config' => ['access_token' => 'ops-old', 'refresh_token' => 'refresh-ops'],
            'enabled' => true,
            'is_default' => false,
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ops-new',
                'expires_in' => 3600,
            ]),
            'https://example.test/*' => Http::response(['ok' => true]),
        ]);

        $client = new GoogleClient(
            clientId: 'client-ops',
            clientSecret: 'secret-ops',
            accessToken: 'ops-old',
            refreshToken: 'refresh-ops',
            expiresAt: time() - 60,
            integrationId: 'google-calendar',
            accountAlias: 'ops',
        );

        $this->assertSame(['ok' => true], $client->get('https://example.test/resource'));

        $default = IntegrationSetting::forWorkspace()
            ->where('integration_id', 'google-calendar')
            ->where('account_alias', '')
            ->firstOrFail();

        $ops = IntegrationSetting::forWorkspace()
            ->where('integration_id', 'google-calendar')
            ->where('account_alias', 'ops')
            ->firstOrFail();

        $this->assertSame('default-old', $default->getConfigValue('access_token'));
        $this->assertSame('ops-new', $ops->getConfigValue('access_token'));
    }
}

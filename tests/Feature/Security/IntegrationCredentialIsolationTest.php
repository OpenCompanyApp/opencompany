<?php

namespace Tests\Feature\Security;

use App\Models\IntegrationSetting;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;
use OpenCompany\Integrations\Google\GoogleClient;
use OpenCompany\Integrations\Plausible\Tools\PlausibleRealtimeVisitors;
use OpenCompany\IntegrationCore\Contracts\CredentialResolver;
use OpenCompany\Integrations\Klaviyo\KlaviyoService;
use Tests\TestCase;

/**
 * Roadmap Phase 0 P0 #12: integration packages register their API clients as
 * container singletons that read credentials once. In a long-running worker
 * the first workspace's key must never be reused for another workspace.
 */
class IntegrationCredentialIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function keyOf(KlaviyoService $service): string
    {
        return (string) (new \ReflectionProperty($service, 'apiKey'))->getValue($service);
    }

    private function setting(Workspace $workspace, string $key): void
    {
        IntegrationSetting::create([
            'id' => (string) Str::uuid(), 'workspace_id' => $workspace->id,
            'integration_id' => 'klaviyo', 'config' => ['api_key' => $key], 'enabled' => true,
        ]);
    }

    public function test_package_clients_resolve_with_the_current_workspace_credentials(): void
    {
        $other = Workspace::create(['name' => 'Other', 'slug' => 'other-creds']);
        $this->setting($this->workspace, 'key-workspace-a');
        $this->setting($other, 'key-workspace-b');

        app()->instance('currentWorkspace', $this->workspace);
        $first = app(KlaviyoService::class);

        // Same process (queue worker / Octane), next job in another workspace.
        app()->instance('currentWorkspace', $other);
        $second = app(KlaviyoService::class);

        $this->assertSame('key-workspace-a', $this->keyOf($first));
        $this->assertSame('key-workspace-b', $this->keyOf($second));
    }

    // The audited path (planning-session finding #1): a ToolProvider returns
    // `app(XService::class)` for the default account. Two workspaces, same
    // integration, sequential tool calls in ONE process (queue worker): each
    // outbound request must carry that workspace's own API key.
    public function test_sequential_default_account_tool_calls_use_each_workspaces_own_key(): void
    {
        Http::fake(['*' => Http::response('7')]);
        $other = Workspace::create(['name' => 'Other', 'slug' => 'other-plausible']);
        foreach ([[$this->workspace, 'plausible-key-a'], [$other, 'plausible-key-b']] as [$workspace, $key]) {
            IntegrationSetting::create([
                'id' => (string) Str::uuid(), 'workspace_id' => $workspace->id,
                'integration_id' => 'plausible', 'config' => ['api_key' => $key], 'enabled' => true,
            ]);
        }
        $provider = app(ToolProviderRegistry::class)->get('plausible');

        foreach ([$this->workspace, $other] as $workspace) {
            app()->instance('currentWorkspace', $workspace);
            $result = $provider->createTool(PlausibleRealtimeVisitors::class)->execute(['site_id' => 'example.com']);
            $this->assertTrue($result->succeeded());
        }

        $tokens = Http::recorded()->map(fn ($pair) => $pair[0]->header('Authorization')[0] ?? null)->all();
        $this->assertSame(['Bearer plausible-key-a', 'Bearer plausible-key-b'], $tokens);
    }

    // Every package binding must stay non-shared; a package added later that
    // registers a credential-bearing singleton is caught here.
    public function test_no_integration_package_binding_is_shared(): void
    {
        $shared = collect(app()->getBindings())
            ->filter(fn ($binding, $abstract) => ($binding['shared'] ?? false) && str_starts_with((string) $abstract, 'OpenCompany\\Integrations\\'))
            ->keys()->all();

        $this->assertSame([], $shared);
    }

    // Planning-session finding #4: a Google token refresh with no bound
    // workspace persisted through an unscoped query into whichever tenant's
    // row matched first. It must persist only into the bound workspace's row.
    public function test_google_token_refresh_never_writes_another_tenants_setting(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600])]);
        $other = Workspace::create(['name' => 'Other', 'slug' => 'other-google']);
        $rows = [];
        foreach ([[$this->workspace, 'token-a'], [$other, 'token-b']] as [$workspace, $token]) {
            $rows[] = IntegrationSetting::create([
                'id' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'integration_id' => 'google_drive',
                'config' => ['access_token' => $token, 'refresh_token' => 'r'], 'enabled' => true,
            ]);
        }
        $refresh = fn () => (new \ReflectionMethod(GoogleClient::class, 'refreshAccessToken'))
            ->invoke(new GoogleClient('id', 'secret', 'old', 'r', 0, 'google_drive'));

        app()->forgetInstance('currentWorkspace');
        $refresh();
        $this->assertSame('token-a', $rows[0]->fresh()->config['access_token']);
        $this->assertSame('token-b', $rows[1]->fresh()->config['access_token']);

        app()->instance('currentWorkspace', $other);
        $refresh();
        $this->assertSame('token-a', $rows[0]->fresh()->config['access_token']);
        $this->assertSame('fresh-token', $rows[1]->fresh()->config['access_token']);
    }

    public function test_without_a_bound_workspace_tenant_credentials_are_invisible(): void
    {
        $this->setting($this->workspace, 'tenant-secret');
        app()->forgetInstance('currentWorkspace');

        $this->assertNull(app(CredentialResolver::class)->get('klaviyo', 'api_key'));
        $this->assertSame([], app(CredentialResolver::class)->getAccounts('klaviyo'));
    }
}

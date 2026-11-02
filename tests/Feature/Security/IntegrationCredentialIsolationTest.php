<?php

namespace Tests\Feature\Security;

use App\Models\IntegrationSetting;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_without_a_bound_workspace_tenant_credentials_are_invisible(): void
    {
        $this->setting($this->workspace, 'tenant-secret');
        app()->forgetInstance('currentWorkspace');

        $this->assertNull(app(CredentialResolver::class)->get('klaviyo', 'api_key'));
        $this->assertSame([], app(CredentialResolver::class)->getAccounts('klaviyo'));
    }
}

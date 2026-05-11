<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\Workspace;
use App\Services\PrismServerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use OpenCompany\PrismRelay\Registry\RelayRegistry;
use Prism\Prism\Facades\PrismServer;
use Tests\TestCase;

class PrismServerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PrismServer::clearResolvedInstance('prism-server');
        app()->forgetInstance('prism-server');
    }

    public function test_prism_server_model_resolution_uses_request_workspace_context(): void
    {
        $registry = app(RelayRegistry::class);
        $providerName = 'z';
        $model = (string) ($registry->provider($providerName)['default_model'] ?? 'default');
        $brain = "{$providerName}:{$model}";
        $url = $registry->url($providerName);

        $otherWorkspace = Workspace::create([
            'name' => 'Other Workspace',
            'slug' => 'other',
        ]);

        $this->createIntegration($this->workspace, $providerName, [
            'api_key' => 'workspace-one-key',
            'url' => $url,
        ]);
        $this->createIntegration($otherWorkspace, $providerName, [
            'api_key' => 'workspace-two-key',
            'url' => $url,
        ]);
        $this->createIntegration($this->workspace, 'prism-server', [
            'enabled_models' => [$brain],
        ]);
        $this->createIntegration($otherWorkspace, 'prism-server', [
            'enabled_models' => [$brain],
        ]);

        app(PrismServerService::class)->registerModels();

        $entry = PrismServer::prisms()->sole('name', $brain);

        app()->instance('currentWorkspace', $otherWorkspace);
        $entry['prism']();

        $this->assertSame('workspace-two-key', config('prism.providers.z.api_key'));

        app()->instance('currentWorkspace', $this->workspace);
        $entry['prism']();

        $this->assertSame('workspace-one-key', config('prism.providers.z.api_key'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function createIntegration(Workspace $workspace, string $integrationId, array $config): void
    {
        IntegrationSetting::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'integration_id' => $integrationId,
            'enabled' => true,
            'is_default' => true,
            'account_alias' => '',
            'config' => $config,
        ]);
    }
}

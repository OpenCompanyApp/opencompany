<?php

namespace Tests\Feature\Code;

use App\Models\AgentPermission;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Services\CodeApiDocGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Planning-session finding #7: ~24k generated catalog entries have
 * `parameters: []`, which overrode the live schema and made ScriptBridge skip
 * argument validation. On the agent-discovery path the live schema wins.
 */
class CatalogParameterFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_catalog_schema_falls_back_to_the_live_tool_parameters(): void
    {
        $agent = User::factory()->agent()->create();
        IntegrationSetting::create([
            'id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id,
            'integration_id' => 'abuseipdb', 'config' => ['api_key' => 'test-key'], 'enabled' => true,
        ]);
        AgentPermission::create([
            'id' => (string) Str::uuid(), 'agent_id' => $agent->id, 'scope_type' => 'integration',
            'scope_key' => 'abuseipdb', 'permission' => 'allow', 'requires_approval' => false,
        ]);

        $docs = app(CodeApiDocGenerator::class);
        $functionMap = $docs->buildFunctionMap($agent);
        $path = array_search('abuseipdb_check', $functionMap, true);
        $this->assertIsString($path, 'abuseipdb_check must be discoverable for the enabled integration');

        $parameters = $docs->buildParameterMap($agent)[$path] ?? [];
        $this->assertNotSame([], $parameters, 'live parameters() must replace the empty catalog schema');
    }
}

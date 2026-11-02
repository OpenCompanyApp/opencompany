<?php

namespace Tests\Feature;

use App\Agents\OpenCompanyAgent;
use App\Agents\Runtime\AgentRun;
use App\Agents\Runtime\AgentRunFailed;
use App\Agents\Runtime\AgentRunOptions;
use App\Agents\Runtime\Context\AgentContextPipeline;
use App\Agents\Runtime\Permissions\OpenCompanyPermissionEvaluator;
use App\Agents\Runtime\Subagents\SubagentDependencyGraph;
use App\Agents\Runtime\Subagents\SubagentRun;
use App\Ai\Prompting\SystemPromptBag;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Models\AgentPermission;
use App\Models\IntegrationSetting;
use App\Models\McpServer;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\ModelCatalog;
use App\Services\Ai\ProviderCatalog;
use App\Services\Integrations\IntegrationAccountResolver;
use App\Services\Integrations\IntegrationIdentity;
use App\Services\Mcp\McpRuntime;
use App\Services\Mcp\McpServerRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Agents\Tools\External\ToolProviderRegistry;
use Tests\TestCase;

class RuntimeServiceLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_and_model_catalog_use_workspace_overlay(): void
    {
        IntegrationSetting::create([
            'id' => 'z-setting',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'z',
            'enabled' => true,
            'is_default' => true,
            'config' => [
                'api_key' => 'test-key',
                'url' => 'https://example.test/api/paas/v4',
                'models' => ['glm-5.1' => 'GLM 5.1'],
            ],
        ]);

        $provider = app(ProviderCatalog::class)->provider('z', $this->workspace->id);

        $this->assertTrue($provider['configured']);
        $this->assertSame('integration', $provider['source']);
        $this->assertSame('https://example.test/api/paas/v4', $provider['url']);
        $this->assertSame(['glm-5.1' => 'GLM 5.1'], app(ModelCatalog::class)->modelsForProvider('z', $this->workspace->id));
    }

    public function test_permission_evaluator_denies_cross_workspace_models(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'workspace_id' => $this->workspace->id,
        ]);
        $otherWorkspace = Workspace::create([
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
        ]);
        $other = User::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $decision = app(OpenCompanyPermissionEvaluator::class)->evaluate($agent, 'send_channel_message', [
            'target' => $other,
        ]);

        $this->assertSame('deny', $decision->decision);
        $this->assertSame('workspace_boundary', $decision->source);
    }

    public function test_session_grants_do_not_bypass_workspace_boundaries(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'workspace_id' => $this->workspace->id,
        ]);
        $otherWorkspace = Workspace::create([
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
        ]);
        $other = User::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $decision = app(OpenCompanyPermissionEvaluator::class)->evaluate($agent, 'send_channel_message', [
            'target' => $other,
        ], [], ['send_channel_message']);

        $this->assertSame('deny', $decision->decision);
        $this->assertSame('workspace_boundary', $decision->source);
    }

    public function test_mcp_runtime_permits_enabled_server_and_uses_canonical_permission_slug(): void
    {
        // MCP egress is SSRF-guarded with real DNS; stub the fake host to a
        // public documentation address so Http::fake() handles the call.
        $this->app->instance(WebRequestGuard::class, new class extends WebRequestGuard
        {
            protected function resolveIpAddresses(string $host): array
            {
                return ['93.184.216.34'];
            }
        });

        $agent = User::factory()->create([
            'type' => 'agent',
            'workspace_id' => $this->workspace->id,
        ]);
        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Deep Wiki',
            'slug' => 'deepwiki',
            'url' => 'https://mcp.example.com',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
            'workspace_id' => $this->workspace->id,
            'discovered_tools' => [[
                'name' => 'deep-wiki-search',
                'description' => 'Search DeepWiki',
                'inputSchema' => ['type' => 'object'],
            ]],
        ]);
        McpServerRegistrar::registerAll(app(ToolProviderRegistry::class));

        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'mcp_deepwiki__deep_wiki_search',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        Http::fake([
            'mcp.example.com' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'content' => [
                        ['type' => 'text', 'text' => 'ok'],
                    ],
                ],
            ]),
        ]);

        $result = app(McpRuntime::class)->call($agent, $server, 'deep-wiki-search');

        $this->assertTrue($result['success']);
        $this->assertSame('ok', $result['text']);
        Http::assertSent(fn ($request) => $request['params']['name'] === 'deep-wiki-search');
    }

    public function test_subagent_dependency_graph_orders_dependencies_first(): void
    {
        $ordered = app(SubagentDependencyGraph::class)->order([
            new SubagentRun('write', 'a2', 'write', ['inspect']),
            new SubagentRun('inspect', 'a1', 'inspect'),
        ]);

        $this->assertSame(['inspect', 'write'], array_map(fn (SubagentRun $run) => $run->id, $ordered));
    }

    public function test_agent_run_emits_failure_event_and_clears_prompt_bag(): void
    {
        app()->forgetInstance(SystemPromptBag::class);

        $agentUser = User::factory()->create([
            'type' => 'agent',
            'brain' => 'z:glm-5.1',
            'workspace_id' => $this->workspace->id,
        ]);

        IntegrationSetting::create([
            'id' => 'z-setting',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'z',
            'enabled' => true,
            'is_default' => true,
            'config' => [
                'api_key' => 'test-key',
                'url' => 'https://example.test/api/paas/v4',
                'models' => ['glm-5.1' => 'GLM 5.1'],
            ],
        ]);

        $agent = \Mockery::mock(OpenCompanyAgent::for($agentUser, 'channel-1'))->makePartial();
        $agent->shouldReceive('prompt')->once()->andThrow(new \RuntimeException('provider exploded'));

        $run = new AgentRun(
            $agentUser,
            $agent,
            new AgentRunOptions,
            app(AgentContextPipeline::class),
            'task-1',
        );

        try {
            $run->run('hello');
            $this->fail('Expected AgentRunFailed to be thrown.');
        } catch (AgentRunFailed $e) {
            $this->assertContains('run.failed', array_map(fn ($event) => $event->type, $e->events));
            $this->assertSame('provider exploded', $e->getMessage());
        }

        $this->assertFalse(app()->bound(SystemPromptBag::class));
    }
}

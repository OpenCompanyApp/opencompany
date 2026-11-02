<?php

namespace Tests\Feature\Integrations;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\Integrations\IntegrationRuntime;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

class IntegrationRuntimeWebToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_script_runtime_returns_structured_data_block_from_web_tools(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new class implements Tool
        {
            public ?Request $lastRequest = null;

            public function description(): string
            {
                return 'Fake web tool';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): string
            {
                $this->lastRequest = $request;

                return "Provider: tavily\n\nStructured data:\n".json_encode([
                    'provider' => 'tavily',
                    'results' => [['title' => 'Result']],
                ], JSON_THROW_ON_ERROR);
            }
        };

        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('resolveScriptToolForDispatch')
            ->with('web_search', Mockery::type(User::class), null)
            ->andReturn(['decision' => 'allow', 'reason' => 'Allowed', 'tool' => $tool]);

        $result = (new IntegrationRuntime($registry))->call($agent, 'web_search', [
            'max_results' => 3,
        ]);

        $this->assertSame(3, $tool->lastRequest['maxResults']);
        $this->assertSame('tavily', $result['provider']);
        $this->assertSame('Result', $result['results'][0]['title']);
    }
}

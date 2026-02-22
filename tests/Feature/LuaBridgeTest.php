<?php

namespace Tests\Feature;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\LuaApiDocGenerator;
use App\Services\LuaBridge;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

class LuaBridgeTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);
    }

    private function makeBridge(array $functionMap, ?ToolRegistry $registry = null): LuaBridge
    {
        $docGenerator = Mockery::mock(LuaApiDocGenerator::class);
        $docGenerator->shouldReceive('buildFunctionMap')->andReturn($functionMap);

        $registry = $registry ?? Mockery::mock(ToolRegistry::class);

        return new LuaBridge($this->agent, $registry, $docGenerator);
    }

    private function makeFakeTool(string $returnValue): Tool
    {
        return new class($returnValue) implements Tool
        {
            public ?Request $lastRequest = null;

            public function __construct(private string $returnValue) {}

            public function description(): string
            {
                return 'Fake tool';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): string
            {
                $this->lastRequest = $request;

                return $this->returnValue;
            }
        };
    }

    // ── Routing ──────────────────────────────────────────────────

    public function test_routes_known_path_to_correct_tool(): void
    {
        $fakeTool = $this->makeFakeTool('Message sent');

        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('instantiateToolBySlug')
            ->with('send_channel_message', Mockery::type(User::class))
            ->andReturn($fakeTool);

        $bridge = $this->makeBridge(['chat.send' => 'send_channel_message'], $registry);
        $result = $bridge->call('chat.send', ['channelId' => 'abc', 'content' => 'hi']);

        $this->assertEquals('Message sent', $result);
    }

    public function test_throws_on_unknown_path(): void
    {
        $bridge = $this->makeBridge(['chat.send' => 'send_channel_message']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown function: app.unknown.thing');

        $bridge->call('unknown.thing');
    }

    public function test_throws_when_tool_not_available(): void
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('instantiateToolBySlug')->andReturnNull();

        $bridge = $this->makeBridge(['chat.send' => 'send_channel_message'], $registry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tool not available: send_channel_message');

        $bridge->call('chat.send');
    }

    // ── Parameter passing ────────────────────────────────────────

    public function test_passes_lua_table_args_as_request_params(): void
    {
        $fakeTool = $this->makeFakeTool('ok');

        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('instantiateToolBySlug')->andReturn($fakeTool);

        $bridge = $this->makeBridge(['chat.send' => 'send_channel_message'], $registry);
        $bridge->call('chat.send', ['channelId' => '123', 'content' => 'hello']);

        $this->assertNotNull($fakeTool->lastRequest);
        $this->assertEquals('123', $fakeTool->lastRequest['channelId']);
        $this->assertEquals('hello', $fakeTool->lastRequest['content']);
    }

    // ── Call logging ─────────────────────────────────────────────

    public function test_records_successful_call_in_log(): void
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('instantiateToolBySlug')->andReturn($this->makeFakeTool('ok'));

        $bridge = $this->makeBridge(['chat.send' => 'send_channel_message'], $registry);
        $bridge->call('chat.send', ['content' => 'hi']);

        $log = $bridge->getCallLog();
        $this->assertCount(1, $log);
        $this->assertEquals('chat.send', $log[0]['path']);
        $this->assertEquals('ok', $log[0]['status']);
        $this->assertArrayHasKey('durationMs', $log[0]);
        $this->assertGreaterThanOrEqual(0, $log[0]['durationMs']);
        $this->assertArrayNotHasKey('error', $log[0]);
    }

    public function test_records_error_call_in_log(): void
    {
        $failingTool = new class implements Tool
        {
            public function description(): string
            {
                return 'Fails';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): string
            {
                throw new \RuntimeException('DB error');
            }
        };

        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('instantiateToolBySlug')->andReturn($failingTool);

        $bridge = $this->makeBridge(['chat.send' => 'send_channel_message'], $registry);

        try {
            $bridge->call('chat.send');
        } catch (\RuntimeException) {
            // expected
        }

        $log = $bridge->getCallLog();
        $this->assertCount(1, $log);
        $this->assertEquals('error', $log[0]['status']);
        $this->assertEquals('DB error', $log[0]['error']);
    }

    public function test_records_unknown_path_in_log(): void
    {
        $bridge = $this->makeBridge([]);

        try {
            $bridge->call('unknown.path');
        } catch (\RuntimeException) {
            // expected
        }

        $log = $bridge->getCallLog();
        $this->assertCount(1, $log);
        $this->assertEquals('unknown.path', $log[0]['path']);
        $this->assertEquals('error', $log[0]['status']);
        $this->assertStringContainsString('Unknown function', $log[0]['error']);
    }

    public function test_multiple_calls_accumulate_in_log(): void
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('instantiateToolBySlug')->andReturn($this->makeFakeTool('ok'));

        $bridge = $this->makeBridge([
            'chat.send' => 'send_channel_message',
            'docs.list' => 'list_documents',
        ], $registry);

        $bridge->call('chat.send', ['content' => 'a']);
        $bridge->call('docs.list', []);
        $bridge->call('chat.send', ['content' => 'b']);

        $log = $bridge->getCallLog();
        $this->assertCount(3, $log);
        $this->assertEquals('chat.send', $log[0]['path']);
        $this->assertEquals('docs.list', $log[1]['path']);
        $this->assertEquals('chat.send', $log[2]['path']);
    }
}

<?php

namespace Tests\Feature;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\CodeApiDocGenerator;
use App\Services\CodeBridge;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Mockery;
use OpenCompany\IntegrationCore\Script\ScriptBridgeException;
use Tests\TestCase;

/**
 * Protects the app-owned budgets and telemetry around integration-core's
 * language-neutral ScriptBridge. External effects are represented explicitly
 * because the runtime's retry guidance depends on this ledger.
 */
class CodeBridgeTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent()->create();
    }

    /**
     * @param  array<string, string>  $functionMap
     * @param  array<string, list<array<string, mixed>>>  $parameterMap
     * @param  array<string, int|float>  $profile
     */
    private function makeBridge(
        array $functionMap,
        ?ToolRegistry $registry = null,
        array $parameterMap = [],
        array $profile = [],
    ): CodeBridge {
        $docGenerator = Mockery::mock(CodeApiDocGenerator::class);
        $docGenerator->shouldReceive('buildFunctionMap')->andReturn($functionMap);
        $docGenerator->shouldReceive('buildParameterMap')->andReturn($parameterMap);
        $docGenerator->shouldReceive('buildAccountMap')->andReturn([]);

        return new CodeBridge(
            $this->agent,
            $registry ?? Mockery::mock(ToolRegistry::class),
            $docGenerator,
            array_merge([
                'callback_limit' => 5,
                'callback_wall_limit' => 10.0,
                'callback_total_wall_limit' => 30.0,
                'callback_result_limit' => 4096,
            ], $profile),
        );
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

    public function test_routes_named_ruby_object_to_the_host_tool(): void
    {
        $fakeTool = $this->makeFakeTool('{"message_id":"msg-1"}');
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('getToolMetaBySlug')->with('send_channel_message')->andReturn([
            'icon' => 'ph:chat',
            'name' => 'Send Channel Message',
            'type' => 'write',
        ]);
        $registry->shouldReceive('instantiateToolBySlug')
            ->with('send_channel_message', Mockery::type(User::class), null)
            ->andReturn($fakeTool);

        $bridge = $this->makeBridge(
            ['chat.send' => 'send_channel_message'],
            $registry,
            ['chat.send' => [
                ['name' => 'channel_id', 'type' => 'string', 'required' => true],
                ['name' => 'content', 'type' => 'string', 'required' => true],
            ]],
        );

        $result = $bridge->call('chat.send', ['channel_id' => 'channel-1', 'content' => 'Hello']);

        $this->assertSame(['message_id' => 'msg-1'], $result);
        $this->assertSame('channel-1', $fakeTool->lastRequest['channelId']);
        $this->assertSame(1, $bridge->effectSummary()['writesSucceeded']);
        $this->assertFalse($bridge->isExecutionRetryable());
    }

    public function test_maps_positional_arguments_from_published_parameter_order(): void
    {
        $fakeTool = $this->makeFakeTool('ok');
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('getToolMetaBySlug')->andReturn(['type' => 'read']);
        $registry->shouldReceive('instantiateToolBySlug')->andReturn($fakeTool);
        $bridge = $this->makeBridge(
            ['docs.get' => 'get_document'],
            $registry,
            ['docs.get' => [
                ['name' => 'document_id', 'type' => 'string', 'required' => true],
                ['name' => 'include_comments', 'type' => 'boolean', 'required' => false],
            ]],
        );

        $bridge->call('docs.get', 'doc-1', true);

        $this->assertSame('doc-1', $fakeTool->lastRequest['documentId']);
        $this->assertTrue($fakeTool->lastRequest['includeComments']);
        $this->assertSame(1, $bridge->effectSummary()['reads']);
    }

    public function test_unknown_function_has_ranked_repair_suggestions_without_dispatch(): void
    {
        $bridge = $this->makeBridge([
            'chat.send_channel_message' => 'send_channel_message',
            'chat.read_recent_messages' => 'read_recent_messages',
        ]);

        try {
            $bridge->call('chat.send_message');
            $this->fail('Expected unknown function error.');
        } catch (ScriptBridgeException $exception) {
            $this->assertSame('unknown_function', $exception->errorType);
            $this->assertStringContainsString('app.chat.send_channel_message', $exception->getMessage());
        }

        $this->assertSame('none', $bridge->getCallLog()[0]['effect']);
        $this->assertSame(0, $bridge->effectSummary()['reads']);
    }

    public function test_invalid_arguments_fail_before_provider_dispatch(): void
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('getToolMetaBySlug')->andReturn(['type' => 'write']);
        $registry->shouldNotReceive('instantiateToolBySlug');
        $bridge = $this->makeBridge(
            ['chat.send' => 'send_channel_message'],
            $registry,
            ['chat.send' => [
                ['name' => 'channel_id', 'type' => 'string', 'required' => true],
                ['name' => 'content', 'type' => 'string', 'required' => true],
            ]],
        );

        try {
            $bridge->call('chat.send', ['channel_id' => 'channel-1', 'surprise' => true]);
            $this->fail('Expected argument validation error.');
        } catch (ScriptBridgeException $exception) {
            $this->assertSame('invalid_arguments', $exception->errorType);
            $this->assertStringContainsString('missing: content', $exception->getMessage());
            $this->assertStringContainsString('unknown: surprise', $exception->getMessage());
        }

        $entry = $bridge->getCallLog()[0];
        $this->assertSame('none', $entry['effectStatus']);
        $this->assertSame(0, $bridge->effectSummary()['writesUnknown']);
    }

    public function test_failed_write_is_ambiguous_non_retryable_and_secret_safe(): void
    {
        $failingTool = new class implements Tool
        {
            public function description(): string
            {
                return 'Fails after provider dispatch';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): string
            {
                throw new \RuntimeException('Authorization: Bearer super-secret-token');
            }
        };

        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('getToolMetaBySlug')->andReturn([
            'type' => 'write',
            'name' => 'Write Thing',
        ]);
        $registry->shouldReceive('instantiateToolBySlug')->andReturn($failingTool);
        $bridge = $this->makeBridge(['things.write' => 'write_thing'], $registry);

        try {
            $bridge->call('things.write');
            $this->fail('Expected provider failure.');
        } catch (ScriptBridgeException $exception) {
            $this->assertSame('tool_error', $exception->errorType);
            $this->assertStringNotContainsString('super-secret-token', $exception->getMessage());
            $this->assertStringContainsString('[redacted]', $exception->getMessage());
        }

        $entry = $bridge->getCallLog()[0];
        $this->assertSame('unknown', $entry['effectStatus']);
        $this->assertFalse($entry['retryable']);
        $this->assertStringNotContainsString('super-secret-token', $entry['error']);
        $this->assertSame(1, $bridge->effectSummary()['writesUnknown']);
        $this->assertFalse($bridge->isExecutionRetryable());
    }

    public function test_enforces_callback_and_result_budgets(): void
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('getToolMetaBySlug')->andReturn(['type' => 'read']);
        $registry->shouldReceive('instantiateToolBySlug')->once()->andReturn($this->makeFakeTool(str_repeat('x', 30)));
        $bridge = $this->makeBridge(
            ['docs.list' => 'list_documents'],
            $registry,
            profile: ['callback_limit' => 1, 'callback_result_limit' => 10],
        );

        try {
            $bridge->call('docs.list');
            $this->fail('Expected result-size failure.');
        } catch (ScriptBridgeException $exception) {
            $this->assertSame('callback_result_too_large', $exception->errorType);
            $this->assertTrue($exception->retryable);
        }

        $this->expectException(ScriptBridgeException::class);
        $this->expectExceptionMessage('1-call capability budget');
        $bridge->call('docs.list');
    }
}

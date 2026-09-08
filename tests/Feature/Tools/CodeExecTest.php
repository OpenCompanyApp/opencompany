<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Code\CodeExec;
use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\CodeApiDocGenerator;
use App\Services\MrubySandboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

/**
 * Verifies the model-facing Code Mode contract and its out-of-band trace data.
 */
class CodeExecTest extends TestCase
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
     */
    private function makeTool(array $functionMap = []): CodeExec
    {
        $docs = Mockery::mock(CodeApiDocGenerator::class);
        $docs->shouldReceive('buildFunctionMap')->andReturn($functionMap);
        $docs->shouldReceive('buildParameterMap')->andReturn([]);
        $docs->shouldReceive('buildAccountMap')->andReturn([]);

        return new CodeExec(
            app(MrubySandboxService::class),
            Mockery::mock(ToolRegistry::class),
            $docs,
            $this->agent,
        );
    }

    public function test_returns_concise_output_and_keeps_trace_metadata_out_of_band(): void
    {
        $tool = $this->makeTool();
        $response = $tool->handle(new Request([
            'code' => 'puts("hello world"); { count: 2 }',
        ]));

        $this->assertStringContainsString("Console:\nhello world", $response);
        $this->assertStringContainsString('Return value:', $response);
        $this->assertStringContainsString('"count": 2', $response);
        $this->assertStringContainsString('Execution ID:', $response);
        $this->assertStringNotContainsString('META', $response);
        $this->assertStringNotContainsString('bridgeCalls', $response);

        $meta = $tool->lastExecutionMetadata();
        $this->assertIsArray($meta);
        $this->assertSame('agent', $meta['profile']);
        $this->assertEquals((object) ['count' => 2], $meta['result']);
        $this->assertSame([], $meta['bridgeCalls']);
        $this->assertArrayHasKey('cpuTime', $meta);
        $this->assertArrayHasKey('peakMemoryUsage', $meta);
    }

    public function test_validate_mode_has_no_execution_or_capability_effects(): void
    {
        $tool = $this->makeTool();
        $response = $tool->handle(new Request([
            'code' => 'raise "must not execute"',
            'mode' => 'validate',
        ]));

        $this->assertStringContainsString('Validation passed', $response);
        $this->assertTrue($tool->lastExecutionMetadata()['validatedOnly']);
        $this->assertSame(0, $tool->lastExecutionMetadata()['effects']['callbacks']);
    }

    public function test_reports_structured_syntax_errors_with_repair_guidance(): void
    {
        $tool = $this->makeTool();
        $response = $tool->handle(new Request([
            'code' => "const ok = 1;\nconst broken = ;\nreturn ok;",
            'mode' => 'validate',
        ]));

        $this->assertStringContainsString('Error [syntax_error] at line 2', $response);
        $this->assertStringContainsString('Repair:', $response);
        $this->assertStringContainsString('Retryable:', $response);
        $this->assertSame('syntax_error', $tool->lastExecutionMetadata()['error']['type']);
        $this->assertSame(2, $tool->lastExecutionMetadata()['error']['line']);
    }

    public function test_unknown_app_function_returns_a_ranked_repair_path(): void
    {
        $tool = $this->makeTool([
            'chat.send_channel_message' => 'send_channel_message',
            'chat.read_recent_messages' => 'read_recent_messages',
        ]);
        $response = $tool->handle(new Request([
            'code' => 'return app.chat.send_message({ content: "hello" });',
        ]));

        $this->assertStringContainsString('Error [unknown_function]', $response);
        $this->assertStringContainsString('app.chat.send_channel_message', $response);
        $this->assertStringContainsString('code_read_doc', $response);
        $this->assertSame('unknown_function', $tool->lastExecutionMetadata()['error']['type']);
        $this->assertSame('none', $tool->lastExecutionMetadata()['error']['effectStatus']);
        $this->assertTrue($tool->lastExecutionMetadata()['error']['retryable']);
    }

    public function test_rejects_missing_code_and_unknown_modes_before_starting_runtime(): void
    {
        $tool = $this->makeTool();

        $this->assertStringContainsString('Missing required parameter', $tool->handle(new Request([])));
        $this->assertNull($tool->lastExecutionMetadata());
        $this->assertStringContainsString('Invalid "mode"', $tool->handle(new Request([
            'code' => 'return true;',
            'mode' => 'unsafe',
        ])));
        $this->assertNull($tool->lastExecutionMetadata());
    }

    public function test_agent_cannot_choose_native_resource_limits(): void
    {
        $tool = $this->makeTool();
        $schema = $tool->schema(new JsonSchemaTypeFactory);

        $this->assertArrayHasKey('code', $schema);
        $this->assertArrayHasKey('mode', $schema);
        $this->assertArrayNotHasKey('memoryLimit', $schema);
        $this->assertArrayNotHasKey('cpuLimit', $schema);
    }
}

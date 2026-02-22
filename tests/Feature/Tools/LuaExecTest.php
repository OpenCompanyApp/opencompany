<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Lua\LuaExec;
use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\LuaApiDocGenerator;
use App\Services\LuaResult;
use App\Services\LuaSandboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

class LuaExecTest extends TestCase
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

    private function makeTool(?LuaSandboxService $sandbox = null): LuaExec
    {
        return new LuaExec(
            $sandbox ?? Mockery::mock(LuaSandboxService::class),
            app(ToolRegistry::class),
            app(LuaApiDocGenerator::class),
            $this->agent,
        );
    }

    // ── Output format ────────────────────────────────────────────

    public function test_returns_result_with_lua_meta_marker(): void
    {
        $sandbox = Mockery::mock(LuaSandboxService::class);
        $sandbox->shouldReceive('execute')->andReturn(new LuaResult(
            output: 'hello world',
            error: null,
            result: null,
            executionTime: 5.2,
            memoryUsage: 2048,
        ));

        $tool = $this->makeTool($sandbox);
        $result = $tool->handle(new Request(['code' => 'print("hello world")']));

        // Should start with the __LUA_META__ marker
        $this->assertStringContainsString('<!--__LUA_META__', $result);
        $this->assertStringContainsString('__LUA_META__-->', $result);

        // Extract and verify meta JSON
        preg_match('/<!--__LUA_META__(.*?)__LUA_META__-->/s', $result, $m);
        $meta = json_decode($m[1], true);

        $this->assertEquals('hello world', $meta['output']);
        $this->assertNull($meta['error']);
        $this->assertNull($meta['returnValue']);
        $this->assertEquals(5.2, $meta['executionTime']);
        $this->assertEquals(2048, $meta['memoryUsage']);
        $this->assertIsArray($meta['bridgeCalls']);

        // Human-readable text follows the marker
        $humanText = trim(preg_replace('/<!--__LUA_META__.*?__LUA_META__-->/s', '', $result));
        $this->assertStringContainsString('hello world', $humanText);
        $this->assertStringContainsString('5.2ms', $humanText);
    }

    public function test_includes_return_value_in_output(): void
    {
        $sandbox = Mockery::mock(LuaSandboxService::class);
        $sandbox->shouldReceive('execute')->andReturn(new LuaResult(
            output: '',
            error: null,
            result: 42,
            executionTime: 1.0,
            memoryUsage: 1024,
        ));

        $tool = $this->makeTool($sandbox);
        $result = $tool->handle(new Request(['code' => 'return 42']));

        $this->assertStringContainsString('Return value: 42', $result);
    }

    public function test_formats_array_return_value_as_json(): void
    {
        $sandbox = Mockery::mock(LuaSandboxService::class);
        $sandbox->shouldReceive('execute')->andReturn(new LuaResult(
            output: '',
            error: null,
            result: ['key' => 'value'],
            executionTime: 1.0,
            memoryUsage: 1024,
        ));

        $tool = $this->makeTool($sandbox);
        $result = $tool->handle(new Request(['code' => 'return {key = "value"}']));

        $this->assertStringContainsString('Return value:', $result);
        $this->assertStringContainsString('"key"', $result);
        $this->assertStringContainsString('"value"', $result);
    }

    public function test_includes_error_in_output(): void
    {
        $sandbox = Mockery::mock(LuaSandboxService::class);
        $sandbox->shouldReceive('execute')->andReturn(new LuaResult(
            output: '',
            error: 'attempt to index a nil value',
            result: null,
            executionTime: 0.5,
            memoryUsage: null,
        ));

        $tool = $this->makeTool($sandbox);
        $result = $tool->handle(new Request(['code' => 'x.y = 1']));

        $this->assertStringContainsString('Error: attempt to index a nil value', $result);

        // Meta should also contain the error
        preg_match('/<!--__LUA_META__(.*?)__LUA_META__-->/s', $result, $m);
        $meta = json_decode($m[1], true);
        $this->assertEquals('attempt to index a nil value', $meta['error']);
    }

    public function test_returns_failure_message_on_exception(): void
    {
        $sandbox = Mockery::mock(LuaSandboxService::class);
        $sandbox->shouldReceive('execute')->andThrow(new \RuntimeException('Sandbox crashed'));

        $tool = $this->makeTool($sandbox);
        $result = $tool->handle(new Request(['code' => 'bad code']));

        $this->assertStringContainsString('Failed to execute Lua code: Sandbox crashed', $result);
        // No __LUA_META__ marker on exception
        $this->assertStringNotContainsString('__LUA_META__', $result);
    }

    public function test_passes_memory_and_cpu_limits_to_sandbox(): void
    {
        $sandbox = Mockery::mock(LuaSandboxService::class);
        $sandbox->shouldReceive('execute')
            ->withArgs(function (string $code, array $options) {
                return $options['memoryLimit'] === 16777216
                    && $options['cpuLimit'] === 10.0;
            })
            ->andReturn(new LuaResult(
                output: '',
                error: null,
                result: null,
                executionTime: 0.1,
                memoryUsage: 512,
            ));

        $tool = $this->makeTool($sandbox);
        $tool->handle(new Request([
            'code' => 'return true',
            'memoryLimit' => 16777216,
            'cpuLimit' => 10.0,
        ]));

        // If we reach here without Mockery complaining, the args were correct
        $this->assertTrue(true);
    }

    public function test_formats_memory_bytes_correctly(): void
    {
        $sandbox = Mockery::mock(LuaSandboxService::class);

        // Test MB formatting
        $sandbox->shouldReceive('execute')->andReturn(new LuaResult(
            output: '',
            error: null,
            result: null,
            executionTime: 1.0,
            memoryUsage: 2 * 1024 * 1024,
        ));

        $tool = $this->makeTool($sandbox);
        $result = $tool->handle(new Request(['code' => 'return nil']));

        $this->assertStringContainsString('2 MB', $result);
    }
}

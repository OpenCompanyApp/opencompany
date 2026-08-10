<?php

namespace Tests\Feature;

use App\Services\QuickJsSandboxService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Guards the capability-empty QuickJS boundary and its fixed resource budgets.
 *
 * These tests intentionally use the native extension. Mocking the sandbox would
 * miss the conversion, timeout, protected-global, and source-location contracts
 * that make untrusted Code Mode execution safe and repairable for agents.
 */
class QuickJsSandboxServiceSecurityTest extends TestCase
{
    public function test_executes_standard_javascript_with_console_and_return_values(): void
    {
        $result = app(QuickJsSandboxService::class)->execute(<<<'JS'
            console.info('count', 2);
            print({ ok: true });
            return [1, 2, 3].map(value => value * 2);
            JS);

        $this->assertTrue($result->succeeded());
        $this->assertSame("[info] count 2\n{\n  \"ok\": true\n}", $result->output);
        $this->assertSame([2, 4, 6], $result->result);
        $this->assertSame('agent', $result->profile);
        $this->assertFalse($result->validatedOnly);
        $this->assertNotSame('', $result->executionId);
    }

    public function test_validation_compiles_without_executing_or_installing_capabilities(): void
    {
        $result = app(QuickJsSandboxService::class)->execute(
            'throw new Error("must not run"); return typeof app;',
            validateOnly: true,
        );

        $this->assertTrue($result->succeeded());
        $this->assertTrue($result->validatedOnly);
        $this->assertSame('', $result->output);
        $this->assertNull($result->result);
        $this->assertSame(0, $result->effects['callbacks']);
    }

    public function test_host_callbacks_and_ambient_authority_are_not_reachable(): void
    {
        $result = app(QuickJsSandboxService::class)->execute(<<<'JS'
            return {
              host: typeof __opencompany_host,
              app: typeof app,
              require: typeof require,
              process: typeof process,
              fetch: typeof fetch,
              queueMicrotask: typeof queueMicrotask,
            };
            JS);

        $this->assertTrue($result->succeeded());
        $this->assertSame([
            'host' => 'undefined',
            'app' => 'undefined',
            'require' => 'undefined',
            'process' => 'undefined',
            'fetch' => 'undefined',
            'queueMicrotask' => 'undefined',
        ], $result->result);
    }

    public function test_data_globals_are_converted_without_source_interpolation(): void
    {
        $result = app(QuickJsSandboxService::class)->execute(
            'return [ctx["bad-key"], ctx.payload, typeof injected];',
            globals: [
                'ctx' => [
                    'bad-key' => 7,
                    'payload' => '"]; globalThis.injected = true; //',
                ],
            ],
        );

        $this->assertTrue($result->succeeded());
        $this->assertSame([7, '"]; globalThis.injected = true; //', 'undefined'], $result->result);
    }

    public function test_rejects_reserved_or_invalid_injected_global_names(): void
    {
        $result = app(QuickJsSandboxService::class)->execute(
            'return true;',
            globals: ['__app' => ['owned' => true]],
        );

        $this->assertFalse($result->succeeded());
        $this->assertSame('invalid_global', $result->error['type']);
        $this->assertStringContainsString('reserved JavaScript global name', $result->error['message']);
    }

    public function test_reports_user_source_line_for_syntax_errors(): void
    {
        $result = app(QuickJsSandboxService::class)->execute(<<<'JS'
            const first = 1;
            const broken = ;
            return first;
            JS, sourceName: 'agent-code.js');

        $this->assertFalse($result->succeeded());
        $this->assertSame('syntax_error', $result->error['type']);
        $this->assertSame(2, $result->error['line']);
        $this->assertStringContainsString('validate mode', $result->error['suggestion']);
    }

    public function test_interrupts_unbounded_execution_with_the_profile_cpu_limit(): void
    {
        Config::set('code.profiles.agent.cpu_limit', 0.01);

        $result = app(QuickJsSandboxService::class)->execute('while (true) {}');

        $this->assertFalse($result->succeeded());
        $this->assertSame('timeout', $result->error['type']);
        $this->assertFalse($result->error['retryable']);
    }

    public function test_bounds_console_output_and_marks_truncation(): void
    {
        Config::set('code.profiles.agent.output_limit', 24);
        Config::set('code.profiles.agent.log_limit', 2);

        $result = app(QuickJsSandboxService::class)->execute(<<<'JS'
            console.log('first');
            console.log('second');
            console.log('third');
            return 'done';
            JS);

        $this->assertTrue($result->succeeded());
        $this->assertTrue($result->outputTruncated);
        $this->assertStringContainsString('output truncated', $result->output);
        $this->assertSame('done', $result->result);
    }
}

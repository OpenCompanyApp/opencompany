<?php

namespace Tests\Feature;

use App\Agents\Tools\ToolRegistry;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\CodeApiDocGenerator;
use App\Services\CodeBridge;
use App\Services\MrubySandboxService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

/** Guest rescue cannot override host cancellation, deadlines or pending approval. */
class MrubyHostControlTest extends TestCase
{
    use RefreshDatabase;

    private function bridge(ToolRegistry $registry): CodeBridge
    {
        $docs = Mockery::mock(CodeApiDocGenerator::class);
        $docs->shouldReceive('buildFunctionMap')->andReturn(['docs.read' => 'read_document']);
        $docs->shouldReceive('buildParameterMap')->andReturn([]);
        $docs->shouldReceive('buildAccountMap')->andReturn([]);

        return new CodeBridge(User::factory()->agent()->create(), $registry, $docs, app(MrubySandboxService::class)->profile('agent'));
    }

    public function test_cancellation_interrupts_a_running_guest_and_is_not_retryable(): void
    {
        $start = hrtime(true);
        $result = app(MrubySandboxService::class)->execute('loop {}',
            cancelled: fn (): bool => hrtime(true) - $start > 50_000_000);

        $this->assertSame('cancelled', $result->error['type']);
        $this->assertFalse($result->error['retryable']);
        $this->assertLessThan(1500, (hrtime(true) - $start) / 1_000_000);
    }

    public function test_rescuing_a_pending_read_approval_cannot_report_program_success(): void
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('getToolMetaBySlug')->andReturn(['type' => 'read']);
        $registry->shouldReceive('getToolTypeBySlug')->andReturn('read');
        $registry->shouldReceive('getChannelContext')->andReturnNull();
        $registry->shouldReceive('resolveScriptToolForDispatch')->once()->andReturn([
            'decision' => 'approval_required', 'reason' => 'Approval is required.',
        ]);
        $bridge = $this->bridge($registry);
        $result = app(MrubySandboxService::class)->execute('begin; app.docs.read; rescue; 42; end', bridge: $bridge);

        $this->assertSame('approval_pending', $result->error['type']);
        $this->assertNull($result->result);
        $this->assertFalse($result->error['retryable']);
        $this->assertSame(1, $result->effects['callbacksPendingApproval']);
        $this->assertSame(0, $result->effects['reads']);
        $this->assertSame(1, ApprovalRequest::count());
    }

    public function test_rescuing_callback_timeout_cannot_dispatch_again_or_report_success(): void
    {
        config(['code.profiles.agent.callback_wall_limit' => 0.005]);
        $tool = new class implements Tool
        {
            public function description(): string
            {
                return 'Local deadline fixture';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): string
            {
                usleep(20_000);

                return 'late response';
            }
        };
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('getToolMetaBySlug')->andReturn(['type' => 'read']);
        $registry->shouldReceive('getToolTypeBySlug')->andReturn('read');
        $registry->shouldReceive('resolveScriptToolForDispatch')->once()->andReturn([
            'decision' => 'allow', 'reason' => 'Allowed', 'tool' => $tool,
        ]);
        $result = app(MrubySandboxService::class)->execute(
            '2.times { begin; app.docs.read; rescue; nil; end }; 42', bridge: $this->bridge($registry));

        $this->assertSame('callback_time_exceeded', $result->error['type']);
        $this->assertNull($result->result);
        $this->assertFalse($result->error['retryable']);
    }
}

<?php

namespace Tests\Feature;

use App\Agents\Tools\ToolRegistry;
use App\Models\Document;
use App\Models\Message;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CodeApiDocGenerator;
use App\Services\CodeBridge;
use App\Services\Integrations\IntegrationRuntime;
use App\Services\MrubySandboxService;
use App\Services\ScriptCallbackReceiptService;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Mockery;
use OpenCompany\IntegrationCore\Script\ScriptBridgeException;
use OpenCompany\IntegrationCore\Script\ScriptDispatchException;
use Stringable;
use Tests\TestCase;

/**
 * Regression coverage for task-owned receipts around Code Mode write calls.
 *
 * All tools are in-process fakes. These tests prove ordering and stored
 * evidence only; they intentionally make no provider request or retry claim.
 */
class ScriptCallbackReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent()->create();
        app()->instance('currentWorkspace', Workspace::findOrFail($this->agent->workspace_id));
    }

    private function task(?User $agent = null, ?string $workspaceId = null): Task
    {
        return Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspaceId ?? $this->agent->workspace_id,
            'title' => 'Receipt test task',
            'description' => 'Task authority for a fake callback.',
            'type' => Task::TYPE_REQUEST,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'agent_id' => ($agent ?? $this->agent)->id,
            'requester_id' => $this->agent->id,
            'source' => Task::SOURCE_CHAT,
        ]);
    }

    /** @param Closure(Request): string|Stringable $handler */
    private function fakeTool(Closure $handler): Tool
    {
        return new class($handler) implements Tool
        {
            public function __construct(private Closure $handler) {}

            public function description(): string
            {
                return 'Receipt fake tool';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): string|Stringable
            {
                return ($this->handler)($request);
            }
        };
    }

    private function runtime(Tool $tool, ?string $taskId): IntegrationRuntime
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('resolveScriptToolForDispatch')->once()
            ->with('fake_write', $this->agent, null)
            ->andReturn(['decision' => 'allow', 'reason' => 'Fake allowed write', 'tool' => $tool]);
        $registry->shouldReceive('getToolTypeBySlug')->once()->with('fake_write')->andReturn('write');
        $registry->shouldReceive('getTaskContext')->once()->andReturn($taskId);

        return new IntegrationRuntime($registry, new ScriptCallbackReceiptService);
    }

    /** @return array<string, mixed> */
    private function receiptContext(?string $sourceDigest = null): array
    {
        return [
            'required' => true,
            'source_digest' => $sourceDigest ?? hash('sha256', 'receipt-test-source'),
            'code_invocation_id' => Str::uuid()->toString(),
            'sequence' => 1,
        ];
    }

    public function test_write_intent_exists_before_the_fake_tool_can_mutate(): void
    {
        $task = $this->task();
        $writes = 0;
        $tool = $this->fakeTool(function () use (&$writes, $task): string {
            $step = TaskStep::sole();
            $this->assertSame($task->id, $step->task_id);
            $this->assertSame(TaskStep::STATUS_IN_PROGRESS, $step->status);
            $this->assertSame('intent_recorded', $step->metadata['script_callback']['disposition']);
            $writes++;

            return '{"provider_payload":"never persisted","accepted":true}';
        });

        $result = $this->runtime($tool, $task->id)->call(
            $this->agent,
            'fake_write',
            ['record_id' => '9', 'enabled' => false],
            receiptContext: $this->receiptContext(),
        );

        $this->assertSame(['provider_payload' => 'never persisted', 'accepted' => true], $result);
        $this->assertSame(1, $writes);
        $step = TaskStep::sole();
        $this->assertSame(TaskStep::STATUS_COMPLETED, $step->status);
        $callback = $step->metadata['script_callback'];
        $this->assertSame('succeeded', $callback['disposition']);
        $this->assertTrue($callback['automatic_replay'] === false);
        $this->assertArrayHasKey('receipt_digest', $callback);
        $this->assertStringNotContainsString('never persisted', json_encode($step->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('record_id', json_encode($step->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_write_without_a_task_is_denied_before_dispatch(): void
    {
        $writes = 0;
        $tool = $this->fakeTool(function () use (&$writes): string {
            $writes++;

            return '{}';
        });

        try {
            $this->runtime($tool, null)->call($this->agent, 'fake_write', [], receiptContext: $this->receiptContext());
            $this->fail('Expected task receipt denial.');
        } catch (ScriptDispatchException $exception) {
            $this->assertSame('authorization_denied', $exception->errorType);
        }

        $this->assertSame(0, $writes);
        $this->assertSame(0, TaskStep::count());
    }

    public function test_wrong_workspace_or_agent_task_is_denied_before_dispatch(): void
    {
        $otherWorkspace = Workspace::create(['name' => 'Other receipt workspace', 'slug' => 'other-receipt-workspace']);
        $wrongTask = $this->task(workspaceId: $otherWorkspace->id);
        $writes = 0;
        $tool = $this->fakeTool(function () use (&$writes): string {
            $writes++;

            return '{}';
        });

        $this->expectException(ScriptDispatchException::class);
        try {
            $this->runtime($tool, $wrongTask->id)->call($this->agent, 'fake_write', [], receiptContext: $this->receiptContext());
        } finally {
            $this->assertSame(0, $writes);
            $this->assertSame(0, TaskStep::count());
        }
    }

    public function test_other_agent_task_is_denied_before_dispatch(): void
    {
        $otherAgent = User::factory()->agent()->create(['workspace_id' => $this->agent->workspace_id]);
        $wrongTask = $this->task(agent: $otherAgent);
        $writes = 0;
        $tool = $this->fakeTool(function () use (&$writes): string {
            $writes++;

            return '{}';
        });

        $this->expectException(ScriptDispatchException::class);
        try {
            $this->runtime($tool, $wrongTask->id)->call($this->agent, 'fake_write', [], receiptContext: $this->receiptContext());
        } finally {
            $this->assertSame(0, $writes);
            $this->assertSame(0, TaskStep::count());
        }
    }

    public function test_write_requires_source_binding_even_with_a_valid_task(): void
    {
        $task = $this->task();
        $writes = 0;
        $tool = $this->fakeTool(function () use (&$writes): string {
            $writes++;

            return '{}';
        });

        $this->expectException(ScriptDispatchException::class);
        try {
            $this->runtime($tool, $task->id)->call($this->agent, 'fake_write', [], receiptContext: $this->receiptContext('not-a-digest'));
        } finally {
            $this->assertSame(0, $writes);
            $this->assertSame(0, TaskStep::count());
        }
    }

    public function test_cancelled_task_is_denied_before_dispatch(): void
    {
        $task = $this->task();
        $task->update(['status' => Task::STATUS_CANCELLED]);
        $writes = 0;
        $tool = $this->fakeTool(function () use (&$writes): string {
            $writes++;

            return '{}';
        });

        $this->expectException(ScriptDispatchException::class);
        try {
            $this->runtime($tool, $task->id)->call($this->agent, 'fake_write', [], receiptContext: $this->receiptContext());
        } finally {
            $this->assertSame(0, $writes);
            $this->assertSame(0, TaskStep::count());
        }
    }

    public function test_provider_failure_is_unknown_and_contains_no_provider_error_or_replay_instruction(): void
    {
        $task = $this->task();
        $tool = $this->fakeTool(static function (): string {
            throw new \RuntimeException('provider response contained secret-token');
        });

        try {
            $this->runtime($tool, $task->id)->call($this->agent, 'fake_write', ['secret' => 'never-store'], receiptContext: $this->receiptContext());
            $this->fail('Expected fake provider failure.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('secret-token', $exception->getMessage());
        }

        $step = TaskStep::sole();
        $callback = $step->metadata['script_callback'];
        $this->assertSame('unknown', $callback['disposition']);
        $this->assertNull($callback['receipt_digest']);
        $this->assertFalse($callback['automatic_replay']);
        $this->assertStringNotContainsString('secret-token', json_encode($step->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('never-store', json_encode($step->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_bridge_source_binding_supplies_execution_identity_and_sequence_to_a_write_receipt(): void
    {
        $task = $this->task();
        $source = 'app.call("fake_write", record_id: "9")';
        $tool = $this->fakeTool(static fn (): string => '{"accepted":true}');
        $registry = Mockery::mock(ToolRegistry::class);
        // ScriptBridge reads metadata for its effect ledger; the runtime uses
        // the registry's dedicated type accessor for receipt enforcement.
        $registry->shouldReceive('getToolMetaBySlug')->with('fake_write')->once()->andReturn([]);
        $registry->shouldReceive('getToolTypeBySlug')->with('fake_write')->twice()->andReturn('write');
        $registry->shouldReceive('resolveScriptToolForDispatch')->once()
            ->with('fake_write', $this->agent, null)
            ->andReturn(['decision' => 'allow', 'reason' => 'Fake allowed write', 'tool' => $tool]);
        $registry->shouldReceive('getTaskContext')->once()->andReturn($task->id);
        $docs = Mockery::mock(CodeApiDocGenerator::class);
        $docs->shouldReceive('buildFunctionMap')->andReturn(['fake.write' => 'fake_write']);
        $docs->shouldReceive('buildParameterMap')->andReturn(['fake.write' => []]);
        $docs->shouldReceive('buildAccountMap')->andReturn([]);
        $bridge = new CodeBridge($this->agent, $registry, $docs, [
            'callback_limit' => 2,
            'callback_wall_limit' => 2.0,
            'callback_total_wall_limit' => 2.0,
            'callback_result_limit' => 1024,
            'require_task_receipt_for_writes' => true,
        ]);

        $bridge->bindSource($source);
        $bridge->call('fake.write', ['record_id' => '9']);

        $callback = TaskStep::sole()->metadata['script_callback'];
        $this->assertSame(hash('sha256', $source), $callback['source_digest']);
        $this->assertSame(1, $callback['sequence']);
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $callback['code_invocation_id']);
    }

    public function test_real_registry_default_agent_profile_denies_a_catalog_write_without_task_before_tool_handle(): void
    {
        $registry = app(ToolRegistry::class);
        $docs = app(CodeApiDocGenerator::class);
        $path = array_search('send_channel_message', $docs->buildFunctionMap($this->agent), true);

        $this->assertSame('write', $registry->getToolTypeBySlug('send_channel_message'));
        $this->assertIsString($path, 'The actual script catalog must publish the bounded message write.');
        $bridge = new CodeBridge(
            $this->agent,
            $registry,
            $docs,
            app(MrubySandboxService::class)->profile('agent'),
        );
        $bridge->bindSource('app.chat.send_channel_message(channel_id: "unreachable", content: "must not run")');

        try {
            $bridge->call($path, ['channel_id' => (string) Str::uuid(), 'content' => 'must not run']);
            $this->fail('Expected a task-receipt denial before the actual message tool handles this call.');
        } catch (ScriptBridgeException $exception) {
            $this->assertSame('authorization_denied', $exception->errorType);
        }

        $this->assertSame(0, Message::count());
        $this->assertSame(0, TaskStep::count());
        $this->assertSame(1, $bridge->effectSummary()['callbacksDenied']);
        $this->assertSame(0, $bridge->effectSummary()['writesSucceeded']);
    }

    public function test_real_catalog_document_write_followed_by_ruby_error_is_non_retryable(): void
    {
        $task = $this->task();
        $registry = app(ToolRegistry::class);
        $registry->setTaskContext($task->id);
        $docs = app(CodeApiDocGenerator::class);
        $path = array_search('create_document', $docs->buildFunctionMap($this->agent), true);
        $this->assertSame('docs.create', $path);
        $bridge = new CodeBridge(
            $this->agent,
            $registry,
            $docs,
            app(MrubySandboxService::class)->profile('agent'),
        );

        $result = app(MrubySandboxService::class)->execute(<<<'RUBY'
app.docs.create(title: "Receipt regression", content: "local test document")
raise "after local write"
RUBY, bridge: $bridge, sourceName: 'receipt-partial-effect.rb');

        $this->assertNotNull($result->error);
        $this->assertFalse($result->error['retryable']);
        $this->assertSame(1, $result->effects['writesSucceeded']);
        $this->assertFalse($bridge->isExecutionRetryable());
        $this->assertSame(1, Document::query()->where('title', 'Receipt regression')->count());
    }

    public function test_receipt_digest_supports_nested_empty_and_numeric_key_json_objects(): void
    {
        $task = $this->task();
        $service = new ScriptCallbackReceiptService;
        $step = $service->begin(
            $this->agent,
            $task->id,
            hash('sha256', 'object-source'),
            Str::uuid()->toString(),
            1,
            'fake_write',
            [],
            null,
        );
        $numeric = new \stdClass;
        $numeric->{'0'} = false;
        $result = (object) [
            'empty' => new \stdClass,
            'numeric' => $numeric,
            'items' => [(object) ['name' => 'nested']],
        ];

        $service->succeeded($step, $result);

        $callback = $step->refresh()->metadata['script_callback'];
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $callback['receipt_digest']);
        $this->assertFalse($callback['receipt_digest_unavailable']);
    }

    public function test_exotic_or_cyclic_results_mark_digest_unavailable_without_a_fake_digest(): void
    {
        $task = $this->task();
        $service = new ScriptCallbackReceiptService;
        $step = $service->begin(
            $this->agent,
            $task->id,
            hash('sha256', 'object-source'),
            Str::uuid()->toString(),
            1,
            'fake_write',
            [],
            null,
        );
        $cyclic = new \stdClass;
        $cyclic->self = $cyclic;

        $service->succeeded($step, $cyclic);

        $callback = $step->refresh()->metadata['script_callback'];
        $this->assertNull($callback['receipt_digest']);
        $this->assertTrue($callback['receipt_digest_unavailable']);
    }
}

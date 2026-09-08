<?php

namespace Tests\Feature;

use App\Agents\Conversations\ChannelConversationLoader;
use App\Agents\Runtime\Subagents\GenericSubagent;
use App\Agents\Tools\ToolRegistry;
use App\Models\Channel;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentDocumentService;
use App\Services\ScriptCallbackReceiptService;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Mockery;
use OpenCompany\IntegrationCore\Script\ScriptDispatchException;
use Stringable;
use Tests\TestCase;

/**
 * Covers the task authority boundary for peer-agent Laravel AI prompts.
 *
 * The gateway is always faked: these scenarios prove local task ownership,
 * registry cleanup, and cancellation ordering without selecting an LLM or
 * performing a provider request.
 */
class GenericSubagentTaskOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $parentAgent;

    private User $peerAgent;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parentAgent = User::factory()->agent()->create();
        $this->peerAgent = User::factory()->agent()->create();
        app()->instance('currentWorkspace', Workspace::findOrFail($this->peerAgent->workspace_id));
        $this->channel = Channel::factory()->create(['workspace_id' => $this->peerAgent->workspace_id]);
    }

    private function parentTask(?string $workspaceId = null): Task
    {
        return Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspaceId ?? $this->peerAgent->workspace_id,
            'title' => 'Parent delegated task',
            'description' => 'Parent authority for a peer prompt.',
            'type' => Task::TYPE_REQUEST,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'agent_id' => $this->parentAgent->id,
            'requester_id' => $this->parentAgent->id,
            'source' => Task::SOURCE_CHAT,
            'channel_id' => $this->channel->id,
        ]);
    }

    /**
     * Return a registry mock whose context behaves like the shared singleton.
     *
     * @param  array<int, Tool>  $availableTools
     */
    private function registry(
        array $availableTools = [],
        ?string $channel = 'parent-channel',
        ?string $task = 'parent-task',
        ?Closure $toolResolver = null,
    ): ToolRegistry {
        $registry = Mockery::mock(ToolRegistry::class);
        $currentChannel = $channel;
        $currentTask = $task;

        $registry->shouldReceive('getChannelContext')->andReturnUsing(function () use (&$currentChannel): ?string {
            return $currentChannel;
        });
        $registry->shouldReceive('getTaskContext')->andReturnUsing(function () use (&$currentTask): ?string {
            return $currentTask;
        });
        $registry->shouldReceive('setChannelContext')->andReturnUsing(function (?string $value) use (&$currentChannel): void {
            $currentChannel = $value;
        });
        $registry->shouldReceive('setTaskContext')->andReturnUsing(function (?string $value) use (&$currentTask): void {
            $currentTask = $value;
        });
        $registry->shouldReceive('getToolsForAgent')->andReturnUsing(
            $toolResolver ?? static fn (): array => $availableTools,
        );

        return $registry;
    }

    private function subagent(ToolRegistry $registry, ?string $parentTaskId, ?string $channelId = null): GenericSubagent
    {
        $documents = Mockery::mock(AgentDocumentService::class);
        $documents->shouldReceive('getIdentityFiles')->andReturn(collect());

        return new GenericSubagent(
            $this->peerAgent,
            $channelId ?? $this->channel->id,
            $parentTaskId,
            $documents,
            Mockery::mock(ChannelConversationLoader::class),
            $registry,
        );
    }

    public function test_prompt_creates_peer_owned_child_and_restores_parent_registry_context(): void
    {
        $parent = $this->parentTask();
        $registry = $this->registry();
        $subagent = $this->subagent($registry, $parent->id);
        GenericSubagent::fake(['delegated result'])->preventStrayPrompts();

        $response = $subagent->prompt('Prepare the delegated report.');

        $this->assertSame('delegated result', $response->text);
        $child = Task::query()->where('parent_task_id', $parent->id)->sole();
        $this->assertSame($this->peerAgent->id, $child->agent_id);
        $this->assertSame($this->peerAgent->workspace_id, $child->workspace_id);
        $this->assertSame($parent->requester_id, $child->requester_id);
        $this->assertSame(Task::SOURCE_AGENT_DELEGATION, $child->source);
        $this->assertSame(Task::STATUS_COMPLETED, $child->status);
        $this->assertSame('parent-channel', $registry->getChannelContext());
        $this->assertSame('parent-task', $registry->getTaskContext());
    }

    public function test_listing_tools_creates_no_child_and_never_rebinds_shared_context(): void
    {
        $parent = $this->parentTask();
        $registry = $this->registry();
        $subagent = $this->subagent($registry, $parent->id);

        iterator_to_array($subagent->tools());

        $this->assertSame(1, Task::query()->count());
        $this->assertSame('parent-channel', $registry->getChannelContext());
        $this->assertSame('parent-task', $registry->getTaskContext());
    }

    public function test_same_workspace_channel_mismatch_or_missing_parent_channel_never_starts_a_peer(): void
    {
        $parent = $this->parentTask();
        $otherChannel = Channel::factory()->create(['workspace_id' => $this->peerAgent->workspace_id]);
        $registry = $this->registry();
        GenericSubagent::fake(['must not execute'])->preventStrayPrompts();

        foreach ([$otherChannel->id, null] as $parentChannel) {
            $parent->update(['channel_id' => $parentChannel]);
            try {
                $this->subagent($registry, $parent->id)->prompt('Do not move this conversation.');
                $this->fail('Delegation must retain its parent conversation authority.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('The delegated channel does not match the parent task.', $exception->getMessage());
            }

            $this->assertSame(0, Task::query()->where('parent_task_id', $parent->id)->count());
            $this->assertSame('parent-channel', $registry->getChannelContext());
            $this->assertSame('parent-task', $registry->getTaskContext());
        }

        GenericSubagent::assertNeverPrompted();
    }

    public function test_peer_owned_child_can_acquire_a_receipt_before_a_fake_local_write(): void
    {
        $parent = $this->parentTask();
        $writes = 0;
        $tool = new class($this->peerAgent, $writes) implements Tool
        {
            public function __construct(private User $agent, public int $writes) {}

            public function name(): string
            {
                return 'peer_local_write';
            }

            public function description(): string
            {
                return 'Performs a local fake write under the delegated child task.';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): string|Stringable
            {
                $taskId = app(ToolRegistry::class)->getTaskContext();
                $step = (new ScriptCallbackReceiptService)->begin(
                    $this->agent,
                    $taskId,
                    hash('sha256', 'generic-subagent-success-test'),
                    Str::uuid()->toString(),
                    1,
                    'fake_local_write',
                    ['enabled' => false],
                    null,
                );
                (new ScriptCallbackReceiptService)->succeeded($step, ['written' => true]);
                $this->writes++;

                return 'written';
            }
        };
        $registry = $this->registry([$tool]);
        app()->instance(ToolRegistry::class, $registry);
        $subagent = $this->subagent($registry, $parent->id);
        GenericSubagent::fake([
            new ToolCall('call-success', 'peer_local_write', []),
            'write completed',
        ])->preventStrayPrompts();

        $subagent->prompt('Perform one safe local write.');

        $child = Task::query()->where('parent_task_id', $parent->id)->sole();
        $this->assertSame($this->peerAgent->id, $child->agent_id);
        $this->assertSame(Task::STATUS_COMPLETED, $child->status);
        $this->assertSame(1, $tool->writes);
        $this->assertDatabaseHas('task_steps', [
            'task_id' => $child->id,
            'status' => 'completed',
        ]);
        $this->assertSame('parent-channel', $registry->getChannelContext());
        $this->assertSame('parent-task', $registry->getTaskContext());
    }

    public function test_missing_or_cross_workspace_parent_fails_before_a_child_or_prompt(): void
    {
        $otherWorkspace = Workspace::create(['name' => 'Other', 'slug' => 'other']);
        $otherParent = $this->parentTask($otherWorkspace->id);
        $subagent = $this->subagent($this->registry(), $otherParent->id);
        GenericSubagent::fake(['must not run'])->preventStrayPrompts();

        try {
            $subagent->prompt('This must not start.');
            $this->fail('Expected an out-of-workspace parent to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('parent task', strtolower($exception->getMessage()));
        }

        $this->assertSame(1, Task::query()->count());
        GenericSubagent::assertNeverPrompted();
    }

    public function test_tool_construction_failure_marks_child_failed_and_restores_registry_context(): void
    {
        $parent = $this->parentTask();
        $registry = $this->registry(toolResolver: static function (): array {
            throw new \RuntimeException('provider credential must not be persisted');
        });
        $subagent = $this->subagent($registry, $parent->id);
        GenericSubagent::fake(['not reached'])->preventStrayPrompts();

        try {
            $subagent->prompt('Attempt a delegated tool call.');
            $this->fail('Expected tool construction to abort the prompt.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('provider credential must not be persisted', $exception->getMessage());
        }

        $child = Task::query()->where('parent_task_id', $parent->id)->sole();
        $this->assertSame(Task::STATUS_FAILED, $child->status);
        $this->assertSame(['error' => 'Subagent prompt failed.'], $child->result);
        $this->assertSame('parent-channel', $registry->getChannelContext());
        $this->assertSame('parent-task', $registry->getTaskContext());
    }

    public function test_provider_return_fails_closed_when_the_host_pauses_the_child(): void
    {
        $parent = $this->parentTask();
        $tool = new class implements Tool
        {
            public function name(): string
            {
                return 'pause_delegated_child';
            }

            public function description(): string
            {
                return 'Simulates host-side pausing while the SDK is returning.';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): string|Stringable
            {
                Task::query()->findOrFail(app(ToolRegistry::class)->getTaskContext())->pause();

                return 'child paused';
            }
        };
        $registry = $this->registry([$tool]);
        app()->instance(ToolRegistry::class, $registry);
        $subagent = $this->subagent($registry, $parent->id);
        GenericSubagent::fake([
            new ToolCall('call-pause', 'pause_delegated_child', []),
            'provider returned after host pause',
        ])->preventStrayPrompts();

        try {
            $subagent->prompt('Pause the child before returning.');
            $this->fail('Expected the paused child to stop the delegated prompt.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('parent or delegated task stopped', strtolower($exception->getMessage()));
        }

        $child = Task::query()->where('parent_task_id', $parent->id)->sole();
        $this->assertSame(Task::STATUS_PAUSED, $child->status);
        $this->assertSame('parent-channel', $registry->getChannelContext());
        $this->assertSame('parent-task', $registry->getTaskContext());
    }

    public function test_missing_parent_or_cross_workspace_channel_fails_before_a_child(): void
    {
        $missingParent = $this->subagent($this->registry(), Str::uuid()->toString());
        try {
            $missingParent->prompt('This parent does not exist.');
            $this->fail('Expected a missing parent to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('parent task', strtolower($exception->getMessage()));
        }

        $foreignWorkspace = Workspace::create(['name' => 'Foreign channel', 'slug' => 'foreign-channel']);
        $foreignChannel = Channel::factory()->create(['workspace_id' => $foreignWorkspace->id]);
        $crossWorkspaceChannel = $this->subagent($this->registry(), $this->parentTask()->id, $foreignChannel->id);
        try {
            $crossWorkspaceChannel->prompt('This channel is not in the peer workspace.');
            $this->fail('Expected a cross-workspace channel to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('delegated channel', strtolower($exception->getMessage()));
        }

        $this->assertSame(1, Task::query()->count());
    }

    public function test_parent_cancellation_cascades_child_and_blocks_the_fake_write_before_mutation(): void
    {
        $parent = $this->parentTask();
        $writes = 0;
        $writeBlocked = false;
        $tool = new class($parent, $this->peerAgent, $writes, $writeBlocked) implements Tool
        {
            public function __construct(
                private Task $parent,
                private User $agent,
                public int $writes,
                public bool $writeBlocked,
            ) {}

            public function name(): string
            {
                return 'cancel_parent_then_write';
            }

            public function description(): string
            {
                return 'Cancels the parent before a fake local write.';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): string|Stringable
            {
                $this->parent->cancel();
                $child = Task::query()->where('parent_task_id', $this->parent->id)->sole();

                try {
                    (new ScriptCallbackReceiptService)->begin(
                        $this->agent,
                        $child->id,
                        hash('sha256', 'generic-subagent-cancel-test'),
                        Str::uuid()->toString(),
                        1,
                        'fake_local_write',
                        [],
                        null,
                    );
                    $this->writes++;
                } catch (ScriptDispatchException) {
                    $this->writeBlocked = true;
                }

                return $this->writeBlocked ? 'write blocked' : 'unexpected write';
            }
        };
        $registry = $this->registry([$tool]);
        $subagent = $this->subagent($registry, $parent->id);
        GenericSubagent::fake([
            new ToolCall('call-1', 'cancel_parent_then_write', []),
            'provider returned after cancellation',
        ])->preventStrayPrompts();

        try {
            $subagent->prompt('Cancel before writing.');
            $this->fail('Expected the parent cancellation to fail the delegated prompt.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('parent or delegated task stopped', strtolower($exception->getMessage()));
        }

        $child = Task::query()->where('parent_task_id', $parent->id)->sole();
        $this->assertSame(Task::STATUS_CANCELLED, $parent->fresh()->status);
        $this->assertSame(Task::STATUS_CANCELLED, $child->status);
        $this->assertTrue($tool->writeBlocked);
        $this->assertSame(0, $tool->writes);
        $this->assertSame('parent-channel', $registry->getChannelContext());
        $this->assertSame('parent-task', $registry->getTaskContext());
    }

    public function test_paused_parent_blocks_an_otherwise_active_child_receipt(): void
    {
        $parent = $this->parentTask();
        $child = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->peerAgent->workspace_id,
            'title' => 'Active delegated child',
            'description' => 'A child that remains active while its parent pauses.',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_AGENT_DELEGATION,
            'agent_id' => $this->peerAgent->id,
            'requester_id' => $parent->requester_id,
            'channel_id' => $this->channel->id,
            'parent_task_id' => $parent->id,
            'started_at' => now(),
        ]);
        $parent->pause();

        $this->expectException(ScriptDispatchException::class);
        (new ScriptCallbackReceiptService)->begin(
            $this->peerAgent,
            $child->id,
            hash('sha256', 'generic-subagent-paused-parent-test'),
            Str::uuid()->toString(),
            1,
            'fake_local_write',
            [],
            null,
        );
    }
}

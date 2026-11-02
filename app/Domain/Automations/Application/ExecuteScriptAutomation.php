<?php

namespace App\Domain\Automations\Application;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Tools\ToolRegistry;
use App\Events\AgentStatusUpdated;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Models\AgentRunRecord;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Services\CodeApiDocGenerator;
use App\Services\CodeBridge;
use App\Services\MrubySandboxService;
use App\Services\ScriptAdmission;
use App\Services\ScriptAutomationInvocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes one runtime-pinned Ruby automation through mruby on the durable run
 * control plane.
 *
 * The queue job owns timeout/uniqueness and workspace binding. This use case
 * owns the script lifecycle: source admission, the task/AgentRunRecord pair
 * keyed by the dispatch key, invoking the sandbox bridge, posting output, and
 * recording task/run/automation success or failure.
 *
 * Invariant: a script run has exactly one semantic attempt. A Ruby program may
 * already have performed external writes before a crash, and mruby callbacks
 * record write receipts rather than an exactly-once guarantee, so the whole
 * program is never re-executed automatically. A redelivered dispatch whose
 * attempt already started is failed as ambiguous instead.
 */
class ExecuteScriptAutomation
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private CodeApiDocGenerator $docGenerator,
        private MrubySandboxService $sandbox,
        private ResolveAutomationRun $runs,
    ) {}

    /**
     * @param  string|null  $dispatchKey  Stable key serialized with the queue command; one run per key
     * @param  string|null  $queueJobUuid  Physical queue job id recorded on the attempt
     * @param  array<string, mixed>|null  $sourceReceipt  Source/runtime receipt captured at dispatch time
     */
    public function handle(
        Automation $automation,
        ?string $dispatchKey = null,
        ?string $queueJobUuid = null,
        ?array $sourceReceipt = null,
    ): void {
        if (! app(ScriptAdmission::class)->matches($automation)) {
            $automation->recordFailure(
                'Automation is disabled until its exact Ruby source is validated with the current engine.',
                [
                    'script_runtime' => $automation->script_runtime,
                    'required_runtime' => config('code.runtime'),
                    'retryable' => false,
                ],
            );
            // Runtime incompatibility is not a transient failure. The generic
            // failure counter normally keeps early failures active, so enforce
            // the migration invariant after recording operator diagnostics.
            $automation->update(['is_active' => false]);

            return;
        }

        // Fence queued work whose source/runtime changed after dispatch: the
        // operator approved the code that was queued, not later edits.
        $current = Automation::query()->whereKey($automation->id)->first();
        if ($current === null
            || ($sourceReceipt !== null && ! ScriptAutomationInvocation::matches($current, $sourceReceipt))) {
            return;
        }

        $dispatchKey ??= (string) Str::uuid();

        try {
            $context = $this->runs->handle($automation, $dispatchKey, AgentRunRecord::KIND_SCRIPT_AUTOMATION, 1);
        } catch (\DomainException $exception) {
            $automation->recordFailure($exception->getMessage());
            Log::error('Script automation rejected before execution', [
                'automation' => $automation->id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $agent = $context->agent;
        $task = $context->task;
        $run = $context->run;
        $channelId = $context->channelId;

        if ($task->isTerminal()) {
            $run->reconcileFromTerminalTask();

            return;
        }
        if ($run->isTerminal()) {
            $run->reconcileTaskFromTerminalRun();

            return;
        }
        if ($run->attempt_count > 0) {
            // Redelivery after an attempt began: effects may already exist.
            $message = 'Script automation was interrupted after it started; it is not re-run automatically because external writes may already have happened. Inspect state before running it again.';
            $task->fail($message);
            $run->markFailed($message);
            $automation->recordFailure($message, ['retryable' => false, 'agent_run_id' => $run->id]);
            safeBroadcast(new TaskUpdated($task, 'failed'), 'ambiguous script redelivery');

            return;
        }
        if (in_array($agent->status, ['paused', 'offline'], true)) {
            $task->pause();
            $run->markWaiting();
            safeBroadcast(new TaskUpdated($task, 'paused'), 'script automation agent unavailable');

            return;
        }

        $run->beginAttempt($queueJobUuid);
        $activeRun = new ActiveAgentRun($run->id);
        app()->instance(ActiveAgentRun::class, $activeRun);

        try {
            if ($task->isPending()) {
                $task->start();
            }
            $task->update(['context' => array_merge($task->context ?? [], [
                'script_runtime' => $automation->script_runtime,
            ])]);
            safeBroadcast(new TaskUpdated($task, 'started'), 'script automation started');
            $agent->update(['status' => 'working']);
            safeBroadcast(new AgentStatusUpdated($agent), 'script automation agent working');

            $bridge = new CodeBridge(
                $agent,
                $this->toolRegistry,
                $this->docGenerator,
                $this->sandbox->profile('automation'),
            );
            $priorChannel = $this->toolRegistry->getChannelContext();
            $priorTask = $this->toolRegistry->getTaskContext();
            $this->toolRegistry->setChannelContext($channelId);
            $this->toolRegistry->setTaskContext($task->id);
            try {
                $result = $this->sandbox->execute(
                    code: (string) $automation->script,
                    profile: 'automation',
                    bridge: $bridge,
                    globals: ['ctx' => $this->scriptContext($automation)],
                    sourceName: 'automation-code.rb',
                    // Cancel and pause both stop the engine: a paused Ruby
                    // program cannot be resumed mid-execution.
                    cancelled: fn (): bool => $this->stopRequested($run, $task, $automation),
                );
            } finally {
                // ToolRegistry is shared by queue jobs. Approvals must route to
                // this automation's channel, never a previous job's context.
                $this->toolRegistry->setChannelContext($priorChannel);
                $this->toolRegistry->setTaskContext($priorTask);
            }

            $output = $this->renderOutput($result->output, $result->result);
            $bridgeCalls = $bridge->getCallLog();
            if ($result->error !== null) {
                $errorMessage = $this->formatError($result->error);
                $failureOutput = $this->renderFailureOutput($output, $errorMessage, $result->error);
                $this->postOutputMessage($agent, $channelId, $run, $failureOutput);
                $task->refresh();
                if (($result->error['type'] ?? null) === 'cancelled') {
                    if (! $task->isTerminal()) {
                        $task->cancel();
                    }
                    if (! $run->cancellationRequested()) {
                        $run->requestCancellation();
                    }
                } else {
                    if (! $task->isTerminal()) {
                        $task->fail();
                    }
                    $run->markFailed($errorMessage);
                }
                $task->update([
                    'result' => [
                        'error' => $result->error,
                        'output' => $failureOutput,
                        'execution_id' => $result->executionId,
                        'script_runtime' => $automation->script_runtime,
                        'execution_time_ms' => $result->executionTime,
                        'cpu_time_ms' => $result->cpuTime,
                        'memory_usage' => $result->memoryUsage,
                        'peak_memory_usage' => $result->peakMemoryUsage,
                        'effects' => $result->effects,
                        'bridge_calls' => $bridgeCalls,
                    ],
                ]);

                safeBroadcast(new TaskUpdated($task, $task->status), 'script task failure');
                $automation->recordFailure($errorMessage, [
                    'agent_run_id' => $run->id,
                    'execution_id' => $result->executionId,
                    'script_runtime' => $automation->script_runtime,
                    'runtime_error' => $result->error,
                    'effects' => $result->effects,
                ]);

                return;
            }

            $this->postOutputMessage($agent, $channelId, $run, $output);
            $task->complete([
                'output' => $output,
                'return_value' => $result->result,
                'execution_id' => $result->executionId,
                'script_runtime' => $automation->script_runtime,
                'execution_time_ms' => $result->executionTime,
                'cpu_time_ms' => $result->cpuTime,
                'memory_usage' => $result->memoryUsage,
                'peak_memory_usage' => $result->peakMemoryUsage,
                'effects' => $result->effects,
                'bridge_calls' => $bridgeCalls,
                'tool_calls_count' => count($bridgeCalls),
            ]);
            $run->markCompleted();

            safeBroadcast(new TaskUpdated($task, 'completed'), 'script task completion');

            $automation->recordSuccess([
                'task_id' => $task->id,
                'agent_run_id' => $run->id,
                'output' => Str::limit($output, 200),
                'return_value' => $result->result,
                'execution_id' => $result->executionId,
                'script_runtime' => $automation->script_runtime,
                'execution_time_ms' => $result->executionTime,
                'effects' => $result->effects,
                'bridge_calls_count' => count($bridgeCalls),
                'completed_at' => now()->toIso8601String(),
            ]);

            Log::info('Script automation completed', [
                'automation' => $automation->name,
                'task' => $task->id,
                'run' => $run->id,
                'execution_time_ms' => $result->executionTime,
            ]);
        } catch (\Throwable $e) {
            Log::error('Script automation failed', [
                'automation' => $automation->name,
                'error' => $e->getMessage(),
            ]);

            $task->refresh();
            if (! $task->isTerminal()) {
                $task->fail();
                $task->update(['result' => ['error' => $e->getMessage()]]);
            }
            $run->markFailed($e);
            safeBroadcast(new TaskUpdated($task, 'failed'), 'script task failure');
            $automation->recordFailure($e->getMessage());
        } finally {
            $agent->resolveIdleStatus();
            app()->forgetInstance(ActiveAgentRun::class);
        }
    }

    /**
     * Polled by the engine supervisor. Any stop signal (run cancellation, task
     * no longer active, paused task) ends the execution before more callbacks.
     */
    private function stopRequested(AgentRunRecord $run, Task $task, Automation $automation): bool
    {
        if (AgentRunRecord::query()->whereKey($run->id)->whereNotNull('cancel_requested_at')->exists()) {
            return true;
        }

        return ! Task::query()->whereKey($task->id)
            ->where('workspace_id', $automation->workspace_id)
            ->where('status', Task::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function scriptContext(Automation $automation): array
    {
        /** @var Carbon|null $lastRunAt */
        $lastRunAt = $automation->last_run_at;

        return [
            'automation_id' => $automation->id,
            'automation_name' => $automation->name,
            'agent_id' => $automation->agent_id,
            'channel_id' => $automation->channel_id,
            'run_number' => $automation->run_count + 1,
            'last_run_at' => $lastRunAt?->toIso8601String(),
            'last_result' => $automation->last_result,
            'trigger_type' => $automation->trigger_type,
            'schedule' => $automation->cron_expression,
            'timezone' => $automation->timezone,
            'script_runtime' => $automation->script_runtime,
        ];
    }

    /**
     * Post the run's output once; the agent_run_id correlation makes a repeated
     * call for the same run a no-op instead of a duplicate channel message.
     */
    private function postOutputMessage(User $agent, string $channelId, AgentRunRecord $run, string $output): void
    {
        if ($channelId === '' || $output === '') {
            return;
        }

        $agentMessage = Message::query()->firstOrCreate(
            ['agent_run_id' => $run->id],
            [
                'id' => (string) Str::uuid(),
                'content' => $output,
                'channel_id' => $channelId,
                'author_id' => $agent->id,
                'timestamp' => now(),
                'source' => 'automation',
            ],
        );

        if ($agentMessage->wasRecentlyCreated) {
            Channel::whereKey($channelId)->update(['last_message_at' => now()]);
            safeBroadcast(new MessageSent($agentMessage), 'script automation output');
        }
    }

    /**
     * Prefer explicit console output, but make a returned summary useful for
     * deterministic automations that do not log.
     */
    private function renderOutput(string $output, mixed $returnValue): string
    {
        $output = trim($output);
        if ($output !== '' || $returnValue === null) {
            return $output;
        }

        if (is_string($returnValue)) {
            return $returnValue;
        }

        return (string) json_encode(
            $returnValue,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Make runtime failures unmistakable in the automation channel while
     * preserving bounded partial console output that may help repair the code.
     * The sandbox has already redacted host errors before they reach this layer.
     *
     * @param  array<string, mixed>  $error
     */
    private function renderFailureOutput(string $output, string $errorMessage, array $error): string
    {
        $parts = [];
        if (trim($output) !== '') {
            $parts[] = trim($output);
        }

        $parts[] = $errorMessage;
        if (is_string($error['suggestion'] ?? null) && $error['suggestion'] !== '') {
            $parts[] = 'Repair: '.$error['suggestion'];
        }
        if (($error['effectStatus'] ?? null) === 'unknown') {
            $parts[] = 'Write status is unknown. Verify state with a read before considering a retry.';
        }

        return implode("\n\n", $parts);
    }

    /** @param  array<string, mixed>  $error */
    private function formatError(array $error): string
    {
        $location = isset($error['line'])
            ? ' at line '.$error['line'].(isset($error['column']) ? ':'.$error['column'] : '')
            : '';

        return '['.($error['type'] ?? 'runtime_error').']'.$location.': '.($error['message'] ?? 'Ruby execution failed.');
    }
}

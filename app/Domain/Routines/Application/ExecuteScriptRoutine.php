<?php

namespace App\Domain\Routines\Application;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Tools\ToolRegistry;
use App\Domain\Events\Causality;
use App\Events\AgentStatusUpdated;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Models\AgentRunRecord;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Services\CodeApiDocGenerator;
use App\Services\CodeBridge;
use App\Services\MrubySandboxService;
use App\Services\ScriptAdmission;
use App\Services\ScriptRoutineInvocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes one runtime-pinned Ruby routine through mruby on the durable run
 * control plane.
 *
 * The queue job owns timeout/uniqueness and workspace binding. This use case
 * owns the script lifecycle: source admission, the task/AgentRunRecord pair
 * keyed by the dispatch key, invoking the sandbox bridge, posting output, and
 * recording task/run/routine success or failure.
 *
 * Invariant: a script run has exactly one semantic attempt. A Ruby program may
 * already have performed external writes before a crash, and mruby callbacks
 * record write receipts rather than an exactly-once guarantee, so the whole
 * program is never re-executed automatically. A redelivered dispatch whose
 * attempt already started is failed as ambiguous instead.
 */
class ExecuteScriptRoutine
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private CodeApiDocGenerator $docGenerator,
        private MrubySandboxService $sandbox,
        private ResolveRoutineRun $runs,
    ) {}

    /**
     * @param  string|null  $dispatchKey  Stable key serialized with the queue command; one run per key
     * @param  string|null  $queueJobUuid  Physical queue job id recorded on the attempt
     * @param  array<string, mixed>|null  $sourceReceipt  Source/runtime receipt captured at dispatch time
     * @param  Causality|null  $causality  Chain of the triggering event; only used when the run is first created
     */
    public function handle(
        Routine $routine,
        ?string $dispatchKey = null,
        ?string $queueJobUuid = null,
        ?array $sourceReceipt = null,
        ?Causality $causality = null,
    ): void {
        if (! app(ScriptAdmission::class)->matches($routine)) {
            $routine->recordFailure(
                'Routine is disabled until its exact Ruby source is validated with the current engine.',
                [
                    'script_runtime' => $routine->script_runtime,
                    'required_runtime' => config('code.runtime'),
                    'retryable' => false,
                ],
            );
            // Runtime incompatibility is not a transient failure. The generic
            // failure counter normally keeps early failures active, so enforce
            // the migration invariant after recording operator diagnostics.
            $routine->update(['is_active' => false]);

            return;
        }

        // Fence queued work whose source/runtime changed after dispatch: the
        // operator approved the code that was queued, not later edits.
        $current = Routine::query()->whereKey($routine->id)->first();
        if ($current === null
            || ($sourceReceipt !== null && ! ScriptRoutineInvocation::matches($current, $sourceReceipt))) {
            return;
        }

        $dispatchKey ??= (string) Str::uuid();

        try {
            $context = $this->runs->handle($routine, $dispatchKey, AgentRunRecord::KIND_ROUTINE_SCRIPT, 1, $causality);
        } catch (\DomainException $exception) {
            $routine->recordFailure($exception->getMessage());
            Log::error('Script routine rejected before execution', [
                'routine' => $routine->id,
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
            $message = 'Script routine was interrupted after it started; it is not re-run automatically because external writes may already have happened. Inspect state before running it again.';
            $task->fail($message);
            $run->markFailed($message);
            $routine->recordFailure($message, ['retryable' => false, 'agent_run_id' => $run->id]);
            safeBroadcast(new TaskUpdated($task, 'failed'), 'ambiguous script redelivery');

            return;
        }
        if (in_array($agent->status, ['paused', 'offline'], true)) {
            $task->pause();
            $run->markWaiting();
            safeBroadcast(new TaskUpdated($task, 'paused'), 'script routine agent unavailable');

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
                'script_runtime' => $routine->script_runtime,
            ])]);
            safeBroadcast(new TaskUpdated($task, 'started'), 'script routine started');
            $agent->update(['status' => 'working']);
            safeBroadcast(new AgentStatusUpdated($agent), 'script routine agent working');

            $bridge = new CodeBridge(
                $agent,
                $this->toolRegistry,
                $this->docGenerator,
                $this->sandbox->profile('routine'),
            );
            $priorChannel = $this->toolRegistry->getChannelContext();
            $priorTask = $this->toolRegistry->getTaskContext();
            $this->toolRegistry->setChannelContext($channelId);
            $this->toolRegistry->setTaskContext($task->id);
            try {
                $result = $this->sandbox->execute(
                    code: (string) $routine->script,
                    profile: 'routine',
                    bridge: $bridge,
                    globals: ['ctx' => $this->scriptContext($routine)],
                    sourceName: 'routine-code.rb',
                    // Cancel and pause both stop the engine: a paused Ruby
                    // program cannot be resumed mid-execution.
                    cancelled: fn (): bool => $this->stopRequested($run, $task, $routine),
                );
            } finally {
                // ToolRegistry is shared by queue jobs. Approvals must route to
                // this routine's channel, never a previous job's context.
                $this->toolRegistry->setChannelContext($priorChannel);
                $this->toolRegistry->setTaskContext($priorTask);
            }

            $output = $this->renderOutput($result->output, $result->result);
            $bridgeCalls = $bridge->getCallLog();
            if ($result->error !== null) {
                $errorMessage = $this->formatError($result->error);
                $failureOutput = $this->renderFailureOutput($output, $errorMessage, $result->error);
                $this->postOutputMessage($routine, $agent, $channelId, $run, $failureOutput, failed: true);
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
                        'script_runtime' => $routine->script_runtime,
                        'execution_time_ms' => $result->executionTime,
                        'cpu_time_ms' => $result->cpuTime,
                        'memory_usage' => $result->memoryUsage,
                        'peak_memory_usage' => $result->peakMemoryUsage,
                        'effects' => $result->effects,
                        'bridge_calls' => $bridgeCalls,
                    ],
                ]);

                safeBroadcast(new TaskUpdated($task, $task->status), 'script task failure');
                $routine->recordFailure($errorMessage, [
                    'agent_run_id' => $run->id,
                    'execution_id' => $result->executionId,
                    'script_runtime' => $routine->script_runtime,
                    'runtime_error' => $result->error,
                    'effects' => $result->effects,
                ]);

                return;
            }

            $this->postOutputMessage($routine, $agent, $channelId, $run, $output, failed: false);
            $task->complete([
                'output' => $output,
                'return_value' => $result->result,
                'execution_id' => $result->executionId,
                'script_runtime' => $routine->script_runtime,
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

            $routine->recordSuccess([
                'task_id' => $task->id,
                'agent_run_id' => $run->id,
                'output' => Str::limit($output, 200),
                'return_value' => $result->result,
                'execution_id' => $result->executionId,
                'script_runtime' => $routine->script_runtime,
                'execution_time_ms' => $result->executionTime,
                'effects' => $result->effects,
                'bridge_calls_count' => count($bridgeCalls),
                'completed_at' => now()->toIso8601String(),
            ]);

            Log::info('Script routine completed', [
                'routine' => $routine->name,
                'task' => $task->id,
                'run' => $run->id,
                'execution_time_ms' => $result->executionTime,
            ]);
        } catch (\Throwable $e) {
            Log::error('Script routine failed', [
                'routine' => $routine->name,
                'error' => $e->getMessage(),
            ]);

            $task->refresh();
            if (! $task->isTerminal()) {
                $task->fail();
                $task->update(['result' => ['error' => $e->getMessage()]]);
            }
            $run->markFailed($e);
            safeBroadcast(new TaskUpdated($task, 'failed'), 'script task failure');
            $routine->recordFailure($e->getMessage());
        } finally {
            $agent->resolveIdleStatus();
            app()->forgetInstance(ActiveAgentRun::class);
        }
    }

    /**
     * Polled by the engine supervisor. Any stop signal (run cancellation, task
     * no longer active, paused task) ends the execution before more callbacks.
     */
    private function stopRequested(AgentRunRecord $run, Task $task, Routine $routine): bool
    {
        if (AgentRunRecord::query()->whereKey($run->id)->whereNotNull('cancel_requested_at')->exists()) {
            return true;
        }

        return ! Task::query()->whereKey($task->id)
            ->where('workspace_id', $routine->workspace_id)
            ->where('status', Task::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function scriptContext(Routine $routine): array
    {
        /** @var Carbon|null $lastRunAt */
        $lastRunAt = $routine->last_run_at;

        return [
            'routine_id' => $routine->id,
            'routine_name' => $routine->name,
            'agent_id' => $routine->agent_id,
            'channel_id' => $routine->channel_id,
            'run_number' => $routine->run_count + 1,
            'last_run_at' => $lastRunAt?->toIso8601String(),
            'last_result' => $routine->last_result,
            'routine_version' => $routine->version,
            'schedule' => $routine->cron_expression,
            'timezone' => $routine->timezone,
            'script_runtime' => $routine->script_runtime,
        ];
    }

    /**
     * Post the run's output once, subject to the routine's notify policy; the
     * agent_run_id correlation makes a repeated call for the same run a no-op
     * instead of a duplicate channel message.
     */
    private function postOutputMessage(Routine $routine, User $agent, string $channelId, AgentRunRecord $run, string $output, bool $failed): void
    {
        if ($channelId === '' || $output === '' || ! $routine->shouldNotify($output, $failed)) {
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
                'source' => 'routine',
            ],
        );

        if ($agentMessage->wasRecentlyCreated) {
            $routine->rememberPostedOutput($output);
            Channel::whereKey($channelId)->update(['last_message_at' => now()]);
            safeBroadcast(new MessageSent($agentMessage), 'script routine output');
        }
    }

    /**
     * Prefer explicit console output, but make a returned summary useful for
     * deterministic routines that do not log.
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
     * Make runtime failures unmistakable in the routine channel while
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

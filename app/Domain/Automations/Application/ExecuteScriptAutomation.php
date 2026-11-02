<?php

namespace App\Domain\Automations\Application;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Runtime\AgentRunCancelled;
use App\Agents\Runtime\AgentRunPaused;
use App\Agents\Tools\ToolRegistry;
use App\Domain\AgentRuntime\Application\RetryableRunFailure;
use App\Events\AgentStatusUpdated;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Models\AgentRunRecord;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Services\LuaApiDocGenerator;
use App\Services\LuaBridge;
use App\Services\LuaSandboxService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes one script automation through the same durable run control plane.
 *
 * Lua remains a separate execution engine, but task identity, semantic retry,
 * cancellation, output correlation, and workspace boundaries match prompt runs.
 */
class ExecuteScriptAutomation
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private LuaApiDocGenerator $docGenerator,
        private LuaSandboxService $luaSandbox,
        private ResolveAutomationRun $runs,
        private RetryableRunFailure $retryableFailures,
    ) {}

    public function handle(Automation $automation, ?string $dispatchKey = null, ?string $queueJobUuid = null, int $maxAttempts = 2): void
    {
        $dispatchKey ??= (string) Str::uuid();

        try {
            $context = $this->runs->handle(
                $automation,
                $dispatchKey,
                AgentRunRecord::KIND_SCRIPT_AUTOMATION,
                $maxAttempts,
            );
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
            $task = $task->fresh() ?? $task;
            safeBroadcast(new TaskUpdated($task, $task->status), 'terminal script run reconciliation');

            return;
        }

        if ($task->status === Task::STATUS_PAUSED) {
            return;
        }

        if (in_array($agent->status, ['paused', 'offline'], true)) {
            $task->pause();
            $run->markWaiting();
            safeBroadcast(new TaskUpdated($task, 'paused'), 'script automation agent unavailable');

            return;
        }

        $attempt = $run->beginAttempt($queueJobUuid);
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));

        try {
            if ($task->isPending()) {
                $task->start();
            }
            safeBroadcast(new TaskUpdated($task, 'started'), 'script automation started');

            $agent->update(['status' => 'working']);
            safeBroadcast(new AgentStatusUpdated($agent), 'script automation agent working');

            $bridge = new LuaBridge($agent, $this->toolRegistry, $this->docGenerator);
            $result = $this->luaSandbox->execute(
                $automation->script,
                ['cpuLimit' => 30.0],
                $bridge,
                ['ctx' => $this->scriptContext($automation)],
            );
            app(ActiveAgentRun::class)->checkpoint(force: true);

            $output = trim($result->output);
            $this->postOutputMessage($agent->id, $channelId, $run, $output);

            $bridgeCalls = $bridge->getCallLog();
            if ($result->error !== null) {
                $task->fail($result->error);
                $task->update([
                    'result' => [
                        'error' => $result->error,
                        'output' => $output,
                        'execution_time_ms' => $result->executionTime,
                        'memory_usage' => $result->memoryUsage,
                        'bridge_calls' => $bridgeCalls,
                    ],
                ]);
                $run->markFailed($result->error);

                safeBroadcast(new TaskUpdated($task, 'failed'), 'script task failure');
                $automation->recordFailure($result->error);

                return;
            }

            $task->complete([
                'output' => $output,
                'return_value' => $result->result,
                'execution_time_ms' => $result->executionTime,
                'memory_usage' => $result->memoryUsage,
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
                'execution_time_ms' => $result->executionTime,
                'bridge_calls_count' => count($bridgeCalls),
                'completed_at' => now()->toIso8601String(),
            ]);

            Log::info('Script automation completed', [
                'automation' => $automation->name,
                'task' => $task->id,
                'run' => $run->id,
                'execution_time_ms' => $result->executionTime,
            ]);
        } catch (AgentRunPaused) {
            $run->markWaiting();
            safeBroadcast(new TaskUpdated($task, 'paused'), 'script task paused');
        } catch (AgentRunCancelled) {
            if ($task->isTerminal()) {
                $run->reconcileFromTerminalTask();
            } else {
                $task->cancel();
                if (! $run->cancellationRequested()) {
                    $run->requestCancellation();
                }
            }
            $task = $task->fresh() ?? $task;
            safeBroadcast(new TaskUpdated($task, $task->status), 'script task stopped during run');
        } catch (\Throwable $exception) {
            Log::error('Script automation attempt failed', [
                'automation' => $automation->name,
                'attempt' => $attempt,
                'error' => $exception->getMessage(),
            ]);

            if ($attempt < $maxAttempts && $this->retryableFailures->handle($exception)) {
                $run->markRetrying($exception);
                safeBroadcast(new TaskUpdated($task, 'retrying'), 'script task retry');

                throw $exception;
            }

            if (! $task->isTerminal()) {
                $task->fail($exception->getMessage());
            }
            $run->markFailed($exception);
            $automation->recordFailure($exception->getMessage());
            safeBroadcast(new TaskUpdated($task, 'failed'), 'script task failure');
        } finally {
            $agent->resolveIdleStatus();
            app()->forgetInstance(ActiveAgentRun::class);
        }
    }

    /** @return array<string, mixed> */
    private function scriptContext(Automation $automation): array
    {
        /** @var Carbon|null $lastRunAt */
        $lastRunAt = $automation->last_run_at;

        return [
            'automation_id' => $automation->id,
            'automation_name' => $automation->name,
            'run_number' => $automation->run_count + 1,
            'last_run_at' => $lastRunAt?->toIso8601String(),
            'last_result' => $automation->last_result,
            'trigger_type' => $automation->trigger_type,
            'schedule' => $automation->cron_expression,
            'timezone' => $automation->timezone,
        ];
    }

    private function postOutputMessage(string $agentId, string $channelId, AgentRunRecord $run, string $output): void
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
                'author_id' => $agentId,
                'timestamp' => now(),
                'source' => 'automation',
            ],
        );

        if ($agentMessage->wasRecentlyCreated) {
            Channel::whereKey($channelId)->update(['last_message_at' => now()]);
            safeBroadcast(new MessageSent($agentMessage), 'script automation output');
        }
    }
}

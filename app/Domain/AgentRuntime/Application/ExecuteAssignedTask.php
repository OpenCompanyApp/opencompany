<?php

namespace App\Domain\AgentRuntime\Application;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Runtime\AgentRunBuilder;
use App\Agents\Runtime\AgentRunCancelled;
use App\Agents\Runtime\AgentRunOptions;
use App\Agents\Runtime\AgentRunPaused;
use App\Domain\Ai\Usage\UsageRecorder;
use App\Events\AgentStatusUpdated;
use App\Events\TaskUpdated;
use App\Models\AgentRunRecord;
use App\Models\Channel;
use App\Models\Task;
use App\Models\User;
use App\Support\TokenMetrics;
use Illuminate\Support\Facades\Log;

/**
 * Executes one assigned task through an OpenCompany agent.
 *
 * This use case owns the task lifecycle, prompt construction, status
 * transitions, runtime call, metrics capture, and failure recording for manual
 * or API-triggered task execution. The queue job remains responsible only for
 * transport concerns such as retries, timeout, and workspace binding.
 */
class ExecuteAssignedTask
{
    public function __construct(private RetryableRunFailure $retryableFailures) {}

    public function handle(Task $task, ?string $queueJobUuid = null, int $maxAttempts = 3): void
    {
        if ($task->isTerminal()) {
            $task->agentRun?->reconcileFromTerminalTask();

            return;
        }

        if ($task->status === Task::STATUS_PAUSED) {
            return;
        }

        $agent = User::query()
            ->where('workspace_id', $task->workspace_id)
            ->where('type', 'agent')
            ->find($task->agent_id);

        if (! $agent) {
            $task->fail('No workspace-scoped agent assigned to this task.');
            $task->addStep('No agent assigned to this task.', 'action');
            safeBroadcast(new TaskUpdated($task, 'failed'), 'assigned task missing agent');

            return;
        }

        if (in_array($agent->status, ['paused', 'offline'], true)) {
            if ($task->status !== Task::STATUS_PAUSED) {
                $task->pause();
            }
            $run = AgentRunRecord::ensureForTask($task, AgentRunRecord::KIND_ASSIGNED_TASK, $maxAttempts);
            $run->markWaiting();
            safeBroadcast(new TaskUpdated($task, 'paused'), 'assigned task agent unavailable');

            return;
        }

        if ($task->channel_id !== null && ! Channel::query()
            ->where('workspace_id', $task->workspace_id)
            ->whereKey($task->channel_id)
            ->exists()) {
            $task->fail('Assigned task channel is outside the task workspace.');
            safeBroadcast(new TaskUpdated($task, 'failed'), 'assigned task invalid channel');

            return;
        }

        $run = AgentRunRecord::ensureForTask($task, AgentRunRecord::KIND_ASSIGNED_TASK, $maxAttempts);
        if ($run->isTerminal()) {
            $run->reconcileTaskFromTerminalRun();
            $task = $task->fresh() ?? $task;
            safeBroadcast(new TaskUpdated($task, $task->status), 'terminal assigned run reconciliation');

            return;
        }

        $attempt = $run->beginAttempt($queueJobUuid);
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));

        try {
            if ($task->isPending()) {
                $task->start();
            }
            safeBroadcast(new TaskUpdated($task, 'started'), 'assigned task started');

            $analyzeStep = $task->addStep('Generating response', 'action');
            $prompt = $this->buildTaskPrompt($task);
            $channelId = $task->channel_id ?? $this->getDefaultChannel($agent);

            $agent->update(['status' => 'working']);
            broadcast(new AgentStatusUpdated($agent));

            $agentRun = app(AgentRunBuilder::class)->build(
                $agent,
                $channelId,
                $task->id,
                new AgentRunOptions(resumeFromTask: $attempt > 1),
            );
            $agentInstance = $agentRun->agent();
            $analyzeStep->start();
            $runResult = $agentRun->run($prompt);
            $response = $runResult->response;
            $analyzeStep->complete();

            $responseText = $response->text ?: 'Task processed (no text response).';
            $resultStep = $task->addStep('Task completed: '.substr($responseText, 0, 200), 'action');
            $resultStep->start();
            $resultStep->complete();

            $analyzeStep->refresh();
            $metrics = TokenMetrics::fromResponse($response, $analyzeStep->started_at, $analyzeStep->completed_at);
            app(UsageRecorder::class)->record(
                response: $response,
                task: $task,
                agent: $agent,
                purpose: 'assigned_task',
                requestedProvider: $agentInstance->provider(),
                requestedModel: $agentInstance->model(),
                metrics: $metrics,
            );

            $task->complete();
            $task->update([
                'result' => array_merge(['response' => $response->text], $metrics),
            ]);

            safeBroadcast(new TaskUpdated($task, 'completed'), 'task completion');
            $run->markCompleted();

            Log::info('Agent task completed', [
                'task' => $task->id,
                'agent' => $agent->name,
                'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
            ]);
        } catch (AgentRunPaused) {
            $run->markWaiting();
            safeBroadcast(new TaskUpdated($task, 'paused'), 'assigned task paused');
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
            safeBroadcast(new TaskUpdated($task, $task->status), 'assigned task stopped during run');
        } catch (\Throwable $e) {
            Log::error('Agent task failed', [
                'task' => $task->id,
                'agent' => $agent->name,
                'error' => $e->getMessage(),
            ]);

            if ($attempt < $maxAttempts && $this->retryableFailures->handle($e)) {
                $task->addStep("Retrying after provider error: {$e->getMessage()}", 'action')->complete();
                $run->markRetrying($e);
                safeBroadcast(new TaskUpdated($task, 'retrying'), 'assigned task retry');

                throw $e;
            }

            $task->addStep("Error: {$e->getMessage()}", 'action')->complete();
            $task->fail($e->getMessage());
            $run->markFailed($e);

            safeBroadcast(new TaskUpdated($task, 'failed'), 'task failure');

            throw $e;
        } finally {
            $agent->resolveIdleStatus();
            app()->forgetInstance(ActiveAgentRun::class);
        }
    }

    private function buildTaskPrompt(Task $task): string
    {
        $prompt = "You have been assigned the following task:\n\n";
        $prompt .= "**Title:** {$task->title}\n";
        $prompt .= "**Type:** {$task->type}\n";
        $prompt .= "**Priority:** {$task->priority}\n";

        if ($task->description) {
            $prompt .= "\n**Description:**\n{$task->description}\n";
        }

        if ($task->context) {
            $prompt .= "\n**Context:**\n".json_encode($task->context, JSON_PRETTY_PRINT)."\n";
        }

        $prompt .= "\nPlease analyze this task and provide a comprehensive response. ";
        $prompt .= 'If you need to search documents for context, use the search_documents tool. ';
        $prompt .= "Log your progress using the create_task_step tool with task ID: {$task->id}";

        return $prompt;
    }

    private function getDefaultChannel(User $agent): string
    {
        /** @var Channel|null $channel */
        $channel = $agent->channels()
            ->where('channels.workspace_id', $agent->workspace_id)
            ->first();
        if ($channel) {
            return $channel->id;
        }

        /** @var Channel|null $publicChannel */
        $publicChannel = Channel::forWorkspace()->where('type', 'public')->first();

        return $publicChannel->id ?? '';
    }
}

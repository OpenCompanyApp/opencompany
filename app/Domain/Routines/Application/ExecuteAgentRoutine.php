<?php

namespace App\Domain\Routines\Application;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Runtime\AgentRunBuilder;
use App\Agents\Runtime\AgentRunCancelled;
use App\Agents\Runtime\AgentRunOptions;
use App\Agents\Runtime\AgentRunPaused;
use App\Agents\Tools\ToolRegistry;
use App\Domain\AgentRuntime\Application\RetryableRunFailure;
use App\Domain\Ai\Usage\UsageRecorder;
use App\Domain\Events\Causality;
use App\Events\AgentStatusUpdated;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Models\AgentRunRecord;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Routine;
use App\Models\Task;
use App\Support\TokenMetrics;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes one agent routine (kind `agent`) through the shared AgentRun runtime.
 *
 * ResolveRoutineRun provides a stable task/run pair across queue retries.
 * This service owns instruction/output delivery, the notify policy for the
 * routine's output message, model evidence, accounting, and terminal routine
 * counters; queue reservation remains in RunRoutineJob.
 *
 * Invariant: a trigger is never consent. The run executes with the agent's
 * ordinary grants and approval policy; the schedule or event that started it
 * authorises nothing (UntrustedContent states this to the model).
 */
class ExecuteAgentRoutine
{
    public function __construct(
        private ResolveRoutineRun $runs,
        private RetryableRunFailure $retryableFailures,
    ) {}

    /**
     * @param  string|null  $dispatchKey  Stable key claimed by the tick or manual run; one run per key
     * @param  string|null  $queueJobUuid  Physical queue job id recorded on the attempt
     * @param  Causality|null  $causality  Chain of the triggering event; only used when the run is first created
     *
     * @throws \Throwable rethrown only for a retryable provider failure with attempts left
     */
    public function handle(
        Routine $routine,
        ?string $dispatchKey = null,
        ?string $queueJobUuid = null,
        int $maxAttempts = 2,
        ?Causality $causality = null,
    ): void {
        $dispatchKey ??= (string) Str::uuid();

        try {
            $context = $this->runs->handle(
                $routine,
                $dispatchKey,
                AgentRunRecord::KIND_ROUTINE_AGENT,
                $maxAttempts,
                $causality,
            );
        } catch (\DomainException $exception) {
            $routine->recordFailure($exception->getMessage());
            Log::error('Routine rejected before execution', [
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
            $task = $task->fresh() ?? $task;
            safeBroadcast(new TaskUpdated($task, $task->status), 'terminal routine run reconciliation');

            return;
        }

        if ($task->status === Task::STATUS_PAUSED) {
            return;
        }

        if (in_array($agent->status, ['paused', 'offline'], true)) {
            $task->pause();
            $run->markWaiting();
            safeBroadcast(new TaskUpdated($task, 'paused'), 'routine agent unavailable');

            return;
        }

        $attempt = $run->beginAttempt($queueJobUuid);
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));

        try {
            if ($task->isPending()) {
                $task->start();
            }
            safeBroadcast(new TaskUpdated($task, 'started'), 'routine task started');

            $promptMessage = Message::query()->firstOrCreate(
                [
                    'channel_id' => $channelId,
                    'external_message_id' => "agent-run:{$run->id}:prompt",
                ],
                [
                    'id' => (string) Str::uuid(),
                    'content' => $routine->instructions,
                    'author_id' => $routine->created_by_id,
                    'timestamp' => now(),
                    'source' => 'routine_prompt',
                ],
            );
            if ($promptMessage->wasRecentlyCreated) {
                safeBroadcast(new MessageSent($promptMessage), 'routine prompt');
            }

            $agent->update(['status' => 'working']);
            safeBroadcast(new AgentStatusUpdated($agent), 'routine agent working');

            $agentRun = app(AgentRunBuilder::class)->build(
                $agent,
                $channelId,
                $task->id,
                new AgentRunOptions(resumeFromTask: $attempt > 1),
            );
            $agentInstance = $agentRun->agent();

            try {
                $toolRegistry = app(ToolRegistry::class);
                $promptFrame = $agentInstance->promptFrame();
                $task->update([
                    'context' => array_merge($task->context ?? [], [
                        'system_prompt' => $agentInstance->instructions(),
                        'full_system_prompt' => $agentInstance->fullInstructions(),
                        'volatile_prompt_context' => $agentInstance->volatilePromptContext(),
                        'tools' => $toolRegistry->getToolSlugsForAgent($agent),
                        'model' => $agentInstance->model(),
                        'provider' => $agentInstance->provider(),
                        'prompt_sections' => $promptFrame['stable_breakdown'],
                        'volatile_prompt_sections' => $promptFrame['volatile_breakdown'],
                    ]),
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Failed to capture routine LLM context', ['error' => $exception->getMessage()]);
            }

            $generationStartedAt = now();
            $runResult = $agentRun->run($this->buildScheduledPrompt($routine));
            $response = $runResult->response;
            $generationCompletedAt = now();
            app(ActiveAgentRun::class)->checkpoint(force: true);

            $responseText = trim($response->text ?? '');
            if ($responseText !== '') {
                $this->postOutput($routine, $agent->id, $channelId, $run, $responseText, failed: false);
            }

            $metrics = TokenMetrics::fromResponse($response, $generationStartedAt, $generationCompletedAt);
            app(UsageRecorder::class)->record(
                response: $response,
                task: $task,
                agent: $agent,
                purpose: 'routine',
                requestedProvider: $agentInstance->provider(),
                requestedModel: $agentInstance->model(),
                metrics: $metrics,
            );
            $task->complete(array_merge(
                ['response' => Str::limit($response->text, 500)],
                $metrics,
            ));
            $run->markCompleted();

            safeBroadcast(new TaskUpdated($task, 'completed'), 'routine task completion');

            $routine->recordSuccess([
                'task_id' => $task->id,
                'agent_run_id' => $run->id,
                'response_preview' => Str::limit($response->text, 200),
                'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
                'tool_calls' => $metrics['tool_calls_count'],
                'completed_at' => now()->toIso8601String(),
            ]);

            Log::info('Routine completed', [
                'routine' => $routine->name,
                'agent' => $agent->name,
                'task' => $task->id,
                'run' => $run->id,
            ]);
        } catch (AgentRunPaused) {
            $run->markWaiting();
            safeBroadcast(new TaskUpdated($task, 'paused'), 'routine task paused');
        } catch (AgentRunCancelled) {
            // Re-read: the cancellation may have been applied to the task
            // by another process while the runtime was executing.
            $task = $task->fresh() ?? $task;
            if ($task->isTerminal()) {
                $run->reconcileFromTerminalTask();
            } else {
                $task->cancel();
                if (! $run->cancellationRequested()) {
                    $run->requestCancellation();
                }
            }
            $task = $task->fresh() ?? $task;
            safeBroadcast(new TaskUpdated($task, $task->status), 'routine task stopped during run');
        } catch (\Throwable $exception) {
            Log::error('Routine attempt failed', [
                'routine' => $routine->name,
                'agent' => $agent->name,
                'attempt' => $attempt,
                'error' => $exception->getMessage(),
            ]);

            if ($attempt < $maxAttempts && $this->retryableFailures->handle($exception)) {
                $run->markRetrying($exception);
                safeBroadcast(new TaskUpdated($task, 'retrying'), 'routine task retry');

                throw $exception;
            }

            $task = $task->fresh() ?? $task;
            if (! $task->isTerminal()) {
                $task->fail($exception->getMessage());
            }
            $run->markFailed($exception);
            $routine->recordFailure($exception->getMessage());
            $this->postOutput($routine, $agent->id, $channelId, $run, 'Routine run failed: '.Str::limit($exception->getMessage(), 500), failed: true);
            safeBroadcast(new TaskUpdated($task, 'failed'), 'routine task failure');
        } finally {
            $agent->resolveIdleStatus();
            app()->forgetInstance(ActiveAgentRun::class);
        }
    }

    /**
     * Post the routine's output message at most once per run, subject to the
     * notify policy. The agent_run_id correlation makes a repeated call for
     * the same run a no-op instead of a duplicate channel message.
     */
    private function postOutput(Routine $routine, string $authorId, string $channelId, AgentRunRecord $run, string $content, bool $failed): void
    {
        if (! $routine->shouldNotify($content, $failed)) {
            return;
        }

        $message = Message::query()->firstOrCreate(
            ['agent_run_id' => $run->id],
            [
                'id' => (string) Str::uuid(),
                'content' => $content,
                'channel_id' => $channelId,
                'author_id' => $authorId,
                'timestamp' => now(),
                'source' => 'routine',
            ],
        );

        if ($message->wasRecentlyCreated) {
            $routine->rememberPostedOutput($content);
            Channel::whereKey($channelId)->update(['last_message_at' => now()]);
            safeBroadcast(new MessageSent($message), 'routine response');
        }
    }

    private function buildScheduledPrompt(Routine $routine): string
    {
        $prompt = "This is a scheduled routine run.\n\n";
        $prompt .= "**Routine:** {$routine->name} (version {$routine->version})\n";
        $prompt .= '**Run #:** '.($routine->run_count + 1)."\n";

        if ($routine->last_run_at) {
            /** @var Carbon $lastRunAt */
            $lastRunAt = $routine->last_run_at;
            $prompt .= "**Last run:** {$lastRunAt->diffForHumans()}\n";
        }

        return $prompt."\n---\n\n".$routine->instructions;
    }
}

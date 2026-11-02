<?php

namespace App\Domain\Automations\Application;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Runtime\AgentRunBuilder;
use App\Agents\Runtime\AgentRunCancelled;
use App\Agents\Runtime\AgentRunOptions;
use App\Agents\Runtime\AgentRunPaused;
use App\Agents\Tools\ToolRegistry;
use App\Domain\AgentRuntime\Application\RetryableRunFailure;
use App\Domain\Ai\Usage\UsageRecorder;
use App\Events\AgentStatusUpdated;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Models\AgentRunRecord;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Support\TokenMetrics;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes one prompt automation through the shared AgentRun runtime.
 *
 * ResolveAutomationRun provides a stable task/run pair across queue retries.
 * This service owns prompt/output delivery, model evidence, accounting, and
 * terminal automation counters; queue reservation remains in RunAutomationJob.
 */
class ExecutePromptAutomation
{
    public function __construct(
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
                AgentRunRecord::KIND_PROMPT_AUTOMATION,
                $maxAttempts,
            );
        } catch (\DomainException $exception) {
            $automation->recordFailure($exception->getMessage());
            Log::error('Automation rejected before execution', [
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
            safeBroadcast(new TaskUpdated($task, $task->status), 'terminal automation run reconciliation');

            return;
        }

        if ($task->status === Task::STATUS_PAUSED) {
            return;
        }

        if (in_array($agent->status, ['paused', 'offline'], true)) {
            $task->pause();
            $run->markWaiting();
            safeBroadcast(new TaskUpdated($task, 'paused'), 'automation agent unavailable');

            return;
        }

        $attempt = $run->beginAttempt($queueJobUuid);
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));

        try {
            if ($task->isPending()) {
                $task->start();
            }
            safeBroadcast(new TaskUpdated($task, 'started'), 'automation task started');

            $promptMessage = Message::query()->firstOrCreate(
                [
                    'channel_id' => $channelId,
                    'external_message_id' => "agent-run:{$run->id}:prompt",
                ],
                [
                    'id' => (string) Str::uuid(),
                    'content' => $automation->prompt,
                    'author_id' => $automation->created_by_id,
                    'timestamp' => now(),
                    'source' => 'automation_prompt',
                ],
            );
            if ($promptMessage->wasRecentlyCreated) {
                safeBroadcast(new MessageSent($promptMessage), 'automation prompt');
            }

            $agent->update(['status' => 'working']);
            safeBroadcast(new AgentStatusUpdated($agent), 'automation agent working');

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
                Log::warning('Failed to capture automation LLM context', ['error' => $exception->getMessage()]);
            }

            $generationStartedAt = now();
            $runResult = $agentRun->run($this->buildScheduledPrompt($automation));
            $response = $runResult->response;
            $generationCompletedAt = now();
            app(ActiveAgentRun::class)->checkpoint(force: true);

            if (! empty(trim($response->text ?? ''))) {
                $agentMessage = Message::query()->firstOrCreate(
                    ['agent_run_id' => $run->id],
                    [
                        'id' => (string) Str::uuid(),
                        'content' => $response->text,
                        'channel_id' => $channelId,
                        'author_id' => $agent->id,
                        'timestamp' => now(),
                        'source' => 'automation',
                    ],
                );

                if ($agentMessage->wasRecentlyCreated) {
                    Channel::whereKey($channelId)->update(['last_message_at' => now()]);
                    safeBroadcast(new MessageSent($agentMessage), 'automation response');
                }
            }

            $metrics = TokenMetrics::fromResponse($response, $generationStartedAt, $generationCompletedAt);
            app(UsageRecorder::class)->record(
                response: $response,
                task: $task,
                agent: $agent,
                purpose: 'automation',
                requestedProvider: $agentInstance->provider(),
                requestedModel: $agentInstance->model(),
                metrics: $metrics,
            );
            $task->complete(array_merge(
                ['response' => Str::limit($response->text, 500)],
                $metrics,
            ));
            $run->markCompleted();

            safeBroadcast(new TaskUpdated($task, 'completed'), 'automation task completion');

            $automation->recordSuccess([
                'task_id' => $task->id,
                'agent_run_id' => $run->id,
                'response_preview' => Str::limit($response->text, 200),
                'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
                'tool_calls' => $metrics['tool_calls_count'],
                'completed_at' => now()->toIso8601String(),
            ]);

            Log::info('Automation completed', [
                'automation' => $automation->name,
                'agent' => $agent->name,
                'task' => $task->id,
                'run' => $run->id,
            ]);
        } catch (AgentRunPaused) {
            $run->markWaiting();
            safeBroadcast(new TaskUpdated($task, 'paused'), 'automation task paused');
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
            safeBroadcast(new TaskUpdated($task, $task->status), 'automation task stopped during run');
        } catch (\Throwable $exception) {
            Log::error('Automation attempt failed', [
                'automation' => $automation->name,
                'agent' => $agent->name,
                'attempt' => $attempt,
                'error' => $exception->getMessage(),
            ]);

            if ($attempt < $maxAttempts && $this->retryableFailures->handle($exception)) {
                $run->markRetrying($exception);
                safeBroadcast(new TaskUpdated($task, 'retrying'), 'automation task retry');

                throw $exception;
            }

            if (! $task->isTerminal()) {
                $task->fail($exception->getMessage());
            }
            $run->markFailed($exception);
            $automation->recordFailure($exception->getMessage());
            safeBroadcast(new TaskUpdated($task, 'failed'), 'automation task failure');
        } finally {
            $agent->resolveIdleStatus();
            app()->forgetInstance(ActiveAgentRun::class);
        }
    }

    private function buildScheduledPrompt(Automation $automation): string
    {
        $prompt = "This is a scheduled automation run.\n\n";
        $prompt .= "**Schedule:** {$automation->name}\n";
        $prompt .= '**Run #:** '.($automation->run_count + 1)."\n";

        if ($automation->last_run_at) {
            /** @var Carbon $lastRunAt */
            $lastRunAt = $automation->last_run_at;
            $prompt .= "**Last run:** {$lastRunAt->diffForHumans()}\n";
        }

        return $prompt."\n---\n\n".$automation->prompt;
    }
}

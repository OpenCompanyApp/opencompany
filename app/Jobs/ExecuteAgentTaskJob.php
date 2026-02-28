<?php

namespace App\Jobs;

use App\Agents\OpenCompanyAgent;
use App\Events\AgentStatusUpdated;
use App\Events\TaskUpdated;
use App\Models\Channel;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteAgentTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800;
    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public function __construct(
        private Task $task,
    ) {}

    public function handle(): void
    {
        // Set workspace context from task
        if ($this->task->workspace_id) {
            $workspace = Workspace::find($this->task->workspace_id);
            if ($workspace) {
                app()->instance('currentWorkspace', $workspace);
            }
        }

        $agent = User::find($this->task->agent_id);

        if (!$agent) {
            $this->task->fail();
            $this->task->addStep('No agent assigned to this task.', 'action');
            broadcast(new TaskUpdated($this->task, 'failed'));
            return;
        }

        try {
            // Start the task
            $this->task->start();
            broadcast(new TaskUpdated($this->task, 'started'));

            // Log initial step
            $analyzeStep = $this->task->addStep('Generating response', 'action');

            // Build prompt from task details
            $prompt = $this->buildTaskPrompt();

            // Determine channel for the agent (use task's linked channel or a default)
            $channelId = $this->task->channel_id ?? $this->getDefaultChannel($agent);

            // Create agent and execute
            $agent->update(['status' => 'working']);
            broadcast(new AgentStatusUpdated($agent));

            $agentInstance = OpenCompanyAgent::for($agent, $channelId, $this->task->id);
            $analyzeStep->start();
            $response = $agentInstance->prompt($prompt);
            $analyzeStep->complete();

            // Log the result
            $responseText = $response->text ?: 'Task processed (no text response).';
            $resultStep = $this->task->addStep('Task completed: ' . substr($responseText, 0, 200), 'action');
            $resultStep->start();
            $resultStep->complete();

            // Compute generation timing
            $analyzeStep->refresh();
            /** @var \Carbon\Carbon|null $stepStartedAt */
            $stepStartedAt = $analyzeStep->started_at;
            /** @var \Carbon\Carbon|null $stepCompletedAt */
            $stepCompletedAt = $analyzeStep->completed_at;
            $generationTimeMs = $stepStartedAt && $stepCompletedAt
                ? $stepStartedAt->diffInMilliseconds($stepCompletedAt)
                : null;
            $completionTokens = $response->usage->completionTokens;
            $tokensPerSecond = ($generationTimeMs && $generationTimeMs > 0 && $completionTokens > 0)
                ? round($completionTokens / ($generationTimeMs / 1000), 1)
                : null;

            // Complete the task with result
            $this->task->complete();
            $this->task->update([
                'result' => [
                    'response' => $response->text,
                    'prompt_tokens' => $response->usage->promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_prompt_tokens' => $response->usage->promptTokens,
                    'total_completion_tokens' => $completionTokens,
                    'cache_read_tokens' => $response->usage->cacheReadInputTokens,
                    'cache_write_tokens' => $response->usage->cacheWriteInputTokens,
                    'tool_calls_count' => collect($response->steps)->sum(fn ($step) => count($step->toolCalls)),
                    'generation_time_ms' => $generationTimeMs,
                    'tokens_per_second' => $tokensPerSecond,
                ],
            ]);

            try {
                broadcast(new TaskUpdated($this->task, 'completed'));
            } catch (\Throwable $broadcastError) {
                Log::warning('Failed to broadcast task completion', ['error' => $broadcastError->getMessage()]);
            }

            Log::info('Agent task completed', [
                'task' => $this->task->id,
                'agent' => $agent->name,
                'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
            ]);
        } catch (\Throwable $e) {
            Log::error('Agent task failed', [
                'task' => $this->task->id,
                'agent' => $agent->name,
                'error' => $e->getMessage(),
            ]);

            // Log failure step
            $this->task->addStep("Error: {$e->getMessage()}", 'action');

            // Fail the task
            $this->task->fail();
            $this->task->update([
                'result' => ['error' => $e->getMessage()],
            ]);

            try {
                broadcast(new TaskUpdated($this->task, 'failed'));
            } catch (\Throwable $broadcastError) {
                Log::warning('Failed to broadcast task failure', ['error' => $broadcastError->getMessage()]);
            }

            throw $e;
        } finally {
            $agent->refresh();

            if ($agent->sleeping_until) {
                $agent->update(['status' => 'sleeping']);
            } elseif ($agent->awaiting_approval_id) {
                $agent->update(['status' => 'awaiting_approval']);
            } elseif (!empty($agent->awaiting_delegation_ids)) {
                $agent->update(['status' => 'awaiting_delegation']);
            } else {
                $agent->update(['status' => 'idle']);
            }

            try {
                broadcast(new AgentStatusUpdated($agent));
            } catch (\Throwable $broadcastError) {
                Log::warning('Failed to broadcast agent status', ['error' => $broadcastError->getMessage()]);
            }
        }
    }

    private function buildTaskPrompt(): string
    {
        $prompt = "You have been assigned the following task:\n\n";
        $prompt .= "**Title:** {$this->task->title}\n";
        $prompt .= "**Type:** {$this->task->type}\n";
        $prompt .= "**Priority:** {$this->task->priority}\n";

        if ($this->task->description) {
            $prompt .= "\n**Description:**\n{$this->task->description}\n";
        }

        if ($this->task->context) {
            $prompt .= "\n**Context:**\n" . json_encode($this->task->context, JSON_PRETTY_PRINT) . "\n";
        }

        $prompt .= "\nPlease analyze this task and provide a comprehensive response. ";
        $prompt .= "If you need to search documents for context, use the search_documents tool. ";
        $prompt .= "Log your progress using the create_task_step tool with task ID: {$this->task->id}";

        return $prompt;
    }

    private function getDefaultChannel(User $agent): string
    {
        /** @var Channel|null $channel */
        $channel = $agent->channels()->first();
        if ($channel) {
            return $channel->id;
        }

        /** @var Channel|null $publicChannel */
        $publicChannel = Channel::forWorkspace()->where('type', 'public')->first();
        return $publicChannel->id ?? '';
    }
}

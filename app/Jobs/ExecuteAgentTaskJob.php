<?php

namespace App\Jobs;

use App\Agents\OpenCompanyAgent;
use App\Events\AgentStatusUpdated;
use App\Events\TaskUpdated;
use App\Models\Channel;
use App\Models\Task;
use App\Models\User;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Support\TokenMetrics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteAgentTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SetsWorkspaceContext;

    public int $tries = 3;
    public int $timeout = 1800;
    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public function __construct(
        private Task $task,
    ) {}

    public function handle(): void
    {
        $this->setWorkspaceContext($this->task->workspace_id);

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

            // Complete the task with result
            $analyzeStep->refresh();
            $metrics = TokenMetrics::fromResponse($response, $analyzeStep->started_at, $analyzeStep->completed_at);

            $this->task->complete();
            $this->task->update([
                'result' => array_merge(['response' => $response->text], $metrics),
            ]);

            safeBroadcast(new TaskUpdated($this->task, 'completed'), 'task completion');

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

            safeBroadcast(new TaskUpdated($this->task, 'failed'), 'task failure');

            throw $e;
        } finally {
            $agent->resolveIdleStatus();
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

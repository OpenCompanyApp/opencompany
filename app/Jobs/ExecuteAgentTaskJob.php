<?php

namespace App\Jobs;

use App\Agents\OpenCompanyAgent;
use App\Events\TaskUpdated;
use App\Models\Channel;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteAgentTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        private Task $task,
    ) {}

    public function handle(): void
    {
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
            $analyzeStep = $this->task->addStep('Analyzing task requirements', 'action');
            $analyzeStep->start();

            // Build prompt from task details
            $prompt = $this->buildTaskPrompt();

            // Determine channel for the agent (use task's linked channel or a default)
            $channelId = $this->task->channel_id ?? $this->getDefaultChannel($agent);

            // Create agent and execute
            $agent->update(['status' => 'working']);

            $agentInstance = OpenCompanyAgent::for($agent, $channelId);
            $response = $agentInstance->prompt($prompt);

            $analyzeStep->complete();

            // Log the result
            $resultStep = $this->task->addStep('Task completed: ' . substr($response->text, 0, 200), 'action');
            $resultStep->start();
            $resultStep->complete();

            // Complete the task with result
            $this->task->complete();
            $this->task->update([
                'result' => [
                    'response' => $response->text,
                    'tokens' => $response->usage?->totalTokens ?? null,
                ],
            ]);

            broadcast(new TaskUpdated($this->task, 'completed'));

            Log::info('Agent task completed', [
                'task' => $this->task->id,
                'agent' => $agent->name,
                'tokens' => $response->usage?->totalTokens ?? 'unknown',
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

            broadcast(new TaskUpdated($this->task, 'failed'));
        } finally {
            $agent->update(['status' => 'idle']);
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
        $channelMember = $agent->channels()->first();
        if ($channelMember) {
            return $channelMember->id;
        }

        $publicChannel = Channel::where('type', 'public')->first();
        return $publicChannel?->id ?? '';
    }
}

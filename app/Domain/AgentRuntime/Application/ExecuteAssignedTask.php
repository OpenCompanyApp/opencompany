<?php

namespace App\Domain\AgentRuntime\Application;

use App\Agents\OpenCompanyAgent;
use App\Ai\Prompting\SystemPromptBag;
use App\Domain\Ai\Usage\UsageRecorder;
use App\Events\AgentStatusUpdated;
use App\Events\TaskUpdated;
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
    public function handle(Task $task): void
    {
        $agent = User::find($task->agent_id);

        if (! $agent) {
            $task->fail();
            $task->addStep('No agent assigned to this task.', 'action');
            broadcast(new TaskUpdated($task, 'failed'));

            return;
        }

        try {
            $task->start();
            broadcast(new TaskUpdated($task, 'started'));

            $analyzeStep = $task->addStep('Generating response', 'action');
            $prompt = $this->buildTaskPrompt($task);
            $channelId = $task->channel_id ?? $this->getDefaultChannel($agent);

            $agent->update(['status' => 'working']);
            broadcast(new AgentStatusUpdated($agent));

            $agentInstance = OpenCompanyAgent::for($agent, $channelId, $task->id);
            $analyzeStep->start();
            app()->instance(SystemPromptBag::class, new SystemPromptBag(
                $agentInstance->systemPrompts()
            ));

            $response = $agentInstance->prompt($prompt);
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

            Log::info('Agent task completed', [
                'task' => $task->id,
                'agent' => $agent->name,
                'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
            ]);
        } catch (\Throwable $e) {
            Log::error('Agent task failed', [
                'task' => $task->id,
                'agent' => $agent->name,
                'error' => $e->getMessage(),
            ]);

            $task->addStep("Error: {$e->getMessage()}", 'action');
            $task->fail();
            $task->update([
                'result' => ['error' => $e->getMessage()],
            ]);

            safeBroadcast(new TaskUpdated($task, 'failed'), 'task failure');

            throw $e;
        } finally {
            $agent->resolveIdleStatus();
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
        $channel = $agent->channels()->first();
        if ($channel) {
            return $channel->id;
        }

        /** @var Channel|null $publicChannel */
        $publicChannel = Channel::forWorkspace()->where('type', 'public')->first();

        return $publicChannel->id ?? '';
    }
}

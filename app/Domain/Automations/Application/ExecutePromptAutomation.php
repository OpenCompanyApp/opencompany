<?php

namespace App\Domain\Automations\Application;

use App\Agents\OpenCompanyAgent;
use App\Agents\Tools\ToolRegistry;
use App\Ai\Prompting\SystemPromptBag;
use App\Domain\Ai\Usage\UsageRecorder;
use App\Events\AgentStatusUpdated;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Support\TokenMetrics;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes one prompt-based scheduled automation run.
 *
 * Queue transport, uniqueness, retries, and workspace binding are handled by
 * RunAutomationJob. This service owns the OpenCompany automation workflow:
 * creating the run task, posting prompt/output messages, invoking the agent,
 * recording metrics, and updating automation success/failure counters.
 */
class ExecutePromptAutomation
{
    public function handle(Automation $automation, int $attempts = 1): void
    {
        $agent = User::find($automation->agent_id);

        if (! $agent) {
            $automation->recordFailure('Agent not found');

            return;
        }

        if ($attempts > 1 && $automation->channel_id) {
            $hasRecentResponse = Message::where('channel_id', $automation->channel_id)
                ->where('source', 'automation')
                ->where('created_at', '>', now()->subMinutes(30))
                ->exists();

            if ($hasRecentResponse) {
                Log::info('Skipping automation retry - response already exists', [
                    'automation' => $automation->name,
                    'attempt' => $attempts,
                ]);

                return;
            }
        }

        try {
            $channelId = $automation->ensureChannel();
            $task = Task::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $automation->workspace_id,
                'title' => "Scheduled: {$automation->name}",
                'description' => $automation->prompt,
                'type' => Task::TYPE_CUSTOM,
                'status' => Task::STATUS_ACTIVE,
                'priority' => Task::PRIORITY_NORMAL,
                'source' => Task::SOURCE_AUTOMATION,
                'agent_id' => $agent->id,
                'requester_id' => $automation->created_by_id,
                'channel_id' => $channelId,
                'started_at' => now(),
                'context' => [
                    'automation_id' => $automation->id,
                    'schedule' => $automation->cron_expression,
                    'run_number' => $automation->run_count + 1,
                ],
            ]);

            if ($channelId) {
                $promptMessage = Message::create([
                    'id' => Str::uuid()->toString(),
                    'content' => $automation->prompt,
                    'channel_id' => $channelId,
                    'author_id' => $automation->created_by_id,
                    'timestamp' => now(),
                    'source' => 'automation_prompt',
                ]);
                broadcast(new MessageSent($promptMessage));
            }

            $agent->update(['status' => 'working']);
            broadcast(new AgentStatusUpdated($agent));

            $agentInstance = OpenCompanyAgent::for($agent, $channelId, $task->id);

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
            } catch (\Throwable $e) {
                Log::warning('Failed to capture automation LLM context', ['error' => $e->getMessage()]);
            }

            $generationStartedAt = now();
            app()->instance(SystemPromptBag::class, new SystemPromptBag($agentInstance->systemPrompts()));
            $response = $agentInstance->prompt($this->buildScheduledPrompt($automation));
            $generationCompletedAt = now();

            if ($channelId && ! empty(trim($response->text ?? ''))) {
                $agentMessage = Message::create([
                    'id' => Str::uuid()->toString(),
                    'content' => $response->text,
                    'channel_id' => $channelId,
                    'author_id' => $agent->id,
                    'timestamp' => now(),
                    'source' => 'automation',
                ]);

                Channel::where('id', $channelId)->update(['last_message_at' => now()]);
                broadcast(new MessageSent($agentMessage));
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

            safeBroadcast(new TaskUpdated($task, 'completed'), 'automation task completion');

            $automation->recordSuccess([
                'task_id' => $task->id,
                'response_preview' => Str::limit($response->text, 200),
                'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
                'tool_calls' => $metrics['tool_calls_count'],
                'completed_at' => now()->toIso8601String(),
            ]);

            Log::info('Automation completed', [
                'automation' => $automation->name,
                'agent' => $agent->name,
                'task' => $task->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Automation failed', [
                'automation' => $automation->name,
                'agent' => $agent->name,
                'error' => $e->getMessage(),
            ]);

            if (isset($task)) {
                $task->fail();
                $task->update(['result' => ['error' => $e->getMessage()]]);
                safeBroadcast(new TaskUpdated($task, 'failed'), 'automation task failure');
            }

            $automation->recordFailure($e->getMessage());

            throw $e;
        } finally {
            $agent->resolveIdleStatus();
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

<?php

namespace App\Jobs;

use App\Agents\OpenCompanyAgent;
use App\Events\AgentStatusUpdated;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunAutomationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 1800;

    /** @var array<int, int> */
    public array $backoff = [30];

    /**
     * The number of seconds the unique lock should be maintained.
     */
    public int $uniqueFor = 1800;

    public function __construct(
        private Automation $automation,
    ) {}

    public function uniqueId(): string
    {
        return 'automation_' . $this->automation->id;
    }

    public function handle(): void
    {
        // Set workspace context from automation
        if ($this->automation->workspace_id) {
            $workspace = Workspace::find($this->automation->workspace_id);
            if ($workspace) {
                app()->instance('currentWorkspace', $workspace);
            }
        }

        $agent = User::find($this->automation->agent_id);

        if (! $agent) {
            $this->automation->recordFailure('Agent not found');

            return;
        }

        // Guard: skip retry if this automation already produced a response recently
        if ($this->attempts() > 1 && $this->automation->channel_id) {
            $hasRecentResponse = Message::where('channel_id', $this->automation->channel_id)
                ->where('source', 'automation')
                ->where('created_at', '>', now()->subMinutes(30))
                ->exists();

            if ($hasRecentResponse) {
                Log::info('Skipping automation retry — response already exists', [
                    'automation' => $this->automation->name,
                    'attempt' => $this->attempts(),
                ]);

                return;
            }
        }

        try {
            $channelId = $this->automation->channel_id;

            // Backfill: create channel if automation doesn't have one yet
            if (! $channelId) {
                $channel = Channel::create([
                    'id' => Str::uuid()->toString(),
                    'workspace_id' => $this->automation->workspace_id,
                    'name' => $this->automation->name,
                    'type' => 'dm',
                    'description' => "Automation channel for: {$this->automation->name}",
                    'creator_id' => $this->automation->created_by_id,
                ]);
                $channel->users()->attach(array_filter([$this->automation->created_by_id, $this->automation->agent_id]));
                $this->automation->updateQuietly(['channel_id' => $channel->id]);
                $channelId = $channel->id;
            }

            // Clear channel history if keep_history is disabled
            if ($channelId && ! $this->automation->keep_history) {
                Message::where('channel_id', $channelId)->delete();
            }

            $task = Task::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $this->automation->workspace_id,
                'title' => "Scheduled: {$this->automation->name}",
                'description' => $this->automation->prompt,
                'type' => Task::TYPE_CUSTOM,
                'status' => Task::STATUS_ACTIVE,
                'priority' => Task::PRIORITY_NORMAL,
                'source' => Task::SOURCE_AUTOMATION,
                'agent_id' => $agent->id,
                'requester_id' => $this->automation->created_by_id,
                'channel_id' => $channelId,
                'started_at' => now(),
                'context' => [
                    'automation_id' => $this->automation->id,
                    'schedule' => $this->automation->cron_expression,
                    'run_number' => $this->automation->run_count + 1,
                ],
            ]);
            // Post the prompt into the channel from the system user
            if ($channelId) {
                $promptMessage = Message::create([
                    'id' => Str::uuid()->toString(),
                    'content' => $this->automation->prompt,
                    'channel_id' => $channelId,
                    'author_id' => $this->automation->created_by_id,
                    'timestamp' => now(),
                    'source' => 'automation_prompt',
                ]);
                broadcast(new MessageSent($promptMessage));
            }

            $agent->update(['status' => 'working']);
            broadcast(new AgentStatusUpdated($agent));

            $agentInstance = OpenCompanyAgent::for($agent, $channelId, $task->id);

            // Capture LLM context for observability
            try {
                $toolRegistry = app(\App\Agents\Tools\ToolRegistry::class);
                $task->update([
                    'context' => array_merge($task->context ?? [], [
                        'tools' => $toolRegistry->getToolSlugsForAgent($agent),
                        'model' => $agentInstance->model(),
                        'provider' => $agentInstance->provider(),
                        'prompt_sections' => $agentInstance->instructionsBreakdown(),
                    ]),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to capture automation LLM context', ['error' => $e->getMessage()]);
            }

            $prompt = $this->buildScheduledPrompt();
            $response = $agentInstance->prompt($prompt);

            if ($channelId) {
                $agentMessage = Message::create([
                    'id' => Str::uuid()->toString(),
                    'content' => $response->text,
                    'channel_id' => $channelId,
                    'author_id' => $agent->id,
                    'timestamp' => now(),
                    'source' => 'automation',
                ]);

                Channel::where('id', $channelId)
                    ->update(['last_message_at' => now()]);

                broadcast(new MessageSent($agentMessage));
            }

            // Tool steps are now saved in real-time by CheckpointToolCall listener

            $lastStep = $response->steps->last();
            $toolCallsCount = collect($response->steps)->sum(fn ($step) => count($step->toolCalls));

            $task->complete([
                'response' => Str::limit($response->text, 500),
                'prompt_tokens' => $lastStep?->usage->promptTokens ?? $response->usage->promptTokens,
                'completion_tokens' => $lastStep?->usage->completionTokens ?? $response->usage->completionTokens,
                'total_prompt_tokens' => $response->usage->promptTokens,
                'total_completion_tokens' => $response->usage->completionTokens,
                'cache_read_tokens' => $response->usage->cacheReadInputTokens,
                'cache_write_tokens' => $response->usage->cacheWriteInputTokens,
                'tool_calls_count' => $toolCallsCount,
            ]);

            broadcast(new TaskUpdated($task, 'completed'));

            $this->automation->recordSuccess([
                'task_id' => $task->id,
                'response_preview' => Str::limit($response->text, 200),
                'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
                'tool_calls' => $toolCallsCount,
                'completed_at' => now()->toIso8601String(),
            ]);

            Log::info('Automation completed', [
                'automation' => $this->automation->name,
                'agent' => $agent->name,
                'task' => $task->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Automation failed', [
                'automation' => $this->automation->name,
                'agent' => $agent->name,
                'error' => $e->getMessage(),
            ]);

            if (isset($task)) {
                $task->fail();
                $task->update([
                    'result' => ['error' => $e->getMessage()],
                ]);
                broadcast(new TaskUpdated($task, 'failed'));
            }

            $this->automation->recordFailure($e->getMessage());

            throw $e;
        } finally {
            $agent->refresh();

            if (! $agent->sleeping_until) {
                $agent->update(['status' => 'idle']);
            }

            broadcast(new AgentStatusUpdated($agent));
        }
    }

    private function buildScheduledPrompt(): string
    {
        $prompt = "This is a scheduled automation run.\n\n";
        $prompt .= "**Schedule:** {$this->automation->name}\n";
        $prompt .= "**Run #:** ".($this->automation->run_count + 1)."\n";

        if ($this->automation->last_run_at) {
            /** @var \Carbon\Carbon $lastRunAt */
            $lastRunAt = $this->automation->last_run_at;
            $prompt .= "**Last run:** {$lastRunAt->diffForHumans()}\n";
        }

        $prompt .= "\n---\n\n";
        $prompt .= $this->automation->prompt;

        return $prompt;
    }

}

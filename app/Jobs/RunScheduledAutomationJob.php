<?php

namespace App\Jobs;

use App\Agents\OpenCompanyAgent;
use App\Events\AgentStatusUpdated;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Models\Channel;
use App\Models\Message;
use App\Models\ScheduledAutomation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunScheduledAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 1800;

    public array $backoff = [30];

    public function __construct(
        private ScheduledAutomation $automation,
    ) {}

    public function handle(): void
    {
        $agent = User::find($this->automation->agent_id);

        if (! $agent) {
            $this->automation->recordFailure('Agent not found');

            return;
        }

        $channelId = $this->automation->channel_id;

        // Backfill: create channel if automation doesn't have one yet
        if (! $channelId) {
            $channel = Channel::create([
                'id' => Str::uuid()->toString(),
                'name' => $this->automation->name,
                'type' => 'dm',
                'description' => "Automation channel for: {$this->automation->name}",
                'creator_id' => $this->automation->created_by_id,
            ]);
            $channel->members()->attach(array_filter(['system', $this->automation->agent_id]));
            $this->automation->updateQuietly(['channel_id' => $channel->id]);
            $channelId = $channel->id;
        }

        // Clear channel history if keep_history is disabled
        if ($channelId && ! $this->automation->keep_history) {
            Message::where('channel_id', $channelId)->delete();
        }

        $task = Task::create([
            'id' => Str::uuid()->toString(),
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
                'scheduled_automation_id' => $this->automation->id,
                'schedule' => $this->automation->cron_expression,
                'run_number' => $this->automation->run_count + 1,
            ],
        ]);

        try {
            // Post the prompt into the channel from the system user
            if ($channelId) {
                $promptMessage = Message::create([
                    'id' => Str::uuid()->toString(),
                    'content' => $this->automation->prompt,
                    'channel_id' => $channelId,
                    'author_id' => 'system',
                    'timestamp' => now(),
                    'source' => 'automation_prompt',
                ]);
                broadcast(new MessageSent($promptMessage));
            }

            $agent->update(['status' => 'working']);
            broadcast(new AgentStatusUpdated($agent));

            $agentInstance = OpenCompanyAgent::for($agent, $channelId, $task->id);
            $prompt = $this->buildScheduledPrompt();
            $response = $agentInstance->prompt($prompt);

            if ($channelId) {
                $agentMessage = Message::create([
                    'id' => Str::uuid()->toString(),
                    'content' => $response->text,
                    'channel_id' => $channelId,
                    'author_id' => $agent->id,
                    'timestamp' => now(),
                    'source' => 'scheduled_automation',
                ]);

                Channel::where('id', $channelId)
                    ->update(['last_message_at' => now()]);

                broadcast(new MessageSent($agentMessage));
            }

            $task->complete();
            $task->update([
                'result' => [
                    'response' => Str::limit($response->text, 500),
                    'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
                ],
            ]);

            broadcast(new TaskUpdated($task, 'completed'));

            $this->automation->recordSuccess([
                'task_id' => $task->id,
                'response_preview' => Str::limit($response->text, 200),
                'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
                'completed_at' => now()->toIso8601String(),
            ]);

            Log::info('Scheduled automation completed', [
                'automation' => $this->automation->name,
                'agent' => $agent->name,
                'task' => $task->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Scheduled automation failed', [
                'automation' => $this->automation->name,
                'agent' => $agent->name,
                'error' => $e->getMessage(),
            ]);

            $task->fail();
            $task->update([
                'result' => ['error' => $e->getMessage()],
            ]);

            broadcast(new TaskUpdated($task, 'failed'));
            $this->automation->recordFailure($e->getMessage());
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
            $prompt .= "**Last run:** {$this->automation->last_run_at->diffForHumans()}\n";
        }

        $prompt .= "\n---\n\n";
        $prompt .= $this->automation->prompt;

        return $prompt;
    }

}

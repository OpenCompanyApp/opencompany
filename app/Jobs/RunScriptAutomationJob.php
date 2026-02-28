<?php

namespace App\Jobs;

use App\Agents\Tools\ToolRegistry;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\ConversationSummary;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\LuaApiDocGenerator;
use App\Services\LuaBridge;
use App\Services\LuaSandboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunScriptAutomationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [10];

    public int $uniqueFor = 120;

    public function __construct(
        private Automation $automation,
    ) {}

    public function uniqueId(): string
    {
        return 'script_automation_' . $this->automation->id;
    }

    public function handle(): void
    {
        // Set workspace context
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
                $channel->users()->attach(array_unique(array_filter([$this->automation->created_by_id, $this->automation->agent_id])));
                $this->automation->updateQuietly(['channel_id' => $channel->id]);
                $channelId = $channel->id;
            }

            // Clear channel history if keep_history is disabled
            if ($channelId && ! $this->automation->keep_history) {
                Message::where('channel_id', $channelId)->delete();
                ConversationSummary::where('channel_id', $channelId)->delete();
            }

            $task = Task::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $this->automation->workspace_id,
                'title' => "Script: {$this->automation->name}",
                'description' => Str::limit($this->automation->script, 200),
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
                    'execution_type' => 'script',
                    'schedule' => $this->automation->cron_expression,
                    'run_number' => $this->automation->run_count + 1,
                ],
            ]);

            // Build ctx table for the script
            /** @var \Carbon\Carbon|null $lastRunAt */
            $lastRunAt = $this->automation->last_run_at;

            $ctx = [
                'automation_id' => $this->automation->id,
                'automation_name' => $this->automation->name,
                'run_number' => $this->automation->run_count + 1,
                'last_run_at' => $lastRunAt?->toIso8601String(),
                'last_result' => $this->automation->last_result,
                'trigger_type' => $this->automation->trigger_type,
                'schedule' => $this->automation->cron_expression,
                'timezone' => $this->automation->timezone,
            ];

            // Execute the Luau script
            $toolRegistry = app(ToolRegistry::class);
            $docGenerator = app(LuaApiDocGenerator::class);
            $bridge = new LuaBridge($agent, $toolRegistry, $docGenerator);
            $luaSandbox = app(LuaSandboxService::class);

            $result = $luaSandbox->execute(
                $this->automation->script,
                ['cpuLimit' => 30.0],
                $bridge,
                ['ctx' => $ctx],
            );

            // Post output to channel if non-empty
            $output = trim($result->output);
            if ($channelId && $output !== '') {
                $agentMessage = Message::create([
                    'id' => Str::uuid()->toString(),
                    'content' => $output,
                    'channel_id' => $channelId,
                    'author_id' => $agent->id,
                    'timestamp' => now(),
                    'source' => 'automation',
                ]);

                Channel::where('id', $channelId)
                    ->update(['last_message_at' => now()]);

                broadcast(new MessageSent($agentMessage));
            }

            $bridgeCalls = $bridge->getCallLog();

            if ($result->error !== null) {
                $task->fail();
                $task->update([
                    'result' => [
                        'error' => $result->error,
                        'output' => $output,
                        'execution_time_ms' => $result->executionTime,
                        'memory_usage' => $result->memoryUsage,
                        'bridge_calls' => $bridgeCalls,
                    ],
                ]);

                try {
                    broadcast(new TaskUpdated($task, 'failed'));
                } catch (\Throwable $e) {
                    Log::warning('Failed to broadcast script task failure', ['error' => $e->getMessage()]);
                }

                $this->automation->recordFailure($result->error);

                return;
            }

            $task->complete([
                'output' => $output,
                'return_value' => $result->result,
                'execution_time_ms' => $result->executionTime,
                'memory_usage' => $result->memoryUsage,
                'bridge_calls' => $bridgeCalls,
                'tool_calls_count' => count($bridgeCalls),
            ]);

            try {
                broadcast(new TaskUpdated($task, 'completed'));
            } catch (\Throwable $e) {
                Log::warning('Failed to broadcast script task completion', ['error' => $e->getMessage()]);
            }

            $this->automation->recordSuccess([
                'task_id' => $task->id,
                'output' => Str::limit($output, 200),
                'return_value' => $result->result,
                'execution_time_ms' => $result->executionTime,
                'bridge_calls_count' => count($bridgeCalls),
                'completed_at' => now()->toIso8601String(),
            ]);

            Log::info('Script automation completed', [
                'automation' => $this->automation->name,
                'task' => $task->id,
                'execution_time_ms' => $result->executionTime,
            ]);
        } catch (\Throwable $e) {
            Log::error('Script automation failed', [
                'automation' => $this->automation->name,
                'error' => $e->getMessage(),
            ]);

            if (isset($task)) {
                $task->fail();
                $task->update([
                    'result' => ['error' => $e->getMessage()],
                ]);
                try {
                    broadcast(new TaskUpdated($task, 'failed'));
                } catch (\Throwable $broadcastError) {
                    Log::warning('Failed to broadcast script task failure', ['error' => $broadcastError->getMessage()]);
                }
            }

            $this->automation->recordFailure($e->getMessage());

            throw $e;
        }
    }
}

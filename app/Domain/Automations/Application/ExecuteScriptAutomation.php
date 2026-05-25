<?php

namespace App\Domain\Automations\Application;

use App\Agents\Tools\ToolRegistry;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Services\LuaApiDocGenerator;
use App\Services\LuaBridge;
use App\Services\LuaSandboxService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes one script-based automation run through the Luau sandbox.
 *
 * The queue job owns retry/timeout/uniqueness and workspace binding. This use
 * case owns OpenCompany's script automation lifecycle: creating the run task,
 * building the script context, invoking the sandbox bridge, posting output, and
 * recording task/automation success or failure.
 */
class ExecuteScriptAutomation
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private LuaApiDocGenerator $docGenerator,
        private LuaSandboxService $luaSandbox,
    ) {}

    public function handle(Automation $automation): void
    {
        $agent = User::find($automation->agent_id);

        if (! $agent) {
            $automation->recordFailure('Agent not found');

            return;
        }

        try {
            $channelId = $automation->ensureChannel();
            $task = $this->createRunTask($automation, $agent, $channelId);
            $bridge = new LuaBridge($agent, $this->toolRegistry, $this->docGenerator);
            $result = $this->luaSandbox->execute(
                $automation->script,
                ['cpuLimit' => 30.0],
                $bridge,
                ['ctx' => $this->scriptContext($automation)],
            );

            $output = trim($result->output);
            $this->postOutputMessage($agent, $channelId, $output);

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

                safeBroadcast(new TaskUpdated($task, 'failed'), 'script task failure');
                $automation->recordFailure($result->error);

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

            safeBroadcast(new TaskUpdated($task, 'completed'), 'script task completion');

            $automation->recordSuccess([
                'task_id' => $task->id,
                'output' => Str::limit($output, 200),
                'return_value' => $result->result,
                'execution_time_ms' => $result->executionTime,
                'bridge_calls_count' => count($bridgeCalls),
                'completed_at' => now()->toIso8601String(),
            ]);

            Log::info('Script automation completed', [
                'automation' => $automation->name,
                'task' => $task->id,
                'execution_time_ms' => $result->executionTime,
            ]);
        } catch (\Throwable $e) {
            Log::error('Script automation failed', [
                'automation' => $automation->name,
                'error' => $e->getMessage(),
            ]);

            if (isset($task)) {
                $task->fail();
                $task->update(['result' => ['error' => $e->getMessage()]]);
                safeBroadcast(new TaskUpdated($task, 'failed'), 'script task failure');
            }

            $automation->recordFailure($e->getMessage());

            throw $e;
        }
    }

    private function createRunTask(Automation $automation, User $agent, string $channelId): Task
    {
        return Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $automation->workspace_id,
            'title' => "Script: {$automation->name}",
            'description' => Str::limit($automation->script, 200),
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
                'execution_type' => 'script',
                'schedule' => $automation->cron_expression,
                'run_number' => $automation->run_count + 1,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function scriptContext(Automation $automation): array
    {
        /** @var Carbon|null $lastRunAt */
        $lastRunAt = $automation->last_run_at;

        return [
            'automation_id' => $automation->id,
            'automation_name' => $automation->name,
            'run_number' => $automation->run_count + 1,
            'last_run_at' => $lastRunAt?->toIso8601String(),
            'last_result' => $automation->last_result,
            'trigger_type' => $automation->trigger_type,
            'schedule' => $automation->cron_expression,
            'timezone' => $automation->timezone,
        ];
    }

    private function postOutputMessage(User $agent, string $channelId, string $output): void
    {
        if ($channelId === '' || $output === '') {
            return;
        }

        $agentMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $output,
            'channel_id' => $channelId,
            'author_id' => $agent->id,
            'timestamp' => now(),
            'source' => 'automation',
        ]);

        Channel::where('id', $channelId)->update(['last_message_at' => now()]);
        broadcast(new MessageSent($agentMessage));
    }
}

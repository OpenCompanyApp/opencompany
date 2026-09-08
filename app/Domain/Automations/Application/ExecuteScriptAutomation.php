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
use App\Services\CodeApiDocGenerator;
use App\Services\CodeBridge;
use App\Services\MrubySandboxService;
use App\Services\ScriptAdmission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes one runtime-pinned Ruby automation through mruby.
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
        private CodeApiDocGenerator $docGenerator,
        private MrubySandboxService $sandbox,
    ) {}

    public function handle(Automation $automation): void
    {
        if (! app(ScriptAdmission::class)->matches($automation)) {
            $automation->recordFailure(
                'Automation is disabled until its exact Ruby source is validated with the current engine.',
                [
                    'script_runtime' => $automation->script_runtime,
                    'required_runtime' => config('code.runtime'),
                    'retryable' => false,
                ],
            );
            // Runtime incompatibility is not a transient failure. The generic
            // failure counter normally keeps early failures active, so enforce
            // the migration invariant after recording operator diagnostics.
            $automation->update(['is_active' => false]);

            return;
        }

        $agent = User::where('workspace_id', $automation->workspace_id)->where('type', 'agent')->find($automation->agent_id);

        if (! $agent) {
            $automation->recordFailure('Agent not found');

            return;
        }

        try {
            $channelId = $automation->ensureChannel();
            $task = $this->createRunTask($automation, $agent, $channelId);
            $bridge = new CodeBridge(
                $agent,
                $this->toolRegistry,
                $this->docGenerator,
                $this->sandbox->profile('automation'),
            );
            $result = $this->sandbox->execute(
                code: (string) $automation->script,
                profile: 'automation',
                bridge: $bridge,
                globals: ['ctx' => $this->scriptContext($automation)],
                sourceName: 'automation-code.rb',
            );

            $output = $this->renderOutput($result->output, $result->result);
            $bridgeCalls = $bridge->getCallLog();
            if ($result->error !== null) {
                $errorMessage = $this->formatError($result->error);
                $failureOutput = $this->renderFailureOutput($output, $errorMessage, $result->error);
                $this->postOutputMessage($agent, $channelId, $failureOutput);
                $task->fail();
                $task->update([
                    'result' => [
                        'error' => $result->error,
                        'output' => $failureOutput,
                        'execution_id' => $result->executionId,
                        'script_runtime' => $automation->script_runtime,
                        'execution_time_ms' => $result->executionTime,
                        'cpu_time_ms' => $result->cpuTime,
                        'memory_usage' => $result->memoryUsage,
                        'peak_memory_usage' => $result->peakMemoryUsage,
                        'effects' => $result->effects,
                        'bridge_calls' => $bridgeCalls,
                    ],
                ]);

                safeBroadcast(new TaskUpdated($task, 'failed'), 'script task failure');
                $automation->recordFailure($errorMessage, [
                    'execution_id' => $result->executionId,
                    'script_runtime' => $automation->script_runtime,
                    'runtime_error' => $result->error,
                    'effects' => $result->effects,
                ]);

                return;
            }

            $this->postOutputMessage($agent, $channelId, $output);
            $task->complete([
                'output' => $output,
                'return_value' => $result->result,
                'execution_id' => $result->executionId,
                'script_runtime' => $automation->script_runtime,
                'execution_time_ms' => $result->executionTime,
                'cpu_time_ms' => $result->cpuTime,
                'memory_usage' => $result->memoryUsage,
                'peak_memory_usage' => $result->peakMemoryUsage,
                'effects' => $result->effects,
                'bridge_calls' => $bridgeCalls,
                'tool_calls_count' => count($bridgeCalls),
            ]);

            safeBroadcast(new TaskUpdated($task, 'completed'), 'script task completion');

            $automation->recordSuccess([
                'task_id' => $task->id,
                'output' => Str::limit($output, 200),
                'return_value' => $result->result,
                'execution_id' => $result->executionId,
                'script_runtime' => $automation->script_runtime,
                'execution_time_ms' => $result->executionTime,
                'effects' => $result->effects,
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
                'script_runtime' => $automation->script_runtime,
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
            'agent_id' => $automation->agent_id,
            'channel_id' => $automation->channel_id,
            'run_number' => $automation->run_count + 1,
            'last_run_at' => $lastRunAt?->toIso8601String(),
            'last_result' => $automation->last_result,
            'trigger_type' => $automation->trigger_type,
            'schedule' => $automation->cron_expression,
            'timezone' => $automation->timezone,
            'script_runtime' => $automation->script_runtime,
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

    /**
     * Prefer explicit console output, but make a returned summary useful for
     * deterministic automations that do not log.
     */
    private function renderOutput(string $output, mixed $returnValue): string
    {
        $output = trim($output);
        if ($output !== '' || $returnValue === null) {
            return $output;
        }

        if (is_string($returnValue)) {
            return $returnValue;
        }

        return (string) json_encode(
            $returnValue,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Make runtime failures unmistakable in the automation channel while
     * preserving bounded partial console output that may help repair the code.
     * The sandbox has already redacted host errors before they reach this layer.
     *
     * @param  array<string, mixed>  $error
     */
    private function renderFailureOutput(string $output, string $errorMessage, array $error): string
    {
        $parts = [];
        if (trim($output) !== '') {
            $parts[] = trim($output);
        }

        $parts[] = $errorMessage;
        if (is_string($error['suggestion'] ?? null) && $error['suggestion'] !== '') {
            $parts[] = 'Repair: '.$error['suggestion'];
        }
        if (($error['effectStatus'] ?? null) === 'unknown') {
            $parts[] = 'Write status is unknown. Verify state with a read before considering a retry.';
        }

        return implode("\n\n", $parts);
    }

    /** @param  array<string, mixed>  $error */
    private function formatError(array $error): string
    {
        $location = isset($error['line'])
            ? ' at line '.$error['line'].(isset($error['column']) ? ':'.$error['column'] : '')
            : '';

        return '['.($error['type'] ?? 'runtime_error').']'.$location.': '.($error['message'] ?? 'Ruby execution failed.');
    }
}

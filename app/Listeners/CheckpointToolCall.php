<?php

namespace App\Listeners;

use App\Agents\OpenCompanyAgent;
use App\Models\Task;
use App\Support\LuaMetaParser;
use Laravel\Ai\Events\ToolInvoked;
use App\Agents\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;

class CheckpointToolCall
{
    /**
     * Save each completed tool call immediately as a TaskStep.
     *
     * This runs synchronously within the queue job process so checkpoints
     * are persisted to the database before the next tool call begins.
     * On crash + retry, these checkpoints are injected into the conversation
     * so the LLM can continue from where it left off.
     */
    public function handle(ToolInvoked $event): void
    {
        $agent = $event->agent;

        if (! $agent instanceof OpenCompanyAgent) {
            return;
        }

        $taskId = $agent->currentTaskId();
        if (! $taskId) {
            return;
        }

        $task = Task::find($taskId);
        if (! $task) {
            return;
        }

        try {
            $toolName = method_exists($event->tool, 'name')
                ? $event->tool->name()
                : class_basename($event->tool);
            $result = $event->result;

            // Extract LuaExec structured metadata before truncation
            $extracted = LuaMetaParser::extract($result);
            $luaMeta = $extracted['meta'];
            $result = $extracted['result'];

            // Truncate large string results to prevent DB bloat
            if (is_string($result) && strlen($result) > 2000) {
                $result = mb_strcut($result, 0, 2000, 'UTF-8') . '... [truncated]';
            }

            // Sanitize to valid UTF-8 to prevent JSON encoding failures
            if (is_string($result)) {
                $result = mb_convert_encoding($result, 'UTF-8', 'UTF-8');
            }

            $toolRegistry = app(ToolRegistry::class);

            $step = $task->addStep(
                "Used tool: {$toolName}",
                'action',
                array_filter([
                    'tool' => $toolName,
                    'tool_call_id' => $event->toolInvocationId,
                    'icon' => $toolRegistry->getIconByClassName($toolName),
                    'arguments' => $event->arguments,
                    'result' => $result,
                    'lua_meta' => $luaMeta,
                    'checkpointed' => true,
                ])
            );
            $step->start();
            $step->complete();
        } catch (\Throwable $e) {
            Log::warning('CheckpointToolCall: failed to save checkpoint', [
                'tool' => method_exists($event->tool, 'name')
                    ? $event->tool->name()
                    : class_basename($event->tool),
                'task' => $taskId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

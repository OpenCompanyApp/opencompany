<?php

namespace App\Listeners;

use App\Agents\OpenCompanyAgent;
use App\Agents\Tools\ToolRegistry;
use App\Events\TaskUpdated;
use App\Models\Task;
use App\Models\User;
use App\Services\Memory\OutputTruncator;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\ToolInvoked;

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

            // CodeExec exposes telemetry through the invoked tool instance. Do
            // not embed hidden markers in model-visible tool output merely to
            // transport UI metadata to this synchronous listener.
            $rawCodeMeta = method_exists($event->tool, 'lastExecutionMetadata')
                ? $event->tool->lastExecutionMetadata()
                : null;
            $codeMeta = is_array($rawCodeMeta) ? $rawCodeMeta : null;

            // Truncate large results before checkpoint persistence to keep
            // retry context lean while preserving the full payload durably.
            $result = app(OutputTruncator::class)->truncate($result, $event->toolInvocationId);

            // Sanitize to valid UTF-8 to prevent JSON encoding failures
            if (is_string($result)) {
                $result = mb_convert_encoding($result, 'UTF-8', 'UTF-8');
            }

            $toolRegistry = app(ToolRegistry::class);
            $toolMeta = $toolRegistry->getToolMetaByClassName($toolName);
            $toolDisplayName = $toolMeta['name'];

            // Human-readable descriptions for agent communication steps
            $description = "Used tool: {$toolDisplayName}";
            $stepIcon = $toolMeta['icon'];

            if ($toolName === 'ContactAgent' && isset($event->arguments['action'])) {
                $action = $event->arguments['action'];
                $targetId = $event->arguments['agentId'] ?? null;
                $targetName = $targetId ? User::find($targetId)?->name : null;

                if ($targetName) {
                    $description = match ($action) {
                        'delegate' => "Delegated to {$targetName}",
                        'ask' => "Asked {$targetName}",
                        'notify' => "Notified {$targetName}",
                        default => "Contacted {$targetName}",
                    };
                } else {
                    $verb = match ($action) {
                        'delegate' => 'Delegate', 'ask' => 'Ask', 'notify' => 'Notify', default => 'Contact',
                    };
                    $description = "{$verb} failed: agent not found";
                }
            }

            // For CodeExec, derive description and icon from capability calls.
            $bridgeCalls = is_array($codeMeta['bridgeCalls'] ?? null) ? $codeMeta['bridgeCalls'] : [];
            if ($toolName === 'CodeExec' && $bridgeCalls !== []) {
                $names = [];
                $firstIcon = null;
                foreach ($bridgeCalls as $call) {
                    if (! is_array($call)) {
                        continue;
                    }

                    if (is_string($call['name'] ?? null) && $call['name'] !== '') {
                        $names[] = $call['name'];
                    }
                    if ($firstIcon === null && is_string($call['icon'] ?? null) && $call['icon'] !== '') {
                        $firstIcon = $call['icon'];
                    }
                }
                $names = array_values(array_unique($names));

                if ($names !== []) {
                    $description = implode(', ', array_slice($names, 0, 3));
                    if (count($names) > 3) {
                        $description .= ' +'.(count($names) - 3).' more';
                    }
                    // Use the first bridge call's icon as the step icon.
                    $stepIcon = $firstIcon ?? $stepIcon;
                } else {
                    $description = 'Executed Ruby';
                }
            }

            $step = $task->addStep(
                $description,
                'action',
                array_filter([
                    'tool' => $toolName,
                    'tool_name' => $toolDisplayName,
                    'tool_call_id' => $event->toolInvocationId,
                    'icon' => $stepIcon,
                    'arguments' => $event->arguments,
                    'result' => $result,
                    'code_meta' => $codeMeta,
                    'checkpointed' => true,
                ])
            );
            $step->start();
            $step->complete();
            safeBroadcast(new TaskUpdated($task->fresh(['steps']) ?? $task, 'progress'), 'task tool progress');
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

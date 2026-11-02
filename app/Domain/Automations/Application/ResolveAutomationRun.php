<?php

namespace App\Domain\Automations\Application;

use App\Models\AgentRunRecord;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Resolves one durable task/run pair for a serialized automation dispatch.
 *
 * The dispatch key is created when the queue command is serialized and remains
 * stable across physical retries. This prevents every retry from creating a
 * fresh active task and makes output correlation exact.
 */
class ResolveAutomationRun
{
    public function handle(Automation $automation, string $dispatchKey, string $kind, int $maxAttempts): AutomationRunContext
    {
        $agent = User::query()
            ->where('workspace_id', $automation->workspace_id)
            ->where('type', 'agent')
            ->find($automation->agent_id);

        if (! $agent) {
            throw new \DomainException('Automation agent is missing or outside the automation workspace.');
        }

        $channelId = $automation->ensureChannel();
        if ($channelId === '' || ! Channel::query()
            ->where('workspace_id', $automation->workspace_id)
            ->whereKey($channelId)
            ->exists()) {
            throw new \DomainException('Automation channel is missing or outside the automation workspace.');
        }

        return DB::transaction(function () use ($automation, $dispatchKey, $kind, $maxAttempts, $agent, $channelId): AutomationRunContext {
            $existing = AgentRunRecord::query()
                ->where('workspace_id', $automation->workspace_id)
                ->where('dispatch_key', $dispatchKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return new AutomationRunContext($agent, $existing->task()->firstOrFail(), $existing, $channelId);
            }

            $isScript = $kind === AgentRunRecord::KIND_SCRIPT_AUTOMATION;
            $task = Task::query()->create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $automation->workspace_id,
                'title' => ($isScript ? 'Script: ' : 'Scheduled: ').$automation->name,
                'description' => $isScript ? Str::limit((string) $automation->script, 200) : $automation->prompt,
                'type' => Task::TYPE_CUSTOM,
                'status' => Task::STATUS_PENDING,
                'priority' => Task::PRIORITY_NORMAL,
                'source' => Task::SOURCE_AUTOMATION,
                'agent_id' => $agent->id,
                'requester_id' => $automation->created_by_id,
                'channel_id' => $channelId,
                'context' => array_filter([
                    'automation_id' => $automation->id,
                    'execution_type' => $isScript ? 'script' : 'prompt',
                    'schedule' => $automation->cron_expression,
                    'run_number' => $automation->run_count + 1,
                ], fn ($value) => $value !== null),
            ]);

            $run = AgentRunRecord::query()->create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $automation->workspace_id,
                'task_id' => $task->id,
                'agent_id' => $agent->id,
                'channel_id' => $channelId,
                'kind' => $kind,
                'dispatch_key' => $dispatchKey,
                'status' => AgentRunRecord::STATUS_QUEUED,
                'max_attempts' => $maxAttempts,
                'heartbeat_at' => now(),
                'metadata' => ['automation_id' => $automation->id],
            ]);

            return new AutomationRunContext($agent, $task, $run, $channelId);
        });
    }
}

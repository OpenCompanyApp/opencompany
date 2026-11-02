<?php

namespace App\Domain\Work\Application;

use App\Events\TaskUpdated;
use App\Jobs\AgentRespondJob;
use App\Jobs\ExecuteAgentTaskJob;
use App\Jobs\RunAutomationJob;
use App\Models\AgentRunRecord;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\ListItem;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Workspace task management use cases.
 *
 * Tasks are OpenCompany work records, not generic queue jobs. This service owns
 * the workspace-scoped query shape, lifecycle transitions, task-step mutations,
 * and the decision to hand assigned work to the AgentRuntime queue.
 */
class ManageTasks
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters): array
    {
        $query = Task::forWorkspace()
            ->whereNull('parent_task_id')
            ->with(['agent', 'requester', 'channel', 'steps']);

        foreach (['status', 'agentId', 'requesterId', 'channelId', 'type', 'priority', 'source'] as $filter) {
            if (! array_key_exists($filter, $filters)) {
                continue;
            }

            match ($filter) {
                'status' => $query->whereIn('status', is_array($filters[$filter]) ? $filters[$filter] : [$filters[$filter]]),
                'agentId' => $query->where('agent_id', $filters[$filter]),
                'requesterId' => $query->where('requester_id', $filters[$filter]),
                'channelId' => $query->where('channel_id', $filters[$filter]),
                default => $query->where($filter, $filters[$filter]),
            };
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $query->orderBy($filters['orderBy'] ?? 'created_at', $filters['orderDir'] ?? 'desc');

        /** @var LengthAwarePaginator $paginated */
        $paginated = $query->paginate((int) ($filters['perPage'] ?? 50));
        $rootIds = $paginated->getCollection()->pluck('id')->toArray();

        if ($rootIds !== []) {
            $subtasks = Task::forWorkspace()->whereIn('parent_task_id', $rootIds)
                ->with(['agent', 'requester', 'channel', 'steps'])
                ->orderBy('created_at')
                ->get();
            $paginated->setCollection($paginated->getCollection()->concat($subtasks));
        }

        $paginated->getCollection()->transform(function (Task $task) {
            $task->makeHidden(['context']);

            return $task;
        });

        return array_merge($paginated->toArray(), [
            'counts' => [
                'total' => Task::forWorkspace()->whereNull('parent_task_id')->count(),
                'pending' => Task::forWorkspace()->whereNull('parent_task_id')->where('status', 'pending')->count(),
                'active' => Task::forWorkspace()->whereNull('parent_task_id')->where('status', 'active')->count(),
                'completed' => Task::forWorkspace()->whereNull('parent_task_id')->where('status', 'completed')->count(),
            ],
        ]);
    }

    public function show(string $id): Task
    {
        return Task::forWorkspace()->with([
            'agent',
            'requester',
            'channel',
            'listItem',
            'parentTask',
            'subtasks.agent',
            'steps',
        ])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Task
    {
        $this->assertWorkspaceReferences($data);

        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? Task::TYPE_CUSTOM,
            'status' => Task::STATUS_PENDING,
            'priority' => $data['priority'] ?? Task::PRIORITY_NORMAL,
            'agent_id' => $data['agentId'] ?? null,
            'requester_id' => $data['requesterId'] ?? null,
            'channel_id' => $data['channelId'] ?? null,
            'list_item_id' => $data['listItemId'] ?? null,
            'parent_task_id' => $data['parentTaskId'] ?? null,
            'source' => $data['source'] ?? Task::SOURCE_MANUAL,
            'context' => $data['context'] ?? null,
            'due_at' => $data['dueAt'] ?? null,
        ]);

        safeBroadcast(new TaskUpdated($task, 'created'), 'task created');

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): Task
    {
        $task = Task::forWorkspace()->findOrFail($id);
        $this->assertWorkspaceReferences($data, $task);
        $updates = array_intersect_key($data, array_flip([
            'title', 'description', 'type', 'priority', 'context', 'result',
        ]));

        foreach (['agentId' => 'agent_id', 'channelId' => 'channel_id', 'dueAt' => 'due_at'] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $updates[$column] = $data[$input];
            }
        }

        $task->update($updates);
        safeBroadcast(new TaskUpdated($task, 'updated'), 'task updated');

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    public function delete(string $id): void
    {
        $task = Task::forWorkspace()->findOrFail($id);
        if (! $task->isTerminal() && $task->agentRun()->exists()) {
            throw new \DomainException('Running or queued agent tasks must be cancelled before deletion.');
        }

        $task->delete();
    }

    public function start(string $id): Task
    {
        $task = DB::transaction(function () use ($id): Task {
            $task = Task::forWorkspace()->lockForUpdate()->findOrFail($id);

            if (! $task->isPending()) {
                throw new \DomainException("Only pending tasks can be started; task [{$task->id}] is [{$task->status}].");
            }

            $this->assertWorkspaceReferences([
                'agentId' => $task->agent_id,
                'channelId' => $task->channel_id,
                'parentTaskId' => $task->parent_task_id,
                'listItemId' => $task->list_item_id,
            ], $task);

            if ($task->agent_id) {
                $this->ensureRunForTask($task);
            }

            $task->start();

            return $task;
        });

        if ($task->agent_id) {
            $this->dispatchOrRestoreWaiting($task);
        }

        safeBroadcast(new TaskUpdated($task, 'started'), 'task started');

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    public function transition(string $id, string $action, mixed $payload = null): Task
    {
        $shouldDispatch = false;
        $task = DB::transaction(function () use ($id, $action, $payload, &$shouldDispatch): Task {
            $task = Task::forWorkspace()->lockForUpdate()->findOrFail($id);

            match ($action) {
                'pause' => $task->pause(),
                'resume' => (function () use ($task, &$shouldDispatch): void {
                    $task->resume();
                    if ($task->agent_id) {
                        $this->ensureRunForTask($task);
                        $shouldDispatch = true;
                    }
                    $task->start();
                })(),
                'complete' => $task->complete($payload),
                'fail' => $task->fail($payload),
                'cancel' => $task->cancel(),
                default => throw new \InvalidArgumentException("Unknown task action [{$action}]"),
            };

            return $task;
        });

        if ($shouldDispatch) {
            $this->dispatchOrRestoreWaiting($task);
        }

        $eventAction = match ($action) {
            'resume' => 'started',
            'pause' => 'paused',
            'complete' => 'completed',
            'fail' => 'failed',
            'cancel' => 'cancelled',
        };
        safeBroadcast(new TaskUpdated($task, $eventAction), "task {$action}");

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    /**
     * @return Collection<int, TaskStep>
     */
    public function steps(string $id)
    {
        return Task::forWorkspace()
            ->findOrFail($id)
            ->steps()
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addStep(string $id, array $data): TaskStep
    {
        $task = Task::forWorkspace()->findOrFail($id);

        return $task->addStep(
            $data['description'] ?? '',
            $data['type'] ?? TaskStep::TYPE_ACTION,
            $data['metadata'] ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateStep(string $taskId, string $stepId, array $data): TaskStep
    {
        $task = Task::forWorkspace()->findOrFail($taskId);
        $step = $task->steps()
            ->where('id', $stepId)
            ->firstOrFail();

        $updates = array_intersect_key($data, array_flip(['description', 'metadata']));
        if (array_key_exists('stepType', $data)) {
            $updates['step_type'] = $data['stepType'];
        }

        $step->update($updates);

        return $step;
    }

    public function completeStep(string $taskId, string $stepId): TaskStep
    {
        $task = Task::forWorkspace()->findOrFail($taskId);
        $step = $task->steps()
            ->where('id', $stepId)
            ->firstOrFail();

        $step->complete();

        return $step;
    }

    /**
     * Validate every caller-supplied relationship against the active workspace.
     * IDs are never trusted merely because the parent Task is scoped.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertWorkspaceReferences(array $data, ?Task $task = null): void
    {
        $workspaceId = workspace()->id;

        if (($agentId = $data['agentId'] ?? null) !== null && ! User::query()
            ->whereKey($agentId)
            ->where('type', 'agent')
            ->where('workspace_id', $workspaceId)
            ->exists()) {
            throw new \DomainException('The assigned agent does not belong to the active workspace.');
        }

        if (($requesterId = $data['requesterId'] ?? null) !== null && ! User::query()
            ->whereKey($requesterId)
            ->where(function ($query) use ($workspaceId): void {
                $query->where('workspace_id', $workspaceId)
                    ->orWhereHas('workspaces', fn ($membership) => $membership->where('workspaces.id', $workspaceId));
            })
            ->exists()) {
            throw new \DomainException('The requester does not belong to the active workspace.');
        }

        if (($channelId = $data['channelId'] ?? null) !== null && ! Channel::query()
            ->whereKey($channelId)
            ->where('workspace_id', $workspaceId)
            ->exists()) {
            throw new \DomainException('The channel does not belong to the active workspace.');
        }

        if (($parentTaskId = $data['parentTaskId'] ?? null) !== null) {
            if ($task?->id === $parentTaskId || ! Task::forWorkspace()->whereKey($parentTaskId)->exists()) {
                throw new \DomainException('The parent task does not belong to the active workspace.');
            }
        }

        if (($listItemId = $data['listItemId'] ?? null) !== null && ! ListItem::forWorkspace()->whereKey($listItemId)->exists()) {
            throw new \DomainException('The list item does not belong to the active workspace.');
        }
    }

    private function ensureRunForTask(Task $task): AgentRunRecord
    {
        $kind = match ($task->source) {
            Task::SOURCE_AUTOMATION => (($task->context['execution_type'] ?? null) === 'script'
                ? AgentRunRecord::KIND_SCRIPT_AUTOMATION
                : AgentRunRecord::KIND_PROMPT_AUTOMATION),
            Task::SOURCE_CHAT, Task::SOURCE_AGENT_DELEGATION, Task::SOURCE_AGENT_ASK, Task::SOURCE_AGENT_NOTIFY => AgentRunRecord::KIND_CHAT,
            default => AgentRunRecord::KIND_ASSIGNED_TASK,
        };

        $maxAttempts = in_array($kind, [AgentRunRecord::KIND_PROMPT_AUTOMATION, AgentRunRecord::KIND_SCRIPT_AUTOMATION], true) ? 2 : 3;
        $run = AgentRunRecord::ensureForTask($task, $kind, $maxAttempts);
        if ($run->status === AgentRunRecord::STATUS_WAITING) {
            $run->markQueued();
        }

        return $run;
    }

    /**
     * Dispatch the physical adapter for an already-claimed active Task.
     */
    private function dispatchTask(Task $task): void
    {
        $task->loadMissing(['agent', 'triggerMessage', 'agentRun']);

        if (in_array($task->source, [Task::SOURCE_CHAT, Task::SOURCE_AGENT_DELEGATION, Task::SOURCE_AGENT_ASK, Task::SOURCE_AGENT_NOTIFY], true)) {
            if (! $task->agent) {
                throw new \DomainException('Chat task cannot be dispatched without an agent.');
            }

            if ($task->triggerMessage && $task->channel_id) {
                AgentRespondJob::dispatch($task->triggerMessage, $task->agent, $task->channel_id, $task->id);

                return;
            }

            // Older/imported chat tasks may predate durable trigger-message
            // correlation. They cannot safely reconstruct a chat response, but
            // they still contain a complete task description. Execute them via
            // the assigned-task adapter instead of leaving an active task with
            // no physical queue job after resume.
            ExecuteAgentTaskJob::dispatch($task);

            return;
        }

        if ($task->source === Task::SOURCE_AUTOMATION) {
            $automationId = $task->context['automation_id'] ?? null;
            $automation = Automation::forWorkspace()->find($automationId);
            if (! $automation || ! $task->agentRun?->dispatch_key) {
                throw new \DomainException('Automation task cannot be resumed without its automation and dispatch key.');
            }

            RunAutomationJob::dispatch($automation, $task->agentRun->dispatch_key);

            return;
        }

        ExecuteAgentTaskJob::dispatch($task);
    }

    /**
     * Keep a synchronous dispatch failure from stranding an active task with
     * no physical job. Database-queue dispatch failures are unambiguous: if the
     * insert throws, the task is returned to a visible waiting state and can be
     * resumed after the queue/storage problem is repaired.
     */
    private function dispatchOrRestoreWaiting(Task $task): void
    {
        try {
            $this->dispatchTask($task);
        } catch (\Throwable $exception) {
            $task = DB::transaction(function () use ($task, $exception): Task {
                $locked = Task::forWorkspace()->lockForUpdate()->findOrFail($task->id);

                if ($locked->status === Task::STATUS_ACTIVE) {
                    $locked->pause();
                }
                $locked->agentRun?->markWaiting($exception->getMessage());

                return $locked;
            });

            safeBroadcast(new TaskUpdated($task, 'paused'), 'task dispatch failed');

            throw $exception;
        }
    }
}

<?php

namespace App\Domain\Work\Application;

use App\Jobs\ExecuteAgentTaskJob;
use App\Models\Task;
use App\Models\TaskStep;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
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
            $subtasks = Task::whereIn('parent_task_id', $rootIds)
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
        return Task::create([
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
        ])->load(['agent', 'requester', 'channel', 'steps']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): Task
    {
        $task = Task::forWorkspace()->findOrFail($id);
        $updates = array_intersect_key($data, array_flip([
            'title', 'description', 'type', 'priority', 'context', 'result',
        ]));

        foreach (['agentId' => 'agent_id', 'channelId' => 'channel_id', 'dueAt' => 'due_at'] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $updates[$column] = $data[$input];
            }
        }

        $task->update($updates);

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    public function delete(string $id): void
    {
        Task::forWorkspace()->findOrFail($id)->delete();
    }

    public function start(string $id): Task
    {
        $task = Task::forWorkspace()->findOrFail($id);

        if ($task->agent_id) {
            ExecuteAgentTaskJob::dispatch($task);

            return $task->load(['agent', 'requester', 'channel', 'steps']);
        }

        $task->start();

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    public function transition(string $id, string $action, mixed $payload = null): Task
    {
        $task = Task::forWorkspace()->findOrFail($id);

        match ($action) {
            'pause' => $task->pause(),
            'resume' => $task->resume(),
            'complete' => $task->complete($payload),
            'fail' => $task->fail($payload),
            'cancel' => $task->cancel(),
            default => throw new \InvalidArgumentException("Unknown task action [{$action}]"),
        };

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
}

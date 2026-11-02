<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\Task;
use App\Models\User;

/**
 * Task mount operations for OpenCompanyVfs.
 */
trait MountsVfsTasks
{
    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listTasks(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        return $this->pageTasks($agent, $segments, $path, $budget, [])['entries'];
    }

    /**
     * Return an adapter-owned task page before cursor encoding.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{
     *     entries: list<VfsEntry>,
     *     has_more: bool,
     *     next_state: array<string, mixed>,
     *     scanned: int,
     *     skipped: int
     * }
     */
    private function pageTasks(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        if (count($segments) === 1) {
            return $this->paginateVfsStaticEntries($this->taskRootEntries(), $budget->maxEntries, $state);
        }

        $query = Task::forWorkspace()->with('agent:id,name');
        $label = $segments[1] ?? '';

        if ($label === 'by-id') {
            if (! isset($segments[2])) {
                return $this->paginateVfsRecentQuery(
                    $query,
                    $budget->maxEntries,
                    $budget->maxFiles,
                    $state,
                    fn (Task $task): VfsEntry => $this->taskEntry($agent, $task),
                );
            }
            throw VfsError::notReadable($path);
        }

        if ($label === 'open') {
            $query->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED, Task::STATUS_FAILED]);
        } elseif ($label === 'completed') {
            $query->where('status', Task::STATUS_COMPLETED);
        } elseif ($label === 'by-status' && ! isset($segments[2])) {
            $entries = Task::forWorkspace()
                ->select('status')
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->map(fn (string $status): VfsEntry => new VfsEntry($status, "/tasks/by-status/{$status}", 'directory', capabilities: ['browse', 'read', 'search']))
                ->all();

            return $this->paginateVfsStaticEntries($entries, $budget->maxEntries, $state);
        } elseif ($label === 'by-status' && isset($segments[2])) {
            $query->where('status', $segments[2]);
        } elseif ($label === 'by-agent' && ! isset($segments[2])) {
            return $this->paginateVfsLabelQuery(
                User::query()
                    ->where('workspace_id', $agent->workspace_id)
                    ->where('type', 'agent'),
                'name',
                $budget->maxEntries,
                $state,
                fn (User $target): VfsEntry => new VfsEntry(
                    $this->agentToken($agent, $target),
                    "/tasks/by-agent/{$this->agentToken($agent, $target)}",
                    'directory',
                    capabilities: ['browse', 'read', 'search'],
                    metadata: ['agent_id' => $target->id, 'name' => $target->name],
                ),
            );
        } elseif ($label === 'by-agent' && isset($segments[2])) {
            $query->where('agent_id', $this->resolveAgent($agent, [$segments[2]], $path)->id);
            if (isset($segments[3])) {
                $segments[3] === 'completed'
                    ? $query->where('status', Task::STATUS_COMPLETED)
                    : $query->where('status', $segments[3]);
            }
        } else {
            throw VfsError::notFound($path);
        }

        return $this->paginateVfsRecentQuery(
            $query,
            $budget->maxEntries,
            $budget->maxFiles,
            $state,
            fn (Task $task): VfsEntry => $this->taskEntry($agent, $task),
        );
    }

    /** @return list<VfsEntry> */
    private function taskRootEntries(): array
    {
        return [
            new VfsEntry('open', '/tasks/open', 'directory', capabilities: ['browse', 'read', 'search']),
            new VfsEntry('completed', '/tasks/completed', 'directory', capabilities: ['browse', 'read', 'search']),
            new VfsEntry('by-id', '/tasks/by-id', 'directory', capabilities: ['browse', 'read']),
            new VfsEntry('by-status', '/tasks/by-status', 'directory', capabilities: ['browse']),
            new VfsEntry('by-agent', '/tasks/by-agent', 'directory', capabilities: ['browse']),
            new VfsEntry('recent.md', '/tasks/recent.md', 'file', capabilities: ['read']),
            new VfsEntry('counts.json', '/tasks/counts.json', 'file', capabilities: ['read']),
        ];
    }

    /**
     * @param  list<string>  $segments
     */
    private function readTasks(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (($segments[1] ?? null) === 'counts.json') {
            return json_encode([
                'total' => Task::forWorkspace()->count(),
                'open' => Task::forWorkspace()->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED, Task::STATUS_FAILED])->count(),
                'completed' => Task::forWorkspace()->where('status', Task::STATUS_COMPLETED)->count(),
            ], JSON_PRETTY_PRINT);
        }

        if (($segments[1] ?? null) === 'recent.md') {
            return Task::forWorkspace()->latest('updated_at')->limit(25)->get()->map(fn (Task $task): string => "- [{$task->status}] {$task->title} ({$task->id})")->implode("\n");
        }

        if (($segments[1] ?? null) === 'by-id' && isset($segments[2])) {
            if (isset($segments[3])) {
                throw VfsError::notFound($path);
            }
            $task = Task::forWorkspace()->with(['agent:id,name', 'steps'])->find($segments[2]) ?? throw VfsError::notFound($path);

            return $this->taskMarkdown($task);
        }

        return $this->entriesToText($this->listTasks($agent, $segments, $path, $budget));
    }

    private function taskEntry(User $agent, Task $task): VfsEntry
    {
        $path = "/tasks/by-id/{$task->id}";
        $patchDecision = $this->entryMutationDecision(
            $agent,
            $path,
            'patch',
            ['vfs_patch'],
            'Patch this task with vfs_patch; only the supported task fields may be changed.',
            $task->agent_id === $agent->id,
            'Only the agent assigned to a task can patch it through VFS.',
        );

        return new VfsEntry(
            name: $this->slug($task->title).'.md',
            path: $path,
            type: 'file',
            canonicalPath: $path,
            backendType: 'task',
            backendId: $task->id,
            version: $this->version($task->updated_at?->toIso8601String(), $task->status),
            capabilities: $this->mutationCapabilities(['read', 'search'], ['patch' => $patchDecision]),
            metadata: [
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'agent' => $task->agent->name ?? null,
                'updated_at' => $task->updated_at?->toIso8601String(),
                ...$patchDecision->metadata(),
                ...$this->mutationActionsMetadata(['patch' => $patchDecision]),
            ],
        );
    }

    private function taskMarkdown(Task $task): string
    {
        $lines = [
            '# '.$task->title,
            '',
            "- ID: {$task->id}",
            "- Status: {$task->status}",
            "- Priority: {$task->priority}",
            "- Type: {$task->type}",
            '- Agent: '.($task->agent->name ?? 'Unassigned'),
            '',
            '## Description',
            '',
            (string) ($task->description ?? ''),
        ];

        if ($task->relationLoaded('steps') && $task->steps->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Steps';
            foreach ($task->steps as $step) {
                $lines[] = '- ['.($step->completed_at ? 'x' : ' ').'] '.(string) $step->description;
            }
        }

        return implode("\n", $lines);
    }
}

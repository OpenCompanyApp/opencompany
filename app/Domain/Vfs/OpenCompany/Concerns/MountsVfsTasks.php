<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Task mount operations for OpenCompanyVfs.
 */
trait MountsVfsTasks
{
    /**
     * @return list<VfsEntry>
     */
    private function listTasks(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        if (count($segments) === 1) {
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

        $query = Task::forWorkspace()->with('agent:id,name')->latest('updated_at');
        $label = $segments[1] ?? '';

        if ($label === 'by-id') {
            if (! isset($segments[2])) {
                return $query->limit($budget->maxEntries)->get()->map(fn (Task $task): VfsEntry => $this->taskEntry($task))->all();
            }
            throw VfsError::notReadable($path);
        }

        if ($label === 'open') {
            $query->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED, Task::STATUS_FAILED]);
        } elseif ($label === 'completed') {
            $query->where('status', Task::STATUS_COMPLETED);
        } elseif ($label === 'by-status' && ! isset($segments[2])) {
            return Task::forWorkspace()
                ->select('status')
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->limit($budget->maxEntries)
                ->pluck('status')
                ->map(fn (string $status): VfsEntry => new VfsEntry($status, "/tasks/by-status/{$status}", 'directory', capabilities: ['browse', 'read', 'search']))
                ->all();
        } elseif ($label === 'by-status' && isset($segments[2])) {
            $query->where('status', $segments[2]);
        } elseif ($label === 'by-agent' && ! isset($segments[2])) {
            return User::where('workspace_id', $agent->workspace_id)
                ->where('type', 'agent')
                ->orderBy('name')
                ->limit($budget->maxEntries)
                ->get()
                ->map(fn (User $target): VfsEntry => new VfsEntry($this->slug($target->name), "/tasks/by-agent/{$this->slug($target->name)}", 'directory', capabilities: ['browse', 'read', 'search'], metadata: ['agent_id' => $target->id, 'name' => $target->name]))
                ->all();
        } elseif ($label === 'by-agent' && isset($segments[2])) {
            $agentSlug = $segments[2];
            $target = User::where('workspace_id', $agent->workspace_id)
                ->where('type', 'agent')
                ->get()
                ->first(fn (User $candidate): bool => Str::slug($candidate->name) === $agentSlug);
            $target ? $query->where('agent_id', $target->id) : $query->whereRaw('1 = 0');
            if (isset($segments[3])) {
                $segments[3] === 'completed'
                    ? $query->where('status', Task::STATUS_COMPLETED)
                    : $query->where('status', $segments[3]);
            }
        } else {
            throw VfsError::notFound($path);
        }

        return $query->limit($budget->maxEntries)->get()->map(fn (Task $task): VfsEntry => $this->taskEntry($task))->all();
    }

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

    private function taskEntry(Task $task): VfsEntry
    {
        return new VfsEntry(
            name: $this->slug($task->title).'.md',
            path: "/tasks/by-id/{$task->id}",
            type: 'file',
            canonicalPath: "/tasks/by-id/{$task->id}",
            backendType: 'task',
            backendId: $task->id,
            version: $this->version($task->updated_at?->toIso8601String(), $task->status),
            capabilities: ['read', 'search', 'patch'],
            metadata: [
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'agent' => $task->agent?->name,
                'updated_at' => $task->updated_at?->toIso8601String(),
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
            '- Agent: '.($task->agent?->name ?? 'Unassigned'),
            '',
            '## Description',
            '',
            (string) ($task->description ?? ''),
        ];

        if ($task->relationLoaded('steps') && $task->steps->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Steps';
            foreach ($task->steps as $step) {
                $lines[] = '- ['.($step->completed_at ? 'x' : ' ')."] {$step->title}";
            }
        }

        return implode("\n", $lines);
    }
}

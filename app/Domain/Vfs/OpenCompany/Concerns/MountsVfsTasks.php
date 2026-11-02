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
 *
 * Tasks are agent run records, created by the runtime when work is assigned;
 * there is no create_task domain tool, so the VFS deliberately offers no task
 * create or delete either (an agent "filing work" should create a list
 * item/issue). Patches keep their semantics: the assigned agent may change
 * title, description, status and priority (PatchesVfsRecords).
 *
 * Naming contract (every listed name opens): listings under open/,
 * completed/, by-status/{s}/ and by-agent/{a}[/{s}]/ name a task
 * `{title-slug}--{id8}.md`; /tasks/by-id/ names it `{uuid}.md`. Under ANY of
 * those directories a file token resolves by the full id (with or without
 * `.md`) or by its `--{id8}` suffix, so `{listing dir}/{listed name}`, the
 * canonical `/tasks/by-id/{uuid}` and the by-id listing names all address the
 * same task (taskFileSegments()).
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
        if ($this->taskFileSegments($segments, $path) !== null) {
            // A task file is not a directory; never fall back to listing the
            // parent (that made a listed name look like a folder).
            throw VfsError::notReadable($path);
        }

        $query = Task::forWorkspace()->with('agent:id,name');
        $label = $segments[1] ?? '';
        $maxDepth = match ($label) {
            'by-id', 'open', 'completed' => 2,
            'by-status' => 3,
            'by-agent' => 4,
            default => 0,
        };
        if (count($segments) > $maxDepth) {
            throw VfsError::notFound($path);
        }

        if ($label === 'by-id') {
            return $this->paginateVfsRecentQuery(
                $query,
                $budget->maxEntries,
                $budget->maxFiles,
                $state,
                fn (Task $task): VfsEntry => $this->taskEntry($agent, $task, $path, byId: true),
            );
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
            fn (Task $task): VfsEntry => $this->taskEntry($agent, $task, $path),
        );
    }

    /**
     * The canonical segments `['tasks', 'by-id', <uuid>]` when $segments name
     * one task file under a task listing directory, null when they name a
     * directory (or nothing task-shaped).
     *
     * File position per listing: by-id/{T}, open/{T}, completed/{T},
     * by-status/{s}/{T}, by-agent/{a}/{T} and by-agent/{a}/{s}/{T}. Under
     * by-agent/{a}/ the third segment is a task only when it is file-shaped
     * (taskTokenLooksLikeFile), otherwise it is a status directory.
     *
     * @param  list<string>  $segments
     * @return list<string>|null
     *
     * @throws VfsError not_found when a file-shaped token matches no task
     */
    private function taskFileSegments(array $segments, string $path): ?array
    {
        $position = match ($segments[1] ?? null) {
            'by-id', 'open', 'completed' => 2,
            'by-status' => 3,
            'by-agent' => isset($segments[3]) && ! $this->taskTokenLooksLikeFile($segments[3]) ? 4 : 3,
            default => null,
        };
        if ($position === null || ! isset($segments[$position])) {
            return null;
        }
        if (isset($segments[$position + 1])) {
            throw VfsError::notFound($path);
        }
        if (($segments[1] ?? null) !== 'by-id' && ! $this->taskTokenLooksLikeFile($segments[$position])) {
            return null;
        }

        return ['tasks', 'by-id', $this->resolveTaskToken($segments[$position], $path)->id];
    }

    private function taskTokenLooksLikeFile(string $token): bool
    {
        return str_ends_with(strtolower($token), '.md')
            || Str::isUuid($token)
            || preg_match(self::ID_SUFFIX_PATTERN, $token) === 1;
    }

    /**
     * Resolve a task file token: `{uuid}`, `{uuid}.md`, or `{anything}--{id8}`
     * with or without `.md`. Workspace scoped; an id suffix matching more
     * than one task is reported as not found with the by-id hint rather than
     * guessed.
     *
     * @throws VfsError not_found
     */
    private function resolveTaskToken(string $token, string $path): Task
    {
        $bare = preg_replace('/\.md$/i', '', $token) ?? $token;
        $notFound = fn (): VfsError => new VfsError('not_found', "Path not found: {$path}", [
            'path' => $path,
            'hint' => 'Task files are named as listed (`{title}--{id8}.md`) or by id: /tasks/by-id/{id}.',
        ]);

        if (Str::isUuid($bare) || preg_match(self::ID_SUFFIX_PATTERN, $bare) !== 1) {
            return Task::forWorkspace()->with(['agent:id,name', 'steps'])->find($bare) ?? throw $notFound();
        }

        preg_match(self::ID_SUFFIX_PATTERN, $bare, $m);
        $matches = Task::forWorkspace()->with(['agent:id,name', 'steps'])->where('id', 'like', '%'.$m[2])->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : throw $notFound();
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

        $file = $this->taskFileSegments($segments, $path);
        if ($file !== null) {
            $task = Task::forWorkspace()->with(['agent:id,name', 'steps'])->find($file[2]) ?? throw VfsError::notFound($path);
            $budget->recordCanonicalPath("/tasks/by-id/{$task->id}");

            return $this->taskMarkdown($task);
        }

        return $this->entriesToText($this->listTasks($agent, $segments, $path, $budget));
    }

    /**
     * One task as a listing entry. $directory is the listing it appears in
     * (null: the canonical by-id path itself); the entry path is always
     * `{directory}/{name}` so the listed name opens where it was listed.
     */
    private function taskEntry(User $agent, Task $task, ?string $directory = null, bool $byId = false): VfsEntry
    {
        $canonical = "/tasks/by-id/{$task->id}";
        $name = $byId || $directory === null
            ? "{$task->id}.md"
            : $this->slug((string) $task->title).'--'.$this->idSuffix((string) $task->id).'.md';
        $path = $directory === null ? $canonical : rtrim($directory, '/').'/'.$name;
        $patchDecision = $this->entryMutationDecision(
            $agent,
            $canonical,
            'patch',
            ['vfs_patch'],
            'Patch this task with vfs_patch; only the supported task fields may be changed.',
            $task->agent_id === $agent->id,
            'Only the agent assigned to a task can patch it through VFS.',
        );

        return new VfsEntry(
            name: $name,
            path: $path,
            type: 'file',
            canonicalPath: $canonical,
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

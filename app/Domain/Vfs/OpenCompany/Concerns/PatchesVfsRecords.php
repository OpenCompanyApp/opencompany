<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\ListItem;
use App\Models\ListStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Structured patch helpers for task/list records and text patch validation.
 */
trait PatchesVfsRecords
{
    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function patchTask(User $agent, array $segments, string $path, string $patch, ?string $version, array $toolParameters): array
    {
        if (($segments[1] ?? null) !== 'by-id' || ! isset($segments[2]) || isset($segments[3])) {
            throw VfsError::notWritable($path);
        }

        $task = Task::forWorkspace()->find($segments[2]) ?? throw VfsError::notFound($path);
        if ($task->agent_id !== $agent->id) {
            throw VfsError::permissionDenied($path, 'task is not assigned to this agent');
        }
        $this->assertVersion($version, $this->version($task->updated_at?->toIso8601String(), $task->status), $path);

        $fields = $this->structuredPatchFields($patch, ['title', 'description', 'status', 'priority']);

        if (isset($fields['status'])) {
            $status = (string) $fields['status'];
            $validStatuses = [
                Task::STATUS_PENDING,
                Task::STATUS_ACTIVE,
                Task::STATUS_PAUSED,
                Task::STATUS_COMPLETED,
                Task::STATUS_FAILED,
                Task::STATUS_CANCELLED,
            ];
            if (! in_array($status, $validStatuses, true)) {
                throw VfsError::invalid("Unsupported task status: {$status}");
            }

            $fields['completed_at'] = $status === Task::STATUS_COMPLETED ? now() : null;
        }

        $this->assertMutationToolsAllowed($agent, $path, ['vfs_patch'], $toolParameters, isset($toolParameters['channelId']) && is_string($toolParameters['channelId']) ? $toolParameters['channelId'] : null, [
            'kind' => 'task',
            'task_id' => $task->id,
            'fields' => array_keys($fields),
        ]);

        $task->update($fields);

        return [
            'status' => 'updated',
            'path' => $path,
            'canonical_path' => "/tasks/by-id/{$task->id}",
            'version' => $this->version($task->fresh()->updated_at?->toIso8601String(), $task->fresh()->status),
            'fields' => array_keys($fields),
        ];
    }

    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function patchListItem(User $agent, array $segments, string $path, string $patch, ?string $version, array $toolParameters): array
    {
        if (($segments[1] ?? null) !== 'by-id' || ! isset($segments[2]) || isset($segments[4])) {
            throw VfsError::notWritable($path);
        }

        $item = ListItem::forWorkspace()->find($segments[2]) ?? throw VfsError::notFound($path);
        if (isset($segments[3])) {
            $item = $this->resolveListChild($item, $segments[3], $path);
        }
        if ($item->creator_id !== $agent->id && $item->assignee_id !== $agent->id) {
            throw VfsError::permissionDenied($path, 'list item is not owned by or assigned to this agent');
        }
        $this->assertVersion($version, $this->version($item->updated_at?->toIso8601String(), $item->status ?? ''), $path);

        $fields = $this->structuredPatchFields($patch, ['title', 'description', 'status', 'priority']);

        if (isset($fields['status'])) {
            $status = (string) $fields['status'];
            $newStatus = ListStatus::forWorkspace()->where('slug', $status)->first();
            if ($newStatus) {
                $fields['completed_at'] = $newStatus->is_done ? ($item->completed_at ?? now()) : null;
            }
        }

        $this->assertMutationToolsAllowed($agent, $path, ['vfs_patch'], $toolParameters, isset($toolParameters['channelId']) && is_string($toolParameters['channelId']) ? $toolParameters['channelId'] : null, [
            'kind' => 'list_item',
            'list_item_id' => $item->id,
            'fields' => array_keys($fields),
        ]);

        $item->update($fields);

        return [
            'status' => 'updated',
            'path' => $path,
            'canonical_path' => "/lists/by-id/{$item->id}",
            'version' => $this->version($item->fresh()->updated_at?->toIso8601String(), $item->fresh()->status ?? ''),
            'fields' => array_keys($fields),
        ];
    }

    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function patchTableRow(User $agent, array $segments, string $path, string $patch, ?string $version, array $toolParameters): array
    {
        if (! isset($segments[1]) || ($segments[2] ?? null) !== 'rows' || ($segments[3] ?? null) !== 'by-id' || ! isset($segments[4]) || isset($segments[5])) {
            throw VfsError::notWritable($path);
        }

        $table = DataTable::forWorkspace()
            ->with('columns')
            ->get()
            ->first(fn (DataTable $candidate): bool => $this->slug($candidate->name) === $segments[1])
            ?? throw VfsError::notFound($path);

        $rowId = Str::before($segments[4], '.json');
        $row = DataTableRow::where('table_id', $table->id)->find($rowId) ?? throw VfsError::notFound($path);
        $this->assertVersion($version, $this->tableRowVersion($row), $path);

        $decoded = json_decode($patch, true);
        if (! is_array($decoded) || ! is_array($decoded['data'] ?? null) || $decoded['data'] === []) {
            throw VfsError::invalid('Table row patches require JSON object {"data": {...}}.');
        }

        $resolved = $this->resolveTableRowPatchData($table, $decoded['data']);
        $this->assertMutationToolsAllowed($agent, $path, ['vfs_patch', 'update_table_row'], $toolParameters, isset($toolParameters['channelId']) && is_string($toolParameters['channelId']) ? $toolParameters['channelId'] : null, [
            'kind' => 'table_row',
            'table' => $table->name,
            'row_id' => $row->id,
            'fields' => array_keys($resolved),
        ]);

        $row->update(['data' => array_merge($row->data ?? [], $resolved)]);
        $fresh = $row->fresh();

        return [
            'status' => 'updated',
            'path' => $path,
            'canonical_path' => "/tables/{$this->slug($table->name)}/rows/by-id/{$row->id}.json",
            'version' => $this->tableRowVersion($fresh),
            'fields' => array_keys($resolved),
        ];
    }

    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function patchAgentDocument(User $agent, array $segments, string $path, string $patch, ?string $version, array $toolParameters): array
    {
        $target = $this->resolveAgent($agent, array_slice($segments, 1), $path);
        $this->assertCanAccessAgentDocs($agent, $target, $path);
        $remaining = $this->agentRemainingSegments(array_slice($segments, 1));

        if ($remaining === [] || ! in_array($remaining[0], ['identity', 'memory'], true) || ! isset($remaining[1])) {
            throw VfsError::notWritable($path);
        }

        $doc = $this->agentDocumentFor($target, $remaining, $path);
        if ($doc->is_folder) {
            throw VfsError::notWritable($path);
        }
        $this->assertCanPatchAgentDocument($agent, $target, $doc, $path, $remaining, $toolParameters, [
            'kind' => 'text_patch',
            'agent' => $target->name,
            'document' => $doc->title,
            'patch' => Str::limit($patch, 1000),
        ]);

        $this->assertVersion($version, $this->version($doc->updated_at?->toIso8601String(), $doc->content ?? ''), $path);

        $previousContent = (string) ($doc->content ?? '');
        $next = $this->applyTextPatch($previousContent, $patch);

        $fresh = DB::transaction(function () use ($agent, $doc, $next, $previousContent, $remaining): Document {
            if ($previousContent !== $next) {
                $lastVersion = DocumentVersion::where('document_id', $doc->id)->max('version_number') ?? 0;

                DocumentVersion::create([
                    'id' => Str::uuid()->toString(),
                    'document_id' => $doc->id,
                    'title' => $doc->title,
                    'content' => $previousContent,
                    'author_id' => $agent->id,
                    'version_number' => $lastVersion + 1,
                    'change_description' => $remaining[0] === 'identity'
                        ? 'VFS identity patch snapshot before update'
                        : 'VFS memory patch snapshot before update',
                ]);
            }

            $doc->update(['content' => $next]);

            return $doc->fresh() ?? $doc;
        });

        $indexing = ['ok' => true];
        if ($remaining[0] === 'memory') {
            try {
                app(DocumentIndexingService::class)->index($fresh, $this->agentMemoryIndexCollection($remaining), $target->id);
            } catch (\Throwable $e) {
                $indexing = [
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'status' => 'updated',
            'path' => $path,
            'canonical_path' => "/docs/by-id/{$doc->id}",
            'version' => $this->version($fresh->updated_at?->toIso8601String(), $fresh->content ?? ''),
            'indexing' => $indexing,
        ];
    }

    private function applyTextPatch(string $current, string $patch): string
    {
        $decoded = json_decode($patch, true);
        if (is_array($decoded) && array_key_exists('search', $decoded) && array_key_exists('replace', $decoded)) {
            $search = (string) $decoded['search'];
            if ($search === '' || ! str_contains($current, $search)) {
                throw VfsError::invalid('Patch search text was not found.');
            }

            return Str::replaceFirst($search, (string) $decoded['replace'], $current);
        }

        if (preg_match('/^<<<<<<< SEARCH\R(?<search>.*?)\R=======\R(?<replace>.*?)\R>>>>>>> REPLACE$/s', trim($patch), $matches) === 1) {
            $search = $matches['search'];
            if (! str_contains($current, $search)) {
                throw VfsError::invalid('Patch search block was not found.');
            }

            return Str::replaceFirst($search, $matches['replace'], $current);
        }

        throw VfsError::invalid('Unsupported patch format. Use JSON {"search":"old","replace":"new"} or SEARCH/REPLACE block.');
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function structuredPatchFields(string $patch, array $allowed): array
    {
        $decoded = json_decode($patch, true);
        if (! is_array($decoded)) {
            throw VfsError::invalid('Task and list patches require JSON object fields.');
        }

        $fields = $decoded['fields'] ?? $decoded;
        if (! is_array($fields) || $fields === []) {
            throw VfsError::invalid('Patch fields are required.');
        }

        $unknown = array_diff(array_keys($fields), $allowed);
        if ($unknown !== []) {
            throw VfsError::invalid('Unsupported patch fields: '.implode(', ', $unknown));
        }

        return array_intersect_key($fields, array_flip($allowed));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveTableRowPatchData(DataTable $table, array $data): array
    {
        $columns = $table->columns;
        $byId = $columns->keyBy('id');
        $resolved = [];

        foreach ($data as $key => $value) {
            if ($byId->has($key)) {
                $resolved[$key] = $value;

                continue;
            }

            $matches = $columns
                ->filter(fn (DataTableColumn $column): bool => mb_strtolower($column->name) === mb_strtolower((string) $key))
                ->values();

            if ($matches->isEmpty()) {
                throw VfsError::invalid("Unknown table column: {$key}");
            }
            if ($matches->count() > 1) {
                throw VfsError::invalid("Ambiguous table column name: {$key}. Use the column UUID.");
            }

            $resolved[$matches->first()->id] = $value;
        }

        return $resolved;
    }

    /**
     * @param  list<string>  $remaining
     */
    private function agentMemoryIndexCollection(array $remaining): string
    {
        return match ($remaining[1] ?? null) {
            'topics' => 'topic',
            'peers' => 'peer',
            default => 'memory',
        };
    }

    private function assertVersion(?string $given, ?string $expected, string $path): void
    {
        if ($given !== null && $expected !== null && ! hash_equals($expected, $given)) {
            throw VfsError::invalid("Version mismatch for {$path}", [
                'path' => $path,
                'expected' => $expected,
                'given' => $given,
            ]);
        }
    }
}

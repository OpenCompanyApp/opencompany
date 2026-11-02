<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsTextPatch;
use App\Domain\Vfs\OpenCompany\Policy\VfsActionClass;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\ListItem;
use App\Models\Task;
use App\Models\User;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Structured patch helpers for task/list/table-row records and agent documents.
 *
 * Every version-checked write here is a locked compare-and-write: an unlocked
 * precheck (so stale writes fail before policy can create an approval), the
 * mutation policy, then a transaction that locks the row, recomputes the
 * version from the locked row, and writes. Record patches are strict: a stale
 * supplied version is always `conflict`. Only text patches (agent documents)
 * get content-anchored staleness, via VfsTextPatch.
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
        // Any listed task name patches the task it names (MountsVfsTasks naming contract).
        $file = $this->taskFileSegments($segments, $path) ?? throw VfsError::notWritable($path);

        $task = ($this->vfsUuidOrNull($file[2]) !== null ? Task::forWorkspace()->find($file[2]) : null) ?? throw VfsError::notFound($path);
        if ($task->agent_id !== $agent->id) {
            throw VfsError::permissionDenied($path, 'task is not assigned to this agent');
        }
        $this->assertVersion($version, $this->taskVersion($task), $path);

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
        ], actionClass: VfsActionClass::REVERSIBLE_EDIT);

        $fresh = DB::transaction(function () use ($task, $fields, $version, $path): Task {
            $locked = Task::forWorkspace()->whereKey($task->id)->lockForUpdate()->first() ?? throw VfsError::notFound($path);
            $this->assertVersion($version, $this->taskVersion($locked), $path);
            $locked->update($fields);

            return $locked->fresh() ?? $locked;
        });

        return [
            'status' => 'updated',
            'path' => $path,
            'canonical_path' => "/tasks/by-id/{$fresh->id}",
            'version' => $this->taskVersion($fresh),
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
        $item = $this->resolveListItemSegments($segments, $path);
        $this->assertListItemWritable($agent, $item, $path);
        $this->assertVersion($version, $this->listItemVersion($item), $path);

        $fields = $this->normalizeListItemFields(
            $this->structuredPatchFields($patch, ['title', 'description', 'status', 'priority', 'assignee', 'due_date']),
            $item,
            $path,
        );

        $this->assertMutationToolsAllowed($agent, $path, ['vfs_patch', 'update_list_item'], $toolParameters, isset($toolParameters['channelId']) && is_string($toolParameters['channelId']) ? $toolParameters['channelId'] : null, [
            'kind' => 'list_item',
            'action_class' => 'reversible_edit',
            'list_item_id' => $item->id,
            'fields' => array_keys($fields),
        ], actionClass: VfsActionClass::REVERSIBLE_EDIT);

        $fresh = DB::transaction(function () use ($item, $fields, $version, $path): ListItem {
            $locked = ListItem::forWorkspace()->whereKey($item->id)->lockForUpdate()->first() ?? throw VfsError::notFound($path);
            $this->assertVersion($version, $this->listItemVersion($locked), $path);
            $locked->update($fields);

            return $locked->fresh() ?? $locked;
        });

        return [
            'status' => 'updated',
            'path' => $path,
            'canonical_path' => "/lists/by-id/{$fresh->id}",
            'version' => $this->listItemVersion($fresh),
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
        ['table' => $table, 'rest' => $rest, 'base' => $base] = $this->resolveTableSegments($segments, $path);
        $table->loadMissing('columns');
        if (($rest[0] ?? null) !== 'rows' || in_array($rest, [['rows'], ['rows', 'by-id'], ['rows', '.trash']], true) || ($rest[1] ?? null) === '.trash') {
            throw VfsError::notWritable($path);
        }
        $row = $this->resolveTableRowFromRest($table, $rest, $path, $base);
        $this->assertVersion($version, $this->tableRowVersion($row), $path);

        $decoded = json_decode($patch, true);
        if (! is_array($decoded) || ! is_array($decoded['data'] ?? null) || $decoded['data'] === []) {
            throw VfsError::invalid('Table row patches require JSON object {"data": {...}} (optionally "base": {...} with the values you read).');
        }
        if (array_key_exists('base', $decoded) && ! is_array($decoded['base'])) {
            throw VfsError::invalid('Table row patch "base" must be an object of column => value you last read.', ['field' => 'base']);
        }

        $resolved = $this->resolveTableRowPatchData($table, $decoded['data']);
        $this->validateTableRowValues($table, $resolved);
        $resolvedBase = is_array($decoded['base'] ?? null) ? $this->resolveTableRowPatchData($table, $decoded['base']) : [];
        // Unlocked precheck so a row that already diverged does not create an approval.
        $this->assertTableRowBase($table, $row, $resolvedBase, $path, $version);

        $this->assertMutationToolsAllowed($agent, $path, ['vfs_patch', 'update_table_row'], $toolParameters, $this->channelIdFrom($toolParameters), [
            'kind' => 'table_row',
            'action_class' => 'reversible_edit',
            'table' => $table->name,
            'row_id' => $row->id,
            'fields' => array_keys($resolved),
        ], actionClass: VfsActionClass::REVERSIBLE_EDIT);

        $fresh = DB::transaction(function () use ($agent, $table, $row, $resolved, $resolvedBase, $version, $path): DataTableRow {
            // Invariant: merge the supplied columns into the row as it is
            // under the lock, never into the copy loaded before policy ran.
            // That is what keeps concurrent edits to other columns intact.
            $locked = DataTableRow::where('table_id', $table->id)->whereKey($row->id)->lockForUpdate()->first()
                ?? throw VfsError::notFound($path);
            $this->assertVersion($version, $this->tableRowVersion($locked), $path);
            $this->assertTableRowBase($table, $locked, $resolvedBase, $path, $version);

            $previous = $locked->data ?? [];
            $before = $this->tableRowVersion($locked);
            $merged = array_merge($previous, $resolved);
            if ($merged == $previous) {
                return $locked;
            }
            $locked->update(['data' => $merged]);
            $fresh = $locked->fresh() ?? $locked;
            $this->snapshotTableRowVersion($table, $fresh, $previous, $agent, $before, $this->tableRowVersion($fresh), 'VFS patch');

            return $fresh;
        });

        // `fields` uses column names, the same keys the agent reads in
        // rows/by-id; `field_ids` maps them to the stable column ids.
        $fieldNames = $this->tableColumnNames($table, array_keys($resolved));

        return [
            'status' => 'updated',
            'path' => $path,
            'canonical_path' => $this->tableRowCanonicalPath($table, $fresh),
            'version' => $this->tableRowVersion($fresh),
            'fields' => $fieldNames,
            'field_ids' => array_combine($fieldNames, array_map(fn (string|int $id): string => (string) $id, array_keys($resolved))),
        ];
    }

    /**
     * Reject a row patch whose supplied per-field base values no longer match
     * the current row. Context: {path, current_version, given_version, hint,
     * changed_fields: list<column name>, current: {column name: current value}}.
     *
     * @param  array<string, mixed>  $resolvedBase  column id => expected value
     */
    private function assertTableRowBase(DataTable $table, DataTableRow $row, array $resolvedBase, string $path, ?string $version): void
    {
        if ($resolvedBase === []) {
            return;
        }

        $data = $row->data ?? [];
        $names = $table->columns->keyBy('id');
        $changed = [];
        $current = [];
        foreach ($resolvedBase as $columnId => $expected) {
            $actual = $data[$columnId] ?? null;
            if (! $this->sameCellValue($actual, $expected)) {
                $name = (string) ($names->get($columnId)->name ?? $columnId);
                $changed[] = $name;
                $current[$name] = $actual;
            }
        }

        if ($changed !== []) {
            throw VfsError::conflict(
                $path,
                $this->tableRowVersion($row),
                $version,
                'These fields changed since your read. Re-read the row and decide whether your edit still applies.',
                ['changed_fields' => $changed, 'current' => $current],
                'Table row changed since your read: '.implode(', ', $changed).'.',
            );
        }
    }

    /**
     * Compare a stored cell with a caller-supplied base value, exactly.
     *
     * - Two strings compare as strings: "007" and "7" are different values,
     *   and long numeric identifiers must never collide.
     * - When at least one side is a JSON number, numbers compare by exact
     *   decimal value (JSON round-trips may turn 1 into 1.0 or "1"). Never
     *   cast to float: 9007199254740992 and 9007199254740993 are the same
     *   IEEE double, and a stale base would then pass and overwrite a
     *   human's edit.
     * - Everything else compares by JSON value.
     */
    private function sameCellValue(mixed $actual, mixed $expected): bool
    {
        if (is_string($actual) && is_string($expected)) {
            return $actual === $expected;
        }

        $left = $this->canonicalDecimal($actual);
        $right = $this->canonicalDecimal($expected);
        if ($left !== null && $right !== null) {
            return $left === $right;
        }

        return json_encode($actual) === json_encode($expected);
    }

    /**
     * Exact canonical form `[-]digits e exponent` of an int, a finite float
     * (its shortest round-trip repr) or a numeric string, or null for
     * anything else. "1", 1, 1.0, "1.00" and "1e0" all map to "1e0".
     */
    private function canonicalDecimal(mixed $value): ?string
    {
        if (is_int($value)) {
            $text = (string) $value;
        } elseif (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }
            $text = json_encode($value);
            if (! is_string($text)) {
                return null;
            }
        } elseif (is_string($value)) {
            $text = trim($value);
        } else {
            return null;
        }

        if (preg_match('/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/', $text, $m) !== 1 || ($m[2] === '' && ($m[3] ?? '') === '')) {
            return null;
        }

        $fraction = $m[3] ?? '';
        $digits = ltrim($m[2].$fraction, '0');
        if ($digits === '') {
            return '0';
        }
        $exponent = (int) ($m[4] ?? 0) - strlen($fraction);
        $trimmed = rtrim($digits, '0');
        $exponent += strlen($digits) - strlen($trimmed);

        return ($m[1] === '-' ? '-' : '').$trimmed.'e'.$exponent;
    }

    private function taskVersion(Task $task): string
    {
        return $this->version($task->updated_at?->toIso8601String(), (string) $task->status);
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

        $isAgentArea = ($remaining[0] ?? null) === 'notes' && isset($remaining[1]) || $remaining === ['todo.md'];
        if (! $isAgentArea && ($remaining === [] || ! in_array($remaining[0], ['identity', 'memory'], true) || ! isset($remaining[1]))) {
            throw VfsError::notWritable($path);
        }

        $doc = $this->agentDocumentFor($target, $remaining, $path);
        if ($doc->is_folder) {
            throw VfsError::notWritable($path);
        }

        // Validate the edit against the current content before policy, so an
        // impossible identity/memory patch never creates an approval.
        $textPatch = VfsTextPatch::parse($patch);
        $textPatch->applyAgainstBase(
            (string) ($doc->content ?? ''),
            $path,
            $this->version($doc->updated_at?->toIso8601String(), $doc->content ?? ''),
            $version,
        );

        $this->assertCanPatchAgentDocument($agent, $target, $doc, $path, $remaining, $toolParameters, [
            'kind' => 'text_patch',
            'agent' => $target->name,
            'document' => $doc->title,
            'patch' => Str::limit($patch, 1000),
        ]);

        [$fresh, $applied] = $this->lockedDocumentPatch(
            $agent,
            $doc,
            $path,
            $textPatch,
            $version,
            match ($remaining[0]) {
                'identity' => 'VFS identity patch snapshot before update',
                'memory' => 'VFS memory patch snapshot before update',
                default => 'VFS patch',
            },
        );

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
            'canonical_path' => $isAgentArea ? "/agents/by-id/{$target->id}/".implode('/', $remaining) : "/docs/by-id/{$doc->id}",
            'version' => $this->version($fresh->updated_at?->toIso8601String(), $fresh->content ?? ''),
            'indexing' => $indexing,
            ...$this->textPatchResultFields($applied),
        ];
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

            // Errors name the valid columns so the next call can succeed
            // without another schema read.
            if ($matches->isEmpty()) {
                throw VfsError::invalid("Unknown table column: {$key}", [
                    'field' => (string) $key,
                    'columns' => $columns->pluck('name')->map(fn (mixed $name): string => (string) $name)->values()->all(),
                    'hint' => 'Use one of the column names listed in `columns` (case-insensitive), or a column id from schema.json.',
                ]);
            }
            if ($matches->count() > 1) {
                throw VfsError::invalid("Ambiguous table column name: {$key}. Use the column UUID.", [
                    'field' => (string) $key,
                    'column_ids' => $matches->pluck('id')->map(fn (mixed $id): string => (string) $id)->values()->all(),
                ]);
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

    /**
     * Strict optimistic-concurrency check. Null $given means the caller did
     * not request one (optional in this phase). Callers that hold a row lock
     * pass the version recomputed from the locked row.
     *
     * @throws VfsError conflict {path, current_version, given_version, hint}
     */
    private function assertVersion(?string $given, ?string $expected, string $path): void
    {
        if ($given !== null && $expected !== null && ! hash_equals($expected, $given)) {
            throw VfsError::conflict($path, $expected, $given, 'Read it again and retry with the current version.');
        }
    }
}

<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\DataTableView;
use App\Models\User;

/**
 * Data table mount operations for OpenCompanyVfs.
 *
 * Tables expose schema, rows, and saved views as read-only files. Mutating table
 * records is handled by patch concerns so browse/read semantics stay isolated
 * from structured write rules.
 */
trait MountsVfsTables
{
    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listTables(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        return $this->pageTables($agent, $segments, $path, $budget, [])['entries'];
    }

    /**
     * Return one table-mount page with the table, row, or view identity kept in
     * the encrypted facade cursor through its path binding.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{entries: list<VfsEntry>, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageTables(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        if (count($segments) === 1) {
            return $this->paginateVfsLabelQuery(
                DataTable::forWorkspace(),
                'name',
                $budget->maxEntries,
                $state,
                fn (DataTable $table): VfsEntry => new VfsEntry($this->slug($table->name), '/tables/'.$this->slug($table->name), 'directory', "/tables/{$this->slug($table->name)}", 'data_table', $table->id, capabilities: ['browse', 'read', 'search']),
            );
        }

        $table = DataTable::forWorkspace()->lazy(100)->first(fn (DataTable $candidate): bool => $this->slug($candidate->name) === ($segments[1] ?? '')) ?? throw VfsError::notFound($path);

        if (count($segments) === 4 && $segments[2] === 'rows' && $segments[3] === 'by-id') {
            return $this->paginateVfsRecentQuery(
                DataTableRow::where('table_id', $table->id),
                $budget->maxEntries,
                $budget->maxFiles,
                $state,
                fn (DataTableRow $row): VfsEntry => $this->tableRowEntry($agent, $table, $row),
            );
        }

        if (count($segments) === 3 && $segments[2] === 'rows') {
            return $this->paginateVfsStaticEntries([
                new VfsEntry('by-id', "/tables/{$this->slug($table->name)}/rows/by-id", 'directory', "/tables/{$this->slug($table->name)}/rows/by-id", 'data_table', $table->id, capabilities: ['browse', 'read', 'search']),
            ], $budget->maxEntries, $state);
        }

        if (count($segments) === 3 && $segments[2] === 'views') {
            return $this->paginateVfsTimestampQuery(
                $table->views()->getQuery(),
                'created_at',
                'asc',
                $budget->maxEntries,
                $budget->maxFiles,
                $state,
                fn (DataTableView $view): VfsEntry => new VfsEntry(
                    $this->slug($view->name).'--'.substr($view->id, 0, 8).'.json',
                    "/tables/{$this->slug($table->name)}/views/{$this->slug($view->name)}--".substr($view->id, 0, 8).'.json',
                    'file',
                    backendType: 'data_table_view',
                    backendId: $view->id,
                    capabilities: ['read'],
                    metadata: [
                        'name' => $view->name,
                        'type' => $view->type,
                        ...$this->readOnlyMetadata(
                            'Table view JSON is a generated snapshot.',
                            'table view tools',
                            'Use table view tools to create or update saved views.',
                        ),
                    ],
                ),
            );
        }

        if (count($segments) > 2) {
            throw VfsError::notFound($path);
        }

        return $this->paginateVfsStaticEntries([
            new VfsEntry(
                'schema.json',
                "/tables/{$this->slug($table->name)}/schema.json",
                'file',
                backendType: 'data_table',
                backendId: $table->id,
                capabilities: ['read'],
                metadata: $this->readOnlyMetadata(
                    'Table schema is a generated snapshot.',
                    'table column tools',
                    'Use table column tools to change schema; use rows/by-id for row edits.',
                ),
            ),
            new VfsEntry(
                'rows.ndjson',
                "/tables/{$this->slug($table->name)}/rows.ndjson",
                'file',
                backendType: 'data_table',
                backendId: $table->id,
                capabilities: ['read', 'search'],
                metadata: [
                    'paged_collection' => true,
                    ...$this->readOnlyMetadata(
                        'rows.ndjson is a bulk export and cannot be patched safely.',
                        'vfs_patch',
                        'Use vfs_read with next_cursor to continue; patch individual rows under rows/by-id.',
                    ),
                ],
            ),
            new VfsEntry('rows', "/tables/{$this->slug($table->name)}/rows", 'directory', "/tables/{$this->slug($table->name)}/rows", 'data_table', $table->id, capabilities: ['browse', 'read', 'search']),
            new VfsEntry('views', "/tables/{$this->slug($table->name)}/views", 'directory', backendType: 'data_table', backendId: $table->id, capabilities: ['browse', 'read']),
        ], $budget->maxEntries, $state);
    }

    /**
     * @param  list<string>  $segments
     */
    private function readTables(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1 || count($segments) === 2) {
            return $this->entriesToText($this->listTables($agent, $segments, $path, $budget));
        }
        if (count($segments) > 5) {
            throw VfsError::notFound($path);
        }

        $table = DataTable::forWorkspace()->with('columns')->lazy(100)->first(fn (DataTable $candidate): bool => $this->slug($candidate->name) === ($segments[1] ?? '')) ?? throw VfsError::notFound($path);
        $leaf = $segments[2] ?? '';

        if ($leaf === 'schema.json') {
            if (isset($segments[3])) {
                throw VfsError::notFound($path);
            }

            return json_encode([
                'id' => $table->id,
                'name' => $table->name,
                'description' => $table->description,
                'columns' => $table->columns->map(fn ($column) => $column->toArray())->values()->all(),
            ], JSON_PRETTY_PRINT);
        }

        if ($leaf === 'rows.ndjson') {
            if (isset($segments[3])) {
                throw VfsError::notFound($path);
            }

            return $this->pageTableRowsExport($table, $path, $budget, [])['content'];
        }

        if ($leaf === 'rows') {
            if (($segments[3] ?? null) === 'by-id' && isset($segments[4])) {
                $rowId = preg_replace('/\.json$/', '', $segments[4]) ?? $segments[4];
                $row = DataTableRow::where('table_id', $table->id)->find($rowId) ?? throw VfsError::notFound($path);

                return json_encode($this->tableRowPayload($table, $row), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            if (($segments[3] ?? null) === 'by-id' || ! isset($segments[3])) {
                return $this->entriesToText($this->listTables($agent, $segments, $path, $budget));
            }

            throw VfsError::notFound($path);
        }

        if ($leaf === 'views') {
            if (! isset($segments[3])) {
                return $this->entriesToText($this->listTables($agent, $segments, $path, $budget));
            }

            $token = preg_replace('/\.json$/', '', $segments[3]) ?? $segments[3];
            $view = $table->views()
                ->get()
                ->first(fn (DataTableView $candidate): bool => $this->slug($candidate->name).'--'.substr($candidate->id, 0, 8) === $token)
                ?? throw VfsError::notFound($path);

            return json_encode([
                'id' => $view->id,
                'name' => $view->name,
                'type' => $view->type,
                'filters' => $view->filters,
                'sorts' => $view->sorts,
                'hiddenColumns' => $view->hidden_columns,
                'config' => $view->config,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        throw VfsError::notFound($path);
    }

    /**
     * Produce a valid NDJSON chunk in the same recent-first order as rows/by-id.
     * Each continuation re-applies the owning table predicate before its
     * timestamp/id keyset, preventing cursor state from crossing tables.
     *
     * @param  array<string, mixed>  $state
     * @return array{content: string, returned: int, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageTableRowsExport(DataTable $table, string $path, VfsBudget $budget, array $state): array
    {
        $usedBytes = 0;
        $page = $this->paginateVfsRecentQuery(
            DataTableRow::where('table_id', $table->id),
            $budget->maxEntries,
            $budget->maxFiles,
            $state,
            fn (DataTableRow $row): VfsEntry => new VfsEntry(
                name: (string) $row->id,
                path: "/tables/{$this->slug($table->name)}/rows/by-id/{$row->id}.json",
                type: 'file',
                backendType: 'data_table_row',
                backendId: $row->id,
                metadata: ['export_data' => $row->data],
            ),
            acceptEntry: function (array $entries, VfsEntry $candidate) use ($path, $budget, &$usedBytes): bool {
                $record = $this->tableRowsExportRecord($candidate);
                $additionalBytes = strlen($record) + ($entries === [] ? 0 : 1);
                if (! $this->vfsRecordFitsByteBudget(
                    $path,
                    $budget->maxBytes,
                    $usedBytes,
                    $additionalBytes,
                    count($entries),
                    $candidate->backendId,
                )) {
                    return false;
                }

                $usedBytes += $additionalBytes;

                return true;
            },
        );

        return [
            'content' => collect($page['entries'])->map($this->tableRowsExportRecord(...))->implode("\n"),
            'returned' => count($page['entries']),
            'has_more' => $page['has_more'],
            'next_state' => $page['next_state'],
            'scanned' => $page['scanned'],
            'skipped' => $page['skipped'],
        ];
    }

    /**
     * Resolve the workspace-owned table from an export path before applying
     * row cursor state.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{content: string, returned: int, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function readTableRowsExportPage(array $segments, string $path, VfsBudget $budget, array $state): array
    {
        if (count($segments) !== 3 || $segments[2] !== 'rows.ndjson') {
            throw VfsError::notFound($path);
        }

        $table = DataTable::forWorkspace()->lazy(100)
            ->first(fn (DataTable $candidate): bool => $this->slug($candidate->name) === $segments[1])
            ?? throw VfsError::notFound($path);

        return $this->pageTableRowsExport($table, $path, $budget, $state);
    }

    /**
     * Encode one complete NDJSON record. Invalid UTF-8 is substituted rather
     * than allowing encoding failure to advance a cursor without emitting the
     * corresponding row.
     */
    private function tableRowsExportRecord(VfsEntry $entry): string
    {
        return json_encode([
            'id' => $entry->backendId,
            'data' => $entry->metadata['export_data'] ?? [],
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    private function tableRowEntry(User $agent, DataTable $table, DataTableRow $row): VfsEntry
    {
        $path = "/tables/{$this->slug($table->name)}/rows/by-id/{$row->id}.json";
        $canPatch = $this->canPatchTableRows($agent);
        $capabilities = ['read', 'search'];
        if ($canPatch) {
            $capabilities[] = 'patch';
        }

        return new VfsEntry(
            "{$row->id}.json",
            $path,
            'file',
            $path,
            'data_table_row',
            $row->id,
            $this->tableRowVersion($row),
            $capabilities,
            [
                'table_id' => $table->id,
                'table_name' => $table->name,
                'updated_at' => $row->updated_at?->toIso8601String(),
                ...$this->mutationToolMetadata(
                    $agent,
                    ['vfs_patch', 'update_table_row'],
                    'Patch row data with vfs_patch using {"data": {...}}; keys must be existing column names or column UUIDs.',
                ),
            ],
        );
    }

    private function tableRowVersion(DataTableRow $row): string
    {
        return $this->version(
            $row->updated_at?->toIso8601String(),
            json_encode($row->data ?? [], JSON_UNESCAPED_SLASHES) ?: '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function tableRowPayload(DataTable $table, DataTableRow $row): array
    {
        $columns = $table->columns()->get();
        $dataByColumnId = $row->data ?? [];

        $namedData = $this->tableRowDataByColumnName($columns, $dataByColumnId);

        return [
            'id' => $row->id,
            'table_id' => $table->id,
            'version' => $this->tableRowVersion($row),
            'data' => $namedData['data'],
            'data_by_column_id' => $dataByColumnId,
            'ambiguous_column_names' => $namedData['ambiguous_column_names'],
            'columns' => $columns->map(fn (DataTableColumn $column): array => [
                'id' => $column->id,
                'name' => $column->name,
                'type' => $column->type,
                'required' => $column->required,
                'order' => $column->order,
            ])->values()->all(),
        ];
    }

    /**
     * @param  iterable<int, DataTableColumn>  $columns
     * @param  array<string, mixed>  $dataByColumnId
     * @return array{data: array<string, mixed>, ambiguous_column_names: list<string>}
     */
    private function tableRowDataByColumnName(iterable $columns, array $dataByColumnId): array
    {
        $named = [];
        $columnsByName = [];
        foreach ($columns as $column) {
            $columnsByName[mb_strtolower($column->name)][] = $column->name;
        }

        $ambiguous = collect($columnsByName)
            ->filter(fn (array $names): bool => count($names) > 1)
            ->map(fn (array $names): string => $names[0])
            ->values()
            ->all();
        $ambiguousLookup = array_flip(array_map(fn (string $name): string => mb_strtolower($name), $ambiguous));

        foreach ($columns as $column) {
            if (isset($ambiguousLookup[mb_strtolower($column->name)])) {
                continue;
            }
            if (array_key_exists($column->id, $dataByColumnId)) {
                $named[$column->name] = $dataByColumnId[$column->id];
            }
        }

        return [
            'data' => $named,
            'ambiguous_column_names' => $ambiguous,
        ];
    }
}

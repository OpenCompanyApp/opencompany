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
use Illuminate\Database\Eloquent\Model;

/**
 * Data table mount operations for OpenCompanyVfs.
 *
 * Addressing: every table is reachable as `/tables/by-id/{tableId}/…`
 * (canonical) and as `/tables/{slug}/…` (friendly; `{slug}--{id8}` when two
 * tables share a slug, see ResolvesVfsFriendlyNames). Under a table:
 *
 *   schema.json          columns with id, name, type, required, order, options
 *                        plus a `column_ids` name => id map
 *   rows.ndjson          paged; one line per row:
 *                        {"id","path","version","data":{Name: value},"data_by_column_id":{…}}
 *   rows.csv             paged read-only view of the same rows; header on page 1
 *   rows/by-id/{id}.json one row in the same name-keyed shape (+ columns)
 *   rows/{id}[.json]     alias of rows/by-id/{id}.json
 *   rows/new.json        write-only: vfs_write {"data":{…}} creates a row
 *   rows/.trash/         trashed rows; mv one out to restore it
 *   views/               saved views as JSON
 *
 * Row mutations live in MutatesVfsTableRows / PatchesVfsRecords; this trait
 * is read-side only.
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
        if (count($segments) === 1 || ($segments[1] === 'by-id' && ! isset($segments[2]))) {
            $byId = count($segments) === 2;

            return $this->paginateVfsLabelQuery(
                DataTable::forWorkspace(),
                'name',
                $budget->maxEntries,
                $state,
                function (DataTable $table) use ($byId): VfsEntry {
                    $token = $byId ? (string) $table->id : $this->tableToken($table);

                    return new VfsEntry(
                        $token,
                        $byId ? "/tables/by-id/{$table->id}" : "/tables/{$token}",
                        'directory',
                        "/tables/by-id/{$table->id}",
                        'data_table',
                        $table->id,
                        capabilities: ['browse', 'read', 'search'],
                        metadata: ['name' => $table->name, 'description' => $table->description],
                    );
                },
                $byId ? null : new VfsEntry('by-id', '/tables/by-id', 'directory', capabilities: ['browse']),
            );
        }

        ['table' => $table, 'rest' => $rest, 'base' => $base] = $this->resolveTableSegments($segments, $path);
        $canonical = "/tables/by-id/{$table->id}";

        if ($rest === []) {
            return $this->paginateVfsStaticEntries($this->tableDirectoryEntries($table, $base), $budget->maxEntries, $state);
        }

        if ($rest === ['rows']) {
            // rows/.trash is reachable by path but not listed, so recursive
            // walks (find, rg) never surface trashed rows.
            return $this->paginateVfsStaticEntries([
                new VfsEntry('by-id', "{$base}/rows/by-id", 'directory', "{$canonical}/rows/by-id", 'data_table', $table->id, capabilities: ['browse', 'read', 'search'], metadata: [
                    'description' => 'Rows by id; vfs_write ../new.json with {"data":{…}} creates one; rm trashes into rows/.trash.',
                ]),
            ], $budget->maxEntries, $state);
        }

        if ($rest === ['rows', 'by-id']) {
            $table->loadMissing('columns');

            return $this->paginateVfsRecentQuery(
                DataTableRow::where('table_id', $table->id),
                $budget->maxEntries,
                $budget->maxFiles,
                $state,
                fn (DataTableRow $row): VfsEntry => $this->tableRowEntry($agent, $table, $row, "{$base}/rows/by-id/{$row->id}.json"),
            );
        }

        if ($rest === ['rows', '.trash']) {
            return $this->paginateVfsStaticEntries($this->listTrashedTableRows($table, $base, $budget), $budget->maxEntries, $state);
        }

        if ($rest === ['views']) {
            return $this->paginateVfsTimestampQuery(
                $table->views()->getQuery(),
                'created_at',
                'asc',
                $budget->maxEntries,
                $budget->maxFiles,
                $state,
                fn (DataTableView $view): VfsEntry => new VfsEntry(
                    $this->tableViewToken($view).'.json',
                    "{$base}/views/".$this->tableViewToken($view).'.json',
                    'file',
                    "{$canonical}/views/".$this->tableViewToken($view).'.json',
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

        if (in_array($rest[0], ['schema.json', 'rows.ndjson', 'rows.csv'], true) && count($rest) === 1) {
            throw VfsError::notReadable($path);
        }

        throw $this->tableNotFound($path, $base);
    }

    /**
     * @return list<VfsEntry>
     */
    private function tableDirectoryEntries(DataTable $table, string $base): array
    {
        $canonical = "/tables/by-id/{$table->id}";

        return [
            new VfsEntry(
                'schema.json',
                "{$base}/schema.json",
                'file',
                "{$canonical}/schema.json",
                backendType: 'data_table',
                backendId: $table->id,
                capabilities: ['read'],
                metadata: $this->readOnlyMetadata(
                    'Table schema is a generated snapshot.',
                    'table column tools',
                    'Use table column tools to change schema; edit rows under rows/by-id.',
                ),
            ),
            new VfsEntry(
                'rows.ndjson',
                "{$base}/rows.ndjson",
                'file',
                "{$canonical}/rows.ndjson",
                backendType: 'data_table',
                backendId: $table->id,
                capabilities: ['read', 'search'],
                metadata: [
                    'paged_collection' => true,
                    ...$this->readOnlyMetadata(
                        'rows.ndjson is a bulk export and cannot be patched safely.',
                        'vfs_patch',
                        'Use vfs_read with next_cursor to continue; edit rows under rows/by-id; create with vfs_write rows/new.json.',
                    ),
                ],
            ),
            new VfsEntry(
                'rows.csv',
                "{$base}/rows.csv",
                'file',
                "{$canonical}/rows.csv",
                backendType: 'data_table',
                backendId: $table->id,
                capabilities: ['read'],
                metadata: [
                    'paged_collection' => true,
                    ...$this->readOnlyMetadata(
                        'rows.csv is a read-only view; the header row is on the first page only.',
                        'vfs_patch',
                        'Use vfs_read with next_cursor to continue; edit rows under rows/by-id.',
                    ),
                ],
            ),
            new VfsEntry('rows', "{$base}/rows", 'directory', "{$canonical}/rows", 'data_table', $table->id, capabilities: ['browse', 'read', 'search'], metadata: [
                'description' => 'Rows by id; vfs_write rows/new.json with {"data":{…}} creates one.',
            ]),
            new VfsEntry('views', "{$base}/views", 'directory', "{$canonical}/views", backendType: 'data_table', backendId: $table->id, capabilities: ['browse', 'read']),
        ];
    }

    /**
     * @param  list<string>  $segments
     */
    private function readTables(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1 || ($segments[1] === 'by-id' && ! isset($segments[2]))) {
            return $this->entriesToText($this->listTables($agent, $segments, $path, $budget));
        }

        ['table' => $table, 'rest' => $rest, 'base' => $base] = $this->resolveTableSegments($segments, $path);
        $table->loadMissing('columns');
        $leaf = $rest[0] ?? null;

        if ($leaf === null || $rest === ['rows'] || $rest === ['rows', 'by-id'] || $rest === ['rows', '.trash'] || $rest === ['views']) {
            if ($budget->isContentRead()) {
                throw VfsError::notReadable($path);
            }

            return $this->entriesToText($this->listTables($agent, $segments, $path, $budget));
        }

        if ($rest === ['schema.json']) {
            $budget->recordCanonicalPath("/tables/by-id/{$table->id}/schema.json");

            return json_encode($this->tableSchemaPayload($table), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($rest === ['rows.ndjson']) {
            return $this->pageTableRowsExport($table, $path, $budget, [])['content'];
        }

        if ($rest === ['rows.csv']) {
            return $this->pageTableRowsCsv($table, $path, $budget, [])['content'];
        }

        if ($leaf === 'rows') {
            if (($rest[1] ?? null) === '.trash' && count($rest) === 3) {
                $row = $this->resolveTrashedTableRow($table, $rest[2], $path);

                return json_encode($this->tableRowPayload($table, $row), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $row = $this->resolveTableRowFromRest($table, $rest, $path, $base);
            $budget->recordReadVersion($this->tableRowVersion($row));
            $budget->recordCanonicalPath($this->tableRowCanonicalPath($table, $row));

            return json_encode($this->tableRowPayload($table, $row), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($leaf === 'views' && count($rest) === 2) {
            $token = preg_replace('/\.json$/', '', $rest[1]) ?? $rest[1];
            $view = $table->views()
                ->get()
                ->first(fn (DataTableView $candidate): bool => $this->tableViewToken($candidate) === $token || $candidate->id === $token)
                ?? throw $this->tableNotFound($path, "{$base}/views");

            return json_encode([
                'id' => $view->id,
                'name' => $view->name,
                'type' => $view->type,
                'filters' => $view->filters,
                'sorts' => $view->sorts,
                'hiddenColumns' => $view->hidden_columns,
                'config' => $view->config,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        throw $this->tableNotFound($path, $base);
    }

    /**
     * Resolve the table a /tables path addresses and the segments below it.
     *
     * @param  list<string>  $segments
     * @return array{table: DataTable, rest: list<string>, base: string}
     *
     * @throws VfsError not_found (did_you_mean) / ambiguous_match
     */
    private function resolveTableSegments(array $segments, string $path): array
    {
        if (($segments[1] ?? null) === 'by-id') {
            $id = $segments[2] ?? throw VfsError::notFound($path);
            $table = ($this->vfsUuidOrNull($id) !== null ? DataTable::forWorkspace()->find($id) : null) ?? throw VfsError::notFound($path);

            return ['table' => $table, 'rest' => array_slice($segments, 3), 'base' => "/tables/by-id/{$table->id}"];
        }

        $table = $this->resolveFriendlySegment(
            DataTable::forWorkspace(),
            'name',
            (string) ($segments[1] ?? ''),
            '/tables',
            $path,
            fn (Model $candidate): string => "/tables/by-id/{$candidate->getKey()}",
        );
        if (! $table instanceof DataTable) {
            throw VfsError::notFound($path);
        }

        return ['table' => $table, 'rest' => array_slice($segments, 2), 'base' => '/tables/'.$segments[1]];
    }

    private function tableToken(DataTable $table): string
    {
        return $this->friendlyToken(DataTable::forWorkspace(), 'name', $table);
    }

    private function tableViewToken(DataTableView $view): string
    {
        return $this->slug($view->name).'--'.$this->idSuffix($view->id);
    }

    /**
     * Resolve `rows/by-id/{id}.json`, or the aliases `rows/{id}` and
     * `rows/{id}.json`, to a live row of $table.
     *
     * @param  list<string>  $rest
     */
    private function resolveTableRowFromRest(DataTable $table, array $rest, string $path, string $base): DataTableRow
    {
        $token = match (true) {
            count($rest) === 3 && $rest[1] === 'by-id' => $rest[2],
            count($rest) === 2 && ! in_array($rest[1], ['by-id', '.trash', 'new.json'], true) => $rest[1],
            default => throw $this->tableNotFound($path, $base),
        };
        $rowId = preg_replace('/\.json$/', '', $token) ?? $token;

        return ($this->vfsUuidOrNull($rowId) !== null ? DataTableRow::where('table_id', $table->id)->find($rowId) : null) ?? throw new VfsError('not_found', "Path not found: {$path}", [
            'path' => $path,
            'hint' => "List {$base}/rows/by-id to see row ids, or create one with vfs_write {$base}/rows/new.json.",
        ]);
    }

    private function tableNotFound(string $path, string $base): VfsError
    {
        return new VfsError('not_found', "Path not found: {$path}", [
            'path' => $path,
            'did_you_mean' => ["{$base}/schema.json", "{$base}/rows.ndjson", "{$base}/rows/by-id"],
            'hint' => 'Tables contain schema.json, rows.ndjson, rows.csv, rows/by-id/{id}.json and views/.',
        ]);
    }

    private function tableRowCanonicalPath(DataTable $table, DataTableRow $row): string
    {
        return "/tables/by-id/{$table->id}/rows/by-id/{$row->id}.json";
    }

    /**
     * @return array<string, mixed>
     */
    private function tableSchemaPayload(DataTable $table): array
    {
        $columns = $table->columns;

        return [
            'id' => $table->id,
            'name' => $table->name,
            'description' => $table->description,
            'canonical_path' => "/tables/by-id/{$table->id}",
            'columns' => $columns->map(fn (DataTableColumn $column): array => [
                'id' => $column->id,
                'name' => $column->name,
                'type' => $column->type,
                'required' => (bool) $column->required,
                'order' => $column->order,
                'options' => $column->options,
            ])->values()->all(),
            // Name-keyed rows use these names; data_by_column_id uses the ids.
            'column_ids' => $columns->mapWithKeys(fn (DataTableColumn $column): array => [$column->name => $column->id])->all(),
            'row_shape' => '{"id","path","version","data":{ColumnName: value},"data_by_column_id":{columnId: value}}',
        ];
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
        $table->loadMissing('columns');
        $page = $this->pageTableRowRecords($table, $path, $budget, $state, fn (DataTableRow $row): string => $this->tableRowsExportRecord($table, $row), terminated: true);

        // Every record ends with "\n" (POSIX text, `wc -l` = records) so
        // concatenated pages stay valid NDJSON; an empty page is "".
        return [...$page, 'content' => implode('', array_map(fn (string $record): string => $record."\n", $page['records']))];
    }

    /**
     * CSV view of the same pages as rows.ndjson. Page 1 starts with a header
     * row (`id` then column names in schema order); continuation pages carry
     * data rows only so concatenated pages form one valid CSV.
     *
     * @param  array<string, mixed>  $state
     * @return array{content: string, returned: int, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageTableRowsCsv(DataTable $table, string $path, VfsBudget $budget, array $state): array
    {
        $table->loadMissing('columns');
        $columns = $table->columns->values();
        $page = $this->pageTableRowRecords($table, $path, $budget, $state, function (DataTableRow $row) use ($columns): string {
            $data = $row->data ?? [];

            return $this->csvLine([(string) $row->id, ...$columns->map(fn (DataTableColumn $column): string => $this->csvCell($data[$column->id] ?? null))->all()]);
        });

        $lines = $page['records'];
        if (($state['after'] ?? null) === null) {
            array_unshift($lines, $this->csvLine(['id', ...$columns->map(fn (DataTableColumn $column): string => (string) $column->name)->all()]));
        }

        return [...$page, 'content' => implode("\n", $lines)];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  callable(DataTableRow): string  $render
     * @return array{records: list<string>, returned: int, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageTableRowRecords(DataTable $table, string $path, VfsBudget $budget, array $state, callable $render, bool $terminated = false): array
    {
        $usedBytes = 0;
        $rendered = [];
        $page = $this->paginateVfsRecentQuery(
            DataTableRow::where('table_id', $table->id),
            $budget->maxEntries,
            $budget->maxFiles,
            $state,
            fn (DataTableRow $row): VfsEntry => new VfsEntry(
                name: (string) $row->id,
                path: $this->tableRowCanonicalPath($table, $row),
                type: 'file',
                backendType: 'data_table_row',
                backendId: $row->id,
                metadata: ['record' => $render($row)],
            ),
            acceptEntry: function (array $entries, VfsEntry $candidate) use ($path, $budget, &$usedBytes, $terminated): bool {
                $record = (string) $candidate->metadata['record'];
                // Separator bytes: one newline between records, or after
                // every record when the format is newline-terminated.
                $additionalBytes = strlen($record) + ($terminated || $entries !== [] ? 1 : 0);
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

        foreach ($page['entries'] as $entry) {
            $rendered[] = (string) $entry->metadata['record'];
        }

        return [
            'records' => $rendered,
            'returned' => count($page['entries']),
            'has_more' => $page['has_more'],
            'next_state' => $page['next_state'],
            'scanned' => $page['scanned'],
            'skipped' => $page['skipped'],
        ];
    }

    /**
     * @param  list<string>  $cells
     */
    private function csvLine(array $cells): string
    {
        $handle = fopen('php://memory', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to render CSV.');
        }
        fputcsv($handle, $cells, ',', '"', '');
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return rtrim($line, "\n");
    }

    private function csvCell(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '',
        };
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
        ['table' => $table, 'rest' => $rest] = $this->resolveTableSegments($segments, $path);

        return match ($rest) {
            ['rows.ndjson'] => $this->pageTableRowsExport($table, $path, $budget, $state),
            ['rows.csv'] => $this->pageTableRowsCsv($table, $path, $budget, $state),
            default => throw VfsError::notFound($path),
        };
    }

    /**
     * Whether a /tables path is a paged row export (rows.ndjson or rows.csv).
     *
     * @param  list<string>  $segments
     */
    private function isTableRowsExportPath(array $segments): bool
    {
        if (($segments[0] ?? null) !== 'tables' || ! isset($segments[1])) {
            return false;
        }
        $rest = $segments[1] === 'by-id' ? array_slice($segments, 3) : array_slice($segments, 2);

        return $rest === ['rows.ndjson'] || $rest === ['rows.csv'];
    }

    /**
     * Encode one complete NDJSON record in the shared name-keyed row shape.
     * Invalid UTF-8 is substituted rather than allowing encoding failure to
     * advance a cursor without emitting the corresponding row.
     */
    private function tableRowsExportRecord(DataTable $table, DataTableRow $row): string
    {
        $data = $row->data ?? [];

        return json_encode([
            'id' => $row->id,
            'path' => $this->tableRowCanonicalPath($table, $row),
            'version' => $this->tableRowVersion($row),
            'data' => $this->tableRowDataByColumnName($table->columns, $data)['data'],
            'data_by_column_id' => (object) $data,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    private function tableRowEntry(User $agent, DataTable $table, DataTableRow $row, ?string $path = null): VfsEntry
    {
        $canonical = $this->tableRowCanonicalPath($table, $row);
        $path ??= $canonical;
        $canPatch = $this->canPatchTableRows($agent);
        $capabilities = ['read', 'search'];
        if ($canPatch) {
            $capabilities[] = 'patch';
        }

        return new VfsEntry(
            "{$row->id}.json",
            $path,
            'file',
            $canonical,
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
                    'Patch row data with vfs_patch using {"data": {...}} (optionally "base": {...}); keys are column names or ids. Overwrite the whole row with vfs_write and the row version.',
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
     * The one name-keyed row shape served by rows/by-id/{id}.json.
     *
     * @return array<string, mixed>
     */
    private function tableRowPayload(DataTable $table, DataTableRow $row): array
    {
        $table->loadMissing('columns');
        $columns = $table->columns;
        $dataByColumnId = $row->data ?? [];
        $namedData = $this->tableRowDataByColumnName($columns, $dataByColumnId);

        return [
            'id' => $row->id,
            'path' => $this->tableRowCanonicalPath($table, $row),
            'table_id' => $table->id,
            'version' => $this->tableRowVersion($row),
            'data' => (object) $namedData['data'],
            'data_by_column_id' => (object) $dataByColumnId,
            'ambiguous_column_names' => $namedData['ambiguous_column_names'],
            'columns' => $columns->map(fn (DataTableColumn $column): array => [
                'id' => $column->id,
                'name' => $column->name,
                'type' => $column->type,
                'required' => $column->required,
                'order' => $column->order,
            ])->values()->all(),
            ...($row->trashed() ? ['trashed' => true, 'deleted_at' => $row->deleted_at?->toIso8601String()] : []),
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

    /**
     * @return list<VfsEntry>
     */
    private function listTrashedTableRows(DataTable $table, string $base, VfsBudget $budget): array
    {
        return DataTableRow::onlyTrashed()
            ->where('table_id', $table->id)
            ->orderByDesc('deleted_at')
            ->orderBy('id')
            ->limit($budget->maxEntries)
            ->get()
            ->map(fn (DataTableRow $row): VfsEntry => new VfsEntry(
                "{$row->id}.json",
                "{$base}/rows/.trash/{$row->id}.json",
                'file',
                "/tables/by-id/{$table->id}/rows/.trash/{$row->id}.json",
                'data_table_row',
                $row->id,
                capabilities: ['read'],
                metadata: [
                    'trashed' => true,
                    'deleted_at' => $row->deleted_at?->toIso8601String(),
                    'deleted_by' => $row->getAttribute('deleted_by'),
                    'restore_hint' => "mv {$base}/rows/.trash/{$row->id}.json {$base}/rows/ restores it.",
                ],
            ))
            ->all();
    }

    private function resolveTrashedTableRow(DataTable $table, string $token, string $path): DataTableRow
    {
        $rowId = preg_replace('/\.json$/', '', $token) ?? $token;

        return ($this->vfsUuidOrNull($rowId) !== null ? DataTableRow::onlyTrashed()->where('table_id', $table->id)->find($rowId) : null) ?? throw VfsError::notFound($path);
    }
}

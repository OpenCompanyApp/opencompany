<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\Policy\VfsActionClass;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Table row lifecycle through the VFS: create, overwrite, trash and restore.
 * (Field-level patches stay in PatchesVfsRecords.)
 *
 * Paths (friendly `/tables/{slug}` or canonical `/tables/by-id/{id}`):
 *   vfs_write  {table}/rows/new.json            {"data":{Column: value}}   create
 *   vfs_write  {table}/rows/by-id/{id}.json     {"data":{…}} + version     overwrite
 *   rm         {table}/rows/by-id/{id}.json                                trash
 *   mv         {table}/rows/.trash/{id}.json  {table}/rows/                restore
 *
 * Overwrite replaces the row's data with exactly the supplied columns. The
 * payload may be the object read from rows/by-id (its `data_by_column_id` is
 * used as the base so columns hidden from `data` by ambiguous names survive
 * the round trip); `data` keys win.
 *
 * Governance: create = vfs_write + add_table_row, overwrite = vfs_write +
 * update_table_row (reversible_edit); trash = delete_table_row (delete);
 * restore = delete_table_row. The vfs_write primitive for rm/mv is enforced by
 * the calling surface. Every committed change records a DataTableRowVersion
 * snapshot attributed to the agent and run.
 */
trait MutatesVfsTableRows
{
    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function writeTablePath(User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters): array
    {
        ['table' => $table, 'rest' => $rest, 'base' => $base] = $this->resolveTableSegments($segments, $path);
        $table->loadMissing('columns');

        if ($rest === ['rows', 'new.json']) {
            if ($version !== null) {
                throw VfsError::invalid('rows/new.json always creates a new row; omit version.', ['path' => $path]);
            }

            return $this->createTableRow($agent, $table, $path, $this->decodeTableRowPayload($content, $path), $toolParameters);
        }

        try {
            $row = $this->resolveTableRowFromRest($table, $rest, $path, $base);
        } catch (VfsError $e) {
            throw new VfsError('not_writable', "Path is not writable through VFS: {$path}", [
                'path' => $path,
                'hint' => "Create rows with vfs_write {$base}/rows/new.json and overwrite existing rows at {$base}/rows/by-id/{id}.json. schema.json, rows.ndjson and rows.csv are read-only.",
            ]);
        }

        if ($mode === 'create') {
            throw VfsError::invalid("Path already exists: {$path}", [
                'path' => $path,
                'hint' => 'Use mode "overwrite" with the row version, or vfs_patch {"data":{…}} to change some columns.',
            ]);
        }

        return $this->overwriteTableRow($agent, $table, $row, $path, $this->decodeTableRowPayload($content, $path), $version, $toolParameters);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeTableRowPayload(string $content, string $path): array
    {
        $decoded = json_decode($content, true);
        if (! is_array($decoded) || array_is_list($decoded) && $decoded !== [] || ! is_array($decoded['data'] ?? null)) {
            throw VfsError::invalid('Row content must be a JSON object {"data": {"Column name": value, …}}.', [
                'path' => $path,
                'hint' => 'Keys are column names (or column ids) from schema.json.',
            ]);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function createTableRow(User $agent, DataTable $table, string $path, array $payload, array $toolParameters): array
    {
        $data = $this->resolveTableRowPatchData($table, $payload['data']);
        $this->validateTableRowValues($table, $data, requireRequired: true);

        $this->assertMutationToolsAllowed($agent, $path, ['vfs_write', 'add_table_row'], $toolParameters, $this->channelIdFrom($toolParameters), [
            'kind' => 'table_row',
            'action_class' => 'reversible_edit',
            'mode' => 'create',
            'table' => $table->name,
            'fields' => $this->tableColumnNames($table, array_keys($data)),
        ], 'write', 'Creating table rows requires vfs_write and add_table_row.', actionClass: VfsActionClass::REVERSIBLE_EDIT);

        $row = DB::transaction(function () use ($agent, $table, $data): DataTableRow {
            $row = DataTableRow::create(['table_id' => $table->id, 'data' => $data, 'created_by' => $agent->id]);
            $fresh = $row->fresh() ?? $row;
            $this->snapshotTableRowVersion($table, $fresh, [], $agent, null, $this->tableRowVersion($fresh), 'VFS create');

            return $fresh;
        });

        return $this->tableRowWriteResult('created', $table, $row);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function overwriteTableRow(User $agent, DataTable $table, DataTableRow $row, string $path, array $payload, ?string $version, array $toolParameters): array
    {
        if ($version === null) {
            throw VfsError::invalid("Overwriting {$path} requires the version you read.", [
                'path' => $path,
                'field' => 'version',
                'hint' => 'Read the row first (its JSON includes "version"), or use vfs_patch {"data":{…}} to change only some columns.',
            ]);
        }
        $this->assertVersion($version, $this->tableRowVersion($row), $path);

        $knownIds = $table->columns->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $base = is_array($payload['data_by_column_id'] ?? null)
            ? array_intersect_key($payload['data_by_column_id'], array_flip($knownIds))
            : [];
        $data = [...$base, ...$this->resolveTableRowPatchData($table, $payload['data'])];
        $this->validateTableRowValues($table, $data, requireRequired: true);

        $this->assertMutationToolsAllowed($agent, $path, ['vfs_write', 'update_table_row'], $toolParameters, $this->channelIdFrom($toolParameters), [
            'kind' => 'table_row',
            'action_class' => 'reversible_edit',
            'mode' => 'overwrite',
            'table' => $table->name,
            'row_id' => $row->id,
            'fields' => $this->tableColumnNames($table, array_keys($data)),
        ], 'write', 'Overwriting table rows requires vfs_write and update_table_row.', actionClass: VfsActionClass::REVERSIBLE_EDIT);

        $fresh = DB::transaction(function () use ($agent, $table, $row, $data, $version, $path): DataTableRow {
            $locked = DataTableRow::where('table_id', $table->id)->whereKey($row->id)->lockForUpdate()->first() ?? throw VfsError::notFound($path);
            $before = $this->tableRowVersion($locked);
            $this->assertVersion($version, $before, $path);
            $previous = $locked->data ?? [];
            if ($previous == $data) {
                return $locked;
            }

            $locked->update(['data' => $data]);
            $fresh = $locked->fresh() ?? $locked;
            $this->snapshotTableRowVersion($table, $fresh, $previous, $agent, $before, $this->tableRowVersion($fresh), 'VFS overwrite');

            return $fresh;
        });

        return $this->tableRowWriteResult('updated', $table, $fresh);
    }

    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function trashTableRowPath(User $agent, array $segments, string $path, array $toolParameters): array
    {
        ['table' => $table, 'rest' => $rest, 'base' => $base] = $this->resolveTableSegments($segments, $path);
        if ($rest === [] || ! in_array($rest[0], ['rows'], true) || in_array($rest, [['rows'], ['rows', 'by-id'], ['rows', '.trash']], true)) {
            throw new VfsError('not_writable', "Path is not writable through VFS: {$path}", [
                'path' => $path,
                'hint' => "Only rows can be removed through the VFS: rm {$base}/rows/by-id/{id}.json. Use table tools to delete tables or columns.",
            ]);
        }
        $row = $this->resolveTableRowFromRest($table, $rest, $path, $base);

        $this->mutationPolicy->assertAllowedOrRequestApproval(
            agent: $agent,
            directToolSlug: 'vfs_rm',
            operation: 'delete',
            subject: $path,
            requiredTools: ['delete_table_row'],
            parameters: $toolParameters,
            channelId: $this->channelIdFrom($toolParameters),
            hint: 'Moving a row to the trash requires delete_table_row.',
            preview: ['kind' => 'table_row', 'action_class' => 'delete', 'table' => $table->name, 'row_id' => $row->id],
            actionClass: VfsActionClass::DELETE,
        );

        DB::transaction(function () use ($agent, $table, $row, $path): void {
            $locked = DataTableRow::where('table_id', $table->id)->whereKey($row->id)->lockForUpdate()->first() ?? throw VfsError::notFound($path);
            $locked->forceFill(['deleted_by' => $agent->id])->save();
            $locked->delete();
        });

        return [
            'status' => 'trashed',
            'path' => $path,
            'canonical_path' => "/tables/by-id/{$table->id}/rows/.trash/{$row->id}.json",
            'restore_hint' => "mv /tables/by-id/{$table->id}/rows/.trash/{$row->id}.json /tables/by-id/{$table->id}/rows/ restores it.",
        ];
    }

    /**
     * Restore a trashed row. Rows always return to their own table; the
     * destination must name that table's rows directory (or the row path).
     *
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function restoreTableRowPath(User $agent, string $source, string $destination, array $toolParameters): array
    {
        $sourceSegments = $this->segments($source);
        ['table' => $table, 'rest' => $rest] = $this->resolveTableSegments($sourceSegments, $source);
        if (count($rest) !== 3 || $rest[0] !== 'rows' || $rest[1] !== '.trash') {
            throw VfsError::unsupported('Rows cannot be moved; only trashed rows can be restored with mv.', ['source' => $source]);
        }
        $row = $this->resolveTrashedTableRow($table, $rest[2], $source);

        ['table' => $destinationTable, 'rest' => $destinationRest] = $this->resolveTableSegments($this->segments($destination), $destination);
        if ($destinationTable->id !== $table->id || ($destinationRest[0] ?? null) !== 'rows' || ($destinationRest[1] ?? null) === '.trash') {
            throw VfsError::invalid('A trashed row can only be restored into its own table: mv it to that table\'s rows/ directory.', [
                'source' => $source,
                'destination' => $destination,
                'hint' => "mv {$source} /tables/by-id/{$table->id}/rows/",
            ]);
        }

        $this->mutationPolicy->assertAllowedOrRequestApproval(
            agent: $agent,
            directToolSlug: 'vfs_mv',
            operation: 'restore',
            subject: $source,
            requiredTools: ['delete_table_row'],
            parameters: $toolParameters,
            channelId: $this->channelIdFrom($toolParameters),
            hint: 'Restoring a trashed row requires delete_table_row.',
            preview: ['kind' => 'table_row', 'action_class' => 'reversible_edit', 'table' => $table->name, 'row_id' => $row->id],
            actionClass: VfsActionClass::REVERSIBLE_EDIT,
        );

        $fresh = DB::transaction(function () use ($table, $row, $source): DataTableRow {
            $locked = DataTableRow::onlyTrashed()->where('table_id', $table->id)->whereKey($row->id)->lockForUpdate()->first() ?? throw VfsError::notFound($source);
            $locked->forceFill(['deleted_by' => null]);
            $locked->restore();

            return $locked->fresh() ?? $locked;
        });

        return $this->tableRowWriteResult('restored', $table, $fresh);
    }

    /**
     * Basic per-type validation, shared by create, overwrite and patch.
     * Empty values ('' or null) clear a cell; required columns must not be
     * empty when $requireRequired (whole-row writes).
     *
     * @param  array<string, mixed>  $dataByColumnId
     *
     * @throws VfsError invalid_request {errors: {column name: problem}}
     */
    private function validateTableRowValues(DataTable $table, array $dataByColumnId, bool $requireRequired = false): void
    {
        $errors = [];
        foreach ($table->columns as $column) {
            $present = array_key_exists($column->id, $dataByColumnId);
            $value = $dataByColumnId[$column->id] ?? null;
            $empty = $value === null || $value === '' || $value === [];

            if ($empty) {
                if ($column->required && ($present || $requireRequired)) {
                    $errors[$column->name] = 'is required';
                }

                continue;
            }

            $problem = $this->tableCellProblem($column, $value);
            if ($problem !== null) {
                $errors[$column->name] = $problem;
            }
        }

        if ($errors !== []) {
            throw VfsError::invalid('Row values do not match the table schema: '.implode('; ', array_map(
                fn (string $name, string $problem): string => "{$name} {$problem}",
                array_keys($errors),
                $errors,
            )).'.', ['errors' => $errors, 'hint' => 'Check column types in schema.json.']);
        }
    }

    private function tableCellProblem(DataTableColumn $column, mixed $value): ?string
    {
        $choices = is_array($column->options['choices'] ?? null) ? array_values(array_filter($column->options['choices'], 'is_string')) : [];

        return match ($column->type) {
            'number' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? null : 'must be a number',
            'checkbox' => is_bool($value) ? null : 'must be true or false',
            'date' => is_string($value) && strtotime($value) !== false ? null : 'must be a date (YYYY-MM-DD)',
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false ? null : 'must be a URL',
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? null : 'must be an email address',
            'select' => ! is_scalar($value)
                ? 'must be one choice'
                : ($choices !== [] && ! in_array((string) $value, $choices, true) ? 'must be one of: '.implode(', ', $choices) : null),
            'multiselect' => ! is_array($value) || ! array_is_list($value)
                ? 'must be a list of choices'
                : ($choices !== [] && array_diff(array_map('strval', $value), $choices) !== [] ? 'must only contain: '.implode(', ', $choices) : null),
            'text' => is_scalar($value) ? null : 'must be text',
            default => null,
        };
    }

    /**
     * @param  list<string|int>  $columnIds
     * @return list<string>
     */
    private function tableColumnNames(DataTable $table, array $columnIds): array
    {
        $names = $table->columns->keyBy('id');

        return array_map(fn (string|int $id): string => (string) ($names->get($id)->name ?? $id), $columnIds);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableRowWriteResult(string $status, DataTable $table, DataTableRow $row): array
    {
        $canonical = $this->tableRowCanonicalPath($table, $row);

        return [
            'status' => $status,
            'id' => $row->id,
            'path' => $canonical,
            'canonical_path' => $canonical,
            'version' => $this->tableRowVersion($row),
        ];
    }
}

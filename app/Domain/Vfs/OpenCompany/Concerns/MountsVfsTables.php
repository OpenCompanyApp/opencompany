<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\DataTable;
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
     * @return list<VfsEntry>
     */
    private function listTables(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        if (count($segments) === 1) {
            return DataTable::forWorkspace()
                ->orderBy('name')
                ->limit($budget->maxEntries)
                ->get()
                ->map(fn (DataTable $table): VfsEntry => new VfsEntry($this->slug($table->name), '/tables/'.$this->slug($table->name), 'directory', "/tables/{$this->slug($table->name)}", 'data_table', $table->id, capabilities: ['browse', 'read', 'search']))
                ->all();
        }

        $table = DataTable::forWorkspace()->get()->first(fn (DataTable $candidate): bool => $this->slug($candidate->name) === ($segments[1] ?? '')) ?? throw VfsError::notFound($path);

        if (count($segments) === 3 && ($segments[2] ?? null) === 'views') {
            return $table->views()
                ->orderBy('created_at')
                ->limit($budget->maxEntries)
                ->get()
                ->map(fn (DataTableView $view): VfsEntry => new VfsEntry(
                    $this->slug($view->name).'--'.substr($view->id, 0, 8).'.json',
                    "/tables/{$this->slug($table->name)}/views/{$this->slug($view->name)}--".substr($view->id, 0, 8).'.json',
                    'file',
                    backendType: 'data_table_view',
                    backendId: $view->id,
                    capabilities: ['read'],
                    metadata: ['name' => $view->name, 'type' => $view->type],
                ))
                ->all();
        }

        if (count($segments) > 2) {
            throw VfsError::notFound($path);
        }

        return [
            new VfsEntry('schema.json', "/tables/{$this->slug($table->name)}/schema.json", 'file', backendType: 'data_table', backendId: $table->id, capabilities: ['read']),
            new VfsEntry('rows.ndjson', "/tables/{$this->slug($table->name)}/rows.ndjson", 'file', backendType: 'data_table', backendId: $table->id, capabilities: ['read', 'search']),
            new VfsEntry('views', "/tables/{$this->slug($table->name)}/views", 'directory', backendType: 'data_table', backendId: $table->id, capabilities: ['browse', 'read']),
        ];
    }

    private function readTables(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1 || count($segments) === 2) {
            return $this->entriesToText($this->listTables($agent, $segments, $path, $budget));
        }
        if (count($segments) > 4) {
            throw VfsError::notFound($path);
        }

        $table = DataTable::forWorkspace()->with('columns')->get()->first(fn (DataTable $candidate): bool => $this->slug($candidate->name) === ($segments[1] ?? '')) ?? throw VfsError::notFound($path);
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

            return DataTableRow::where('table_id', $table->id)
                ->limit($budget->maxEntries)
                ->get()
                ->map(fn (DataTableRow $row): string => json_encode(['id' => $row->id, 'data' => $row->data], JSON_UNESCAPED_SLASHES))
                ->implode("\n");
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
}

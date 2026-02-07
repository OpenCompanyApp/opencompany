<?php

namespace App\Agents\Tools;

use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class QueryTable implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Query data tables in the workspace. List tables, view table structure, retrieve rows, or search row data.';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];

            return match ($action) {
                'list_tables' => $this->listTables(),
                'get_table' => $this->getTable($request),
                'get_rows' => $this->getRows($request),
                'search_rows' => $this->searchRows($request),
                default => "Error: Unknown action '{$action}'. Use 'list_tables', 'get_table', 'get_rows', or 'search_rows'.",
            };
        } catch (\Throwable $e) {
            return "Error querying table: {$e->getMessage()}";
        }
    }

    private function listTables(): string
    {
        $tables = DataTable::with('creator')
            ->withCount(['rows as rowsCount', 'columns as columnsCount'])
            ->get();

        if ($tables->isEmpty()) {
            return "No data tables found in the workspace.";
        }

        $lines = ["Data tables ({$tables->count()}):"];
        foreach ($tables as $table) {
            $creator = $table->creator?->name ?? 'Unknown';
            $desc = $table->description ? " - {$table->description}" : '';
            $lines[] = "- {$table->name}{$desc} ({$table->columnsCount} columns, {$table->rowsCount} rows, created by {$creator})";
            $lines[] = "  ID: {$table->id}";
        }

        return implode("\n", $lines);
    }

    private function getTable(Request $request): string
    {
        $tableId = $request['tableId'] ?? null;
        if (!$tableId) {
            return "Error: 'tableId' is required for the 'get_table' action.";
        }

        $table = DataTable::with(['columns', 'creator'])->find($tableId);
        if (!$table) {
            return "Error: Table '{$tableId}' not found.";
        }

        $creator = $table->creator?->name ?? 'Unknown';
        $lines = [
            "Table: {$table->name}",
            "Description: " . ($table->description ?? 'None'),
            "Created by: {$creator}",
            "Columns ({$table->columns->count()}):",
        ];

        foreach ($table->columns as $column) {
            $required = $column->required ? ' (required)' : '';
            $lines[] = "- {$column->name} [{$column->type}]{$required}";
        }

        return implode("\n", $lines);
    }

    private function getRows(Request $request): string
    {
        $tableId = $request['tableId'] ?? null;
        if (!$tableId) {
            return "Error: 'tableId' is required for the 'get_rows' action.";
        }

        $limit = $request['limit'] ?? 25;

        $table = DataTable::find($tableId);
        if (!$table) {
            return "Error: Table '{$tableId}' not found.";
        }

        $rows = DataTableRow::where('table_id', $tableId)
            ->take(min($limit, 100))
            ->get();

        if ($rows->isEmpty()) {
            return "No rows found in table '{$table->name}'.";
        }

        $lines = ["Rows from '{$table->name}' ({$rows->count()}):"];
        foreach ($rows as $index => $row) {
            $lines[] = "Row " . ($index + 1) . " (ID: {$row->id}):";
            if (is_array($row->data)) {
                foreach ($row->data as $key => $value) {
                    $displayValue = is_array($value) ? json_encode($value) : $value;
                    $lines[] = "  {$key}: {$displayValue}";
                }
            }
        }

        return implode("\n", $lines);
    }

    private function searchRows(Request $request): string
    {
        $tableId = $request['tableId'] ?? null;
        if (!$tableId) {
            return "Error: 'tableId' is required for the 'search_rows' action.";
        }

        $search = $request['search'] ?? null;
        if (!$search) {
            return "Error: 'search' is required for the 'search_rows' action.";
        }

        $limit = $request['limit'] ?? 25;

        $table = DataTable::find($tableId);
        if (!$table) {
            return "Error: Table '{$tableId}' not found.";
        }

        $rows = DataTableRow::where('table_id', $tableId)
            ->whereRaw("CAST(data AS TEXT) LIKE ?", ['%' . $search . '%'])
            ->take(min($limit, 100))
            ->get();

        if ($rows->isEmpty()) {
            return "No rows matching '{$search}' found in table '{$table->name}'.";
        }

        $lines = ["Search results for '{$search}' in '{$table->name}' ({$rows->count()}):"];
        foreach ($rows as $index => $row) {
            $lines[] = "Row " . ($index + 1) . " (ID: {$row->id}):";
            if (is_array($row->data)) {
                foreach ($row->data as $key => $value) {
                    $displayValue = is_array($value) ? json_encode($value) : $value;
                    $lines[] = "  {$key}: {$displayValue}";
                }
            }
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("The query action: 'list_tables', 'get_table', 'get_rows', or 'search_rows'.")
                ->required(),
            'tableId' => $schema
                ->string()
                ->description('The UUID of the table. Required for get_table, get_rows, and search_rows.'),
            'search' => $schema
                ->string()
                ->description('Search term to filter rows by. Required for search_rows.'),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of rows to return (default: 25, max: 100).'),
        ];
    }
}

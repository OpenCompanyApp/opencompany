<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetTableRows implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Retrieve rows from a data table with pagination support.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            "tableId" => $schema
                ->string()
                ->description("The UUID of the table.")
                ->required(),
            "limit" => $schema
                ->integer()
                ->description("Maximum number of rows to return (default: 25, max: 100)."),
            "offset" => $schema
                ->integer()
                ->description("Number of rows to skip (default: 0). Use with limit for pagination."),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $tableId = $request["tableId"] ?? null;
            if (!$tableId) {
                return "Error: tableId is required.";
            }

            $limit = min($request["limit"] ?? 25, 100);
            $offset = $request["offset"] ?? 0;

            $table = DataTable::forWorkspace()->find($tableId);
            if (!$table) {
                return "Error: Table {$tableId} not found.";
            }

            // Build column UUID → human-readable name mapping
            $columnMap = $table->columns()->pluck('name', 'id')->toArray();

            $rows = DataTableRow::where("table_id", $tableId)
                ->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            // Return flat array with data keys resolved to column names
            // so Lua can use #result for count and row.FieldName for access
            return json_encode($rows->map(function ($row) use ($columnMap) {
                $mapped = ['_id' => $row->id];
                foreach ($row->data ?? [] as $colId => $value) {
                    $key = $columnMap[$colId] ?? $colId;
                    $mapped[$key] = $value;
                }
                return $mapped;
            })->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error querying table: {$e->getMessage()}";
        }
    }
}
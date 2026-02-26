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

            $total = DataTableRow::where("table_id", $tableId)->count();

            $rows = DataTableRow::where("table_id", $tableId)
                ->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            return json_encode([
                'total' => $total,
                'offset' => $offset,
                'rows' => $rows->map(fn ($row) => [
                    'id' => $row->id,
                    'data' => $row->data ?? [],
                ])->values()->toArray(),
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error querying table: {$e->getMessage()}";
        }
    }
}
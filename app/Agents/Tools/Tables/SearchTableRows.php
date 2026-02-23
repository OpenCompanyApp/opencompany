<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchTableRows implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Search rows in a data table by matching a search term against row data.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            "tableId" => $schema
                ->string()
                ->description("The UUID of the table.")
                ->required(),
            "search" => $schema
                ->string()
                ->description("Search term to filter rows by.")
                ->required(),
            "limit" => $schema
                ->integer()
                ->description("Maximum number of rows to return (default: 25, max: 100)."),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $tableId = $request["tableId"] ?? null;
            if (!$tableId) {
                return "Error: tableId is required.";
            }

            $search = $request["search"] ?? null;
            if (!$search) {
                return "Error: search is required.";
            }

            $limit = $request["limit"] ?? 25;

            $table = DataTable::forWorkspace()->find($tableId);
            if (!$table) {
                return "Error: Table {$tableId} not found.";
            }

            $rows = DataTableRow::where("table_id", $tableId)
                ->whereRaw("CAST(data AS TEXT) LIKE ?", ["%" . $search . "%"])
                ->take(min($limit, 100))
                ->get();

            if ($rows->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($rows->map(fn ($row) => [
                'id' => $row->id,
                'data' => $row->data ?? [],
            ])->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error querying table: {$e->getMessage()}";
        }
    }
}
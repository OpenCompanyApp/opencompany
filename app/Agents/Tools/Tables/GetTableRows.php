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
        return "Retrieve rows from a data table, with an optional limit.";
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
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $tableId = $request["tableId"] ?? null;
            if (!$tableId) {
                return "Error: tableId is required.";
            }

            $limit = $request["limit"] ?? 25;

            $table = DataTable::forWorkspace()->find($tableId);
            if (!$table) {
                return "Error: Table {$tableId} not found.";
            }

            $rows = DataTableRow::where("table_id", $tableId)
                ->take(min($limit, 100))
                ->get();

            if ($rows->isEmpty()) {
                return "No rows found in table {$table->name}.";
            }

            $lines = ["Rows from {$table->name} ({$rows->count()}):"];
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
        } catch (\Throwable $e) {
            return "Error querying table: {$e->getMessage()}";
        }
    }
}
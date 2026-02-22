<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteTableRow implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Delete a row from a data table.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            "rowId" => $schema
                ->string()
                ->description("The UUID of the row to delete.")
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $row = DataTableRow::findOrFail($request["rowId"]);
            DataTable::forWorkspace()->findOrFail($row->table_id); // verify workspace
            $row->delete();

            return "Row deleted.";
        } catch (\Throwable $e) {
            return "Error managing table rows: {$e->getMessage()}";
        }
    }
}
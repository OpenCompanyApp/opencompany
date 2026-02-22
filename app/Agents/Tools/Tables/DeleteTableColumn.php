<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteTableColumn implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Delete a column from a data table.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            "columnId" => $schema
                ->string()
                ->description("The UUID of the column to delete.")
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $column = DataTableColumn::findOrFail($request["columnId"]);
            DataTable::forWorkspace()->findOrFail($column->table_id); // verify workspace
            $column->delete();

            return "Column deleted.";
        } catch (\Throwable $e) {
            return "Error managing table: {$e->getMessage()}";
        }
    }
}
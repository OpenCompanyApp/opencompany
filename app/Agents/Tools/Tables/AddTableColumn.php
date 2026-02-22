<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AddTableColumn implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Add a new column to an existing data table.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            "tableId" => $schema
                ->string()
                ->description("The UUID of the table.")
                ->required(),
            "name" => $schema
                ->string()
                ->description("The name of the column.")
                ->required(),
            "columnType" => $schema
                ->string()
                ->description("The column type: text, number, date, select, multiselect, checkbox, url, email, user, or attachment."),
            "columnOptions" => $schema
                ->string()
                ->description("JSON string of column options (e.g. select choices)."),
            "required" => $schema
                ->boolean()
                ->description("Whether the column is required."),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $table = DataTable::forWorkspace()->findOrFail($request["tableId"]);

            $maxOrder = $table->columns()->max("order") ?? -1;

            $column = DataTableColumn::create([
                "table_id" => $table->id,
                "name" => $request["name"],
                "type" => $request["columnType"] ?? "text",
                "options" => isset($request["columnOptions"]) ? json_decode($request["columnOptions"], true) : null,
                "order" => $maxOrder + 1,
                "required" => $request["required"] ?? false,
            ]);

            return "Column {$column->name} added.";
        } catch (\Throwable $e) {
            return "Error managing table: {$e->getMessage()}";
        }
    }
}
<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateTableColumn implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Update an existing column name, type, options, or required status.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            "columnId" => $schema
                ->string()
                ->description("The UUID of the column.")
                ->required(),
            "name" => $schema
                ->string()
                ->description("The new name of the column."),
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
            $column = DataTableColumn::findOrFail($request["columnId"]);
            DataTable::forWorkspace()->findOrFail($column->table_id); // verify workspace

            if (isset($request["name"])) {
                $column->name = $request["name"];
            }

            if (isset($request["columnType"])) {
                $column->type = $request["columnType"];
            }

            if (isset($request["columnOptions"])) {
                $column->options = json_decode($request["columnOptions"], true);
            }

            if (isset($request["required"])) {
                $column->required = $request["required"];
            }

            $column->save();

            return "Column updated.";
        } catch (\Throwable $e) {
            return "Error managing table: {$e->getMessage()}";
        }
    }
}
<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateTable implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Update an existing data table name or description.";
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
                ->description("The new name of the table."),
            "description" => $schema
                ->string()
                ->description("The new description for the table."),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $table = DataTable::forWorkspace()->findOrFail($request["tableId"]);

            if (isset($request["name"])) {
                $table->name = $request["name"];
            }

            if (isset($request["description"])) {
                $table->description = $request["description"];
            }

            $table->save();

            return "Table updated.";
        } catch (\Throwable $e) {
            return "Error managing table: {$e->getMessage()}";
        }
    }
}
<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateTable implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Create a new data table in the workspace.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            "name" => $schema
                ->string()
                ->description("The name of the table.")
                ->required(),
            "description" => $schema
                ->string()
                ->description("A description for the table."),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $table = DataTable::create([
                "name" => $request["name"],
                "description" => $request["description"] ?? null,
                "created_by" => $this->agent->id,
                "workspace_id" => $this->agent->workspace_id ?? workspace()->id,
            ]);

            return "Table created: {$table->name} (ID: {$table->id})";
        } catch (\Throwable $e) {
            return "Error managing table: {$e->getMessage()}";
        }
    }
}
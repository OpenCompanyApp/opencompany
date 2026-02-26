<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableColumn;
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
        return "Create a new data table in the workspace, optionally with initial columns.";
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
            "icon" => $schema
                ->string()
                ->description("Icon identifier for the table."),
            "columns" => $schema
                ->array()
                ->description("Initial columns to create. Array of objects with: name (required), type (text|number|date|select|multiselect|checkbox|url|email|user|attachment), options (for select types), required (boolean)."),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $table = DataTable::create([
                "name" => $request["name"],
                "description" => $request["description"] ?? null,
                "icon" => $request["icon"] ?? null,
                "created_by" => $this->agent->id,
                "workspace_id" => $this->agent->workspace_id ?? workspace()->id,
            ]);

            $columns = $request["columns"] ?? [];
            foreach ($columns as $i => $col) {
                DataTableColumn::create([
                    'table_id' => $table->id,
                    'name' => $col['name'],
                    'type' => $col['type'] ?? 'text',
                    'options' => $col['options'] ?? null,
                    'required' => $col['required'] ?? false,
                    'order' => $i,
                ]);
            }

            return "Table created: {$table->name} (ID: {$table->id})";
        } catch (\Throwable $e) {
            return "Error managing table: {$e->getMessage()}";
        }
    }
}
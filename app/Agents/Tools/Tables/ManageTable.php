<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ManageTable implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create, update, or delete data tables and their columns. Use this to manage structured data in the workspace.';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];

            return match ($action) {
                'create_table' => $this->createTable($request),
                'update_table' => $this->updateTable($request),
                'delete_table' => $this->deleteTable($request),
                'add_column' => $this->addColumn($request),
                'update_column' => $this->updateColumn($request),
                'delete_column' => $this->deleteColumn($request),
                default => "Unknown action: {$action}. Use 'create_table', 'update_table', 'delete_table', 'add_column', 'update_column', or 'delete_column'.",
            };
        } catch (\Throwable $e) {
            return "Error managing table: {$e->getMessage()}";
        }
    }

    private function createTable(Request $request): string
    {
        $table = DataTable::create([
            'name' => $request['name'],
            'description' => $request['description'] ?? null,
            'created_by' => $this->agent->id,
        ]);

        return "Table created: '{$table->name}' (ID: {$table->id})";
    }

    private function updateTable(Request $request): string
    {
        $table = DataTable::findOrFail($request['tableId']);

        if (isset($request['name'])) {
            $table->name = $request['name'];
        }

        if (isset($request['description'])) {
            $table->description = $request['description'];
        }

        $table->save();

        return 'Table updated.';
    }

    private function deleteTable(Request $request): string
    {
        $table = DataTable::findOrFail($request['tableId']);
        $name = $table->name;
        $table->delete();

        return "Table '{$name}' deleted.";
    }

    private function addColumn(Request $request): string
    {
        $table = DataTable::findOrFail($request['tableId']);

        $maxOrder = $table->columns()->max('order') ?? -1;

        $column = DataTableColumn::create([
            'table_id' => $table->id,
            'name' => $request['name'],
            'type' => $request['columnType'] ?? 'text',
            'options' => isset($request['columnOptions']) ? json_decode($request['columnOptions'], true) : null,
            'order' => $maxOrder + 1,
            'required' => $request['required'] ?? false,
        ]);

        return "Column '{$column->name}' added.";
    }

    private function updateColumn(Request $request): string
    {
        $column = DataTableColumn::findOrFail($request['columnId']);

        if (isset($request['name'])) {
            $column->name = $request['name'];
        }

        if (isset($request['columnType'])) {
            $column->type = $request['columnType'];
        }

        if (isset($request['columnOptions'])) {
            $column->options = json_decode($request['columnOptions'], true);
        }

        if (isset($request['required'])) {
            $column->required = $request['required'];
        }

        $column->save();

        return 'Column updated.';
    }

    private function deleteColumn(Request $request): string
    {
        $column = DataTableColumn::findOrFail($request['columnId']);
        $column->delete();

        return 'Column deleted.';
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("The action to perform: 'create_table', 'update_table', 'delete_table', 'add_column', 'update_column', or 'delete_column'.")
                ->required(),
            'tableId' => $schema
                ->string()
                ->description('The UUID of the table. Required for all actions except create_table.'),
            'columnId' => $schema
                ->string()
                ->description('The UUID of the column. Required for update_column and delete_column.'),
            'name' => $schema
                ->string()
                ->description('The name of the table or column.'),
            'description' => $schema
                ->string()
                ->description('A description for the table.'),
            'columnType' => $schema
                ->string()
                ->description("The column type: 'text', 'number', 'date', 'select', 'multiselect', 'checkbox', 'url', 'email', 'user', or 'attachment'."),
            'columnOptions' => $schema
                ->string()
                ->description('JSON string of column options (e.g. select choices).'),
            'required' => $schema
                ->boolean()
                ->description('Whether the column is required.'),
        ];
    }
}

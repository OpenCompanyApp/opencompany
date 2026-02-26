<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableView;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateTableView implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create a saved view for a data table. Supports grid, kanban, gallery, and calendar view types with filters, sorts, and column visibility.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tableId' => $schema
                ->string()
                ->description('The UUID of the table.')
                ->required(),
            'name' => $schema
                ->string()
                ->description('Name for the view.')
                ->required(),
            'type' => $schema
                ->string()
                ->description('View type: grid, kanban, gallery, or calendar.')
                ->required(),
            'filters' => $schema
                ->array()
                ->description('Filter conditions for the view.'),
            'sorts' => $schema
                ->array()
                ->description('Sort rules for the view.'),
            'hiddenColumns' => $schema
                ->array()
                ->description('Array of column IDs to hide.'),
            'config' => $schema
                ->object()
                ->description('Additional view configuration (e.g. kanban grouping column, calendar date column).'),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $table = DataTable::forWorkspace()->findOrFail($request['tableId']);

            $view = DataTableView::create([
                'table_id' => $table->id,
                'name' => $request['name'],
                'type' => $request['type'],
                'filters' => $request['filters'] ?? [],
                'sorts' => $request['sorts'] ?? [],
                'hidden_columns' => $request['hiddenColumns'] ?? [],
                'config' => $request['config'] ?? [],
            ]);

            return json_encode([
                'id' => $view->id,
                'name' => $view->name,
                'type' => $view->type,
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error creating view: {$e->getMessage()}";
        }
    }
}

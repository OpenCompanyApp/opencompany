<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTableView;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateTableView implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Update a saved table view (name, type, filters, sorts, hidden columns, or config).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'viewId' => $schema
                ->string()
                ->description('The UUID of the view to update.')
                ->required(),
            'name' => $schema
                ->string()
                ->description('New name for the view.'),
            'type' => $schema
                ->string()
                ->description('New view type: grid, kanban, gallery, or calendar.'),
            'filters' => $schema
                ->array()
                ->description('New filter conditions.'),
            'sorts' => $schema
                ->array()
                ->description('New sort rules.'),
            'hiddenColumns' => $schema
                ->array()
                ->description('New array of column IDs to hide.'),
            'config' => $schema
                ->object()
                ->description('New view configuration.'),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $view = DataTableView::findOrFail($request['viewId']);

            if (isset($request['name'])) {
                $view->name = $request['name'];
            }
            if (isset($request['type'])) {
                $view->type = $request['type'];
            }
            if (isset($request['filters'])) {
                $view->filters = $request['filters'];
            }
            if (isset($request['sorts'])) {
                $view->sorts = $request['sorts'];
            }
            if (isset($request['hiddenColumns'])) {
                $view->hidden_columns = $request['hiddenColumns'];
            }
            if (isset($request['config'])) {
                $view->config = $request['config'];
            }

            $view->save();

            return json_encode([
                'status' => 'updated',
                'id' => $view->id,
                'name' => $view->name,
                'type' => $view->type,
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error updating view: {$e->getMessage()}";
        }
    }
}

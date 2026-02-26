<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTableView;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteTableView implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Delete a saved table view.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'viewId' => $schema
                ->string()
                ->description('The UUID of the view to delete.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $view = DataTableView::findOrFail($request['viewId']);
            $name = $view->name;
            $view->delete();

            return "View '{$name}' deleted.";
        } catch (\Throwable $e) {
            return "Error deleting view: {$e->getMessage()}";
        }
    }
}

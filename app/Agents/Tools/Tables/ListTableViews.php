<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Lists saved views for a workspace-owned table.
 *
 * Views are returned with their structured configuration so agents can inspect
 * existing filters/sorts before creating or updating another view.
 */
class ListTableViews implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List saved views for a data table (grid, kanban, gallery, calendar).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tableId' => $schema
                ->string()
                ->description('The UUID of the table.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $table = DataTable::forWorkspace()->findOrFail($request['tableId']);

            // Order by creation time to match the user's mental model of saved
            // views in the UI sidebar.
            $views = $table->views()->orderBy('created_at')->get();

            return json_encode($views->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'type' => $v->type,
                'filters' => $v->filters,
                'sorts' => $v->sorts,
                'hiddenColumns' => $v->hidden_columns,
                'config' => $v->config,
            ])->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing views: {$e->getMessage()}";
        }
    }
}

<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Updates table-level metadata for a workspace-owned table.
 *
 * Schema and row changes intentionally live in separate tools so agents can ask
 * for narrow approvals and produce clearer audit trails.
 */
class UpdateTable implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Update an existing data table name, description, or icon.';
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
                ->description('The new name of the table.'),
            'description' => $schema
                ->string()
                ->description('The new description for the table.'),
            'icon' => $schema
                ->string()
                ->description('Icon identifier for the table.'),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $table = DataTable::forWorkspace()->findOrFail($request['tableId']);

            // Only touch fields that were explicitly supplied. Empty strings are
            // valid UI values, so use isset rather than truthiness.
            if (isset($request['name'])) {
                $table->name = $request['name'];
            }

            if (isset($request['description'])) {
                $table->description = $request['description'];
            }

            if (isset($request['icon'])) {
                $table->icon = $request['icon'];
            }

            $table->save();

            return 'Table updated.';
        } catch (\Throwable $e) {
            return "Error managing table: {$e->getMessage()}";
        }
    }
}

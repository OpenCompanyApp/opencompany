<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Deletes a workspace-owned table.
 *
 * The database relationships/cascades own the cleanup of columns, rows, and
 * saved views; this tool only decides whether the table itself is in scope.
 */
class DeleteTable implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Delete a data table and all its columns and rows from the workspace.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tableId' => $schema
                ->string()
                ->description('The UUID of the table to delete.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $table = DataTable::forWorkspace()->findOrFail($request['tableId']);
            $name = $table->name;
            // Deleting a table is intentionally destructive and irreversible at
            // the tool layer. Approval policy should wrap this when required.
            $table->delete();

            return "Table {$name} deleted.";
        } catch (\Throwable $e) {
            return "Error managing table: {$e->getMessage()}";
        }
    }
}

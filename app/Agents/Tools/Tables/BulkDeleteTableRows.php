<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class BulkDeleteTableRows implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Delete multiple rows from a data table at once.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tableId' => $schema
                ->string()
                ->description('The UUID of the table.')
                ->required(),
            'rowIds' => $schema
                ->array()
                ->description('Array of row UUIDs to delete.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $table = DataTable::forWorkspace()->findOrFail($request['tableId']);
            $rowIds = $request['rowIds'] ?? [];

            if (empty($rowIds)) {
                return 'Error: rowIds is required.';
            }

            $deleted = DataTableRow::where('table_id', $table->id)
                ->whereIn('id', $rowIds)
                ->delete();

            return "Deleted {$deleted} row(s).";
        } catch (\Throwable $e) {
            return "Error deleting rows: {$e->getMessage()}";
        }
    }
}

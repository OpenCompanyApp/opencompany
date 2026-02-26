<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ReorderTableColumns implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Reorder columns in a data table by specifying column IDs and their new order positions.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tableId' => $schema
                ->string()
                ->description('The UUID of the table.')
                ->required(),
            'columnOrder' => $schema
                ->array()
                ->description('Array of objects with columnId and order (e.g. [{"columnId": "uuid", "order": 0}, ...])')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $table = DataTable::forWorkspace()->findOrFail($request['tableId']);
            $columnOrder = $request['columnOrder'] ?? [];

            if (empty($columnOrder)) {
                return 'Error: columnOrder is required.';
            }

            foreach ($columnOrder as $item) {
                $columnId = $item['columnId'] ?? null;
                $order = $item['order'] ?? null;

                if ($columnId === null || $order === null) {
                    continue;
                }

                DataTableColumn::where('id', $columnId)
                    ->where('table_id', $table->id)
                    ->update(['order' => $order]);
            }

            return 'Columns reordered.';
        } catch (\Throwable $e) {
            return "Error reordering columns: {$e->getMessage()}";
        }
    }
}

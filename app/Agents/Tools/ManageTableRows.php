<?php

namespace App\Agents\Tools;

use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ManageTableRows implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Add, update, or delete rows in a data table. Use this to manage row-level data in workspace tables.';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];

            return match ($action) {
                'add_row' => $this->addRow($request),
                'update_row' => $this->updateRow($request),
                'delete_row' => $this->deleteRow($request),
                'bulk_add' => $this->bulkAdd($request),
                default => "Unknown action: {$action}. Use 'add_row', 'update_row', 'delete_row', or 'bulk_add'.",
            };
        } catch (\Throwable $e) {
            return "Error managing table rows: {$e->getMessage()}";
        }
    }

    private function addRow(Request $request): string
    {
        $table = DataTable::findOrFail($request['tableId']);
        $data = isset($request['data']) ? json_decode($request['data'], true) : [];

        $row = DataTableRow::create([
            'table_id' => $table->id,
            'data' => $data,
            'created_by' => $this->agent->id,
        ]);

        return "Row added (ID: {$row->id})";
    }

    private function updateRow(Request $request): string
    {
        $row = DataTableRow::findOrFail($request['rowId']);
        $data = isset($request['data']) ? json_decode($request['data'], true) : [];

        $row->data = array_merge($row->data ?? [], $data);
        $row->save();

        return 'Row updated.';
    }

    private function deleteRow(Request $request): string
    {
        $row = DataTableRow::findOrFail($request['rowId']);
        $row->delete();

        return 'Row deleted.';
    }

    private function bulkAdd(Request $request): string
    {
        $table = DataTable::findOrFail($request['tableId']);
        $rows = json_decode($request['rows'], true);

        $count = 0;
        foreach ($rows as $rowData) {
            DataTableRow::create([
                'table_id' => $table->id,
                'data' => $rowData,
                'created_by' => $this->agent->id,
            ]);
            $count++;
        }

        return "Added {$count} rows.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("The action to perform: 'add_row', 'update_row', 'delete_row', or 'bulk_add'.")
                ->required(),
            'tableId' => $schema
                ->string()
                ->description('The UUID of the table.')
                ->required(),
            'rowId' => $schema
                ->string()
                ->description('The UUID of the row. Required for update_row and delete_row.'),
            'data' => $schema
                ->string()
                ->description('JSON string of column_id:value pairs for the row data.'),
            'rows' => $schema
                ->string()
                ->description('JSON array of row data objects for bulk_add. Each element is a column_id:value map.'),
        ];
    }
}

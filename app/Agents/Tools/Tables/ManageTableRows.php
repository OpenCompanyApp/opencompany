<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableColumn;
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
        $table = DataTable::forWorkspace()->findOrFail($request['tableId']);
        $data = isset($request['data']) ? json_decode($request['data'], true) : [];

        $data = $this->resolveDataKeys($table, $data);

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

        $table = DataTable::forWorkspace()->findOrFail($row->table_id);
        $data = $this->resolveDataKeys($table, $data);

        $row->data = array_merge($row->data ?? [], $data);
        $row->save();

        return 'Row updated.';
    }

    private function deleteRow(Request $request): string
    {
        $row = DataTableRow::findOrFail($request['rowId']);
        DataTable::forWorkspace()->findOrFail($row->table_id); // verify workspace
        $row->delete();

        return 'Row deleted.';
    }

    private function bulkAdd(Request $request): string
    {
        $table = DataTable::forWorkspace()->findOrFail($request['tableId']);
        $rows = json_decode($request['rows'], true);

        // Pre-resolve columns from the first row so we don't re-create per row
        if (!empty($rows)) {
            $this->resolveDataKeys($table, $rows[0]);
        }

        $count = 0;
        foreach ($rows as $rowData) {
            DataTableRow::create([
                'table_id' => $table->id,
                'data' => $this->resolveDataKeys($table, $rowData),
                'created_by' => $this->agent->id,
            ]);
            $count++;
        }

        return "Added {$count} rows.";
    }

    /**
     * Resolve human-readable data keys to column UUIDs, auto-creating columns as needed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveDataKeys(DataTable $table, array $data): array
    {
        if (empty($data)) {
            return $data;
        }

        $columns = $table->columns()->get();
        $columnById = $columns->keyBy('id');
        $columnByName = $columns->keyBy(fn (DataTableColumn $col) => strtolower($col->name));

        $resolved = [];
        $maxOrder = $columns->max('order') ?? -1;

        foreach ($data as $key => $value) {
            // Key is already a valid column UUID
            if ($columnById->has($key)) {
                $resolved[$key] = $value;
                continue;
            }

            // Key matches an existing column name
            $lowerKey = strtolower($key);
            if ($columnByName->has($lowerKey)) {
                /** @var DataTableColumn $matchedCol */
                $matchedCol = $columnByName->get($lowerKey);
                $resolved[$matchedCol->id] = $value;
                continue;
            }

            // Auto-create a new column
            $maxOrder++;
            $column = DataTableColumn::create([
                'table_id' => $table->id,
                'name' => str_replace('_', ' ', ucfirst($key)),
                'type' => $this->inferColumnType($value),
                'order' => $maxOrder,
                'required' => false,
            ]);

            // Cache for subsequent keys/rows
            $columnByName->put($lowerKey, $column);
            $columnById->put($column->id, $column);

            $resolved[$column->id] = $value;
        }

        return $resolved;
    }

    private function inferColumnType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'checkbox';
        }

        if (is_int($value) || is_float($value)) {
            return 'number';
        }

        if (is_string($value)) {
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return 'url';
            }
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'email';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                return 'date';
            }
        }

        return 'text';
    }

    /**
     * @return array<string, mixed>
     */
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
                ->description('JSON string of key:value pairs for the row data. Keys can be column names (e.g. "status") or column UUIDs. New columns are auto-created for unknown keys.'),
            'rows' => $schema
                ->string()
                ->description('JSON array of row data objects for bulk_add. Each element is a key:value map. Keys can be column names or UUIDs.'),
        ];
    }
}

<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class BulkAddTableRows implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Add multiple rows to a data table at once. Keys can be column names or UUIDs; unknown keys auto-create columns.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            "tableId" => $schema
                ->string()
                ->description("The UUID of the table.")
                ->required(),
            "rows" => $schema
                ->string()
                ->description("JSON array of row data objects. Each element is a key:value map. Keys can be column names or UUIDs.")
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $table = DataTable::forWorkspace()->findOrFail($request["tableId"]);
            $raw = $request["rows"];
            $rows = is_array($raw) ? $raw : json_decode($raw, true);

            // Pre-resolve columns from the first row so we do not re-create per row
            if (!empty($rows)) {
                $this->resolveDataKeys($table, $rows[0]);
            }

            $count = 0;
            foreach ($rows as $rowData) {
                DataTableRow::create([
                    "table_id" => $table->id,
                    "data" => $this->resolveDataKeys($table, $rowData),
                    "created_by" => $this->agent->id,
                ]);
                $count++;
            }

            return "Added {$count} rows.";
        } catch (\Throwable $e) {
            return "Error managing table rows: {$e->getMessage()}";
        }
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
        $columnById = $columns->keyBy("id");
        $columnByName = $columns->keyBy(fn (DataTableColumn $col) => strtolower($col->name));

        $resolved = [];
        $maxOrder = $columns->max("order") ?? -1;

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
                "table_id" => $table->id,
                "name" => str_replace("_", " ", ucfirst($key)),
                "type" => $this->inferColumnType($value),
                "order" => $maxOrder,
                "required" => false,
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
            return "checkbox";
        }

        if (is_int($value) || is_float($value)) {
            return "number";
        }

        if (is_string($value)) {
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return "url";
            }
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return "email";
            }
            if (preg_match("/^\d{4}-\d{2}-\d{2}/", $value)) {
                return "date";
            }
        }

        return "text";
    }
}
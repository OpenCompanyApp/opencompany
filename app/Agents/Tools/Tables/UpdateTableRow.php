<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateTableRow implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Update an existing row in a data table. By default merges with existing data; set merge to false to replace entirely.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            "rowId" => $schema
                ->string()
                ->description("The UUID of the row to update.")
                ->required(),
            "data" => $schema
                ->object()
                ->description("Key:value pairs for the row data. Keys can be column names (e.g. status) or column UUIDs. New columns are auto-created for unknown keys.")
                ->required(),
            "merge" => $schema
                ->boolean()
                ->description("Merge with existing data (true, default) or replace entirely (false)."),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $row = DataTableRow::findOrFail($request["rowId"]);
            $data = $request["data"] ?? [];

            $table = DataTable::forWorkspace()->findOrFail($row->table_id);
            $data = $this->resolveDataKeys($table, $data);

            $merge = $request["merge"] ?? true;
            $row->data = $merge ? array_merge($row->data ?? [], $data) : $data;
            $row->save();

            return "Row updated.";
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
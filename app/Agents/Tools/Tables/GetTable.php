<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetTable implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Get a data table's structure, including its columns and their types.";
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
            $tableId = $request['tableId'] ?? null;
            if (!$tableId) {
                return "Error: 'tableId' is required.";
            }

            $table = DataTable::forWorkspace()->with(['columns', 'creator'])->find($tableId);
            if (!$table) {
                return "Error: Table '{$tableId}' not found.";
            }

            $creator = ($table->creator ? $table->creator->name : 'Unknown');
            $lines = [
                "Table: {$table->name}",
                "Description: " . ($table->description ?? 'None'),
                "Created by: {$creator}",
                "Columns ({$table->columns->count()}):",
            ];

            foreach ($table->columns as $column) {
                $required = $column->required ? ' (required)' : '';
                $lines[] = "- {$column->name} [{$column->type}]{$required}";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error querying table: {$e->getMessage()}";
        }
    }
}
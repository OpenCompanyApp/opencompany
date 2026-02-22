<?php

namespace App\Agents\Tools\Tables;

use App\Models\DataTable;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListTables implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List all data tables in the workspace, including their column and row counts.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        try {
            $tables = DataTable::forWorkspace()->with('creator')
                ->withCount(['rows as rowsCount', 'columns as columnsCount'])
                ->get();

            if ($tables->isEmpty()) {
                return "No data tables found in the workspace.";
            }

            $lines = ["Data tables ({$tables->count()}):"];
            foreach ($tables as $table) {
                $creator = ($table->creator ? $table->creator->name : 'Unknown');
                $desc = $table->description ? " - {$table->description}" : '';
                $lines[] = "- {$table->name}{$desc} ({$table->columnsCount} columns, {$table->rowsCount} rows, created by {$creator})";
                $lines[] = "  ID: {$table->id}";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error querying table: {$e->getMessage()}";
        }
    }
}

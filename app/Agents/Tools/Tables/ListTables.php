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
                return json_encode([]);
            }

            return json_encode($tables->map(fn ($table) => [
                'id' => $table->id,
                'name' => $table->name,
                'description' => $table->description,
                'columns' => $table->columnsCount,
                'rows' => $table->rowsCount,
                'createdBy' => $table->creator?->name ?? 'Unknown',
            ])->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error querying table: {$e->getMessage()}";
        }
    }
}

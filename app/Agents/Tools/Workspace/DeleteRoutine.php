<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Routine;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Agent tool `delete_routine`: deletes a routine in the current workspace.
 * Script revisions survive deletion; built-in routines are refused.
 */
class DeleteRoutine implements Tool
{
    public function description(): string
    {
        return 'Delete a routine and its schedule permanently.';
    }

    public function handle(Request $request): string
    {
        try {
            $routineId = $request['routineId'] ?? null;
            if (! $routineId) {
                return 'routineId is required.';
            }

            $routine = Routine::forWorkspace()->find($routineId);
            if (! $routine) {
                return "Routine not found: {$routineId}";
            }
            if ($routine->isBuiltin()) {
                return 'Built-in routines are managed by OpenCompany and cannot be deleted.';
            }

            $name = $routine->name;
            $routine->delete();

            return "Routine deleted: {$name}";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'routineId' => $schema
                ->string()
                ->description('Routine UUID.')
                ->required(),
        ];
    }
}

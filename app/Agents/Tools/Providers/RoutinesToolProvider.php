<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Workspace\CreateRoutine;
use App\Agents\Tools\Workspace\DeleteRoutine;
use App\Agents\Tools\Workspace\GetRoutine;
use App\Agents\Tools\Workspace\ListRoutines;
use App\Agents\Tools\Workspace\RunRoutine;
use App\Agents\Tools\Workspace\UpdateRoutine;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers workspace routine tools (tool group `routines`).
 *
 * Routines are grouped separately from general workspace management because
 * they are executable runtime assets: an agent can create, edit, and trigger
 * scheduled agent routines or runtime-pinned Ruby script routines. Built-in
 * routines are listed and runnable but system-managed (not editable).
 * Creating a routine grants it nothing: its runs use the executing agent's
 * ordinary permissions.
 */
class RoutinesToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'routines';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'list, get, create, update, delete, run',
            'description' => 'Scheduled routines. kind "agent" runs instructions through an agent; "script" runs validated synchronous Ruby on opencompany-code-v1 (no LLM). Use code_read_doc for APIs.',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:lightning';
    }

    public function tools(): array
    {
        return [
            'list_routines' => [
                'class' => ListRoutines::class,
                'type' => 'read',
                'name' => 'List Routines',
                'description' => 'List all routines with status, kind, run count, and next run time.',
                'icon' => 'ph:lightning',
            ],
            'get_routine' => [
                'class' => GetRoutine::class,
                'type' => 'read',
                'name' => 'Get Routine',
                'description' => 'Get full routine details including config, content, execution history, and status.',
                'icon' => 'ph:lightning',
            ],
            'create_routine' => [
                'class' => CreateRoutine::class,
                'type' => 'write',
                'name' => 'Create Routine',
                'description' => 'Create an agent routine or a validated opencompany-code-v1 Ruby script routine.',
                'icon' => 'ph:lightning',
            ],
            'update_routine' => [
                'class' => UpdateRoutine::class,
                'type' => 'write',
                'name' => 'Update Routine',
                'description' => "Update a routine's configuration or active status.",
                'icon' => 'ph:lightning',
            ],
            'delete_routine' => [
                'class' => DeleteRoutine::class,
                'type' => 'write',
                'name' => 'Delete Routine',
                'description' => 'Delete a routine permanently.',
                'icon' => 'ph:trash',
            ],
            'run_routine' => [
                'class' => RunRoutine::class,
                'type' => 'write',
                'name' => 'Run Routine',
                'description' => 'Manually trigger a routine to run immediately.',
                'icon' => 'ph:play',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        // Create/update need the acting agent for workspace and author checks;
        // the rest resolve scope through forWorkspace().
        return match ($class) {
            CreateRoutine::class, UpdateRoutine::class => new $class($agent),
            ListRoutines::class, GetRoutine::class, DeleteRoutine::class, RunRoutine::class => new $class,
            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
    }
}

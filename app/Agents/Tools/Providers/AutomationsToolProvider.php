<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Workspace\CreateAutomation;
use App\Agents\Tools\Workspace\DeleteAutomation;
use App\Agents\Tools\Workspace\GetAutomation;
use App\Agents\Tools\Workspace\ListAutomations;
use App\Agents\Tools\Workspace\RunAutomation;
use App\Agents\Tools\Workspace\UpdateAutomation;
use App\Models\User;
use App\Services\MrubySandboxService;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers workspace automation tools.
 *
 * Automations are grouped separately from general workspace management because
 * they are executable runtime assets: an agent can create, edit, and trigger
 * scheduled prompt or runtime-pinned Ruby workflows.
 */
class AutomationsToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'automations';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'list, get, create, update, delete, run',
            'description' => 'Scheduled automations. "prompt" uses an agent; "script" runs validated synchronous Ruby on opencompany-code-v1. Use code_read_doc for APIs.',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:lightning';
    }

    public function tools(): array
    {
        return [
            'list_automations' => [
                'class' => ListAutomations::class,
                'type' => 'read',
                'name' => 'List Automations',
                'description' => 'List all automations with status, type, run count, and next run time.',
                'icon' => 'ph:lightning',
            ],
            'get_automation' => [
                'class' => GetAutomation::class,
                'type' => 'read',
                'name' => 'Get Automation',
                'description' => 'Get full automation details including config, content, execution history, and status.',
                'icon' => 'ph:lightning',
            ],
            'create_automation' => [
                'class' => CreateAutomation::class,
                'type' => 'write',
                'name' => 'Create Automation',
                'description' => 'Create a prompt automation or a validated opencompany-code-v1 Ruby automation.',
                'icon' => 'ph:lightning',
            ],
            'update_automation' => [
                'class' => UpdateAutomation::class,
                'type' => 'write',
                'name' => 'Update Automation',
                'description' => "Update an automation's configuration or active status.",
                'icon' => 'ph:lightning',
            ],
            'delete_automation' => [
                'class' => DeleteAutomation::class,
                'type' => 'write',
                'name' => 'Delete Automation',
                'description' => 'Delete an automation permanently.',
                'icon' => 'ph:trash',
            ],
            'run_automation' => [
                'class' => RunAutomation::class,
                'type' => 'write',
                'name' => 'Run Automation',
                'description' => 'Manually trigger an automation to run immediately.',
                'icon' => 'ph:play',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        return match ($class) {
            CreateAutomation::class,
            UpdateAutomation::class => new $class($agent, app(MrubySandboxService::class)),
            default => new $class($agent),
        };
    }
}

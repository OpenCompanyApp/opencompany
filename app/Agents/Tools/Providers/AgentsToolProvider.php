<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Agents\ContactAgent;
use App\Agents\Tools\Workspace\ListAgents;
use App\Models\User;
use App\Services\AgentCommunicationService;
use App\Services\AgentPermissionService;

class AgentsToolProvider implements BuiltInToolProvider
{
    public function __construct(
        private AgentPermissionService $permissionService,
    ) {}

    public function groupName(): string
    {
        return 'agents';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'ask, delegate, notify, list',
            'description' => 'Inter-agent communication',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:users-three';
    }

    public function tools(): array
    {
        return [
            'contact_agent' => [
                'class' => ContactAgent::class,
                'type' => 'write',
                'name' => 'Contact Agent',
                'description' => 'Send a message, ask a question, or delegate work to another agent.',
                'icon' => 'ph:users-three',
            ],
            'list_agents' => [
                'class' => ListAgents::class,
                'type' => 'read',
                'name' => 'List Agents',
                'description' => 'List all agents in the workspace.',
                'icon' => 'ph:robot',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): \Laravel\Ai\Contracts\Tool
    {
        return match ($class) {
            ContactAgent::class => new ContactAgent($agent, $this->permissionService, app(AgentCommunicationService::class), $context['task_id'] ?? null),
            ListAgents::class => new ListAgents,
            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
    }
}

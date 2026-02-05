<?php

namespace App\Agents\Tools;

use App\Models\User;
use App\Services\AgentPermissionService;

class ToolRegistry
{
    /**
     * Registry of all available tools with metadata.
     */
    private const TOOL_MAP = [
        'send_channel_message' => [
            'class' => SendChannelMessage::class,
            'type' => 'write',
            'name' => 'Send Channel Message',
            'description' => 'Send a message to a channel in the workspace.',
            'icon' => 'ph:chat-circle',
        ],
        'search_documents' => [
            'class' => SearchDocuments::class,
            'type' => 'read',
            'name' => 'Search Documents',
            'description' => 'Search workspace documents by keyword.',
            'icon' => 'ph:magnifying-glass',
        ],
        'create_task_step' => [
            'class' => CreateTaskStep::class,
            'type' => 'write',
            'name' => 'Create Task Step',
            'description' => 'Log a progress step on a task you are working on.',
            'icon' => 'ph:list-checks',
        ],
        'wait_for_approval' => [
            'class' => WaitForApproval::class,
            'type' => 'write',
            'name' => 'Wait For Approval',
            'description' => 'Pause execution until a pending approval is decided.',
            'icon' => 'ph:pause-circle',
        ],
    ];

    public function __construct(
        private AgentPermissionService $permissionService,
    ) {}

    /**
     * Get tools available for a given agent, filtered by permissions.
     * Tools requiring approval are wrapped in ApprovalWrappedTool.
     *
     * @return array<\Laravel\Ai\Contracts\Tool>
     */
    public function getToolsForAgent(User $agent): array
    {
        $tools = [];

        foreach (self::TOOL_MAP as $slug => $meta) {
            $result = $this->permissionService->resolveToolPermission(
                $agent, $slug, $meta['type']
            );

            if (!$result['allowed']) {
                continue;
            }

            $tool = $this->instantiateTool($meta['class'], $agent);

            if ($result['requires_approval']) {
                $tool = new ApprovalWrappedTool($tool, $agent, $slug);
            }

            $tools[] = $tool;
        }

        return $tools;
    }

    /**
     * Get metadata for ALL tools with permission status for a specific agent.
     * Used by the API to populate the capabilities tab.
     */
    public function getAllToolsMeta(User $agent): array
    {
        $result = [];

        foreach (self::TOOL_MAP as $slug => $meta) {
            $permission = $this->permissionService->resolveToolPermission(
                $agent, $slug, $meta['type']
            );

            $result[] = [
                'id' => $slug,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'type' => $meta['type'],
                'icon' => $meta['icon'],
                'enabled' => $permission['allowed'],
                'requiresApproval' => $permission['requires_approval'],
            ];
        }

        return $result;
    }

    /**
     * Instantiate a specific tool by slug (for post-approval execution).
     */
    public function instantiateToolBySlug(string $slug, User $agent): ?\Laravel\Ai\Contracts\Tool
    {
        if (!isset(self::TOOL_MAP[$slug])) {
            return null;
        }

        return $this->instantiateTool(self::TOOL_MAP[$slug]['class'], $agent);
    }

    /**
     * Instantiate a tool class with the appropriate constructor arguments.
     */
    private function instantiateTool(string $class, User $agent): \Laravel\Ai\Contracts\Tool
    {
        return match ($class) {
            SendChannelMessage::class => new SendChannelMessage($agent, $this->permissionService),
            SearchDocuments::class => new SearchDocuments($agent, $this->permissionService),
            CreateTaskStep::class => new CreateTaskStep($agent),
            WaitForApproval::class => new WaitForApproval($agent),
            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
    }
}

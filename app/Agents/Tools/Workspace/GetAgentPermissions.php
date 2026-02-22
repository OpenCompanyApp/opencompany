<?php

namespace App\Agents\Tools\Workspace;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetAgentPermissions implements Tool
{
    public function __construct(
        private AgentPermissionService $permissionService,
        private ToolRegistry $toolRegistry,
    ) {}

    public function description(): string
    {
        return 'View the full permissions for a specific agent including tools, channels, folders, and integrations.';
    }

    public function handle(Request $request): string
    {
        try {
            $agentId = $request['agentId'] ?? null;
            if (!$agentId) {
                return 'agentId is required.';
            }

            $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->find($agentId);
            if (!$agent) {
                return "Agent not found: {$agentId}";
            }

            $tools = $this->toolRegistry->getAllToolsMeta($agent);
            $enabledIntegrations = $this->permissionService->getEnabledIntegrations($agent);
            $channelIds = $agent->channelPermissions()->where('permission', 'allow')->pluck('scope_key')->values();
            $folderIds = $agent->folderPermissions()->where('permission', 'allow')->pluck('scope_key')->values();

            $lines = [
                "Permissions for {$agent->name}:",
                '',
                'Behavior Mode: ' . ($agent->behavior_mode ?? 'autonomous'),
                '',
                'Enabled Integrations: ' . ($enabledIntegrations ? implode(', ', $enabledIntegrations) : 'all (no restrictions)'),
                'Allowed Channels: ' . ($channelIds->isEmpty() ? 'unrestricted' : $channelIds->implode(', ')),
                'Allowed Folders: ' . ($folderIds->isEmpty() ? 'unrestricted' : $folderIds->implode(', ')),
                '',
                'Tools:',
            ];

            foreach ($tools as $tool) {
                $status = $tool['enabled'] ? 'enabled' : 'DENIED';
                $approval = $tool['requiresApproval'] ? ' (requires approval)' : '';
                $lines[] = "- {$tool['id']}: {$status}{$approval}";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'agentId' => $schema
                ->string()
                ->description('Agent UUID.')
                ->required(),
        ];
    }
}
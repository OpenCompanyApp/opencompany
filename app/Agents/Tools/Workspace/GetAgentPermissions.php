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

            return json_encode([
                'agentName' => $agent->name,
                'behaviorMode' => $agent->behavior_mode ?? 'autonomous',
                'integrations' => $enabledIntegrations ?: null,
                'channels' => $channelIds->isEmpty() ? null : $channelIds->toArray(),
                'folders' => $folderIds->isEmpty() ? null : $folderIds->toArray(),
                'tools' => collect($tools)->map(fn ($tool) => [
                    'id' => $tool['id'],
                    'enabled' => $tool['enabled'],
                    'requiresApproval' => $tool['requiresApproval'],
                ])->values()->toArray(),
            ], JSON_PRETTY_PRINT);
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
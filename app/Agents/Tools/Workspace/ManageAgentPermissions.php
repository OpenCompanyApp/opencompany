<?php

namespace App\Agents\Tools\Workspace;

use App\Agents\Tools\ToolRegistry;
use App\Models\AgentPermission;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ManageAgentPermissions implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Update tool, channel, folder, and integration permissions for agents. You can only grant permissions you yourself have.';
    }

    public function handle(Request $request): string
    {
        try {
            $agentId = $request['agentId'] ?? null;
            if (!$agentId) {
                return 'agentId is required.';
            }

            // Self-modification guard
            if ($this->agent->id === $agentId) {
                return 'Self-modification denied: you cannot change your own permissions. Ask a human or another agent.';
            }

            $target = User::where('type', 'agent')->find($agentId);
            if (!$target) {
                return "Agent not found: {$agentId}";
            }

            return match ($request['action']) {
                'update_tools' => $this->updateTools($request, $target),
                'update_channels' => $this->updateChannels($request, $target),
                'update_folders' => $this->updateFolders($request, $target),
                'update_integrations' => $this->updateIntegrations($request, $target),
                default => "Unknown action: {$request['action']}. Use: update_tools, update_channels, update_folders, update_integrations",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function updateTools(Request $request, User $target): string
    {
        $toolsJson = $request['tools'] ?? null;
        if (!$toolsJson) {
            return 'tools is required. JSON array: [{"scopeKey":"slug","permission":"allow|deny","requiresApproval":true|false}]';
        }

        $tools = json_decode($toolsJson, true);
        if (!is_array($tools)) {
            return 'Invalid JSON for tools parameter.';
        }

        // Privilege escalation check: caller can only grant tools it has
        $denied = [];
        $escalated = [];
        foreach ($tools as $tool) {
            if (empty($tool['scopeKey']) || empty($tool['permission'])) {
                continue;
            }

            if ($tool['permission'] === 'allow') {
                $callerPerm = $this->permissionService->resolveToolPermission(
                    $this->agent, $tool['scopeKey'], 'write' // check as write (strictest)
                );

                if (!$callerPerm['allowed']) {
                    $denied[] = $tool['scopeKey'];
                    continue;
                }

                // Can't remove approval requirement if caller has it
                $wantsNoApproval = !($tool['requiresApproval'] ?? false);
                if ($wantsNoApproval && $callerPerm['requires_approval']) {
                    $escalated[] = $tool['scopeKey'];
                    continue;
                }
            }
        }

        if ($denied) {
            return "Privilege escalation denied: you don't have access to these tools and can't grant them: " . implode(', ', $denied);
        }
        if ($escalated) {
            return "Privilege escalation denied: you require approval for these tools and can't waive it for others: " . implode(', ', $escalated);
        }

        // All checks passed — apply
        AgentPermission::where('agent_id', $target->id)->where('scope_type', 'tool')->delete();

        foreach ($tools as $tool) {
            if (empty($tool['scopeKey']) || empty($tool['permission'])) {
                continue;
            }

            AgentPermission::create([
                'id' => Str::uuid()->toString(),
                'agent_id' => $target->id,
                'scope_type' => 'tool',
                'scope_key' => $tool['scopeKey'],
                'permission' => $tool['permission'],
                'requires_approval' => $tool['requiresApproval'] ?? false,
            ]);
        }

        return "Tool permissions updated for {$target->name}. Set " . count($tools) . " tool permission(s).";
    }

    private function updateChannels(Request $request, User $target): string
    {
        $channelsJson = $request['channels'] ?? '[]';
        $channels = json_decode($channelsJson, true);

        if (!is_array($channels)) {
            return 'Invalid JSON for channels parameter. Expected array of channel UUIDs.';
        }

        // Privilege escalation check
        $callerChannels = $this->permissionService->getAllowedChannelIds($this->agent);

        if ($callerChannels !== null) {
            // Caller is restricted — can't grant unrestricted access
            if (empty($channels)) {
                return 'Privilege escalation denied: you have restricted channel access and cannot grant unrestricted access.';
            }

            // Can only grant channels the caller has
            $unauthorized = array_diff($channels, $callerChannels);
            if ($unauthorized) {
                return "Privilege escalation denied: you don't have access to these channels: " . implode(', ', $unauthorized);
            }
        }

        AgentPermission::where('agent_id', $target->id)->where('scope_type', 'channel')->delete();

        foreach ($channels as $channelId) {
            AgentPermission::create([
                'id' => Str::uuid()->toString(),
                'agent_id' => $target->id,
                'scope_type' => 'channel',
                'scope_key' => $channelId,
                'permission' => 'allow',
                'requires_approval' => false,
            ]);
        }

        $status = empty($channels) ? 'unrestricted (all channels)' : count($channels) . ' channel(s) allowed';
        return "Channel permissions updated for {$target->name}: {$status}.";
    }

    private function updateFolders(Request $request, User $target): string
    {
        $foldersJson = $request['folders'] ?? '[]';
        $folders = json_decode($foldersJson, true);

        if (!is_array($folders)) {
            return 'Invalid JSON for folders parameter. Expected array of folder UUIDs.';
        }

        // Privilege escalation check
        $callerFolders = $this->permissionService->getAllowedFolderIds($this->agent);

        if ($callerFolders !== null) {
            // Caller is restricted — can't grant unrestricted access
            if (empty($folders)) {
                return 'Privilege escalation denied: you have restricted folder access and cannot grant unrestricted access.';
            }

            // Can only grant folders the caller has
            $unauthorized = array_diff($folders, $callerFolders);
            if ($unauthorized) {
                return "Privilege escalation denied: you don't have access to these folders: " . implode(', ', $unauthorized);
            }
        }

        AgentPermission::where('agent_id', $target->id)->where('scope_type', 'folder')->delete();

        foreach ($folders as $folderId) {
            AgentPermission::create([
                'id' => Str::uuid()->toString(),
                'agent_id' => $target->id,
                'scope_type' => 'folder',
                'scope_key' => $folderId,
                'permission' => 'allow',
                'requires_approval' => false,
            ]);
        }

        $status = empty($folders) ? 'unrestricted (all folders)' : count($folders) . ' folder(s) allowed';
        return "Folder permissions updated for {$target->name}: {$status}.";
    }

    private function updateIntegrations(Request $request, User $target): string
    {
        $integrationsJson = $request['integrations'] ?? '[]';
        $integrations = json_decode($integrationsJson, true);

        if (!is_array($integrations)) {
            return 'Invalid JSON for integrations parameter. Expected array of app names (e.g., ["telegram","plausible"]).';
        }

        // Privilege escalation check: can only enable integrations the caller has
        $callerIntegrations = $this->permissionService->getEnabledIntegrations($this->agent);
        $unauthorized = array_diff($integrations, $callerIntegrations);

        if ($unauthorized) {
            return "Privilege escalation denied: you don't have these integrations enabled: " . implode(', ', $unauthorized);
        }

        AgentPermission::where('agent_id', $target->id)->where('scope_type', 'integration')->delete();

        foreach (ToolRegistry::INTEGRATION_APPS as $app) {
            AgentPermission::create([
                'id' => Str::uuid()->toString(),
                'agent_id' => $target->id,
                'scope_type' => 'integration',
                'scope_key' => $app,
                'permission' => in_array($app, $integrations) ? 'allow' : 'deny',
                'requires_approval' => false,
            ]);
        }

        return "Integration permissions updated for {$target->name}. Enabled: " . ($integrations ? implode(', ', $integrations) : 'none') . '.';
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("Action: 'update_tools', 'update_channels', 'update_folders', 'update_integrations'")
                ->required(),
            'agentId' => $schema
                ->string()
                ->description('Target agent UUID. Required for all actions.')
                ->required(),
            'tools' => $schema
                ->string()
                ->description('JSON array for update_tools: [{"scopeKey":"tool_slug","permission":"allow|deny","requiresApproval":true|false}]'),
            'channels' => $schema
                ->string()
                ->description('JSON array of channel UUIDs for update_channels. Empty array = unrestricted.'),
            'folders' => $schema
                ->string()
                ->description('JSON array of folder UUIDs for update_folders. Empty array = unrestricted.'),
            'integrations' => $schema
                ->string()
                ->description('JSON array of integration app names for update_integrations, e.g. ["telegram","plausible"].'),
        ];
    }
}

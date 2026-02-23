<?php

namespace App\Agents\Tools\Workspace;

use App\Models\AgentPermission;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateAgentFolderAccess implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Update folder access permissions for an agent. Empty array means unrestricted access.';
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

            $target = User::where('type', 'agent')->where('workspace_id', $this->agent->workspace_id)->find($agentId);
            if (!$target) {
                return "Agent not found: {$agentId}";
            }

            $raw = $request['folders'] ?? [];
            $folders = is_array($raw) ? $raw : json_decode($raw, true);

            if (!is_array($folders)) {
                return 'Invalid folders parameter. Expected array of folder UUIDs.';
            }

            // Privilege escalation check
            $callerFolders = $this->permissionService->getAllowedFolderIds($this->agent);

            if ($callerFolders !== null) {
                // Caller is restricted — cannot grant unrestricted access
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
                ->description('Target agent UUID.')
                ->required(),
            'folders' => $schema
                ->string()
                ->description('JSON array of folder UUIDs. Empty array = unrestricted.')
                ->required(),
        ];
    }
}
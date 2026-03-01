<?php

namespace App\Agents\Tools\Workspace;

use App\Models\AgentPermission;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateAgentFileFolderAccess implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Update file folder access permissions for an agent. By default agents can only access their own home folder. Use this to grant access to additional folders. Pass ["*"] for unrestricted access.';
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

            $folders = $request['folders'] ?? [];

            if (!is_array($folders)) {
                return 'Invalid folders parameter. Expected array of folder UUIDs (or ["*"] for unrestricted).';
            }

            // Privilege escalation check
            $callerFolders = $this->permissionService->getAllowedFileFolderIds($this->agent);

            if ($callerFolders !== null && !empty($callerFolders)) {
                // Caller is restricted — cannot grant unrestricted access
                if (in_array('*', $folders)) {
                    return 'Privilege escalation denied: you have restricted file folder access and cannot grant unrestricted access.';
                }

                // Can only grant folders the caller has
                $unauthorized = array_diff($folders, $callerFolders);
                if ($unauthorized) {
                    return "Privilege escalation denied: you don't have access to these file folders: " . implode(', ', $unauthorized);
                }
            }

            AgentPermission::where('agent_id', $target->id)->where('scope_type', 'file_folder')->delete();

            foreach ($folders as $folderId) {
                AgentPermission::create([
                    'id' => Str::uuid()->toString(),
                    'agent_id' => $target->id,
                    'scope_type' => 'file_folder',
                    'scope_key' => $folderId,
                    'permission' => 'allow',
                    'requires_approval' => false,
                ]);
            }

            $status = in_array('*', $folders) ? 'unrestricted (all file folders)' :
                (empty($folders) ? 'home folder only (default)' : count($folders) . ' file folder(s) allowed');
            return "File folder permissions updated for {$target->name}: {$status}.";
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
                ->array()
                ->items($schema->string())
                ->description('Array of file folder UUIDs to grant access. Empty array = home folder only. ["*"] = unrestricted.')
                ->required(),
        ];
    }
}

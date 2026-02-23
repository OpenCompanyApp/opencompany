<?php

namespace App\Agents\Tools\Workspace;

use App\Models\AgentPermission;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateAgentChannelAccess implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Update channel access permissions for an agent. Empty array means unrestricted access.';
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

            $channels = $request['channels'] ?? [];

            if (!is_array($channels)) {
                return 'Invalid channels parameter. Expected array of channel UUIDs.';
            }

            // Privilege escalation check
            $callerChannels = $this->permissionService->getAllowedChannelIds($this->agent);

            if ($callerChannels !== null) {
                // Caller is restricted — cannot grant unrestricted access
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
            'channels' => $schema
                ->array()
                ->items($schema->string())
                ->description('Array of channel UUIDs. Empty array = unrestricted.')
                ->required(),
        ];
    }
}
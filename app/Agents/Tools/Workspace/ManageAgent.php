<?php

namespace App\Agents\Tools\Workspace;

use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Services\AgentAvatarService;
use App\Services\AgentDocumentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ManageAgent implements Tool
{
    public function __construct(
        private User $agent,
        private AgentDocumentService $agentDocumentService,
        private AgentAvatarService $agentAvatarService,
    ) {}

    public function description(): string
    {
        return 'Create, update, or delete agents. Read or update agent identity files.';
    }

    public function handle(Request $request): string
    {
        try {
            return match ($request['action']) {
                'create' => $this->create($request),
                'update' => $this->update($request),
                'delete' => $this->delete($request),
                'read_identity_file' => $this->readIdentityFile($request),
                'update_identity_file' => $this->updateIdentityFile($request),
                default => "Unknown action: {$request['action']}. Use: create, update, delete, read_identity_file, update_identity_file",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function create(Request $request): string
    {
        $name = $request['name'] ?? null;
        $agentType = $request['agentType'] ?? null;
        $brain = $request['brain'] ?? null;

        if (!$name || !$agentType || !$brain) {
            return 'Required: name, agentType, brain.';
        }

        if (!str_contains($brain, ':')) {
            return 'Invalid brain format. Expected "provider:model" (e.g., "anthropic:claude-sonnet-4-5-20250929").';
        }

        [$provider] = explode(':', $brain, 2);
        $standardProviders = ['anthropic', 'openai', 'gemini', 'groq', 'xai', 'openrouter', 'deepseek', 'mistral', 'ollama'];

        if (!in_array($provider, $standardProviders)) {
            $integration = IntegrationSetting::where('integration_id', $provider)
                ->where('enabled', true)
                ->first();

            if (!$integration) {
                return "AI model provider '{$provider}' is not configured or enabled.";
            }
        }

        // Spawn approval for non-autonomous agents
        $mode = $this->agent->behavior_mode ?? 'autonomous';
        if ($mode !== 'autonomous') {
            $approval = ApprovalRequest::create([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'type' => 'spawn',
                'title' => "Spawn agent: {$name}",
                'description' => "{$this->agent->name} wants to create a new {$agentType} agent named {$name}",
                'requester_id' => $this->agent->id,
                'status' => 'pending',
                'tool_execution_context' => [
                    'tool_slug' => 'manage_agent',
                    'parameters' => $request->toArray(),
                ],
            ]);

            if ($this->agent->must_wait_for_approval) {
                $this->agent->update(['awaiting_approval_id' => $approval->id]);

                return "Spawn request requires approval. An approval request has been created (ID: {$approval->id}). " .
                    "You are configured to wait for approvals. Execution will pause after your response.";
            }

            return "Spawn request requires approval. An approval request has been created (ID: {$approval->id}). " .
                "You can call wait_for_approval with this ID to pause until it's decided, or continue working.";
        }

        $agent = User::create([
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'type' => 'agent',
            'agent_type' => $agentType,
            'brain' => $brain,
            'status' => 'idle',
            'presence' => 'online',
            'is_ephemeral' => $request['isEphemeral'] ?? false,
            'behavior_mode' => $request['behavior'] ?? 'autonomous',
            'current_task' => null,
            'manager_id' => $this->agent->id,
            'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
        ]);

        // Parse identity content from JSON string
        $identityContent = [];
        if (!empty($request['identity'])) {
            $decoded = json_decode($request['identity'], true);
            if (is_array($decoded)) {
                $identityContent = $decoded;
            }
        }

        $agentFolder = $this->agentDocumentService->createAgentDocumentStructure($agent, $identityContent);
        $agent->update(['docs_folder_id' => $agentFolder->id]);

        // Generate procedural avatar
        $this->agentAvatarService->generate($agent);

        // Create DM channel between creator and new agent
        $dmChannel = Channel::create([
            'id' => Str::uuid()->toString(),
            'name' => 'DM: ' . $this->agent->name . ' ↔ ' . $agent->name,
            'type' => 'dm',
            'is_ephemeral' => false,
            'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
        ]);

        ChannelMember::create([
            'channel_id' => $dmChannel->id,
            'user_id' => $this->agent->id,
        ]);
        ChannelMember::create([
            'channel_id' => $dmChannel->id,
            'user_id' => $agent->id,
        ]);

        // Add to #general
        $generalChannel = Channel::forWorkspace()->where('name', 'general')->first();
        if ($generalChannel) {
            ChannelMember::create([
                'channel_id' => $generalChannel->id,
                'user_id' => $agent->id,
            ]);
        }

        return "Agent created: {$agent->name} (ID: {$agent->id}, brain: {$agent->brain}, behavior: {$agent->behavior_mode})";
    }

    private function update(Request $request): string
    {
        $agentId = $request['agentId'] ?? null;
        if (!$agentId) {
            return 'agentId is required.';
        }

        $target = User::where('type', 'agent')->where('workspace_id', $this->agent->workspace_id)->find($agentId);
        if (!$target) {
            return "Agent not found: {$agentId}";
        }

        // Self-escalation guard
        if ($this->agent->id === $agentId) {
            if (isset($request['behaviorMode'])) {
                $modes = ['strict' => 0, 'supervised' => 1, 'autonomous' => 2];
                $currentLevel = $modes[$this->agent->behavior_mode ?? 'autonomous'] ?? 2;
                $newLevel = $modes[$request['behaviorMode']] ?? 2;
                if ($newLevel > $currentLevel) {
                    return 'Self-escalation denied: you cannot set your own behavior mode to a less restrictive level.';
                }
            }
            if (isset($request['mustWaitForApproval']) && $request['mustWaitForApproval'] === 'false') {
                if ($this->agent->must_wait_for_approval) {
                    return 'Self-escalation denied: you cannot disable your own approval requirement.';
                }
            }
        }

        $updates = [];

        if (isset($request['name'])) {
            $updates['name'] = $request['name'];
        }
        if (isset($request['brain'])) {
            if (!str_contains($request['brain'], ':')) {
                return 'Invalid brain format. Expected "provider:model".';
            }
            $updates['brain'] = $request['brain'];
        }
        if (isset($request['status'])) {
            $updates['status'] = $request['status'];
        }
        if (isset($request['behaviorMode'])) {
            $updates['behavior_mode'] = $request['behaviorMode'];
        }
        if (isset($request['mustWaitForApproval'])) {
            $updates['must_wait_for_approval'] = filter_var($request['mustWaitForApproval'], FILTER_VALIDATE_BOOLEAN);
        }

        if (empty($updates)) {
            return 'No fields to update. Provide at least one of: name, brain, status, behaviorMode, mustWaitForApproval.';
        }

        $target->update($updates);

        return "Agent updated: {$target->name} (ID: {$target->id})";
    }

    private function delete(Request $request): string
    {
        $agentId = $request['agentId'] ?? null;
        if (!$agentId) {
            return 'agentId is required.';
        }

        // Self-deletion guard
        if ($this->agent->id === $agentId) {
            return 'Self-deletion denied: you cannot delete yourself.';
        }

        $target = User::where('type', 'agent')->where('workspace_id', $this->agent->workspace_id)->find($agentId);
        if (!$target) {
            return "Agent not found: {$agentId}";
        }

        $name = $target->name;
        $this->agentDocumentService->deleteAgentDocumentStructure($target);
        $target->delete();

        return "Agent deleted: {$name}";
    }

    private function readIdentityFile(Request $request): string
    {
        $agentId = $request['agentId'] ?? null;
        $fileType = $request['fileType'] ?? null;

        if (!$agentId || !$fileType) {
            return 'Required: agentId, fileType (e.g., IDENTITY, SOUL, USER, AGENTS, TOOLS, HEARTBEAT, BOOTSTRAP, MEMORY).';
        }

        $target = User::where('type', 'agent')->where('workspace_id', $this->agent->workspace_id)->find($agentId);
        if (!$target) {
            return "Agent not found: {$agentId}";
        }

        $file = $this->agentDocumentService->getIdentityFile($target, $fileType);
        if (!$file) {
            return "Identity file not found: {$fileType}";
        }

        return "File: {$file->title}\nUpdated: {$file->updated_at}\n\n{$file->content}";
    }

    private function updateIdentityFile(Request $request): string
    {
        $agentId = $request['agentId'] ?? null;
        $fileType = $request['fileType'] ?? null;
        $content = $request['content'] ?? null;

        if (!$agentId || !$fileType || $content === null) {
            return 'Required: agentId, fileType, content.';
        }

        $target = User::where('type', 'agent')->where('workspace_id', $this->agent->workspace_id)->find($agentId);
        if (!$target) {
            return "Agent not found: {$agentId}";
        }

        $file = $this->agentDocumentService->updateIdentityFile($target, $fileType, $content);
        if (!$file) {
            return "Failed to update identity file: {$fileType}";
        }

        return "Identity file updated: {$file->title} for agent {$target->name}";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("Action: 'create', 'update', 'delete', 'read_identity_file', 'update_identity_file'")
                ->required(),
            'agentId' => $schema
                ->string()
                ->description('Agent UUID. Required for update, delete, read_identity_file, update_identity_file.'),
            'name' => $schema
                ->string()
                ->description('Agent name. Required for create.'),
            'agentType' => $schema
                ->string()
                ->description("Agent type. Required for create. One of: general, writer, analyst, creative, researcher, coder, coordinator, workspace-manager."),
            'brain' => $schema
                ->string()
                ->description('AI model in "provider:model" format (e.g., "anthropic:claude-sonnet-4-5-20250929"). Required for create.'),
            'behavior' => $schema
                ->string()
                ->description("Behavior mode for create: 'autonomous', 'supervised', or 'strict'. Default: autonomous."),
            'isEphemeral' => $schema
                ->boolean()
                ->description('Whether agent is ephemeral (temporary). Default: false.'),
            'identity' => $schema
                ->string()
                ->description('JSON string with identity file contents, e.g. {"IDENTITY":"...","SOUL":"..."}'),
            'status' => $schema
                ->string()
                ->description("Agent status for update: 'idle', 'working', 'offline', 'awaiting_approval'."),
            'behaviorMode' => $schema
                ->string()
                ->description("Behavior mode for update: 'autonomous', 'supervised', or 'strict'."),
            'mustWaitForApproval' => $schema
                ->string()
                ->description("Whether agent must wait for approval: 'true' or 'false'."),
            'fileType' => $schema
                ->string()
                ->description('Identity file type: IDENTITY, SOUL, USER, AGENTS, TOOLS, HEARTBEAT, BOOTSTRAP, or MEMORY.'),
            'content' => $schema
                ->string()
                ->description('Content for update_identity_file.'),
        ];
    }
}

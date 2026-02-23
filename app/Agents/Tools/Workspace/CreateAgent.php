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

class CreateAgent implements Tool
{
    public function __construct(
        private User $agent,
        private AgentDocumentService $agentDocumentService,
        private AgentAvatarService $agentAvatarService,
    ) {}

    public function description(): string
    {
        return 'Create a new agent in the workspace with specified name, type, and brain model.';
    }

    public function handle(Request $request): string
    {
        try {
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
                    'id' => Str::uuid()->toString(),
                    'type' => 'spawn',
                    'title' => "Spawn agent: {$name}",
                    'description' => "{$this->agent->name} wants to create a new {$agentType} agent named {$name}",
                    'requester_id' => $this->agent->id,
                    'status' => 'pending',
                    'tool_execution_context' => [
                        'tool_slug' => 'create_agent',
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

            $identityContent = $request['identity'] ?? [];

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
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->description('Agent name.')
                ->required(),
            'agentType' => $schema
                ->string()
                ->description('Agent type. One of: general, writer, analyst, creative, researcher, coder, coordinator, workspace-manager.')
                ->required(),
            'brain' => $schema
                ->string()
                ->description('AI model in "provider:model" format (e.g., "anthropic:claude-sonnet-4-5-20250929").')
                ->required(),
            'behavior' => $schema
                ->string()
                ->description('Behavior mode: "autonomous", "supervised", or "strict". Default: autonomous.'),
            'isEphemeral' => $schema
                ->boolean()
                ->description('Whether agent is ephemeral (temporary). Default: false.'),
            'identity' => $schema
                ->object()
                ->description('Identity file contents, e.g. {"IDENTITY":"...","SOUL":"..."}.'),
        ];
    }
}
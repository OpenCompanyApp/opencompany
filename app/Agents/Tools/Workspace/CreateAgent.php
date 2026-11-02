<?php

namespace App\Agents\Tools\Workspace;

use App\Agents\Providers\AgentBrainValidator;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use App\Services\AgentAvatarService;
use App\Services\AgentDocumentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateAgent implements Tool
{
    public function __construct(
        private User $agent,
        private AgentDocumentService $agentDocumentService,
        private AgentAvatarService $agentAvatarService,
        private AgentBrainValidator $brainValidator,
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

            try {
                $this->brainValidator->validate($brain, $this->agent->workspace_id ?? workspace()->id);
            } catch (InvalidArgumentException $e) {
                return "{$e->getMessage()} Example: {$this->brainValidator->example()}.";
            }

            // No separate spawn approval: create_agent is a write tool, so the
            // policy model already requires approval (or an admin grant) before
            // this handler runs.
            $agent = User::create([
                'id' => Str::uuid()->toString(),
                'name' => $name,
                'type' => 'agent',
                'agent_type' => $agentType,
                'brain' => $brain,
                'status' => 'idle',
                'presence' => 'online',
                'is_ephemeral' => $request['isEphemeral'] ?? false,
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

            return "Agent created: {$agent->name} (ID: {$agent->id}, brain: {$agent->brain})";
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
                ->description('AI model in "provider:model" format.')
                ->required(),
            'isEphemeral' => $schema
                ->boolean()
                ->description('Whether agent is ephemeral (temporary). Default: false.'),
            'identity' => $schema
                ->object()
                ->description('Identity file contents, e.g. {"IDENTITY":"...","INSTRUCTIONS":"...","MEMORY":"..."}.'),
        ];
    }
}

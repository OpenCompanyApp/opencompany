<?php

namespace App\Domain\Agents\Application;

use App\Agents\Providers\AgentBrainValidator;
use App\Domain\Collaboration\Application\CreateDirectMessageThread;
use App\Domain\Knowledge\Application\CreateAgentIdentityTree;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentAvatarService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Provisions a runnable agent inside a workspace.
 *
 * This use case owns the agent-lifecycle transaction. It keeps the ordering
 * explicit: validate provider/model first, create the agent record, provision
 * prompt documents, generate avatar, and create the initial collaboration
 * surfaces required by the response pipeline.
 */
class CreateAgent
{
    public function __construct(
        private AgentBrainValidator $brainValidator,
        private CreateAgentIdentityTree $identityTree,
        private AgentAvatarService $avatarService,
        private CreateDirectMessageThread $directMessages,
    ) {}

    public function handle(Workspace $workspace, User $creator, CreateAgentInput $input): User
    {
        $this->brainValidator->validate($input->brain, $workspace->id);

        $agent = DB::transaction(function () use ($workspace, $creator, $input): User {
            $agent = User::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $workspace->id,
                'name' => $input->name,
                'type' => 'agent',
                'agent_type' => $input->agentType,
                'brain' => $input->brain,
                'status' => 'idle',
                'presence' => 'online',
                'is_ephemeral' => $input->isEphemeral,
                'current_task' => $input->task,
                'behavior_mode' => $input->behavior,
                'manager_id' => $input->managerId ?? $creator->id,
            ]);

            $agentFolder = $this->identityTree->handle($agent, $input->identity);
            $agent->update(['docs_folder_id' => $agentFolder->id]);

            $this->directMessages->handle($workspace, $creator, $agent);
            $this->joinGeneralChannel($workspace, $agent);

            return $agent->fresh();
        });

        try {
            $this->avatarService->generate($agent);
        } catch (\Throwable $e) {
            Log::warning('Agent avatar generation failed after provisioning', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $agent->fresh();
    }

    private function joinGeneralChannel(Workspace $workspace, User $agent): void
    {
        $generalChannel = Channel::query()
            ->where('workspace_id', $workspace->id)
            ->where('name', 'general')
            ->first();

        if (! $generalChannel) {
            return;
        }

        ChannelMember::firstOrCreate([
            'channel_id' => $generalChannel->id,
            'user_id' => $agent->id,
        ]);
    }
}

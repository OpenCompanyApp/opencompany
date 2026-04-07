<?php

namespace Tests\Feature\Agents;

use App\Agents\OpenCompanyAgent;
use App\Jobs\IndexDocumentJob;
use App\Models\Channel;
use App\Models\Document;
use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class OpenCompanyAgentMemoryTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private AgentDocumentService $docService;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([IndexDocumentJob::class]);

        $this->agent = User::factory()->agent()->create([
            'name' => 'test-agent',
            'brain' => 'openai:gpt-4o',
        ]);

        $this->docService = app(AgentDocumentService::class);
        $agentFolder = $this->docService->createAgentDocumentStructure($this->agent, [
            'MEMORY' => "# Memory\n\n## Core Knowledge\n\nRemember: user prefers dark mode.",
        ]);
        $this->agent->update(['docs_folder_id' => $agentFolder->id]);
    }

    private function createChannel(string $type, array $members = []): Channel
    {
        $creator = User::factory()->create();
        $channel = Channel::factory()->state(['type' => $type])->create([
            'creator_id' => $creator->id,
        ]);

        foreach ($members as $member) {
            $channel->users()->attach($member->id);
        }

        return $channel;
    }

    public function test_memory_md_loaded_in_dm_channel(): void
    {
        $channel = $this->createChannel('dm');
        $agentInstance = OpenCompanyAgent::for($this->agent, $channel->id);

        $instructions = $agentInstance->instructions();

        $this->assertStringContainsString('MEMORY.md', $instructions);
        $this->assertStringContainsString('user prefers dark mode', $instructions);
    }

    public function test_memory_md_loaded_in_agent_channel(): void
    {
        $channel = $this->createChannel('agent');
        $agentInstance = OpenCompanyAgent::for($this->agent, $channel->id);

        $instructions = $agentInstance->instructions();

        $this->assertStringContainsString('MEMORY.md', $instructions);
        $this->assertStringContainsString('user prefers dark mode', $instructions);
    }

    public function test_memory_md_excluded_in_public_channel(): void
    {
        $channel = $this->createChannel('public');
        $agentInstance = OpenCompanyAgent::for($this->agent, $channel->id);

        $instructions = $agentInstance->instructions();

        $this->assertStringNotContainsString('user prefers dark mode', $instructions);
    }

    public function test_memory_md_included_in_external_channel(): void
    {
        $channel = $this->createChannel('external');
        $agentInstance = OpenCompanyAgent::for($this->agent, $channel->id);

        $instructions = $agentInstance->instructions();

        $this->assertStringContainsString('user prefers dark mode', $instructions);
    }

    public function test_memory_prompt_included_in_private_channel(): void
    {
        $channel = $this->createChannel('dm');
        $agentInstance = OpenCompanyAgent::for($this->agent, $channel->id);

        $instructions = $agentInstance->instructions();

        $this->assertStringContainsString('## Memory System', $instructions);
        $this->assertStringContainsString('save_memory', $instructions);
        $this->assertStringContainsString('recall_memory', $instructions);
        $this->assertStringContainsString('edit_memory', $instructions);
        $this->assertStringContainsString('forget_memory', $instructions);
    }

    public function test_memory_prompt_excluded_in_public_channel(): void
    {
        $channel = $this->createChannel('public');
        $agentInstance = OpenCompanyAgent::for($this->agent, $channel->id);

        $instructions = $agentInstance->instructions();

        $this->assertStringNotContainsString('## Memory System', $instructions);
    }

    public function test_identity_and_instructions_loaded(): void
    {
        $channel = $this->createChannel('dm');
        $agentInstance = OpenCompanyAgent::for($this->agent, $channel->id);

        $instructions = $agentInstance->instructions();

        // IDENTITY.md and INSTRUCTIONS.md should be loaded
        $this->assertStringContainsString('IDENTITY.md', $instructions);
        $this->assertStringContainsString('INSTRUCTIONS.md', $instructions);
    }

    public function test_peer_cards_loaded_for_channel_participants(): void
    {
        $human = User::factory()->create(['name' => 'Rutger']);
        $otherAgent = User::factory()->agent()->create(['name' => 'Atlas']);

        // Create peer memories
        $this->docService->savePeerMemory($this->agent, $human->id, 'user', 'Birthday March 15.');
        $this->docService->savePeerMemory($this->agent, $otherAgent->id, 'agent', 'Atlas is a coordinator.');

        $channel = $this->createChannel('dm', [$human, $otherAgent]);
        $agentInstance = OpenCompanyAgent::for($this->agent, $channel->id);

        $instructions = $agentInstance->instructions();

        // Both user and agent peer cards should be loaded
        $this->assertStringContainsString('Birthday March 15', $instructions);
        $this->assertStringContainsString('Atlas is a coordinator', $instructions);
    }

    public function test_user_peer_not_loaded_when_user_absent(): void
    {
        $human = User::factory()->create(['name' => 'Rutger']);
        $otherHuman = User::factory()->create(['name' => 'Sarah']);

        $this->docService->savePeerMemory($this->agent, $human->id, 'user', 'Rutger prefers async.');
        $this->docService->savePeerMemory($this->agent, $otherHuman->id, 'user', 'Sarah prefers meetings.');

        // Channel only has Rutger, not Sarah
        $channel = $this->createChannel('dm', [$human]);
        $agentInstance = OpenCompanyAgent::for($this->agent, $channel->id);

        $instructions = $agentInstance->instructions();

        $this->assertStringContainsString('Rutger prefers async', $instructions);
        $this->assertStringNotContainsString('Sarah prefers meetings', $instructions);
    }
}

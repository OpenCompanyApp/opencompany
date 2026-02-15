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
            'MEMORY' => "# Core Memory\n\nRemember: user prefers dark mode.",
        ]);
        $this->agent->update(['docs_folder_id' => $agentFolder->id]);
    }

    private function createChannel(string $type): Channel
    {
        $creator = User::factory()->create();

        return Channel::factory()->state(['type' => $type])->create([
            'creator_id' => $creator->id,
        ]);
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
    }

    public function test_memory_prompt_excluded_in_public_channel(): void
    {
        $channel = $this->createChannel('public');
        $agentInstance = OpenCompanyAgent::for($this->agent, $channel->id);

        $instructions = $agentInstance->instructions();

        $this->assertStringNotContainsString('## Memory System', $instructions);
    }
}

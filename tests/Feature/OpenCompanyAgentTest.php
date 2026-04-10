<?php

namespace Tests\Feature;

use App\Agents\OpenCompanyAgent;
use App\Jobs\IndexDocumentJob;
use App\Models\Channel;
use App\Models\Document;
use App\Models\Task;
use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpenCompanyAgentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([IndexDocumentJob::class]);
    }

    public function test_instructions_assembled_from_identity_files(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'agent_type' => 'coder',
            'brain' => 'codex:gpt-5.3-codex',
        ]);

        // Create identity document structure
        $docService = app(AgentDocumentService::class);
        $folder = $docService->createAgentDocumentStructure($agent, [
            'IDENTITY' => "# Identity\n\n- **Name**: TestBot\n- **Type**: Coder",
            'INSTRUCTIONS' => "# Operating Instructions\n\nBe helpful and accurate.",
        ]);
        $agent->update(['docs_folder_id' => $folder->id]);

        $agentInstance = OpenCompanyAgent::for($agent, 'channel-1');

        $instructions = $agentInstance->instructions();

        $this->assertStringContainsString('IDENTITY.md', $instructions);
        $this->assertStringContainsString('TestBot', $instructions);
        $this->assertStringContainsString('INSTRUCTIONS.md', $instructions);
        $this->assertStringContainsString('Be helpful and accurate', $instructions);
        $this->assertStringContainsString('## Tools', $instructions);
    }

    public function test_fallback_instructions_when_no_documents(): void
    {
        $agent = User::factory()->create([
            'name' => 'TestAgent',
            'type' => 'agent',
            'brain' => 'codex:gpt-5.3-codex',
        ]);

        $agentInstance = OpenCompanyAgent::for($agent, 'channel-1');

        $instructions = $agentInstance->instructions();

        $this->assertStringContainsString('TestAgent', $instructions);
        $this->assertStringContainsString('helpful AI assistant', $instructions);
    }

    public function test_provider_and_model_resolved_from_brain(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'codex:gpt-5.3-codex',
        ]);

        $agentInstance = OpenCompanyAgent::for($agent, 'channel-1');

        $this->assertEquals('codex', $agentInstance->provider());
        $this->assertEquals('gpt-5.3-codex', $agentInstance->model());
    }

    public function test_tools_returned(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'codex:gpt-5.3-codex',
        ]);

        $agentInstance = OpenCompanyAgent::for($agent, 'channel-1');
        $tools = iterator_to_array($agentInstance->tools());

        $registry = app(\App\Agents\Tools\ToolRegistry::class);
        $expectedToolCount = count($registry->getToolsForAgent($agent));
        $this->assertCount($expectedToolCount, $tools);
    }

    public function test_fake_prevents_real_api_calls(): void
    {
        OpenCompanyAgent::fake(['Hello! I am a test response.']);

        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'codex:gpt-5.3-codex',
        ]);

        $agentInstance = OpenCompanyAgent::for($agent, 'channel-1');
        $response = $agentInstance->prompt('Test message');

        $this->assertEquals('Hello! I am a test response.', $response->text);

        OpenCompanyAgent::assertPrompted(fn ($prompt) => $prompt->prompt === 'Test message');
    }

    public function test_system_prompts_split_stable_and_runtime_context(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'codex:gpt-5.3-codex',
        ]);
        $channel = Channel::factory()->create(['type' => 'dm']);

        $task = Task::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Test task',
            'description' => 'Do the thing',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $agent->id,
            'channel_id' => $channel->id,
            'started_at' => now(),
        ]);

        $agentInstance = OpenCompanyAgent::for($agent, $channel->id, $task->id);
        $prepared = $agentInstance->preparePrompt('User request here.');
        $systemPrompts = $agentInstance->systemPrompts();

        $this->assertSame('User request here.', $prepared);
        $this->assertCount(2, $systemPrompts);
        $this->assertStringNotContainsString('## Current Task', $systemPrompts[0]);
        $this->assertStringContainsString('## Current Time', $systemPrompts[1]);
        $this->assertStringContainsString('## Current Context', $systemPrompts[1]);
        $this->assertStringContainsString('## Current Task', $systemPrompts[1]);
    }
}

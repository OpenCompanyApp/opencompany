<?php

namespace Tests\Feature;

use App\Agents\OpenCompanyAgent;
use App\Models\Document;
use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenCompanyAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructions_assembled_from_identity_files(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'agent_type' => 'coder',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);

        // Create identity document structure
        $docService = app(AgentDocumentService::class);
        $folder = $docService->createAgentDocumentStructure($agent, [
            'IDENTITY' => "# Agent Identity\n\n- **Name**: TestBot\n- **Type**: Coder",
            'SOUL' => "# Core Values\n\nBe helpful and accurate.",
        ]);
        $agent->update(['docs_folder_id' => $folder->id]);

        $agentInstance = OpenCompanyAgent::for($agent, 'channel-1');

        $instructions = $agentInstance->instructions();

        $this->assertStringContainsString('IDENTITY.md', $instructions);
        $this->assertStringContainsString('TestBot', $instructions);
        $this->assertStringContainsString('SOUL.md', $instructions);
        $this->assertStringContainsString('Be helpful and accurate', $instructions);
        $this->assertStringContainsString('Available Tools', $instructions);
    }

    public function test_fallback_instructions_when_no_documents(): void
    {
        $agent = User::factory()->create([
            'name' => 'TestAgent',
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
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
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);

        $agentInstance = OpenCompanyAgent::for($agent, 'channel-1');

        $this->assertEquals('anthropic', $agentInstance->provider());
        $this->assertEquals('claude-sonnet-4-5-20250929', $agentInstance->model());
    }

    public function test_tools_returned(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);

        $agentInstance = OpenCompanyAgent::for($agent, 'channel-1');
        $tools = iterator_to_array($agentInstance->tools());

        $this->assertCount(3, $tools);
    }

    public function test_fake_prevents_real_api_calls(): void
    {
        OpenCompanyAgent::fake(['Hello! I am a test response.']);

        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);

        $agentInstance = OpenCompanyAgent::for($agent, 'channel-1');
        $response = $agentInstance->prompt('Test message');

        $this->assertEquals('Hello! I am a test response.', $response->text);

        OpenCompanyAgent::assertPrompted(fn ($prompt) => $prompt->prompt === 'Test message');
    }
}

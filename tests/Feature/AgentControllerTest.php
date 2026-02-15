<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['type' => 'human']);

        $this->agent = User::factory()->create([
            'name' => 'TestAgent',
            'type' => 'agent',
            'agent_type' => 'coder',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);

        // Create identity documents
        $docService = app(AgentDocumentService::class);
        $folder = $docService->createAgentDocumentStructure($this->agent, [
            'IDENTITY' => "# Agent Identity\n\n- **Name**: TestAgent\n- **Type**: Coder\n- **Emoji**: 🧪\n- **Vibe**: Test-driven and precise",
            'SOUL' => "# Core Values\n\nBe accurate and thorough.",
            'AGENTS' => "# Agent Network\n\nWork with other agents cooperatively.",
            'TOOLS' => "# Available Tools\n\nPrefer automated testing.",
        ]);
        $this->agent->update(['docs_folder_id' => $folder->id]);
    }

    public function test_show_returns_enriched_agent_data(): void
    {
        $response = $this->actingAs($this->user)->getJson("/api/agents/{$this->agent->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'type',
                'agentType',
                'status',
                'brain',
                'identity' => ['name', 'emoji', 'type', 'description'],
                'capabilities',
                'stats' => ['tasksCompleted', 'totalTasks', 'efficiency'],
                'tasks',
            ]);

        $data = $response->json();

        $this->assertEquals('TestAgent', $data['identity']['name']);
        $this->assertEquals('🧪', $data['identity']['emoji']);
        $this->assertEquals('coder', $data['identity']['type']);
        $this->assertEquals('Test-driven and precise', $data['identity']['description']);
        $registry = app(\App\Agents\Tools\ToolRegistry::class);
        $expectedToolCount = count($registry->getAllToolsMeta($this->agent));
        $this->assertCount($expectedToolCount, $data['capabilities']);
    }

    public function test_show_returns_task_stats(): void
    {
        // Create some tasks for the agent
        $requester = User::factory()->create(['type' => 'human']);

        \App\Models\Task::create([
            'id' => 'task-1',
            'title' => 'Completed task',
            'type' => 'custom',
            'status' => 'completed',
            'priority' => 'normal',
            'agent_id' => $this->agent->id,
            'requester_id' => $requester->id,
            'workspace_id' => $this->workspace->id,
        ]);

        \App\Models\Task::create([
            'id' => 'task-2',
            'title' => 'Active task',
            'type' => 'custom',
            'status' => 'active',
            'priority' => 'normal',
            'agent_id' => $this->agent->id,
            'requester_id' => $requester->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/agents/{$this->agent->id}");

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals(1, $data['stats']['tasksCompleted']);
        $this->assertEquals(2, $data['stats']['totalTasks']);
        $this->assertEquals(50, $data['stats']['efficiency']); // 1/2 = 50%
        $this->assertCount(2, $data['tasks']);
    }

    public function test_identity_files_endpoint(): void
    {
        $response = $this->actingAs($this->user)->getJson("/api/agents/{$this->agent->id}/identity");

        $response->assertOk();
        $files = $response->json();

        $this->assertCount(8, $files); // 8 identity file types

        $types = collect($files)->pluck('type')->sort()->values()->toArray();
        $this->assertEquals(
            ['AGENTS', 'BOOTSTRAP', 'HEARTBEAT', 'IDENTITY', 'MEMORY', 'SOUL', 'TOOLS', 'USER'],
            $types
        );
    }

    public function test_update_identity_file(): void
    {
        $newContent = "# Updated Soul\n\nBe creative and bold.";

        $response = $this->actingAs($this->user)->putJson("/api/agents/{$this->agent->id}/identity/SOUL", [
            'content' => $newContent,
        ]);

        $response->assertOk();
        $this->assertEquals($newContent, $response->json('content'));

        // Verify persistence via identity endpoint
        $verifyResponse = $this->actingAs($this->user)->getJson("/api/agents/{$this->agent->id}/identity");
        $soulFile = collect($verifyResponse->json())->firstWhere('type', 'SOUL');
        $this->assertStringContainsString('creative and bold', $soulFile['content']);
    }

    public function test_update_agent_status(): void
    {
        $response = $this->actingAs($this->user)->patchJson("/api/agents/{$this->agent->id}", [
            'status' => 'working',
        ]);

        $response->assertOk();
        $this->assertEquals('working', $response->json('status'));
    }

    public function test_show_returns_404_for_non_agent(): void
    {
        $human = User::factory()->create(['type' => 'human']);

        $response = $this->actingAs($this->user)->getJson("/api/agents/{$human->id}");

        $response->assertNotFound();
    }

    public function test_show_returns_404_for_missing_agent(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/agents/nonexistent-id');

        $response->assertNotFound();
    }
}

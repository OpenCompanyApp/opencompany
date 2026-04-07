<?php

namespace Tests\Feature\Services;

use App\Jobs\IndexDocumentJob;
use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AgentDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private AgentDocumentService $docService;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([IndexDocumentJob::class]);

        $this->agent = User::factory()->agent()->create(['name' => 'test-agent']);
        $this->docService = app(AgentDocumentService::class);
    }

    private function setupAgent(): void
    {
        $folder = $this->docService->createAgentDocumentStructure($this->agent);
        $this->agent->update(['docs_folder_id' => $folder->id]);
    }

    // --- updateMemoryIndex ---

    public function test_update_memory_index_adds_section_to_empty_memory(): void
    {
        $this->setupAgent();

        // Clear MEMORY.md content
        $this->docService->updateIdentityFile($this->agent, 'MEMORY', '');

        $this->docService->updateMemoryIndex($this->agent, 'Topics', 'vue3-migration', 'topics/vue3-migration.md');

        $memory = $this->docService->getIdentityFile($this->agent, 'MEMORY');
        $this->assertStringContainsString('## Topics', $memory->content);
        $this->assertStringContainsString('vue3-migration → [topics/vue3-migration.md]', $memory->content);
    }

    public function test_update_memory_index_appends_entry_to_existing_section(): void
    {
        $this->setupAgent();

        $this->docService->updateMemoryIndex($this->agent, 'Topics', 'vue3-migration', 'topics/vue3-migration.md');
        $this->docService->updateMemoryIndex($this->agent, 'Topics', 'deploy-checklist', 'topics/deploy-checklist.md');

        $memory = $this->docService->getIdentityFile($this->agent, 'MEMORY');
        $this->assertStringContainsString('vue3-migration', $memory->content);
        $this->assertStringContainsString('deploy-checklist', $memory->content);
    }

    public function test_update_memory_index_deduplicates_by_file_ref(): void
    {
        $this->setupAgent();

        $this->docService->updateMemoryIndex($this->agent, 'Topics', 'vue3', 'topics/vue3-migration.md');
        $this->docService->updateMemoryIndex($this->agent, 'Topics', 'vue3 updated', 'topics/vue3-migration.md');

        $memory = $this->docService->getIdentityFile($this->agent, 'MEMORY');
        // Should only have one entry for this fileRef
        $this->assertEquals(1, substr_count($memory->content, 'topics/vue3-migration.md'));
    }

    public function test_update_memory_index_adds_entry_without_file_ref(): void
    {
        $this->setupAgent();

        $this->docService->updateMemoryIndex($this->agent, 'People', 'Alice (CEO)', null);

        $memory = $this->docService->getIdentityFile($this->agent, 'MEMORY');
        $this->assertStringContainsString('- Alice (CEO)', $memory->content);
        $this->assertStringNotContainsString('→', $memory->content);
    }

    public function test_update_memory_index_preserves_other_sections(): void
    {
        $this->setupAgent();

        $this->docService->updateMemoryIndex($this->agent, 'Topics', 'vue3', 'topics/vue3-migration.md');
        $this->docService->updateMemoryIndex($this->agent, 'People', 'Alice', 'peers/users/alice.md');

        $memory = $this->docService->getIdentityFile($this->agent, 'MEMORY');
        $this->assertStringContainsString('## Topics', $memory->content);
        $this->assertStringContainsString('## People', $memory->content);
    }

    // --- removeMemoryIndexEntry ---

    public function test_remove_memory_index_entry_removes_from_correct_section(): void
    {
        $this->setupAgent();

        $this->docService->updateMemoryIndex($this->agent, 'Topics', 'vue3', 'topics/vue3-migration.md');
        $this->docService->updateMemoryIndex($this->agent, 'Topics', 'deploy', 'topics/deploy-checklist.md');
        $this->docService->updateMemoryIndex($this->agent, 'People', 'Alice', 'peers/users/alice.md');

        $this->docService->removeMemoryIndexEntry($this->agent, 'Topics', 'vue3');

        $memory = $this->docService->getIdentityFile($this->agent, 'MEMORY');
        $this->assertStringNotContainsString('vue3', $memory->content);
        // Other entries preserved
        $this->assertStringContainsString('deploy', $memory->content);
        $this->assertStringContainsString('Alice', $memory->content);
    }

    public function test_remove_memory_index_entry_noops_when_section_missing(): void
    {
        $this->setupAgent();

        // Should not throw or corrupt
        $this->docService->removeMemoryIndexEntry($this->agent, 'Nonexistent', 'entry');

        $memory = $this->docService->getIdentityFile($this->agent, 'MEMORY');
        $this->assertNotNull($memory);
    }

    public function test_remove_memory_index_entry_noops_when_entry_missing(): void
    {
        $this->setupAgent();

        $this->docService->updateMemoryIndex($this->agent, 'Topics', 'vue3', 'topics/vue3-migration.md');
        $contentBefore = $this->docService->getIdentityFile($this->agent, 'MEMORY')->content;

        $this->docService->removeMemoryIndexEntry($this->agent, 'Topics', 'nonexistent-entry');

        $contentAfter = $this->docService->getIdentityFile($this->agent, 'MEMORY')->content;
        $this->assertEquals($contentBefore, $contentAfter);
    }

    // --- listMemoryTopics ---

    public function test_list_memory_topics_returns_all_topic_files(): void
    {
        $this->setupAgent();

        $this->docService->saveMemoryTopic($this->agent, 'vue3-migration', 'Vue 3 content');
        $this->docService->saveMemoryTopic($this->agent, 'deploy-checklist', 'Deploy content');

        $topics = $this->docService->listMemoryTopics($this->agent);

        $this->assertCount(2, $topics);
        $titles = $topics->pluck('title')->sort()->values()->toArray();
        $this->assertEquals(['deploy-checklist.md', 'vue3-migration.md'], $titles);
    }

    // --- deleteAgentDocumentStructure ---

    public function test_delete_agent_document_structure_removes_all_files(): void
    {
        $this->setupAgent();

        $folderId = $this->agent->docs_folder_id;
        $this->assertNotNull($folderId);

        $this->docService->saveMemoryTopic($this->agent, 'test-topic', 'Content');
        $this->docService->savePeerMemory($this->agent, 'peer-123', 'user', 'Peer content');

        $this->docService->deleteAgentDocumentStructure($this->agent);

        // Agent folder and all children should be gone
        $this->assertDatabaseMissing('documents', ['id' => $folderId]);
    }
}

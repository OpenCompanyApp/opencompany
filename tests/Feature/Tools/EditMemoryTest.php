<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Memory\EditMemory;
use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\Memory\DocumentIndexingService;
use App\Services\Memory\MemoryScopeGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

class EditMemoryTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([IndexDocumentJob::class]);

        $this->agent = User::factory()->agent()->create(['name' => 'test-agent']);
    }

    private function createTopicFile(string $slug, string $content): Document
    {
        $docService = app(AgentDocumentService::class);
        $agentFolder = $docService->createAgentDocumentStructure($this->agent);
        $this->agent->update(['docs_folder_id' => $agentFolder->id]);

        return $docService->saveMemoryTopic($this->agent, $slug, $content);
    }

    private function createPeerFile(string $peerId, string $peerType, string $content): Document
    {
        $docService = app(AgentDocumentService::class);
        $agentFolder = $docService->createAgentDocumentStructure($this->agent);
        $this->agent->update(['docs_folder_id' => $agentFolder->id]);

        return $docService->savePeerMemory($this->agent, $peerId, $peerType, $content);
    }

    public function test_edit_topic_updates_content(): void
    {
        $this->createTopicFile('vue3-migration', 'Old content about Vue 3.');

        $docService = app(AgentDocumentService::class);
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('index')->once();

        $tool = new EditMemory($this->agent, $docService, $indexer);
        $result = $tool->handle(new Request([
            'target' => 'topic',
            'topic' => 'vue3-migration',
            'content' => 'Updated Vue 3 migration guide.',
        ]));

        $this->assertStringContainsString("updated", $result);

        $file = $docService->getMemoryTopicFile($this->agent, 'vue3-migration');
        $this->assertEquals('Updated Vue 3 migration guide.', $file->content);
    }

    public function test_edit_topic_not_found(): void
    {
        $docService = app(AgentDocumentService::class);
        $agentFolder = $docService->createAgentDocumentStructure($this->agent);
        $this->agent->update(['docs_folder_id' => $agentFolder->id]);

        $indexer = Mockery::mock(DocumentIndexingService::class);

        $tool = new EditMemory($this->agent, $docService, $indexer);
        $result = $tool->handle(new Request([
            'target' => 'topic',
            'topic' => 'nonexistent',
            'content' => 'New content.',
        ]));

        $this->assertStringContainsString('not found', $result);
    }

    public function test_edit_peer_updates_content(): void
    {
        $peerUser = User::factory()->create();
        $this->createPeerFile($peerUser->id, 'user', 'Old peer content.');

        $docService = app(AgentDocumentService::class);
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('index')->once();

        $tool = new EditMemory($this->agent, $docService, $indexer);
        $result = $tool->handle(new Request([
            'target' => 'peer',
            'peer_id' => $peerUser->id,
            'peer_type' => 'user',
            'content' => 'Updated peer notes.',
        ]));

        $this->assertStringContainsString('updated', $result);

        $file = $docService->getPeerMemory($this->agent, $peerUser->id, 'user');
        $this->assertEquals('Updated peer notes.', $file->content);
    }

    public function test_edit_peer_not_found(): void
    {
        $docService = app(AgentDocumentService::class);
        $agentFolder = $docService->createAgentDocumentStructure($this->agent);
        $this->agent->update(['docs_folder_id' => $agentFolder->id]);

        $indexer = Mockery::mock(DocumentIndexingService::class);

        $tool = new EditMemory($this->agent, $docService, $indexer);
        $result = $tool->handle(new Request([
            'target' => 'peer',
            'peer_id' => 'nonexistent-id',
            'peer_type' => 'user',
            'content' => 'New content.',
        ]));

        $this->assertStringContainsString('Error', $result);
    }

    public function test_edit_requires_target_and_content(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $docService = Mockery::mock(AgentDocumentService::class);

        $tool = new EditMemory($this->agent, $docService, $indexer);
        $result = $tool->handle(new Request([
            'target' => '',
            'content' => '',
        ]));

        $this->assertStringContainsString('Error', $result);
    }

    public function test_edit_invalid_peer_type(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $docService = Mockery::mock(AgentDocumentService::class);
        $docService->shouldReceive('getPeerMemory')->andReturn(null);

        $tool = new EditMemory($this->agent, $docService, $indexer);
        $result = $tool->handle(new Request([
            'target' => 'peer',
            'peer_id' => 'some-id',
            'peer_type' => 'invalid',
            'content' => 'Content.',
        ]));

        $this->assertStringContainsString('Error', $result);
    }

    public function test_edit_denied_in_group_channel(): void
    {
        $scopeGuard = Mockery::mock(MemoryScopeGuard::class);
        $scopeGuard->shouldReceive('canUseMemoryTools')
            ->once()
            ->andReturn(false);
        $scopeGuard->shouldReceive('denialMessage')
            ->once()
            ->with('edit_memory')
            ->andReturn('Memory tools are not available in group channels.');

        $docService = Mockery::mock(AgentDocumentService::class);
        $indexer = Mockery::mock(DocumentIndexingService::class);

        $tool = new EditMemory($this->agent, $docService, $indexer, $scopeGuard, 'public-channel-id');
        $result = $tool->handle(new Request([
            'target' => 'topic',
            'topic' => 'test',
            'content' => 'Content.',
        ]));

        $this->assertStringContainsString('not available', $result);
    }
}

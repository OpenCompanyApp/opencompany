<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Memory\SaveMemory;
use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\Memory\DocumentIndexingService;
use App\Services\Memory\MemoryScopeGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Ai\Tools\Request;
use Mockery;
use Prism\Prism\Embeddings\Response as EmbeddingResponse;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Embedding;
use Prism\Prism\ValueObjects\EmbeddingsUsage;
use Prism\Prism\ValueObjects\Meta;
use Tests\TestCase;

class SaveMemoryTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private AgentDocumentService $docService;

    private DocumentIndexingService $indexer;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([IndexDocumentJob::class]);

        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        $this->agent = User::factory()->agent()->create(['name' => 'test-agent']);
        $this->docService = app(AgentDocumentService::class);
        $this->indexer = app(DocumentIndexingService::class);
    }

    private function fakeEmbeddingResponse(int $count = 1): void
    {
        $responses = [];
        for ($i = 0; $i < $count; $i++) {
            $responses[] = new EmbeddingResponse(
                embeddings: [new Embedding(array_fill(0, 1536, 0.1 * ($i + 1)))],
                usage: new EmbeddingsUsage(tokens: 10),
                meta: new Meta(id: 'test', model: 'test'),
            );
        }
        Prism::fake($responses);
    }

    private function createAgentFolderStructure(): Document
    {
        $agentFolder = $this->docService->createAgentDocumentStructure($this->agent, [
            'MEMORY' => "# Memory\n\n## Core Knowledge\n\nInitial content.",
        ]);
        $this->agent->update(['docs_folder_id' => $agentFolder->id]);

        return $agentFolder;
    }

    public function test_save_to_daily_log(): void
    {
        $this->createAgentFolderStructure();
        $this->fakeEmbeddingResponse(1);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => 'User prefers dark mode in the editor.',
            'category' => 'preference',
            'target' => 'log',
        ]));

        $this->assertStringContainsString('recallable via recall_memory', $result);

        $today = now()->format('Y-m-d');
        $agentFolder = Document::find($this->agent->docs_folder_id);
        $memoryFolder = Document::where('parent_id', $agentFolder->id)
            ->where('title', 'memory')
            ->first();
        $logsFolder = Document::where('parent_id', $memoryFolder->id)
            ->where('title', 'logs')
            ->first();

        $logDoc = Document::where('parent_id', $logsFolder->id)
            ->where('title', "{$today}.md")
            ->first();

        $this->assertNotNull($logDoc);
        $this->assertStringContainsString('User prefers dark mode', $logDoc->content);
        $this->assertStringContainsString('[preference]', $logDoc->content);
    }

    public function test_save_to_core_memory(): void
    {
        $this->createAgentFolderStructure();
        $this->fakeEmbeddingResponse(1);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => 'The CEO is named Alice.',
            'category' => 'fact',
            'target' => 'core',
        ]));

        $this->assertStringContainsString('Core memory saved', $result);

        $memoryFile = $this->docService->getIdentityFile($this->agent, 'MEMORY');
        $this->assertNotNull($memoryFile);
        $this->assertStringContainsString('The CEO is named Alice.', $memoryFile->content);
        $this->assertStringContainsString('### fact', $memoryFile->content);
        $this->assertStringContainsString('Initial content.', $memoryFile->content);
    }

    public function test_save_to_topic(): void
    {
        $this->createAgentFolderStructure();
        $this->fakeEmbeddingResponse(1);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => '# Vue 3 Migration\n\nStep 1: Update composer.json...',
            'target' => 'topic',
            'topic' => 'vue3-migration',
        ]));

        $this->assertStringContainsString('topics/vue3-migration.md', $result);

        $topicFile = $this->docService->getMemoryTopicFile($this->agent, 'vue3-migration');
        $this->assertNotNull($topicFile);
        $this->assertStringContainsString('Vue 3 Migration', $topicFile->content);
    }

    public function test_save_to_peer(): void
    {
        $this->createAgentFolderStructure();
        $this->fakeEmbeddingResponse(1);

        $peerUser = User::factory()->create(['name' => 'Rutger']);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => 'Birthday March 15. Prefers async updates.',
            'target' => 'peer',
            'peer_id' => $peerUser->id,
            'peer_type' => 'user',
        ]));

        $this->assertStringContainsString('Peer memory saved', $result);
        $this->assertStringContainsString('Rutger', $result);

        $peerFile = $this->docService->getPeerMemory($this->agent, $peerUser->id, 'user');
        $this->assertNotNull($peerFile);
        $this->assertStringContainsString('Birthday March 15', $peerFile->content);
    }

    public function test_save_topic_requires_slug(): void
    {
        $this->createAgentFolderStructure();

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => 'Some content.',
            'target' => 'topic',
        ]));

        $this->assertStringContainsString('Error', $result);
        $this->assertStringContainsString('"topic" parameter is required', $result);
    }

    public function test_save_peer_requires_id_and_type(): void
    {
        $this->createAgentFolderStructure();

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => 'Some content.',
            'target' => 'peer',
        ]));

        $this->assertStringContainsString('Error', $result);
        $this->assertStringContainsString('"peer_id" and "peer_type"', $result);
    }

    public function test_save_unknown_target_returns_error(): void
    {
        $this->createAgentFolderStructure();

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => 'Some content.',
            'target' => 'unknown',
        ]));

        $this->assertStringContainsString('Unknown target', $result);
    }

    public function test_save_indexes_for_recall(): void
    {
        $this->createAgentFolderStructure();
        $this->fakeEmbeddingResponse(1);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $tool->handle(new Request([
            'content' => 'Important decision about API design.',
            'category' => 'decision',
        ]));

        $chunks = DocumentChunk::where('collection', 'memory')
            ->where('agent_id', $this->agent->id)
            ->get();

        $this->assertGreaterThanOrEqual(1, $chunks->count());
    }

    public function test_save_without_agent_folder_returns_error(): void
    {
        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => 'This should fail gracefully.',
        ]));

        $this->assertStringContainsString('Error', $result);
    }

    public function test_save_defaults_to_log_target(): void
    {
        $this->createAgentFolderStructure();
        $this->fakeEmbeddingResponse(1);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => 'Default target test.',
        ]));

        $this->assertStringContainsString('recallable via recall_memory', $result);
    }

    public function test_save_appends_to_existing_daily_log(): void
    {
        $this->createAgentFolderStructure();
        $this->fakeEmbeddingResponse(2);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);

        $tool->handle(new Request([
            'content' => 'First memory entry.',
            'category' => 'fact',
            'target' => 'log',
        ]));

        $tool->handle(new Request([
            'content' => 'Second memory entry.',
            'category' => 'learning',
            'target' => 'log',
        ]));

        $today = now()->format('Y-m-d');
        $agentFolder = Document::find($this->agent->docs_folder_id);
        $memoryFolder = Document::where('parent_id', $agentFolder->id)
            ->where('title', 'memory')
            ->first();
        $logsFolder = Document::where('parent_id', $memoryFolder->id)
            ->where('title', 'logs')
            ->first();

        $logDoc = Document::where('parent_id', $logsFolder->id)
            ->where('title', "{$today}.md")
            ->first();

        $this->assertNotNull($logDoc);
        $this->assertStringContainsString('First memory entry.', $logDoc->content);
        $this->assertStringContainsString('Second memory entry.', $logDoc->content);
        $this->assertStringContainsString('---', $logDoc->content);

        $logCount = Document::where('parent_id', $logsFolder->id)
            ->where('title', "{$today}.md")
            ->count();
        $this->assertEquals(1, $logCount);
    }

    public function test_save_core_without_memory_file_returns_error(): void
    {
        $this->createAgentFolderStructure();

        $memoryFile = $this->docService->getIdentityFile($this->agent, 'MEMORY');
        $memoryFile->update(['is_system' => false]);
        $memoryFile->delete();

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => 'This should fail.',
            'target' => 'core',
        ]));

        $this->assertStringContainsString('Error', $result);
        $this->assertStringContainsString('MEMORY.md not found', $result);
    }

    public function test_save_with_empty_content(): void
    {
        $this->createAgentFolderStructure();
        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => '',
            'target' => 'log',
        ]));

        $this->assertStringContainsString('Error', $result);
        $this->assertStringContainsString('"content" is required', $result);
    }

    public function test_save_category_defaults_to_general(): void
    {
        $this->createAgentFolderStructure();
        $this->fakeEmbeddingResponse(1);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $tool->handle(new Request([
            'content' => 'No category specified.',
            'target' => 'log',
        ]));

        $today = now()->format('Y-m-d');
        $agentFolder = Document::find($this->agent->docs_folder_id);
        $memoryFolder = Document::where('parent_id', $agentFolder->id)
            ->where('title', 'memory')
            ->first();
        $logsFolder = Document::where('parent_id', $memoryFolder->id)
            ->where('title', 'logs')
            ->first();
        $logDoc = Document::where('parent_id', $logsFolder->id)
            ->where('title', "{$today}.md")
            ->first();

        $this->assertStringContainsString('[general]', $logDoc->content);
    }

    public function test_save_core_appends_multiple_entries(): void
    {
        $this->createAgentFolderStructure();
        $this->fakeEmbeddingResponse(2);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);

        $tool->handle(new Request([
            'content' => 'First core memory.',
            'category' => 'fact',
            'target' => 'core',
        ]));

        $tool->handle(new Request([
            'content' => 'Second core memory.',
            'category' => 'preference',
            'target' => 'core',
        ]));

        $memoryFile = $this->docService->getIdentityFile($this->agent, 'MEMORY');
        $this->assertStringContainsString('Initial content.', $memoryFile->content);
        $this->assertStringContainsString('First core memory.', $memoryFile->content);
        $this->assertStringContainsString('Second core memory.', $memoryFile->content);
    }

    public function test_save_denied_in_group_channel(): void
    {
        $scopeGuard = Mockery::mock(MemoryScopeGuard::class);
        $scopeGuard->shouldReceive('canUseMemoryTools')
            ->once()
            ->with($this->agent, 'public-channel-id')
            ->andReturn(false);
        $scopeGuard->shouldReceive('denialMessage')
            ->once()
            ->with('save_memory')
            ->andReturn('Memory tools are not available in group channels.');

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer, $scopeGuard, 'public-channel-id');
        $result = $tool->handle(new Request([
            'content' => 'Should not save.',
        ]));

        $this->assertStringContainsString('not available in group channels', $result);
    }
}

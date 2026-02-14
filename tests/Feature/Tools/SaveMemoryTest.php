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
use Illuminate\Support\Str;
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
        $agentsFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'agents',
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->agent->id,
        ]);

        $agentFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'test-agent',
            'parent_id' => $agentsFolder->id,
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->agent->id,
        ]);

        // Set agent's docs_folder_id
        $this->agent->update(['docs_folder_id' => $agentFolder->id]);

        // Create identity folder with MEMORY.md
        $identityFolder = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'identity',
            'parent_id' => $agentFolder->id,
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->agent->id,
        ]);

        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'MEMORY.md',
            'parent_id' => $identityFolder->id,
            'content' => "# Core Memory\n\nInitial content.",
            'is_folder' => false,
            'is_system' => true,
            'author_id' => $this->agent->id,
        ]);

        // Create memory folder for daily logs
        Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'memory',
            'parent_id' => $agentFolder->id,
            'is_folder' => true,
            'content' => '',
            'author_id' => $this->agent->id,
        ]);

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

        // Check that a daily log document was created
        $today = now()->format('Y-m-d');
        $agentFolder = Document::find($this->agent->docs_folder_id);
        $memoryFolder = Document::where('parent_id', $agentFolder->id)
            ->where('title', 'memory')
            ->first();

        $logDoc = Document::where('parent_id', $memoryFolder->id)
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

        // Check MEMORY.md was updated
        $memoryFile = $this->docService->getIdentityFile($this->agent, 'MEMORY');
        $this->assertNotNull($memoryFile);
        $this->assertStringContainsString('The CEO is named Alice.', $memoryFile->content);
        $this->assertStringContainsString('### fact', $memoryFile->content);
        // Original content preserved
        $this->assertStringContainsString('Initial content.', $memoryFile->content);
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

        // Check that chunks were created in the memory collection
        $chunks = DocumentChunk::where('collection', 'memory')
            ->where('agent_id', $this->agent->id)
            ->get();

        $this->assertGreaterThanOrEqual(1, $chunks->count());
    }

    public function test_save_without_agent_folder_returns_error(): void
    {
        // Agent has no docs_folder_id set — no folder structure
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

        // Default target is "log", so it should say recallable
        $this->assertStringContainsString('recallable via recall_memory', $result);
    }

    public function test_save_appends_to_existing_daily_log(): void
    {
        $this->createAgentFolderStructure();

        $this->fakeEmbeddingResponse(2);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);

        // First save
        $tool->handle(new Request([
            'content' => 'First memory entry.',
            'category' => 'fact',
            'target' => 'log',
        ]));

        // Second save — same day → should append
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

        $logDoc = Document::where('parent_id', $memoryFolder->id)
            ->where('title', "{$today}.md")
            ->first();

        $this->assertNotNull($logDoc);
        $this->assertStringContainsString('First memory entry.', $logDoc->content);
        $this->assertStringContainsString('Second memory entry.', $logDoc->content);
        $this->assertStringContainsString('---', $logDoc->content);

        // Only one log file for today, not two
        $logCount = Document::where('parent_id', $memoryFolder->id)
            ->where('title', "{$today}.md")
            ->count();
        $this->assertEquals(1, $logCount);
    }

    public function test_save_core_without_memory_file_returns_error(): void
    {
        $this->createAgentFolderStructure();

        // Delete MEMORY.md
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

        $this->fakeEmbeddingResponse(1);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => '',
            'target' => 'log',
        ]));

        // Should still save without crashing
        $this->assertStringContainsString('recallable via recall_memory', $result);
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
        $logDoc = Document::where('parent_id', $memoryFolder->id)
            ->where('title', "{$today}.md")
            ->first();

        $this->assertStringContainsString('[general]', $logDoc->content);
    }

    public function test_save_log_includes_timestamp(): void
    {
        $this->createAgentFolderStructure();

        $this->fakeEmbeddingResponse(1);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $tool->handle(new Request([
            'content' => 'Timestamped entry.',
            'target' => 'log',
        ]));

        $today = now()->format('Y-m-d');
        $agentFolder = Document::find($this->agent->docs_folder_id);
        $memoryFolder = Document::where('parent_id', $agentFolder->id)
            ->where('title', 'memory')
            ->first();
        $logDoc = Document::where('parent_id', $memoryFolder->id)
            ->where('title', "{$today}.md")
            ->first();

        // Should contain HH:MM time format
        $this->assertMatchesRegularExpression('/\d{2}:\d{2}/', $logDoc->content);
    }

    public function test_save_log_returns_filename_in_response(): void
    {
        $this->createAgentFolderStructure();

        $this->fakeEmbeddingResponse(1);

        $tool = new SaveMemory($this->agent, $this->docService, $this->indexer);
        $result = $tool->handle(new Request([
            'content' => 'Filename test.',
            'target' => 'log',
        ]));

        $today = now()->format('Y-m-d');
        $this->assertStringContainsString("{$today}.md", $result);
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
        $this->assertStringContainsString('### fact', $memoryFile->content);
        $this->assertStringContainsString('### preference', $memoryFile->content);
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

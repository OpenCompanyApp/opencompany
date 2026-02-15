<?php

namespace Tests\Feature\Services\Memory;

use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\Memory\ChunkingService;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Prism\Prism\Embeddings\Response as EmbeddingResponse;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Embedding;
use Prism\Prism\ValueObjects\EmbeddingsUsage;
use Prism\Prism\ValueObjects\Meta;
use Tests\TestCase;

class DocumentIndexingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentIndexingService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DocumentIndexingService::class);
        $this->user = User::factory()->create();
        // Prevent observer from dispatching jobs during direct service tests
        Bus::fake([IndexDocumentJob::class]);
    }

    private function fakeEmbeddingResponse(int $count = 1): void
    {
        $responses = [];
        for ($i = 0; $i < $count; $i++) {
            $embedding = array_fill(0, 1536, 0.1 * ($i + 1));
            $responses[] = new EmbeddingResponse(
                embeddings: [new Embedding($embedding)],
                usage: new EmbeddingsUsage(tokens: 10),
                meta: new Meta(id: 'test', model: 'test'),
            );
        }
        Prism::fake($responses);
    }

    private function createDocument(string $title = 'Test Doc', string $content = 'Some test content', ?string $parentId = null): Document
    {
        return Document::create([
            'id' => Str::uuid()->toString(),
            'title' => $title,
            'content' => $content,
            'parent_id' => $parentId,
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);
    }

    public function test_index_creates_chunks(): void
    {
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        $doc = $this->createDocument('Test', 'First paragraph with enough words to matter.');

        $this->fakeEmbeddingResponse(1);

        $this->service->index($doc);

        $chunks = DocumentChunk::where('document_id', $doc->id)->get();
        $this->assertGreaterThanOrEqual(1, $chunks->count());
        $this->assertEquals('general', $chunks->first()->collection);
        $this->assertNull($chunks->first()->agent_id);
        $this->assertEquals('Test', $chunks->first()->metadata['title']);
    }

    public function test_index_with_collection_and_agent(): void
    {
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        $doc = $this->createDocument('Memory Log', 'Agent memory content here.');
        $agent = User::factory()->agent()->create();

        $this->fakeEmbeddingResponse(1);

        $this->service->index($doc, 'memory', $agent->id);

        $chunk = DocumentChunk::where('document_id', $doc->id)->first();
        $this->assertEquals('memory', $chunk->collection);
        $this->assertEquals($agent->id, $chunk->agent_id);
    }

    public function test_index_replaces_existing_chunks(): void
    {
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        $doc = $this->createDocument('Test', 'Original content.');

        $this->fakeEmbeddingResponse(1);
        $this->service->index($doc);

        $this->assertEquals(1, DocumentChunk::where('document_id', $doc->id)->count());

        // Update and reindex
        $doc->update(['content' => 'Updated content.']);

        $this->fakeEmbeddingResponse(1);
        $this->service->index($doc);

        $chunks = DocumentChunk::where('document_id', $doc->id)->get();
        $this->assertEquals(1, $chunks->count());
        $this->assertStringContainsString('Updated', $chunks->first()->content);
    }

    public function test_index_empty_content_removes_chunks(): void
    {
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        $doc = $this->createDocument('Test', 'Some content.');

        $this->fakeEmbeddingResponse(1);
        $this->service->index($doc);

        $this->assertEquals(1, DocumentChunk::where('document_id', $doc->id)->count());

        $doc->update(['content' => '']);
        $this->service->index($doc);

        $this->assertEquals(0, DocumentChunk::where('document_id', $doc->id)->count());
    }

    public function test_deindex_removes_all_chunks(): void
    {
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        $doc = $this->createDocument('Test', 'Content to index.');

        $this->fakeEmbeddingResponse(1);
        $this->service->index($doc);

        $this->assertGreaterThan(0, DocumentChunk::where('document_id', $doc->id)->count());

        $this->service->deindex($doc);

        $this->assertEquals(0, DocumentChunk::where('document_id', $doc->id)->count());
    }

    public function test_content_hash_is_sha256(): void
    {
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        $doc = $this->createDocument('Test', 'Hashable content.');

        $this->fakeEmbeddingResponse(1);
        $this->service->index($doc);

        $chunk = DocumentChunk::where('document_id', $doc->id)->first();
        $this->assertEquals(64, strlen($chunk->content_hash));
        $this->assertEquals(hash('sha256', $chunk->content), $chunk->content_hash);
    }

    public function test_index_whitespace_only_content_removes_chunks(): void
    {
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        $doc = $this->createDocument('Test', 'Some content first.');

        $this->fakeEmbeddingResponse(1);
        $this->service->index($doc);

        $this->assertEquals(1, DocumentChunk::where('document_id', $doc->id)->count());

        // Update to whitespace-only content
        $doc->update(['content' => "   \n\n  \t  "]);
        $this->service->index($doc);

        $this->assertEquals(0, DocumentChunk::where('document_id', $doc->id)->count());
    }

    public function test_index_stores_metadata_with_title_and_date(): void
    {
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        $doc = $this->createDocument('My Important Doc', 'Content to index.');

        $this->fakeEmbeddingResponse(1);
        $this->service->index($doc);

        $chunk = DocumentChunk::where('document_id', $doc->id)->first();
        $this->assertArrayHasKey('title', $chunk->metadata);
        $this->assertArrayHasKey('updated_at', $chunk->metadata);
        $this->assertEquals('My Important Doc', $chunk->metadata['title']);
    }

    public function test_index_stores_updated_at_in_metadata(): void
    {
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        $doc = $this->createDocument('Dated Doc', 'Content with timestamp.');

        $this->fakeEmbeddingResponse(1);
        $this->service->index($doc);

        $chunk = DocumentChunk::where('document_id', $doc->id)->first();
        $this->assertNotNull($chunk->metadata['updated_at']);
        // ISO 8601 format check
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $chunk->metadata['updated_at']);
    }

    public function test_index_multiple_chunks_for_long_content(): void
    {
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);
        config(['memory.chunking.max_chunk_size' => 50]); // Very small to force multiple chunks

        // Create content with multiple paragraphs
        $paragraphs = [];
        for ($i = 0; $i < 20; $i++) {
            $paragraphs[] = "This is paragraph number {$i} with enough words to matter for chunking purposes and testing.";
        }
        $content = implode("\n\n", $paragraphs);

        // Pre-compute chunk count so we can fake the exact number of embeddings
        $chunker = app(ChunkingService::class);
        $expectedChunks = $chunker->chunk($content);
        $chunkCount = count($expectedChunks);

        $doc = $this->createDocument('Long Doc', $content);

        // embedBatch sends all chunks in ONE API call, so fake ONE response with $chunkCount embeddings
        $embeddings = [];
        for ($i = 0; $i < $chunkCount; $i++) {
            $embeddings[] = new Embedding(array_fill(0, 1536, 0.1 * ($i + 1)));
        }
        Prism::fake([
            new EmbeddingResponse(
                embeddings: $embeddings,
                usage: new EmbeddingsUsage(tokens: 10 * $chunkCount),
                meta: new Meta(id: 'test', model: 'test'),
            ),
        ]);

        $this->service->index($doc);

        $chunks = DocumentChunk::where('document_id', $doc->id)->orderBy('chunk_index')->get();
        $this->assertGreaterThan(1, $chunks->count());
        $this->assertEquals($chunkCount, $chunks->count());

        // Verify chunk_index is sequential
        foreach ($chunks as $i => $chunk) {
            $this->assertEquals($i, $chunk->chunk_index);
        }
    }

    public function test_deindex_on_never_indexed_document(): void
    {
        $doc = $this->createDocument('Never Indexed', 'Not indexed yet.');

        $this->assertEquals(0, DocumentChunk::where('document_id', $doc->id)->count());

        // Should not throw
        $this->service->deindex($doc);

        $this->assertEquals(0, DocumentChunk::where('document_id', $doc->id)->count());
    }

    public function test_index_embedding_failure_propagates(): void
    {
        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);

        $doc = $this->createDocument('Test', 'Content that will fail to embed.');

        // Don't fake Prism — let it try to call the real API without a key
        $this->expectException(\Throwable::class);

        $this->service->index($doc);
    }
}

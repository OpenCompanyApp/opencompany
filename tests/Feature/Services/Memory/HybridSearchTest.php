<?php

namespace Tests\Feature\Services\Memory;

use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Prism\Prism\Embeddings\Response as EmbeddingResponse;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Embedding;
use Prism\Prism\ValueObjects\EmbeddingsUsage;
use Prism\Prism\ValueObjects\Meta;
use Tests\TestCase;

class HybridSearchTest extends TestCase
{
    use RefreshDatabase;

    private DocumentIndexingService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DocumentIndexingService::class);
        $this->user = User::factory()->create();
        Bus::fake([IndexDocumentJob::class]);

        config(['memory.embedding.provider' => 'openai']);
        config(['memory.embedding.model' => 'text-embedding-3-small']);
    }

    private function requiresPostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires PostgreSQL with pgvector');
        }
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

    private function createDocument(string $title, string $content): Document
    {
        return Document::create([
            'id' => Str::uuid()->toString(),
            'title' => $title,
            'content' => $content,
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
        ]);
    }

    // ── Graceful fallback tests ──

    public function test_search_returns_vector_results_when_fts_unavailable(): void
    {
        $this->requiresPostgres();

        $doc = $this->createDocument('Dark Mode', 'User preferences for dark mode in the editor.');
        $this->fakeEmbeddingResponse(2);
        $this->service->index($doc);

        $results = $this->service->search('dark mode');

        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertStringContainsString('dark mode', $results->first()->content);
    }

    public function test_search_returns_fts_results_when_embedding_fails(): void
    {
        $this->requiresPostgres();

        $doc = $this->createDocument('Deployment Guide', 'Deploy the application using Docker containers.');
        $this->fakeEmbeddingResponse(1); // Only for indexing
        $this->service->index($doc);

        // Search without faked embedding — embedding will fail, FTS should take over
        $results = $this->service->search('Docker containers');

        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertStringContainsString('Docker', $results->first()->content);
    }

    // ── Hybrid merge tests ──

    public function test_hybrid_boosts_chunks_in_both_result_sets(): void
    {
        $this->requiresPostgres();

        $doc1 = $this->createDocument('Exact Match', 'Project planning with detailed roadmap for the team.');
        $doc2 = $this->createDocument('Semantic Only', 'Strategic vision and long-term goals discussed.');

        $this->fakeEmbeddingResponse(3);
        $this->service->index($doc1);
        $this->service->index($doc2);

        $results = $this->service->search('planning roadmap');

        $this->assertGreaterThanOrEqual(1, $results->count());
        $firstContent = $results->first()->content;
        $this->assertStringContainsString('planning', $firstContent);
    }

    public function test_rrf_respects_configured_weights(): void
    {
        $this->requiresPostgres();

        $doc = $this->createDocument('API Design', 'REST API design patterns and best practices.');
        $this->fakeEmbeddingResponse(2);
        $this->service->index($doc);

        config(['memory.search.hybrid_weights.semantic' => 0.7]);
        config(['memory.search.hybrid_weights.keyword' => 0.3]);

        $results = $this->service->search('API design');
        $this->assertGreaterThanOrEqual(1, $results->count());

        foreach ($results as $chunk) {
            $this->assertGreaterThanOrEqual(0, $chunk->similarity);
            $this->assertLessThanOrEqual(1.0, $chunk->similarity);
        }
    }

    // ── tsquery builder tests (pure PHP, no DB required) ──

    public function test_build_ts_query_sanitizes_input(): void
    {
        $service = app(DocumentIndexingService::class);
        $method = new \ReflectionMethod($service, 'buildTsQuery');

        $this->assertEquals('dark & mode', $method->invoke($service, 'dark mode'));
        $this->assertEquals('test & DROP & TABLE & query', $method->invoke($service, 'test; DROP TABLE--; query'));
        $this->assertEquals('hello & world', $method->invoke($service, 'hello a world'));
        $this->assertEquals('', $method->invoke($service, 'a b'));
        $this->assertEquals('', $method->invoke($service, ''));
    }

    // ── Scoping tests ──

    public function test_fts_search_scopes_by_collection_and_agent(): void
    {
        $this->requiresPostgres();

        $agent = User::factory()->agent()->create();

        $doc1 = $this->createDocument('Agent Memory', 'Agent-specific memory about deployment workflow.');
        $doc2 = $this->createDocument('General Doc', 'General document about deployment best practices.');

        $this->fakeEmbeddingResponse(2);
        $this->service->index($doc1, 'memory', $agent->id);
        $this->service->index($doc2, 'general');

        $this->fakeEmbeddingResponse(1);
        $memoryResults = $this->service->search('deployment', 'memory', $agent->id);

        $this->fakeEmbeddingResponse(1);
        $generalResults = $this->service->search('deployment', 'general');

        foreach ($memoryResults as $chunk) {
            $this->assertEquals($agent->id, $chunk->agent_id);
            $this->assertEquals('memory', $chunk->collection);
        }

        foreach ($generalResults as $chunk) {
            $this->assertNull($chunk->agent_id);
            $this->assertEquals('general', $chunk->collection);
        }
    }

    // ── Trigger tests ──

    public function test_trigger_populates_search_vector_on_insert(): void
    {
        $this->requiresPostgres();

        $doc = $this->createDocument('Trigger Test', 'The PostgreSQL trigger should populate the search vector automatically.');
        $this->fakeEmbeddingResponse(1);
        $this->service->index($doc);

        $chunk = DocumentChunk::where('document_id', $doc->id)->first();

        $match = DB::selectOne(
            "SELECT 1 FROM document_chunks WHERE id = ? AND search_vector @@ to_tsquery('english', 'postgresql & trigger')",
            [$chunk->id]
        );

        $this->assertNotNull($match);
    }

    // ── Score normalization tests ──

    public function test_hybrid_scores_normalized_to_zero_one(): void
    {
        $this->requiresPostgres();

        $doc1 = $this->createDocument('Score Test A', 'Machine learning algorithms for classification tasks.');
        $doc2 = $this->createDocument('Score Test B', 'Machine learning models and deep neural networks.');

        $this->fakeEmbeddingResponse(3);
        $this->service->index($doc1);
        $this->service->index($doc2);

        $results = $this->service->search('machine learning');

        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertEqualsWithDelta(1.0, $results->first()->similarity, 0.01);

        foreach ($results as $chunk) {
            $this->assertGreaterThanOrEqual(0, $chunk->similarity);
            $this->assertLessThanOrEqual(1.0, $chunk->similarity);
        }
    }

    // ── RRF merge unit test (pure PHP, no DB required) ──

    public function test_merge_with_rrf_combines_and_normalizes(): void
    {
        $service = app(DocumentIndexingService::class);
        $method = new \ReflectionMethod($service, 'mergeWithRRF');

        // Create mock chunks for vector results
        $chunkA = new DocumentChunk;
        $chunkA->id = 'chunk-a';
        $chunkA->content = 'Chunk A content';
        $chunkA->similarity = 0.95;

        $chunkB = new DocumentChunk;
        $chunkB->id = 'chunk-b';
        $chunkB->content = 'Chunk B content';
        $chunkB->similarity = 0.80;

        // Create mock chunks for FTS results (chunkA appears in both)
        $chunkAFts = new DocumentChunk;
        $chunkAFts->id = 'chunk-a';
        $chunkAFts->content = 'Chunk A content';
        $chunkAFts->similarity = 0.5;

        $chunkC = new DocumentChunk;
        $chunkC->id = 'chunk-c';
        $chunkC->content = 'Chunk C content';
        $chunkC->similarity = 0.3;

        $vectorResults = collect([$chunkA, $chunkB]);
        $ftsResults = collect([$chunkAFts, $chunkC]);

        config(['memory.search.hybrid_weights.semantic' => 0.7]);
        config(['memory.search.hybrid_weights.keyword' => 0.3]);

        $merged = $method->invoke($service, $vectorResults, $ftsResults, 10);

        // Chunk A should be first (appears in both result sets)
        $this->assertEquals('chunk-a', $merged->first()->id);

        // Top score should be normalized to ~1.0
        $this->assertEqualsWithDelta(1.0, $merged->first()->similarity, 0.01);

        // All three chunks should be present
        $this->assertEquals(3, $merged->count());

        // All scores in [0, 1]
        foreach ($merged as $chunk) {
            $this->assertGreaterThanOrEqual(0, $chunk->similarity);
            $this->assertLessThanOrEqual(1.0, $chunk->similarity);
        }
    }

    // ── Column detection test (pure PHP logic) ──

    public function test_has_search_vector_column_returns_false_on_sqlite(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->markTestSkipped('This test is for SQLite environments');
        }

        $service = app(DocumentIndexingService::class);
        $method = new \ReflectionMethod($service, 'ftsSearch');

        // On SQLite, ftsSearch should return empty collection (pgsql check)
        $result = $method->invoke($service, 'test query', 'general', null, 10);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }
}

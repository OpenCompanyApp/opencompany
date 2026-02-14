<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Memory\RecallMemory;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\Memory\DocumentIndexingService;
use App\Services\Memory\MemoryScopeGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

class RecallMemoryTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->agent()->create(['name' => 'test-agent']);
    }

    private function makeChunk(string $content, float $similarity = 0.85, ?string $date = null): DocumentChunk
    {
        $chunk = new DocumentChunk;
        $chunk->content = $content;
        $chunk->similarity = $similarity;
        $chunk->metadata = ['updated_at' => $date ?? now()->toDateTimeString()];

        return $chunk;
    }

    private function mockIndexer(Collection $results): DocumentIndexingService
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')
            ->once()
            ->andReturn($results);

        return $indexer;
    }

    private function mockDocService(): AgentDocumentService
    {
        return Mockery::mock(AgentDocumentService::class);
    }

    private function makeTool(DocumentIndexingService $indexer, ?AgentDocumentService $docService = null): RecallMemory
    {
        return new RecallMemory($this->agent, $indexer, $docService ?? $this->mockDocService());
    }

    // ── Search mode tests ──

    public function test_recall_returns_matching_memories(): void
    {
        $indexer = $this->mockIndexer(collect([
            $this->makeChunk('User prefers dark mode and vim keybindings.', 0.92),
        ]));

        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request([
            'query' => 'dark mode preference',
        ]));

        $this->assertStringContainsString('Found 1 memory', $result);
        $this->assertStringContainsString('dark mode', $result);
        $this->assertStringContainsString('92% match', $result);
    }

    public function test_recall_returns_no_results_message(): void
    {
        $indexer = $this->mockIndexer(collect());

        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request([
            'query' => 'something that does not exist',
        ]));

        $this->assertStringContainsString('No memories found', $result);
    }

    public function test_recall_passes_agent_scoping_to_search(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $collection, $agentId) {
                return $collection === 'memory' && $agentId === $this->agent->id;
            })
            ->andReturn(collect());

        $tool = $this->makeTool($indexer);
        $tool->handle(new Request(['query' => 'test']));
    }

    public function test_recall_respects_limit(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $collection, $agentId, $limit) {
                return $limit === 2;
            })
            ->andReturn(collect([
                $this->makeChunk('Memory one', 0.9),
                $this->makeChunk('Memory two', 0.8),
            ]));

        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request([
            'query' => 'memory',
            'limit' => 2,
        ]));

        $this->assertStringContainsString('Found 2 memory', $result);
    }

    public function test_recall_clamps_output_length(): void
    {
        $longContent = str_repeat('This is a long memory entry with lots of detail. ', 100);
        $indexer = $this->mockIndexer(collect([
            $this->makeChunk($longContent, 0.95),
            $this->makeChunk($longContent, 0.90),
            $this->makeChunk($longContent, 0.85),
            $this->makeChunk($longContent, 0.80),
            $this->makeChunk($longContent, 0.75),
            $this->makeChunk($longContent, 0.70),
        ]));

        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request([
            'query' => 'long memory',
        ]));

        // Total output should be clamped under 4500 chars (4000 content + header)
        $this->assertLessThan(4500, strlen($result));
    }

    public function test_recall_formats_multiple_results_with_separators(): void
    {
        $indexer = $this->mockIndexer(collect([
            $this->makeChunk('First memory about project planning.', 0.95, '2026-02-10 09:00:00'),
            $this->makeChunk('Second memory about deployment.', 0.80, '2026-02-11 14:30:00'),
        ]));

        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request(['query' => 'project']));

        $this->assertStringContainsString('Found 2 memory', $result);
        $this->assertStringContainsString('---', $result);
        $this->assertStringContainsString('95% match', $result);
        $this->assertStringContainsString('80% match', $result);
    }

    public function test_recall_default_limit_is_six(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $collection, $agentId, $limit) {
                return $limit === 6;
            })
            ->andReturn(collect());

        $tool = $this->makeTool($indexer);
        $tool->handle(new Request(['query' => 'test']));
    }

    public function test_recall_with_missing_metadata_date(): void
    {
        $chunk = new DocumentChunk;
        $chunk->content = 'Memory without date.';
        $chunk->similarity = 0.85;
        $chunk->metadata = []; // No updated_at

        $indexer = $this->mockIndexer(collect([$chunk]));

        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request(['query' => 'test']));

        $this->assertStringContainsString('unknown date', $result);
    }

    public function test_recall_with_zero_similarity(): void
    {
        $indexer = $this->mockIndexer(collect([
            $this->makeChunk('Zero similarity memory.', 0.0),
        ]));

        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request(['query' => 'test']));

        $this->assertStringContainsString('0% match', $result);
    }

    public function test_recall_truncates_when_exceeding_max_total(): void
    {
        // Each entry uses ~750 chars (700 snippet + date/similarity overhead)
        // maxTotal = 4000, so ~5 entries fit. Create 6 to verify truncation.
        $longSnippet = str_repeat('Memory content with enough detail to fill the snippet. ', 15);
        $chunks = [];
        for ($i = 0; $i < 6; $i++) {
            $chunks[] = $this->makeChunk($longSnippet, 0.95 - ($i * 0.02));
        }

        $indexer = $this->mockIndexer(collect($chunks));

        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request(['query' => 'test']));

        // Should include fewer than 6 results due to maxTotal cap
        $this->assertStringNotContainsString('Found 6 memory', $result);
        $this->assertLessThan(4500, strlen($result));
    }

    public function test_recall_uses_min_similarity_from_config(): void
    {
        config(['memory.search.min_similarity' => 0.75]);

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $collection, $agentId, $limit, $minSimilarity) {
                return $minSimilarity === 0.75;
            })
            ->andReturn(collect());

        $tool = $this->makeTool($indexer);
        $tool->handle(new Request(['query' => 'test']));
    }

    // ── Validation tests ──

    public function test_recall_requires_query_or_date(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request([]));

        $this->assertStringContainsString('Error', $result);
        $this->assertStringContainsString('query', $result);
        $this->assertStringContainsString('date', $result);
    }

    // ── Date browse mode tests ──

    public function test_recall_by_date_returns_log_content(): void
    {
        $content = "# Memory Log - 2026-02-10\n\n### [fact] 09:15\n\nUser prefers dark mode.";

        $doc = new Document;
        $doc->content = $content;

        $docService = Mockery::mock(AgentDocumentService::class);
        $docService->shouldReceive('getMemoryLog')
            ->once()
            ->with($this->agent, '2026-02-10')
            ->andReturn($doc);

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $tool = new RecallMemory($this->agent, $indexer, $docService);

        $result = $tool->handle(new Request(['date' => '2026-02-10']));

        $this->assertStringContainsString('Memory log for 2026-02-10', $result);
        $this->assertStringContainsString('User prefers dark mode', $result);
    }

    public function test_recall_by_date_not_found(): void
    {
        $docService = Mockery::mock(AgentDocumentService::class);
        $docService->shouldReceive('getMemoryLog')
            ->once()
            ->with($this->agent, '2026-01-01')
            ->andReturn(null);

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $tool = new RecallMemory($this->agent, $indexer, $docService);

        $result = $tool->handle(new Request(['date' => '2026-01-01']));

        $this->assertStringContainsString('No memory log found for 2026-01-01', $result);
    }

    public function test_recall_by_date_rejects_large_log_without_max_chars(): void
    {
        // Create content larger than DEFAULT_MAX_CHARS (4000)
        $content = str_repeat("### [fact] 09:00\n\nSome memory entry content here.\n\n---\n\n", 100);

        $doc = new Document;
        $doc->content = $content;

        $docService = Mockery::mock(AgentDocumentService::class);
        $docService->shouldReceive('getMemoryLog')
            ->once()
            ->andReturn($doc);

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $tool = new RecallMemory($this->agent, $indexer, $docService);

        $result = $tool->handle(new Request(['date' => '2026-02-10']));

        $this->assertStringContainsString('Too large to return in full', $result);
        $this->assertStringContainsString('max_chars', $result);
        $this->assertStringContainsString('characters', $result);
    }

    public function test_recall_by_date_truncates_with_explicit_max_chars(): void
    {
        // Create content larger than our max_chars
        $content = str_repeat("### [fact] 09:00\n\nSome memory entry content here.\n\n---\n\n", 100);

        $doc = new Document;
        $doc->content = $content;

        $docService = Mockery::mock(AgentDocumentService::class);
        $docService->shouldReceive('getMemoryLog')
            ->once()
            ->andReturn($doc);

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $tool = new RecallMemory($this->agent, $indexer, $docService);

        $result = $tool->handle(new Request(['date' => '2026-02-10', 'max_chars' => 500]));

        $this->assertStringContainsString('Showing first 500 chars', $result);
        $this->assertStringContainsString('Truncated', $result);
        $this->assertStringContainsString('chars omitted', $result);
    }

    public function test_recall_by_date_small_log_returns_full(): void
    {
        $content = "# Memory Log\n\nShort entry.";

        $doc = new Document;
        $doc->content = $content;

        $docService = Mockery::mock(AgentDocumentService::class);
        $docService->shouldReceive('getMemoryLog')
            ->once()
            ->andReturn($doc);

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $tool = new RecallMemory($this->agent, $indexer, $docService);

        $result = $tool->handle(new Request(['date' => '2026-02-10']));

        $this->assertStringContainsString('Short entry', $result);
        $this->assertStringNotContainsString('Truncated', $result);
        $this->assertStringNotContainsString('Too large', $result);
    }

    public function test_recall_by_date_empty_log_returns_not_found(): void
    {
        $doc = new Document;
        $doc->content = '   ';

        $docService = Mockery::mock(AgentDocumentService::class);
        $docService->shouldReceive('getMemoryLog')
            ->once()
            ->andReturn($doc);

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $tool = new RecallMemory($this->agent, $indexer, $docService);

        $result = $tool->handle(new Request(['date' => '2026-02-10']));

        $this->assertStringContainsString('No memory log found', $result);
    }

    public function test_recall_search_respects_custom_max_chars(): void
    {
        $indexer = $this->mockIndexer(collect([
            $this->makeChunk('First memory about project planning with lots of detail.', 0.95),
            $this->makeChunk('Second memory about deployment and infrastructure.', 0.90),
            $this->makeChunk('Third memory about user preferences and settings.', 0.85),
        ]));

        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request([
            'query' => 'test',
            'max_chars' => 200,
        ]));

        // With max_chars = 200, should truncate early
        $this->assertLessThan(500, strlen($result));
    }

    // ── Date validation tests ──

    public function test_recall_rejects_invalid_date_format(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request(['date' => 'not-a-date']));

        $this->assertStringContainsString('Invalid date format', $result);
    }

    public function test_recall_rejects_impossible_date(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request(['date' => '2026-02-30']));

        $this->assertStringContainsString('Invalid date format', $result);
    }

    public function test_recall_rejects_partial_date(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request(['date' => '2026-02']));

        $this->assertStringContainsString('Invalid date format', $result);
    }

    public function test_recall_rejects_date_with_path_traversal(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $tool = $this->makeTool($indexer);
        $result = $tool->handle(new Request(['date' => '../../etc/passwd']));

        $this->assertStringContainsString('Invalid date format', $result);
    }

    // ── Scope guard tests ──

    public function test_recall_denied_in_group_channel(): void
    {
        $scopeGuard = Mockery::mock(MemoryScopeGuard::class);
        $scopeGuard->shouldReceive('canUseMemoryTools')
            ->once()
            ->with($this->agent, 'public-channel-id')
            ->andReturn(false);
        $scopeGuard->shouldReceive('denialMessage')
            ->once()
            ->with('recall_memory')
            ->andReturn('Memory tools are not available in group channels.');

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $docService = $this->mockDocService();

        $tool = new RecallMemory($this->agent, $indexer, $docService, $scopeGuard, 'public-channel-id');
        $result = $tool->handle(new Request(['query' => 'test']));

        $this->assertStringContainsString('not available in group channels', $result);
    }
}

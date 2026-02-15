<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Docs\SearchDocuments;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Ai\Tools\Request;
use Mockery;
use Tests\TestCase;

class SearchDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTool(?User $agent = null, ?DocumentIndexingService $indexer = null): SearchDocuments
    {
        $agent = $agent ?? User::factory()->create(['type' => 'agent']);
        return new SearchDocuments($agent, app(AgentPermissionService::class), $indexer);
    }

    private function makeChunk(string $content, float $similarity = 0.85, string $title = 'Untitled'): DocumentChunk
    {
        $chunk = new DocumentChunk;
        $chunk->content = $content;
        $chunk->similarity = $similarity;
        $chunk->metadata = ['title' => $title, 'updated_at' => now()->toIso8601String()];

        return $chunk;
    }

    public function test_finds_documents_by_title(): void
    {
        $author = User::factory()->create();

        Document::create([
            'id' => 'doc-1',
            'title' => 'API Documentation',
            'content' => 'REST API endpoints for user management.',
            'author_id' => $author->id,
            'is_folder' => false,
            'workspace_id' => $this->workspace->id,
        ]);

        Document::create([
            'id' => 'doc-2',
            'title' => 'Meeting Notes',
            'content' => 'Discussed project timeline.',
            'author_id' => $author->id,
            'is_folder' => false,
            'workspace_id' => $this->workspace->id,
        ]);

        $tool = $this->makeTool();
        $request = new Request(['query' => 'API']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('API Documentation', $result);
        $this->assertStringContainsString('1 document(s)', $result);
        $this->assertStringNotContainsString('Meeting Notes', $result);
    }

    public function test_finds_documents_by_content(): void
    {
        $author = User::factory()->create();

        Document::create([
            'id' => 'doc-1',
            'title' => 'Notes',
            'content' => 'The deployment pipeline uses Docker containers.',
            'author_id' => $author->id,
            'is_folder' => false,
            'workspace_id' => $this->workspace->id,
        ]);

        $tool = $this->makeTool();
        $request = new Request(['query' => 'docker']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Notes', $result);
        $this->assertStringContainsString('Docker containers', $result);
    }

    public function test_returns_no_results_message(): void
    {
        $tool = $this->makeTool();
        $request = new Request(['query' => 'nonexistent-term-xyz']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('No documents found', $result);
    }

    public function test_respects_limit(): void
    {
        $author = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            Document::create([
                'id' => "doc-{$i}",
                'title' => "Test Document {$i}",
                'content' => 'Matching content for search test.',
                'author_id' => $author->id,
                'is_folder' => false,
                'workspace_id' => $this->workspace->id,
            ]);
        }

        $tool = $this->makeTool();
        $request = new Request(['query' => 'matching', 'limit' => 2]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('2 document(s)', $result);
    }

    public function test_excludes_folders(): void
    {
        $author = User::factory()->create();

        Document::create([
            'id' => 'folder-1',
            'title' => 'Test Folder',
            'content' => 'folder with matching content',
            'author_id' => $author->id,
            'is_folder' => true,
            'workspace_id' => $this->workspace->id,
        ]);

        $tool = $this->makeTool();
        $request = new Request(['query' => 'matching']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('No documents found', $result);
    }

    public function test_keyword_mode_only_uses_keyword_search(): void
    {
        $author = User::factory()->create();

        Document::create([
            'id' => 'doc-kw',
            'title' => 'Keyword Test',
            'content' => 'This is keyword-searchable content.',
            'author_id' => $author->id,
            'is_folder' => false,
            'workspace_id' => $this->workspace->id,
        ]);

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldNotReceive('search');

        $tool = $this->makeTool(indexer: $indexer);
        $result = $tool->handle(new Request(['query' => 'keyword', 'mode' => 'keyword']));

        $this->assertStringContainsString('Keyword Test', $result);
    }

    public function test_semantic_mode_with_results(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                $this->makeChunk('Relevant semantic content about APIs.', 0.92, 'API Guide'),
            ]));

        $tool = $this->makeTool(indexer: $indexer);
        $result = $tool->handle(new Request(['query' => 'API design', 'mode' => 'semantic']));

        $this->assertStringContainsString('1 relevant document', $result);
        $this->assertStringContainsString('API Guide', $result);
        $this->assertStringContainsString('92% match', $result);
    }

    public function test_semantic_mode_no_results(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')
            ->once()
            ->andReturn(collect());

        $tool = $this->makeTool(indexer: $indexer);
        $result = $tool->handle(new Request(['query' => 'nonexistent', 'mode' => 'semantic']));

        $this->assertStringContainsString('No documents found', $result);
    }

    public function test_semantic_mode_without_indexer_falls_back_to_keyword(): void
    {
        $author = User::factory()->create();

        Document::create([
            'id' => 'doc-fb',
            'title' => 'Fallback Doc',
            'content' => 'Keyword fallback content here.',
            'author_id' => $author->id,
            'is_folder' => false,
            'workspace_id' => $this->workspace->id,
        ]);

        // No indexer — semantic mode should fall back to keyword
        $tool = $this->makeTool(indexer: null);
        $result = $tool->handle(new Request(['query' => 'fallback', 'mode' => 'semantic']));

        // Without indexer, semantic returns null → falls to "No documents found" message
        // but since mode is 'semantic' and indexer is null, it returns the empty string result
        // Actually looking at code: semanticSearch returns [null, 0] when no indexer,
        // then for mode='semantic': semanticResults is null → condition fails → falls to keyword
        $this->assertStringContainsString('Fallback Doc', $result);
    }

    public function test_auto_mode_sufficient_semantic(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                $this->makeChunk('First semantic result about deployment.', 0.95, 'Deploy Guide'),
                $this->makeChunk('Second semantic result about CI/CD.', 0.88, 'CI/CD Docs'),
            ]));

        $tool = $this->makeTool(indexer: $indexer);
        $result = $tool->handle(new Request(['query' => 'deployment', 'mode' => 'auto']));

        // 2+ semantic results → returns semantic only (no keyword merge)
        $this->assertStringContainsString('2 relevant document', $result);
        $this->assertStringContainsString('Deploy Guide', $result);
        $this->assertStringNotContainsString('Keyword matches', $result);
    }

    public function test_auto_mode_insufficient_semantic_merges(): void
    {
        $author = User::factory()->create();

        Document::create([
            'id' => 'doc-merge',
            'title' => 'Keyword Match Doc',
            'content' => 'Infrastructure deployment documentation.',
            'author_id' => $author->id,
            'is_folder' => false,
            'workspace_id' => $this->workspace->id,
        ]);

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                $this->makeChunk('Only one semantic result.', 0.78, 'Single Result'),
            ]));

        $tool = $this->makeTool(indexer: $indexer);
        $result = $tool->handle(new Request(['query' => 'infrastructure', 'mode' => 'auto']));

        // 1 semantic result → merges with keyword
        $this->assertStringContainsString('Keyword matches', $result);
        $this->assertStringContainsString('Single Result', $result);
    }

    public function test_auto_mode_no_semantic_falls_to_keyword(): void
    {
        $author = User::factory()->create();

        Document::create([
            'id' => 'doc-auto-kw',
            'title' => 'Auto Keyword',
            'content' => 'Auto mode keyword fallback test content.',
            'author_id' => $author->id,
            'is_folder' => false,
            'workspace_id' => $this->workspace->id,
        ]);

        // No indexer → auto mode can't do semantic, falls to keyword
        $tool = $this->makeTool(indexer: null);
        $result = $tool->handle(new Request(['query' => 'auto mode', 'mode' => 'auto']));

        $this->assertStringContainsString('Auto Keyword', $result);
        $this->assertStringNotContainsString('Keyword matches', $result); // no merge, just keyword
    }

    public function test_semantic_exception_returns_error(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')
            ->once()
            ->andThrow(new \RuntimeException('Embedding service is down'));

        // In semantic mode, the exception inside semanticSearch is caught → returns [null, 0]
        // Then code falls to keyword, but no docs exist → "No documents found"
        $tool = $this->makeTool(indexer: $indexer);
        $result = $tool->handle(new Request(['query' => 'test', 'mode' => 'semantic']));

        // semanticSearch catches exception → returns [null, 0]
        // mode=semantic, semanticResults=null → falls through to keyword → no results
        $this->assertStringContainsString('No documents found', $result);
    }

    public function test_search_with_empty_query(): void
    {
        $author = User::factory()->create();

        Document::create([
            'id' => 'doc-empty',
            'title' => 'Any Doc',
            'content' => 'Content that matches empty query.',
            'author_id' => $author->id,
            'is_folder' => false,
            'workspace_id' => $this->workspace->id,
        ]);

        $tool = $this->makeTool();
        $result = $tool->handle(new Request(['query' => '']));

        // Empty query → LIKE '%%' matches everything
        $this->assertStringContainsString('Any Doc', $result);
    }

    public function test_default_mode_is_auto(): void
    {
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                $this->makeChunk('Semantic result one.', 0.95, 'Doc A'),
                $this->makeChunk('Semantic result two.', 0.90, 'Doc B'),
            ]));

        // No mode param → should default to 'auto' → uses semantic first
        $tool = $this->makeTool(indexer: $indexer);
        $result = $tool->handle(new Request(['query' => 'test']));

        // Auto mode with 2+ semantic results → returns semantic only
        $this->assertStringContainsString('2 relevant document', $result);
    }

    public function test_semantic_search_filters_by_allowed_folders(): void
    {
        $agent = User::factory()->agent()->create();
        $author = User::factory()->create();

        // Create two folders
        $allowedFolder = Document::create([
            'id' => 'folder-allowed',
            'title' => 'Allowed Folder',
            'content' => '',
            'author_id' => $author->id,
            'is_folder' => true,
            'workspace_id' => $this->workspace->id,
        ]);
        $restrictedFolder = Document::create([
            'id' => 'folder-restricted',
            'title' => 'Restricted Folder',
            'content' => '',
            'author_id' => $author->id,
            'is_folder' => true,
            'workspace_id' => $this->workspace->id,
        ]);

        // Create docs in each folder
        $allowedDoc = Document::create([
            'id' => 'doc-allowed',
            'title' => 'Allowed Doc',
            'content' => 'Allowed content',
            'author_id' => $author->id,
            'parent_id' => $allowedFolder->id,
            'is_folder' => false,
            'workspace_id' => $this->workspace->id,
        ]);
        $restrictedDoc = Document::create([
            'id' => 'doc-restricted',
            'title' => 'Restricted Doc',
            'content' => 'Secret content',
            'author_id' => $author->id,
            'parent_id' => $restrictedFolder->id,
            'is_folder' => false,
            'workspace_id' => $this->workspace->id,
        ]);

        // Create chunks referencing these documents
        $allowedChunk = $this->makeChunk('Allowed content about APIs.', 0.95, 'Allowed Doc');
        $allowedChunk->document_id = $allowedDoc->id;
        $restrictedChunk = $this->makeChunk('Secret content about passwords.', 0.90, 'Restricted Doc');
        $restrictedChunk->document_id = $restrictedDoc->id;

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')->once()->andReturn(collect([$allowedChunk, $restrictedChunk]));

        // Mock permission service to restrict to allowed folder only
        $permissionService = Mockery::mock(AgentPermissionService::class);
        $permissionService->shouldReceive('getAllowedFolderIds')
            ->with($agent)
            ->andReturn([$allowedFolder->id]);

        $tool = new SearchDocuments($agent, $permissionService, $indexer);
        $result = $tool->handle(new Request(['query' => 'content', 'mode' => 'semantic']));

        $this->assertStringContainsString('Allowed Doc', $result);
        $this->assertStringNotContainsString('Restricted Doc', $result);
        $this->assertStringContainsString('1 relevant document', $result);
    }

    public function test_semantic_search_unrestricted_agent_returns_all(): void
    {
        $agent = User::factory()->agent()->create();

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                $this->makeChunk('First doc content.', 0.95, 'Doc A'),
                $this->makeChunk('Second doc content.', 0.90, 'Doc B'),
            ]));

        // Mock permission service returning null (unrestricted)
        $permissionService = Mockery::mock(AgentPermissionService::class);
        $permissionService->shouldReceive('getAllowedFolderIds')
            ->with($agent)
            ->andReturn(null);

        $tool = new SearchDocuments($agent, $permissionService, $indexer);
        $result = $tool->handle(new Request(['query' => 'content', 'mode' => 'semantic']));

        $this->assertStringContainsString('2 relevant document', $result);
        $this->assertStringContainsString('Doc A', $result);
        $this->assertStringContainsString('Doc B', $result);
    }
}

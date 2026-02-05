<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\SearchDocuments;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class SearchDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_documents_by_title(): void
    {
        $author = User::factory()->create();

        Document::create([
            'id' => 'doc-1',
            'title' => 'API Documentation',
            'content' => 'REST API endpoints for user management.',
            'author_id' => $author->id,
            'is_folder' => false,
        ]);

        Document::create([
            'id' => 'doc-2',
            'title' => 'Meeting Notes',
            'content' => 'Discussed project timeline.',
            'author_id' => $author->id,
            'is_folder' => false,
        ]);

        $tool = new SearchDocuments();
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
        ]);

        $tool = new SearchDocuments();
        $request = new Request(['query' => 'docker']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Notes', $result);
        $this->assertStringContainsString('Docker containers', $result);
    }

    public function test_returns_no_results_message(): void
    {
        $tool = new SearchDocuments();
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
            ]);
        }

        $tool = new SearchDocuments();
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
        ]);

        $tool = new SearchDocuments();
        $request = new Request(['query' => 'matching']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('No documents found', $result);
    }
}

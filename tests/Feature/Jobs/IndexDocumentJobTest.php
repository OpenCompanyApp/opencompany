<?php

namespace Tests\Feature\Jobs;

use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\User;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class IndexDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function createDocument(string $content = 'Test content'): Document
    {
        return Document::withoutEvents(fn () => Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Test Doc',
            'content' => $content,
            'is_folder' => false,
            'author_id' => $this->user->id,
        ]));
    }

    public function test_job_calls_indexer_with_correct_params(): void
    {
        $doc = $this->createDocument();
        $agent = User::factory()->agent()->create();

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('index')
            ->once()
            ->with($doc, 'memory', $agent->id);

        $this->app->instance(DocumentIndexingService::class, $indexer);

        $job = new IndexDocumentJob($doc, 'memory', $agent->id);
        $job->handle($indexer);
    }

    public function test_job_catches_indexer_exception(): void
    {
        $doc = $this->createDocument();

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('index')
            ->once()
            ->andThrow(new \RuntimeException('Embedding API down'));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'IndexDocumentJob failed') && str_contains($msg, 'Embedding API down'));

        $job = new IndexDocumentJob($doc);
        // Should not throw
        $job->handle($indexer);
    }

    public function test_job_with_default_collection(): void
    {
        $doc = $this->createDocument();

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('index')
            ->once()
            ->with($doc, 'general', null);

        $job = new IndexDocumentJob($doc);
        $job->handle($indexer);
    }

    public function test_job_with_custom_collection_and_agent(): void
    {
        $doc = $this->createDocument();
        $agent = User::factory()->agent()->create();

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('index')
            ->once()
            ->with($doc, 'identity', $agent->id);

        $job = new IndexDocumentJob($doc, 'identity', $agent->id);
        $job->handle($indexer);
    }

    public function test_job_handles_deleted_document(): void
    {
        $doc = $this->createDocument();
        $docId = $doc->id;

        // Delete the document before the job runs
        Document::withoutEvents(fn () => $doc->forceDelete());

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('index')
            ->andThrow(new \RuntimeException('Model not found'));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'IndexDocumentJob failed'));

        // Reconstruct a stub for the job — in reality SerializesModels would throw,
        // but our try-catch should handle that too
        $job = new IndexDocumentJob($doc);
        $job->handle($indexer);
    }
}

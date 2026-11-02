<?php

namespace Tests\Feature\Jobs;

use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
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
            'workspace_id' => $this->workspace->id,
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

    public function test_job_rethrows_indexer_exception(): void
    {
        $doc = $this->createDocument();

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('index')
            ->once()
            ->andThrow(new \RuntimeException('Embedding API down'));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'IndexDocumentJob failed') && str_contains($msg, 'Embedding API down'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Embedding API down');

        $job = new IndexDocumentJob($doc);
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

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Model not found');

        // Reconstruct a stub for the job — in reality SerializesModels would throw,
        // but our try-catch logs the warning and rethrows so the job fails visibly
        $job = new IndexDocumentJob($doc);
        $job->handle($indexer);
    }

    // Regression: the job bound the document's workspace as currentWorkspace
    // and never restored the caller's binding. On the sync queue (VFS writes,
    // tests) that leaked a foreign workspace into the request, which the VFS
    // workspace assertion then rejected. The document's workspace must be
    // bound only while indexing, on success and on failure.
    public function test_job_restores_the_callers_workspace_binding(): void
    {
        $other = Workspace::create(['name' => 'Other Workspace', 'slug' => 'other']);
        $doc = Document::withoutEvents(fn () => Document::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Foreign Doc',
            'content' => 'x',
            'is_folder' => false,
            'author_id' => $this->user->id,
            'workspace_id' => $other->id,
        ]));

        $boundDuringIndex = null;
        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('index')->once()->andReturnUsing(function () use (&$boundDuringIndex): void {
            $boundDuringIndex = app('currentWorkspace')->id;
        });

        (new IndexDocumentJob($doc))->handle($indexer);

        $this->assertSame($other->id, $boundDuringIndex);
        $this->assertSame($this->workspace->id, app('currentWorkspace')->id);

        $failing = Mockery::mock(DocumentIndexingService::class);
        $failing->shouldReceive('index')->once()->andThrow(new \RuntimeException('down'));

        try {
            (new IndexDocumentJob($doc))->handle($failing);
            $this->fail('expected the indexer exception to propagate');
        } catch (\RuntimeException) {
            // rethrown for queue retries
        }

        $this->assertSame($this->workspace->id, app('currentWorkspace')->id);
    }

    // With nothing bound before (a fresh queue worker), nothing stays bound.
    public function test_job_leaves_no_binding_when_none_existed(): void
    {
        $doc = $this->createDocument();
        app()->forgetInstance('currentWorkspace');

        $indexer = Mockery::mock(DocumentIndexingService::class);
        $indexer->shouldReceive('index')->once();

        (new IndexDocumentJob($doc))->handle($indexer);

        $this->assertFalse(app()->bound('currentWorkspace'));
    }
}

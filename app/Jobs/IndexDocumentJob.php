<?php

namespace App\Jobs;

use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\Document;
use App\Models\Workspace;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Index one document's chunks and embeddings for memory search.
 *
 * Owns: binding the document's workspace for the duration of the indexing
 * call and restoring the caller's binding afterwards (it often runs on the
 * sync queue inside a request or VFS write). Does not own chunking or
 * embedding policy (DocumentIndexingService). Failures are logged and
 * rethrown so the queue retries ($tries/$backoff).
 */
class IndexDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SetsWorkspaceContext;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public function __construct(
        private Document $document,
        private string $collection = 'general',
        private ?string $agentId = null,
    ) {}

    public function handle(DocumentIndexingService $indexer): void
    {
        // The document's workspace is bound for the indexing call (needed for
        // EmbeddingCache writes) and the caller's binding is restored after:
        // this job runs synchronously inside VFS writes on the sync queue,
        // and a leaked binding would trip the VFS workspace assertion.
        $workspace = $this->document->workspace_id ? Workspace::find($this->document->workspace_id) : null;

        $this->withWorkspaceBinding($workspace, function () use ($indexer): void {
            try {
                $indexer->index($this->document, $this->collection, $this->agentId);
            } catch (\Throwable $e) {
                Log::warning("IndexDocumentJob failed for document {$this->document->id}: {$e->getMessage()}");
                throw $e;
            }
        });
    }
}

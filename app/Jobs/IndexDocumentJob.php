<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        try {
            $indexer->index($this->document, $this->collection, $this->agentId);
        } catch (\Throwable $e) {
            Log::warning("IndexDocumentJob failed for document {$this->document->id}: {$e->getMessage()}");
        }
    }
}

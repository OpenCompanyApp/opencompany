<?php

namespace App\Console\Commands;

use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use Illuminate\Console\Command;

class MemoryIndexDocuments extends Command
{
    protected $signature = 'memory:index-documents {--fresh : Delete all existing chunks first}';

    protected $description = 'Index all documents for semantic search (chunk + embed)';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $count = DocumentChunk::count();
            DocumentChunk::truncate();
            $this->components->info("Cleared {$count} existing chunks.");
        }

        $documents = Document::where('is_folder', false)->get();

        if ($documents->isEmpty()) {
            $this->components->warn('No documents found to index.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($documents->count());
        $bar->start();

        $dispatched = 0;

        foreach ($documents as $document) {
            $collection = $this->resolveCollection($document);
            $agentId = $this->resolveAgentId($document);

            IndexDocumentJob::dispatch($document, $collection, $agentId);
            $dispatched++;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->components->info("Dispatched {$dispatched} indexing jobs.");

        return self::SUCCESS;
    }

    private function resolveCollection(Document $document): string
    {
        $parent = $document->parent;

        while ($parent) {
            if ($parent->is_folder) {
                if ($parent->title === 'memory') {
                    return 'memory';
                }
                if ($parent->title === 'identity') {
                    return 'identity';
                }
            }
            $parent = $parent->parent;
        }

        return 'general';
    }

    private function resolveAgentId(Document $document): ?string
    {
        $parent = $document->parent;

        while ($parent) {
            if ($parent->parent->title === 'agents' && $parent->parent->parent_id === null) {
                $agent = User::where('type', 'agent')
                    ->whereRaw("LOWER(REPLACE(name, ' ', '-')) = ?", [strtolower($parent->title)])
                    ->first();

                return $agent?->id;
            }
            $parent = $parent->parent;
        }

        return null;
    }
}

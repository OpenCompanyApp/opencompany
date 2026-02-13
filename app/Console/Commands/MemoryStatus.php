<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\DocumentChunk;
use App\Models\EmbeddingCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MemoryStatus extends Command
{
    protected $signature = 'memory:status';

    protected $description = 'Display the status of the memory subsystem';

    public function handle(): int
    {
        $this->info('Memory Subsystem Status');
        $this->line('');

        // pgvector extension
        $this->components->twoColumnDetail('pgvector extension', $this->checkPgvector());

        // Document chunks by collection
        $this->line('');
        $this->info('Document Chunks');

        try {
            $total = DocumentChunk::count();
            $byCollection = DocumentChunk::selectRaw('collection, count(*) as count')
                ->groupBy('collection')
                ->pluck('count', 'collection');

            $this->components->twoColumnDetail('Total chunks', (string) $total);

            foreach ($byCollection as $collection => $count) {
                $this->components->twoColumnDetail("  {$collection}", (string) $count);
            }

            if ($total === 0) {
                $this->components->twoColumnDetail('  (none)', '0');
            }
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail('Status', '<fg=red>Table not available</>');
        }

        // Embedding cache
        $this->line('');
        $this->info('Embedding Cache');

        try {
            $cacheCount = EmbeddingCache::count();
            $this->components->twoColumnDetail('Cached embeddings', (string) $cacheCount);
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail('Status', '<fg=red>Table not available</>');
        }

        // Current embedding model
        $this->line('');
        $this->info('Configuration');

        $embeddingModel = AppSetting::getValue('memory_embedding_model')
            ?? config('memory.embedding.provider') . ':' . config('memory.embedding.model');
        $this->components->twoColumnDetail('Embedding model', $embeddingModel);
        $this->components->twoColumnDetail('Embedding dimensions', (string) config('memory.embedding.dimensions', 1536));
        $this->components->twoColumnDetail('Chunk size (tokens)', (string) config('memory.chunking.max_chunk_size', 512));
        $this->components->twoColumnDetail('Chunk overlap (tokens)', (string) config('memory.chunking.chunk_overlap', 64));
        $this->components->twoColumnDetail('Compaction enabled', config('memory.compaction.enabled', true) ? 'Yes' : 'No');

        $summaryModel = AppSetting::getValue('memory_summary_model')
            ?? config('memory.compaction.summary_model', 'anthropic:claude-sonnet-4-5-20250929');
        $this->components->twoColumnDetail('Summary model', $summaryModel);

        $rerankingEnabled = AppSetting::getValue('memory_reranking_enabled')
            ?? config('memory.reranking.enabled', true);
        $this->components->twoColumnDetail('Reranking enabled', $rerankingEnabled ? 'Yes' : 'No');

        if ($rerankingEnabled) {
            $rerankingModel = AppSetting::getValue('memory_reranking_model')
                ?? config('memory.reranking.provider') . ':' . config('memory.reranking.model');
            $this->components->twoColumnDetail('Reranking model', $rerankingModel);
        }

        return self::SUCCESS;
    }

    private function checkPgvector(): string
    {
        try {
            $result = DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'vector'");

            return $result ? '<fg=green>Installed</>' : '<fg=yellow>Not installed</>';
        } catch (\Throwable) {
            return '<fg=red>Check failed</>';
        }
    }
}

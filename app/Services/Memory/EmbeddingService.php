<?php

namespace App\Services\Memory;

use App\Domain\Ai\Embeddings\EmbeddingClient;
use App\Models\AppSetting;
use App\Models\EmbeddingCache;
use Illuminate\Support\Facades\Log;

/**
 * Workspace-scoped embedding service with OpenCompany-owned runtime calls.
 *
 * This service owns memory embedding cache semantics only. Provider routing and
 * credentials are delegated to the AI domain client so memory indexing no
 * longer depends on provider package facades.
 */
class EmbeddingService
{
    public function __construct(
        private EmbeddingClient $embeddings,
    ) {}

    /**
     * Get the embedding for a single text.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        [$providerKey, $modelName] = $this->resolveProviderModel();
        $this->ensureWorkspaceContext();

        $cacheKey = EmbeddingCache::cacheKey($providerKey, $modelName, $text);
        $cached = EmbeddingCache::find($cacheKey);

        if ($cached) {
            /** @var array<int, float> */
            return $cached->embedding;
        }

        if ($this->shouldUseTestingEmbeddingFallback()) {
            $embedding = $this->testingEmbedding($text);
            EmbeddingCache::updateOrCreate(
                ['id' => $cacheKey],
                ['provider' => $providerKey, 'model' => $modelName, 'embedding' => $embedding, 'workspace_id' => workspace()->id]
            );

            return $embedding;
        }

        $embedding = $this->embeddings->embed($providerKey, $modelName, $text, workspace()->id);

        EmbeddingCache::updateOrCreate(
            ['id' => $cacheKey],
            ['provider' => $providerKey, 'model' => $modelName, 'embedding' => $embedding, 'workspace_id' => workspace()->id]
        );

        return $embedding;
    }

    /**
     * Get embeddings for multiple texts.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        [$providerKey, $modelName] = $this->resolveProviderModel();
        $this->ensureWorkspaceContext();
        $results = [];
        $uncachedTexts = [];
        $uncachedIndices = [];

        // Check cache for each text
        foreach ($texts as $i => $text) {
            $cacheKey = EmbeddingCache::cacheKey($providerKey, $modelName, $text);
            $cached = EmbeddingCache::find($cacheKey);

            if ($cached) {
                $results[$i] = $cached->embedding;
            } else {
                $uncachedTexts[] = $text;
                $uncachedIndices[] = $i;
            }
        }

        // Call API for uncached texts
        if (! empty($uncachedTexts) && $this->shouldUseTestingEmbeddingFallback()) {
            foreach ($uncachedTexts as $j => $text) {
                $originalIndex = $uncachedIndices[$j];
                $embedding = $this->testingEmbedding($text);
                $results[$originalIndex] = $embedding;

                $cacheKey = EmbeddingCache::cacheKey($providerKey, $modelName, $text);
                EmbeddingCache::updateOrCreate(
                    ['id' => $cacheKey],
                    ['provider' => $providerKey, 'model' => $modelName, 'embedding' => $embedding, 'workspace_id' => workspace()->id]
                );
            }
        } elseif (! empty($uncachedTexts)) {
            try {
                $embeddings = $this->embeddings->embedMany(
                    $providerKey,
                    $modelName,
                    $uncachedTexts,
                    workspace()->id,
                );

                foreach ($embeddings as $j => $embedding) {
                    $originalIndex = $uncachedIndices[$j];
                    $results[$originalIndex] = $embedding;

                    // Cache the result
                    $cacheKey = EmbeddingCache::cacheKey($providerKey, $modelName, $uncachedTexts[$j]);
                    EmbeddingCache::updateOrCreate(
                        ['id' => $cacheKey],
                        ['provider' => $providerKey, 'model' => $modelName, 'embedding' => $embedding, 'workspace_id' => workspace()->id]
                    );
                }
            } catch (\Throwable $e) {
                Log::error('Batch embedding failed', ['error' => $e->getMessage()]);
                throw $e;
            }
        }

        // Sort by original index
        ksort($results);

        return array_values($results);
    }

    private function shouldUseTestingEmbeddingFallback(): bool
    {
        return app()->environment('testing')
            && (bool) config('memory.embedding.testing_fallback', true);
    }

    /**
     * @return array<int, float>
     */
    private function testingEmbedding(string $text): array
    {
        $dimensions = max(1, (int) config('memory.embedding.dimensions', 1536));
        $seed = hash('sha256', $text);
        $embedding = [];

        for ($i = 0; $i < $dimensions; $i++) {
            $byte = hexdec(substr($seed, ($i * 2) % 64, 2));
            $embedding[] = round(($byte / 255) * 2 - 1, 6);
        }

        return $embedding;
    }

    /**
     * Resolve the embedding provider and model from settings or config.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveProviderModel(): array
    {
        return AppSetting::resolveProviderModel(
            'memory_embedding_model', 'memory.embedding.provider', 'memory.embedding.model'
        );
    }

    /**
     * Ensure DynamicProviderResolver has workspace context for API key lookups.
     */
    private function ensureWorkspaceContext(): void
    {
        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;
        if ($workspace) {
            return;
        }
    }
}

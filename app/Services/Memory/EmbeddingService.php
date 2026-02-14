<?php

namespace App\Services\Memory;

use App\Agents\Providers\DynamicProviderResolver;
use App\Models\AppSetting;
use App\Models\EmbeddingCache;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;

class EmbeddingService
{
    public function __construct(
        private DynamicProviderResolver $providerResolver,
    ) {}

    /**
     * Get the embedding for a single text.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        [$providerKey, $modelName] = $this->resolveProviderModel();

        $cacheKey = EmbeddingCache::cacheKey($providerKey, $modelName, $text);
        $cached = EmbeddingCache::find($cacheKey);

        if ($cached) {
            return $cached->embedding;
        }

        $resolved = $this->providerResolver->resolveFromParts($providerKey, $modelName);

        $response = Prism::embeddings()
            ->using($resolved['provider'], $resolved['model'])
            ->fromInput($text)
            ->asEmbeddings();

        $embedding = $response->embeddings[0]->embedding;

        EmbeddingCache::create([
            'id' => $cacheKey,
            'provider' => $providerKey,
            'model' => $modelName,
            'embedding' => $embedding,
        ]);

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
        $resolved = $this->providerResolver->resolveFromParts($providerKey, $modelName);

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
        if (!empty($uncachedTexts)) {
            try {
                $response = Prism::embeddings()
                    ->using($resolved['provider'], $resolved['model'])
                    ->fromArray($uncachedTexts)
                    ->asEmbeddings();

                foreach ($response->embeddings as $j => $embeddingResult) {
                    $originalIndex = $uncachedIndices[$j];
                    $embedding = $embeddingResult->embedding;
                    $results[$originalIndex] = $embedding;

                    // Cache the result
                    $cacheKey = EmbeddingCache::cacheKey($providerKey, $modelName, $uncachedTexts[$j]);
                    EmbeddingCache::create([
                        'id' => $cacheKey,
                        'provider' => $providerKey,
                        'model' => $modelName,
                        'embedding' => $embedding,
                    ]);
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
}

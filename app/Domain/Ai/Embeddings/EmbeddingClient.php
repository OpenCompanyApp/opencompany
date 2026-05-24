<?php

namespace App\Domain\Ai\Embeddings;

use App\Agents\Providers\DynamicProviderResolver;
use Laravel\Ai\AiManager;

/**
 * Generates embeddings through OpenCompany's provider runtime.
 *
 * Provider setup stays behind OpenCompany runtime resolution. This client keeps
 * embedding calls in the same app-owned provider resolution path as agent text
 * generation so workspace credentials, aliases, and custom URLs behave the same
 * way everywhere.
 */
class EmbeddingClient
{
    public function __construct(
        private readonly DynamicProviderResolver $providerResolver,
        private readonly AiManager $ai,
    ) {}

    /**
     * @param  list<string>  $inputs
     * @return list<array<int, float>>
     */
    public function embedMany(string $providerKey, string $modelName, array $inputs, ?string $workspaceId = null): array
    {
        if ($inputs === []) {
            return [];
        }

        $resolver = clone $this->providerResolver;
        $resolved = $resolver
            ->setWorkspaceId($workspaceId)
            ->resolveFromParts($providerKey, $modelName);

        $provider = $this->ai->embeddingProvider($resolved['provider']);
        $response = $provider->embeddings(
            inputs: $inputs,
            dimensions: $this->dimensions(),
            model: $resolved['model'],
            timeout: (int) config('ai.request_timeout', 30),
        );

        return array_values(array_map(
            static fn (array $embedding): array => array_map('floatval', $embedding),
            $response->embeddings,
        ));
    }

    /**
     * @return array<int, float>
     */
    public function embed(string $providerKey, string $modelName, string $input, ?string $workspaceId = null): array
    {
        return $this->embedMany($providerKey, $modelName, [$input], $workspaceId)[0] ?? [];
    }

    private function dimensions(): int
    {
        return max(1, (int) config('memory.embedding.dimensions', 1536));
    }
}

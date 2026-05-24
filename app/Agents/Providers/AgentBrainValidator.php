<?php

namespace App\Agents\Providers;

use App\Domain\Ai\Catalog\AiCatalog;
use InvalidArgumentException;

/**
 * Validates the provider:model brain string before it is stored on an agent.
 *
 * The resolver performs the real provider/model lookup because available models
 * can come from workspace-specific integration settings as well as catalog
 * defaults.
 */
class AgentBrainValidator
{
    public function __construct(
        private readonly DynamicProviderResolver $resolver,
        private readonly AiCatalog $catalog,
    ) {}

    public function validate(string $brain, ?string $workspaceId = null): void
    {
        $parts = explode(':', $brain, 2);

        if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
            throw new InvalidArgumentException('Invalid brain format. Expected "provider:model".');
        }

        // Resolve the pair instead of only checking syntax so invalid workspace
        // overlays fail at the same boundary as unknown global providers.
        $this->resolver
            ->setWorkspaceId($workspaceId)
            ->resolveFromParts(trim($parts[0]), trim($parts[1]));
    }

    public function example(): string
    {
        // Use the catalog for the model example so validation errors can suggest
        // a real configured default instead of a stale hardcoded model.
        $provider = (string) config('ai.default_for_agents', config('ai.default'));
        $model = $this->catalog->defaultModel($provider);

        return "{$provider}:{$model}";
    }
}

<?php

namespace App\Agents\Providers;

use InvalidArgumentException;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

class AgentBrainValidator
{
    public function __construct(
        private readonly DynamicProviderResolver $resolver,
        private readonly RelayRegistry $registry,
    ) {}

    public function validate(string $brain, ?string $workspaceId = null): void
    {
        $parts = explode(':', $brain, 2);

        if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
            throw new InvalidArgumentException('Invalid brain format. Expected "provider:model".');
        }

        $this->resolver
            ->setWorkspaceId($workspaceId)
            ->resolveFromParts(trim($parts[0]), trim($parts[1]));
    }

    public function example(): string
    {
        $provider = (string) config('ai.default_for_agents', config('ai.default'));
        $model = (string) ($this->registry->provider($provider)['default_model'] ?? 'model');

        return "{$provider}:{$model}";
    }
}

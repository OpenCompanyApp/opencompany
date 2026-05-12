<?php

namespace App\Ai\Providers;

use Laravel\Ai\AiManager;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

/**
 * Registers every Prism Relay provider with Laravel AI.
 *
 * Provider names come from the relay registry so OpenCompany does not need to
 * hardcode each AI vendor in the app service provider.
 */
class RelayAiProviderRegistrar
{
    public function __construct(
        private readonly RelayRegistry $registry,
        private readonly RelayAiProviderFactory $factory,
    ) {}

    public function register(AiManager $aiManager): void
    {
        $factory = $this->factory;

        // Capture the factory by value so each closure can build a provider
        // from Laravel's resolved config at call time.
        foreach ($this->registry->registrationNames() as $name) {
            $aiManager->extend($name, function ($app, array $config) use ($factory, $name) {
                return $factory->create($name, $config);
            });
        }
    }
}

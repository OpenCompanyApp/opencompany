<?php

namespace App\Ai\Providers;

use Laravel\Ai\AiManager;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

class RelayAiProviderRegistrar
{
    public function __construct(
        private readonly RelayRegistry $registry,
        private readonly RelayAiProviderFactory $factory,
    ) {}

    public function register(AiManager $aiManager): void
    {
        $factory = $this->factory;

        foreach ($this->registry->registrationNames() as $name) {
            $aiManager->extend($name, function ($app, array $config) use ($factory, $name) {
                return $factory->create($name, $config);
            });
        }
    }
}

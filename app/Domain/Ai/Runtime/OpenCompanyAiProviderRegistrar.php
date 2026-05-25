<?php

namespace App\Domain\Ai\Runtime;

use App\Domain\Ai\Catalog\AiCatalog;
use Laravel\Ai\AiManager;

/**
 * Registers all catalog providers with Laravel AI.
 *
 * The app catalog owns registration names, including legacy aliases. Registering
 * aliases keeps existing stored agent brains valid while new code works through
 * canonical provider IDs.
 */
class OpenCompanyAiProviderRegistrar
{
    public function __construct(
        private readonly AiCatalog $catalog,
        private readonly OpenCompanyAiProviderFactory $factory,
    ) {}

    public function register(AiManager $aiManager): void
    {
        $factory = $this->factory;

        foreach ($this->catalog->registrationNames() as $name) {
            $aiManager->extend($name, function ($app, array $config) use ($factory, $name) {
                return $factory->create($name, $config);
            });
        }
    }
}

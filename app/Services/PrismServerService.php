<?php

namespace App\Services;

use App\Agents\Providers\DynamicProviderResolver;
use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\PrismServer;
use Prism\Prism\Facades\Prism;

class PrismServerService
{
    public function __construct(
        private readonly DynamicProviderResolver $resolver,
    ) {}

    /**
     * Register enabled models with PrismServer.
     */
    public function registerModels(): void
    {
        try {
            $setting = IntegrationSetting::where('integration_id', 'prism-server')
                ->where('enabled', true)
                ->first();
        } catch (\Throwable) {
            // Table may not exist yet (fresh install, tests)
            return;
        }

        if (! $setting) {
            return;
        }

        $enabledModels = $setting->getConfigValue('enabled_models', []);

        if (empty($enabledModels)) {
            return;
        }

        foreach ($enabledModels as $modelId) {
            // Model ID format: "provider:model" (e.g. "codex:gpt-5.3-codex")
            $parts = explode(':', $modelId, 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$providerKey, $model] = $parts;

            try {
                $resolved = $this->resolver->resolveFromParts($providerKey, $model);

                PrismServer::register(
                    $modelId,
                    fn () => Prism::text()
                        ->using($resolved['provider'], $resolved['model'])
                );
            } catch (\Throwable $e) {
                Log::warning("Failed to register PrismServer model '{$modelId}': {$e->getMessage()}");
            }
        }
    }
}

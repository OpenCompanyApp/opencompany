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
            $settings = IntegrationSetting::where('integration_id', 'prism-server')
                ->where('enabled', true)
                ->get();
        } catch (\Throwable) {
            // Table may not exist yet (fresh install, tests)
            return;
        }

        if ($settings->isEmpty()) {
            return;
        }

        $enabledModels = $settings
            ->flatMap(fn (IntegrationSetting $setting): array => $setting->getConfigValue('enabled_models', []))
            ->filter(fn (mixed $model): bool => is_string($model) && str_contains($model, ':'))
            ->unique()
            ->values();

        if ($enabledModels->isEmpty()) {
            return;
        }

        foreach ($enabledModels as $modelId) {
            // Model ID format: "provider:model".
            $parts = explode(':', $modelId, 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$providerKey, $model] = $parts;

            PrismServer::register(
                $modelId,
                function () use ($providerKey, $model) {
                    try {
                        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;
                        $this->resolver->setWorkspaceId($workspace?->id);
                        $resolved = $this->resolver->resolveFromParts($providerKey, $model);

                        return Prism::text()
                            ->using($resolved['provider'], $resolved['model']);
                    } catch (\Throwable $e) {
                        Log::warning("Failed to resolve PrismServer model '{$providerKey}:{$model}': {$e->getMessage()}");

                        throw $e;
                    }
                }
            );
        }
    }
}

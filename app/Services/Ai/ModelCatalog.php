<?php

namespace App\Services\Ai;

use App\Models\IntegrationSetting;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

class ModelCatalog
{
    public function __construct(
        private RelayRegistry $registry,
        private ProviderCatalog $providers,
    ) {}

    /**
     * @return array<string, string>
     */
    public function modelsForProvider(string $provider, ?string $workspaceId = null): array
    {
        $canonical = $this->registry->canonicalProvider($provider) ?? $provider;
        $setting = $this->setting($canonical, $workspaceId);
        $stored = $setting?->getConfigValue('models', []);
        if (is_array($stored) && $stored !== []) {
            return $stored;
        }

        $definition = $this->registry->provider($canonical) ?? [];
        $models = [];
        foreach (($definition['models'] ?? []) as $modelId => $meta) {
            $models[$modelId] = is_array($meta)
                ? (string) ($meta['label'] ?? $meta['name'] ?? $this->formatModelName((string) $modelId))
                : (string) $meta;
        }

        return $models;
    }

    public function defaultModel(string $provider, ?string $workspaceId = null): string
    {
        $canonical = $this->registry->canonicalProvider($provider) ?? $provider;
        $setting = $this->setting($canonical, $workspaceId);
        $stored = $setting?->getConfigValue('models', []);
        if (is_array($stored) && $stored !== []) {
            return (string) array_key_first($stored);
        }

        $definition = $this->registry->provider($canonical) ?? [];

        return (string) ($definition['default_model'] ?? array_key_first($definition['models'] ?? []) ?? 'default');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function enabledModels(?string $workspaceId = null): array
    {
        $models = [];
        foreach ($this->providers->all($workspaceId) as $provider) {
            if (! ($provider['configured'] ?? false)) {
                continue;
            }

            foreach ($this->modelsForProvider($provider['id'], $workspaceId) as $modelId => $modelName) {
                $models[] = [
                    'id' => $provider['id'].':'.$modelId,
                    'provider' => $provider['id'],
                    'providerName' => $provider['name'] ?? $provider['id'],
                    'model' => $modelId,
                    'name' => $modelName,
                    'icon' => $provider['icon'] ?? 'ph:brain',
                ];
            }
        }

        return $models;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function providerOptions(?string $workspaceId = null): array
    {
        return collect($this->providers->all($workspaceId))
            ->filter(fn (array $provider) => $this->modelsForProvider($provider['id'], $workspaceId) !== [])
            ->map(fn (array $provider) => [
                'id' => $provider['id'],
                'name' => $provider['name'] ?? $provider['id'],
                'icon' => $provider['icon'] ?? 'ph:brain',
                'configured' => (bool) ($provider['configured'] ?? false),
                'source' => $provider['source'] ?? 'relay',
                'models' => collect($this->modelsForProvider($provider['id'], $workspaceId))
                    ->map(fn (string $name, string $id) => ['id' => $id, 'name' => $name])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function setting(string $provider, ?string $workspaceId): ?IntegrationSetting
    {
        $query = IntegrationSetting::query()
            ->where('integration_id', $provider)
            ->default();

        if ($workspaceId !== null) {
            $query->where('workspace_id', $workspaceId);
        } elseif (app()->bound('currentWorkspace')) {
            $query->forWorkspace();
        }

        return $query->first();
    }

    private function formatModelName(string $modelId): string
    {
        $name = preg_replace('/-\d{8}$/', '', $modelId) ?: $modelId;
        $name = str_replace(['-', '_', '/'], [' ', ' ', ' / '], $name);

        return ucwords($name);
    }
}

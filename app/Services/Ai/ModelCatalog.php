<?php

namespace App\Services\Ai;

use App\Domain\Ai\Catalog\AiCatalog;
use App\Models\IntegrationSetting;

/**
 * Workspace-aware model list helper backed by the app-owned catalog.
 *
 * Catalog model lists are defaults for UI choices and analytics metadata only.
 * Runtime resolution still accepts arbitrary model IDs for known providers so a
 * workspace can adopt a newly released model before the committed catalog moves.
 */
class ModelCatalog
{
    public function __construct(
        private AiCatalog $catalog,
        private ProviderCatalog $providers,
    ) {}

    /**
     * @return array<string, string>
     */
    public function modelsForProvider(string $provider, ?string $workspaceId = null): array
    {
        $canonical = $this->catalog->canonicalProvider($provider) ?? $provider;
        $setting = $this->setting($canonical, $workspaceId);
        $stored = $setting?->getConfigValue('models', []);
        if (is_array($stored) && $stored !== []) {
            return array_map('strval', $stored);
        }

        $info = $this->catalog->provider($canonical);
        if ($info === null) {
            return [];
        }

        $models = [];
        foreach ($info->models as $model) {
            $models[$model->id] = $model->label;
        }

        return $models;
    }

    public function defaultModel(string $provider, ?string $workspaceId = null): string
    {
        $canonical = $this->catalog->canonicalProvider($provider) ?? $provider;
        $setting = $this->setting($canonical, $workspaceId);
        $stored = $setting?->getConfigValue('models', []);
        if (is_array($stored) && $stored !== []) {
            return (string) array_key_first($stored);
        }

        return $this->catalog->defaultModel($canonical);
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
                'source' => $provider['source'] ?? 'catalog',
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
}

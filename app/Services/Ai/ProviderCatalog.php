<?php

namespace App\Services\Ai;

use App\Domain\Ai\Catalog\AiCatalog;
use App\Domain\Ai\Catalog\ProviderInfo;
use App\Domain\Ai\Codex\CodexTokenStore;
use App\Models\IntegrationSetting;

/**
 * Workspace-aware view over OpenCompany's app-owned AI provider catalog.
 *
 * The committed catalog owns provider identity, aliases, defaults, metadata,
 * and runtime driver mapping. OpenCompany workspace settings add credentials,
 * custom URLs, and optional model lists without changing the global catalog.
 */
class ProviderCatalog
{
    public function __construct(private AiCatalog $catalog) {}

    public function canonicalProvider(string $provider): ?string
    {
        return $this->catalog->canonicalProvider($provider);
    }

    public function hasProvider(string $provider): bool
    {
        return $this->catalog->hasProvider($provider);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function provider(string $provider, ?string $workspaceId = null): ?array
    {
        $info = $this->catalog->provider($provider);
        if ($info === null) {
            return null;
        }

        $setting = $this->setting($info->id, $workspaceId);
        $url = $setting?->getConfigValue('url')
            ?: config("ai.providers.{$info->id}.url")
            ?: $info->defaultUrl;

        return [
            'id' => $info->id,
            'canonical' => $info->id,
            'name' => $info->name,
            'description' => $info->description,
            'icon' => $info->icon,
            'driver' => $info->driver,
            'api_format' => $info->apiFormat,
            'auth_mode' => $info->authMode,
            'requires_api_key' => $info->requiresApiKey(),
            'url' => $url,
            'default_url' => $info->defaultUrl,
            'api_key_url' => $info->apiKeyUrl,
            'default_model' => $info->defaultModel,
            'models' => $this->models($info),
            'configured' => $this->configured($info->id, $workspaceId),
            'source' => $setting?->hasValidConfig() ? 'integration' : ($this->configHasCredentials($info->id) ? 'config' : $info->source),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?string $workspaceId = null): array
    {
        $providers = [];
        foreach ($this->catalog->providers() as $provider) {
            $descriptor = $this->provider($provider->id, $workspaceId);
            if ($descriptor !== null) {
                $providers[] = $descriptor;
            }
        }

        return $providers;
    }

    public function configured(string $provider, ?string $workspaceId = null): bool
    {
        $info = $this->catalog->provider($provider);
        if ($info === null) {
            return false;
        }

        if ($info->authMode === 'oauth') {
            if ($info->id === 'codex') {
                $token = CodexTokenStore::current();

                return $token !== null && ! $token->isExpired();
            }

            return false;
        }

        $setting = $this->setting($info->id, $workspaceId);
        if ($setting?->hasValidConfig()) {
            return true;
        }

        if ($this->configHasCredentials($info->id)) {
            return true;
        }

        if (! $info->requiresApiKey()) {
            return filled(config("ai.providers.{$info->id}.url") ?: $info->defaultUrl);
        }

        return false;
    }

    private function configHasCredentials(string $provider): bool
    {
        return filled(config("ai.providers.{$provider}.key"))
            || filled(config("ai.providers.{$provider}.api_key"));
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

    /**
     * @return array<string, array<string, mixed>>
     */
    private function models(ProviderInfo $provider): array
    {
        $models = [];
        foreach ($provider->models as $model) {
            $models[$model->id] = $model->toArray();
        }

        return $models;
    }
}

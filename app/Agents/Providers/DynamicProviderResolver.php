<?php

namespace App\Agents\Providers;

use App\Models\IntegrationSetting;
use App\Models\User;
use App\Services\Ai\ModelCatalog;
use App\Services\Ai\ProviderCatalog;
use App\Services\Ai\ProviderConfigResolver;
use InvalidArgumentException;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

/**
 * Resolves an agent brain setting into the provider/model pair used by Laravel AI.
 *
 * OpenCompany stores user-editable brains as compact strings, while the runtime
 * needs canonical provider IDs, current model aliases, workspace-scoped
 * credentials, and provider registration. This resolver keeps that translation
 * out of the agent class so provider catalog behavior can be tested directly.
 */
class DynamicProviderResolver
{
    private ?string $workspaceId = null;

    public function __construct(
        private ?RelayRegistry $registry = null,
        private ?ProviderCatalog $providerCatalog = null,
        private ?ModelCatalog $modelCatalog = null,
        private ?ProviderConfigResolver $configResolver = null,
    ) {}

    /**
     * Set the workspace ID for scoping IntegrationSetting queries.
     * Use this when calling resolveFromParts() directly without resolve(User).
     */
    public function setWorkspaceId(?string $workspaceId): self
    {
        $this->workspaceId = $workspaceId;

        return $this;
    }

    /**
     * Parse a User's brain field and resolve to SDK provider + model.
     *
     * Brain format: "provider:model".
     *
     * @return array{provider: string, model: string}
     */
    public function resolve(User $agent): array
    {
        $this->workspaceId = $agent->workspace_id;

        $brain = $agent->brain ?? (string) config('ai.default_for_agents', config('ai.default'));
        $parts = explode(':', $brain, 2);
        $providerKey = $parts[0];
        $model = $parts[1] ?? $this->getDefaultModel($providerKey);

        return $this->resolveFromParts($providerKey, $model);
    }

    /**
     * Resolve a provider key + model string to SDK provider + model.
     *
     * @return array{provider: string, model: string}
     */
    public function resolveFromParts(string $providerKey, string $model): array
    {
        [$providerKey, $model] = $this->normalizeLegacyBrain($providerKey, $model);
        $providerKey = $this->providers()->canonicalProvider($providerKey) ?? $providerKey;

        if (! $this->providers()->hasProvider($providerKey)) {
            throw new InvalidArgumentException("Unknown provider: {$providerKey}");
        }

        return $this->config()->resolve($providerKey, $model, $this->workspaceId);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizeLegacyBrain(string $providerKey, string $model): array
    {
        return match ($providerKey) {
            // Older Atlas brains used a synthetic provider key for GLM coding.
            // Keep them working, but route through the current Z.ai provider and
            // upgrade obsolete GLM 4.x defaults to the supported GLM 5.1 model.
            'glm-coding' => ['z', str_starts_with($model, 'glm-4.') ? 'glm-5.1' : $model],
            default => [$providerKey, $model],
        };
    }

    /**
     * Get the default model for a provider.
     */
    private function getDefaultModel(string $providerKey): string
    {
        // Workspace settings win over package defaults because teams can expose
        // a restricted model list or custom aliases from the integrations UI.
        $setting = IntegrationSetting::where('workspace_id', $this->workspaceId)
            ->where('integration_id', $providerKey)->first();
        $models = $setting?->getConfigValue('models', []);
        if (is_array($models) && ! empty($models)) {
            return array_key_first($models);
        }

        return $this->models()->defaultModel($providerKey, $this->workspaceId);
    }

    private function registry(): RelayRegistry
    {
        return $this->registry ??= app(RelayRegistry::class);
    }

    private function providers(): ProviderCatalog
    {
        return $this->providerCatalog ??= app(ProviderCatalog::class);
    }

    private function models(): ModelCatalog
    {
        return $this->modelCatalog ??= app(ModelCatalog::class);
    }

    private function config(): ProviderConfigResolver
    {
        return $this->configResolver ??= app(ProviderConfigResolver::class);
    }
}

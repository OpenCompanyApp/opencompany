<?php

namespace App\Domain\Ai\Gateway;

use App\Models\IntegrationSetting;
use App\Services\Ai\ModelCatalog;
use App\Services\Ai\ProviderCatalog;

/**
 * Workspace model exposure rules for the OpenCompany AI Gateway.
 *
 * The gateway intentionally exposes only models enabled in workspace settings.
 * This keeps external API clients from bypassing the curated model surface that
 * admins configure in the OpenCompany UI.
 */
class AiGatewayModelRegistry
{
    public function __construct(
        private readonly ModelCatalog $models,
        private readonly ProviderCatalog $providers,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->setting()?->enabled;
    }

    /**
     * @return list<array{id: string, provider: string, model: string, name: string}>
     */
    public function models(): array
    {
        $enabledIds = $this->enabledModelIds();

        if ($enabledIds === []) {
            return [];
        }

        $known = collect($this->models->enabledModels(workspace()->id))->keyBy('id');

        return array_values(array_map(function (string $id) use ($known): array {
            [$provider, $model] = $this->splitModelId($id);
            $knownModel = $known->get($id, []);

            return [
                'id' => $id,
                'provider' => $provider,
                'model' => $model,
                'name' => (string) ($knownModel['name'] ?? $model),
            ];
        }, $enabledIds));
    }

    /**
     * @return array{provider: string, model: string, gateway_model: string}
     */
    public function resolve(string $requestedModel): array
    {
        if (! $this->enabled()) {
            throw new \InvalidArgumentException('AI Gateway is disabled for this workspace.');
        }

        $enabled = $this->enabledModelIds();
        if ($enabled === []) {
            throw new \InvalidArgumentException('No AI Gateway models are enabled for this workspace.');
        }

        $modelId = in_array($requestedModel, $enabled, true)
            ? $requestedModel
            : $this->matchBareModel($requestedModel, $enabled);

        if ($modelId === null) {
            throw new \InvalidArgumentException("Model '{$requestedModel}' is not enabled for this workspace.");
        }

        [$provider, $model] = $this->splitModelId($modelId);

        if (! $this->providers->configured($provider, workspace()->id)) {
            throw new \InvalidArgumentException("Provider '{$provider}' is not configured for this workspace.");
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'gateway_model' => $modelId,
        ];
    }

    /**
     * @return list<string>
     */
    private function enabledModelIds(): array
    {
        $models = $this->setting()?->getConfigValue('enabled_models', []);

        return is_array($models)
            ? array_values(array_filter(array_map('strval', $models)))
            : [];
    }

    private function setting(): ?IntegrationSetting
    {
        return IntegrationSetting::forWorkspace()
            ->where('integration_id', 'ai-gateway')
            ->first();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitModelId(string $modelId): array
    {
        if (! str_contains($modelId, ':')) {
            throw new \InvalidArgumentException("Gateway model '{$modelId}' must use provider:model format.");
        }

        return explode(':', $modelId, 2);
    }

    /**
     * @param  list<string>  $enabled
     */
    private function matchBareModel(string $requestedModel, array $enabled): ?string
    {
        foreach ($enabled as $modelId) {
            [, $model] = $this->splitModelId($modelId);
            if ($model === $requestedModel) {
                return $modelId;
            }
        }

        return null;
    }
}

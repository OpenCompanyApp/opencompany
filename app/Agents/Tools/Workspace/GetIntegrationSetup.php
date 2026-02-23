<?php

namespace App\Agents\Tools\Workspace;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class GetIntegrationSetup implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Get setup requirements for a configurable integration, including required fields and their types.';
    }

    public function handle(Request $request): string
    {
        try {
            $integrationId = $request['integrationId'] ?? null;
            if (!$integrationId) {
                return 'integrationId is required.';
            }

            $provider = $this->findConfigurableProvider($integrationId);
            if (!$provider) {
                return "No configurable integration found for '{$integrationId}'. This action is for dynamic integrations from packages.";
            }

            $meta = $provider->integrationMeta();
            $configSchema = $provider->configSchema();

            return json_encode([
                'id' => $integrationId,
                'name' => $meta['name'],
                'fields' => collect($configSchema)->map(fn ($field) => array_filter([
                    'key' => $field['key'],
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'required' => !empty($field['required']) ?: null,
                    'default' => isset($field['default']) && $field['default'] !== '' && $field['default'] !== [] ? $field['default'] : null,
                    'placeholder' => $field['placeholder'] ?? null,
                ]))->values()->toArray(),
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function findConfigurableProvider(string $id): ?ConfigurableIntegration
    {
        $provider = app(ToolProviderRegistry::class)->get($id);

        return $provider instanceof ConfigurableIntegration ? $provider : null;
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'integrationId' => $schema
                ->string()
                ->description('Integration ID (e.g., "telegram", "glm", "plausible"). Includes both static and dynamic package-provided integrations.')
                ->required(),
        ];
    }
}

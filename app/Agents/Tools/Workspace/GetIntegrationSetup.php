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
            $schema = $provider->configSchema();

            $lines = ["{$meta['name']} setup requirements:"];
            /** @var array{key: string, type: string, label: string, required?: bool, default?: mixed, placeholder?: string} $field */
            foreach ($schema as $field) {
                $parts = ["{$field['key']} ({$field['type']})"];
                if (!empty($field['required'])) {
                    $parts[] = 'REQUIRED';
                }
                if (isset($field['default']) && $field['default'] !== '' && $field['default'] !== []) {
                    $defaultStr = is_array($field['default']) ? json_encode($field['default']) : $field['default'];
                    $parts[] = "default: {$defaultStr}";
                }
                if (!empty($field['placeholder'])) {
                    $parts[] = "e.g. {$field['placeholder']}";
                }
                $lines[] = '- ' . implode(' — ', $parts);
            }

            $lines[] = '';
            $lines[] = "Use update_integration_config with integrationId='{$integrationId}' and provide values for the fields above. For string_list fields, pass a JSON array string.";

            return implode("\n", $lines);
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

<?php

namespace App\Agents\Tools\Workspace;

use App\Models\User;
use App\Services\Integrations\ConfigSchemaNormalizer;
use App\Services\Integrations\IntegrationConfigResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

/**
 * Describes the setup contract for an integration before credentials are saved.
 *
 * Package-owned integrations expose their schema through the integration
 * registry. Telegram is intentionally handled as an app-owned chat integration:
 * its tools and package metadata may exist, but the workspace setup surface must
 * describe OpenCompany's Telegram runtime and webhook, not Chatogrator/package
 * credentials.
 */
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
            if (! $integrationId) {
                return 'integrationId is required.';
            }

            if ($integrationId === 'telegram') {
                return $this->telegramSetup();
            }

            $provider = $this->findConfigurableProvider($integrationId);
            if (! $provider) {
                return "No configurable integration found for '{$integrationId}'. This action is for dynamic integrations from packages.";
            }

            $meta = $provider->integrationMeta();
            $configSchema = ConfigSchemaNormalizer::normalize($provider->configSchema());

            return json_encode([
                'id' => $integrationId,
                'name' => $meta['name'],
                'fields' => collect($configSchema)->map(fn ($field) => array_filter([
                    'key' => $field['key'],
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'required' => ! empty($field['required']) ?: null,
                    'default' => isset($field['default']) && $field['default'] !== '' && $field['default'] !== [] ? $field['default'] : null,
                    'placeholder' => $field['placeholder'] ?? null,
                ]))->values()->toArray(),
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function telegramSetup(): string
    {
        $info = config('chat_integrations.telegram', []);
        $configFields = $info['config_fields'] ?? [];
        $webhookUrl = rtrim((string) config('app.url'), '/').'/api/webhooks/chat/telegram';

        return json_encode([
            'id' => 'telegram',
            'name' => $info['name'] ?? 'Telegram',
            'runtime' => 'opencompany_chat',
            'domain' => 'chat',
            'webhookUrl' => $webhookUrl,
            'webhookTool' => 'setup_integration_webhook',
            'fields' => collect(IntegrationConfigResolver::buildStaticConfigSchema($configFields))->map(fn ($field) => array_filter([
                'key' => $field['key'],
                'type' => $field['type'],
                'label' => $field['label'],
                'required' => ! empty($field['required']) ?: null,
                'placeholder' => $field['placeholder'] ?? null,
                'hint' => $field['hint'] ?? null,
            ]))->values()->toArray(),
        ], JSON_PRETTY_PRINT);
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
                ->description('Integration ID (e.g., "telegram", "z", "plausible"). Includes both static and dynamic package-provided integrations.')
                ->required(),
        ];
    }
}

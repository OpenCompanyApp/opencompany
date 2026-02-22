<?php

namespace App\Agents\Tools\Workspace;

use App\Models\IntegrationSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class GetIntegrationConfig implements Tool
{
    public function description(): string
    {
        return 'Get the configuration details for a specific integration.';
    }

    public function handle(Request $request): string
    {
        try {
            $integrationId = $request['integrationId'] ?? null;
            if (!$integrationId) {
                return 'integrationId is required.';
            }

            // Check dynamic providers first
            $provider = app(ToolProviderRegistry::class)->get($integrationId);
            if ($provider instanceof ConfigurableIntegration) {
                return $this->getDynamicIntegrationConfig($integrationId, $provider);
            }

            // Static providers
            $available = IntegrationSetting::getAvailableIntegrations();
            if (!isset($available[$integrationId])) {
                $allIds = array_keys($available);
                foreach (app(ToolProviderRegistry::class)->all() as $p) {
                    if ($p instanceof ConfigurableIntegration) {
                        $allIds[] = $p->appName();
                    }
                }

                return "Integration not found: {$integrationId}. Available: " . implode(', ', $allIds);
            }

            /** @var \App\Models\IntegrationSetting|null $setting */
            $setting = IntegrationSetting::forWorkspace()->where('integration_id', $integrationId)->first();
            $info = $available[$integrationId];

            $lines = [
                "Integration: {$info['name']} ({$integrationId})",
                "Description: {$info['description']}",
                "Enabled: " . (($setting && $setting->enabled) ? 'yes' : 'no'),
                "Configured: " . (($setting?->hasValidConfig() ?? false) ? 'yes' : 'no'),
                "API Key: " . ($setting?->getMaskedApiKey() ?? 'not set'),
            ];

            if ($integrationId === 'telegram') {
                $lines[] = "Default Agent ID: " . ($setting?->getConfigValue('default_agent_id') ?? 'not set');
                $lines[] = "Notify Chat ID: " . ($setting?->getConfigValue('notify_chat_id') ?? 'not set');
                $allowed = $setting?->getConfigValue('allowed_telegram_users', []) ?? [];
                $lines[] = "Allowed Telegram Users: " . ($allowed ? implode(', ', $allowed) : 'none');
            } else {
                $lines[] = "URL: " . ($setting?->getConfigValue('url') ?? ($info['default_url'] ?? 'not set'));
                $lines[] = "Default Model: " . ($setting?->getConfigValue('default_model') ?? 'not set');
                if (!empty($info['models'])) {
                    $lines[] = "Available Models: " . implode(', ', array_keys($info['models']));
                }
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function getDynamicIntegrationConfig(string $integrationId, ConfigurableIntegration $provider): string
    {
        $meta = $provider->integrationMeta();
        $schema = $provider->configSchema();
        /** @var \App\Models\IntegrationSetting|null $setting */
        $setting = IntegrationSetting::forWorkspace()->where('integration_id', $integrationId)->first();
        $config = $setting ? $setting->config : [];

        $lines = [
            "Integration: {$meta['name']} ({$integrationId})",
            "Description: {$meta['description']}",
            "Enabled: " . (($setting && $setting->enabled) ? 'yes' : 'no'),
        ];

        /** @var array{key: string, type: string, label: string, default?: mixed} $field */
        foreach ($schema as $field) {
            $value = $config[$field['key']] ?? null;

            if ($field['type'] === 'secret') {
                $masked = $setting?->getMaskedValue($field['key']) ?? 'not set';
                $lines[] = "{$field['label']}: {$masked}";
            } elseif ($field['type'] === 'string_list') {
                $items = is_array($value) ? $value : [];
                $lines[] = "{$field['label']}: " . ($items ? implode(', ', $items) : 'none');
            } else {
                $display = $value ?? ($field['default'] ?? 'not set');
                $lines[] = "{$field['label']}: {$display}";
            }
        }

        return implode("\n", $lines);
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'integrationId' => $schema
                ->string()
                ->description('Integration ID (e.g. "telegram", "openai").')
                ->required(),
        ];
    }
}

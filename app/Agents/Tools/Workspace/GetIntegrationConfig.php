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

            $result = [
                'id' => $integrationId,
                'name' => $info['name'],
                'description' => $info['description'],
                'enabled' => (bool) ($setting && $setting->enabled),
                'configured' => (bool) ($setting?->hasValidConfig() ?? false),
                'apiKey' => $setting?->getMaskedApiKey(),
            ];

            if ($integrationId === 'telegram') {
                $result['defaultAgentId'] = $setting?->getConfigValue('default_agent_id');
                $result['notifyChatId'] = $setting?->getConfigValue('notify_chat_id');
                $result['allowedTelegramUsers'] = $setting?->getConfigValue('allowed_telegram_users', []) ?? [];
            } else {
                $result['url'] = $setting?->getConfigValue('url') ?? ($info['default_url'] ?? null);
                $result['defaultModel'] = $setting?->getConfigValue('default_model');
                if (!empty($info['models'])) {
                    $result['availableModels'] = array_keys($info['models']);
                }
            }

            return json_encode($result, JSON_PRETTY_PRINT);
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

        $result = [
            'id' => $integrationId,
            'name' => $meta['name'],
            'description' => $meta['description'],
            'enabled' => (bool) ($setting && $setting->enabled),
        ];

        /** @var array{key: string, type: string, label: string, default?: mixed} $field */
        foreach ($schema as $field) {
            $value = $config[$field['key']] ?? null;

            if ($field['type'] === 'secret') {
                $result[$field['key']] = $setting?->getMaskedValue($field['key']);
            } elseif ($field['type'] === 'string_list') {
                $result[$field['key']] = is_array($value) ? $value : [];
            } else {
                $result[$field['key']] = $value ?? ($field['default'] ?? null);
            }
        }

        return json_encode($result, JSON_PRETTY_PRINT);
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

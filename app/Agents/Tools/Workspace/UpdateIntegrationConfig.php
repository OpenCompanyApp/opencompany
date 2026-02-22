<?php

namespace App\Agents\Tools\Workspace;

use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class UpdateIntegrationConfig implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Update configuration for an integration: API keys, URLs, models, and other settings.';
    }

    public function handle(Request $request): string
    {
        try {
            $integrationId = $request['integrationId'] ?? null;
            if (!$integrationId) {
                return 'integrationId is required.';
            }

            // Check dynamic providers first, then static
            $provider = $this->findConfigurableProvider($integrationId);
            $available = IntegrationSetting::getAvailableIntegrations();

            if (!$provider && !isset($available[$integrationId])) {
                $allIds = array_keys($available);
                foreach (app(ToolProviderRegistry::class)->all() as $p) {
                    if ($p instanceof ConfigurableIntegration) {
                        $allIds[] = $p->appName();
                    }
                }

                return "Integration not found: {$integrationId}. Available: " . implode(', ', $allIds);
            }

            $setting = IntegrationSetting::forWorkspace()->firstOrNew(['integration_id' => $integrationId, 'workspace_id' => workspace()->id]);
            if (!$setting->id) {
                $setting->id = Str::uuid()->toString();
            }

            $config = $setting->config ?? [];

            if ($provider) {
                // Dynamic provider — iterate config schema
                foreach ($provider->configSchema() as $field) {
                    $key = $field['key'];
                    $value = $request[$key] ?? null;

                    // Also accept 'apiKey' param for 'api_key' schema field
                    if ($key === 'api_key' && $value === null) {
                        $value = $request['apiKey'] ?? null;
                    }

                    if ($value === null) {
                        continue;
                    }

                    if ($field['type'] === 'secret') {
                        if (is_string($value) && str_contains($value, '*')) {
                            continue; // Skip masked values
                        }
                        $config[$key] = $value;
                    } elseif ($field['type'] === 'string_list') {
                        if (is_array($value)) {
                            $config[$key] = $value;
                        } elseif (is_string($value)) {
                            $decoded = json_decode($value, true);
                            $config[$key] = is_array($decoded) ? $decoded : [];
                        }
                    } else {
                        $config[$key] = $value;
                    }
                }
            } else {
                // Static provider — existing logic
                if (!empty($request['apiKey']) && !str_contains($request['apiKey'], '*')) {
                    $config['api_key'] = $request['apiKey'];
                }

                if (isset($request['url'])) {
                    $config['url'] = $request['url'] ?: ($available[$integrationId]['default_url'] ?? '');
                }

                if (isset($request['defaultModel'])) {
                    $config['default_model'] = $request['defaultModel'];
                }

                // Telegram-specific
                if (isset($request['defaultAgentId'])) {
                    $config['default_agent_id'] = $request['defaultAgentId'];
                }
                if (isset($request['notifyChatId'])) {
                    $config['notify_chat_id'] = $request['notifyChatId'];
                }
                if (isset($request['allowedTelegramUsers'])) {
                    $value = $request['allowedTelegramUsers'];
                    if (is_array($value)) {
                        $config['allowed_telegram_users'] = $value;
                    } elseif (is_string($value)) {
                        $decoded = json_decode($value, true);
                        $config['allowed_telegram_users'] = is_array($decoded) ? $decoded : [];
                    } else {
                        $config['allowed_telegram_users'] = [];
                    }
                }
            }

            $setting->config = $config;

            if (isset($request['enabled'])) {
                $setting->enabled = filter_var($request['enabled'], FILTER_VALIDATE_BOOLEAN);
            }

            $setting->save();

            // Build response
            $name = $provider ? $provider->integrationMeta()['name'] : ($available[$integrationId]['name'] ?? $integrationId);
            $info = "Integration '{$name}' updated. Enabled: " . ($setting->enabled ? 'yes' : 'no') . '.';

            if ($provider) {
                // Show stored fields from schema (mask secrets)
                foreach ($provider->configSchema() as $field) {
                    $stored = $config[$field['key']] ?? null;
                    if ($stored === null || $stored === '' || $stored === []) {
                        continue;
                    }
                    if ($field['type'] === 'secret') {
                        $info .= " {$field['label']}: " . $setting->getMaskedValue($field['key']) . '.';
                    } elseif ($field['type'] === 'string_list') {
                        $info .= " {$field['label']}: " . implode(', ', $stored) . '.';
                    } else {
                        $info .= " {$field['label']}: {$stored}.";
                    }
                }
            } else {
                $maskedKey = $setting->getMaskedApiKey();
                $info .= " API Key: {$maskedKey}. Configured: " . ($setting->hasValidConfig() ? 'yes' : 'no') . '.';

                if ($integrationId === 'telegram') {
                    $allowedUsers = $config['allowed_telegram_users'] ?? [];
                    $defaultAgent = $config['default_agent_id'] ?? 'none';
                    $notifyChat = $config['notify_chat_id'] ?? 'none';
                    $info .= " Default agent: {$defaultAgent}. Notify chat: {$notifyChat}. Allowed users: " . (empty($allowedUsers) ? 'all (unrestricted)' : implode(', ', $allowedUsers)) . '.';
                }
            }

            return $info;
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
            'apiKey' => $schema
                ->string()
                ->description('Raw API key/token for the integration.'),
            'url' => $schema
                ->string()
                ->description('API base URL (for OpenAI-compatible providers, Plausible, etc.).'),
            'defaultModel' => $schema
                ->string()
                ->description('Default model ID for AI providers.'),
            'enabled' => $schema
                ->string()
                ->description("'true' or 'false' to enable/disable the integration."),
            'defaultAgentId' => $schema
                ->string()
                ->description('Telegram: default agent UUID to handle messages.'),
            'notifyChatId' => $schema
                ->string()
                ->description('Telegram: chat ID for notifications.'),
            'allowedTelegramUsers' => $schema
                ->string()
                ->description('Telegram: JSON array of allowed user IDs, e.g. ["123456","789012"].'),
            'sites' => $schema
                ->string()
                ->description('JSON array of site domains for string_list fields, e.g. ["example.com"].'),
        ];
    }
}
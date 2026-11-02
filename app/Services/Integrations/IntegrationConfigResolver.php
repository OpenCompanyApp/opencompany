<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSetting;
use Illuminate\Http\Request;

/**
 * Reads and writes workspace integration configuration.
 *
 * This service is the app boundary around the remaining app-owned settings
 * (AI providers, Telegram, chat adapters): masking, account aliases and
 * persisted enablement. The per-package configuration path was removed with
 * the integration package system; external systems become Connections with
 * vault-backed secrets (docs/planning/connections-events-routines.md).
 */
class IntegrationConfigResolver
{
    public function __construct(
        private IntegrationAccountResolver $accounts,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function show(string $id, ?string $account = null): ?array
    {
        $id = IntegrationIdentity::rawId($id);
        $available = IntegrationSetting::getAvailableIntegrations();
        if (! isset($available[$id])) {
            return null;
        }

        return $this->showStatic($id, $available[$id], $account);
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function update(Request $request, string $id, ?string $account = null): array
    {
        $id = IntegrationIdentity::rawId($id);
        $available = IntegrationSetting::getAvailableIntegrations();
        if (! isset($available[$id])) {
            return [['error' => 'Integration not found'], 404];
        }

        return [$this->updateStatic($request, $id, $available[$id], $account), 200];
    }

    /**
     * Read only the static AI-provider configuration shape. This deliberately
     * bypasses package providers with colliding slugs such as openai so the
     * model-runtime UI and API-tool integration UI cannot overwrite each other.
     *
     * @return array<string, mixed>|null
     */
    public function showAiProvider(string $id, ?string $account = null): ?array
    {
        $id = IntegrationIdentity::rawId($id);
        $available = IntegrationSetting::getAvailableIntegrations();
        if (! isset($available[$id]) || ($available[$id]['category'] ?? null) !== 'ai-models') {
            return null;
        }

        return $this->showStatic($id, $available[$id], $account);
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function updateAiProvider(Request $request, string $id, ?string $account = null): array
    {
        $id = IntegrationIdentity::rawId($id);
        $available = IntegrationSetting::getAvailableIntegrations();
        if (! isset($available[$id]) || ($available[$id]['category'] ?? null) !== 'ai-models') {
            return [['error' => 'AI provider not found'], 404];
        }

        return [$this->updateStatic($request, $id, $available[$id], $account), 200];
    }

    /**
     * @param  array<string, mixed>  $configFields
     * @return list<array<string, mixed>>
     */
    public static function buildStaticConfigSchema(array $configFields): array
    {
        $schema = [];
        foreach ($configFields as $key => $field) {
            $schema[] = [
                'key' => $key,
                'type' => match ($field['type']) {
                    'array' => 'string_list',
                    'agent_select' => 'text',
                    default => $field['type'],
                },
                'label' => $field['label'],
                'required' => $field['required'] ?? false,
                'placeholder' => $field['placeholder'] ?? null,
                'hint' => $field['hint'] ?? null,
            ];
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $info
     * @return array<string, mixed>
     */
    private function showStatic(string $id, array $info, ?string $account): array
    {
        $setting = $this->accounts->findSetting($id, $account);
        $configFields = $info['config_fields'] ?? null;

        if ($configFields) {
            $schema = self::buildStaticConfigSchema($configFields);

            return [
                'id' => $id,
                'name' => $info['name'],
                'description' => $info['description'],
                'icon' => $info['icon'] ?? 'ph:gear',
                'enabled' => $setting ? $setting->enabled : false,
                'config' => $this->readConfig($schema, $setting),
                'configSchema' => $schema,
            ];
        }

        return [
            'id' => $id,
            'name' => $info['name'],
            'description' => $info['description'],
            'icon' => $info['icon'] ?? 'ph:gear',
            'models' => $info['models'] ?? null,
            'defaultUrl' => $info['default_url'] ?? null,
            'apiFormat' => $info['api_format'] ?? null,
            'apiKeyUrl' => $info['api_key_url'] ?? null,
            'enabled' => $setting ? $setting->enabled : false,
            'config' => [
                'apiKey' => $setting?->getMaskedApiKey(),
                'url' => $setting?->getConfigValue('url') ?? ($info['default_url'] ?? ''),
                'defaultModel' => $setting?->getConfigValue('default_model') ?? array_key_first($info['models'] ?? []),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $schema
     * @return array<string, mixed>
     */
    private function readConfig(array $schema, ?IntegrationSetting $setting): array
    {
        $config = [];
        foreach ($schema as $field) {
            $key = $field['key'];
            if ($field['type'] === 'secret' || $field['type'] === 'oauth_connect') {
                // Never return raw secrets to the browser. The masked value is
                // only a placeholder that lets update/test flows know an
                // existing secret may be reused.
                $config[$key] = $setting?->getMaskedValue($key);
            } else {
                $config[$key] = $setting?->getConfigValue($key, $field['default'] ?? null)
                    ?? ($field['default'] ?? null);
            }
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $info
     * @return array<string, mixed>
     */
    private function updateStatic(Request $request, string $id, array $info, ?string $account): array
    {
        $setting = $this->accounts->findOrNewSetting($id, $account);
        $config = $setting->config ?? [];

        if ($configFields = ($info['config_fields'] ?? null)) {
            $setting->config = $this->writeSchemaConfig($request, self::buildStaticConfigSchema($configFields), $config);
            $setting->enabled = $request->input('enabled', true);
            $setting->save();

            return [
                'success' => true,
                'enabled' => $setting->enabled,
                'configured' => $setting->hasValidConfig(),
            ];
        }

        $request->validate([
            'apiKey' => 'nullable|string',
            'url' => 'nullable|string|url',
            'defaultModel' => 'nullable|string',
            'enabled' => 'nullable|boolean',
        ]);

        if ($request->has('apiKey') && $request->input('apiKey') && ! str_contains($request->input('apiKey'), '*')) {
            $config['api_key'] = $request->input('apiKey');
        }
        if ($request->has('url')) {
            $config['url'] = $request->input('url') ?: ($info['default_url'] ?? '');
        }
        if ($request->has('defaultModel')) {
            $config['default_model'] = $request->input('defaultModel');
        }

        $setting->config = $config;
        if ($request->has('enabled')) {
            $setting->enabled = $request->input('enabled');
        }
        $setting->save();

        return [
            'success' => true,
            'enabled' => $setting->enabled,
            'configured' => $setting->hasValidConfig(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $schema
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function writeSchemaConfig(Request $request, array $schema, array $config): array
    {
        foreach ($schema as $field) {
            $key = $field['key'];
            if (! $request->has($key)) {
                continue;
            }

            if ($field['type'] === 'oauth_connect') {
                continue;
            }

            if ($field['type'] === 'secret') {
                $value = $request->input($key);
                if (! $value || str_contains($value, '*')) {
                    // Masked/blank secrets mean "keep the stored value." This
                    // avoids overwriting working credentials with UI masks.
                    continue;
                }
            }

            $config[$key] = $request->input($key, $field['default'] ?? null);
        }

        return $config;
    }

}

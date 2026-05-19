<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSetting;
use Illuminate\Http\Request;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

/**
 * Reads and writes workspace integration configuration.
 *
 * This service is the app boundary around integration settings. Package
 * providers own their schema and validation rules; OpenCompany owns masking,
 * account aliases, persisted enablement, and compatibility with legacy static
 * provider entries.
 */
class IntegrationConfigResolver
{
    public function __construct(
        private ToolProviderRegistry $registry,
        private IntegrationAccountResolver $accounts,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function show(string $id, ?string $account = null): ?array
    {
        $provider = $this->findConfigurableProvider($id);
        if ($provider) {
            return $this->showConfigurable($provider, $account);
        }

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
        $provider = $this->findConfigurableProvider($id);
        if ($provider) {
            return [$this->updateConfigurable($request, $provider, $account), 200];
        }

        $available = IntegrationSetting::getAvailableIntegrations();
        if (! isset($available[$id])) {
            return [['error' => 'Integration not found'], 404];
        }

        return [$this->updateStatic($request, $id, $available[$id], $account), 200];
    }

    /**
     * Clear OAuth token fields for a configurable integration account.
     */
    public function disconnect(string $id, ?string $account = null): ?bool
    {
        $provider = $this->findConfigurableProvider($id);
        if (! $provider) {
            return null;
        }

        $setting = $this->accounts->findSetting($id, $account);
        if (! $setting) {
            return true;
        }

        $config = $setting->config ?? [];
        foreach (ConfigSchemaNormalizer::normalize($provider->configSchema()) as $field) {
            if ($field['type'] === 'oauth_connect') {
                unset($config[$field['key']]);
            }
        }

        $setting->config = $config;
        $setting->enabled = false;
        $setting->save();

        return true;
    }

    public function findConfigurableProvider(string $id): ?ConfigurableIntegration
    {
        $provider = $this->registry->get($id);

        return $provider instanceof ConfigurableIntegration ? $provider : null;
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
     * @return array<string, mixed>
     */
    private function showConfigurable(ConfigurableIntegration $provider, ?string $account): array
    {
        $id = $provider->appName();
        $setting = $this->accounts->findSetting($id, $account);
        $schema = ConfigSchemaNormalizer::normalize($provider->configSchema());
        $meta = $provider->integrationMeta();
        $config = $this->readConfig($schema, $setting);

        // OAuth providers in the same vendor family often share client
        // credentials. Fill masked/read-only display values from sibling
        // settings so users do not have to duplicate secrets in every panel.
        $this->fillSharedCredentials($id, $account, $config);

        return [
            'id' => $id,
            'name' => $meta['name'],
            'description' => $meta['description'],
            'icon' => $meta['icon'] ?? 'ph:gear',
            'logo' => $meta['logo'] ?? null,
            'category' => $meta['category'] ?? null,
            'docsUrl' => $meta['docs_url'] ?? null,
            'enabled' => $setting ? $setting->enabled : false,
            'config' => $config,
            'configSchema' => $schema,
        ];
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
     * @param  array<string, mixed>  $config
     */
    private function fillSharedCredentials(string $id, ?string $account, array &$config): void
    {
        $group = $this->accounts->sharedCredentialGroup($id);
        if ($group['integration_ids'] === [] || $group['keys'] === []) {
            return;
        }

        foreach ($group['keys'] as $sharedKey) {
            if (! empty($config[$sharedKey])) {
                continue;
            }

            foreach ($group['integration_ids'] as $sibling) {
                if ($sibling === $id) {
                    continue;
                }

                $siblingSetting = $this->accounts->findSetting($sibling, $account);
                $siblingVal = $siblingSetting?->getConfigValue($sharedKey);
                if (! empty($siblingVal) && is_string($siblingVal)) {
                    $config[$sharedKey] = $sharedKey === 'client_secret'
                        ? $siblingSetting->getMaskedValue($sharedKey)
                        : $siblingVal;
                    break;
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function updateConfigurable(Request $request, ConfigurableIntegration $provider, ?string $account): array
    {
        $request->validate(array_merge($provider->validationRules(), ['enabled' => 'nullable|boolean']));

        $id = $provider->appName();
        $setting = $this->accounts->findOrNewSetting($id, $account);
        $config = $this->writeSchemaConfig($request, ConfigSchemaNormalizer::normalize($provider->configSchema()), $setting->config ?? []);

        // Shared credential copying happens after request parsing so explicit
        // request values always win over sibling defaults.
        $this->copySharedCredentials($id, $account, $config);

        $setting->config = $config;
        $setting->enabled = $request->input('enabled', true);
        $setting->save();

        return [
            'success' => true,
            'enabled' => $setting->enabled,
            'configured' => $setting->hasValidConfig(),
        ];
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

    /**
     * @param  array<string, mixed>  $config
     */
    private function copySharedCredentials(string $id, ?string $account, array &$config): void
    {
        $group = $this->accounts->sharedCredentialGroup($id);
        if ($group['integration_ids'] === [] || $group['keys'] === []) {
            return;
        }

        foreach ($group['integration_ids'] as $sibling) {
            if ($sibling === $id) {
                continue;
            }

            foreach ($group['keys'] as $sharedKey) {
                if (! empty($config[$sharedKey])) {
                    continue;
                }

                $siblingVal = $this->accounts->findSetting($sibling, $account)?->getConfigValue($sharedKey);
                if (! empty($siblingVal) && is_string($siblingVal)) {
                    $config[$sharedKey] = $siblingVal;
                    break;
                }
            }
        }
    }
}

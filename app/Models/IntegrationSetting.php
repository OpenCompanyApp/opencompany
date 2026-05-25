<?php

namespace App\Models;

use App\Domain\Ai\Catalog\AiCatalog;
use App\Models\Concerns\BelongsToWorkspace;
use App\Services\Integrations\ConfigSchemaNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

/**
 * Workspace integration configuration, including encrypted provider secrets.
 *
 * One integration can have multiple account aliases. The default/unaliased row
 * is used by ordinary runtime calls, while named accounts can be selected by
 * Lua/package tools. Never expose raw config values from this model to clients.
 *
 * @property array<string, mixed> $config
 * @property string $workspace_id
 * @property bool $enabled
 * @property string $integration_id
 * @property string $account_alias
 * @property bool $is_default
 */
class IntegrationSetting extends Model
{
    /** @use HasFactory<Factory<self>> */
    use BelongsToWorkspace, HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'integration_id',
        'account_alias',
        'config',
        'enabled',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Scope to a specific account alias.
     *
     * Null targets the default account. Empty string targets the legacy
     * un-aliased account explicitly.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForAccount($query, ?string $account)
    {
        if ($account === null) {
            return $query->where(function ($q) {
                $q->where('is_default', true)->orWhere('account_alias', '');
            })->orderByDesc('is_default');
        }

        $alias = $account === '' ? '' : $account;

        return $query->where('account_alias', $alias);
    }

    /**
     * Scope to the default account (is_default = true or the un-aliased row).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDefault($query)
    {
        return $query->where(function ($q) {
            $q->where('is_default', true)->orWhere('account_alias', '');
        })->orderBy('is_default');
    }

    /**
     * Get all non-default account aliases for an integration in the current workspace.
     *
     * @return list<string>
     */
    public static function getAccountsFor(string $integrationId): array
    {
        $query = app()->bound('currentWorkspace')
            ? static::forWorkspace()
            : static::query();

        return $query
            ->where('integration_id', $integrationId)
            ->where('account_alias', '!=', '')
            ->pluck('account_alias')
            ->values()
            ->all();
    }

    /**
     * Get a specific config value
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set a specific config value
     */
    public function setConfigValue(string $key, mixed $value): void
    {
        $config = $this->config ?? [];
        $config[$key] = $value;
        $this->config = $config;
    }

    /**
     * Get the masked API key for display
     */
    public function getMaskedApiKey(): ?string
    {
        return $this->getMaskedValue('api_key');
    }

    /**
     * Get a masked version of any secret config value.
     */
    public function getMaskedValue(string $key): ?string
    {
        $value = $this->getConfigValue($key);
        if (! $value || ! is_string($value)) {
            return null;
        }

        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4).str_repeat('*', $length - 8).substr($value, -4);
    }

    /**
     * Check if this integration has a valid configuration
     */
    public function hasValidConfig(): bool
    {
        $requiredKeys = $this->requiredCredentialKeys();

        if ($requiredKeys === []) {
            // Legacy/static integrations do not expose a package credential
            // schema, so treat any common secret field as enough configuration.
            $requiredKeys = [
                'api_key',
                'api_token',
                'access_token',
                'refresh_token',
                'token',
                'bearer_token',
                'client_secret',
                'password',
            ];

            foreach ($requiredKeys as $key) {
                if ($this->filledConfigValue($key)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($requiredKeys as $key) {
            if (! $this->filledConfigValue($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function requiredCredentialKeys(): array
    {
        if ($this->integration_id === 'telegram') {
            return ['api_key'];
        }

        try {
            if (! app()->bound(ToolProviderRegistry::class)) {
                return [];
            }

            $provider = app(ToolProviderRegistry::class)->get($this->integration_id);
            if ($provider === null) {
                return [];
            }

            $credentialFields = ConfigSchemaNormalizer::normalize($provider->credentialFields());
            $required = [];

            // Package credential metadata is preferred because it is owned by
            // the integration package and can differ per provider.
            foreach ($credentialFields as $field) {
                if (($field['required'] ?? false) && isset($field['key'])) {
                    $required[] = (string) $field['key'];
                }
            }

            if ($required !== []) {
                return array_values(array_unique($required));
            }

            if ($provider instanceof ConfigurableIntegration) {
                foreach (ConfigSchemaNormalizer::normalize($provider->configSchema()) as $field) {
                    if (($field['required'] ?? false) && isset($field['key'])) {
                        $required[] = (string) $field['key'];
                    }
                }
            }

            return array_values(array_unique($required));
        } catch (\Throwable) {
            return [];
        }
    }

    private function filledConfigValue(string $key): bool
    {
        $value = $this->getConfigValue($key);

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return ! empty($value);
    }

    /**
     * Available integration types (config metadata + DB-stored models).
     */
    /** @return array<string, mixed> */
    public static function getAvailableIntegrations(): array
    {
        $base = array_merge(
            self::catalogAiIntegrations(),
            config('integrations', []),
            config('chat_integrations', []),
        );

        try {
            $settings = (app()->bound('currentWorkspace') ? static::forWorkspace()->default()->get() : static::query()->default()->get())->keyBy('integration_id');
        } catch (\Throwable) {
            return $base;
        }

        foreach ($base as $id => &$info) {
            /** @var self|null $setting */
            $setting = $settings->get($id);
            $models = $setting?->getConfigValue('models');
            if (is_array($models) && ! empty($models)) {
                // Workspace-configured model lists override catalog defaults
                // so admins can expose custom aliases or restrict choices.
                $info['models'] = $models;
            }
        }

        return $base;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function catalogAiIntegrations(): array
    {
        try {
            $catalog = app(AiCatalog::class);
            $providers = [];

            foreach ($catalog->providers() as $provider) {
                if ($provider->authMode === 'oauth') {
                    // OAuth providers are surfaced through dedicated auth flows,
                    // not generic API-key integration cards.
                    continue;
                }

                $models = [];

                foreach ($provider->models as $model) {
                    $models[$model->id] = $model->label;
                }

                $providers[$provider->id] = [
                    'category' => 'ai-models',
                    'name' => $provider->name,
                    'description' => $provider->description,
                    'icon' => $provider->icon,
                    'default_url' => $provider->defaultUrl,
                    'api_format' => $provider->apiFormat,
                    'api_key_url' => $provider->apiKeyUrl,
                    'models' => $models,
                    'driver' => $provider->driver,
                    'auth' => $provider->authMode,
                ];
            }

            return $providers;
        } catch (\Throwable) {
            return [];
        }
    }
}

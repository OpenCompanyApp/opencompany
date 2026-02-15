<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToWorkspace;

/**
 * @property array<string, mixed> $config
 * @property bool $enabled
 * @property string $integration_id
 */
class IntegrationSetting extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory, BelongsToWorkspace;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'integration_id',
        'config',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'enabled' => 'boolean',
        ];
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
        if (!$value || !is_string($value)) {
            return null;
        }

        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', $length - 8) . substr($value, -4);
    }

    /**
     * Check if this integration has a valid configuration
     */
    public function hasValidConfig(): bool
    {
        return !empty($this->getConfigValue('api_key'));
    }

    /**
     * Available integration types (config metadata + DB-stored models).
     */
    /** @return array<string, mixed> */
    public static function getAvailableIntegrations(): array
    {
        $base = config('integrations', []);

        try {
            $settings = (app()->bound('currentWorkspace') ? static::forWorkspace()->get() : static::all())->keyBy('integration_id');
        } catch (\Throwable) {
            return $base;
        }

        foreach ($base as $id => &$info) {
            /** @var self|null $setting */
            $setting = $settings->get($id);
            $models = $setting?->getConfigValue('models');
            if (is_array($models) && !empty($models)) {
                $info['models'] = $models;
            }
        }

        return $base;
    }
}

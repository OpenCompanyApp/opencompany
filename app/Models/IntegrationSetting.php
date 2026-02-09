<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $config
 * @property bool $enabled
 * @property string $integration_id
 */
class IntegrationSetting extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
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
     * Available integration types (from config/integrations.php).
     */
    public static function getAvailableIntegrations(): array
    {
        return config('integrations', []);
    }
}

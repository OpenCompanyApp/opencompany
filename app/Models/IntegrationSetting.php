<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        $apiKey = $this->getConfigValue('api_key');
        if (!$apiKey) {
            return null;
        }

        $length = strlen($apiKey);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($apiKey, 0, 4) . str_repeat('*', $length - 8) . substr($apiKey, -4);
    }

    /**
     * Check if this integration has a valid configuration
     */
    public function hasValidConfig(): bool
    {
        return !empty($this->getConfigValue('api_key'));
    }

    /**
     * Available integration types
     */
    public static function getAvailableIntegrations(): array
    {
        return [
            'glm' => [
                'name' => 'GLM (Zhipu AI)',
                'description' => 'General-purpose Chinese LLM',
                'icon' => 'ph:brain',
                'models' => [
                    'glm-4-plus' => 'GLM 4 Plus (Most Capable)',
                    'glm-4' => 'GLM 4',
                    'glm-4-air' => 'GLM 4 Air (Balanced)',
                    'glm-4-flash' => 'GLM 4 Flash (Fast)',
                ],
                'default_url' => 'https://open.bigmodel.cn/api/paas/v4',
            ],
            'glm-coding' => [
                'name' => 'GLM Coding Plan',
                'description' => 'Specialized coding LLM via Zhipu Coding Plan',
                'icon' => 'ph:code',
                'models' => [
                    'glm-4.7' => 'GLM 4.7 (Coding Optimized)',
                ],
                'default_url' => 'https://api.z.ai/api/coding/paas/v4',
            ],
            'telegram' => [
                'name' => 'Telegram',
                'description' => 'Telegram Bot for DMs, notifications, and approvals',
                'icon' => 'ph:telegram-logo',
            ],
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AppSetting extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'key',
        'category',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Get a setting value by key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Resolve a provider:model pair from AppSetting with config fallback.
     *
     * For combined config keys (provider:model in one key):
     *   resolveProviderModel('memory_summary_model', 'memory.compaction.summary_model')
     *
     * For split config keys (separate provider + model keys):
     *   resolveProviderModel('memory_embedding_model', 'memory.embedding.provider', 'memory.embedding.model')
     *
     * @return array{0: string, 1: string}  [provider, model]
     */
    public static function resolveProviderModel(string $settingKey, string $configKey, ?string $configModelKey = null): array
    {
        $value = static::getValue($settingKey);

        if ($value === null) {
            $value = $configModelKey
                ? config($configKey).':'.config($configModelKey)
                : config($configKey);
        }

        $parts = explode(':', (string) $value, 2);

        return [$parts[0], $parts[1] ?? $parts[0]];
    }

    /**
     * Set a setting value (upsert).
     */
    public static function setValue(string $key, mixed $value, string $category = 'general'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'id' => static::where('key', $key)->value('id') ?? Str::uuid()->toString(),
                'category' => $category,
                'value' => $value,
            ]
        );
    }

    /**
     * Get all settings in a category as key-value pairs.
     */
    public static function getByCategory(string $category): array
    {
        return static::where('category', $category)
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Batch upsert settings for a category.
     */
    public static function setMany(array $settings, string $category): void
    {
        foreach ($settings as $key => $value) {
            static::setValue($key, $value, $category);
        }
    }

    /**
     * Get all settings grouped by category.
     */
    public static function allGrouped(): array
    {
        $settings = static::all();
        $grouped = [];

        foreach ($settings as $setting) {
            $grouped[$setting->category][$setting->key] = $setting->value;
        }

        return $grouped;
    }

    /**
     * Default settings with their categories and values.
     */
    public static function defaults(): array
    {
        return [
            'organization' => [
                'org_name' => '',
                'org_email' => '',
                'org_timezone' => 'UTC',
            ],
            'agents' => [
                'default_behavior' => 'supervised',
                'auto_spawn' => false,
                'budget_approval_threshold' => 0,
            ],
            'notifications' => [
                'email_notifications' => true,
                'slack_notifications' => false,
                'daily_summary' => true,
            ],
            'policies' => [
                'action_policies' => [],
            ],
            'memory' => [
                'memory_embedding_model' => config('memory.embedding.provider').':'.config('memory.embedding.model'),
                'memory_summary_model' => config('memory.compaction.summary_model'),
                'memory_compaction_enabled' => config('memory.compaction.enabled', true),
                'memory_reranking_enabled' => config('memory.reranking.enabled', true),
                'memory_reranking_model' => config('memory.reranking.provider').':'.config('memory.reranking.model'),
                'model_context_windows' => [],
            ],
        ];
    }

    /**
     * Get all settings grouped by category, merged with defaults.
     */
    public static function allWithDefaults(): array
    {
        $stored = static::allGrouped();
        $defaults = static::defaults();

        $result = [];
        foreach ($defaults as $category => $keys) {
            foreach ($keys as $key => $default) {
                $result[$category][$key] = $stored[$category][$key] ?? $default;
            }
        }

        return $result;
    }
}

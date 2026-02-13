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
                'memory_embedding_model' => 'openai:text-embedding-3-small',
                'memory_summary_model' => 'anthropic:claude-sonnet-4-5-20250929',
                'memory_compaction_enabled' => true,
                'memory_reranking_enabled' => true,
                'memory_reranking_model' => 'ollama:dengcao/Qwen3-Reranker-0.6B:Q8_0',
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

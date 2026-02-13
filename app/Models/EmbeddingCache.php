<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmbeddingCache extends Model
{
    protected $table = 'embedding_cache';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'provider',
        'model',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    /**
     * Generate a cache key from provider + model + content.
     */
    public static function cacheKey(string $provider, string $model, string $content): string
    {
        return hash('sha256', "{$provider}:{$model}:{$content}");
    }
}

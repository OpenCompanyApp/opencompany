<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToWorkspace;

class EmbeddingCache extends Model
{
    use BelongsToWorkspace;

    protected $table = 'embedding_cache';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
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

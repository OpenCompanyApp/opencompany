<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $key_hash
 * @property string $key_prefix
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PrismApiKey extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'key_hash',
        'key_prefix',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Generate a new API key.
     *
     * @return array{key: self, plainTextKey: string}
     */
    public static function generateKey(string $name): array
    {
        $plainText = 'ps_live_' . bin2hex(random_bytes(24));

        $key = static::create([
            'name' => $name,
            'key_hash' => hash('sha256', $plainText),
            'key_prefix' => substr($plainText, 0, 16),
        ]);

        return ['key' => $key, 'plainTextKey' => $plainText];
    }

    /**
     * Find a key by its plain-text value.
     */
    public static function findByPlainText(string $plainText): ?self
    {
        return static::where('key_hash', hash('sha256', $plainText))->first();
    }

    /**
     * Check if the key has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Get a masked display version of the key.
     */
    public function getMaskedKeyAttribute(): string
    {
        return $this->key_prefix . str_repeat('•', 32) . '...';
    }
}

<?php

namespace App\Domain\Ai\Codex;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Encrypted single-row token model for OpenCompany's Codex integration.
 *
 * This model owns only persistence. Runtime code should depend on the
 * CodexTokenStore contract so auth refresh can stay isolated from Eloquent.
 *
 * @property string $access_token
 * @property string $refresh_token
 * @property Carbon $expires_at
 * @property string|null $account_id
 * @property string|null $email
 * @property array<string, mixed>|null $token_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CodexTokenStore extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'token_data' => 'encrypted:array',
        ];
    }

    public function getTable(): string
    {
        return config('codex.table', 'codex_tokens');
    }

    public static function current(): ?self
    {
        return static::query()->latest()->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function store(array $data): self
    {
        $existing = static::query()->first();

        if ($existing) {
            $existing->update($data);

            return $existing->fresh();
        }

        return static::create($data);
    }

    public static function clear(): void
    {
        static::query()->delete();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $bufferSeconds = 60): bool
    {
        return $this->expires_at->subSeconds($bufferSeconds)->isPast();
    }
}

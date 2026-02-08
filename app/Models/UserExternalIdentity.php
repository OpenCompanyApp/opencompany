<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read User|null $user
 */
class UserExternalIdentity extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'provider',
        'external_id',
        'display_name',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resolve the system user linked to an external identity.
     */
    public static function resolveUser(string $provider, string $externalId): ?User
    {
        $identity = static::where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();

        return $identity?->user;
    }
}

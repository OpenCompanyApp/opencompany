<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Channel membership row (channel_members), also the pivot model for
 * Channel::users() and User::channels() so attach() assigns a UUID too.
 *
 * Keyed by UUID for the archive round trip (pillar 11): no app record may
 * hide meaning in an auto-increment id.
 */
class ChannelMember extends Pivot
{
    /** @use HasFactory<\Database\Factories\ChannelMemberFactory> */
    use HasFactory, HasUuids;

    protected $table = 'channel_members';

    public $incrementing = false;

    protected $keyType = 'string';
    protected $fillable = [
        'channel_id',
        'user_id',
        'role',
        'unread_count',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

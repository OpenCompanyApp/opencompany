<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\DirectMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectMessage extends Model
{
    /** @use HasFactory<DirectMessageFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'participant_key',
        'user1_id',
        'user2_id',
        'channel_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DirectMessage $directMessage): void {
            $participants = [$directMessage->user1_id, $directMessage->user2_id];
            sort($participants, SORT_STRING);
            $directMessage->participant_key = implode(':', $participants);
            $directMessage->workspace_id ??= Channel::query()->whereKey($directMessage->channel_id)->value('workspace_id');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}

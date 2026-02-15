<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Models\Concerns\BelongsToWorkspace;

class CalendarFeed extends Model
{
    use HasUuids, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'token',
        'name',
    ];

    protected static function booted(): void
    {
        static::creating(function (CalendarFeed $feed) {
            if (empty($feed->token)) {
                $feed->token = Str::random(48);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

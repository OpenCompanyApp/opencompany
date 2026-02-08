<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $status
 * @property string $event_id
 * @property string $user_id
 */
class CalendarEventAttendee extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_id',
        'user_id',
        'status',
    ];

    public function toArray()
    {
        $array = parent::toArray();

        $array['eventId'] = $this->event_id;
        $array['userId'] = $this->user_id;

        return $array;
    }

    /** @return BelongsTo<CalendarEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'event_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

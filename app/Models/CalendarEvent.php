<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToWorkspace;

/**
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property string|null $color
 * @property string|null $recurrence_rule
 * @property bool $all_day
 * @property string|null $created_by
 * @property \Carbon\Carbon|null $start_at
 * @property \Carbon\Carbon|null $end_at
 * @property \Carbon\Carbon|null $recurrence_end
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class CalendarEvent extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory, HasUuids, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'title',
        'description',
        'start_at',
        'end_at',
        'all_day',
        'location',
        'color',
        'recurrence_rule',
        'recurrence_end',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'all_day' => 'boolean',
            'recurrence_end' => 'datetime',
        ];
    }

    public function toArray()
    {
        $array = parent::toArray();

        $array['startAt'] = $this->start_at;
        $array['endAt'] = $this->end_at;
        $array['allDay'] = $this->all_day;
        $array['createdBy'] = $this->created_by;
        $array['recurrenceRule'] = $this->recurrence_rule;
        $array['recurrenceEnd'] = $this->recurrence_end;
        $array['isRecurrenceInstance'] = $this->getAttribute('is_recurrence_instance') ?? false;
        $array['originalEventId'] = $this->getAttribute('original_event_id');
        $array['createdAt'] = $this->created_at;
        $array['updatedAt'] = $this->updated_at;

        return $array;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CalendarEventAttendee, $this> */
    public function attendees(): HasMany
    {
        return $this->hasMany(CalendarEventAttendee::class, 'event_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<User, $this> */
    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'calendar_event_attendees', 'event_id', 'user_id')
            ->withPivot('status')
            ->withTimestamps();
    }
}

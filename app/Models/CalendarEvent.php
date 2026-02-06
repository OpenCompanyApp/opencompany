<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'description',
        'start_at',
        'end_at',
        'all_day',
        'location',
        'color',
        'recurrence_rule',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'all_day' => 'boolean',
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
        $array['createdAt'] = $this->created_at;
        $array['updatedAt'] = $this->updated_at;

        return $array;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(CalendarEventAttendee::class, 'event_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'calendar_event_attendees', 'event_id', 'user_id')
            ->withPivot('status')
            ->withTimestamps();
    }
}

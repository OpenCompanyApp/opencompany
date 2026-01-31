<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'avatar',
        'type',
        'agent_type',
        'status',
        'presence',
        'last_seen_at',
        'current_task',
        'email',
        'password',
        'is_temporary',
        'manager_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_temporary' => 'boolean',
        ];
    }

    /**
     * Override toArray to add camelCase versions for frontend compatibility
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Add camelCase versions of snake_case fields
        $array['agentType'] = $this->agent_type;
        $array['lastSeenAt'] = $this->last_seen_at;
        $array['currentTask'] = $this->current_task;
        $array['isTemporary'] = $this->is_temporary;
        $array['managerId'] = $this->manager_id;

        return $array;
    }

    // Relationships
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function activitySteps(): HasMany
    {
        return $this->hasMany(ActivityStep::class);
    }

    public function channelMemberships(): HasMany
    {
        return $this->hasMany(ChannelMember::class);
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_members')
            ->withPivot('unread_count', 'joined_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'author_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'author_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'actor_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'created_by');
    }

    public function calendarAttendances()
    {
        return $this->belongsToMany(CalendarEvent::class, 'calendar_event_attendees', 'user_id', 'event_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function dataTables(): HasMany
    {
        return $this->hasMany(DataTable::class, 'created_by');
    }

    // Helper methods
    public function isAgent(): bool
    {
        return $this->type === 'agent';
    }

    public function isHuman(): bool
    {
        return $this->type === 'human';
    }
}

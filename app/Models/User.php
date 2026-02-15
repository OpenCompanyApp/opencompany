<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string|null $status
 * @property string|null $type
 * @property string|null $workspace_id
 * @property array<string>|null $awaiting_delegation_ids
 * @property \Carbon\Carbon|null $sleeping_until
 * @property \Carbon\Carbon|null $bootstrapped_at
 * @property \Carbon\Carbon|null $last_seen_at
 * @property \Carbon\Carbon|null $email_verified_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'avatar',
        'type',
        'agent_type',
        'brain',
        'docs_folder_id',
        'status',
        'presence',
        'last_seen_at',
        'current_task',
        'email',
        'password',
        'is_ephemeral',
        'behavior_mode',
        'awaiting_approval_id',
        'awaiting_delegation_ids',
        'must_wait_for_approval',
        'sleeping_until',
        'sleeping_reason',
        'bootstrapped_at',
        'manager_id',
        'workspace_id',
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
            'is_ephemeral' => 'boolean',
            'must_wait_for_approval' => 'boolean',
            'sleeping_until' => 'datetime',
            'bootstrapped_at' => 'datetime',
            'awaiting_delegation_ids' => 'array',
        ];
    }

    /**
     * Override toArray to add camelCase versions for frontend compatibility
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Add camelCase versions of snake_case fields
        $array['agentType'] = $this->agent_type;
        $array['brain'] = $this->brain;
        $array['docsFolderId'] = $this->docs_folder_id;
        $array['lastSeenAt'] = $this->last_seen_at;
        $array['currentTask'] = $this->current_task;
        $array['isEphemeral'] = $this->is_ephemeral;
        $array['behaviorMode'] = $this->behavior_mode;
        $array['awaitingApprovalId'] = $this->awaiting_approval_id;
        $array['awaitingDelegationIds'] = $this->awaiting_delegation_ids;
        $array['mustWaitForApproval'] = $this->must_wait_for_approval;
        $array['managerId'] = $this->manager_id;
        $array['workspaceId'] = $this->workspace_id;

        return $array;
    }

    // Relationships

    /** @return BelongsTo<ApprovalRequest, $this> */
    public function awaitingApproval(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'awaiting_approval_id');
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** Workspace this agent belongs to (null for human users). */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Workspaces this human user is a member of. */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<User, $this> */
    public function directReports(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    /** @return HasMany<ActivityStep, $this> */
    public function activitySteps(): HasMany
    {
        return $this->hasMany(ActivityStep::class);
    }

    /** @return HasMany<ChannelMember, $this> */
    public function channelMemberships(): HasMany
    {
        return $this->hasMany(ChannelMember::class);
    }

    /** @return BelongsToMany<Channel, $this> */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_members')
            ->withPivot('unread_count', 'joined_at');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'author_id');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'author_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function docsFolder(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'docs_folder_id');
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'actor_id');
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** @return HasMany<CalendarEvent, $this> */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'created_by');
    }

    /** @return BelongsToMany<CalendarEvent, $this> */
    public function calendarAttendances(): BelongsToMany
    {
        return $this->belongsToMany(CalendarEvent::class, 'calendar_event_attendees', 'user_id', 'event_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    /** @return HasMany<DataTable, $this> */
    public function dataTables(): HasMany
    {
        return $this->hasMany(DataTable::class, 'created_by');
    }

    /** @return HasMany<UserExternalIdentity, $this> */
    public function externalIdentities(): HasMany
    {
        return $this->hasMany(UserExternalIdentity::class);
    }

    /** @return HasMany<AgentPermission, $this> */
    public function agentPermissions(): HasMany
    {
        return $this->hasMany(AgentPermission::class, 'agent_id');
    }

    /** @return HasMany<AgentPermission, $this> */
    public function toolPermissions(): HasMany
    {
        return $this->agentPermissions()->where('scope_type', 'tool');
    }

    /** @return HasMany<AgentPermission, $this> */
    public function channelPermissions(): HasMany
    {
        return $this->agentPermissions()->where('scope_type', 'channel');
    }

    /** @return HasMany<AgentPermission, $this> */
    public function folderPermissions(): HasMany
    {
        return $this->agentPermissions()->where('scope_type', 'folder');
    }

    // Helper methods
    public function isAwaitingApproval(): bool
    {
        return $this->awaiting_approval_id !== null;
    }

    public function isSleeping(): bool
    {
        return $this->status === 'sleeping';
    }

    public function clearAwaitingApproval(): void
    {
        $this->update(['awaiting_approval_id' => null, 'status' => 'idle']);
    }

    public function addAwaitingDelegation(string $taskId): void
    {
        $ids = $this->awaiting_delegation_ids ?? [];
        $ids[] = $taskId;
        $this->update(['awaiting_delegation_ids' => array_unique($ids)]);
    }

    public function removeAwaitingDelegation(string $taskId): void
    {
        $ids = $this->awaiting_delegation_ids ?? [];
        $ids = array_values(array_filter($ids, fn ($id) => $id !== $taskId));
        $this->update(['awaiting_delegation_ids' => empty($ids) ? null : $ids]);
    }

    public function isAwaitingDelegation(): bool
    {
        return !empty($this->awaiting_delegation_ids);
    }

    public function isAgent(): bool
    {
        return $this->type === 'agent';
    }

    public function isHuman(): bool
    {
        return $this->type === 'human';
    }

    /** Check if user is an admin of the given (or current) workspace. */
    public function isWorkspaceAdmin(?Workspace $workspace = null): bool
    {
        $ws = $workspace ?? app('currentWorkspace');

        return $this->workspaces()
            ->where('workspaces.id', $ws->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    /** Get user's role in the given workspace, or null if not a member. */
    public function currentWorkspaceRole(Workspace $workspace): ?string
    {
        $membership = $this->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->first();

        return $membership?->pivot?->role;
    }
}

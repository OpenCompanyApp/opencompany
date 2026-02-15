<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToWorkspace;

/**
 * Task - Discrete work items that agents work on (like cases)
 *
 * Examples: support tickets, content requests, research tasks, analysis jobs
 * For kanban board items, see the ListItem model instead.
 *
 * @property array<string, mixed>|null $result
 * @property array<string, mixed>|null $context
 * @property string $status
 * @property string|null $agent_id
 * @property string|null $channel_id
 * @property string|null $parent_task_id
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon|null $due_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory, BelongsToWorkspace;

    protected $table = 'tasks';
    protected $keyType = 'string';
    public $incrementing = false;

    public const TYPE_TICKET = 'ticket';
    public const TYPE_REQUEST = 'request';
    public const TYPE_ANALYSIS = 'analysis';
    public const TYPE_CONTENT = 'content';
    public const TYPE_RESEARCH = 'research';
    public const TYPE_CUSTOM = 'custom';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_CHAT = 'chat';
    public const SOURCE_AUTOMATION = 'automation';
    public const SOURCE_AGENT_DELEGATION = 'agent_delegation';
    public const SOURCE_AGENT_ASK = 'agent_ask';
    public const SOURCE_AGENT_NOTIFY = 'agent_notify';

    protected $fillable = [
        'id',
        'workspace_id',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'agent_id',
        'requester_id',
        'channel_id',
        'list_item_id',
        'parent_task_id',
        'source',
        'context',
        'result',
        'started_at',
        'completed_at',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'due_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Override toArray to add camelCase versions for frontend compatibility
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        $array['agentId'] = $this->agent_id;
        $array['requesterId'] = $this->requester_id;
        $array['channelId'] = $this->channel_id;
        $array['listItemId'] = $this->list_item_id;
        $array['parentTaskId'] = $this->parent_task_id;
        $array['source'] = $this->source;
        $array['startedAt'] = $this->started_at;
        $array['completedAt'] = $this->completed_at;
        $array['dueAt'] = $this->due_at;
        $array['createdAt'] = $this->created_at;
        $array['updatedAt'] = $this->updated_at;

        return $array;
    }

    // Relationships

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<ListItem, $this> */
    public function listItem(): BelongsTo
    {
        return $this->belongsTo(ListItem::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /** @return HasMany<Task, $this> */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /** @return HasMany<TaskStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(TaskStep::class)->orderBy('created_at');
    }

    // Lifecycle Methods

    public function start(): self
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        return $this;
    }

    public function pause(): self
    {
        $this->update(['status' => self::STATUS_PAUSED]);
        return $this;
    }

    public function resume(): self
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
        return $this;
    }

    /** @param array<string, mixed>|null $result */
    public function complete(?array $result = null): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'result' => $result,
        ]);

        return $this;
    }

    public function fail(?string $reason = null): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'result' => ['error' => $reason],
        ]);

        return $this;
    }

    public function cancel(): self
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);

        return $this;
    }

    // Query Scopes

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCompleted(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForAgent(\Illuminate\Database\Eloquent\Builder $query, string $agentId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForRequester(\Illuminate\Database\Eloquent\Builder $query, string $requesterId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('requester_id', $requesterId);
    }

    // Helper Methods

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ]);
    }

    /** @param array<string, mixed> $metadata */
    public function addStep(string $description, string $type = 'action', array $metadata = []): TaskStep
    {
        /** @var TaskStep */
        return $this->steps()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'description' => $description,
            'step_type' => $type,
            'status' => TaskStep::STATUS_PENDING,
            'metadata' => $metadata,
        ]);
    }
}

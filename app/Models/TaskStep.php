<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TaskStep - Individual steps/actions within a Task
 *
 * Tracks the progress and activity of an agent working on a task.
 */
class TaskStep extends Model
{
    use HasFactory;

    protected $table = 'task_steps';
    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';

    public const TYPE_ACTION = 'action';
    public const TYPE_DECISION = 'decision';
    public const TYPE_APPROVAL = 'approval';
    public const TYPE_SUB_TASK = 'sub_task';
    public const TYPE_MESSAGE = 'message';

    protected $fillable = [
        'id',
        'task_id',
        'description',
        'status',
        'step_type',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

        $array['taskId'] = $this->task_id;
        $array['stepType'] = $this->step_type;
        $array['startedAt'] = $this->started_at;
        $array['completedAt'] = $this->completed_at;
        $array['createdAt'] = $this->created_at;
        $array['updatedAt'] = $this->updated_at;

        return $array;
    }

    // Relationships

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    // Lifecycle Methods

    public function start(): self
    {
        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        return $this;
    }

    public function complete(): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return $this;
    }

    public function skip(): self
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'completed_at' => now(),
        ]);

        return $this;
    }

    // Helper Methods

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}

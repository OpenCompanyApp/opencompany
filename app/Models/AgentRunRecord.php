<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Durable control-plane record for one logical agent run.
 *
 * Laravel's jobs table owns physical delivery and reservation. This model owns
 * the application-level identity that survives retries: workspace boundaries,
 * semantic attempts, cooperative cancellation, heartbeat, and exactly-once
 * visible-message correlation. One Task has at most one logical run.
 */
class AgentRunRecord extends Model
{
    use BelongsToWorkspace;

    public const KIND_CHAT = 'chat';

    public const KIND_ASSIGNED_TASK = 'assigned_task';

    public const KIND_PROMPT_AUTOMATION = 'prompt_automation';

    public const KIND_SCRIPT_AUTOMATION = 'script_automation';

    public const KIND_SUBAGENT = 'subagent';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_WAITING = 'waiting';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'agent_runs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'task_id',
        'agent_id',
        'channel_id',
        'trigger_message_id',
        'kind',
        'dispatch_key',
        'status',
        'attempt_count',
        'max_attempts',
        'queue_job_uuid',
        'cancel_requested_at',
        'heartbeat_at',
        'started_at',
        'finished_at',
        'last_error',
        'metadata',
        'causality_id',
        'causality_depth',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'metadata' => 'array',
            'cancel_requested_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function triggerMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'trigger_message_id');
    }

    /**
     * Return the logical run for a task, creating it before the first attempt.
     */
    public static function ensureForTask(Task $task, string $kind, int $maxAttempts = 3): self
    {
        /** @var self $run */
        $run = self::query()->firstOrCreate(
            ['task_id' => $task->id],
            [
                'id' => (string) Str::uuid(),
                'workspace_id' => $task->workspace_id,
                'agent_id' => $task->agent_id,
                'channel_id' => $task->channel_id,
                'trigger_message_id' => $task->trigger_message_id,
                'kind' => $kind,
                'status' => self::STATUS_QUEUED,
                'max_attempts' => $maxAttempts,
                'heartbeat_at' => now(),
                // Every run carries the causality chain of the work it executes.
                'causality_id' => $task->causality_id,
                'causality_depth' => (int) ($task->causality_depth ?? 0),
            ],
        );

        return $run;
    }

    /**
     * Atomically claim one semantic execution attempt after queue middleware
     * has acquired the shared agent lock. Queue releases therefore do not burn
     * provider retry attempts.
     */
    public function beginAttempt(?string $queueJobUuid = null): int
    {
        self::query()->whereKey($this->id)->update([
            'status' => self::STATUS_RUNNING,
            'queue_job_uuid' => $queueJobUuid,
            'attempt_count' => $this->attempt_count + 1,
            'heartbeat_at' => now(),
            'started_at' => $this->started_at ?? now(),
            'finished_at' => null,
            'last_error' => null,
            'updated_at' => now(),
        ]);

        $this->refresh();

        return $this->attempt_count;
    }

    public function heartbeat(): void
    {
        $this->forceFill(['heartbeat_at' => now()])->save();
    }

    public function markRetrying(\Throwable $exception): void
    {
        $this->update([
            'status' => self::STATUS_RETRYING,
            'heartbeat_at' => now(),
            'last_error' => Str::limit($exception->getMessage(), 10_000, ''),
        ]);
    }

    public function markWaiting(?string $reason = null): void
    {
        $attributes = [
            'status' => self::STATUS_WAITING,
            'heartbeat_at' => now(),
        ];
        if ($reason !== null) {
            $attributes['last_error'] = Str::limit($reason, 10_000, '');
        }

        $this->update($attributes);
    }

    public function markQueued(): void
    {
        if ($this->isTerminal()) {
            throw new \DomainException("Terminal agent run [{$this->id}] cannot be queued again.");
        }

        $this->update([
            'status' => self::STATUS_QUEUED,
            'queue_job_uuid' => null,
            'heartbeat_at' => now(),
            'finished_at' => null,
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'heartbeat_at' => now(),
            'finished_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markFailed(\Throwable|string|null $error = null): void
    {
        $message = $error instanceof \Throwable ? $error->getMessage() : $error;

        $this->update([
            'status' => self::STATUS_FAILED,
            'heartbeat_at' => now(),
            'finished_at' => now(),
            'last_error' => $message === null ? null : Str::limit($message, 10_000, ''),
        ]);
    }

    public function requestCancellation(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancel_requested_at' => now(),
            'heartbeat_at' => now(),
            'finished_at' => now(),
        ]);
    }

    public function cancellationRequested(): bool
    {
        return $this->cancel_requested_at !== null || $this->status === self::STATUS_CANCELLED;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Repair the narrow crash window where Task reached terminal state before
     * the paired run update committed.
     */
    public function reconcileFromTerminalTask(): void
    {
        $task = $this->task;
        if (! $task?->isTerminal()) {
            return;
        }

        match ($task->status) {
            Task::STATUS_COMPLETED => $this->markCompleted(),
            Task::STATUS_CANCELLED => $this->requestCancellation(),
            Task::STATUS_FAILED => $this->markFailed($task->result['error'] ?? null),
        };
    }

    /**
     * Repair the inverse crash window where the run reached terminal state
     * immediately before its human-facing Task transition committed.
     */
    public function reconcileTaskFromTerminalRun(): void
    {
        $task = $this->task;
        if (! $this->isTerminal() || ! $task || $task->isTerminal()) {
            return;
        }

        match ($this->status) {
            self::STATUS_COMPLETED => $task->complete([
                'response' => '(reconciled from terminal agent run)',
                'agent_run_id' => $this->id,
            ]),
            self::STATUS_CANCELLED => $task->cancel(),
            self::STATUS_FAILED => $task->fail($this->last_error),
        };
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_QUEUED,
            self::STATUS_RUNNING,
            self::STATUS_WAITING,
            self::STATUS_RETRYING,
        ]);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Durable pre-call ledger entry for one potentially mutating agent tool call.
 *
 * This record is deliberately separate from TaskStep. TaskStep is user-visible
 * progress written after a successful call; this ledger is committed before a
 * call and therefore owns crash ambiguity and safe result replay. Tool results
 * are encrypted with the application key because integration payloads may
 * contain sensitive workspace data.
 */
class AgentToolExecution extends Model
{
    use BelongsToWorkspace, HasUuids;

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_AMBIGUOUS = 'ambiguous';

    protected $fillable = [
        'id',
        'workspace_id',
        'agent_run_id',
        'surface',
        'sequence',
        'tool_name',
        'signature',
        'tool_invocation_id',
        'status',
        'result_payload',
        'error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'result_payload' => 'encrypted',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AgentRunRecord, $this> */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRunRecord::class, 'agent_run_id');
    }

    /**
     * Persist the replay payload only after the underlying tool and the final
     * cancellation checkpoint have both succeeded.
     */
    public function markCompleted(string $payload): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'result_payload' => $payload,
            'error' => null,
            'finished_at' => now(),
        ]);
    }

    /**
     * An exception cannot prove that an external system did not mutate. Keep
     * the call reviewable and block automatic replay for this logical run.
     */
    public function markAmbiguous(\Throwable|string $error): void
    {
        $message = $error instanceof \Throwable ? $error->getMessage() : $error;

        $this->update([
            'status' => self::STATUS_AMBIGUOUS,
            'error' => Str::limit($message, 10_000, ''),
            'finished_at' => now(),
        ]);
    }
}

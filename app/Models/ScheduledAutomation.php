<?php

namespace App\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToWorkspace;

class ScheduledAutomation extends Model
{
    use BelongsToWorkspace;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'name',
        'description',
        'agent_id',
        'prompt',
        'cron_expression',
        'timezone',
        'is_active',
        'last_run_at',
        'next_run_at',
        'run_count',
        'consecutive_failures',
        'channel_id',
        'keep_history',
        'created_by_id',
        'last_result',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'keep_history' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'last_result' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ScheduledAutomation $automation) {
            if (! $automation->next_run_at && $automation->cron_expression) {
                $automation->next_run_at = $automation->computeNextRunAt();
            }
        });
    }

    // --- Relationships ---

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    // --- Schedule Computation ---

    public function computeNextRunAt(?Carbon $after = null): Carbon
    {
        $cron = new CronExpression($this->cron_expression);
        $reference = ($after ?? now())->timezone($this->timezone);
        $nextRun = Carbon::instance($cron->getNextRunDate($reference->toDateTime()));

        return $nextRun->utc();
    }

    /** @return array<int, Carbon> */
    public function getNextRuns(int $count = 5): array
    {
        $runs = [];
        $cron = new CronExpression($this->cron_expression);
        $reference = now()->timezone($this->timezone);

        for ($i = 0; $i < $count; $i++) {
            $reference = Carbon::instance(
                $cron->getNextRunDate($reference->toDateTime())
            );
            $runs[] = $reference->copy()->utc();
        }

        return $runs;
    }

    public function refreshNextRunAt(): void
    {
        $this->next_run_at = $this->computeNextRunAt();
        $this->saveQuietly();
    }

    // --- Run Recording ---

    /** @param array<string, mixed> $result */
    public function recordSuccess(array $result): void
    {
        $this->update([
            'last_run_at' => now(),
            'next_run_at' => $this->computeNextRunAt(),
            'run_count' => $this->run_count + 1,
            'consecutive_failures' => 0,
            'last_result' => $result,
        ]);
    }

    public function recordFailure(string $error): void
    {
        $failures = $this->consecutive_failures + 1;

        $this->update([
            'last_run_at' => now(),
            'next_run_at' => $this->computeNextRunAt(),
            'run_count' => $this->run_count + 1,
            'consecutive_failures' => $failures,
            'is_active' => $failures < 5,
            'last_result' => ['error' => $error],
        ]);
    }
}

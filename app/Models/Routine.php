<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A routine: a versioned unit of recurring work (docs/planning/connections-
 * events-routines.md, "Routines"). Replaces the old Automation model.
 *
 * Owns: the routine definition (kind, instructions or Ruby source, schedule
 * trigger, mode, notify policy, output channel), its version counter, its
 * append-only script revisions, and run bookkeeping (counters, last result).
 * Does not own: dispatch (`routines:tick` records the trigger event and
 * dispatches), execution (Domain\Routines executors), or permissions.
 *
 * Kinds:
 *  - `agent`: the agent runs `instructions` through the LLM runtime;
 *  - `script`: admitted mruby source runs with no LLM;
 *  - `builtin`: code-defined logic keyed by `built_in_key` (no LLM, no agent).
 *
 * Invariant: a trigger is never consent. Routine runs get exactly the
 * executing agent's normal grants; nothing on this model widens them.
 *
 * `mode` (`read_only` | `act`) is stored now but NOT yet enforced: Phase 3
 * enforces read-only routines at tool registration. Do not rely on it as a
 * security boundary until then.
 *
 * Workspace boundary: always query with `forWorkspace()` from request/agent
 * paths; only the tick and reconciliation scan across workspaces.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $name
 * @property string|null $description
 * @property string $kind
 * @property string|null $built_in_key
 * @property string|null $agent_id
 * @property string|null $instructions
 * @property string|null $script
 * @property string|null $script_runtime
 * @property string|null $script_digest
 * @property string|null $script_engine_digest
 * @property Carbon|null $script_validated_at
 * @property int $version
 * @property string $mode
 * @property string $notify
 * @property string|null $last_output_digest
 * @property string $cron_expression
 * @property string $timezone
 * @property bool $is_active
 * @property Carbon|null $last_run_at
 * @property Carbon|null $next_run_at
 * @property int $run_count
 * @property int $consecutive_failures
 * @property string|null $channel_id
 * @property bool $keep_history
 * @property string|null $created_by_id
 * @property array<string, mixed>|null $last_result
 */
class Routine extends Model
{
    use BelongsToWorkspace;

    public const KIND_AGENT = 'agent';

    public const KIND_SCRIPT = 'script';

    public const KIND_BUILTIN = 'builtin';

    public const KINDS = [self::KIND_AGENT, self::KIND_SCRIPT, self::KIND_BUILTIN];

    public const MODE_READ_ONLY = 'read_only';

    public const MODE_ACT = 'act';

    public const MODES = [self::MODE_READ_ONLY, self::MODE_ACT];

    public const NOTIFY_ALWAYS = 'always';

    public const NOTIFY_IF_NEW = 'if_new';

    public const NOTIFY_ON_FAILURE = 'on_failure';

    public const NOTIFIES = [self::NOTIFY_ALWAYS, self::NOTIFY_IF_NEW, self::NOTIFY_ON_FAILURE];

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Mirror the column defaults so freshly created models (not yet
     * refreshed) report the same kind/version/mode/notify as the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => self::KIND_AGENT,
        'version' => 1,
        'mode' => self::MODE_ACT,
        'notify' => self::NOTIFY_ALWAYS,
    ];

    protected $fillable = [
        'id',
        'workspace_id',
        'name',
        'kind',
        'built_in_key',
        'description',
        'agent_id',
        'instructions',
        'script',
        'script_runtime',
        'script_digest',
        'script_engine_digest',
        'script_validated_at',
        'version',
        'mode',
        'notify',
        'last_output_digest',
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
            'script_validated_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Routine $routine) {
            if (! $routine->next_run_at && $routine->cron_expression) {
                $routine->next_run_at = $routine->computeNextRunAt();
            }
        });
        // Invariant: the version identifies what a run executed. Any change
        // to the instructions or Ruby source is a new version; schedule,
        // channel or notify edits are not.
        static::updating(function (Routine $routine): void {
            if ($routine->isDirty(['instructions', 'script'])) {
                $routine->version = ((int) ($routine->getOriginal('version') ?? 1)) + 1;
            }
        });
        static::saved(function (Routine $routine): void {
            if ($routine->script === null || (! $routine->wasRecentlyCreated
                && ! $routine->wasChanged(['script', 'script_runtime', 'script_engine_digest']))) {
                return;
            }
            // Revisions are append-only and survive routine deletion. They
            // remain workspace-owned; public resources never fetch them globally.
            DB::table('routine_script_revisions')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'workspace_id' => $routine->workspace_id,
                'routine_id' => $routine->id, 'runtime' => $routine->script_runtime,
                'source' => $routine->script, 'source_digest' => hash('sha256', $routine->script),
                'engine_digest' => $routine->script_engine_digest,
                'status' => $routine->script_runtime === config('code.runtime') ? 'validated' : 'unadmitted',
                'created_at' => now(),
            ]);
        });
    }

    /** Keep source replacement and its immutable revision in one transaction. */
    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    // --- Type Helpers ---

    public function isScript(): bool
    {
        return $this->kind === self::KIND_SCRIPT;
    }

    public function isAgent(): bool
    {
        return $this->kind === self::KIND_AGENT;
    }

    public function isBuiltin(): bool
    {
        return $this->kind === self::KIND_BUILTIN;
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

    // --- Channel Management ---

    /**
     * Ensure this routine has a dedicated channel, creating one if needed.
     * Optionally clears history based on the keep_history flag.
     *
     * @return string The channel ID.
     */
    public function ensureChannel(): string
    {
        if (! $this->channel_id) {
            $channel = Channel::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $this->workspace_id,
                'name' => $this->name,
                'type' => 'dm',
                'description' => "Routine channel for: {$this->name}",
                'creator_id' => $this->created_by_id,
            ]);

            $channel->users()->attach(array_unique(array_filter([
                $this->created_by_id, $this->agent_id,
            ])));

            $this->updateQuietly(['channel_id' => $channel->id]);
        }

        if (! $this->keep_history && $this->channel_id) {
            Message::where('channel_id', $this->channel_id)->delete();
            ConversationSummary::where('channel_id', $this->channel_id)->delete();
        }

        return $this->channel_id;
    }

    // --- Notify Policy ---

    /**
     * Decide whether one run's output is posted to the routine channel.
     *
     *  - `always`: every run posts;
     *  - `on_failure`: only failed runs post;
     *  - `if_new`: a successful run posts only when its output differs from
     *    the last posted output (sha256 of the trimmed text). Failures always
     *    post so a broken routine is never silenced.
     *
     * Only the routine's own output message is gated. Messages an agent sends
     * through tools during the run, and the run's task/result records, are
     * not affected. Phase 3 replaces the hash with a proper checkpoint.
     */
    public function shouldNotify(string $output, bool $failed): bool
    {
        return match ($this->notify) {
            self::NOTIFY_ON_FAILURE => $failed,
            self::NOTIFY_IF_NEW => $failed || $this->last_output_digest === null
                || ! hash_equals($this->last_output_digest, self::outputDigest($output)),
            default => true,
        };
    }

    /** Remember the posted output so `if_new` can suppress identical repeats. */
    public function rememberPostedOutput(string $output): void
    {
        $this->updateQuietly(['last_output_digest' => self::outputDigest($output)]);
    }

    public static function outputDigest(string $output): string
    {
        return hash('sha256', trim($output));
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

    /**
     * Record a failed run with optional structured runtime/effect diagnostics.
     *
     * @param  array<string, mixed>  $details
     */
    public function recordFailure(string $error, array $details = []): void
    {
        $failures = $this->consecutive_failures + 1;

        $this->update([
            'last_run_at' => now(),
            'next_run_at' => $this->computeNextRunAt(),
            'run_count' => $this->run_count + 1,
            'consecutive_failures' => $failures,
            'is_active' => $failures < 5,
            'last_result' => array_merge(['error' => $error], $details),
        ]);
    }
}

<?php

namespace App\Domain\Vfs\Events;

use App\Agents\Runtime\ActiveAgentRun;
use App\Models\AgentRunRecord;

/**
 * Ambient context of the VFS operation currently executing in this process.
 *
 * Owns three things the facade cannot see from its arguments:
 * - the surface that called the VFS (`tool`, `code`, `computer`, `mcp`,
 *   `api`, `approval_replay`), set by the caller boundary (IntegrationRuntime
 *   for Code Mode, ApprovalExecutionService for replay; `tool` otherwise);
 * - nesting: only the outermost facade call of one logical operation is
 *   audited (a patch's preflight read, a search's per-file reads and a
 *   preview builder's reads are part of their parent operation);
 * - the run causality (run id, causality id, depth) taken from the durable
 *   agent run bound by the runtime (ActiveAgentRun), else null/0.
 *
 * State is process-static and always restored in `finally`, the same pattern
 * as VfsApprovedExecutionContext: queue workers reuse the container, so a
 * leaked surface or approval id would mislabel the next job's events.
 *
 * Does not own: authorization, workspace binding or persistence.
 */
class VfsOperationContext
{
    public const SURFACES = ['tool', 'code', 'computer', 'mcp', 'api', 'approval_replay'];

    private static string $surface = 'tool';

    private static ?string $approvalId = null;

    private static int $depth = 0;

    private static int $suppressed = 0;

    /** @var array<string, array{causality_id: string|null, depth: int}> */
    private static array $runCache = [];

    /**
     * Run $callback with a different caller surface (and, for replays, the
     * approval being executed).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(string $surface, callable $callback, ?string $approvalId = null): mixed
    {
        if (! in_array($surface, self::SURFACES, true)) {
            throw new \InvalidArgumentException("Unknown VFS surface: {$surface}");
        }

        $previous = [self::$surface, self::$approvalId];
        self::$surface = $surface;
        self::$approvalId = $approvalId ?? self::$approvalId;

        try {
            return $callback();
        } finally {
            [self::$surface, self::$approvalId] = $previous;
        }
    }

    /**
     * Run VFS calls that are an implementation detail of something already
     * audited (approval previews, change-feed permission re-checks). Nothing
     * inside is recorded.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function unaudited(callable $callback): mixed
    {
        self::$suppressed++;

        try {
            return $callback();
        } finally {
            self::$suppressed--;
        }
    }

    /**
     * Enter one facade call. Returns true when this call is the outermost
     * audited operation and must record its own event. Always pair with
     * leave() in a finally block.
     */
    public function enter(): bool
    {
        self::$depth++;

        return self::$depth === 1 && self::$suppressed === 0;
    }

    public function leave(): void
    {
        self::$depth = max(0, self::$depth - 1);
    }

    public function surface(): string
    {
        return self::$surface;
    }

    public function approvalId(): ?string
    {
        return self::$approvalId;
    }

    /**
     * Causality of the current agent run, or nulls when the VFS is used
     * outside a run (artisan, HTTP, tests).
     *
     * causality_id: the run's task context `causality_id` when the runtime
     * stamped one (future event-log triggers), else the run id itself, so
     * every event of one run shares one chain id. depth: the delegation depth
     * stamped by DelegationGuard on the run's task, else 0.
     *
     * @return array{run_id: string|null, causality_id: string|null, depth: int}
     */
    public function run(): array
    {
        if (! app()->bound(ActiveAgentRun::class)) {
            return ['run_id' => null, 'causality_id' => null, 'depth' => 0];
        }

        $runId = app(ActiveAgentRun::class)->runId;
        if (! isset(self::$runCache[$runId])) {
            $run = AgentRunRecord::query()->with('task:id,context')->find($runId);
            $context = is_array($run?->task?->context) ? $run->task->context : [];
            self::$runCache = array_slice(self::$runCache, -50, preserve_keys: true);
            self::$runCache[$runId] = [
                'causality_id' => is_string($context['causality_id'] ?? null) ? $context['causality_id'] : $runId,
                'depth' => (int) ($context['delegation_depth'] ?? 0),
            ];
        }

        return ['run_id' => $runId, ...self::$runCache[$runId]];
    }
}

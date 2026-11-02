<?php

namespace App\Domain\AgentRuntime\Application;

use App\Domain\Events\Causality;
use App\Domain\Events\EventLog;
use App\Models\Task;
use App\Models\User;

/**
 * Durable depth and cycle guard for agent-to-agent delegation.
 *
 * Owns: deciding whether one agent may hand work to another. Depth is the
 * workspace causality chain (`tasks.causality_id`/`causality_depth`, the same
 * chain every event and run carries — docs/planning/connections-events-
 * routines.md "Loop protection"), limited by `config('events.max_depth')` via
 * Causality. The agent chain used for A->B->A cycle detection is stamped on
 * the delegated task's context (`delegation_chain`). Both live on persisted
 * rows, so the guard holds across queue hops, worker restarts and Octane
 * reuse — unlike the former in-process static counter.
 *
 * Does not own: task creation, queueing, permissions or approvals. Callers
 * (ContactAgent, GenericSubagent) must call {@see assertCanDelegate()} BEFORE
 * creating the child task or dispatching any job, merge {@see childContext()}
 * into the child's context and {@see childColumns()} into its attributes, then
 * {@see recordDelegation()}.
 *
 * Workspace boundary: the parent task is only consulted when it belongs to the
 * delegating agent's workspace; lineage never crosses workspaces.
 */
class DelegationGuard
{
    /** Task sources that represent one delegation hop. */
    private const DELEGATION_SOURCES = [
        Task::SOURCE_AGENT_DELEGATION,
        Task::SOURCE_AGENT_ASK,
        Task::SOURCE_AGENT_NOTIFY,
    ];

    public function maxDepth(): int
    {
        return Causality::maxDepth();
    }

    /**
     * Lineage of the task the delegating agent is currently working on.
     *
     * @return array{depth: int, chain: list<string>} chain = agent ids from the
     *                                                  origin to (and including) the task's agent
     */
    public function lineage(?Task $current): array
    {
        if ($current === null) {
            return ['depth' => 0, 'chain' => []];
        }

        $context = is_array($current->context) ? $current->context : [];
        // Invariant: when a task carries a causality chain, its depth IS the
        // delegation depth (events, routines and delegations share one limit).
        if ($current->causality_id !== null) {
            $chain = is_array($context['delegation_chain'] ?? null) ? $context['delegation_chain'] : [];
            if ($current->agent_id !== null && ! in_array((string) $current->agent_id, $chain, true)) {
                $chain[] = (string) $current->agent_id;
            }

            return [
                'depth' => (int) $current->causality_depth,
                'chain' => array_values(array_map('strval', $chain)),
            ];
        }

        // Tasks created before lineage was stamped: walk the parent chain,
        // bounded so a corrupt self-referencing chain cannot loop forever.
        $depth = 0;
        $chain = [];
        $node = $current;
        $guard = 0;
        while ($node !== null && $guard++ <= $this->maxDepth() + 5) {
            if ($node->agent_id !== null) {
                array_unshift($chain, (string) $node->agent_id);
            }
            if (! in_array($node->source, self::DELEGATION_SOURCES, true) || $node->parent_task_id === null) {
                break;
            }
            $depth++;
            $node = Task::query()
                ->whereKey($node->parent_task_id)
                ->where('workspace_id', $current->workspace_id)
                ->first();
        }

        return ['depth' => $depth, 'chain' => array_values(array_unique($chain))];
    }

    /**
     * Refuse a delegation that would exceed the depth limit or loop back.
     *
     * @throws \DomainException with an agent-readable reason; no side effects
     */
    public function assertCanDelegate(User $from, User $target, ?Task $current, bool $allowCycle = false): void
    {
        if ($from->id === $target->id) {
            throw new \DomainException('An agent cannot delegate to itself.');
        }

        $lineage = $this->lineage($current);
        $nextDepth = $lineage['depth'] + 1;
        if ($nextDepth > $this->maxDepth()) {
            throw new \DomainException(sprintf(
                'Delegation depth limit reached (%d). Finish this work yourself or report back to the requester instead of delegating further.',
                $this->maxDepth(),
            ));
        }

        // Invariant: ask/delegate back into the current chain (A -> B -> A)
        // creates ping-pong loops bounded only by locks. Notifications are
        // fire-and-forget but still count toward depth.
        if (! $allowCycle && in_array((string) $target->id, $lineage['chain'], true)) {
            throw new \DomainException("{$target->name} is already part of this delegation chain. Reply to your requester instead of delegating back.");
        }
    }

    /**
     * Context to merge into the delegated child task: the agent chain used for
     * cycle detection.
     *
     * @return array{delegation_chain: list<string>}
     */
    public function childContext(User $from, ?Task $current): array
    {
        $chain = $this->lineage($current)['chain'];
        if ($chain === [] || end($chain) !== (string) $from->id) {
            $chain[] = (string) $from->id;
        }

        return ['delegation_chain' => array_values($chain)];
    }

    /**
     * Causality columns for the delegated child task: same chain as the
     * current task (or a new chain), one hop deeper.
     *
     * @return array{causality_id: string, causality_depth: int}
     */
    public function childColumns(?Task $current): array
    {
        $causality = $current?->causality_id !== null
            ? new Causality((string) $current->causality_id, (int) $current->causality_depth)
            : Causality::root();
        $next = $causality->next();

        return ['causality_id' => $next->id, 'causality_depth' => $next->depth];
    }

    /**
     * Record the delegation in the workspace event log on the child's chain.
     * Best effort: the audit entry must never block the delegation itself.
     */
    public function recordDelegation(User $from, User $target, Task $child, string $action): void
    {
        try {
            app(EventLog::class)->record(
                (string) $child->workspace_id,
                'agent.delegated',
                'internal',
                ['type' => 'agent', 'id' => (string) $from->id],
                payload: ['action' => $action, 'target_agent_id' => (string) $target->id, 'task_id' => (string) $child->id],
                subject: 'task:'.$child->id,
                causality: new Causality((string) $child->causality_id, (int) $child->causality_depth),
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}

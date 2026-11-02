<?php

namespace App\Domain\AgentRuntime\Application;

use App\Models\Task;
use App\Models\User;

/**
 * Durable depth and cycle guard for agent-to-agent delegation.
 *
 * Owns: deciding whether one agent may hand work to another, and the lineage
 * metadata (`delegation_depth`, `delegation_chain`) stamped onto the delegated
 * task's context. Because the lineage lives on persisted Task rows, it survives
 * queue hops, worker restarts and Octane container reuse — unlike the former
 * in-process static counter, which could never see across queued jobs.
 *
 * Does not own: task creation, queueing, permissions or approvals. Callers
 * (ContactAgent, GenericSubagent) must call {@see assertCanDelegate()} BEFORE
 * creating the child task or dispatching any job, and merge {@see childContext()}
 * into the child's context.
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
        return max(1, (int) config('agents.delegation.max_depth', 3));
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
        if (isset($context['delegation_depth']) && is_array($context['delegation_chain'] ?? null)) {
            return [
                'depth' => (int) $context['delegation_depth'],
                'chain' => array_values(array_map('strval', $context['delegation_chain'])),
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
     * Context to merge into the delegated child task.
     *
     * @return array{delegation_depth: int, delegation_chain: list<string>}
     */
    public function childContext(User $from, ?Task $current): array
    {
        $lineage = $this->lineage($current);
        $chain = $lineage['chain'];
        if ($chain === [] || end($chain) !== (string) $from->id) {
            $chain[] = (string) $from->id;
        }

        return [
            'delegation_depth' => $lineage['depth'] + 1,
            'delegation_chain' => array_values($chain),
        ];
    }
}

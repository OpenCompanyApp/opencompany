<?php

namespace App\Domain\Authorization\Application;

use App\Agents\Runtime\ActiveAgentRun;
use App\Models\Task;
use App\Models\User;

/**
 * On-behalf-of access intersection (pillar 5 invariant; governance-model.md
 * decision step 9): an agent acting for a human can never do more than that
 * human may. Effective access = agent grants ∩ principal's access.
 *
 * Owns:
 * - resolving the principal for a task: the first human requester up the
 *   task chain (chat/task requester; routine runs carry the routine owner as
 *   requester; delegated tasks inherit through `parent_task_id`);
 * - the ambient principal: an explicit override (approval replay) or the
 *   `on_behalf_of_user_id` persisted on the bound ActiveAgentRun;
 * - nothing else: the intersection *rule* lives in PolicyEngine (GIN-1197).
 *   For tools (`tool.invoke`) the principal must still be a member and only
 *   an admin principal can drive `governance` or `exposure_widening`; for
 *   resource actions (`channel.read`, `channel.post`, ...) the same request
 *   is decided for the principal and a denial there denies the agent.
 *   Space-level checks join the engine when Spaces exist.
 *
 * No principal (agent-initiated work outside any human request) means no
 * intersection beyond the agent's own grants; the preset gates still apply.
 */
class OnBehalfOf
{
    /** @var array{set: bool, id: ?string} explicit override (approval replay) */
    private array $override = ['set' => false, 'id' => null];

    /**
     * Human principal for a task, or null when no human is upstream.
     * Bounded walk; never leaves the task's workspace.
     */
    public function principalForTask(Task $task): ?string
    {
        $node = $task;
        for ($hops = 0; $node !== null && $hops < 32; $hops++) {
            $requester = User::query()->find($node->requester_id);
            if ($requester !== null && $requester->type !== 'agent') {
                return (string) $requester->id;
            }
            $node = $node->parent_task_id === null ? null : Task::query()
                ->whereKey($node->parent_task_id)
                ->where('workspace_id', $task->workspace_id)
                ->first();
        }

        return null;
    }

    /**
     * Run $callback with an explicit principal (null = none known), e.g. when
     * replaying an approved call outside its run. Restores the previous value.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(?string $principalId, callable $callback): mixed
    {
        $previous = $this->override;
        $this->override = ['set' => true, 'id' => $principalId];
        try {
            return $callback();
        } finally {
            $this->override = $previous;
        }
    }

    /** Ambient principal id: explicit override, else the bound run's. */
    public function currentPrincipalId(): ?string
    {
        if ($this->override['set']) {
            return $this->override['id'];
        }
        if (! app()->bound(ActiveAgentRun::class)) {
            return null;
        }

        // recordOrNull() never runs a query that can abort the caller's
        // transaction (a caught DB error still poisons a PostgreSQL
        // transaction), so no exception swallowing is needed here.
        $id = app(ActiveAgentRun::class)->recordOrNull()?->on_behalf_of_user_id;

        return is_string($id) && $id !== '' ? $id : null;
    }
}

<?php

namespace App\Domain\Authorization\Application;

use App\Agents\Runtime\ActiveAgentRun;
use App\Models\PolicyGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches and consumes scoped policy grants (Product Direction pillar 5).
 *
 * Owns: deciding whether a live, exact-match grant lets one tool call run
 * without approval for the current execution context, and consuming `once`
 * grants when the call actually executes.
 *
 * Context: `run` and `task` grants match the ambient ActiveAgentRun (bound by
 * the run control plane for chat, tasks, routines, subagents and
 * Code Mode callbacks inside a run). Outside a run they never match.
 *
 * Workspace boundary: a grant matches only the agent's own workspace.
 */
class PolicyGrantService
{
    /**
     * Whether a live grant covers this exact tool for the agent right now.
     * Evaluation never consumes a grant (tools are evaluated at registration).
     *
     * $includeAlways=false ignores `always` grants: purge, exposure_widening
     * and structural actions never accept an "always" exception
     * (docs/architecture/governance-model.md), so each instance asks unless a
     * narrower grant (once/run/task/until) covers it.
     */
    public function covers(User $agent, string $toolSlug, bool $includeAlways = true): bool
    {
        $query = $this->liveQuery($agent, $toolSlug);
        if (! $includeAlways) {
            $query->where('scope', '!=', 'always');
        }

        return $query->exists();
    }

    /**
     * Whether the ONLY live grant for this tool is a single-use one, i.e. the
     * caller must consume it when the tool actually executes.
     */
    public function coversOnlyOnce(User $agent, string $toolSlug): bool
    {
        $scopes = $this->liveQuery($agent, $toolSlug)->pluck('scope')->unique()->values()->all();

        return $scopes === ['once'];
    }

    /**
     * Consume one matching `once` grant after the call has executed. Other
     * scopes are not consumed. A no-op when the call was covered by another
     * scope or by an approval.
     */
    public function consumeOnce(User $agent, string $toolSlug): void
    {
        // A broader live grant (run/task/until/always) already covers the
        // call; do not burn a single-use grant that was not needed.
        if (! $this->coversOnlyOnce($agent, $toolSlug)) {
            return;
        }

        $grant = $this->liveQuery($agent, $toolSlug)
            ->where('scope', 'once')
            ->orderBy('created_at')
            ->first();

        // Invariant: conditional update so concurrent executions consume a
        // single-use grant at most once.
        $grant && PolicyGrant::query()->whereKey($grant->id)->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    /** @return Builder<PolicyGrant> */
    private function liveQuery(User $agent, string $toolSlug): Builder
    {
        [$runId, $taskId] = $this->runContext();

        return PolicyGrant::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('agent_id', $agent->id)
            ->where('tool_slug', $toolSlug)
            ->whereNull('revoked_at')
            ->where(function (Builder $query) use ($runId, $taskId): void {
                $query->where('scope', 'always')
                    ->orWhere(fn (Builder $q) => $q->where('scope', 'until')->where('expires_at', '>', now()))
                    ->orWhere(fn (Builder $q) => $q->where('scope', 'once')->whereNull('consumed_at'));
                if ($runId !== null) {
                    $query->orWhere(fn (Builder $q) => $q->where('scope', 'run')->where('run_id', $runId));
                }
                if ($taskId !== null) {
                    $query->orWhere(fn (Builder $q) => $q->where('scope', 'task')->where('task_id', $taskId));
                }
            });
    }

    /** @return array{0: ?string, 1: ?string} */
    private function runContext(): array
    {
        if (! app()->bound(ActiveAgentRun::class)) {
            return [null, null];
        }

        try {
            $record = app(ActiveAgentRun::class)->record();

            return [$record->id, $record->task_id];
        } catch (\Throwable) {
            return [null, null];
        }
    }
}

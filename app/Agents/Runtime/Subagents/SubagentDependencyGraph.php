<?php

namespace App\Agents\Runtime\Subagents;

/**
 * Topologically orders a model-supplied subagent plan.
 *
 * Contract: every `dependsOn` id must name another run in the same plan, and
 * the plan must be acyclic. Violations throw instead of being silently
 * repaired, because a dropped edge would let a dependent run without the
 * upstream output it was promised. Order is otherwise stable (input order).
 */
class SubagentDependencyGraph
{
    /**
     * @param  list<SubagentRun>  $runs
     * @return list<SubagentRun>
     *
     * @throws \InvalidArgumentException on duplicate ids, unknown dependencies or cycles
     */
    public function order(array $runs): array
    {
        $byId = [];
        foreach ($runs as $run) {
            if (isset($byId[$run->id])) {
                throw new \InvalidArgumentException("Duplicate subagent run id \"{$run->id}\".");
            }
            $byId[$run->id] = $run;
        }

        foreach ($runs as $run) {
            foreach ($run->dependsOn as $dep) {
                if (! isset($byId[$dep])) {
                    throw new \InvalidArgumentException("Run \"{$run->id}\" depends on unknown run \"{$dep}\".");
                }
            }
        }

        $ordered = [];
        $state = []; // id => 'visiting' | 'done'

        $visit = function (SubagentRun $run, array $path) use (&$visit, &$ordered, &$state, $byId): void {
            if (($state[$run->id] ?? null) === 'done') {
                return;
            }
            if (($state[$run->id] ?? null) === 'visiting') {
                $cycle = implode(' -> ', [...$path, $run->id]);
                throw new \InvalidArgumentException("Subagent plan has a dependency cycle: {$cycle}.");
            }
            $state[$run->id] = 'visiting';

            foreach ($run->dependsOn as $dep) {
                $visit($byId[$dep], [...$path, $run->id]);
            }

            $state[$run->id] = 'done';
            $ordered[] = $run;
        };

        foreach ($runs as $run) {
            $visit($run, []);
        }

        return $ordered;
    }
}

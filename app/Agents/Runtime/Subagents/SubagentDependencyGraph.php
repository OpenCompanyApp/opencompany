<?php

namespace App\Agents\Runtime\Subagents;

class SubagentDependencyGraph
{
    /**
     * @param  list<SubagentRun>  $runs
     * @return list<SubagentRun>
     */
    public function order(array $runs): array
    {
        $byId = [];
        foreach ($runs as $run) {
            $byId[$run->id] = $run;
        }

        $ordered = [];
        $seen = [];

        $visit = function (SubagentRun $run) use (&$visit, &$ordered, &$seen, $byId): void {
            if (isset($seen[$run->id])) {
                return;
            }
            $seen[$run->id] = true;

            foreach ($run->dependsOn as $dep) {
                if (isset($byId[$dep])) {
                    $visit($byId[$dep]);
                }
            }

            $ordered[] = $run;
        };

        foreach ($runs as $run) {
            $visit($run);
        }

        return $ordered;
    }
}

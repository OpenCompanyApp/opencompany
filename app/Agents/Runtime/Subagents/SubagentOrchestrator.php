<?php

namespace App\Agents\Runtime\Subagents;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Executes a dependency-ordered subagent plan one run at a time.
 *
 * This orchestrator is intentionally sequential for now. Sequential execution
 * makes parent task history deterministic and gives each dependent run a clear
 * predecessor, even though future versions may add safe parallel branches.
 *
 * Contracts:
 * - A dependent run receives the final outputs of the runs it depends on,
 *   appended to its delegated task as clearly labelled upstream results.
 *   Subagents run with isolated context, so this is their only view of them.
 * - If any dependency did not complete, the dependent is `skipped` (never
 *   prompted) and so is everything downstream of it.
 * - Invalid plans (unknown dependency, duplicate id, cycle) fail as a whole
 *   before any subagent runs; see SubagentDependencyGraph.
 */
class SubagentOrchestrator
{
    /** Per-dependency cap on upstream output forwarded into a dependent task. */
    private const UPSTREAM_OUTPUT_LIMIT = 8_000;

    /** @var array<string, SubagentStats> */
    private array $stats = [];

    public function __construct(private SubagentDependencyGraph $graph) {}

    /**
     * @param  list<array{id?: string, agent: User, task: string, dependsOn?: list<string>}>  $definitions
     * @return array<string, SubagentStats>
     *
     * @throws \InvalidArgumentException when the plan itself is invalid (nothing ran)
     */
    public function runSequential(array $definitions, string $channelId, ?string $taskId = null): array
    {
        // Tool instances may be reused within one parent run. Do not leak stats
        // from a previous plan into a later orchestration result.
        $this->stats = [];

        $runs = array_map(fn (array $definition) => new SubagentRun(
            id: (string) ($definition['id'] ?? Str::uuid()->toString()),
            agentId: $definition['agent']->id,
            task: $definition['task'],
            dependsOn: $definition['dependsOn'] ?? [],
        ), $definitions);

        $agents = collect($definitions)->mapWithKeys(fn (array $definition, int $index) => [$runs[$index]->id => $definition['agent']]);
        $ordered = $this->graph->order($runs);

        foreach ($ordered as $run) {
            $stats = $this->stats[$run->id] = new SubagentStats(status: 'running', startedAt: now());

            // Invariant: dependents never run on missing upstream results.
            $unmet = array_values(array_filter(
                $run->dependsOn,
                fn (string $dep): bool => ($this->stats[$dep]->status ?? null) !== 'completed',
            ));
            if ($unmet !== []) {
                $stats->status = 'skipped';
                $stats->error = 'Skipped because dependency did not complete: '.implode(', ', $unmet).'.';
                $stats->finishedAt = now();

                continue;
            }

            try {
                /** @var User $agent */
                $agent = $agents[$run->id];

                // GenericSubagent runs through the same Laravel AI prompt path
                // as other agents, but with isolated messages. The delegated
                // task must therefore include all context the subagent needs,
                // including upstream results.
                $response = GenericSubagent::for($agent, $channelId, $taskId)->prompt($this->taskWithUpstream($run));
                $stats->status = 'completed';
                $stats->output = $response->text;
                $stats->promptTokens = $response->usage->promptTokens ?? 0;
                $stats->completionTokens = $response->usage->completionTokens ?? 0;
            } catch (\Throwable $e) {
                $stats->status = 'failed';
                $stats->error = $e->getMessage();
            } finally {
                $stats->finishedAt = now();
            }
        }

        return $this->stats;
    }

    /**
     * Append completed upstream outputs to a dependent's task text.
     */
    private function taskWithUpstream(SubagentRun $run): string
    {
        if ($run->dependsOn === []) {
            return $run->task;
        }

        $sections = [];
        foreach ($run->dependsOn as $dep) {
            $output = (string) ($this->stats[$dep]->output ?? '');
            $sections[] = "### Result of \"{$dep}\"\n".Str::limit($output, self::UPSTREAM_OUTPUT_LIMIT, "\n[... truncated]");
        }

        return $run->task."\n\n---\nUpstream results you depend on (produced by other subagents in this plan):\n\n".implode("\n\n", $sections);
    }
}

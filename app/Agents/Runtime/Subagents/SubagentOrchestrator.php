<?php

namespace App\Agents\Runtime\Subagents;

use App\Models\User;
use Illuminate\Support\Str;

class SubagentOrchestrator
{
    /** @var array<string, SubagentStats> */
    private array $stats = [];

    public function __construct(private SubagentDependencyGraph $graph) {}

    /**
     * @param  list<array{id?: string, agent: User, task: string, dependsOn?: list<string>}>  $definitions
     * @return array<string, SubagentStats>
     */
    public function runSequential(array $definitions, string $channelId, ?string $taskId = null): array
    {
        $runs = array_map(fn (array $definition) => new SubagentRun(
            id: (string) ($definition['id'] ?? Str::uuid()->toString()),
            agentId: $definition['agent']->id,
            task: $definition['task'],
            dependsOn: $definition['dependsOn'] ?? [],
        ), $definitions);

        $agents = collect($definitions)->mapWithKeys(fn (array $definition, int $index) => [$runs[$index]->id => $definition['agent']]);

        foreach ($this->graph->order($runs) as $run) {
            $stats = $this->stats[$run->id] = new SubagentStats(status: 'running', startedAt: now());

            try {
                /** @var User $agent */
                $agent = $agents[$run->id];
                $response = GenericSubagent::for($agent, $channelId, $taskId)->prompt($run->task);
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
}

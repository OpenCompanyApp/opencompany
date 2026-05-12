<?php

namespace App\Agents\Runtime\Subagents;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Agent-callable orchestration tool for delegating work to peer agents.
 *
 * Individual peer agents are also exposed as direct SDK subagents. This tool is
 * for the heavier case where the current agent needs an ordered plan with
 * explicit run IDs and dependencies that can be inspected after execution.
 */
class OrchestrateSubagents implements Tool
{
    public function __construct(
        private User $agent,
        private string $channelId,
        private ?string $taskId,
        private SubagentOrchestrator $orchestrator,
    ) {}

    public static function for(User $agent, string $channelId, ?string $taskId = null): self
    {
        return app(self::class, compact('agent', 'channelId', 'taskId'));
    }

    public function description(): string
    {
        return 'Run a dependency-ordered plan across multiple workspace subagents. Use only for multi-agent work that needs ordered handoffs.';
    }

    public function handle(Request $request): string
    {
        $planJson = (string) ($request['planJson'] ?? '[]');
        $plan = json_decode($planJson, true);

        if (! is_array($plan) || ! array_is_list($plan)) {
            return 'Error: planJson must be a JSON array of runs.';
        }

        $definitions = [];
        foreach ($plan as $index => $run) {
            if (! is_array($run)) {
                return "Error: run {$index} must be an object.";
            }

            $agentId = (string) ($run['agentId'] ?? '');
            $id = (string) ($run['id'] ?? ($index + 1));
            $task = trim((string) ($run['task'] ?? ''));
            if ($agentId === '' || $task === '') {
                return "Error: run {$index} requires agentId and task.";
            }

            if ($agentId === $this->agent->id) {
                return "Error: run {$index} cannot target the current agent.";
            }

            // Delegation is workspace-local and excludes system agents. System
            // agents may own automation/runtime responsibilities that should not
            // be reachable from model-generated delegation plans.
            $target = User::query()
                ->where('id', $agentId)
                ->where('workspace_id', $this->agent->workspace_id)
                ->where('type', 'agent')
                ->where(fn ($query) => $query->whereNull('agent_type')->orWhere('agent_type', '!=', 'system'))
                ->first();

            if (! $target) {
                return "Error: subagent not found or not delegatable for run {$index}.";
            }

            $dependsOn = $run['dependsOn'] ?? [];
            if (! is_array($dependsOn)) {
                return "Error: dependsOn for run {$index} must be an array.";
            }

            // Run IDs are model-supplied on purpose: they make dependsOn edges
            // stable and readable in task context instead of relying on array
            // offsets that change when the model edits the plan.
            $definitions[] = [
                'id' => $id,
                'agent' => $target,
                'task' => $task,
                'dependsOn' => array_values(array_filter($dependsOn, 'is_string')),
            ];
        }

        $stats = $this->orchestrator->runSequential($definitions, $this->channelId, $this->taskId);

        // Return structured JSON rather than prose so the calling model can
        // inspect which subagent failed or produced output before deciding how
        // to continue the parent task.
        return json_encode(collect($stats)->map(fn (SubagentStats $stat) => [
            'status' => $stat->status,
            'output' => $stat->output,
            'error' => $stat->error,
            'promptTokens' => $stat->promptTokens,
            'completionTokens' => $stat->completionTokens,
        ])->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'planJson' => $schema
                ->string()
                ->description('JSON array of runs. Each run: {"id":"inspect","agentId":"...","task":"...","dependsOn":["optional-run-id"]}.')
                ->required(),
        ];
    }
}

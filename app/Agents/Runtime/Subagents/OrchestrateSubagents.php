<?php

namespace App\Agents\Runtime\Subagents;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

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

            $definitions[] = [
                'id' => $id,
                'agent' => $target,
                'task' => $task,
                'dependsOn' => array_values(array_filter($dependsOn, 'is_string')),
            ];
        }

        $stats = $this->orchestrator->runSequential($definitions, $this->channelId, $this->taskId);

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

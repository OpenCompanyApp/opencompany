<?php

namespace App\Agents\Runtime\Context;

use App\Agents\OpenCompanyAgent;
use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\Memory\ContextBudget;
use App\Services\Memory\ModelContextRegistry;
use Illuminate\Support\Str;

/**
 * Builds a diagnostic snapshot of what an agent run will send to the model.
 *
 * The snapshot is for observability and planning; it mirrors prompt sections,
 * messages, tools, and budget information without making pruning decisions.
 */
class AgentContextPipeline
{
    public function __construct(
        private ToolRegistry $tools,
        private ContextBudget $budget,
        private ModelContextRegistry $models,
    ) {}

    public function snapshot(User $agentUser, OpenCompanyAgent $agent): AgentContextSnapshot
    {
        $promptFrame = $agent->promptFrame();
        $messages = $agent->messages();
        // Budgeting uses full instructions, not the shorter stable prompt, so
        // hidden identity/memory context is represented in token estimates.
        $contextBudget = $this->budget->snapshotForAgent(
            $agentUser,
            $messages,
            $agent->fullInstructions(),
        );

        return new AgentContextSnapshot([
            'system_prompt' => $agent->instructions(),
            'full_system_prompt' => $agent->fullInstructions(),
            'volatile_prompt_context' => $agent->volatilePromptContext(),
            'messages' => collect($messages)
                // Snapshot payloads should be inspectable in logs/UI without
                // hauling full conversation bodies into every runtime record.
                ->map(fn ($m) => [
                    'role' => $m->role->value,
                    'content' => Str::limit($m->content ?? '', 2000),
                ])->values()->toArray(),
            'tools' => $this->tools->getToolSlugsForAgent($agentUser),
            'model' => $agent->model(),
            'provider' => $agent->provider(),
            'prompt_sections' => $promptFrame['stable_breakdown'],
            'volatile_prompt_sections' => $promptFrame['volatile_breakdown'],
            'context_window' => $this->models->getContextWindow($agent->model(), $agent->provider()),
            'context_budget' => $contextBudget,
            // The current pipeline is inspect-only. Future pruning stages should
            // update this plan instead of inventing a second context report.
            'context_plan' => (new AgentContextPlan([
                'kept' => ['system_prompt', 'volatile_prompt_context', 'messages'],
                'pruned' => [],
                'compacted' => false,
                'protected' => ['latest_user_message', 'system_prompt'],
                'fallback_trimmed' => false,
            ]))->toArray(),
        ]);
    }
}

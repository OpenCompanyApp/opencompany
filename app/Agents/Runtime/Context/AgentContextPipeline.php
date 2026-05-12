<?php

namespace App\Agents\Runtime\Context;

use App\Agents\OpenCompanyAgent;
use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\Memory\ContextBudget;
use App\Services\Memory\ModelContextRegistry;
use Illuminate\Support\Str;

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

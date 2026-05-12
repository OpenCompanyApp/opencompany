<?php

namespace App\Agents\Runtime;

use App\Agents\OpenCompanyAgent;
use App\Agents\Runtime\Context\AgentContextPipeline;
use App\Ai\Prompting\SystemPromptBag;
use App\Models\User;
use Laravel\Ai\Responses\Data\FinishReason;

class AgentRun
{
    /** @var list<AgentRuntimeEvent> */
    private array $events = [];

    public function __construct(
        private User $agentUser,
        private OpenCompanyAgent $agent,
        private AgentRunOptions $options,
        private AgentContextPipeline $context,
        private ?string $taskId = null,
    ) {}

    public function run(string $prompt): AgentRunResult
    {
        $this->emit('run.started');
        $snapshot = $this->context->snapshot($this->agentUser, $this->agent)->toArray();
        $this->emit('context.planned', $snapshot['context_plan'] ?? []);
        $this->emit('model.resolved', [
            'provider' => $this->agent->provider(),
            'model' => $this->agent->model(),
        ]);

        app()->instance(SystemPromptBag::class, new SystemPromptBag($this->agent->systemPrompts()));

        try {
            $response = $this->agent->prompt(
                $prompt,
                provider: $this->options->provider,
                model: $this->options->model,
                timeout: $this->options->timeout,
            );

            $wasTruncated = $response->steps->contains(
                fn ($step) => $step->finishReason === FinishReason::Length
            );

            $text = $wasTruncated
                ? $response->steps->filter(fn ($step) => ! empty($step->text))->pluck('text')->join('')
                : $response->text;

            if ($text === '') {
                $text = "I processed your request but didn't generate a text response.";
            }

            $this->emit('run.completed', [
                'promptTokens' => $response->usage->promptTokens ?? null,
                'completionTokens' => $response->usage->completionTokens ?? null,
            ]);

            return new AgentRunResult($response, $text, $snapshot, $this->events);
        } catch (\Throwable $e) {
            $this->emit('run.failed', [
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);

            throw new AgentRunFailed($e->getMessage(), $this->events, $snapshot, $e);
        } finally {
            app()->forgetInstance(SystemPromptBag::class);
        }
    }

    public function agent(): OpenCompanyAgent
    {
        return $this->agent;
    }

    public function snapshot(): array
    {
        return $this->context->snapshot($this->agentUser, $this->agent)->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function emit(string $type, array $data = []): void
    {
        $this->events[] = new AgentRuntimeEvent($type, $data, $this->taskId, $this->agentUser->id);
    }
}

<?php

namespace App\Agents\Runtime;

use App\Agents\OpenCompanyAgent;
use App\Agents\Runtime\Context\AgentContextPipeline;
use App\Ai\Prompting\SystemPromptBag;
use App\Models\User;
use Laravel\Ai\Responses\Data\FinishReason;

/**
 * Executes one OpenCompany agent turn and records the runtime evidence around it.
 *
 * This class is deliberately small: it owns run-scoped events, context planning,
 * prompt-bag binding, the live model call, and cleanup. Persistence belongs to
 * the queue/job layer so synchronous test probes and queued chat responses use
 * the same execution contract.
 */
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

        // Capture the model-visible context before the call so both success and
        // failure paths can persist exactly what the agent was allowed to see.
        $snapshot = $this->context->snapshot($this->agentUser, $this->agent)->toArray();
        $this->emit('context.planned', $snapshot['context_plan'] ?? []);
        $this->emit('model.resolved', [
            'provider' => $this->agent->provider(),
            'model' => $this->agent->model(),
        ]);

        // The Laravel AI SDK resolves system prompts from the container during
        // prompt execution. Bind this run's prompt bag only for the duration of
        // the call so queued jobs cannot leak identity/context across agents.
        app()->instance(SystemPromptBag::class, new SystemPromptBag($this->agent->systemPrompts()));

        try {
            // This is the only live LLM call in the run object. Callers that do
            // not want network traffic should fake the Laravel AI provider above
            // this layer rather than bypassing runtime bookkeeping here.
            $response = $this->agent->prompt(
                $prompt,
                provider: $this->options->provider,
                model: $this->options->model,
                timeout: $this->options->timeout,
            );

            $wasTruncated = $response->steps->contains(
                fn ($step) => $step->finishReason === FinishReason::Length
            );

            // When the provider stops for length, Laravel AI may leave the
            // aggregate response text empty even though earlier steps contain
            // usable text. Preserve partial output instead of treating that as
            // a silent failure.
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
            // Always remove the run-local prompt bag. Octane/queue workers can
            // reuse the same container instance across unrelated agent runs.
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

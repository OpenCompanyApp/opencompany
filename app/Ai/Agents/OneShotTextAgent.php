<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

/**
 * Minimal Laravel AI agent for one-off text generation.
 *
 * Use this when a feature needs model output without OpenCompany's full chat,
 * tool, memory, and task runtime around it.
 */
class OneShotTextAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    public function __construct(
        private readonly string $instructions,
        private readonly ?int $maxTokens = null,
        private readonly ?float $temperature = null,
    ) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    public function messages(): iterable
    {
        // One-shot prompts carry all user content through Promptable::prompt();
        // there is no conversation history for this lightweight agent.
        return [];
    }

    public function tools(): iterable
    {
        // Keep this agent tool-free so callers do not accidentally bypass the
        // OpenCompany permission/runtime layer.
        return [];
    }

    public function maxTokens(): ?int
    {
        return $this->maxTokens;
    }

    public function temperature(): ?float
    {
        return $this->temperature;
    }
}

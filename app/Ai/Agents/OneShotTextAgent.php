<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

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
        return [];
    }

    public function tools(): iterable
    {
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

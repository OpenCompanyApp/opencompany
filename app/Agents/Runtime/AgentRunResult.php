<?php

namespace App\Agents\Runtime;

use Laravel\Ai\Responses\AgentResponse;

class AgentRunResult
{
    /**
     * @param  list<AgentRuntimeEvent>  $events
     * @param  array<string, mixed>  $contextSnapshot
     */
    public function __construct(
        public readonly AgentResponse $response,
        public readonly string $text,
        public readonly array $contextSnapshot,
        public readonly array $events,
    ) {}
}

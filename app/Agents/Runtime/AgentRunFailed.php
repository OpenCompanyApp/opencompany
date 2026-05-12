<?php

namespace App\Agents\Runtime;

class AgentRunFailed extends \RuntimeException
{
    /**
     * @param  list<AgentRuntimeEvent>  $events
     * @param  array<string, mixed>  $contextSnapshot
     */
    public function __construct(
        string $message,
        public readonly array $events,
        public readonly array $contextSnapshot,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

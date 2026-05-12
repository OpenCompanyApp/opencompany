<?php

namespace App\Agents\Runtime;

class AgentRuntimeEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $type,
        public readonly array $data = [],
        public readonly ?string $taskId = null,
        public readonly ?string $agentId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'taskId' => $this->taskId,
            'agentId' => $this->agentId,
            'data' => $this->data,
            'at' => now()->toISOString(),
        ];
    }
}

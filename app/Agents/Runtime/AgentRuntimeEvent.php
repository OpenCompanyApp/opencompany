<?php

namespace App\Agents\Runtime;

use Carbon\CarbonImmutable;

class AgentRuntimeEvent
{
    public readonly CarbonImmutable $occurredAt;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $type,
        public readonly array $data = [],
        public readonly ?string $taskId = null,
        public readonly ?string $agentId = null,
    ) {
        $this->occurredAt = CarbonImmutable::now();
    }

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
            'at' => $this->occurredAt->toISOString(),
        ];
    }
}

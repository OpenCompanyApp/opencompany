<?php

namespace App\Agents\Runtime\Subagents;

class SubagentRun
{
    public function __construct(
        public readonly string $id,
        public readonly string $agentId,
        public readonly string $task,
        public readonly array $dependsOn = [],
        public readonly string $status = 'pending',
    ) {}
}

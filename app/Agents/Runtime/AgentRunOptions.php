<?php

namespace App\Agents\Runtime;

class AgentRunOptions
{
    public function __construct(
        public readonly bool $resumeFromTask = false,
        public readonly ?int $maxTurns = null,
        public readonly ?int $timeout = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $permissionMode = null,
    ) {}
}

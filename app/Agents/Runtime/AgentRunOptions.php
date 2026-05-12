<?php

namespace App\Agents\Runtime;

/**
 * Immutable runtime switches for a single agent invocation.
 *
 * These values are deliberately separate from the persisted agent record so CLI
 * tests, retries, and orchestrated subagent calls can override one run without
 * mutating the agent's long-lived configuration.
 */
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

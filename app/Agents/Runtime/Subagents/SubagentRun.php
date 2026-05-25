<?php

namespace App\Agents\Runtime\Subagents;

/**
 * Planned subagent unit of work.
 *
 * The orchestrator treats this as a lightweight dependency graph node: it names
 * the target agent, the task to run, and any prerequisite run IDs.
 */
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

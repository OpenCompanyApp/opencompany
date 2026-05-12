<?php

namespace App\Agents\Runtime\Subagents;

/**
 * Runtime telemetry collected while a subagent is executing.
 *
 * These fields are mutable on purpose: orchestrators can update progress,
 * token usage, tool status, and final output without replacing the object.
 */
class SubagentStats
{
    public function __construct(
        public string $status = 'pending',
        public ?string $currentTool = null,
        public ?string $model = null,
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public ?string $error = null,
        public ?string $output = null,
        public ?\DateTimeInterface $startedAt = null,
        public ?\DateTimeInterface $finishedAt = null,
    ) {}
}

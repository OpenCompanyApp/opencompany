<?php

namespace App\Agents\Runtime\Subagents;

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

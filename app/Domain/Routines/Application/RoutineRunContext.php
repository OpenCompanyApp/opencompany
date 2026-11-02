<?php

namespace App\Domain\Routines\Application;

use App\Models\AgentRunRecord;
use App\Models\Task;
use App\Models\User;

/**
 * Resolved durable records for one scheduled routine dispatch.
 */
class RoutineRunContext
{
    public function __construct(
        public readonly User $agent,
        public readonly Task $task,
        public readonly AgentRunRecord $run,
        public readonly string $channelId,
    ) {}
}

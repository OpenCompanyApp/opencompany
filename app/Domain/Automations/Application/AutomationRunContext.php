<?php

namespace App\Domain\Automations\Application;

use App\Models\AgentRunRecord;
use App\Models\Task;
use App\Models\User;

/**
 * Resolved durable records for one scheduled automation dispatch.
 */
class AutomationRunContext
{
    public function __construct(
        public readonly User $agent,
        public readonly Task $task,
        public readonly AgentRunRecord $run,
        public readonly string $channelId,
    ) {}
}

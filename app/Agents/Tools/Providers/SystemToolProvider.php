<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\System\Wait;
use App\Agents\Tools\System\WaitForApproval;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers execution-control tools used by the agent loop itself.
 *
 * These tools change runtime flow rather than workspace content: agents can
 * wait for time to pass or pause until a human approval is decided.
 */
class SystemToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'system';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'wait, wait_for_approval',
            'description' => 'Execution control',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:gear';
    }

    public function tools(): array
    {
        return [
            'wait' => [
                'class' => Wait::class,
                'type' => 'write',
                'name' => 'Wait',
                'description' => 'Suspend execution for a specified number of minutes, then auto-resume.',
                'icon' => 'ph:timer',
            ],
            'wait_for_approval' => [
                'class' => WaitForApproval::class,
                'type' => 'write',
                'name' => 'Wait For Approval',
                'description' => 'Pause execution until a pending approval is decided.',
                'icon' => 'ph:pause-circle',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        // System tools need the agent record so they can persist waiting state.
        return new $class($agent);
    }
}

<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\System\Wait;
use App\Agents\Tools\System\WaitForApproval;
use App\Models\User;

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

    public function createTool(string $class, User $agent, array $context = []): \Laravel\Ai\Contracts\Tool
    {
        return new $class($agent);
    }
}

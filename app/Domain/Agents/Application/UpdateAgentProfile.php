<?php

namespace App\Domain\Agents\Application;

use App\Agents\Providers\AgentBrainValidator;
use App\Models\User;
use App\Models\Workspace;
use InvalidArgumentException;

/**
 * Updates mutable agent profile and runtime fields.
 *
 * Brain changes affect live provider/model routing, so validation remains part
 * of the use case rather than the controller.
 */
class UpdateAgentProfile
{
    public function __construct(private AgentBrainValidator $brainValidator) {}

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws InvalidArgumentException
     */
    public function handle(Workspace $workspace, User $agent, array $validated): User
    {
        if (isset($validated['brain'])) {
            $this->brainValidator->validate($validated['brain'], $workspace->id);
        }

        $agent->update([
            'name' => $validated['name'] ?? $agent->name,
            'brain' => $validated['brain'] ?? $agent->brain,
            'status' => $validated['status'] ?? $agent->status,
            'current_task' => $validated['currentTask'] ?? $agent->current_task,
            'behavior_mode' => $validated['behaviorMode'] ?? $agent->behavior_mode,
            'must_wait_for_approval' => $validated['mustWaitForApproval'] ?? $agent->must_wait_for_approval,
            'manager_id' => array_key_exists('managerId', $validated) ? $validated['managerId'] : $agent->manager_id,
            'sleeping_until' => array_key_exists('sleepingUntil', $validated) ? $validated['sleepingUntil'] : $agent->sleeping_until,
            'sleeping_reason' => array_key_exists('sleepingReason', $validated) ? $validated['sleepingReason'] : $agent->sleeping_reason,
        ]);

        return $agent->fresh();
    }
}

<?php

namespace App\Agents\Tools;

use App\Models\User;

class ToolRegistry
{
    /**
     * Get all tools available for a given agent.
     *
     * Currently returns all tools for all agents (hardcoded).
     * Sprint 2 will replace this with DB-driven capability mapping.
     *
     * @return array<\Laravel\Ai\Contracts\Tool>
     */
    public function getToolsForAgent(User $agent): array
    {
        return [
            new SendChannelMessage($agent),
            new SearchDocuments(),
            new CreateTaskStep($agent),
        ];
    }
}

<?php

namespace App\Policies;

use App\Models\User;

class AgentPolicy
{
    /**
     * Determine whether the user can view the agent.
     * Admins see all workspace agents. Members see only their own.
     */
    public function view(User $user, User $agent): bool
    {
        if (! $agent->isAgent()) {
            return false;
        }

        // Agent must belong to user's current workspace
        $workspace = app('currentWorkspace');
        if ($agent->workspace_id !== $workspace->id) {
            return false;
        }

        // Admins can see all agents
        if ($user->isWorkspaceAdmin($workspace)) {
            return true;
        }

        // Members can only see agents they manage
        return $agent->manager_id === $user->id;
    }

    /**
     * Determine whether the user can update the agent.
     */
    public function update(User $user, User $agent): bool
    {
        return $this->view($user, $agent);
    }

    /**
     * Determine whether the user can delete the agent.
     */
    public function delete(User $user, User $agent): bool
    {
        return $this->view($user, $agent);
    }
}

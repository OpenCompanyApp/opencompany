<?php

namespace App\Domain\Knowledge\Application;

use App\Models\User;
use App\Services\AgentDocumentService;

/**
 * Deletes the protected system document tree for one agent.
 *
 * Ordinary document deletion must keep rejecting system documents. This action
 * is the narrow, agent-lifecycle path that may remove those files.
 */
class DeleteAgentIdentityTree
{
    public function __construct(private AgentDocumentService $documents) {}

    public function handle(User $agent): void
    {
        $this->documents->deleteAgentDocumentStructure($agent);
    }
}

<?php

namespace App\Domain\Knowledge\Application;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentDocumentService;

/**
 * Creates the system document tree required for an agent to run.
 *
 * Knowledge owns the physical document layout while the Agents context owns the
 * decision to provision an agent. This action is intentionally app-local:
 * OpenCompany's prompt-document structure is product behavior, not a generic
 * package concern.
 */
class CreateAgentIdentityTree
{
    public function __construct(private AgentDocumentService $documents) {}

    /**
     * @param  array<string, string>  $identityContent
     */
    public function handle(User $agent, array $identityContent = []): Document
    {
        return $this->documents->createAgentDocumentStructure($agent, $identityContent);
    }
}

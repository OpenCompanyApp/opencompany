<?php

namespace App\Domain\Knowledge\Application;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentDocumentService;
use Illuminate\Support\Collection;

/**
 * Reads the prompt documents that define one agent's identity and durable memory.
 *
 * This keeps controller/runtime code from knowing the document-tree layout.
 */
class ReadAgentPromptDocuments
{
    public function __construct(private AgentDocumentService $documents) {}

    /**
     * @return Collection<int, Document>
     */
    public function handle(User $agent): Collection
    {
        return $this->documents->getIdentityFiles($agent);
    }
}

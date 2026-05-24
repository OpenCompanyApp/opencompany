<?php

namespace App\Domain\Knowledge\Application;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentDocumentService;
use InvalidArgumentException;

/**
 * Updates one known prompt document for an agent.
 *
 * Arbitrary document creation belongs to document workflows. This action only
 * accepts the stable identity file types used by agent prompt assembly.
 */
class UpdateAgentIdentityFile
{
    public function __construct(private AgentDocumentService $documents) {}

    public function handle(User $agent, string $fileType, string $content): ?Document
    {
        $normalizedType = strtoupper($fileType);
        $allowedTypes = $this->documents->getIdentityFileTypes();

        if (! in_array($normalizedType, $allowedTypes, true)) {
            throw new InvalidArgumentException(
                "Invalid file type '{$fileType}'. Allowed: ".implode(', ', $allowedTypes)
            );
        }

        return $this->documents->updateIdentityFile($agent, $normalizedType, $content);
    }
}

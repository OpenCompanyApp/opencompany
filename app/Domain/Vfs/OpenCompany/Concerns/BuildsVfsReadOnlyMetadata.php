<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

/**
 * Shared metadata helpers for generated/read-only VFS entries.
 *
 * Capabilities describe what can actually be executed on a path. These
 * metadata fields explain why intentionally read-only projections do not expose
 * write or patch capabilities, and point agents at the right mutation surface.
 */
trait BuildsVfsReadOnlyMetadata
{
    /**
     * @return array<string, string>
     */
    private function readOnlyMetadata(string $reason, ?string $mutationTool = null, ?string $mutationHint = null): array
    {
        return array_filter([
            'read_only_reason' => $reason,
            'mutation_tool' => $mutationTool,
            'mutation_hint' => $mutationHint,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, string>
     */
    private function toolDocumentMetadata(): array
    {
        return $this->readOnlyMetadata(
            'Tool documentation files are generated snapshots of callable surfaces.',
            null,
            'Use the documented app tools or VFS commands rather than patching /tools files.',
        );
    }
}

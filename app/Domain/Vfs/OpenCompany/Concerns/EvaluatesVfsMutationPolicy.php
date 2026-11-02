<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\Policy\VfsMutationDecision;
use App\Models\Document;
use App\Models\User;

/**
 * Shared mutation policy for VFS-backed records.
 *
 * VFS entries expose capabilities before an agent mutates anything, so the same
 * policy must be used by both metadata builders and write paths. Keep tool
 * permission checks here instead of duplicating them in individual mounts.
 */
trait EvaluatesVfsMutationPolicy
{
    /**
     * @param  list<string>  $toolSlugs
     * @param  array<string, mixed>|null  $parameters
     */
    private function mutationDecision(User $agent, string $path, array $toolSlugs, string $hint = '', string $operation = 'patch', ?array $parameters = null): VfsMutationDecision
    {
        return $this->mutationPolicy->decide(
            $agent,
            $toolSlugs[0],
            $operation,
            $path,
            $toolSlugs,
            $parameters ?? ['path' => $path],
            $hint,
        );
    }

    /**
     * Resolve the policy shown on a concrete entry after its mount-specific
     * eligibility rule has run. A task owned by somebody else, for example,
     * must remain denied even when the acting agent has the global vfs_patch
     * primitive enabled.
     *
     * @param  list<string>  $toolSlugs
     */
    private function entryMutationDecision(
        User $agent,
        string $path,
        string $operation,
        array $toolSlugs,
        string $hint,
        bool $domainEligible = true,
        ?string $deniedReason = null,
    ): VfsMutationDecision {
        if (! $domainEligible) {
            return new VfsMutationDecision(
                status: 'denied',
                operation: $operation,
                subject: $path,
                requiredTools: $toolSlugs,
                hint: $hint,
                deniedReason: $deniedReason ?? 'This path is not eligible for the requested mutation.',
            );
        }

        return $this->mutationDecision($agent, $path, $toolSlugs, $hint, $operation);
    }

    /**
     * Add only actions that can actually execute or enter an approval flow.
     * Domain-denied and tool-denied decisions remain visible in metadata but do
     * not become executable capabilities.
     *
     * @param  list<string>  $baseCapabilities
     * @param  array<string, VfsMutationDecision>  $decisions
     * @return list<string>
     */
    private function mutationCapabilities(array $baseCapabilities, array $decisions): array
    {
        foreach ($decisions as $capability => $decision) {
            if ($decision->allowedForCapability()) {
                $baseCapabilities[] = $capability;
            }
        }

        return array_values(array_unique($baseCapabilities));
    }

    /**
     * Expose action-indexed permission state for entries such as workspace files
     * whose patch and raw-write permissions are intentionally independent.
     *
     * @param  array<string, VfsMutationDecision>  $decisions
     * @return array{mutation_actions: array<string, array<string, mixed>>}
     */
    private function mutationActionsMetadata(array $decisions): array
    {
        return [
            'mutation_actions' => array_map(
                fn (VfsMutationDecision $decision): array => $decision->actionMetadata(),
                $decisions,
            ),
        ];
    }

    /**
     * @param  list<string>  $toolSlugs
     * @param  array<string, mixed>|null  $parameters
     * @param  array<string, mixed>|null  $preview
     */
    private function assertMutationToolsAllowed(
        User $agent,
        string $path,
        array $toolSlugs,
        ?array $parameters = null,
        ?string $channelId = null,
        ?array $preview = null,
        string $operation = 'patch',
        string $hint = '',
    ): void {
        $this->mutationPolicy->assertAllowedOrRequestApproval(
            agent: $agent,
            directToolSlug: $toolSlugs[0],
            operation: $operation,
            subject: $path,
            requiredTools: $toolSlugs,
            parameters: $parameters ?? ['path' => $path],
            channelId: $channelId,
            hint: $hint,
            preview: $preview,
        );
    }

    /**
     * @param  list<string>  $toolSlugs
     * @return array<string, mixed>
     */
    private function mutationToolMetadata(User $agent, array $toolSlugs, string $hint): array
    {
        return $this->mutationDecision($agent, '__metadata__', $toolSlugs, $hint)->metadata();
    }

    private function canPatchTableRows(User $agent): bool
    {
        return $this->mutationDecision($agent, '__metadata__', ['vfs_patch', 'update_table_row'])->allowedForCapability();
    }

    /**
     * @param  list<string>  $remaining
     */
    private function canPatchAgentDocument(User $viewer, User $target, Document $doc, string $path, array $remaining): bool
    {
        if ($doc->is_folder) {
            return false;
        }

        $tools = $this->agentDocumentPatchTools($viewer, $target, $doc, $path, $remaining);
        if ($tools === null) {
            return false;
        }

        return $this->mutationDecision($viewer, $path, $tools)->allowedForCapability();
    }

    /**
     * @param  list<string>  $remaining
     * @return list<string>|null
     */
    private function agentDocumentPatchTools(User $viewer, User $target, Document $doc, string $path, array $remaining): ?array
    {
        if ($doc->is_folder || (! isset($remaining[1]) && $remaining !== ['todo.md'])) {
            return null;
        }

        if ($remaining[0] === 'identity') {
            if ($viewer->id !== $target->id && $target->manager_id !== $viewer->id) {
                return null;
            }

            return ['vfs_patch', 'update_agent_identity_file'];
        }

        // Agent working state: notes/ and todo.md have exactly one writer,
        // the agent itself. Managers and reports may read, never write.
        if ($remaining[0] === 'notes' || $remaining === ['todo.md']) {
            return $viewer->id === $target->id ? ['vfs_patch'] : null;
        }

        if ($remaining[0] === 'memory') {
            $isSelf = $viewer->id === $target->id;
            $isManagerEditingCoreMemory = $target->manager_id === $viewer->id
                && count($remaining) === 2
                && $doc->title === 'MEMORY.md';

            if (! $isSelf && ! $isManagerEditingCoreMemory) {
                return null;
            }

            return ['vfs_patch'];
        }

        return null;
    }

    /**
     * @param  list<string>  $remaining
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>|null  $preview
     */
    private function assertCanPatchAgentDocument(User $viewer, User $target, Document $doc, string $path, array $remaining, array $parameters, ?array $preview = null): void
    {
        $tools = $this->agentDocumentPatchTools($viewer, $target, $doc, $path, $remaining);
        if ($tools === null) {
            throw VfsError::permissionDenied($path, 'agent document patch is not allowed for this relationship or path');
        }

        $this->assertMutationToolsAllowed($viewer, $path, $tools, $parameters, isset($parameters['channelId']) && is_string($parameters['channelId']) ? $parameters['channelId'] : null, $preview);
    }
}

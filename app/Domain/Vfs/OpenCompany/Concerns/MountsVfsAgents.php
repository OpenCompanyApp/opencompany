<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Agent document mount operations for OpenCompanyVfs.
 */
trait MountsVfsAgents
{
    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listAgents(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        return $this->pageAgents($agent, $segments, $path, $budget, [])['entries'];
    }

    /**
     * Page agent indexes and delegate nested task/document collections to their
     * existing keyset contracts without escaping the relationship permission
     * check performed for the target agent.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{entries: list<VfsEntry>, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageAgents(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        if (count($segments) === 1) {
            return $this->paginateVfsLabelQuery(
                User::where('workspace_id', $agent->workspace_id)->where('type', 'agent'),
                'name',
                $budget->maxEntries,
                $state,
                fn (User $target): VfsEntry => new VfsEntry(Str::slug($target->name), '/agents/'.Str::slug($target->name), 'directory', "/agents/by-id/{$target->id}", 'agent', $target->id, capabilities: ['browse', 'read']),
                new VfsEntry('by-id', '/agents/by-id', 'directory', capabilities: ['browse']),
            );
        }

        if (isset($segments[1]) && $segments[1] === 'by-id' && ! isset($segments[2])) {
            return $this->paginateVfsLabelQuery(
                User::where('workspace_id', $agent->workspace_id)->where('type', 'agent'),
                'name',
                $budget->maxEntries,
                $state,
                fn (User $target): VfsEntry => new VfsEntry(
                    (string) $target->id,
                    "/agents/by-id/{$target->id}",
                    'directory',
                    "/agents/by-id/{$target->id}",
                    'agent',
                    $target->id,
                    capabilities: ['browse', 'read'],
                    metadata: ['name' => $target->name],
                ),
            );
        }

        $target = $this->resolveAgent($agent, array_slice($segments, 1), $path);
        $this->assertCanAccessAgentDocs($agent, $target, $path);

        $remaining = $this->agentRemainingSegments(array_slice($segments, 1));
        if ($remaining === []) {
            $entries = [
                new VfsEntry('identity', rtrim($path, '/').'/identity', 'directory', "/agents/by-id/{$target->id}/identity", 'agent', $target->id, capabilities: ['browse', 'read']),
                new VfsEntry('memory', rtrim($path, '/').'/memory', 'directory', "/agents/by-id/{$target->id}/memory", 'agent', $target->id, capabilities: ['browse', 'read', 'search']),
                new VfsEntry('tasks', rtrim($path, '/').'/tasks', 'directory', "/agents/by-id/{$target->id}/tasks", 'agent', $target->id, capabilities: ['browse', 'read']),
            ];
            if ($this->agentDocumentFolder($target, $path) !== null) {
                $entries[] = new VfsEntry('files', rtrim($path, '/').'/files', 'directory', "/agents/by-id/{$target->id}/files", 'agent', $target->id, capabilities: ['browse', 'read']);
            }

            return $this->paginateVfsStaticEntries($entries, $budget->maxEntries, $state);
        }

        if ($remaining[0] === 'tasks') {
            $taskSegments = ['tasks', 'by-agent', $this->slug($target->name), ...array_slice($remaining, 1)];

            return $this->pageTasks($agent, $taskSegments, "/tasks/by-agent/{$this->slug($target->name)}", $budget, $state);
        }

        if ($remaining[0] === 'files') {
            $folder = $this->agentDocumentFolder($target, $path);
            if ($folder === null) {
                throw VfsError::notFound($path);
            }

            return $this->paginateVfsMountQuery(
                baseQuery: Document::forWorkspace()->where('parent_id', $folder->id)->where('is_system', false),
                labelColumn: 'title',
                limit: $budget->maxEntries,
                scanLimit: $budget->maxFiles,
                state: $state,
                canAccess: fn (Document $doc): bool => $this->canAccessDocument($agent, $doc),
                toEntry: fn (Document $doc): VfsEntry => $this->documentEntry($agent, $doc, rtrim($path, '/').'/'.$this->slug($doc->title)),
            );
        }

        $doc = $this->agentDocumentFor($target, $remaining, $path);
        if (! $doc->is_folder) {
            throw VfsError::notReadable($path);
        }

        return $this->pageAgentDocumentChildren($agent, $target, $doc, $path, $budget, $state);
    }

    /**
     * @param  list<string>  $segments
     */
    private function readAgents(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listAgents($agent, $segments, $path, $budget));
        }

        if (isset($segments[1]) && $segments[1] === 'by-id' && ! isset($segments[2])) {
            return $this->entriesToText($this->listAgents($agent, $segments, $path, $budget));
        }

        $target = $this->resolveAgent($agent, array_slice($segments, 1), $path);
        $this->assertCanAccessAgentDocs($agent, $target, $path);
        $remaining = $this->agentRemainingSegments(array_slice($segments, 1));

        if ($remaining === []) {
            return $this->entriesToText($this->listAgents($agent, $segments, $path, $budget));
        }

        if ($remaining[0] === 'tasks') {
            return $this->readTasks($agent, ['tasks', 'by-agent', $this->slug($target->name), ...array_slice($remaining, 1)], "/tasks/by-agent/{$this->slug($target->name)}", $budget);
        }

        if ($remaining[0] === 'files') {
            $folder = $this->agentDocumentFolder($target, $path);
            if ($folder === null) {
                throw VfsError::notFound($path);
            }

            return $this->entriesToText($this->documentChildren($agent, $folder, $path, $budget));
        }

        $doc = $this->agentDocumentFor($target, $remaining, $path);
        if ($doc->is_folder) {
            return $this->entriesToText($this->agentDocumentChildren($agent, $target, $doc, $path, $budget));
        }

        return VfsText::truncate((string) $doc->content, $budget->maxBytes);
    }

    /**
     * @param  list<string>  $parts
     */
    private function resolveAgent(User $viewer, array $parts, string $path): User
    {
        if (isset($parts[0], $parts[1]) && $parts[0] === 'by-id') {
            return User::where('workspace_id', $viewer->workspace_id)
                ->where('type', 'agent')
                ->find($parts[1]) ?? throw VfsError::notFound($path);
        }

        $slug = $parts[0] ?? '';

        return User::where('workspace_id', $viewer->workspace_id)
            ->where('type', 'agent')
            ->lazy(100)
            ->first(fn (User $candidate): bool => Str::slug($candidate->name) === $slug) ?? throw VfsError::notFound($path);
    }

    /**
     * @param  list<string>  $agentPath
     * @return list<string>
     */
    private function agentRemainingSegments(array $agentPath): array
    {
        if (($agentPath[0] ?? null) === 'by-id') {
            return array_slice($agentPath, 2);
        }

        return array_slice($agentPath, 1);
    }

    /**
     * @param  list<string>  $remaining
     */
    private function agentDocumentFor(User $target, array $remaining, string $path): Document
    {
        $folder = $this->agentDocumentFolder($target, $path) ?? throw VfsError::notFound($path);
        if ($remaining === []) {
            return $folder;
        }

        return $this->resolveDocumentPathFromParent($folder->id, $remaining, $path);
    }

    private function agentDocumentFolder(User $target, string $path): ?Document
    {
        if (! $target->docs_folder_id) {
            return null;
        }

        return Document::forWorkspace()->find($target->docs_folder_id);
    }

    private function assertCanAccessAgentDocs(User $viewer, User $target, string $path): void
    {
        if ($viewer->id === $target->id || $target->manager_id === $viewer->id || $viewer->manager_id === $target->id) {
            return;
        }

        throw VfsError::permissionDenied($path, 'agent memory is private');
    }

    /**
     * @return list<VfsEntry>
     */
    private function agentDocumentChildren(User $viewer, User $target, Document $folder, string $path, VfsBudget $budget): array
    {
        return $this->pageAgentDocumentChildren($viewer, $target, $folder, $path, $budget, [])['entries'];
    }

    /**
     * Agent identity and memory trees are relationship-scoped rather than
     * governed by general document-folder grants. Preserve that boundary while
     * applying the same folder/title/id keyset as the document mount.
     *
     * @param  array<string, mixed>  $state
     * @return array{entries: list<VfsEntry>, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageAgentDocumentChildren(User $viewer, User $target, Document $folder, string $path, VfsBudget $budget, array $state): array
    {
        $this->assertCanAccessAgentDocs($viewer, $target, $path);

        return $this->paginateVfsMountQuery(
            baseQuery: Document::forWorkspace()->where('parent_id', $folder->id),
            labelColumn: 'title',
            limit: $budget->maxEntries,
            scanLimit: $budget->maxFiles,
            state: $state,
            canAccess: fn (Document $doc): bool => true,
            toEntry: fn (Document $doc): VfsEntry => $this->agentDocumentEntry($viewer, $target, $doc, rtrim($path, '/').'/'.$this->slug($doc->title)),
        );
    }

    private function agentDocumentEntry(User $viewer, User $target, Document $doc, string $path): VfsEntry
    {
        $remaining = $this->agentRemainingSegments(array_slice($this->segments($path), 1));
        $isIdentityFile = ! $doc->is_folder && ($remaining[0] ?? null) === 'identity';
        $isMemoryFile = ! $doc->is_folder && ($remaining[0] ?? null) === 'memory';
        $patchTools = $this->agentDocumentPatchTools($viewer, $target, $doc, $path, $remaining);
        $canPatch = $patchTools !== null && $this->mutationDecision($viewer, $path, $patchTools)->allowedForCapability();

        $metadata = [
            'title' => $doc->title,
            'is_system' => $doc->is_system,
            'content_format' => $doc->content_format ?? 'markdown',
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ];

        if ($patchTools !== null) {
            $metadata = [
                ...$metadata,
                ...$this->mutationToolMetadata(
                    $viewer,
                    $patchTools,
                    $isIdentityFile
                        ? 'Patch this identity file through vfs_patch; managers may patch direct report identity files.'
                        : 'Patch this memory file through vfs_patch.',
                ),
            ];
        } elseif ($isIdentityFile) {
            $metadata = [
                ...$metadata,
                ...$this->readOnlyMetadata(
                    'Agent identity files are prompt-critical and only self or manager edits are allowed.',
                    'update_agent_identity_file',
                    'Use agent identity tools or VFS patch from the agent itself or its manager.',
                ),
            ];
        } elseif ($isMemoryFile) {
            $metadata = [
                ...$metadata,
                ...$this->readOnlyMetadata(
                    'This memory file is not patchable for the current agent relationship.',
                    'vfs_patch',
                    'Agents can patch their own memory files; managers can patch only direct report MEMORY.md.',
                ),
            ];
        }

        $capabilities = ['read', 'search'];
        if ($canPatch) {
            $capabilities[] = 'patch';
        }

        return new VfsEntry(
            name: $doc->title,
            path: $path,
            type: $doc->is_folder ? 'directory' : 'file',
            canonicalPath: "/docs/by-id/{$doc->id}",
            backendType: 'document',
            backendId: $doc->id,
            version: $this->version($doc->updated_at?->toIso8601String(), $doc->content ?? ''),
            capabilities: $doc->is_folder ? ['browse', 'read', 'search'] : $capabilities,
            metadata: $metadata,
        );
    }
}

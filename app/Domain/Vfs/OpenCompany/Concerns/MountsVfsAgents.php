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
     * @return list<VfsEntry>
     */
    private function listAgents(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        if (count($segments) === 1) {
            $entries = [new VfsEntry('by-id', '/agents/by-id', 'directory', capabilities: ['browse'])];
            User::where('workspace_id', $agent->workspace_id)
                ->where('type', 'agent')
                ->orderBy('name')
                ->limit(max(0, $budget->maxEntries - count($entries)))
                ->get()
                ->each(function (User $target) use (&$entries): void {
                    $entries[] = new VfsEntry(Str::slug($target->name), '/agents/'.Str::slug($target->name), 'directory', "/agents/by-id/{$target->id}", 'agent', $target->id, capabilities: ['browse', 'read']);
                });

            return $entries;
        }

        if (($segments[1] ?? null) === 'by-id' && ! isset($segments[2])) {
            return User::where('workspace_id', $agent->workspace_id)
                ->where('type', 'agent')
                ->orderBy('name')
                ->limit($budget->maxEntries)
                ->get()
                ->map(fn (User $target): VfsEntry => new VfsEntry(
                    (string) $target->id,
                    "/agents/by-id/{$target->id}",
                    'directory',
                    "/agents/by-id/{$target->id}",
                    'agent',
                    $target->id,
                    capabilities: ['browse', 'read'],
                    metadata: ['name' => $target->name],
                ))
                ->all();
        }

        $target = $this->resolveAgent($agent, array_slice($segments, 1), $path);
        $this->assertCanAccessAgentDocs($agent, $target, $path);

        $remaining = $this->agentRemainingSegments(array_slice($segments, 1));
        if ($remaining === []) {
            $entries = [
                new VfsEntry('identity', rtrim($path, '/').'/identity', 'directory', "/agents/by-id/{$target->id}/identity", 'agent', $target->id, capabilities: ['browse', 'read']),
                new VfsEntry('memory', rtrim($path, '/').'/memory', 'directory', "/agents/by-id/{$target->id}/memory", 'agent', $target->id, capabilities: ['browse', 'read', 'search', 'patch']),
                new VfsEntry('tasks', rtrim($path, '/').'/tasks', 'directory', "/agents/by-id/{$target->id}/tasks", 'agent', $target->id, capabilities: ['browse', 'read']),
            ];
            if ($this->agentDocumentFolder($target, $path) !== null) {
                $entries[] = new VfsEntry('files', rtrim($path, '/').'/files', 'directory', "/agents/by-id/{$target->id}/files", 'agent', $target->id, capabilities: ['browse', 'read']);
            }

            return $entries;
        }

        if (($remaining[0] ?? null) === 'tasks') {
            $taskSegments = ['tasks', 'by-agent', $this->slug($target->name), ...array_slice($remaining, 1)];

            return $this->listTasks($agent, $taskSegments, "/tasks/by-agent/{$this->slug($target->name)}", $budget);
        }

        if (($remaining[0] ?? null) === 'files') {
            $folder = $this->agentDocumentFolder($target, $path);
            if ($folder === null) {
                throw VfsError::notFound($path);
            }

            return $this->documentChildren($agent, $folder, $path, $budget);
        }

        $doc = $this->agentDocumentFor($target, $remaining, $path);
        if (! $doc->is_folder) {
            throw VfsError::notReadable($path);
        }

        return $this->documentChildren($agent, $doc, $path, $budget);
    }

    private function readAgents(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listAgents($agent, $segments, $path, $budget));
        }

        if (($segments[1] ?? null) === 'by-id' && ! isset($segments[2])) {
            return $this->entriesToText($this->listAgents($agent, $segments, $path, $budget));
        }

        $target = $this->resolveAgent($agent, array_slice($segments, 1), $path);
        $this->assertCanAccessAgentDocs($agent, $target, $path);
        $remaining = $this->agentRemainingSegments(array_slice($segments, 1));

        if ($remaining === []) {
            return $this->entriesToText($this->listAgents($agent, $segments, $path, $budget));
        }

        if (($remaining[0] ?? null) === 'tasks') {
            return $this->readTasks($agent, ['tasks', 'by-agent', $this->slug($target->name), ...array_slice($remaining, 1)], "/tasks/by-agent/{$this->slug($target->name)}", $budget);
        }

        if (($remaining[0] ?? null) === 'files') {
            $folder = $this->agentDocumentFolder($target, $path);
            if ($folder === null) {
                throw VfsError::notFound($path);
            }

            return $this->entriesToText($this->documentChildren($agent, $folder, $path, $budget));
        }

        $doc = $this->agentDocumentFor($target, $remaining, $path);
        if ($doc->is_folder) {
            return $this->entriesToText($this->documentChildren($agent, $doc, $path, $budget));
        }

        return VfsText::truncate((string) $doc->content, $budget->maxBytes);
    }

    private function resolveAgent(User $viewer, array $parts, string $path): User
    {
        if (($parts[0] ?? null) === 'by-id' && isset($parts[1])) {
            return User::where('workspace_id', $viewer->workspace_id)
                ->where('type', 'agent')
                ->find($parts[1]) ?? throw VfsError::notFound($path);
        }

        $slug = $parts[0] ?? '';

        return User::where('workspace_id', $viewer->workspace_id)
            ->where('type', 'agent')
            ->get()
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
}

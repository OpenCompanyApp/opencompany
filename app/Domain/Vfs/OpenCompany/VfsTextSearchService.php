<?php

namespace App\Domain\Vfs\OpenCompany;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\OpenCompany\Concerns\FindsVfsSourceTextCandidates;
use App\Models\Channel;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkspaceFile;
use App\Services\AgentPermissionService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Source-table lexical candidate finder for VFS text search.
 *
 * This service is not an embedding layer and does not decide final matches.
 * It uses ordinary source tables, backed by PostgreSQL trigram indexes where
 * available, to cheaply find candidate VFS paths. OpenCompanyVfs then re-reads
 * every candidate through the normal adapter (permissions re-checked there)
 * and verifies exact literal/regex line matches before returning anything.
 *
 * Scoping: a requested path inside a mount narrows the candidate query to
 * that subtree using the backend ref the caller resolved for it (a /docs or
 * /files folder: documents/files inside it up to maxDepth levels; a table:
 * its rows; a channel: its messages). Candidates of a narrowed query are
 * by-id paths that are inside the requested subtree by construction, so they
 * skip the textual "under the requested path" filter that only friendly
 * paths can pass.
 */
class VfsTextSearchService
{
    use FindsVfsSourceTextCandidates;

    public function __construct(
        private readonly AgentPermissionService $permissions,
    ) {}

    /**
     * @param  list<string>  $paths  Normalized requested paths
     * @param  array<string, array{backend_type?: string|null, backend_id?: string|null, type?: string|null}>  $scopes  Resolved backend ref per requested path
     * @param  Builder<Channel>|null  $visibleChannels  The channel mount's visibility query
     * @return list<string>
     */
    public function candidatePaths(
        User $agent,
        string $pattern,
        array $paths,
        VfsBudget $budget,
        bool $regex = false,
        array $scopes = [],
        ?Builder $visibleChannels = null,
    ): array {
        if ($regex || trim($pattern) === '') {
            return [];
        }

        $candidates = [];
        foreach ($paths === [] ? ['/'] : $paths as $path) {
            [$found, $scoped] = $this->candidatesForPath($agent, $pattern, $path, $budget, $scopes[$path] ?? [], $visibleChannels);
            foreach ($found as $candidate) {
                if (! $scoped && ! $this->candidateIsInsideRequestedPath($candidate, $path)) {
                    continue;
                }
                if (! $scoped && ! $this->candidateIsWithinRequestedDepth($candidate, $path, $budget->maxDepth)) {
                    continue;
                }
                $candidates[$candidate] = true;
                if (count($candidates) >= $budget->maxFiles) {
                    break 2;
                }
            }
        }

        return array_keys($candidates);
    }

    /**
     * @param  array{backend_type?: string|null, backend_id?: string|null, type?: string|null}  $scope
     * @param  Builder<Channel>|null  $visibleChannels
     * @return array{0: list<string>, 1: bool} candidates, and whether they are scoped by backend ref
     */
    private function candidatesForPath(User $agent, string $pattern, string $path, VfsBudget $budget, array $scope, ?Builder $visibleChannels): array
    {
        $backendType = $scope['backend_type'] ?? null;
        $backendId = $scope['backend_id'] ?? null;
        $isDirectory = ($scope['type'] ?? null) === 'directory';

        if ($path !== '/docs' && $this->under($path, '/docs') && $backendType === 'document' && is_string($backendId)) {
            return [$isDirectory ? $this->documentCandidates($pattern, $budget, $this->descendantFolderIds(Document::class, $backendId, $budget->maxDepth)) : [], true];
        }
        if ($path !== '/files' && $this->under($path, '/files') && is_string($backendId) && $isDirectory) {
            return [$this->fileCandidates($agent, $pattern, $budget, $this->descendantFolderIds(WorkspaceFile::class, $backendId, $budget->maxDepth)), true];
        }
        if ($this->under($path, '/tables') && $path !== '/tables' && $backendType === 'data_table' && is_string($backendId)) {
            // Canonical by-id row paths are inside the table by construction.
            return [$this->rowCandidates($pattern, $budget, $backendId), true];
        }
        if ($this->under($path, '/channels') && $path !== '/channels' && $backendType === 'channel' && is_string($backendId) && $visibleChannels !== null) {
            return [$this->channelCandidates($pattern, $budget, $visibleChannels, $backendId), true];
        }

        $found = match (true) {
            $this->under($path, '/docs') => $this->documentCandidates($pattern, $budget),
            $this->under($path, '/files') => $this->fileCandidates($agent, $pattern, $budget),
            $this->under($path, '/tasks') => $this->taskCandidates($pattern, $budget),
            $this->under($path, '/lists') => $this->listCandidates($pattern, $budget),
            $this->under($path, '/channels') => $visibleChannels !== null ? $this->channelCandidates($pattern, $budget, $visibleChannels) : [],
            $this->under($path, '/tables') => array_merge($this->rowCandidates($pattern, $budget), $this->tableCandidates($pattern, $budget)),
            $path === '/' => array_merge(
                $this->documentCandidates($pattern, $budget),
                $this->fileCandidates($agent, $pattern, $budget),
                $this->rowCandidates($pattern, $budget),
                $this->tableCandidates($pattern, $budget),
                $this->taskCandidates($pattern, $budget),
                $this->listCandidates($pattern, $budget),
                $visibleChannels !== null ? $this->channelCandidates($pattern, $budget, $visibleChannels) : [],
            ),
            default => [],
        };

        return [$found, false];
    }

    /**
     * $rootId and its descendant folder ids, breadth-first, at most $maxDepth
     * levels below the root (bounded like the live walk), workspace-scoped.
     *
     * @param  class-string<Document|WorkspaceFile>  $model
     * @return list<string>
     */
    private function descendantFolderIds(string $model, string $rootId, int $maxDepth): array
    {
        $ids = [$rootId];
        $frontier = [$rootId];

        for ($level = 1; $level < max(1, $maxDepth) && $frontier !== [] && count($ids) < 5_000; $level++) {
            $frontier = $model::forWorkspace()
                ->where('is_folder', true)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn (mixed $id): string => (string) $id)
                ->diff($ids)
                ->values()
                ->all();
            array_push($ids, ...$frontier);
        }

        return $ids;
    }

    private function under(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/');
    }

    private function candidateIsInsideRequestedPath(string $candidate, string $requested): bool
    {
        if ($requested === '/') {
            return true;
        }

        return $this->under($candidate, $requested);
    }

    private function candidateIsWithinRequestedDepth(string $candidate, string $requested, int $maxDepth): bool
    {
        // by-id candidates have a fixed shape; their friendly depth is not
        // knowable from the path, and the mount-level query already bounds them.
        if (str_contains($candidate, '/by-id/')) {
            return true;
        }

        $relative = $requested === '/'
            ? trim($candidate, '/')
            : trim(substr($candidate, strlen(rtrim($requested, '/'))), '/');

        if ($relative === '') {
            return true;
        }

        return count(explode('/', $relative)) <= $maxDepth;
    }
}

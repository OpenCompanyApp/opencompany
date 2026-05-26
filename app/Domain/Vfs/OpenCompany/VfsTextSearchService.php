<?php

namespace App\Domain\Vfs\OpenCompany;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\OpenCompany\Concerns\FindsVfsSourceTextCandidates;
use App\Models\User;
use App\Services\AgentPermissionService;

/**
 * Source-table lexical candidate finder for VFS text search.
 *
 * This service is not an embedding layer and does not decide final matches.
 * It uses ordinary source tables, backed by PostgreSQL trigram/FTS indexes
 * where available, to cheaply find candidate VFS paths. OpenCompanyVfs then
 * re-reads every candidate through the normal adapter and verifies exact
 * literal/regex line matches before returning anything to the agent.
 */
class VfsTextSearchService
{
    use FindsVfsSourceTextCandidates;

    public function __construct(
        private readonly AgentPermissionService $permissions,
    ) {}

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    public function candidatePaths(User $agent, string $pattern, array $paths, VfsBudget $budget, bool $regex = false): array
    {
        if ($regex || trim($pattern) === '') {
            return [];
        }

        $candidates = [];
        foreach ($paths === [] ? ['/'] : $paths as $path) {
            foreach ($this->candidatesForPath($agent, $pattern, $path, $budget) as $candidate) {
                if (! $this->candidateIsInsideRequestedPath($candidate, $path)) {
                    continue;
                }
                if (! $this->candidateIsWithinRequestedDepth($candidate, $path, $budget->maxDepth)) {
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
     * @return list<string>
     */
    private function candidatesForPath(User $agent, string $pattern, string $path, VfsBudget $budget): array
    {
        return match (true) {
            $this->under($path, '/docs') => $this->documentCandidates($pattern, $budget),
            $this->under($path, '/files') => $this->fileCandidates($agent, $pattern, $budget),
            $this->under($path, '/tasks') => $this->taskCandidates($pattern, $budget),
            $this->under($path, '/lists') => $this->listCandidates($pattern, $budget),
            $this->under($path, '/channels') => $this->channelCandidates($agent, $pattern, $budget),
            $this->under($path, '/tables') => $this->tableCandidates($pattern, $budget),
            $path === '/' => array_merge(
                $this->documentCandidates($pattern, $budget),
                $this->fileCandidates($agent, $pattern, $budget),
                $this->taskCandidates($pattern, $budget),
                $this->listCandidates($pattern, $budget),
                $this->channelCandidates($agent, $pattern, $budget),
                $this->tableCandidates($pattern, $budget),
            ),
            default => [],
        };
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
        if ($requested === '/') {
            $relative = trim($candidate, '/');
        } else {
            $relative = trim(substr($candidate, strlen(rtrim($requested, '/'))), '/');
        }

        if ($relative === '') {
            return true;
        }

        return count(explode('/', $relative)) <= $maxDepth;
    }
}

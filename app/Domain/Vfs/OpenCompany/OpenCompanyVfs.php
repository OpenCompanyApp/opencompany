<?php

namespace App\Domain\Vfs\OpenCompany;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsPage;
use App\Domain\Vfs\Core\VfsPageCursor;
use App\Domain\Vfs\Core\VfsReadPage;
use App\Domain\Vfs\OpenCompany\Concerns\AuthorizesVfsDocuments;
use App\Domain\Vfs\OpenCompany\Concerns\BuildsVfsReadOnlyMetadata;
use App\Domain\Vfs\OpenCompany\Concerns\BuildsVfsToolCatalogIndex;
use App\Domain\Vfs\OpenCompany\Concerns\EvaluatesVfsMutationPolicy;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsAgents;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsApprovals;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsAutomations;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsCollaboration;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsDocuments;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsFiles;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsLists;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsTasks;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsTools;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsWorkspace;
use App\Domain\Vfs\OpenCompany\Concerns\MutatesVfsContent;
use App\Domain\Vfs\OpenCompany\Concerns\MutatesVfsOpenCompanyModels;
use App\Domain\Vfs\OpenCompany\Concerns\PaginatesVfsMountQueries;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsDirectEntries;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsDocumentFileEntries;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsDocuments;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsEntries;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsPaths;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsResourceDirectEntries;
use App\Domain\Vfs\OpenCompany\Concerns\RunsVfsFileMutations;
use App\Domain\Vfs\OpenCompany\Concerns\SearchesVfsContent;
use App\Domain\Vfs\OpenCompany\Concerns\StatsVfsPaths;
use App\Domain\Vfs\OpenCompany\Policy\VfsMutationPolicy;
use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;

/**
 * OpenCompany-backed virtual filesystem service.
 *
 * This facade owns routing and public VFS operations. Domain-specific mounts and
 * mutation helpers live in traits under OpenCompany\Concerns so new mounts can
 * be added without concentrating every model rule in this one class again.
 */
class OpenCompanyVfs
{
    use AuthorizesVfsDocuments;
    use BuildsVfsReadOnlyMetadata;
    use BuildsVfsToolCatalogIndex;
    use EvaluatesVfsMutationPolicy;
    use MountsVfsAgents;
    use MountsVfsApprovals;
    use MountsVfsAutomations;
    use MountsVfsCollaboration;
    use MountsVfsDocuments;
    use MountsVfsFiles;
    use MountsVfsLists;
    use MountsVfsTasks;
    use MountsVfsTools;
    use MountsVfsWorkspace;
    use MutatesVfsContent;
    use MutatesVfsOpenCompanyModels;
    use PaginatesVfsMountQueries;
    use ResolvesVfsDirectEntries;
    use ResolvesVfsDocumentFileEntries;
    use ResolvesVfsDocuments;
    use ResolvesVfsEntries;
    use ResolvesVfsPaths;
    use ResolvesVfsResourceDirectEntries;
    use RunsVfsFileMutations;
    use SearchesVfsContent;
    use StatsVfsPaths;

    public function __construct(
        private readonly AgentPermissionService $permissions,
        private readonly FileSystemService $files,
        private readonly ToolRegistry $tools,
        private readonly VfsTextSearchService $textSearch,
        private readonly VfsMutationPolicy $mutationPolicy,
    ) {}

    /**
     * @return list<VfsEntry>
     */
    public function list(User $agent, string $path, ?VfsBudget $budget = null): array
    {
        $budget ??= new VfsBudget;
        $path = $this->normalizePath($path);
        $segments = $this->segments($path);

        if ($segments === []) {
            return array_slice($this->rootEntries(), 0, $budget->maxEntries);
        }

        $entries = match ($segments[0]) {
            'docs' => $this->listDocs($agent, $segments, $path, $budget),
            'files' => $this->listFiles($agent, $segments, $path, $budget),
            'agents' => $this->listAgents($agent, $segments, $path, $budget),
            'tasks' => $this->listTasks($agent, $segments, $path, $budget),
            'lists' => $this->listLists($agent, $segments, $path, $budget),
            'channels' => $this->listChannels($agent, $segments, $path, $budget),
            'tables' => $this->listTables($agent, $segments, $path, $budget),
            'tools' => $this->listTools($path),
            'approvals' => $this->listApprovals($agent, $segments, $path, $budget),
            'automations' => $this->listAutomations($agent, $segments, $path, $budget),
            'workspace' => $this->listWorkspace($path),
            default => throw VfsError::notFound($path),
        };

        return array_slice($entries, 0, $budget->maxEntries);
    }

    /**
     * List one structured page with cursor ownership delegated to the mount.
     *
     * Database-backed mounts own stable query contracts. Only deliberately
     * finite synthetic roots retain the compatibility adapter.
     */
    public function listPage(
        User $agent,
        string $path,
        ?VfsBudget $budget = null,
        ?string $cursor = null,
    ): VfsPage {
        $budget ??= new VfsBudget;
        $path = $this->normalizePath($path);
        $segments = $this->segments($path);
        $adapter = match ($segments[0] ?? null) {
            'docs' => 'documents-keyset-v1',
            'files' => 'workspace-files-keyset-v1',
            'tasks' => 'tasks-paged-v1',
            'lists' => 'lists-paged-v1',
            'agents' => 'agents-paged-v1',
            'channels' => 'channels-paged-v1',
            'tables' => 'tables-paged-v1',
            'approvals' => 'approvals-paged-v1',
            'automations' => 'automations-paged-v1',
            default => 'compatibility-offset-v1',
        };
        $scope = $this->paginationScope($agent);
        $cursorPayload = VfsPageCursor::decode($cursor, $path, $adapter, $scope);

        $page = match ($adapter) {
            'documents-keyset-v1' => $this->pageDocs($agent, $segments, $path, $budget, $cursorPayload['state']),
            'workspace-files-keyset-v1' => $this->pageFiles($agent, $segments, $path, $budget, $cursorPayload['state']),
            'tasks-paged-v1' => $this->pageTasks($agent, $segments, $path, $budget, $cursorPayload['state']),
            'lists-paged-v1' => $this->pageLists($agent, $segments, $path, $budget, $cursorPayload['state']),
            'agents-paged-v1' => $this->pageAgents($agent, $segments, $path, $budget, $cursorPayload['state']),
            'channels-paged-v1' => $this->pageChannels($agent, $segments, $path, $budget, $cursorPayload['state']),
            'tables-paged-v1' => $this->pageTables($agent, $segments, $path, $budget, $cursorPayload['state']),
            'approvals-paged-v1' => $this->pageApprovals($agent, $segments, $path, $budget, $cursorPayload['state']),
            'automations-paged-v1' => $this->pageAutomations($agent, $segments, $path, $budget, $cursorPayload['state']),
            default => $this->compatibilityPage($agent, $path, $budget, $cursorPayload['state']),
        };

        $nextCursor = $page['has_more']
            ? VfsPageCursor::encode(
                $path,
                $adapter,
                $scope,
                $page['next_state'],
                $cursorPayload['seen'] + count($page['entries']),
            )
            : null;

        return new VfsPage(
            entries: $page['entries'],
            truncated: $page['has_more'],
            nextCursor: $nextCursor,
            seenBefore: $cursorPayload['seen'],
            scanned: $page['scanned'],
            skipped: $page['skipped'],
        );
    }

    /**
     * Preserve bounded offset behavior for mounts not included in this slice.
     *
     * @param  array<string, mixed>  $state
     * @return array{
     *     entries: list<VfsEntry>,
     *     has_more: bool,
     *     next_state: array<string, mixed>,
     *     scanned: int,
     *     skipped: int
     * }
     */
    private function compatibilityPage(User $agent, string $path, VfsBudget $budget, array $state): array
    {
        $offset = $state['offset'] ?? 0;
        if (! is_int($offset) || $offset < 0) {
            throw VfsError::invalid('cursor is invalid or expired.', ['field' => 'cursor']);
        }

        $probeBudget = new VfsBudget(
            maxEntries: min($offset + $budget->maxEntries + 1, 1_000),
            maxDepth: $budget->maxDepth,
            maxBytes: $budget->maxBytes,
            maxFiles: $budget->maxFiles,
            maxMatches: $budget->maxMatches,
            maxLineLength: $budget->maxLineLength,
        );
        $rawEntries = $this->list($agent, $path, $probeBudget);
        $entries = array_slice($rawEntries, $offset, $budget->maxEntries);
        $hasMore = count($rawEntries) > $offset + $budget->maxEntries;

        return [
            'entries' => $entries,
            'has_more' => $hasMore,
            'next_state' => ['offset' => $offset + count($entries)],
            'scanned' => count($entries),
            'skipped' => 0,
        ];
    }

    /**
     * Collect visible entries across mount pages up to an explicit operation
     * budget. Shell traversal, globbing, and archive callers use this method
     * because they expose bounded one-shot output rather than cursors of their
     * own; stopping after the first sparse permission page would be incorrect.
     *
     * @return list<VfsEntry>
     */
    public function listBounded(User $agent, string $path, VfsBudget $budget): array
    {
        $entries = [];
        $cursor = null;
        $scanned = 0;

        do {
            $remainingEntries = $budget->maxEntries - count($entries);
            $remainingScan = $budget->maxFiles - $scanned;
            if ($remainingEntries <= 0 || $remainingScan <= 0) {
                break;
            }

            $page = $this->listPage($agent, $path, new VfsBudget(
                maxEntries: $remainingEntries,
                maxDepth: $budget->maxDepth,
                maxBytes: $budget->maxBytes,
                maxFiles: $remainingScan,
                maxMatches: $budget->maxMatches,
                maxLineLength: $budget->maxLineLength,
            ), $cursor);
            array_push($entries, ...$page->entries);
            $scanned += $page->scanned;
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        return $entries;
    }

    /**
     * Read one valid chunk from an ordinary file or a generated collection.
     * Collection cursors use the same path/workspace/agent binding as listings.
     */
    public function readPage(
        User $agent,
        string $path,
        ?VfsBudget $budget = null,
        ?string $cursor = null,
    ): VfsReadPage {
        $budget ??= new VfsBudget;
        $path = $this->normalizePath($path);
        $segments = $this->segments($path);
        $adapter = $this->readPageAdapter($segments);
        $payload = VfsPageCursor::decode($cursor, $path, $adapter, $this->paginationScope($agent));

        $page = match ($adapter) {
            'channel-transcript-keyset-v1' => $this->readChannelTranscriptPage($agent, $segments, $path, $budget, $payload['state']),
            'table-rows-export-keyset-v1' => $this->readTableRowsExportPage($segments, $path, $budget, $payload['state']),
            'workspace-members-keyset-v1' => $this->pageWorkspaceMembersExport($budget, $payload['state']),
            default => [
                'content' => $this->read($agent, $path, $budget),
                'returned' => 1,
                'has_more' => false,
                'next_state' => [],
                'scanned' => 1,
                'skipped' => 0,
            ],
        };

        $nextCursor = $page['has_more']
            ? VfsPageCursor::encode(
                $path,
                $adapter,
                $this->paginationScope($agent),
                $page['next_state'],
                $payload['seen'] + $page['returned'],
            )
            : null;

        return new VfsReadPage(
            content: $page['content'],
            truncated: $page['has_more'],
            nextCursor: $nextCursor,
            seenBefore: $payload['seen'],
            returned: $page['returned'],
            scanned: $page['scanned'],
            skipped: $page['skipped'],
        );
    }

    /** @param list<string> $segments */
    private function readPageAdapter(array $segments): string
    {
        if (($segments[0] ?? null) === 'channels' && in_array('messages', $segments, true) && preg_match('/^\d{4}-\d{2}-\d{2}\.md$/', end($segments) ?: '') === 1) {
            return 'channel-transcript-keyset-v1';
        }
        if (($segments[0] ?? null) === 'tables' && ($segments[2] ?? null) === 'rows.ndjson' && ! isset($segments[3])) {
            return 'table-rows-export-keyset-v1';
        }
        if ($segments === ['workspace', 'members.json']) {
            return 'workspace-members-keyset-v1';
        }

        return 'plain-read-v1';
    }

    private function paginationScope(User $agent): string
    {
        return hash('sha256', (string) $agent->workspace_id.'|'.(string) $agent->id);
    }

    public function read(User $agent, string $path, ?VfsBudget $budget = null): string
    {
        $budget ??= new VfsBudget;
        $path = $this->normalizePath($path);
        $segments = $this->segments($path);

        if ($segments === []) {
            return $this->entriesToText($this->rootEntries());
        }

        return match ($segments[0]) {
            'docs' => $this->readDocs($agent, $segments, $path, $budget),
            'files' => $this->readFiles($agent, $segments, $path, $budget),
            'agents' => $this->readAgents($agent, $segments, $path, $budget),
            'tasks' => $this->readTasks($agent, $segments, $path, $budget),
            'lists' => $this->readLists($agent, $segments, $path, $budget),
            'channels' => $this->readChannels($agent, $segments, $path, $budget),
            'tables' => $this->readTables($agent, $segments, $path, $budget),
            'tools' => $this->readTools($agent, $path, $budget),
            'approvals' => $this->readApprovals($agent, $segments, $path, $budget),
            'automations' => $this->readAutomations($agent, $segments, $path, $budget),
            'workspace' => $this->readWorkspace($agent, $segments, $path, $budget),
            default => throw VfsError::notFound($path),
        };
    }
}

<?php

namespace App\Domain\Vfs\OpenCompany;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsPage;
use App\Domain\Vfs\Core\VfsPageCursor;
use App\Domain\Vfs\Core\VfsReadPage;
use App\Domain\Vfs\Core\VfsText;
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
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsMe;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsTasks;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsTools;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsWorkspace;
use App\Domain\Vfs\OpenCompany\Concerns\MutatesVfsContent;
use App\Domain\Vfs\OpenCompany\Concerns\MutatesVfsOpenCompanyModels;
use App\Domain\Vfs\OpenCompany\Concerns\PaginatesVfsMountQueries;
use App\Domain\Vfs\OpenCompany\Concerns\RecordsVfsHistory;
use App\Domain\Vfs\OpenCompany\Concerns\RegistersVfsBuiltinMounts;
use App\Domain\Vfs\OpenCompany\Concerns\RemembersVfsIdempotency;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsDirectEntries;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsDocumentFileEntries;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsDocuments;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsEntries;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsFriendlyNames;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsPaths;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsResourceDirectEntries;
use App\Domain\Vfs\OpenCompany\Concerns\RunsVfsFileMutations;
use App\Domain\Vfs\OpenCompany\Concerns\SearchesVfsContent;
use App\Domain\Vfs\OpenCompany\Concerns\StatsVfsPaths;
use App\Domain\Vfs\OpenCompany\Policy\VfsMutationPolicy;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;

/**
 * OpenCompany-backed virtual filesystem service.
 *
 * This facade owns routing and public VFS operations. Domain-specific mounts and
 * mutation helpers live in traits under OpenCompany\Concerns so new mounts can
 * be added without concentrating every model rule in this one class again.
 *
 * Workspace boundary: every public entry point first calls
 * assertBoundWorkspace(). Mount queries scope through forWorkspace() (the bound
 * workspace) while some helpers key off $agent->workspace_id; the assertion is
 * what makes those two equivalent. Do not add a public entry point without it.
 *
 * Read modes: read()/readPage() are display reads (budgeted, may end with the
 * visible truncation line). readContent() is the content read used by every
 * read-modify-write path; it never truncates. See VfsBudget.
 *
 * Path routing: every public entry point routes its path through routePath()
 * (normalize, then rewrite `/me/…` onto the acting agent's backing paths), so
 * aliases inherit the backing mount's checks. `<object>/.history/` views are
 * dispatched before the mounts (RecordsVfsHistory); `<mount>/.trash/` views
 * are served by their mounts. `/README.md` is the root orientation file.
 *
 * Mount dispatch: every per-path operation resolves its VfsMount through
 * mounts() (longest prefix; built-ins from RegistersVfsBuiltinMounts plus the
 * container's VfsMountRegistry). Governance stays here, around the mount:
 * workspace assertion, routing, idempotency, the mutation policy for mounts
 * that do not govern their own mutations (governMountMutation), and — in
 * AuditedOpenCompanyVfs — audit and events. Never add a per-root `match`
 * back; register a mount instead.
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
    use MountsVfsMe;
    use MountsVfsTasks;
    use MountsVfsTools;
    use MountsVfsWorkspace;
    use MutatesVfsContent;
    use MutatesVfsOpenCompanyModels;
    use PaginatesVfsMountQueries;
    use RecordsVfsHistory;
    use RegistersVfsBuiltinMounts;
    use RemembersVfsIdempotency;
    use ResolvesVfsDirectEntries;
    use ResolvesVfsDocumentFileEntries;
    use ResolvesVfsDocuments;
    use ResolvesVfsEntries;
    use ResolvesVfsFriendlyNames;
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
     * Assert that the ambient workspace binding (`currentWorkspace`, used by
     * forWorkspace()) is the acting agent's workspace.
     *
     * Invariant: call this before any query, policy decision, or approval at
     * every public VFS entry point (and in VfsCommandExecutor::execute, which
     * evaluates mutation policy before it reaches this facade). A mismatch is a
     * host wiring bug — e.g. a queue worker that bound the wrong workspace — and
     * fails closed with permission_denied instead of mixing two tenants.
     *
     * @throws VfsError permission_denied (reason workspace_binding_mismatch)
     */
    public function assertBoundWorkspace(User $agent): void
    {
        $bound = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;

        if (! $bound instanceof Workspace
            || $agent->workspace_id === null
            || (string) $bound->getKey() !== (string) $agent->workspace_id) {
            throw VfsError::workspaceBindingMismatch();
        }
    }

    /**
     * Normalize a caller path and resolve the `/me` alias onto its backing
     * path. Invariant: every public entry point uses this, never
     * normalizePath() alone, so `/me/…` cannot skip a backing mount's checks.
     */
    private function routePath(User $agent, string $path): string
    {
        // Friendly-name memo is per operation: a mutation earlier in the same
        // process may have renamed or added siblings.
        $this->friendlyTokenCache = [];

        return $this->rewriteMePath($agent, $this->normalizePath($path));
    }

    /**
     * The subject the mutation paths hand to VfsMutationPolicy for $path:
     * normalized, with `/me/…` rewritten onto its backing path.
     *
     * Approval wrappers and replay (VfsDirectToolOperation) build their
     * subject from tool parameters before the facade runs; they must use
     * this, or an approved `/me/…` call is compared against the backing
     * subject on replay, rejected, and asks for a second approval. Pure path
     * mapping: no authorization, no audit.
     */
    public function policySubject(User $agent, string $path): string
    {
        return $this->rewriteMePath($agent, $this->normalizePath($path));
    }

    /**
     * @return list<VfsEntry>
     */
    public function list(User $agent, string $path, ?VfsBudget $budget = null): array
    {
        $this->assertBoundWorkspace($agent);
        $budget ??= new VfsBudget;
        $path = $this->routePath($agent, $path);
        $segments = $this->segments($path);

        if ($segments === []) {
            return array_slice($this->rootEntries(), 0, $budget->maxEntries);
        }
        if ($this->isHistoryPath($segments)) {
            return $this->listHistory($agent, $segments, $path, $budget);
        }
        if ($segments === ['README.md']) {
            throw VfsError::notReadable($path);
        }

        $mount = $this->mounts()->resolve($segments) ?? throw VfsError::notFound($path);
        $entries = $mount->list($agent, $segments, $path, $budget);

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
        $this->assertBoundWorkspace($agent);
        $budget ??= new VfsBudget;
        $path = $this->routePath($agent, $path);
        $segments = $this->segments($path);
        // History views and the root are finite: offset compatibility pages.
        $mount = $this->isHistoryPath($segments) ? null : $this->mounts()->resolve($segments);
        $adapter = $mount?->pageAdapter($segments) ?? 'compatibility-offset-v1';
        $scope = $this->paginationScope($agent);
        $cursorPayload = VfsPageCursor::decode($cursor, $path, $adapter, $scope);

        $page = $mount !== null && $adapter !== 'compatibility-offset-v1'
            ? $mount->page($agent, $segments, $path, $budget, $cursorPayload['state'])
            : $this->compatibilityPage($agent, $path, $budget, $cursorPayload['state']);

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
        $this->assertBoundWorkspace($agent);
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
        $this->assertBoundWorkspace($agent);
        $budget ??= new VfsBudget;
        $path = $this->routePath($agent, $path);
        $segments = $this->segments($path);
        $mount = $this->mounts()->resolve($segments);
        $adapter = $mount?->readPageAdapter($segments) ?? 'plain-read-v1';
        $payload = VfsPageCursor::decode($cursor, $path, $adapter, $this->paginationScope($agent));

        $page = $mount !== null && $adapter !== 'plain-read-v1'
            ? $mount->readPage($agent, $segments, $path, $budget, $payload['state'], $adapter)
            : [
                'content' => $this->read($agent, $path, $budget),
                'returned' => 1,
                'has_more' => false,
                'next_state' => [],
                'scanned' => 1,
                'skipped' => 0,
            ];

        // `resumable` collections (the change feed) always return a cursor so
        // a drained feed can be polled again later from where it stopped.
        $nextCursor = $page['has_more'] || ($page['resumable'] ?? false) === true
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

    private function paginationScope(User $agent): string
    {
        return hash('sha256', (string) $agent->workspace_id.'|'.(string) $agent->id);
    }

    /**
     * Display read: the body is capped at $budget->maxBytes and may end with
     * the visible "[... truncated]" line. Never write this result back; use
     * readContent() for read-modify-write.
     */
    public function read(User $agent, string $path, ?VfsBudget $budget = null): string
    {
        $this->assertBoundWorkspace($agent);
        $budget ??= new VfsBudget;
        $path = $this->routePath($agent, $path);
        $segments = $this->segments($path);

        if ($segments === []) {
            return $this->entriesToText($this->rootEntries());
        }
        if ($segments === ['README.md']) {
            $budget->recordCanonicalPath('/README.md');

            return VfsText::fit($this->rootReadme(), $budget, $path);
        }
        if ($this->isHistoryPath($segments)) {
            return $this->readHistory($agent, $segments, $path, $budget);
        }

        $mount = $this->mounts()->resolve($segments) ?? throw VfsError::notFound($path);

        return $mount->read($agent, $segments, $path, $budget);
    }

    /**
     * Content read: the full body of one readable path, never truncated and
     * never carrying the display truncation line.
     *
     * Contract for later phases: this is the only read a caller may write
     * back (patch, append, tee -a, archive create/extract, split). It applies
     * exactly the same read authorization as read() because it routes through
     * the same mounts. Bodies above config('vfs.max_content_bytes') fail with
     * invalid_request (context: path, bytes, max_content_bytes, hint) instead
     * of returning a prefix. Directories fail with not_readable.
     *
     * The version of the served object, when the mount knows it, is available
     * to callers that pass their own budget via readContentWithVersion().
     *
     * @throws VfsError
     */
    public function readContent(User $agent, string $path): string
    {
        return $this->readContentWithVersion($agent, $path)['content'];
    }

    /**
     * Content read that also returns the version token computed from the same
     * bytes (null for generated paths without a version).
     *
     * `canonical_path` is the by-id path of the object served, when the
     * mount knows it (Stream C1 addition; additive to the Phase 1 shape).
     *
     * @return array{content: string, version: string|null, canonical_path: string|null}
     *
     * @throws VfsError
     */
    public function readContentWithVersion(User $agent, string $path): array
    {
        $budget = VfsBudget::forContent((int) config('vfs.max_content_bytes', 20 * 1024 * 1024));
        $content = $this->read($agent, $path, $budget);

        return ['content' => $content, 'version' => $budget->lastReadVersion(), 'canonical_path' => $budget->lastCanonicalPath()];
    }
}

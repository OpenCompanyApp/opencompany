<?php

namespace App\Domain\Vfs\OpenCompany\Mounts;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Models\User;

/**
 * One subtree of the OpenCompany VFS, addressed by a path prefix.
 *
 * Owns: listing, reading, direct stat entries, paging and mutations of the
 * objects under its prefix, plus its line in `ls /` and the README mount map.
 *
 * Does NOT own (the facade, OpenCompanyVfs / AuditedOpenCompanyVfs, runs
 * these around every mount call, so a newly registered mount is governed
 * with no extra code):
 * - the workspace binding assertion (OpenCompanyVfs::assertBoundWorkspace),
 * - path normalization and `/me` alias rewriting (routePath),
 * - `<object>/.history/` views and `/README.md`,
 * - idempotency (RemembersVfsIdempotency),
 * - audit and CER events (AuditedOpenCompanyVfs → VfsAuditor),
 * - the mutation policy and approvals for mounts where
 *   governsMutations() is false: the facade composes mutationTools() for the
 *   operation through VfsMutationPolicy BEFORE the mount is called.
 *
 * Built-in record mounts (/docs, /tables, /lists, /agents …) return true from
 * governsMutations(): their record paths compose domain tools at a precise
 * point (after eligibility and preflight, before the locked write; see
 * MutatesVfsContent's ordering invariant), so they call the policy
 * themselves. Agent breadcrumb: do not "simplify" a new mount into that mode;
 * a mount that returns true must call VfsMutationPolicy on every mutation.
 *
 * Arguments: `$path` is the normalized, alias-rewritten path; `$segments` are
 * ALL of its segments from the root (so `/docs/a` is ['docs', 'a'], also for
 * nested mounts such as `/files/drive`). Mounts throw VfsError for every
 * failure; the defaults in AbstractVfsMount throw the same errors the
 * facade used for roots without that operation.
 *
 * Workspace scope: the bound workspace (`currentWorkspace`) is asserted to be
 * the acting agent's before any mount method runs; mounts must still scope
 * every query (forWorkspace() / whereHas) and never trust ids from paths.
 *
 * Planned mounts plug in here (audit §5.6): `/files/drive/<connection>`
 * (nested prefix under /files), `/connections/<name>` (read-only MCP
 * resources), `/skills` (progressive disclosure), `/apps/<app>`. Register
 * them on the container's VfsMountRegistry; they then appear in `ls /` (top
 * level prefixes) and route by longest prefix.
 */
interface VfsMount
{
    /** Normalized prefix, e.g. `/docs` or `/files/drive`. Never `/`. */
    public function prefix(): string;

    /** One line for `ls /` and the README mount map. Byte-stable. */
    public function description(): string;

    /**
     * True for alias mounts (`/me` → `/agents/by-id/{self}`). Recursive walks
     * and searches starting at `/` skip alias entries so objects are not
     * counted twice; explicit alias paths still work.
     */
    public function isAlias(): bool;

    /**
     * True when the mount evaluates VfsMutationPolicy itself for every
     * mutation (built-in record mounts). False: the facade runs the policy
     * with mutationTools() before calling write/patch/remove/move/mkdir.
     */
    public function governsMutations(): bool;

    /**
     * Permissions the facade composes for one mutation when
     * governsMutations() is false. Defaults (AbstractVfsMount): write →
     * [vfs_write], patch → [vfs_patch], remove/move/mkdir → [] (those verbs
     * are already governed as vfs_write by the calling surface: ToolRegistry
     * permissionAs for vfs_rm/vfs_mv/vfs_mkdir and the shell's vfs_exec +
     * vfs_write composition, exactly like built-in /files).
     *
     * @param  'write'|'patch'|'remove'|'move'|'mkdir'  $operation
     * @return list<string>
     */
    public function mutationTools(string $operation, string $path): array;

    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    public function list(User $agent, array $segments, string $path, VfsBudget $budget): array;

    /**
     * Display or content read (see VfsBudget::isContentRead()). Directories
     * may render their listing for display reads; content reads of
     * directories fail with not_readable.
     *
     * @param  list<string>  $segments
     */
    public function read(User $agent, array $segments, string $path, VfsBudget $budget): string;

    /**
     * Direct stat lookup without listing the parent; null = unknown here
     * (stat then falls back to listing the parent).
     *
     * @param  list<string>  $segments
     */
    public function entry(User $agent, array $segments, string $path): ?VfsEntry;

    /**
     * Cursor adapter name for structured listing pages, or null for the
     * bounded offset compatibility adapter. The name is bound into cursors:
     * changing it invalidates outstanding cursors.
     *
     * @param  list<string>  $segments
     */
    public function pageAdapter(array $segments): ?string;

    /**
     * One structured page for pageAdapter(). Only called when it is non-null.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{entries: list<VfsEntry>, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    public function page(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array;

    /**
     * Cursor adapter for paged collection files (rows.ndjson, transcripts,
     * feeds), or null for a plain read.
     *
     * @param  list<string>  $segments
     */
    public function readPageAdapter(array $segments): ?string;

    /**
     * One page of a paged collection file. `resumable: true` keeps a cursor
     * even when drained (polled feeds).
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{content: string, returned: int, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int, resumable?: bool}
     */
    public function readPage(User $agent, array $segments, string $path, VfsBudget $budget, array $state, string $adapter): array;

    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    public function write(User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters, bool $permissionAlreadyChecked): array;

    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    public function patch(User $agent, array $segments, string $path, string $patch, ?string $version, array $toolParameters): array;

    /**
     * Move to trash (never a hard delete).
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    public function remove(User $agent, array $segments, string $path, array $toolParameters): array;

    /**
     * Move/rename within this mount, or restore from its `.trash/` view.
     * The facade has already rejected cross-mount moves.
     *
     * @param  list<string>  $segments  Segments of $source
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    public function move(User $agent, array $segments, string $source, string $destination, array $toolParameters): array;

    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    public function makeDirectory(User $agent, array $segments, string $path, array $toolParameters): array;

    /**
     * Index candidates for a search under $path (paths the facade then stats
     * and reads through this mount, so permissions are re-checked). Built-in
     * mounts return [] because VfsTextSearchService indexes them centrally.
     *
     * @return list<string>
     */
    public function searchCandidates(User $agent, string $pattern, string $path, VfsBudget $budget, bool $regex): array;
}

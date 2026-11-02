<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsTextPatch;
use App\Models\User;

/**
 * Public write and patch operations for VFS paths.
 *
 * Ordering invariant for every mutation routed from here:
 *
 *   1. resolve + domain eligibility (an impossible edit never creates an approval)
 *   2. preflight the edit against a content read (no-match/ambiguous fail early)
 *   3. mutation policy (may create an approval and throw approval_required)
 *   4. locked compare-and-write in a DB transaction (authoritative version and
 *      anchor checks against the row as it is now)
 *
 * Step 3 must stay outside the transaction in step 4: an approval row created
 * inside a transaction that then rolls back would vanish while the agent is
 * told to wait for it.
 */
trait MutatesVfsContent
{
    /**
     * Create or overwrite raw content at a writable path (/files only).
     *
     * A supplied $version is enforced atomically (row lock + recompute);
     * stale writes fail with `conflict`. Versions stay optional for in-app
     * callers in this phase.
     *
     * @param  array<string, mixed>|null  $toolParameters
     * @param  bool  $permissionAlreadyChecked  Internal command-engine handoff only. Set this after VfsCommandExecutor has composed vfs_exec with vfs_write; never derive it from agent or tool input.
     * @return array<string, mixed>
     */
    public function write(
        User $agent,
        string $path,
        string $content,
        string $mode = 'create',
        ?string $version = null,
        ?array $toolParameters = null,
        bool $permissionAlreadyChecked = false,
    ): array {
        $this->assertBoundWorkspace($agent);

        if (! in_array($mode, ['create', 'overwrite'], true)) {
            throw VfsError::invalid('write mode must be create or overwrite.', [
                'mode' => $mode,
                'supported_modes' => ['create', 'overwrite'],
            ]);
        }

        $path = $this->normalizePath($path);
        $segments = $this->segments($path);

        if ($segments === []) {
            throw VfsError::notWritable($path);
        }

        $toolParameters ??= array_filter([
            'path' => $path,
            'content' => $content,
            'mode' => $mode,
            'version' => $version,
        ], fn (mixed $value): bool => $value !== null);

        // Shell mutations have already passed the command engine's composed
        // vfs_exec + vfs_write policy. Every other caller is checked here so
        // direct service, replay, and background usage cannot bypass vfs_write.
        return match ($segments[0]) {
            'files' => $this->writeWorkspaceFile($agent, $segments, $path, $content, $mode, $version, $toolParameters, ! $permissionAlreadyChecked),
            default => throw VfsError::notWritable($path),
        };
    }

    /**
     * Apply a targeted patch.
     *
     * Text paths (/docs, /files, /agents identity+memory) accept the
     * VfsTextPatch forms: exact match, ambiguous_match unless replace_all,
     * and content-anchored staleness (result.stale_base). Record paths
     * (/tasks, /lists, /tables rows) accept structured JSON and are strict
     * about versions.
     *
     * @param  array<string, mixed>|null  $toolParameters
     * @return array<string, mixed>
     */
    public function patch(User $agent, string $path, string $patch, ?string $version = null, ?array $toolParameters = null): array
    {
        $this->assertBoundWorkspace($agent);

        $path = $this->normalizePath($path);
        $segments = $this->segments($path);
        $toolParameters ??= array_filter([
            'path' => $path,
            'patch' => $patch,
            'version' => $version,
        ], fn (mixed $value): bool => $value !== null);

        if ($segments === []) {
            throw VfsError::notWritable($path);
        }

        $root = $segments[0];

        if ($root === 'tasks') {
            return $this->patchTask($agent, $segments, $path, $patch, $version, $toolParameters);
        }

        if ($root === 'lists') {
            return $this->patchListItem($agent, $segments, $path, $patch, $version, $toolParameters);
        }

        if ($root === 'tables') {
            return $this->patchTableRow($agent, $segments, $path, $patch, $version, $toolParameters);
        }

        if ($root === 'agents') {
            return $this->patchAgentDocument($agent, $segments, $path, $patch, $version, $toolParameters);
        }

        if ($root !== 'docs' && $root !== 'files') {
            throw VfsError::notWritable($path);
        }

        $document = $root === 'docs' ? $this->resolvePatchableDocument($agent, $segments, $path) : null;
        $textPatch = VfsTextPatch::parse($patch);

        // Preflight on a full content read (never a truncated display read)
        // so impossible or ambiguous edits fail before an approval exists.
        // The authoritative application happens again under the row lock.
        $current = $this->readContentWithVersion($agent, $path);
        $textPatch->applyAgainstBase($current['content'], $path, $current['version'], $version);

        $this->assertMutationToolsAllowed($agent, $path, ['vfs_patch'], $toolParameters, $this->channelIdFrom($toolParameters));

        if ($document !== null) {
            return $this->patchDocumentContent($agent, $document, $path, $textPatch, $version, 'VFS patch snapshot before update');
        }

        // A targeted file patch is governed by vfs_patch. Calling the public
        // write surface here would incorrectly add vfs_write as a second
        // requirement, so use the already-scoped backing mutation directly.
        return $this->writeWorkspaceFile(
            $agent,
            $segments,
            $path,
            fn (string $locked, ?string $lockedVersion): array => $textPatch->applyAgainstBase($locked, $path, $lockedVersion, $version),
            'overwrite',
            $version,
        );
    }

    /**
     * @param  array<string, mixed>|null  $parameters
     */
    private function channelIdFrom(?array $parameters): ?string
    {
        return isset($parameters['channelId']) && is_string($parameters['channelId']) ? $parameters['channelId'] : null;
    }

    /**
     * Result fields shared by every text patch that may have applied against
     * a stale base. Phase 2's vfs_edit returns the same keys.
     *
     * @param  array{replacements: int, stale_base: bool}  $applied
     * @return array<string, mixed>
     */
    private function textPatchResultFields(array $applied): array
    {
        return [
            'replacements' => $applied['replacements'],
            ...($applied['stale_base'] ? [
                'stale_base' => true,
                'note' => 'content changed elsewhere since your read; your edit was applied to the current content because its search text still matched exactly once.',
            ] : []),
        ];
    }
}

<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsTextPatch;
use App\Domain\Vfs\OpenCompany\Policy\VfsActionClass;
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
     * Create or overwrite content at a writable path.
     *
     * Writable roots: /files (raw bytes), /docs (documents), /tables rows
     * (`rows/new.json` creates, `rows/by-id/{id}.json` overwrites), /lists
     * items (front-matter markdown; `new.md` inside a project creates) and the
     * acting agent's own notes/ and todo.md (also via /me). `.trash/` and
     * `.history/` views are read-only.
     *
     * A supplied $version is enforced atomically (row lock + recompute);
     * stale writes fail with `conflict`. Overwriting an existing record
     * (document, row, list item, note) REQUIRES the version from a read
     * (missing → invalid_request "read it first"); /files keeps versions
     * optional for in-app callers such as shell redirects.
     *
     * `$toolParameters['idempotency_key']` (optional) makes a retried call
     * return the first call's result (RemembersVfsIdempotency).
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

        $path = $this->routePath($agent, $path);
        $segments = $this->segments($path);

        if ($segments === [] || $this->isHistoryPath($segments)) {
            throw new VfsError('not_writable', "Path is not writable through VFS: {$path}", [
                'path' => $path,
                ...($this->isHistoryPath($segments) ? ['hint' => 'History is read-only. To restore, write the old content to the object path with its current version.'] : []),
            ]);
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
        // Record writes always compose vfs_write with their domain tool: a
        // gated vfs_write after a shell approval then yields one more
        // approval for the backing write, never a silent bypass.
        return $this->withIdempotency($agent, $toolParameters, 'write', $path, function () use ($agent, $segments, $path, $content, $mode, $version, $toolParameters, $permissionAlreadyChecked): array {
            $mount = $this->mounts()->resolve($segments) ?? throw VfsError::notWritable($path);
            $this->governMountMutation($mount, $agent, 'write', $path, $toolParameters, $permissionAlreadyChecked);

            return $mount->write($agent, $segments, $path, $content, $mode, $version, $toolParameters, $permissionAlreadyChecked);
        });
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

        $path = $this->routePath($agent, $path);
        $segments = $this->segments($path);
        $toolParameters ??= array_filter([
            'path' => $path,
            'patch' => $patch,
            'version' => $version,
        ], fn (mixed $value): bool => $value !== null);

        if ($segments === [] || $this->isHistoryPath($segments) || in_array('.trash', $segments, true)) {
            throw VfsError::notWritable($path);
        }

        return $this->withIdempotency($agent, $toolParameters, 'patch', $path, function () use ($agent, $segments, $path, $patch, $version, $toolParameters): array {
            $mount = $this->mounts()->resolve($segments) ?? throw VfsError::notWritable($path);
            $this->governMountMutation($mount, $agent, 'patch', $path, $toolParameters);

            return $mount->patch($agent, $segments, $path, $patch, $version, $toolParameters);
        });
    }

    /**
     * Text patch of a /docs document or /files file (the built-in text
     * mounts' patch; record mounts have their own: patchTask, patchListItem,
     * patchTableRow, patchAgentDocument).
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function patchTextPath(User $agent, array $segments, string $path, string $patch, ?string $version, array $toolParameters): array
    {
        $document = $segments[0] === 'docs' ? $this->resolvePatchableDocument($agent, $segments, $path) : null;
        $textPatch = VfsTextPatch::parse($patch);

        // Preflight on a full content read (never a truncated display read)
        // so impossible or ambiguous edits fail before an approval exists.
        // The authoritative application happens again under the row lock.
        $current = $this->readContentWithVersion($agent, $path);
        $textPatch->applyAgainstBase($current['content'], $path, $current['version'], $version);

        // Declared reversible_edit; the policy raises it to governance for
        // agent identity/memory paths (per-path classifier wins upward).
        $this->assertMutationToolsAllowed($agent, $path, ['vfs_patch'], $toolParameters, $this->channelIdFrom($toolParameters), actionClass: VfsActionClass::REVERSIBLE_EDIT);

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

<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Agents\Tools\Vfs\Support\VfsTextEditPlan;
use App\Agents\Tools\Vfs\Support\VfsUnifiedDiff;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsOperationLog;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Domain\Vfs\Spec\VfsOperationSpecRegistry;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * `vfs_edit`: atomic multi-edit of one text file by exact replacement.
 *
 * Flow (all or nothing):
 *  1. content read with version (never the truncated display read);
 *  2. VfsTextEditPlan applies every edit in memory with Phase 1's
 *     VfsTextPatch rules (not found / ambiguous_match with lines /
 *     replace_all / content-anchored staleness against the caller's version);
 *  3. dry_run stops here and returns a unified diff: no write, no approval;
 *  4. otherwise the plan is reduced to ONE anchored search/replace and sent
 *     through OpenCompanyVfs::patch() with the version from step 1. That is
 *     the governed vfs_patch path: same composed permissions
 *     (vfs_patch [+ update_agent_identity_file]), same approval request and
 *     approved replay (the approval stores a vfs_patch call), same atomic
 *     locked compare-and-write and audit.
 *
 * Governance: vfs_edit has no permission toggle of its own; ToolRegistry
 * evaluates it as vfs_patch (spec `permission`). It is not wrapped in
 * ApprovalWrappedTool: the composed policy inside patch() already requests
 * approval for every gated permission, so a wrapper approval would only
 * duplicate it (and would gate harmless dry runs).
 */
class VfsEdit implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public const TOOL_SLUG = 'vfs_edit';

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function handle(Request $request): string
    {
        $started = microtime(true);
        $ok = false;
        $errorCode = null;
        $path = '';
        try {
            $input = $this->input($request);
            $path = $this->vfs->normalizePath(trim($input->string('path')));
            if (trim($input->string('path')) === '') {
                throw VfsError::invalid('path is required.', ['field' => 'path']);
            }
            $this->assertTextEditable($path);

            $edits = $input->raw('edits');
            if (is_string($edits)) {
                $edits = json_decode($edits, true);
            }
            if (! is_array($edits) || ! array_is_list($edits)) {
                throw VfsError::invalid('edits must be a list of {old_text, new_text, replace_all?} objects.', ['field' => 'edits', 'path' => $path]);
            }

            $givenVersion = $input->optionalString('version');
            ['content' => $current, 'version' => $currentVersion] = $this->vfs->readContentWithVersion($this->agent, $path);
            $plan = VfsTextEditPlan::build($path, $current, $currentVersion, $givenVersion, $edits);

            $summary = [
                'path' => $path,
                'edits_applied' => count($plan->edits),
                'replacements' => $plan->replacements,
                ...($plan->staleBase ? [
                    'stale_base' => true,
                    'note' => 'The file changed since your read; your edits were applied to the current content because each old_text still matched exactly once.',
                ] : []),
            ];

            if ($input->bool('dry_run')) {
                $ok = true;

                return $this->encodeResult([
                    'status' => 'dry_run',
                    ...$summary,
                    'version' => $currentVersion,
                    'diff' => VfsUnifiedDiff::render($path, $current, $plan->content),
                ]);
            }

            if (! $plan->changed()) {
                $ok = true;

                return $this->encodeResult(['status' => 'unchanged', ...$summary, 'version' => $currentVersion]);
            }

            $result = $this->vfs->patch(
                $this->agent,
                $path,
                (string) json_encode($plan->anchoredPatch(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $currentVersion,
                $this->patchParameters($request, $path, $plan, $currentVersion),
            );
            $ok = true;

            return $this->encodeResult([
                'status' => (string) ($result['status'] ?? 'updated'),
                ...$summary,
                'canonical_path' => $result['canonical_path'] ?? $path,
                'version' => $result['version'] ?? null,
                ...(isset($result['indexing']) ? ['indexing' => $result['indexing']] : []),
            ]);
        } catch (\Throwable $e) {
            $errorCode = $e instanceof VfsError ? $e->errorCode : 'internal_error';

            return $this->errorResponse($e);
        } finally {
            event(VfsOperationLog::forAgent(
                $this->agent,
                'tool',
                self::TOOL_SLUG,
                $path !== '' ? $path : (string) ($request['path'] ?? ''),
                null,
                $ok,
                $errorCode,
                (int) round((microtime(true) - $started) * 1000),
                ['edits' => is_array($request['edits'] ?? null) ? count($request['edits']) : null, 'dry_run' => (bool) ($request['dry_run'] ?? false)],
            ));
        }
    }

    /**
     * Parameters stored with an approval request (and hashed for dedupe).
     * They must be a valid vfs_patch call: VFS-composed approvals replay
     * through the direct tool slug the policy recorded (vfs_patch).
     *
     * @return array<string, mixed>
     */
    private function patchParameters(Request $request, string $path, VfsTextEditPlan $plan, ?string $version): array
    {
        return array_filter([
            'path' => $path,
            'patch' => (string) json_encode($plan->anchoredPatch(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'version' => $version,
            'channelId' => $request['channelId'] ?? null,
            'idempotency_key' => $request['idempotency_key'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }

    private function assertTextEditable(string $path): void
    {
        $root = explode('/', ltrim($path, '/'))[0];
        if (in_array($root, VfsOperationSpecRegistry::TEXT_EDIT_ROOTS, true)) {
            return;
        }

        throw VfsError::unsupported("vfs_edit changes text files; {$path} is a record or generated path.", [
            'path' => $path,
            'hint' => 'Use vfs_patch for task/list fields and table rows (e.g. {"status":"completed"} or {"data":{...}}).',
            'text_roots' => array_map(fn (string $root): string => '/'.$root, VfsOperationSpecRegistry::TEXT_EDIT_ROOTS),
        ]);
    }
}

<?php

namespace App\Domain\Vfs\OpenCompany\Approvals;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsTextPatch;
use App\Domain\Vfs\Events\VfsOperationContext;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;

/**
 * Builds what a human approver sees for a gated VFS operation, and pins the
 * version of the object the approver is looking at.
 *
 * Preview shapes (all carry `kind` and `path`):
 * - text patch/overwrite/append/create: `{kind: text_change, path, diff,
 *   diff_truncated, added_lines, removed_lines}` — a capped unified diff
 *   (VfsApprovalDiff) of the object as it is now versus after the change;
 * - record patches (rows, tasks, list items): the mount's own preview plus
 *   `changes: {field: {before, after}}`;
 * - deletes: `{kind: delete, path, name, backend_type, backend_id}` naming
 *   the object that will move to trash;
 * - shell commands: `{kind: vfs_command, command}`.
 *
 * Side effects: reads the target through the requesting agent's own VFS read
 * authorization (the approver must never see more than the agent could), and
 * only inside VfsOperationContext::unaudited — the preview is part of the
 * approval request, not a separate read. A preview that cannot be computed
 * (object unreadable, patch no longer applies) degrades to the mount's preview
 * rather than failing the request: the policy decision, not the preview, is
 * authoritative, and replay re-validates against current state anyway.
 *
 * The facade is resolved lazily: OpenCompanyVfs -> VfsMutationPolicy ->
 * VfsApprovalRequestFactory -> this class would otherwise be a constructor
 * cycle.
 */
class VfsApprovalPreviewBuilder
{
    public function __construct(private readonly VfsOperationContext $context) {}

    /**
     * @param  array<string, mixed>  $parameters  The gated call's tool parameters
     * @param  array<string, mixed>|null  $mountPreview  Preview supplied by the mount (may be null)
     * @return array{preview: array<string, mixed>|null, base_version: string|null}
     */
    public function build(User $agent, string $directToolSlug, string $operation, string $subject, array $parameters, ?array $mountPreview): array
    {
        try {
            return $this->context->unaudited(fn (): array => $this->compute($agent, $directToolSlug, $operation, $subject, $parameters, $mountPreview));
        } catch (\Throwable) {
            return ['preview' => $mountPreview, 'base_version' => null];
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>|null  $mountPreview
     * @return array{preview: array<string, mixed>|null, base_version: string|null}
     */
    private function compute(User $agent, string $directToolSlug, string $operation, string $subject, array $parameters, ?array $mountPreview): array
    {
        if ($operation === 'exec_write' || $directToolSlug === 'vfs_exec') {
            return ['preview' => $mountPreview ?? ['kind' => 'vfs_command', 'command' => $subject], 'base_version' => null];
        }

        if (! str_starts_with($subject, '/')) {
            return ['preview' => $mountPreview, 'base_version' => null];
        }

        if (in_array($operation, ['remove', 'delete', 'trash'], true)) {
            return $this->deletePreview($agent, $subject, $mountPreview);
        }

        $current = $this->currentContent($agent, $subject);
        $patch = is_string($parameters['patch'] ?? null) ? $parameters['patch'] : null;

        if ($operation === 'patch' && $patch !== null && $this->isRecordPatch($mountPreview)) {
            return [
                'preview' => $this->recordPreview($mountPreview ?? [], $current['content'], $patch),
                'base_version' => $current['version'],
            ];
        }

        $after = match (true) {
            $operation === 'patch' && $patch !== null && $current['content'] !== null => VfsTextPatch::parse($patch)->apply($current['content'], $subject)['content'],
            $operation === 'write' && is_string($parameters['content'] ?? null) => ($parameters['mode'] ?? null) === 'append'
                ? ($current['content'] ?? '').$parameters['content']
                : $parameters['content'],
            default => null,
        };

        if ($after === null) {
            return ['preview' => $mountPreview, 'base_version' => $current['version']];
        }

        $diff = VfsApprovalDiff::unified($subject, $current['content'] ?? '', $after);

        return [
            'preview' => [
                ...($mountPreview ?? []),
                'kind' => $current['content'] === null ? 'text_create' : 'text_change',
                'path' => $subject,
                'diff' => $diff['diff'],
                'diff_truncated' => $diff['truncated'],
                'added_lines' => $diff['added'],
                'removed_lines' => $diff['removed'],
            ],
            'base_version' => $current['version'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $mountPreview
     * @return array{preview: array<string, mixed>, base_version: string|null}
     */
    private function deletePreview(User $agent, string $subject, ?array $mountPreview): array
    {
        $stat = $this->vfs()->stat($agent, $subject);

        return [
            'preview' => [
                ...($mountPreview ?? []),
                'kind' => 'delete',
                'path' => $subject,
                'name' => $stat['name'] ?? basename($subject),
                'backend_type' => $stat['backend_type'] ?? null,
                'backend_id' => $stat['backend_id'] ?? null,
                'note' => 'Moves to trash (restorable); this is not a permanent purge.',
            ],
            'base_version' => is_string($stat['version'] ?? null) ? $stat['version'] : null,
        ];
    }

    /**
     * @return array{content: string|null, version: string|null}
     */
    private function currentContent(User $agent, string $subject): array
    {
        try {
            return $this->vfs()->readContentWithVersion($agent, $subject);
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                return ['content' => null, 'version' => null];
            }

            throw $e;
        }
    }

    /** @param array<string, mixed>|null $mountPreview */
    private function isRecordPatch(?array $mountPreview): bool
    {
        return in_array($mountPreview['kind'] ?? null, ['table_row', 'task', 'list_item'], true);
    }

    /**
     * Field-level before -> after for structured record patches. Record JSON
     * is keyed by field (rows by column name), the patch by `data` (rows) or
     * `fields`/top-level keys (tasks, list items).
     *
     * @param  array<string, mixed>  $mountPreview
     * @return array<string, mixed>
     */
    private function recordPreview(array $mountPreview, ?string $currentJson, string $patch): array
    {
        $decoded = json_decode($patch, true);
        $current = is_string($currentJson) ? json_decode($currentJson, true) : null;
        if (! is_array($decoded)) {
            return $mountPreview;
        }

        $changes = match ($mountPreview['kind'] ?? null) {
            'table_row' => is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
            default => is_array($decoded['fields'] ?? null) ? $decoded['fields'] : $decoded,
        };
        $currentFields = is_array($current) ? (is_array($current['data'] ?? null) ? $current['data'] : $current) : [];

        $rendered = [];
        foreach ($changes as $field => $value) {
            $before = $currentFields[$field] ?? $this->caseInsensitive($currentFields, (string) $field);
            $rendered[(string) $field] = [
                'before' => VfsApprovalDiff::value($before),
                'after' => VfsApprovalDiff::value($value),
            ];
        }

        return [...$mountPreview, 'changes' => $rendered];
    }

    /** @param array<array-key, mixed> $fields */
    private function caseInsensitive(array $fields, string $field): mixed
    {
        foreach ($fields as $key => $value) {
            if (strcasecmp((string) $key, $field) === 0) {
                return $value;
            }
        }

        return null;
    }

    private function vfs(): OpenCompanyVfs
    {
        return app(OpenCompanyVfs::class);
    }
}

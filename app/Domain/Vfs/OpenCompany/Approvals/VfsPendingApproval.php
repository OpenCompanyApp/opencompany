<?php

namespace App\Domain\Vfs\OpenCompany\Approvals;

use App\Domain\Vfs\Core\VfsError;
use App\Models\ApprovalRequest;

/**
 * The single agent-facing shape for "this VFS operation is waiting for a
 * human".
 *
 * Contract (stable, documented in docs/tools/vfs.md by Stream B):
 *
 *   {"ok": true, "status": "pending_approval", "approval_id": "...",
 *    "operation": "patch", "path": "/docs/by-id/...", "required_tools": [...],
 *    "action_class": "reversible_edit", "preview": {...}|null,
 *    "next": "Not an error. Continue other work; ..."}
 *
 * `ok: true` is deliberate: the request was accepted and recorded, nothing
 * failed, and an `ok:false` shape invited models to retry (creating
 * duplicates before dedupe existed). It is NOT a success of the mutation:
 * `status` is the discriminator, and Code Mode's IntegrationRuntime latches
 * dispatch on it (see isPending()). Keep both sides in sync.
 *
 * Produced in two places, with identical keys:
 * - VFS tools, when the facade throws approval_required because a backing
 *   permission is gated (HandlesVfsJson / tool encoders call fromError());
 * - ApprovalWrappedTool, when the vfs_* primitive itself is gated
 *   (fromApproval()).
 *
 * `path` is null for shell commands; `command` carries the command instead.
 */
final class VfsPendingApproval
{
    public const STATUS = 'pending_approval';

    public const NEXT = 'Not an error. Continue other work; this change applies automatically when approved and the outcome is posted back to this conversation. Do not retry.';

    public const NEXT_WAITING = 'Not an error. You are configured to wait for approvals: tell the user what you are waiting for and stop; the outcome is posted back to this conversation when it is decided. Do not retry.';

    /**
     * Envelope for an approval_required VfsError raised by the VFS policy.
     *
     * @return array<string, mixed>|null null when $error is not approval_required
     */
    public static function fromError(VfsError $error): ?array
    {
        if ($error->errorCode !== 'approval_required') {
            return null;
        }

        $context = $error->context;

        return self::envelope(
            approvalId: (string) ($context['approval_id'] ?? ''),
            operation: is_string($context['operation'] ?? null) ? $context['operation'] : null,
            path: is_string($context['path'] ?? null) ? $context['path'] : null,
            requiredTools: is_array($context['required_tools'] ?? null) ? array_values($context['required_tools']) : [],
            approvalTools: is_array($context['tools'] ?? null) ? array_values($context['tools']) : [],
            actionClass: is_string($context['action_class'] ?? null) ? $context['action_class'] : null,
            preview: is_array($context['preview'] ?? null) ? $context['preview'] : null,
            waiting: (bool) ($context['wait_for_approval'] ?? false),
        );
    }

    /**
     * Envelope for an approval recorded by ApprovalWrappedTool for a gated
     * vfs_* primitive.
     *
     * @return array<string, mixed>
     */
    public static function fromApproval(ApprovalRequest $approval, VfsDirectToolOperation $operation, bool $waiting): array
    {
        $meta = $approval->tool_execution_context['vfs_meta'] ?? [];

        return self::envelope(
            approvalId: (string) $approval->id,
            operation: $operation->operation,
            path: $operation->subjectIsPath() ? $operation->subject : null,
            requiredTools: $operation->requiredTools,
            approvalTools: [$operation->toolSlug],
            actionClass: $operation->actionClass,
            preview: is_array($meta['preview'] ?? null) ? $meta['preview'] : null,
            waiting: $waiting,
            command: $operation->toolSlug === 'vfs_exec' ? $operation->subject : null,
        );
    }

    /**
     * Approval id when $envelope is a pending_approval envelope, else null.
     * This is the Code Mode latch's single test; do not duplicate it.
     */
    public static function isPending(mixed $envelope): ?string
    {
        if (! is_array($envelope)
            || ($envelope['status'] ?? null) !== self::STATUS
            || ! is_string($envelope['approval_id'] ?? null)
            || $envelope['approval_id'] === '') {
            return null;
        }

        return $envelope['approval_id'];
    }

    /**
     * @param  list<mixed>  $requiredTools
     * @param  list<mixed>  $approvalTools
     * @param  array<string, mixed>|null  $preview
     * @return array<string, mixed>
     */
    private static function envelope(
        string $approvalId,
        ?string $operation,
        ?string $path,
        array $requiredTools,
        array $approvalTools,
        ?string $actionClass,
        ?array $preview,
        bool $waiting,
        ?string $command = null,
    ): array {
        return [
            'ok' => true,
            'status' => self::STATUS,
            'approval_id' => $approvalId,
            'operation' => $operation,
            'path' => $path,
            ...($command !== null ? ['command' => $command] : []),
            'required_tools' => $requiredTools,
            'approval_tools' => $approvalTools,
            'action_class' => $actionClass,
            'preview' => $preview,
            'approval_path' => "/approvals/{$approvalId}.json",
            'next' => $waiting ? self::NEXT_WAITING : self::NEXT,
        ];
    }
}

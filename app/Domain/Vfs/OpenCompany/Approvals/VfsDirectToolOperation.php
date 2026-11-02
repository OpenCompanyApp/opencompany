<?php

namespace App\Domain\Vfs\OpenCompany\Approvals;

use App\Domain\Vfs\Core\VfsPath;
use App\Domain\Vfs\OpenCompany\Policy\VfsActionClass;

/**
 * Describe what one direct VFS primitive call (vfs_patch, vfs_write,
 * vfs_exec, and the Code Mode helpers) will do, from its tool parameters
 * alone.
 *
 * Used where only the model-visible call is known and the VFS mutation path
 * has not run yet: ApprovalWrappedTool (the primitive itself is gated) and
 * ApprovalExecutionService replay. Both must agree with the operation,
 * subject and required tools the VFS mutation paths pass to
 * VfsMutationPolicy, otherwise an approved replay would ask again.
 *
 * Invariant: subjects use VfsPath::normalize / VfsPath::commandSubject, the
 * same normalization the policy uses. Keep requiredTools in sync with
 * ExecutesVfsMutationCommands, MutatesVfsFiles, PatchesVfsRecords and
 * EvaluatesVfsMutationPolicy when a mount composes a new backing tool.
 */
final class VfsDirectToolOperation
{
    /** Direct primitives whose replay runs inside VfsApprovedExecutionContext. */
    public const REPLAYABLE_TOOLS = ['vfs_exec', 'vfs_patch', 'vfs_write'];

    /**
     * @param  list<string>  $requiredTools
     */
    private function __construct(
        public readonly string $toolSlug,
        public readonly string $operation,
        public readonly string $subject,
        public readonly array $requiredTools,
        public readonly string $actionClass,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters  Tool parameters exactly as the tool receives them
     */
    public static function fromToolCall(string $toolSlug, array $parameters): ?self
    {
        if (! str_starts_with($toolSlug, 'vfs_')) {
            return null;
        }

        $operation = match ($toolSlug) {
            'vfs_exec' => 'exec_write',
            'vfs_write' => 'write',
            'vfs_patch', 'vfs_edit' => 'patch',
            'vfs_rm' => 'remove',
            'vfs_mv' => 'move',
            'vfs_cp' => 'copy',
            'vfs_mkdir' => 'mkdir',
            default => 'read',
        };
        $subject = $toolSlug === 'vfs_exec'
            ? VfsPath::commandSubject((string) ($parameters['command'] ?? ''))
            : VfsPath::normalize((string) ($parameters['path'] ?? $parameters['source'] ?? ''));
        $requiredTools = self::requiredTools($toolSlug, $subject);

        return new self(
            $toolSlug,
            $operation,
            $subject,
            $requiredTools,
            VfsActionClass::classify($operation, $subject, $requiredTools),
        );
    }

    /**
     * Mirror of the required tool sets the VFS mutation paths pass to policy.
     * $subject is already normalized.
     *
     * @return list<string>
     */
    public static function requiredTools(string $toolSlug, string $subject): array
    {
        if ($toolSlug === 'vfs_exec') {
            return ['vfs_exec', 'vfs_write'];
        }

        if ($toolSlug !== 'vfs_patch' && $toolSlug !== 'vfs_edit') {
            return [$toolSlug];
        }

        if (str_starts_with($subject, '/tables/') && str_contains($subject, '/rows/')) {
            return ['vfs_patch', 'update_table_row'];
        }
        if (preg_match('#^/(?:agents/(?:by-id/)?[^/]+|me)/identity/#', $subject) === 1) {
            return ['vfs_patch', 'update_agent_identity_file'];
        }

        return ['vfs_patch'];
    }

    /** Whether the subject is a VFS path (exec subjects are command lines). */
    public function subjectIsPath(): bool
    {
        return $this->toolSlug !== 'vfs_exec' && str_starts_with($this->subject, '/');
    }
}

<?php

namespace App\Domain\Vfs\OpenCompany\Policy;

/**
 * Immutable result of composing primitive VFS permissions with backing domain
 * tool permissions.
 *
 * VFS mounts use this object for metadata, while mutation paths use the same
 * decision for enforcement. Keeping those two reads of policy together prevents
 * entries from advertising a capability that execution later rejects for a
 * different reason.
 */
final class VfsMutationDecision
{
    /**
     * @param  list<string>  $requiredTools
     * @param  list<string>  $approvalTools  Every required tool that is approval-gated and not yet approved, in requiredTools order. approvalTool is its first element (kept for metadata compatibility).
     */
    public function __construct(
        public readonly string $status,
        public readonly string $operation,
        public readonly string $subject,
        public readonly array $requiredTools,
        public readonly ?string $deniedTool = null,
        public readonly ?string $approvalTool = null,
        public readonly string $hint = '',
        public readonly ?string $deniedReason = null,
        public readonly array $approvalTools = [],
    ) {}

    public function allowedForCapability(): bool
    {
        return in_array($this->status, ['allowed', 'requires_approval'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return [
            'mutation_tool' => implode('+', $this->requiredTools),
            'mutation_required_tools' => $this->requiredTools,
            'mutation_hint' => $this->hint,
            'mutation_permission' => $this->status,
            'mutation_requires_approval' => $this->status === 'requires_approval',
            ...($this->deniedTool ? ['mutation_denied_tool' => $this->deniedTool] : []),
            ...($this->approvalTool ? ['mutation_approval_tool' => $this->approvalTool] : []),
            ...($this->approvalTools !== [] ? ['mutation_approval_tools' => $this->approvalTools] : []),
            ...($this->deniedReason ? ['mutation_denied_reason' => $this->deniedReason] : []),
        ];
    }

    /**
     * Return an action-local representation for entries with more than one
     * mutation surface. In particular, workspace files may be patchable through
     * `vfs_patch` while raw overwrite/create behavior is controlled separately
     * by `vfs_write`; one flat permission field cannot describe both truthfully.
     *
     * @return array<string, mixed>
     */
    public function actionMetadata(): array
    {
        return [
            'tool' => implode('+', $this->requiredTools),
            'required_tools' => $this->requiredTools,
            'hint' => $this->hint,
            'permission' => $this->status,
            'requires_approval' => $this->status === 'requires_approval',
            ...($this->deniedTool ? ['denied_tool' => $this->deniedTool] : []),
            ...($this->approvalTool ? ['approval_tool' => $this->approvalTool] : []),
            ...($this->approvalTools !== [] ? ['approval_tools' => $this->approvalTools] : []),
            ...($this->deniedReason ? ['denied_reason' => $this->deniedReason] : []),
        ];
    }
}

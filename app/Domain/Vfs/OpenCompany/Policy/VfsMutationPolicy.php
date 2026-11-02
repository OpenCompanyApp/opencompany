<?php

namespace App\Domain\Vfs\OpenCompany\Policy;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\Approvals\VfsApprovalRequestFactory;
use App\Domain\Vfs\OpenCompany\Approvals\VfsApprovedExecutionContext;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\AgentPermissionService;

/**
 * Composes VFS primitive permissions with existing domain tool permissions.
 *
 * This is not a new permission store. It evaluates the same AgentPermission rows
 * that the capabilities UI edits, then turns the combined result into metadata,
 * denials, or normal approval requests.
 */
class VfsMutationPolicy
{
    public function __construct(
        private readonly AgentPermissionService $permissions,
        private readonly ToolRegistry $tools,
        private readonly VfsApprovedExecutionContext $approvedContext,
        private readonly VfsApprovalRequestFactory $approvals,
    ) {}

    /**
     * Compose every required tool's permission into one decision.
     *
     * Invariant (evaluation order): any denied tool denies the whole
     * operation; otherwise every approval-gated tool that the current
     * approved replay context does not cover is collected into approvalTools.
     * Collecting all of them (not just the first) is what lets one human
     * approval cover the whole operation instead of ping-ponging one approval
     * per gated permission.
     *
     * @param  list<string>  $requiredTools
     * @param  array<string, mixed>  $parameters
     */
    public function decide(
        User $agent,
        string $directToolSlug,
        string $operation,
        string $subject,
        array $requiredTools,
        array $parameters = [],
        string $hint = '',
    ): VfsMutationDecision {
        $approvalTools = [];
        $parametersHash = $this->approvals->hashParameters($parameters);

        foreach ($requiredTools as $toolSlug) {
            $state = $this->toolState($agent, $toolSlug);
            if (! $state['allowed']) {
                return new VfsMutationDecision(
                    status: 'denied',
                    operation: $operation,
                    subject: $subject,
                    requiredTools: $requiredTools,
                    deniedTool: $toolSlug,
                    hint: $hint,
                );
            }

            if (
                $state['requires_approval']
                && ! $this->approvedContext->approves($agent, $directToolSlug, $operation, $subject, $toolSlug, $requiredTools, $parametersHash)
            ) {
                $approvalTools[] = $toolSlug;
            }
        }

        return new VfsMutationDecision(
            status: $approvalTools !== [] ? 'requires_approval' : 'allowed',
            operation: $operation,
            subject: $subject,
            requiredTools: $requiredTools,
            approvalTool: $approvalTools[0] ?? null,
            hint: $hint,
            approvalTools: $approvalTools,
        );
    }

    /**
     * Enforce a decision: return when allowed, throw permission_denied when
     * denied, or create (or reuse) ONE approval covering every gated tool and
     * throw approval_required.
     *
     * approval_required context: {approval_id, path, tool (first gated tool),
     * tools (every gated tool the approval covers), required_tools,
     * wait_for_approval}.
     *
     * @param  list<string>  $requiredTools
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>|null  $preview
     */
    public function assertAllowedOrRequestApproval(
        User $agent,
        string $directToolSlug,
        string $operation,
        string $subject,
        array $requiredTools,
        array $parameters = [],
        ?string $channelId = null,
        string $hint = '',
        ?array $preview = null,
    ): ?ApprovalRequest {
        $decision = $this->decide($agent, $directToolSlug, $operation, $subject, $requiredTools, $parameters, $hint);

        if ($decision->status === 'denied') {
            throw VfsError::permissionDenied($subject, "requires {$decision->deniedTool} permission");
        }

        if ($decision->status !== 'requires_approval' || $decision->approvalTool === null) {
            return null;
        }

        $approval = $this->approvals->create(
            agent: $agent,
            directToolSlug: $directToolSlug,
            operation: $operation,
            subject: $subject,
            requiredTools: $requiredTools,
            approvalTool: $decision->approvalTool,
            parameters: $parameters,
            channelId: $channelId,
            preview: $preview,
            approvalTools: $decision->approvalTools,
        );

        throw VfsError::approvalRequired("Approval required to {$operation} {$subject}", [
            'approval_id' => $approval->id,
            'path' => str_starts_with($subject, '/') ? $subject : null,
            'tool' => $decision->approvalTool,
            'tools' => $decision->approvalTools,
            'required_tools' => $requiredTools,
            'wait_for_approval' => $agent->must_wait_for_approval,
        ]);
    }

    /**
     * @return array{allowed: bool, requires_approval: bool}
     */
    private function toolState(User $agent, string $toolSlug): array
    {
        $meta = $this->tools->getToolDefinitionBySlug($toolSlug);

        return $this->permissions->resolveToolPermission(
            $agent,
            $toolSlug,
            (string) ($meta['type'] ?? 'write'),
        );
    }
}

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
        $approvalTool = null;
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
                && $approvalTool === null
            ) {
                $approvalTool = $toolSlug;
            }
        }

        return new VfsMutationDecision(
            status: $approvalTool ? 'requires_approval' : 'allowed',
            operation: $operation,
            subject: $subject,
            requiredTools: $requiredTools,
            approvalTool: $approvalTool,
            hint: $hint,
        );
    }

    /**
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
        );

        throw VfsError::approvalRequired("Approval required to {$operation} {$subject}", [
            'approval_id' => $approval->id,
            'path' => str_starts_with($subject, '/') ? $subject : null,
            'tool' => $decision->approvalTool,
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

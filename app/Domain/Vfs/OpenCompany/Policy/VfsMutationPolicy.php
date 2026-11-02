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
     * $actionClass is the governance reversibility class (VfsActionClass);
     * null derives it from operation/subject/tools. It never changes the
     * outcome today; it is carried into the decision, the approval context
     * and audit so the future policy engine can gate by reversibility.
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
        ?string $actionClass = null,
    ): VfsMutationDecision {
        $actionClass ??= VfsActionClass::classify($operation, $subject, $requiredTools);
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
                    actionClass: $actionClass,
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
            actionClass: $actionClass,
        );
    }

    /**
     * Enforce a decision: return when allowed, throw permission_denied when
     * denied, or create (or reuse) ONE approval covering every gated tool and
     * throw approval_required.
     *
     * approval_required context: {approval_id, operation, path, tool (first
     * gated tool), tools (every gated tool the approval covers),
     * required_tools, action_class, preview, wait_for_approval}. Agent-facing
     * tools render this as the non-error pending_approval envelope
     * (VfsPendingApproval); the facade keeps throwing so no caller can mistake
     * "waiting for a human" for "written".
     *
     * permission_denied context: {path, reason, denied_tool, required_tools,
     * action_class, hint}; the message names the exact tool grant that would
     * enable the operation.
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
        ?string $actionClass = null,
    ): ?ApprovalRequest {
        $decision = $this->decide($agent, $directToolSlug, $operation, $subject, $requiredTools, $parameters, $hint, $actionClass);

        if ($decision->status === 'denied') {
            $denial = VfsError::permissionDenied($subject, "requires {$decision->deniedTool} permission");

            throw new VfsError($denial->errorCode, $denial->getMessage(), $denial->context + [
                'denied_tool' => $decision->deniedTool,
                'required_tools' => $requiredTools,
                'action_class' => $decision->actionClass,
                'hint' => "Ask a workspace admin to allow the {$decision->deniedTool} tool for this agent (Capabilities), then retry.",
            ]);
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
            actionClass: $decision->actionClass,
        );
        $stored = $approval->tool_execution_context['vfs_approval'] ?? [];

        throw VfsError::approvalRequired("Approval required to {$operation} {$subject}", [
            'approval_id' => $approval->id,
            'operation' => $operation,
            'path' => str_starts_with($subject, '/') ? $subject : null,
            'tool' => $decision->approvalTool,
            'tools' => $decision->approvalTools,
            'required_tools' => $requiredTools,
            'action_class' => $decision->actionClass,
            'preview' => is_array($stored) ? ($stored['preview'] ?? null) : null,
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

<?php

namespace App\Domain\Authorization\Application;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\WorkspaceMember;

/**
 * Single source of truth for "may this user approve or reject this approval?".
 *
 * Owns: the responder rules for human-in-the-loop approvals, across every
 * transport (web API, Telegram callback buttons, Telegram Mini App, ChatBridge
 * adapters). ApprovalExecutionService::resolve() calls authorize() before its
 * atomic pending->status claim, so no transport can bypass these rules;
 * transports may call decide()/canResolve() earlier only to render UI or give
 * a friendlier message.
 *
 * Does not own: which approvals a user may *see* (workspace scoping via
 * ApprovalRequest::forWorkspace()), or whether an approved action may still
 * execute (ApprovalExecutionService re-checks tool permissions and workspace).
 *
 * Rules, evaluated in this order (first denial wins; do not reorder without a
 * security review, the order also determines the user-facing reason):
 *  1. There must be an attributable responder. Unattributed (null) decisions
 *     are refused so an external event without a user cannot resolve anything.
 *  2. Agents may never resolve approvals, not even their own or another
 *     agent's. Approvals exist to put a human in the loop.
 *  3. The approval must have a known workspace (fail closed for legacy rows
 *     that could not be backfilled).
 *  4. Nobody may resolve an approval they requested themselves.
 *  5. The responder must be a member of the approval's workspace with role
 *     `admin` or `approver`. Plain `member`s can view but not resolve.
 */
class ApprovalResolutionPolicy
{
    public const DENY_UNATTRIBUTED = 'approval_responder_required';

    public const DENY_AGENT = 'approval_agent_cannot_resolve';

    public const DENY_NO_WORKSPACE = 'approval_workspace_unknown';

    public const DENY_SELF = 'approval_self_resolution_forbidden';

    public const DENY_ROLE = 'approval_role_required';

    /**
     * Per-instance memo of workspace roles keyed by "workspace:user" so list
     * endpoints computing `can_resolve` for many rows do one query per pair.
     *
     * @var array<string, string|null>
     */
    private array $roles = [];

    /**
     * Evaluate the rules without side effects.
     *
     * @return array{allowed: bool, code: string|null, message: string|null}
     */
    public function decide(ApprovalRequest $approval, ?User $responder): array
    {
        if ($responder === null) {
            return $this->deny(self::DENY_UNATTRIBUTED, 'Approvals must be resolved by an identified workspace user.');
        }

        if ($responder->type === 'agent') {
            return $this->deny(self::DENY_AGENT, 'Agents cannot approve or reject approval requests.');
        }

        $workspaceId = $this->workspaceIdFor($approval);
        if ($workspaceId === null) {
            return $this->deny(self::DENY_NO_WORKSPACE, 'This approval is not linked to a workspace and cannot be resolved.');
        }

        if ($approval->requester_id !== null && (string) $approval->requester_id === (string) $responder->id) {
            return $this->deny(self::DENY_SELF, 'You cannot resolve an approval you requested yourself.');
        }

        if (! WorkspaceMember::roleCanResolveApprovals($this->roleFor($workspaceId, (string) $responder->id))) {
            return $this->deny(self::DENY_ROLE, 'Only workspace admins and approvers can resolve approval requests.');
        }

        return ['allowed' => true, 'code' => null, 'message' => null];
    }

    public function canResolve(ApprovalRequest $approval, ?User $responder): bool
    {
        return $this->decide($approval, $responder)['allowed'];
    }

    /**
     * Throw when the responder may not resolve the approval.
     *
     * @throws ApprovalResolutionDenied (an AuthorizationException, so HTTP
     *                                  callers that do not catch it get a 403)
     */
    public function authorize(ApprovalRequest $approval, ?User $responder): void
    {
        $decision = $this->decide($approval, $responder);

        if (! $decision['allowed']) {
            throw new ApprovalResolutionDenied((string) $decision['message'], (string) $decision['code']);
        }
    }

    /**
     * The approval's tenant. Falls back to the channel/agent relation only for
     * rows created before the workspace_id column existed and not backfilled.
     */
    private function workspaceIdFor(ApprovalRequest $approval): ?string
    {
        if ($approval->workspace_id) {
            return (string) $approval->workspace_id;
        }

        $fromChannel = $approval->channel?->workspace_id;
        if ($fromChannel) {
            return (string) $fromChannel;
        }

        $requester = $approval->requester;

        return $requester?->type === 'agent' && $requester->workspace_id ? (string) $requester->workspace_id : null;
    }

    private function roleFor(string $workspaceId, string $userId): ?string
    {
        $key = $workspaceId.':'.$userId;

        if (! array_key_exists($key, $this->roles)) {
            $this->roles[$key] = WorkspaceMember::query()
                ->where('workspace_id', $workspaceId)
                ->where('user_id', $userId)
                ->value('role');
        }

        return $this->roles[$key];
    }

    /**
     * @return array{allowed: false, code: string, message: string}
     */
    private function deny(string $code, string $message): array
    {
        return ['allowed' => false, 'code' => $code, 'message' => $message];
    }
}

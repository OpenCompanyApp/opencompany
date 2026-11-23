<?php

namespace App\Domain\Authorization\Application;

use App\Domain\Authorization\Engine\Decision;
use App\Domain\Authorization\Engine\PolicyEngine;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\Workspace;
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
 * Since GIN-1197 the rules are evaluated by the central PolicyEngine
 * (`approval.resolve`, see tests/conformance/authorization/approvals.json);
 * this class only builds the facts, memoizes roles for list endpoints and
 * records resolution attempts in the authorization ledger (authorize()).
 * decide()/canResolve() never record, so rendering `can_resolve` stays quiet.
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
        $decision = $this->engineDecision($approval, $responder);

        return $decision->allowed()
            ? ['allowed' => true, 'code' => null, 'message' => null]
            : $this->deny($decision->reasonCode, $decision->reason);
    }

    /**
     * The engine decision. Unattributed responders are refused before the
     * engine (there is no actor to decide for).
     */
    private function engineDecision(ApprovalRequest $approval, ?User $responder): Decision
    {
        if ($responder === null) {
            return new Decision(Decision::DENY, self::DENY_UNATTRIBUTED, 'Approvals must be resolved by an identified workspace user.', [], PolicyEngine::VERSION);
        }

        $facts = app(AuthorizationFacts::class);
        $resource = $facts->approval($approval);
        // A legacy approval with no workspace still needs a request workspace;
        // the engine then answers approval_workspace_unknown from the facts.
        // Never query with the placeholder: workspace ids are uuid columns on
        // PostgreSQL, and a failed cast would abort the caller's transaction.
        $knownWorkspaceId = $resource['workspace_id'] ?? null;
        $workspaceId = $knownWorkspaceId ?? 'approval-without-workspace';
        $workspace = $knownWorkspaceId !== null ? Workspace::query()->find($knownWorkspaceId) : null;
        $responderId = (string) $responder->id;
        $role = $responder->type === 'agent' || $knownWorkspaceId === null ? null : $this->roleFor($knownWorkspaceId, $responderId);

        return app(PolicyEngine::class)->decide(
            ['workspace' => $workspaceId, 'actor' => $responderId, 'on_behalf_of' => null, 'action' => 'approval.resolve'],
            [
                'now' => now()->utc()->toIso8601String(),
                'workspace' => ['id' => $workspaceId, 'owner_id' => $workspace?->owner_id, 'preset' => 'team'],
                'actors' => [$responderId => $responder->type === 'agent'
                    ? ['kind' => 'agent', 'workspace_id' => $responder->workspace_id]
                    : ['kind' => 'human', 'workspace_role' => $role, 'platform_operator' => false]],
                'resource' => $resource,
                'context' => [],
            ],
        );
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
        $decision = $this->engineDecision($approval, $responder);

        // Ledger: every attributable resolution attempt in a known workspace.
        $workspaceId = app(AuthorizationFacts::class)->approval($approval)['workspace_id'] ?? null;
        if ($responder !== null && $workspaceId !== null && Workspace::query()->whereKey($workspaceId)->exists()) {
            app(AuthorizationLedger::class)->recordDecision(
                (string) $workspaceId,
                'approval.resolve',
                $decision,
                ['type' => $responder->type === 'agent' ? 'agent' : 'human', 'id' => (string) $responder->id],
                null,
                ['type' => 'approval', 'id' => (string) $approval->id],
            );
        }

        if (! $decision->allowed()) {
            throw new ApprovalResolutionDenied($decision->reason, $decision->reasonCode);
        }
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

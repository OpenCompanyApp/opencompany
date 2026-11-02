<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\Approvals\VfsSecretRedactor;
use App\Models\ApprovalRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Approval-request mount: the acting agent's own approvals.
 *
 * `/approvals/{id}.json` shows the stored request including the VFS block
 * (operation, subject, required tools, action class, preview/diff, pinned
 * base version, origin) and the replay disposition (`execution`), so an
 * agent can tell its pending changes apart and learn what happened to them.
 * Secrets are redacted precisely (VfsSecretRedactor); UUIDs, paths and
 * hashes stay readable.
 */
trait MountsVfsApprovals
{
    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listApprovals(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        return $this->pageApprovals($agent, $segments, $path, $budget, [])['entries'];
    }

    /**
     * The acting agent's own approvals in the agent's (bound) workspace.
     *
     * Invariant: scope by the approval's persisted workspace_id, never by the
     * requester's current workspace. An agent reassigned from W1 to W2 used
     * to satisfy a whereHas(requester) check with its new workspace and could
     * read W1's descriptions, parameters and previews. The facade already
     * asserts that the bound workspace equals $agent->workspace_id
     * (OpenCompanyVfs::assertBoundWorkspace), and replay refuses the same
     * reassignment (ApprovalExecutionService). Rows without a workspace_id
     * fail closed. Every approval read (listing, file, stat, /me/pending)
     * must start from this query.
     *
     * @return Builder<ApprovalRequest>
     */
    private function agentApprovalsQuery(User $agent): Builder
    {
        return ApprovalRequest::query()
            ->where('requester_id', $agent->id)
            ->where('workspace_id', $agent->workspace_id);
    }

    /**
     * Page only approvals requested by the acting agent. Workspace scoping
     * (agentApprovalsQuery) runs inside every continuation query rather than
     * trusting the actor binding stored in the cursor.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{entries: list<VfsEntry>, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageApprovals(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        if (count($segments) > 1) {
            throw VfsError::notReadable($path);
        }

        return $this->paginateVfsTimestampQuery(
            $this->agentApprovalsQuery($agent),
            'created_at',
            'desc',
            $budget->maxEntries,
            $budget->maxFiles,
            $state,
            fn (ApprovalRequest $approval): VfsEntry => new VfsEntry($approval->id.'.json', "/approvals/{$approval->id}.json", 'file', backendType: 'approval', backendId: $approval->id, capabilities: ['read'], metadata: [
                'status' => $approval->status,
                'title' => $this->redactSecrets($approval->title),
                ...$this->readOnlyMetadata(
                    'Approval JSON is a generated approval record.',
                    'approval tools',
                    'Use approval tools to approve, reject, or inspect approval state.',
                ),
            ]),
        );
    }

    /**
     * @param  list<string>  $segments
     */
    private function readApprovals(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listApprovals($agent, $segments, $path, $budget));
        }
        if (count($segments) !== 2) {
            throw VfsError::notFound($path);
        }

        $id = Str::before($segments[1], '.json');
        $approval = $this->agentApprovalsQuery($agent)
            ->where('id', $id)
            ->first() ?? throw VfsError::notFound($path);

        return json_encode($this->approvalPayload($approval), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Approval context can contain full tool arguments. Keep the audit shape
     * useful while aggressively redacting secret-like keys and values.
     *
     * @return array<string, mixed>
     */
    private function approvalPayload(ApprovalRequest $approval): array
    {
        $respondedAt = $approval->getAttribute('responded_at');

        return [
            'id' => $approval->id,
            'type' => $approval->type,
            'title' => $this->redactSecrets($approval->title),
            'description' => $this->redactSecrets($approval->description),
            'requester_id' => $approval->requester_id,
            'amount' => $approval->amount,
            'status' => $approval->status,
            'responded_by_id' => $approval->responded_by_id,
            'responded_at' => $respondedAt instanceof CarbonInterface ? $respondedAt->toIso8601String() : null,
            'channel_id' => $approval->channel_id,
            'tool_execution_context' => $this->redactSecrets($approval->tool_execution_context ?? []),
            'created_at' => $approval->created_at?->toIso8601String(),
            'updated_at' => $approval->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Precise secret redaction (VfsSecretRedactor): secret-like keys and known
     * secret patterns only, so ids, paths, subjects and hashes stay readable.
     */
    private function redactSecrets(mixed $value): mixed
    {
        return VfsSecretRedactor::redact($value);
    }
}

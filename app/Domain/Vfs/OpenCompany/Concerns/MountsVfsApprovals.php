<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\ApprovalRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Approval-request mount with conservative redaction.
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
     * Page only approvals requested by the acting agent. Requester workspace
     * validation remains inside every continuation query rather than trusting
     * the actor binding stored in the cursor.
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
            ApprovalRequest::query()
                ->where('requester_id', $agent->id)
                ->whereHas('requester', fn (Builder $query) => $query->where('workspace_id', $agent->workspace_id)),
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
        $approval = ApprovalRequest::query()
            ->where('id', $id)
            ->where('requester_id', $agent->id)
            ->whereHas('requester', fn (Builder $query) => $query->where('workspace_id', $agent->workspace_id))
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

    private function redactSecrets(mixed $value): mixed
    {
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $item) {
                $keyString = (string) $key;
                $redacted[$key] = preg_match('/(secret|token|auth|api[_-]?key|authorization|password|credential|bearer|cookie|session)/i', $keyString) === 1
                    ? '[redacted]'
                    : $this->redactSecrets($item);
            }

            return $redacted;
        }

        if (is_string($value)) {
            $redacted = preg_replace('/\b(authorization\s*:\s*(?:bearer|basic)\s+)[^\r\n,;]+/i', '$1[redacted]', $value) ?? $value;
            $redacted = preg_replace('/\b(secret|token|auth|api[_-]?key|authorization|password|credential|bearer|cookie|session)(\s*[:=]\s*)([^\s,&;]+)/i', '$1$2[redacted]', $redacted) ?? $redacted;
            $redacted = preg_replace('/\b(bearer|basic)\s+([A-Za-z0-9._~+\/=-]+(?:\s+[A-Za-z0-9._~+\/=-]+)?)/i', '$1 [redacted]', $redacted) ?? $redacted;
            $redacted = preg_replace('/([?&](?:access_token|client_secret|secret|token|auth|api[_-]?key|authorization|password|credential|bearer|session)=)([^&\s]+)/i', '$1[redacted]', $redacted) ?? $redacted;

            if (preg_match('/(sk-[A-Za-z0-9_-]{12,}|[A-Za-z0-9_\/+=-]{32,})/', $redacted) === 1) {
                return '[redacted]';
            }

            return $redacted;
        }

        return $value;
    }
}

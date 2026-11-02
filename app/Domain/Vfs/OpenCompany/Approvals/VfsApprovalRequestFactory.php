<?php

namespace App\Domain\Vfs\OpenCompany\Approvals;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates normal OpenCompany approval records for VFS-composed permissions.
 *
 * Direct agent-tool approval remains owned by ToolRegistry/ApprovalWrappedTool.
 * This factory handles backing permissions such as update_table_row and also
 * protects direct domain-service calls that do not pass through a tool wrapper.
 */
class VfsApprovalRequestFactory
{
    /**
     * Create one approval for a VFS operation, or return the identical one
     * that is still pending.
     *
     * Contract:
     * - `approved_tools` lists EVERY approval-gated tool the operation needs
     *   ($approvalTools), so a single human decision lets the replay pass all
     *   of them. `approval_tool` stays the first one for older readers.
     * - Dedupe: a pending request from the same requester with the same
     *   direct tool, operation, subject and parameters_hash, whose
     *   approved_tools already cover $approvalTools, is returned instead of
     *   creating a duplicate. Agents (and rescued Code Mode retries) therefore
     *   get a stable approval id for a retried call.
     *
     * The approval_requests table has no workspace_id column at this commit;
     * workspace scope is implied by the requester (see
     * ApprovalExecutionService::executeApprovedTool).
     *
     * @param  list<string>  $requiredTools
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>|null  $preview
     * @param  list<string>  $approvalTools  Every gated tool; defaults to [$approvalTool]
     */
    public function create(
        User $agent,
        string $directToolSlug,
        string $operation,
        string $subject,
        array $requiredTools,
        string $approvalTool,
        array $parameters,
        ?string $channelId = null,
        ?array $preview = null,
        array $approvalTools = [],
    ): ApprovalRequest {
        $approvalTools = array_values(array_unique($approvalTools === [] ? [$approvalTool] : $approvalTools));
        $hash = $this->hashParameters($parameters);

        $approval = $this->pendingDuplicate($agent, $directToolSlug, $operation, $subject, $hash, $approvalTools)
            ?? ApprovalRequest::create([
                'id' => Str::uuid()->toString(),
                'type' => 'action',
                'workspace_id' => $agent->workspace_id,
                'title' => $this->title($operation, $approvalTools),
                'description' => $this->description($agent, $operation, $subject, $approvalTools),
                'requester_id' => $agent->id,
                'status' => 'pending',
                'tool_execution_context' => [
                    'tool_slug' => $directToolSlug,
                    'parameters' => $parameters,
                    'vfs_approval' => [
                        'requester_id' => $agent->id,
                        'direct_tool_slug' => $directToolSlug,
                        'operation' => $operation,
                        'subject' => $subject,
                        'required_tools' => $requiredTools,
                        'approval_tool' => $approvalTools[0],
                        'approved_tools' => $approvalTools,
                        'parameters_hash' => $hash,
                        'preview' => $preview,
                    ],
                ],
                'channel_id' => $channelId,
            ]);

        if ($agent->must_wait_for_approval) {
            $agent->update(['awaiting_approval_id' => $approval->id]);
        }

        return $approval;
    }

    /**
     * @param  list<string>  $approvalTools
     */
    private function pendingDuplicate(User $agent, string $directToolSlug, string $operation, string $subject, string $hash, array $approvalTools): ?ApprovalRequest
    {
        // Filtered in PHP: tool_execution_context is a JSON column and this
        // must behave identically on sqlite and Postgres. The candidate set is
        // one requester's pending approvals, which stays small.
        return ApprovalRequest::query()
            ->where('requester_id', $agent->id)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->first(function (ApprovalRequest $approval) use ($directToolSlug, $operation, $subject, $hash, $approvalTools): bool {
                $context = $approval->tool_execution_context['vfs_approval'] ?? null;
                if (! is_array($context)) {
                    return false;
                }

                $covered = is_array($context['approved_tools'] ?? null) ? $context['approved_tools'] : [];

                return ($context['direct_tool_slug'] ?? null) === $directToolSlug
                    && ($context['operation'] ?? null) === $operation
                    && ($context['subject'] ?? null) === $subject
                    && ($context['parameters_hash'] ?? null) === $hash
                    && array_diff($approvalTools, $covered) === [];
            });
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function hashParameters(array $parameters): string
    {
        $normalized = $this->normalize($parameters);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * @param  list<string>  $approvalTools
     */
    private function title(string $operation, array $approvalTools): string
    {
        if (count($approvalTools) > 1) {
            return 'Approve VFS '.($operation === 'exec_write' ? 'file write command' : $operation).' ('.implode(' + ', $approvalTools).')';
        }

        $approvalTool = $approvalTools[0];

        return match ($approvalTool) {
            'update_table_row' => 'Approve table row update through VFS',
            'update_agent_identity_file' => 'Approve agent identity update through VFS',
            'vfs_write' => $operation === 'exec_write'
                ? 'Approve file write command through VFS'
                : 'Approve VFS file write',
            default => "Approve {$approvalTool} through VFS",
        };
    }

    /**
     * @param  list<string>  $approvalTools
     */
    private function description(User $agent, string $operation, string $subject, array $approvalTools): string
    {
        $label = match ($operation) {
            'patch' => 'patch',
            'write' => 'write',
            'exec_write' => 'run a file-mutating VFS command',
            default => $operation,
        };
        $permissions = implode(', ', $approvalTools);
        $noun = count($approvalTools) > 1 ? 'Required permissions (all covered by this approval)' : 'Required permission';

        return "Agent {$agent->name} wants to {$label} {$subject}. {$noun}: {$permissions}.";
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalize($item);
        }

        if (! $isList) {
            ksort($normalized);
        }

        return $normalized;
    }
}

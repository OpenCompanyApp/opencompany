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
     * @param  list<string>  $requiredTools
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>|null  $preview
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
    ): ApprovalRequest {
        $hash = $this->hashParameters($parameters);
        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => 'action',
            'title' => $this->title($operation, $approvalTool),
            'description' => $this->description($agent, $operation, $subject, $approvalTool),
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
                    'approval_tool' => $approvalTool,
                    'approved_tools' => [$approvalTool],
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
     * @param  array<string, mixed>  $parameters
     */
    public function hashParameters(array $parameters): string
    {
        $normalized = $this->normalize($parameters);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    private function title(string $operation, string $approvalTool): string
    {
        return match ($approvalTool) {
            'update_table_row' => 'Approve table row update through VFS',
            'update_agent_identity_file' => 'Approve agent identity update through VFS',
            'vfs_write' => $operation === 'exec_write'
                ? 'Approve file write command through VFS'
                : 'Approve VFS file write',
            default => "Approve {$approvalTool} through VFS",
        };
    }

    private function description(User $agent, string $operation, string $subject, string $approvalTool): string
    {
        $label = match ($operation) {
            'patch' => 'patch',
            'write' => 'write',
            'exec_write' => 'run a file-mutating VFS command',
            default => $operation,
        };

        return "Agent {$agent->name} wants to {$label} {$subject}. Required permission: {$approvalTool}.";
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

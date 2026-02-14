<?php

namespace App\Agents\Tools\System;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class WaitForApproval implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Pause execution and wait for a pending approval to be decided. Use this when your next steps depend on the approval outcome. You will be automatically resumed when the approval is granted or denied.';
    }

    public function handle(Request $request): string
    {
        try {
            $approvalId = $request['approvalId'];

            $approval = ApprovalRequest::find($approvalId);
            if (!$approval) {
                return "Error: Approval request '{$approvalId}' not found.";
            }

            if ($approval->status !== 'pending') {
                return "Error: Approval '{$approvalId}' is already {$approval->status}. No need to wait.";
            }

            if ($approval->requester_id !== $this->agent->id) {
                return "Error: This approval was not requested by you.";
            }

            $this->agent->update(['awaiting_approval_id' => $approvalId]);

            return "Execution will pause after your response. You will be automatically resumed when approval '{$approval->title}' is decided. Inform the user that you are waiting for their approval.";
        } catch (\Throwable $e) {
            return "Error setting up approval wait: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'approvalId' => $schema
                ->string()
                ->description('The UUID of the approval request to wait for.')
                ->required(),
        ];
    }
}

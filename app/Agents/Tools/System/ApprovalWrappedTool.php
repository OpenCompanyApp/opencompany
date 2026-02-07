<?php

namespace App\Agents\Tools\System;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ApprovalWrappedTool implements Tool
{
    public function __construct(
        private Tool $innerTool,
        private User $agent,
        private string $toolSlug,
    ) {}

    public function description(): string
    {
        return $this->innerTool->description() .
            ' (Note: This tool requires human approval before execution.)';
    }

    public function handle(Request $request): string
    {
        try {
            $approval = ApprovalRequest::create([
                'id' => Str::uuid()->toString(),
                'type' => 'action',
                'title' => "Tool: {$this->toolSlug}",
                'description' => "Agent {$this->agent->name} wants to execute {$this->toolSlug}",
                'requester_id' => $this->agent->id,
                'status' => 'pending',
                'tool_execution_context' => [
                    'tool_slug' => $this->toolSlug,
                    'parameters' => $request->toArray(),
                ],
                'channel_id' => $request['channelId'] ?? null,
            ]);

            if ($this->agent->must_wait_for_approval) {
                $this->agent->update(['awaiting_approval_id' => $approval->id]);

                return "This action requires human approval. An approval request has been created (ID: {$approval->id}). " .
                    "You are configured to always wait for approvals. Execution will pause after your response. " .
                    "Tell the user you are waiting for their approval on: {$this->toolSlug}.";
            }

            return "This action requires human approval. An approval request has been created (ID: {$approval->id}). " .
                "You can either: (1) Call wait_for_approval with this approval ID to pause until it's decided — do this if your next steps depend on this action's result. " .
                "Or (2) Continue working on other things — the action will execute automatically if approved later.";
        } catch (\Throwable $e) {
            return "Error creating approval request: {$e->getMessage()}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->innerTool->schema($schema);
    }

    /**
     * Get the inner (unwrapped) tool instance.
     */
    public function getInnerTool(): Tool
    {
        return $this->innerTool;
    }
}

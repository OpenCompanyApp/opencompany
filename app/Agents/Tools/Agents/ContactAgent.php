<?php

namespace App\Agents\Tools\Agents;

use App\Jobs\AgentRespondJob;
use App\Models\ApprovalRequest;
use App\Models\Task;
use App\Models\User;
use App\Services\AgentCommunicationService;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ContactAgent implements Tool
{

    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private AgentCommunicationService $commService,
        private ?string $currentTaskId = null,
    ) {}

    public function description(): string
    {
        return 'Send a message, ask a question, or delegate work to another agent. Actions: "ask" (ask a question — you will receive the response as a callback), "delegate" (hand off work — you will receive the result as a callback when done), "notify" (send an FYI — the agent processes it independently, NO callback to you). Use list_agents() to discover available agents.';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];
            $agentId = $request['agentId'];
            $message = $request['message'];
            $context = $request['context'] ?? null;
            $priority = $request['priority'] ?? 'normal';

            // Validate action
            if (!in_array($action, ['ask', 'delegate', 'notify'])) {
                return "Error: Invalid action '{$action}'. Must be 'ask', 'delegate', or 'notify'.";
            }

            // Block self-contact
            if ($agentId === $this->agent->id) {
                return 'Error: You cannot contact yourself.';
            }

            // Find target agent
            $target = User::where('id', $agentId)->where('type', 'agent')->where('workspace_id', $this->agent->workspace_id)->first();
            if (!$target) {
                return "Error: Agent not found. Use list_agents() to find available agents.";
            }

            // Check permission
            $permission = $this->permissionService->canContactAgent($this->agent, $target);
            if (!$permission['allowed']) {
                if ($permission['can_request']) {
                    $approval = $this->permissionService->createAccessRequest(
                        $this->agent,
                        'agent',
                        $target->id,
                        "{$this->agent->name} is requesting permission to contact {$target->name}."
                    );

                    if ($this->agent->must_wait_for_approval) {
                        $this->agent->update(['awaiting_approval_id' => $approval->id]);

                        return "Access to contact {$target->name} requires approval. An access request has been created (ID: {$approval->id}). "
                            . "Execution will pause after your response.";
                    }

                    return "Access to contact {$target->name} requires approval. An access request has been created (ID: {$approval->id}). "
                        . "Call wait_for_approval with this ID to pause until it's decided, or continue with other work.";
                }

                return "Error: You do not have permission to contact {$target->name}.";
            }

            // Handle approval requirement
            if ($permission['requires_approval']) {
                $approval = ApprovalRequest::create([
                    'id' => Str::uuid()->toString(),
                    'type' => 'action',
                    'title' => "Contact Agent: {$target->name} ({$action})",
                    'description' => "Agent {$this->agent->name} wants to {$action} {$target->name}: {$message}",
                    'requester_id' => $this->agent->id,
                    'status' => 'pending',
                    'tool_execution_context' => [
                        'tool_slug' => 'contact_agent',
                        'parameters' => $request->toArray(),
                    ],
                ]);

                if ($this->agent->must_wait_for_approval) {
                    $this->agent->update(['awaiting_approval_id' => $approval->id]);

                    return "Contacting {$target->name} requires approval. An approval request has been created (ID: {$approval->id}). "
                        . "Execution will pause after your response. Tell the user you are waiting for approval to contact {$target->name}.";
                }

                return "Contacting {$target->name} requires approval. An approval request has been created (ID: {$approval->id}). "
                    . "Call wait_for_approval with this ID to pause until it's decided, or continue with other work.";
            }

            // Status checks for ask and delegate
            if (in_array($action, ['ask', 'delegate']) && $target->status === 'offline') {
                return "Error: {$target->name} is currently offline and cannot process requests.";
            }

            // Get or create DM channel
            $channelId = $this->commService->getOrCreateDmChannel($this->agent, $target);

            // Format and post request message to DM
            $formattedMessage = $this->commService->formatRequestMessage(
                $action, $this->agent, $message, $context, $priority
            );

            return match ($action) {
                'ask' => $this->handleAsk($target, $channelId, $message, $formattedMessage),
                'delegate' => $this->handleDelegate($target, $channelId, $message, $formattedMessage, $context, $priority),
                'notify' => $this->handleNotify($target, $channelId, $message, $formattedMessage),
            };
        } catch (\Throwable $e) {
            return "Error contacting agent: {$e->getMessage()}";
        }
    }

    private function handleAsk(User $target, string $channelId, string $message, string $formattedMessage): string
    {
        // Wake sleeping agent for ask (implies urgency)
        if ($target->sleeping_until) {
            $target->update([
                'sleeping_until' => null,
                'sleeping_reason' => null,
                'status' => 'idle',
            ]);
        }

        // Use task ID from execution context, fall back to query
        $parentTaskId = $this->currentTaskId;
        if (!$parentTaskId) {
            $currentTask = Task::forWorkspace()->where('agent_id', $this->agent->id)
                ->where('status', Task::STATUS_ACTIVE)
                ->latest('started_at')
                ->first();
            $parentTaskId = $currentTask?->id;
        }

        // Create ask subtask
        $askTask = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => Str::limit($message, 80),
            'description' => $message,
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_PENDING,
            'priority' => 'normal',
            'source' => Task::SOURCE_AGENT_ASK,
            'agent_id' => $target->id,
            'requester_id' => $this->agent->id,
            'channel_id' => $channelId,
            'parent_task_id' => $parentTaskId,
            'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
        ]);

        // Track on parent agent — delegation-hold mechanism will keep parent waiting
        $this->agent->addAwaitingDelegation($askTask->id);

        // Post request to DM and dispatch async job for target agent
        $this->commService->postMessage($channelId, $this->agent, $formattedMessage, 'agent_contact');

        $triggerMessage = $this->commService->postMessage(
            $channelId, $this->agent,
            $this->commService->formatRequestMessage('ask', $this->agent, $message, null, 'normal', $askTask->id),
            'agent_contact'
        );
        AgentRespondJob::dispatch($triggerMessage, $target, $channelId);

        return "Question sent to {$target->name}. Their response will be delivered when ready (task {$askTask->id}).";
    }

    private function handleDelegate(
        User $target,
        string $channelId,
        string $message,
        string $formattedMessage,
        ?string $context,
        string $priority,
    ): string {
        // Use task ID from execution context, fall back to query
        $parentTaskId = $this->currentTaskId;
        if (!$parentTaskId) {
            $currentTask = Task::forWorkspace()->where('agent_id', $this->agent->id)
                ->where('status', Task::STATUS_ACTIVE)
                ->latest('started_at')
                ->first();
            $parentTaskId = $currentTask?->id;
        }

        // Create subtask
        $subtask = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => Str::limit($message, 80),
            'description' => $message,
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_PENDING,
            'priority' => $priority,
            'source' => Task::SOURCE_AGENT_DELEGATION,
            'agent_id' => $target->id,
            'requester_id' => $this->agent->id,
            'channel_id' => $channelId,
            'parent_task_id' => $parentTaskId,
            'context' => $context ? ['delegation_context' => $context] : null,
            'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
        ]);

        // Track delegation on caller
        $this->agent->addAwaitingDelegation($subtask->id);

        // Post formatted message with subtask ID
        $messageWithTask = $this->commService->formatRequestMessage(
            'delegate', $this->agent, $message, $context, $priority, $subtask->id
        );
        $triggerMessage = $this->commService->postMessage($channelId, $this->agent, $messageWithTask, 'agent_contact');

        // Dispatch AgentRespondJob for target (delay if sleeping)
        $job = AgentRespondJob::dispatch($triggerMessage, $target, $channelId);
        if ($target->sleeping_until && $target->sleeping_until->isFuture()) {
            $job->delay($target->sleeping_until);
        }

        return "Delegated to {$target->name} (subtask {$subtask->id}). You'll receive the result automatically when {$target->name} finishes.";
    }

    private function handleNotify(User $target, string $channelId, string $message, string $formattedMessage): string
    {
        // Create standalone task (no parent — independent from caller's task)
        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => Str::limit($message, 80),
            'description' => $message,
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_PENDING,
            'priority' => 'normal',
            'source' => Task::SOURCE_AGENT_NOTIFY,
            'agent_id' => $target->id,
            'requester_id' => $this->agent->id,
            'channel_id' => $channelId,
            'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
        ]);

        $triggerMessage = $this->commService->postMessage($channelId, $this->agent, $formattedMessage, 'agent_contact');
        AgentRespondJob::dispatch($triggerMessage, $target, $channelId);

        return "Notification sent to {$target->name}. They will process it independently (no callback).";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description('Communication pattern: "ask" (ask a question — response delivered back to you as callback), "delegate" (hand off work — result delivered back to you as callback when done), "notify" (FYI message — agent processes independently, no callback, no response to you).')
                ->required(),
            'agentId' => $schema
                ->string()
                ->description('UUID of the target agent. Use list_agents() to discover available agents.')
                ->required(),
            'message' => $schema
                ->string()
                ->description('The question, task description, or notification to send.')
                ->required(),
            'context' => $schema
                ->string()
                ->description('Background context the receiving agent needs. Summarize relevant info from your current conversation — the target does NOT see your original thread.'),
            'priority' => $schema
                ->string()
                ->description('For "delegate" only: low, normal (default), high, urgent.'),
        ];
    }
}

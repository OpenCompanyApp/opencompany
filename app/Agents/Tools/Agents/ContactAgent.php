<?php

namespace App\Agents\Tools\Agents;

use App\Agents\OpenCompanyAgent;
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
    private const MAX_DEPTH = 3;
    private const ASK_TIMEOUT_SECONDS = 120;

    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private AgentCommunicationService $commService,
    ) {}

    public function description(): string
    {
        return 'Send a message, ask a question, or delegate work to another agent. Actions: "ask" (synchronous — wait for response), "delegate" (async — hand off work, result comes back automatically), "notify" (fire-and-forget FYI). Use query_workspace(action: "list_agents") to discover available agents.';
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
            $target = User::where('id', $agentId)->where('type', 'agent')->first();
            if (!$target) {
                return "Error: Agent not found. Use query_workspace(action: \"list_agents\") to find available agents.";
            }

            // Check permission
            $permission = $this->permissionService->canContactAgent($this->agent, $target);
            if (!$permission['allowed']) {
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
        // Depth check
        if (AgentCommunicationService::currentDepth() >= self::MAX_DEPTH) {
            return "Error: Maximum agent communication depth (" . self::MAX_DEPTH . ") reached. Use 'delegate' for async processing instead.";
        }

        // Wake sleeping agent for ask (it's urgent enough that caller is waiting)
        if ($target->sleeping_until) {
            $target->update([
                'sleeping_until' => null,
                'sleeping_reason' => null,
                'status' => 'idle',
            ]);
        }

        // Find caller's current task for parent_task_id
        $currentTask = Task::where('agent_id', $this->agent->id)
            ->where('status', Task::STATUS_ACTIVE)
            ->latest('started_at')
            ->first();

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
            'parent_task_id' => $currentTask?->id,
        ]);
        $askTask->start();

        // Post request to DM
        $this->commService->postMessage($channelId, $this->agent, $formattedMessage, 'agent_contact');

        AgentCommunicationService::incrementDepth();
        try {
            $responseText = $this->invokeSynchronously($target, $channelId, $message);

            if ($responseText === null) {
                $askTask->fail('Timeout: no response within ' . self::ASK_TIMEOUT_SECONDS . ' seconds');
                return "Error: {$target->name} did not respond within " . self::ASK_TIMEOUT_SECONDS . " seconds. Consider using 'delegate' for async processing.";
            }

            // Post response to DM
            $this->commService->postMessage($channelId, $target, $responseText, 'agent_contact');

            $askTask->complete(['response' => $responseText]);

            return "Response from {$target->name}: {$responseText}";
        } catch (\Throwable $e) {
            $askTask->fail($e->getMessage());
            throw $e;
        } finally {
            AgentCommunicationService::decrementDepth();
        }
    }

    private function handleDelegate(
        User $target,
        string $channelId,
        string $message,
        string $formattedMessage,
        ?string $context,
        string $priority,
    ): string {
        // Find caller's current task for parent_task_id
        $currentTask = Task::where('agent_id', $this->agent->id)
            ->where('status', Task::STATUS_ACTIVE)
            ->latest('started_at')
            ->first();

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
            'parent_task_id' => $currentTask?->id,
            'context' => $context ? ['delegation_context' => $context] : null,
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
        // Post notification to DM — no task created, no job dispatched
        $this->commService->postMessage($channelId, $this->agent, $formattedMessage, 'agent_contact');

        return "Notification sent to {$target->name}.";
    }

    /**
     * Invoke target agent's LLM synchronously with a timeout.
     */
    private function invokeSynchronously(User $target, string $channelId, string $message): ?string
    {
        // Use pcntl_alarm for timeout if available, otherwise fall back to no timeout
        $useAlarm = function_exists('pcntl_alarm') && function_exists('pcntl_signal');

        if ($useAlarm) {
            $timedOut = false;

            pcntl_signal(SIGALRM, function () use (&$timedOut) {
                $timedOut = true;
                throw new \RuntimeException('Agent ask timeout');
            });
            $previousAlarm = pcntl_alarm(self::ASK_TIMEOUT_SECONDS);

            try {
                $agentInstance = OpenCompanyAgent::for($target, $channelId);
                $response = $agentInstance->prompt($message);
                pcntl_alarm(0); // Cancel alarm on success

                return $response->text;
            } catch (\RuntimeException $e) {
                if ($timedOut || str_contains($e->getMessage(), 'Agent ask timeout')) {
                    return null;
                }
                throw $e;
            } finally {
                pcntl_alarm($previousAlarm ?: 0);
                pcntl_signal(SIGALRM, SIG_DFL);
            }
        }

        // Fallback: no timeout protection
        $agentInstance = OpenCompanyAgent::for($target, $channelId);
        $response = $agentInstance->prompt($message);

        return $response->text;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description('Communication pattern: "ask" (synchronous — wait for response inline), "delegate" (async — hand off work, result comes back automatically when done), "notify" (fire-and-forget, no response expected).')
                ->required(),
            'agentId' => $schema
                ->string()
                ->description('UUID of the target agent. Use query_workspace(action: "list_agents") to discover available agents.')
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

<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Jobs\AgentRespondJob;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request as ToolRequest;

/**
 * Applies approval decisions to pending tool/access requests.
 *
 * Approval callbacks can arrive from external chat buttons or in-app flows after
 * the original agent job has paused. This service re-binds workspace context,
 * executes the approved side effect once, records a message result, and resumes
 * the waiting agent when appropriate.
 */
class ApprovalExecutionService
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private ?AgentPermissionService $permissions = null,
    ) {}

    /**
     * Execute a tool after its approval request is approved.
     */
    public function executeApprovedTool(ApprovalRequest $approval, bool $agentIsWaiting): void
    {
        $context = $approval->tool_execution_context ?? [];
        /** @var User|null $agent */
        $agent = $approval->requester;
        $toolSlug = $context['tool_slug'] ?? null;

        if (! $agent || ! $toolSlug) {
            return;
        }

        // Queue and webhook callbacks may not have ResolveWorkspace middleware.
        // Bind from the requesting agent before instantiating tools that rely on
        // workspace() or forWorkspace().
        $this->bindWorkspaceContext($agent);

        $tool = $this->toolRegistry->instantiateToolBySlug($toolSlug, $agent);

        if (! $tool) {
            return;
        }

        $toolMeta = $this->toolRegistry->getToolDefinitionBySlug($toolSlug);
        $permission = ($this->permissions ?? app(AgentPermissionService::class))->resolveToolPermission(
            $agent,
            $toolSlug,
            (string) ($toolMeta['type'] ?? 'read'),
        );

        if (($permission['allowed'] ?? false) !== true) {
            Log::warning("Approved tool {$toolSlug} was not executed because permission is now denied.", [
                'approval_id' => $approval->id,
                'agent_id' => $agent->id,
            ]);

            return;
        }

        try {
            $toolRequest = new ToolRequest($context['parameters'] ?? []);
            $result = $tool->handle($toolRequest);
            $decodedResult = is_string($result) ? json_decode($result, true) : null;
            $toolSucceeded = ! (is_array($decodedResult) && ($decodedResult['ok'] ?? true) === false);

            // Post the result as a normal channel message so the resumed agent
            // sees the approved side effect in conversation history.
            $channelId = $approval->channel_id ?? ($context['parameters']['channelId'] ?? null);
            if ($channelId) {
                $channel = $this->approvalChannel($agent, $channelId);
                if ($channel) {
                    $resultMessage = Message::create([
                        'id' => Str::uuid()->toString(),
                        'content' => ($toolSucceeded ? '**Approved action executed:** ' : '**Approved action failed:** ').$result,
                        'channel_id' => $channelId,
                        'author_id' => $agent->id,
                        'timestamp' => now(),
                    ]);
                    $channel->update(['last_message_at' => now()]);

                    // Resume only the agent that explicitly parked on this
                    // approval. Non-waiting approvals still execute, but they
                    // should not create an extra response loop.
                    if ($agentIsWaiting) {
                        $agent->clearAwaitingApproval();
                        $task = Task::createPending($resultMessage, $agent, $channelId);
                        AgentRespondJob::dispatch($resultMessage, $agent, $channelId, $task->id);
                    }
                }
            } elseif ($agentIsWaiting) {
                $agent->clearAwaitingApproval();
            }
        } catch (\Throwable $e) {
            Log::error("Failed to execute approved tool {$toolSlug}: {$e->getMessage()}");

            if ($agentIsWaiting) {
                $agent->clearAwaitingApproval();
            }
        }
    }

    /**
     * Grant access after an access-type approval is approved.
     * Creates an AgentPermission record and notifies the agent.
     */
    public function executeApprovedAccess(ApprovalRequest $approval, bool $agentIsWaiting): void
    {
        $context = $approval->tool_execution_context ?? [];
        /** @var User|null $agent */
        $agent = $approval->requester;
        $scopeType = $context['scope_type'] ?? null;
        $scopeKey = $context['scope_key'] ?? null;

        if (! $agent || ! $scopeType || ! $scopeKey) {
            return;
        }

        $this->bindWorkspaceContext($agent);

        try {
            // Approval of access reverses a previous deny for the exact scope
            // before creating the allow record, so future permission resolution
            // has one clear winning rule.
            AgentPermission::forAgent($agent->id)
                ->where('scope_type', $scopeType)
                ->where('scope_key', $scopeKey)
                ->where('permission', 'deny')
                ->delete();

            // Create the allow permission record
            AgentPermission::create([
                'id' => Str::uuid()->toString(),
                'agent_id' => $agent->id,
                'scope_type' => $scopeType,
                'scope_key' => $scopeKey,
                'permission' => 'allow',
                'requires_approval' => false,
            ]);

            // Notify through the original channel when possible. That message
            // becomes the prompt that wakes the agent if it was waiting.
            $channelId = $approval->channel_id;
            if ($channelId) {
                $channel = $this->approvalChannel($agent, $channelId);
                if ($channel) {
                    $message = Message::create([
                        'id' => Str::uuid()->toString(),
                        'content' => "**Access granted:** You now have access to {$scopeType} `{$scopeKey}`.",
                        'channel_id' => $channelId,
                        'author_id' => $agent->id,
                        'timestamp' => now(),
                    ]);
                    $channel->update(['last_message_at' => now()]);

                    if ($agentIsWaiting) {
                        $agent->clearAwaitingApproval();
                        $task = Task::createPending($message, $agent, $channelId);
                        AgentRespondJob::dispatch($message, $agent, $channelId, $task->id);

                        return;
                    }
                }
            }

            if ($agentIsWaiting) {
                $agent->clearAwaitingApproval();
            }
        } catch (\Throwable $e) {
            Log::error("Failed to execute approved access for {$scopeType}/{$scopeKey}: {$e->getMessage()}");

            if ($agentIsWaiting) {
                $agent->clearAwaitingApproval();
            }
        }
    }

    /**
     * Handle a rejected approval when the agent was waiting.
     */
    public function handleRejectedTool(ApprovalRequest $approval): void
    {
        /** @var User|null $agent */
        $agent = $approval->requester;
        if (! $agent) {
            return;
        }

        $this->bindWorkspaceContext($agent);

        $context = $approval->tool_execution_context ?? [];
        $channelId = $approval->channel_id ?? ($context['parameters']['channelId'] ?? null);

        if ($channelId) {
            $channel = $this->approvalChannel($agent, $channelId);
            if ($channel) {
                $denialMessage = Message::create([
                    'id' => Str::uuid()->toString(),
                    'content' => "**Approval denied:** {$approval->title}. The requested action was not approved.",
                    'channel_id' => $channelId,
                    'author_id' => $agent->id,
                    'timestamp' => now(),
                ]);
                $channel->update(['last_message_at' => now()]);

                $agent->clearAwaitingApproval();
                $task = Task::createPending($denialMessage, $agent, $channelId);
                AgentRespondJob::dispatch($denialMessage, $agent, $channelId, $task->id);
            } else {
                $agent->clearAwaitingApproval();
            }
        } else {
            $agent->clearAwaitingApproval();
        }
    }

    /**
     * Bind workspace context from agent so workspace() and forWorkspace() work in queue workers.
     */
    private function bindWorkspaceContext(User $agent): void
    {
        if ($agent->workspace_id) {
            $workspace = Workspace::find($agent->workspace_id);
            if ($workspace) {
                app()->instance('currentWorkspace', $workspace);
            }
        }
    }

    private function approvalChannel(User $agent, string $channelId): ?Channel
    {
        return Channel::query()
            ->where('workspace_id', $agent->workspace_id)
            ->find($channelId);
    }
}

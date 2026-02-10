<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Jobs\AgentRespondJob;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request as ToolRequest;

class ApprovalExecutionService
{
    public function __construct(
        private ToolRegistry $toolRegistry,
    ) {}

    /**
     * Execute a tool after its approval request is approved.
     */
    public function executeApprovedTool(ApprovalRequest $approval, bool $agentIsWaiting): void
    {
        $context = $approval->tool_execution_context ?? [];
        /** @var \App\Models\User|null $agent */
        $agent = $approval->requester;
        $toolSlug = $context['tool_slug'] ?? null;

        if (!$agent || !$toolSlug) {
            return;
        }

        $tool = $this->toolRegistry->instantiateToolBySlug($toolSlug, $agent);

        if (!$tool) {
            return;
        }

        try {
            $toolRequest = new ToolRequest($context['parameters'] ?? []);
            $result = $tool->handle($toolRequest);

            // Post the result as a message to the channel
            $channelId = $approval->channel_id ?? ($context['parameters']['channelId'] ?? null);
            if ($channelId) {
                $channel = Channel::find($channelId);
                if ($channel) {
                    $resultMessage = Message::create([
                        'id' => Str::uuid()->toString(),
                        'content' => "**Approved action executed:** {$result}",
                        'channel_id' => $channelId,
                        'author_id' => $agent->id,
                        'timestamp' => now(),
                    ]);
                    $channel->update(['last_message_at' => now()]);

                    // Resume agent if it was waiting for this approval
                    if ($agentIsWaiting) {
                        $agent->clearAwaitingApproval();
                        AgentRespondJob::dispatch($resultMessage, $agent, $channelId);
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
        /** @var \App\Models\User|null $agent */
        $agent = $approval->requester;
        $scopeType = $context['scope_type'] ?? null;
        $scopeKey = $context['scope_key'] ?? null;

        if (!$agent || !$scopeType || !$scopeKey) {
            return;
        }

        try {
            // Remove any existing deny record for this scope
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

            // Notify the agent via its last active channel
            $channelId = $approval->channel_id;
            if ($channelId) {
                $channel = Channel::find($channelId);
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
                        AgentRespondJob::dispatch($message, $agent, $channelId);

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
        /** @var \App\Models\User|null $agent */
        $agent = $approval->requester;
        if (!$agent) {
            return;
        }

        $context = $approval->tool_execution_context ?? [];
        $channelId = $approval->channel_id ?? ($context['parameters']['channelId'] ?? null);

        if ($channelId) {
            $channel = Channel::find($channelId);
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
                AgentRespondJob::dispatch($denialMessage, $agent, $channelId);
            } else {
                $agent->clearAwaitingApproval();
            }
        } else {
            $agent->clearAwaitingApproval();
        }
    }
}

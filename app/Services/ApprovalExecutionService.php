<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Jobs\AgentRespondJob;
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
        $context = $approval->tool_execution_context;
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
     * Handle a rejected approval when the agent was waiting.
     */
    public function handleRejectedTool(ApprovalRequest $approval): void
    {
        $agent = $approval->requester;
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

<?php

namespace App\Http\Controllers\Api;

use App\Agents\Tools\ToolRegistry;
use App\Http\Controllers\Controller;
use App\Jobs\AgentRespondJob;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request as ToolRequest;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $query = ApprovalRequest::with(['requester', 'respondedBy']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => $request->input('type'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'requester_id' => $request->input('requesterId'),
            'amount' => $request->input('amount'),
            'status' => 'pending',
        ]);

        return $approval->load('requester');
    }

    public function update(Request $request, string $id)
    {
        $approval = ApprovalRequest::findOrFail($id);

        $approval->update([
            'status' => $request->input('status'),
            'responded_by_id' => $request->input('respondedById'),
            'responded_at' => now(),
        ]);

        $agent = $approval->requester;
        $agentIsWaiting = $agent
            && $agent->type === 'agent'
            && $agent->awaiting_approval_id === $approval->id;

        if ($approval->status === 'approved' && $approval->tool_execution_context) {
            $this->executeApprovedTool($approval, $agentIsWaiting);
        } elseif ($approval->status === 'rejected' && $agentIsWaiting) {
            $this->handleRejectedTool($approval);
        }

        return $approval->load(['requester', 'respondedBy']);
    }

    /**
     * Execute a tool after its approval request is approved.
     */
    private function executeApprovedTool(ApprovalRequest $approval, bool $agentIsWaiting): void
    {
        $context = $approval->tool_execution_context;
        $agent = $approval->requester;
        $toolSlug = $context['tool_slug'] ?? null;

        if (!$agent || !$toolSlug) {
            return;
        }

        $toolRegistry = app(ToolRegistry::class);
        $tool = $toolRegistry->instantiateToolBySlug($toolSlug, $agent);

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
                // No channel to post to, but still clear the awaiting state
                $agent->clearAwaitingApproval();
            }
        } catch (\Throwable $e) {
            \Log::error("Failed to execute approved tool {$toolSlug}: {$e->getMessage()}");

            if ($agentIsWaiting) {
                $agent->clearAwaitingApproval();
            }
        }
    }

    /**
     * Handle a rejected approval when the agent was waiting.
     */
    private function handleRejectedTool(ApprovalRequest $approval): void
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

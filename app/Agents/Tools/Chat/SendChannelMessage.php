<?php

namespace App\Agents\Tools\Chat;

use App\Events\MessageSent;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use App\Agents\Runtime\ApprovedToolReplay;
use App\Agents\Tools\ToolRegistry;
use App\Domain\Authorization\Application\AgentPermissionResolver;
use App\Models\ApprovalRequest;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Posts an agent message into a workspace channel.
 *
 * External channels (bridged to Telegram, Slack, ...) leave OpenCompany, so a
 * send to an external channel other than the conversation the agent is
 * currently answering requires approval regardless of behavior mode, unless
 * an admin opted this tool out (see AgentPermissionResolver). Replying inside
 * the current conversation is not gated.
 */
class SendChannelMessage implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    private function needsExternalSendApproval(Channel $channel): bool
    {
        if ($channel->type !== 'external' || ApprovedToolReplay::isApproved('send_channel_message')) {
            return false;
        }
        if (app(ToolRegistry::class)->getChannelContext() === $channel->id) {
            return false;
        }

        return app(AgentPermissionResolver::class)->requiresExternalChannelSendApproval($this->agent);
    }

    public function description(): string
    {
        return 'Send a message to any workspace channel. Messages to external channels (Telegram, Slack) other than the conversation you are answering require human approval before delivery.';
    }

    public function handle(Request $request): string
    {
        try {
            $channelId = $request['channelId'];
            $content = $request['content'];

            // Check channel access permission
            $channelAccess = $this->permissionService->canAccessChannel($this->agent, $channelId);
            if (!$channelAccess['allowed']) {
                if ($channelAccess['can_request']) {
                    $channelName = Channel::forWorkspace()->find($channelId)->name ?? $channelId;
                    $approval = $this->permissionService->createAccessRequest(
                        $this->agent, 'channel', $channelId,
                        "{$this->agent->name} is requesting access to channel #{$channelName}."
                    );

                    return "Access to this channel requires approval. An access request has been created (ID: {$approval->id}). "
                        . "Call wait_for_approval with this ID to pause until it's decided, or continue with other work.";
                }

                return "Error: You do not have permission to send messages to this channel.";
            }

            $channel = Channel::forWorkspace()->find($channelId);
            if (!$channel) {
                return "Error: Channel '{$channelId}' not found.";
            }

            // Invariant: outbound delivery to a non-OpenCompany destination is
            // an external mutation; a trigger or prompt is not consent.
            if ($this->needsExternalSendApproval($channel)) {
                $approval = ApprovalRequest::create([
                    'id' => Str::uuid()->toString(),
                    'type' => 'action',
                    'workspace_id' => $this->agent->workspace_id,
                    'title' => 'Tool: send_channel_message',
                    'description' => "Agent {$this->agent->name} wants to send a message to external channel #{$channel->name}",
                    'requester_id' => $this->agent->id,
                    'status' => 'pending',
                    'tool_execution_context' => [
                        'tool_slug' => 'send_channel_message',
                        'parameters' => $request->toArray(),
                    ],
                    'channel_id' => app(ToolRegistry::class)->getChannelContext(),
                ]);

                return "Sending to an external channel requires human approval. An approval request has been created (ID: {$approval->id}). "
                    .'Call wait_for_approval with this ID if your next steps depend on it, or continue — it is delivered automatically if approved.';
            }

            $message = Message::create([
                'id' => Str::uuid()->toString(),
                'content' => $content,
                'channel_id' => $channelId,
                'author_id' => $this->agent->id,
                'timestamp' => now(),
            ]);

            $channel->update(['last_message_at' => now()]);

            broadcast(new MessageSent($message));

            return json_encode(['id' => $message->id, 'channel' => $channel->name, 'message' => 'Message sent']);
        } catch (\Throwable $e) {
            return "Error sending message: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channelId' => $schema
                ->string()
                ->description('The UUID of the channel to send the message to.')
                ->required(),
            'content' => $schema
                ->string()
                ->description('The message content to send.')
                ->required(),
        ];
    }
}

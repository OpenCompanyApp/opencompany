<?php

namespace App\Agents\Tools\Chat;

use App\Events\MessageSent;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SendChannelMessage implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Send a message to any workspace channel, including external channels (Telegram, Slack). Messages to external channels are automatically delivered.';
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
                    $channelName = Channel::find($channelId)->name ?? $channelId;
                    $approval = $this->permissionService->createAccessRequest(
                        $this->agent, 'channel', $channelId,
                        "{$this->agent->name} is requesting access to channel #{$channelName}."
                    );

                    return "Access to this channel requires approval. An access request has been created (ID: {$approval->id}). "
                        . "Call wait_for_approval with this ID to pause until it's decided, or continue with other work.";
                }

                return "Error: You do not have permission to send messages to this channel.";
            }

            $channel = Channel::find($channelId);
            if (!$channel) {
                return "Error: Channel '{$channelId}' not found.";
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

            return "Message sent successfully to channel '{$channel->name}'.";
        } catch (\Throwable $e) {
            return "Error sending message: {$e->getMessage()}";
        }
    }

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

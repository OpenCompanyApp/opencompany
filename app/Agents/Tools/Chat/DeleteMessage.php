<?php

namespace App\Agents\Tools\Chat;

use App\Events\MessageDeleted;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteMessage implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Delete a message from a channel. Deletion syncs to external platforms (Telegram, Discord).';
    }

    public function handle(Request $request): string
    {
        try {
            $messageId = $request['messageId'];

            $message = Message::resolveByShortId($messageId);
            if (!$message) {
                return "Error: Message '{$messageId}' not found.";
            }

            $channelAccess = $this->permissionService->canAccessChannel($this->agent, $message->channel_id);
            if (!$channelAccess['allowed']) {
                return "Error: You do not have permission to manage messages in this channel.";
            }

            event(new MessageDeleted($message));

            $synced = $this->syncIndicator($message);

            $message->delete();

            return "Message deleted.{$synced}";
        } catch (\Throwable $e) {
            return "Error managing message: {$e->getMessage()}";
        }
    }

    private function syncIndicator(Message $message): string
    {
        $channel = $message->channel;
        if ($channel && $channel->type === 'external' && $channel->external_provider) {
            return " Synced to {$channel->external_provider}.";
        }

        return '';
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'messageId' => $schema
                ->string()
                ->description('The UUID of the message to delete.')
                ->required(),
        ];
    }
}

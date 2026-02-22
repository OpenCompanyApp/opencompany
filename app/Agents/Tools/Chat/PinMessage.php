<?php

namespace App\Agents\Tools\Chat;

use App\Events\MessagePinned;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class PinMessage implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Toggle the pinned status of a message. Pins or unpins the message in its channel.';
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

            if ($message->is_pinned) {
                $message->update([
                    'is_pinned' => false,
                    'pinned_at' => null,
                    'pinned_by_id' => null,
                ]);

                return "Message unpinned.";
            }

            $message->update([
                'is_pinned' => true,
                'pinned_at' => now(),
                'pinned_by_id' => $this->agent->id,
            ]);

            event(new MessagePinned($message));

            $synced = $this->syncIndicator($message);

            return "Message pinned.{$synced}";
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
                ->description('The UUID of the message to pin or unpin.')
                ->required(),
        ];
    }
}

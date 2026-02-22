<?php

namespace App\Agents\Tools\Chat;

use App\Events\MessageReactionAdded;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AddMessageReaction implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Add an emoji reaction to a message. Reactions sync to external platforms (Telegram, Discord).';
    }

    public function handle(Request $request): string
    {
        try {
            $messageId = $request['messageId'];
            $emoji = $request['emoji'];

            $message = Message::resolveByShortId($messageId);
            if (!$message) {
                return "Error: Message '{$messageId}' not found.";
            }

            $channelAccess = $this->permissionService->canAccessChannel($this->agent, $message->channel_id);
            if (!$channelAccess['allowed']) {
                return "Error: You do not have permission to manage messages in this channel.";
            }

            MessageReaction::create([
                'id' => Str::uuid()->toString(),
                'message_id' => $message->id,
                'user_id' => $this->agent->id,
                'emoji' => $emoji,
            ]);

            event(new MessageReactionAdded($message, $emoji));

            $synced = $this->syncIndicator($message);

            return "Reaction added.{$synced}";
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
                ->description('The UUID of the message to react to.')
                ->required(),
            'emoji' => $schema
                ->string()
                ->description('The emoji to add as a reaction.')
                ->required(),
        ];
    }
}

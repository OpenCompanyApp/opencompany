<?php

namespace App\Agents\Tools\Chat;

use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessagePinned;
use App\Events\MessageReactionAdded;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ManageMessage implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Manage messages in channels. Edit, delete, toggle pins, or add and remove reactions. Actions sync to external platforms (Telegram, Discord).';
    }

    public function handle(Request $request): string
    {
        try {
            $messageId = $request['messageId'];
            $action = $request['action'];

            $message = Message::resolveByShortId($messageId);
            if (!$message) {
                return "Error: Message '{$messageId}' not found.";
            }

            $channelAccess = $this->permissionService->canAccessChannel($this->agent, $message->channel_id);
            if (!$channelAccess['allowed']) {
                return "Error: You do not have permission to manage messages in this channel.";
            }

            return match ($action) {
                'edit' => $this->editMessage($message, $request),
                'delete' => $this->deleteMessage($message),
                'pin' => $this->pinMessage($message),
                'add_reaction' => $this->addReaction($message, $request),
                'remove_reaction' => $this->removeReaction($request),
                default => "Error: Unknown action '{$action}'. Use 'edit', 'delete', 'pin', 'add_reaction', or 'remove_reaction'.",
            };
        } catch (\Throwable $e) {
            return "Error managing message: {$e->getMessage()}";
        }
    }

    private function editMessage(Message $message, Request $request): string
    {
        $content = $request['content'] ?? null;
        if (!$content) {
            return "Error: 'content' is required for the 'edit' action.";
        }

        $message->update(['content' => $content]);

        event(new MessageEdited($message));

        $synced = $this->syncIndicator($message);

        return "Message edited.{$synced}";
    }

    private function deleteMessage(Message $message): string
    {
        event(new MessageDeleted($message));

        $synced = $this->syncIndicator($message);

        $message->delete();

        return "Message deleted.{$synced}";
    }

    private function pinMessage(Message $message): string
    {
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
    }

    private function addReaction(Message $message, Request $request): string
    {
        $emoji = $request['emoji'] ?? null;
        if (!$emoji) {
            return "Error: 'emoji' is required for the 'add_reaction' action.";
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
    }

    private function removeReaction(Request $request): string
    {
        $reactionId = $request['reactionId'] ?? null;
        if (!$reactionId) {
            return "Error: 'reactionId' is required for the 'remove_reaction' action.";
        }

        $reaction = MessageReaction::find($reactionId);
        if (!$reaction) {
            return "Error: Reaction '{$reactionId}' not found.";
        }

        $reaction->delete();

        return "Reaction removed.";
    }

    /**
     * Return a sync indicator suffix when the message is on an external channel.
     */
    private function syncIndicator(Message $message): string
    {
        $channel = $message->channel;
        if ($channel && $channel->type === 'external' && $channel->external_provider) {
            return " Synced to {$channel->external_provider}.";
        }

        return '';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'messageId' => $schema
                ->string()
                ->description('The UUID of the message to manage.')
                ->required(),
            'action' => $schema
                ->string()
                ->description("The management action: 'edit', 'delete', 'pin', 'add_reaction', or 'remove_reaction'.")
                ->required(),
            'content' => $schema
                ->string()
                ->description('The new message content. Required for the edit action.'),
            'emoji' => $schema
                ->string()
                ->description('The emoji to add as a reaction. Required for add_reaction.'),
            'reactionId' => $schema
                ->string()
                ->description('The UUID of the reaction to remove. Required for remove_reaction.'),
        ];
    }
}

<?php

namespace App\Agents\Tools\Chat;

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
        return 'Manage messages in channels. Delete messages, toggle pins, or add and remove reactions.';
    }

    public function handle(Request $request): string
    {
        try {
            $messageId = $request['messageId'];
            $action = $request['action'];

            $message = Message::find($messageId);
            if (!$message) {
                return "Error: Message '{$messageId}' not found.";
            }

            if (!$this->permissionService->canAccessChannel($this->agent, $message->channel_id)) {
                return "Error: You do not have permission to manage messages in this channel.";
            }

            return match ($action) {
                'delete' => $this->deleteMessage($message),
                'pin' => $this->pinMessage($message),
                'add_reaction' => $this->addReaction($message, $request),
                'remove_reaction' => $this->removeReaction($request),
                default => "Error: Unknown action '{$action}'. Use 'delete', 'pin', 'add_reaction', or 'remove_reaction'.",
            };
        } catch (\Throwable $e) {
            return "Error managing message: {$e->getMessage()}";
        }
    }

    private function deleteMessage(Message $message): string
    {
        $message->delete();

        return "Message deleted.";
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

        return "Message pinned.";
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

        return "Reaction added.";
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

    public function schema(JsonSchema $schema): array
    {
        return [
            'messageId' => $schema
                ->string()
                ->description('The UUID of the message to manage.')
                ->required(),
            'action' => $schema
                ->string()
                ->description("The management action: 'delete', 'pin', 'add_reaction', or 'remove_reaction'.")
                ->required(),
            'emoji' => $schema
                ->string()
                ->description('The emoji to add as a reaction. Required for add_reaction.'),
            'reactionId' => $schema
                ->string()
                ->description('The UUID of the reaction to remove. Required for remove_reaction.'),
        ];
    }
}

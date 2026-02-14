<?php

namespace App\Agents\Tools\Chat;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ReadChannel implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Read messages from a channel. Supports recent messages, threaded replies, and pinned messages.';
    }

    public function handle(Request $request): string
    {
        try {
            $channelId = $request['channelId'];
            $action = $request['action'] ?? 'recent_messages';
            $limit = $request['limit'] ?? 20;

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

                return "Error: You do not have permission to access this channel.";
            }

            $channel = Channel::find($channelId);
            if (!$channel) {
                return "Error: Channel '{$channelId}' not found.";
            }

            return match ($action) {
                'recent_messages' => $this->recentMessages($channel, $limit),
                'thread' => $this->thread($request),
                'pinned' => $this->pinnedMessages($channel),
                default => "Error: Unknown action '{$action}'. Use 'recent_messages', 'thread', or 'pinned'.",
            };
        } catch (\Throwable $e) {
            return "Error reading channel: {$e->getMessage()}";
        }
    }

    private function recentMessages(Channel $channel, int $limit): string
    {
        $messages = Message::with('author')
            ->where('channel_id', $channel->id)
            ->orderBy('created_at', 'desc')
            ->take(min($limit, 50))
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return "No messages found in channel '{$channel->name}'.";
        }

        $lines = ["Recent messages in #{$channel->name}:"];
        foreach ($messages as $message) {
            $lines[] = $this->formatMessage($message);
        }

        return implode("\n", $lines);
    }

    private function thread(Request $request): string
    {
        $messageId = $request['messageId'] ?? null;
        if (!$messageId) {
            return "Error: 'messageId' is required for the 'thread' action.";
        }

        $parent = Message::resolveByShortId($messageId)?->load('author');
        if (!$parent) {
            return "Error: Message '{$messageId}' not found.";
        }

        $replies = Message::with('author')
            ->where('reply_to_id', $parent->id)
            ->orderBy('created_at', 'asc')
            ->get();

        /** @var User|null $parentAuthor */
        $parentAuthor = $parent->author;
        $author = $parentAuthor->name ?? 'Unknown';
        $lines = ["Thread for message by {$author}:"];
        $lines[] = $this->formatMessage($parent);
        $lines[] = "--- Replies ({$replies->count()}) ---";

        foreach ($replies as $reply) {
            $lines[] = $this->formatMessage($reply);
        }

        return implode("\n", $lines);
    }

    private function pinnedMessages(Channel $channel): string
    {
        $messages = Message::with('author')
            ->where('channel_id', $channel->id)
            ->where('is_pinned', true)
            ->orderBy('pinned_at', 'desc')
            ->get();

        if ($messages->isEmpty()) {
            return "No pinned messages in channel '{$channel->name}'.";
        }

        $lines = ["Pinned messages in #{$channel->name}:"];
        foreach ($messages as $message) {
            $lines[] = $this->formatMessage($message);
        }

        return implode("\n", $lines);
    }

    private function formatMessage(Message $message): string
    {
        $shortId = substr($message->id, 0, 6);
        $time = $message->created_at->format('Y-m-d H:i');

        /** @var User|null $author */
        $author = $message->author;
        $authorName = $author->name ?? 'Unknown';

        $source = $message->source && $message->source !== 'workspace'
            ? " (via {$message->source})"
            : '';

        $pin = $message->is_pinned ? ' 📌' : '';

        return "[msg:{$shortId}] [{$time}] {$authorName}{$source}: {$message->content}{$pin}";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channelId' => $schema
                ->string()
                ->description('The UUID of the channel to read messages from.')
                ->required(),
            'action' => $schema
                ->string()
                ->description("The type of read action: 'recent_messages' (default), 'thread', or 'pinned'."),
            'messageId' => $schema
                ->string()
                ->description('The UUID of the parent message. Required when action is "thread".'),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of messages to return (default: 20, max: 50).'),
        ];
    }
}

<?php

namespace App\Agents\Tools\Chat;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ReadRecentMessages implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Read recent messages from a channel, ordered by most recent first.';
    }

    public function handle(Request $request): string
    {
        try {
            $channelId = $request['channelId'];
            $limit = $request['limit'] ?? 20;

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

                return "Error: You do not have permission to access this channel.";
            }

            $channel = Channel::forWorkspace()->find($channelId);
            if (!$channel) {
                return "Error: Channel '{$channelId}' not found.";
            }

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
        } catch (\Throwable $e) {
            return "Error reading channel: {$e->getMessage()}";
        }
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
            'limit' => $schema
                ->integer()
                ->description('Maximum number of messages to return (default: 20, max: 50).'),
        ];
    }
}

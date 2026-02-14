<?php

namespace App\Agents\Tools\Chat;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchMessages implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Search messages across channels by keyword, with optional channel and author filtering.';
    }

    public function handle(Request $request): string
    {
        try {
            $query = $request['query'] ?? null;
            if (!$query) {
                return "Error: 'query' is required.";
            }

            $channelId = $request['channelId'] ?? null;
            $authorId = $request['authorId'] ?? null;
            $limit = min($request['limit'] ?? 20, 50);

            $search = Message::with(['author', 'channel'])
                ->where('content', 'LIKE', "%{$query}%")
                ->orderBy('created_at', 'desc')
                ->limit($limit);

            if ($channelId) {
                $channelAccess = $this->permissionService->canAccessChannel($this->agent, $channelId);
                if (!$channelAccess['allowed']) {
                    return "Error: You do not have permission to search in this channel.";
                }
                $search->where('channel_id', $channelId);
            }

            if ($authorId) {
                $search->where('author_id', $authorId);
            }

            $messages = $search->get();

            if ($messages->isEmpty()) {
                return "No messages found matching '{$query}'.";
            }

            $lines = ["Search results for '{$query}' ({$messages->count()} found):"];

            foreach ($messages as $message) {
                $shortId = substr($message->id, 0, 6);
                $time = $message->created_at->format('Y-m-d H:i');

                /** @var User|null $author */
                $author = $message->author;
                $authorName = $author->name ?? 'Unknown';

                /** @var Channel|null $channel */
                $channel = $message->channel;
                $channelName = $channel->name ?? 'unknown';

                $snippet = mb_strlen($message->content) > 120
                    ? mb_substr($message->content, 0, 120) . '...'
                    : $message->content;

                $lines[] = "[msg:{$shortId}] [#{$channelName}] [{$time}] {$authorName}: {$snippet}";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error searching messages: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The search term to find in message content.')
                ->required(),
            'channelId' => $schema
                ->string()
                ->description('Optional: scope search to a specific channel UUID.'),
            'authorId' => $schema
                ->string()
                ->description('Optional: filter by author UUID.'),
            'limit' => $schema
                ->integer()
                ->description('Maximum results to return (default: 20, max: 50).'),
        ];
    }
}

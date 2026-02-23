<?php

namespace App\Agents\Tools\Chat;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ReadThread implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Read a message thread including the parent message and all its replies.';
    }

    public function handle(Request $request): string
    {
        try {
            $channelId = $request['channelId'];
            $messageId = $request['messageId'];

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

            $parent = Message::resolveByShortId($messageId)?->load('author');
            if (!$parent) {
                return "Error: Message '{$messageId}' not found.";
            }

            $replies = Message::with('author')
                ->where('reply_to_id', $parent->id)
                ->orderBy('created_at', 'asc')
                ->get();

            return json_encode([
                'parent' => $this->formatMessage($parent),
                'replies' => $replies->map(fn ($reply) => $this->formatMessage($reply))->values()->toArray(),
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error reading channel: {$e->getMessage()}";
        }
    }

    private function formatMessage(Message $message): array
    {
        return array_filter([
            'id' => $message->id,
            'author' => $message->author?->name ?? 'Unknown',
            'authorId' => $message->author_id,
            'content' => $message->content,
            'source' => $message->source !== 'workspace' ? $message->source : null,
            'pinned' => $message->is_pinned ?: null,
            'createdAt' => $message->created_at->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channelId' => $schema
                ->string()
                ->description('The UUID of the channel the thread belongs to.')
                ->required(),
            'messageId' => $schema
                ->string()
                ->description('The UUID of the parent message to read the thread for.')
                ->required(),
        ];
    }
}

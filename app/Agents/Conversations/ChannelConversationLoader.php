<?php

namespace App\Agents\Conversations;

use App\Models\Message;
use App\Models\User;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

class ChannelConversationLoader
{
    /**
     * Default number of messages to load for conversation context.
     */
    private const DEFAULT_LIMIT = 20;

    /**
     * Load recent messages from a channel as SDK message objects.
     *
     * @return iterable<UserMessage|AssistantMessage>
     */
    public function load(string $channelId, User $agent, int $limit = self::DEFAULT_LIMIT): iterable
    {
        $messages = Message::where('channel_id', $channelId)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->reverse()
            ->values();

        $sdkMessages = [];

        foreach ($messages as $message) {
            if (empty($message->content)) {
                continue;
            }

            if ($message->author_id === $agent->id) {
                $sdkMessages[] = new AssistantMessage($message->content);
            } else {
                // Prefix with author name for multi-user context
                /** @var User|null $author */
                $author = $message->author;
                $authorName = $author->name ?? 'User';
                $content = "[{$authorName}]: {$message->content}";
                $sdkMessages[] = new UserMessage($content);
            }
        }

        return $sdkMessages;
    }
}

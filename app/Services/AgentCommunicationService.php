<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentCommunicationService
{
    /**
     * Tracks synchronous ask nesting depth.
     * Safe for queue workers: PHP processes are single-threaded, each worker has its own static.
     */
    private static int $depth = 0;

    /**
     * Get or create a DM channel between two agents.
     * Returns the channel_id string.
     */
    public function getOrCreateDmChannel(User $agentA, User $agentB): string
    {
        $existingDm = DirectMessage::where(function ($query) use ($agentA, $agentB) {
            $query->where('user1_id', $agentA->id)->where('user2_id', $agentB->id);
        })->orWhere(function ($query) use ($agentA, $agentB) {
            $query->where('user1_id', $agentB->id)->where('user2_id', $agentA->id);
        })->first();

        if ($existingDm) {
            return $existingDm->channel_id;
        }

        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'name' => 'DM',
            'type' => 'dm',
        ]);

        foreach ([$agentA->id, $agentB->id] as $userId) {
            ChannelMember::create([
                'id' => Str::uuid()->toString(),
                'channel_id' => $channel->id,
                'user_id' => $userId,
                'role' => 'member',
            ]);
        }

        DirectMessage::create([
            'id' => Str::uuid()->toString(),
            'user1_id' => $agentA->id,
            'user2_id' => $agentB->id,
            'channel_id' => $channel->id,
        ]);

        return $channel->id;
    }

    /**
     * Post a message to a channel and broadcast it.
     */
    public function postMessage(string $channelId, User $author, string $content, string $source = 'agent_contact'): Message
    {
        $message = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $content,
            'channel_id' => $channelId,
            'author_id' => $author->id,
            'timestamp' => now(),
            'source' => $source,
        ]);

        Channel::where('id', $channelId)->update(['last_message_at' => now()]);
        DirectMessage::where('channel_id', $channelId)->update(['last_message_at' => now()]);

        try {
            broadcast(new MessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast agent communication message', [
                'error' => $e->getMessage(),
                'channel' => $channelId,
            ]);
        }

        return $message;
    }

    /**
     * Format a request message header per the interagent-comms protocol.
     */
    public function formatRequestMessage(
        string $action,
        User $sender,
        string $message,
        ?string $context = null,
        ?string $priority = null,
        ?string $taskId = null,
    ): string {
        $header = "[Agent Request from {$sender->name} | Pattern: {$action}";

        if ($priority && $action === 'delegate') {
            $header .= " | Priority: {$priority}";
        }
        if ($taskId && $action === 'delegate') {
            $header .= " | Task: {$taskId}";
        }

        $header .= ']';

        if ($action === 'notify') {
            $header = "[Agent Notification from {$sender->name}]";
        }

        $parts = [$header];

        if ($context) {
            $parts[] = "\nContext: {$context}";
        }

        $parts[] = "\n{$message}";

        return implode('', $parts);
    }

    /**
     * Format a delegation result message.
     */
    public function formatResultMessage(User $agent, string $subtaskId, string $result): string
    {
        return "[Delegation Result from {$agent->name} | {$subtaskId}]\n{$result}";
    }

    // Depth tracking for synchronous ask nesting

    public static function incrementDepth(): void
    {
        self::$depth++;
    }

    public static function decrementDepth(): void
    {
        self::$depth = max(0, self::$depth - 1);
    }

    public static function currentDepth(): int
    {
        return self::$depth;
    }

    public static function resetDepth(): void
    {
        self::$depth = 0;
    }
}

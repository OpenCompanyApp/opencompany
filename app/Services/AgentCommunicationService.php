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
     * Get or create a DM channel between two agents.
     * Returns the channel_id string.
     */
    public function getOrCreateDmChannel(User $agentA, User $agentB): string
    {
        // Check for existing DM in either user order (backwards compatible)
        $existingDm = DirectMessage::where(function ($query) use ($agentA, $agentB) {
            $query->where('user1_id', $agentA->id)->where('user2_id', $agentB->id);
        })->orWhere(function ($query) use ($agentA, $agentB) {
            $query->where('user1_id', $agentB->id)->where('user2_id', $agentA->id);
        })->first();

        if ($existingDm) {
            return $existingDm->channel_id;
        }

        try {
            $channel = Channel::create([
                'id' => Str::uuid()->toString(),
                'name' => 'DM',
                'type' => 'dm',
                'workspace_id' => $agentA->workspace_id ?? workspace()->id,
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
        } catch (\Illuminate\Database\QueryException $e) {
            // Race condition: another worker created the DM first
            $dm = DirectMessage::where(function ($query) use ($agentA, $agentB) {
                $query->where('user1_id', $agentA->id)->where('user2_id', $agentB->id);
            })->orWhere(function ($query) use ($agentA, $agentB) {
                $query->where('user1_id', $agentB->id)->where('user2_id', $agentA->id);
            })->first();

            if ($dm) {
                return $dm->channel_id;
            }

            throw $e;
        }
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

        if ($priority && in_array($action, ['delegate', 'ask'])) {
            $header .= " | Priority: {$priority}";
        }
        if ($taskId && in_array($action, ['delegate', 'ask'])) {
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

}

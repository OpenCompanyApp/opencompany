<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Chat\AddMessageReaction;
use App\Agents\Tools\Chat\DeleteMessage;
use App\Agents\Tools\Chat\EditMessage;
use App\Agents\Tools\Chat\JoinExternalChannel;
use App\Agents\Tools\Chat\LeaveExternalChannel;
use App\Agents\Tools\Chat\ListChannels;
use App\Agents\Tools\Chat\ListExternalChannels;
use App\Agents\Tools\Chat\PinMessage;
use App\Agents\Tools\Chat\ReadPinnedMessages;
use App\Agents\Tools\Chat\ReadRecentMessages;
use App\Agents\Tools\Chat\ReadThread;
use App\Agents\Tools\Chat\RemoveMessageReaction;
use App\Agents\Tools\Chat\SearchMessages;
use App\Agents\Tools\Chat\SendChannelMessage;
use App\Models\User;
use App\Services\AgentPermissionService;

class ChatToolProvider implements BuiltInToolProvider
{
    public function __construct(
        private AgentPermissionService $permissionService,
    ) {}

    public function groupName(): string
    {
        return 'chat';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'send, read, list, edit, delete, pin, react, search, external',
            'description' => 'Channel messaging (incl. external: Telegram, Slack). Files and images in messages auto-forward to external platforms.',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:chat-circle';
    }

    public function tools(): array
    {
        return [
            'send_channel_message' => [
                'class' => SendChannelMessage::class,
                'type' => 'write',
                'name' => 'Send Channel Message',
                'description' => 'Send a message to any workspace channel, including external channels (Telegram, Slack). Messages to external channels are automatically delivered to the external platform.',
                'icon' => 'ph:chat-circle',
            ],
            'read_recent_messages' => [
                'class' => ReadRecentMessages::class,
                'type' => 'read',
                'name' => 'Read Recent Messages',
                'description' => 'Read the most recent messages from a channel.',
                'icon' => 'ph:chat-dots',
            ],
            'read_thread' => [
                'class' => ReadThread::class,
                'type' => 'read',
                'name' => 'Read Thread',
                'description' => 'Read a message thread (replies) by thread root message ID.',
                'icon' => 'ph:chat-dots',
            ],
            'read_pinned_messages' => [
                'class' => ReadPinnedMessages::class,
                'type' => 'read',
                'name' => 'Read Pinned Messages',
                'description' => 'Read pinned messages from a channel.',
                'icon' => 'ph:push-pin',
            ],
            'list_channels' => [
                'class' => ListChannels::class,
                'type' => 'read',
                'name' => 'List Channels',
                'description' => 'List channels you have access to, including external (Telegram, Slack) channels.',
                'icon' => 'ph:list-bullets',
            ],
            'edit_message' => [
                'class' => EditMessage::class,
                'type' => 'write',
                'name' => 'Edit Message',
                'description' => 'Edit a message\'s content.',
                'icon' => 'ph:pencil-simple',
            ],
            'delete_message' => [
                'class' => DeleteMessage::class,
                'type' => 'write',
                'name' => 'Delete Message',
                'description' => 'Delete a message.',
                'icon' => 'ph:trash',
            ],
            'pin_message' => [
                'class' => PinMessage::class,
                'type' => 'write',
                'name' => 'Pin Message',
                'description' => 'Toggle pin status on a message.',
                'icon' => 'ph:push-pin',
            ],
            'add_message_reaction' => [
                'class' => AddMessageReaction::class,
                'type' => 'write',
                'name' => 'Add Message Reaction',
                'description' => 'Add an emoji reaction to a message.',
                'icon' => 'ph:smiley',
            ],
            'remove_message_reaction' => [
                'class' => RemoveMessageReaction::class,
                'type' => 'write',
                'name' => 'Remove Message Reaction',
                'description' => 'Remove an emoji reaction from a message.',
                'icon' => 'ph:smiley',
            ],
            'search_messages' => [
                'class' => SearchMessages::class,
                'type' => 'read',
                'name' => 'Search Messages',
                'description' => 'Search messages across channels by keyword, with optional channel and author filtering.',
                'icon' => 'ph:magnifying-glass',
            ],
            'list_external_channels' => [
                'class' => ListExternalChannels::class,
                'type' => 'read',
                'name' => 'List External Channels',
                'description' => 'List available channels on external platforms (Telegram, Discord).',
                'icon' => 'ph:globe',
            ],
            'join_external_channel' => [
                'class' => JoinExternalChannel::class,
                'type' => 'write',
                'name' => 'Join External Channel',
                'description' => 'Join an external platform channel to start receiving messages.',
                'icon' => 'ph:sign-in',
            ],
            'leave_external_channel' => [
                'class' => LeaveExternalChannel::class,
                'type' => 'write',
                'name' => 'Leave External Channel',
                'description' => 'Leave an external platform channel.',
                'icon' => 'ph:sign-out',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): \Laravel\Ai\Contracts\Tool
    {
        return new $class($agent, $this->permissionService);
    }
}

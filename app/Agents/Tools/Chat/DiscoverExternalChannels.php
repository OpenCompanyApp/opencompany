<?php

namespace App\Agents\Tools\Chat;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DiscoverExternalChannels implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Discover and manage external platform channels (Telegram, Discord). List monitored channels, join new ones, or leave channels.';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'] ?? 'list';
            $provider = $request['provider'] ?? null;

            return match ($action) {
                'list' => $this->listChannels($provider),
                'join' => $this->joinChannel($request),
                'leave' => $this->leaveChannel($request),
                default => "Error: Unknown action '{$action}'. Use 'list', 'join', or 'leave'.",
            };
        } catch (\Throwable $e) {
            return "Error discovering channels: {$e->getMessage()}";
        }
    }

    private function listChannels(?string $provider): string
    {
        $query = Channel::where('type', 'external');

        if ($provider) {
            $query->where('external_provider', $provider);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $scope = $provider ? " for {$provider}" : '';
            return "No external channels found{$scope}.";
        }

        $lines = ['External channels:'];

        foreach ($channels as $channel) {
            $memberCount = ChannelMember::where('channel_id', $channel->id)->count();
            $messageCount = Message::where('channel_id', $channel->id)->count();
            $lastMessage = Message::where('channel_id', $channel->id)
                ->orderBy('created_at', 'desc')
                ->first();
            $lastActivity = $lastMessage
                ? $lastMessage->created_at->diffForHumans()
                : 'no activity';

            $isMember = ChannelMember::where('channel_id', $channel->id)
                ->where('user_id', $this->agent->id)
                ->exists();
            $status = $isMember ? 'joined' : 'not joined';

            $lines[] = "- {$channel->name} ({$channel->external_provider})"
                . " | id: {$channel->id}"
                . " | {$status}"
                . " | {$memberCount} members, {$messageCount} messages"
                . " | last: {$lastActivity}";
        }

        return implode("\n", $lines);
    }

    private function joinChannel(Request $request): string
    {
        $channelId = $request['channelId'] ?? null;
        if (!$channelId) {
            return "Error: 'channelId' is required for the 'join' action.";
        }

        $channel = Channel::find($channelId);
        if (!$channel || $channel->type !== 'external') {
            return "Error: External channel '{$channelId}' not found.";
        }

        $existing = ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $this->agent->id)
            ->first();

        if ($existing) {
            return "You are already a member of '{$channel->name}'.";
        }

        ChannelMember::create([
            'id' => Str::uuid()->toString(),
            'channel_id' => $channel->id,
            'user_id' => $this->agent->id,
            'joined_at' => now(),
        ]);

        return "Joined '{$channel->name}' ({$channel->external_provider}).";
    }

    private function leaveChannel(Request $request): string
    {
        $channelId = $request['channelId'] ?? null;
        if (!$channelId) {
            return "Error: 'channelId' is required for the 'leave' action.";
        }

        $channel = Channel::find($channelId);
        if (!$channel || $channel->type !== 'external') {
            return "Error: External channel '{$channelId}' not found.";
        }

        $membership = ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $this->agent->id)
            ->first();

        if (!$membership) {
            return "You are not a member of '{$channel->name}'.";
        }

        $membership->delete();

        return "Left '{$channel->name}' ({$channel->external_provider}).";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("The action: 'list' (default), 'join', or 'leave'.")
                ->required(),
            'provider' => $schema
                ->string()
                ->description("Filter by provider: 'telegram' or 'discord'. Optional for list action."),
            'channelId' => $schema
                ->string()
                ->description('The UUID of the channel to join or leave. Required for join/leave actions.'),
        ];
    }
}

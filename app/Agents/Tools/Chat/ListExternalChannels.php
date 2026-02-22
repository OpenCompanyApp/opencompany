<?php

namespace App\Agents\Tools\Chat;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListExternalChannels implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'List external platform channels (Telegram, Discord) with membership status, member count, and activity.';
    }

    public function handle(Request $request): string
    {
        try {
            $provider = $request['provider'] ?? null;

            $query = Channel::forWorkspace()->where('type', 'external');

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
        } catch (\Throwable $e) {
            return "Error discovering channels: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'provider' => $schema
                ->string()
                ->description("Filter by provider: 'telegram' or 'discord'. Optional."),
        ];
    }
}

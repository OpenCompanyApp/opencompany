<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AddChannelMember implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Add a user to a channel.';
    }

    public function handle(Request $request): string
    {
        try {
            $channelId = $request['channelId'] ?? null;
            $userId = $request['userId'] ?? null;

            if (!$channelId || !$userId) {
                return 'Required: channelId, userId.';
            }

            $channel = Channel::forWorkspace()->find($channelId);
            if (!$channel) {
                return "Channel not found: {$channelId}";
            }

            $user = User::find($userId);
            if (!$user) {
                return "User not found: {$userId}";
            }

            $exists = ChannelMember::where('channel_id', $channelId)->where('user_id', $userId)->exists();
            if ($exists) {
                return "{$user->name} is already a member of #{$channel->name}.";
            }

            ChannelMember::create([
                'channel_id' => $channelId,
                'user_id' => $userId,
            ]);

            return "Added {$user->name} to #{$channel->name}.";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channelId' => $schema
                ->string()
                ->description('Channel UUID.')
                ->required(),
            'userId' => $schema
                ->string()
                ->description('User UUID.')
                ->required(),
        ];
    }
}

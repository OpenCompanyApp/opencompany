<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RemoveChannelMember implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Remove a user from a channel.';
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

            $deleted = ChannelMember::where('channel_id', $channelId)->where('user_id', $userId)->delete();

            if (!$deleted) {
                return "{$user->name} is not a member of #{$channel->name}.";
            }

            return "Removed {$user->name} from #{$channel->name}.";
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

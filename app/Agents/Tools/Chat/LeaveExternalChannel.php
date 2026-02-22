<?php

namespace App\Agents\Tools\Chat;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class LeaveExternalChannel implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Leave an external platform channel (Telegram, Discord) to stop receiving messages from it.';
    }

    public function handle(Request $request): string
    {
        try {
            $channelId = $request['channelId'];

            $channel = Channel::forWorkspace()->find($channelId);
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
        } catch (\Throwable $e) {
            return "Error discovering channels: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channelId' => $schema
                ->string()
                ->description('The UUID of the external channel to leave.')
                ->required(),
        ];
    }
}

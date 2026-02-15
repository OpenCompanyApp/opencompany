<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ManageChannel implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create channels and manage channel membership.';
    }

    public function handle(Request $request): string
    {
        try {
            return match ($request['action']) {
                'create' => $this->create($request),
                'add_member' => $this->addMember($request),
                'remove_member' => $this->removeMember($request),
                default => "Unknown action: {$request['action']}. Use: create, add_member, remove_member",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function create(Request $request): string
    {
        $name = $request['name'] ?? null;
        if (!$name) {
            return 'name is required.';
        }

        $type = $request['type'] ?? 'public';
        if (!in_array($type, ['public', 'private', 'dm'])) {
            return "Invalid channel type: {$type}. Use: public, private, dm.";
        }

        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'type' => $type,
            'description' => $request['description'] ?? null,
            'is_ephemeral' => false,
            'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
        ]);

        // Add the creating agent as a member
        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id' => $this->agent->id,
        ]);

        // Add additional members
        if (!empty($request['memberIds'])) {
            $memberIds = array_map('trim', explode(',', $request['memberIds']));
            foreach ($memberIds as $memberId) {
                if ($memberId === $this->agent->id) {
                    continue; // Already added
                }
                $user = User::find($memberId);
                if ($user) {
                    ChannelMember::create([
                        'channel_id' => $channel->id,
                        'user_id' => $memberId,
                    ]);
                }
            }
        }

        $memberCount = ChannelMember::where('channel_id', $channel->id)->count();
        return "Channel created: #{$channel->name} (ID: {$channel->id}, type: {$type}, {$memberCount} member(s))";
    }

    private function addMember(Request $request): string
    {
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
    }

    private function removeMember(Request $request): string
    {
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
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("Action: 'create', 'add_member', 'remove_member'")
                ->required(),
            'name' => $schema
                ->string()
                ->description('Channel name. Required for create.'),
            'type' => $schema
                ->string()
                ->description("Channel type: 'public' (default), 'private', or 'dm'."),
            'description' => $schema
                ->string()
                ->description('Channel description. Optional for create.'),
            'memberIds' => $schema
                ->string()
                ->description('Comma-separated user UUIDs to add as initial members on create.'),
            'channelId' => $schema
                ->string()
                ->description('Channel UUID. Required for add_member and remove_member.'),
            'userId' => $schema
                ->string()
                ->description('User UUID. Required for add_member and remove_member.'),
        ];
    }
}

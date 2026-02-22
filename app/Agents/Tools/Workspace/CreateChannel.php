<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateChannel implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create a new channel and optionally add initial members.';
    }

    public function handle(Request $request): string
    {
        try {
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
                        continue;
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
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->description('Channel name.')
                ->required(),
            'type' => $schema
                ->string()
                ->description("Channel type: 'public' (default), 'private', or 'dm'."),
            'description' => $schema
                ->string()
                ->description('Channel description.'),
            'memberIds' => $schema
                ->string()
                ->description('Comma-separated user UUIDs to add as initial members.'),
        ];
    }
}

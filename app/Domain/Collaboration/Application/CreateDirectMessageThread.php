<?php

namespace App\Domain\Collaboration\Application;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Creates the default private conversation between a human and an agent.
 *
 * Collaboration owns channel and DM records. Agent provisioning calls this
 * action so controller code does not need to know the channel side effects.
 */
class CreateDirectMessageThread
{
    public function handle(Workspace $workspace, User $human, User $agent): Channel
    {
        $channel = Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'name' => 'DM: '.($human->name ?? 'User').' <-> '.$agent->name,
            'type' => 'dm',
            'is_ephemeral' => false,
        ]);

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id' => $human->id,
        ]);

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id' => $agent->id,
        ]);

        DirectMessage::create([
            'id' => Str::uuid()->toString(),
            'user1_id' => $human->id,
            'user2_id' => $agent->id,
            'channel_id' => $channel->id,
        ]);

        return $channel;
    }
}

<?php

namespace App\Domain\Collaboration\Application;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates the complete initial record set for one workspace.
 *
 * The workspace, owner membership, system automation agent, general channel,
 * and channel membership form one tenant bootstrap invariant. This service
 * owns their database transaction so callers cannot leave a partially created
 * workspace when any individual insert fails. Authentication, validation,
 * sessions, registration events, and avatar generation remain caller-owned.
 */
class ProvisionWorkspace
{
    public function create(
        User $owner,
        string $name,
        string $slug,
        string $icon = 'ph:buildings',
        string $color = 'neutral',
    ): Workspace {
        if ($owner->isAgent()) {
            throw new \InvalidArgumentException('An agent cannot own a workspace.');
        }

        return DB::transaction(function () use ($owner, $name, $slug, $icon, $color): Workspace {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => $slug,
                'icon' => $icon,
                'color' => $color,
                'owner_id' => $owner->id,
            ]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $owner->id,
                'role' => 'admin',
            ]);

            User::create([
                'id' => 'sys-'.Str::random(8),
                'name' => 'Automation',
                'type' => 'agent',
                'agent_type' => 'system',
                'status' => 'idle',
                'presence' => 'online',
                'workspace_id' => $workspace->id,
            ]);

            $general = Channel::create([
                'id' => Str::uuid()->toString(),
                'name' => 'general',
                'type' => 'public',
                'description' => 'General discussion and announcements',
                'creator_id' => $owner->id,
                'workspace_id' => $workspace->id,
            ]);

            ChannelMember::create([
                'channel_id' => $general->id,
                'user_id' => $owner->id,
                'role' => 'admin',
                'joined_at' => now(),
            ]);

            return $workspace;
        });
    }
}

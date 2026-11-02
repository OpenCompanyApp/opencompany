<?php

use App\Models\ChannelMember;
use Illuminate\Support\Facades\Broadcast;

/**
 * Workspace channel authorization. Humans belong to a workspace only through
 * workspace_members; `users.workspace_id` is the agent ownership column and
 * must never admit a human (it can hold a stale/primary workspace).
 */
$isWorkspaceParticipant = function ($user, string $workspaceId): bool {
    if (($user->type ?? null) === 'agent') {
        return $user->workspace_id === $workspaceId;
    }

    return $user->workspaces()->where('workspaces.id', $workspaceId)->exists();
};

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Chat channel - only members can access
Broadcast::channel('chat.{channelId}', function ($user, $channelId) {
    return ChannelMember::where('channel_id', $channelId)
        ->where('user_id', $user->id)
        ->exists();
});

// Workspace-scoped online presence channel
Broadcast::channel('workspace.{workspaceId}.online', function ($user, $workspaceId) use ($isWorkspaceParticipant) {
    if (! $isWorkspaceParticipant($user, (string) $workspaceId)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'presence' => $user->presence ?? 'online',
    ];
});

// Workspace-scoped task updates
Broadcast::channel('workspace.{workspaceId}.tasks', function ($user, $workspaceId) use ($isWorkspaceParticipant) {
    return $isWorkspaceParticipant($user, (string) $workspaceId);
});

// Workspace-scoped agent status updates
Broadcast::channel('workspace.{workspaceId}.agents', function ($user, $workspaceId) use ($isWorkspaceParticipant) {
    return $isWorkspaceParticipant($user, (string) $workspaceId);
});

// Workspace-scoped approval notifications
Broadcast::channel('workspace.{workspaceId}.approvals', function ($user, $workspaceId) use ($isWorkspaceParticipant) {
    return $isWorkspaceParticipant($user, (string) $workspaceId);
});

// User notifications - only the owner
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});

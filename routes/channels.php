<?php

use App\Models\ChannelMember;
use Illuminate\Support\Facades\Broadcast;

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
Broadcast::channel('workspace.{workspaceId}.online', function ($user, $workspaceId) {
    if (! $user->workspaces()->where('workspaces.id', $workspaceId)->exists()
        && $user->workspace_id !== $workspaceId) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'presence' => $user->presence ?? 'online',
    ];
});

// Workspace-scoped task updates
Broadcast::channel('workspace.{workspaceId}.tasks', function ($user, $workspaceId) {
    return $user->workspaces()->where('workspaces.id', $workspaceId)->exists()
        || $user->workspace_id === $workspaceId;
});

// Workspace-scoped agent status updates
Broadcast::channel('workspace.{workspaceId}.agents', function ($user, $workspaceId) {
    return $user->workspaces()->where('workspaces.id', $workspaceId)->exists()
        || $user->workspace_id === $workspaceId;
});

// Workspace-scoped approval notifications
Broadcast::channel('workspace.{workspaceId}.approvals', function ($user, $workspaceId) {
    return $user->workspaces()->where('workspaces.id', $workspaceId)->exists()
        || $user->workspace_id === $workspaceId;
});

// User notifications - only the owner
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});

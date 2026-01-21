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

// Online presence channel - all authenticated users
Broadcast::channel('online', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'presence' => $user->presence ?? 'online',
    ];
});

// User notifications - only the owner
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});

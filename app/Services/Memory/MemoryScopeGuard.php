<?php

namespace App\Services\Memory;

use App\Models\Channel;
use App\Models\User;

class MemoryScopeGuard
{
    /**
     * Check whether memory tools (save_memory, recall_memory) can be used
     * by the given agent in the given channel context.
     *
     * Scope modes:
     * - dm_only (default): Only allow in private channels (dm, agent)
     * - all: Allow in all channel types
     * - none: Disable memory tools everywhere
     */
    public function canUseMemoryTools(User $agent, ?string $channelId): bool
    {
        $scopeMode = config('memory.scope.default', 'dm_only');

        if ($scopeMode === 'none') {
            return false;
        }

        if ($scopeMode === 'all') {
            return true;
        }

        // dm_only (default): check channel type
        if (!$channelId) {
            return false;
        }

        $channel = Channel::find($channelId);
        if (!$channel) {
            return false;
        }

        return in_array($channel->type, ['dm', 'agent', 'external']);
    }

    /**
     * Return a user-friendly denial message for the agent.
     */
    public function denialMessage(string $toolName): string
    {
        return "Memory tools are not available in group channels. "
            . "Use {$toolName} in a private (DM) channel instead.";
    }
}

<?php

namespace App\Services\Chat;

use App\Models\IntegrationSetting;
use App\Models\Workspace;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\State\CacheStateAdapter;

/**
 * Builds a Chatogrator runtime for one OpenCompany workspace.
 *
 * Chat runtime instances are cached per workspace because adapter registration
 * and callback wiring are relatively expensive. Call forget() after integration
 * settings change so the next inbound/outbound message sees fresh credentials.
 */
class ChatManager
{
    /** @var array<string, Chat> */
    private array $instances = [];

    public function forWorkspace(Workspace|string $workspace): Chat
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;

        if (isset($this->instances[$workspaceId])) {
            return $this->instances[$workspaceId];
        }

        $chat = Chat::make("ws:{$workspaceId}")
            ->state(new CacheStateAdapter("chatogrator:{$workspaceId}"))
            ->logger('single');

        // Only enabled workspace settings become live adapters. This prevents a
        // configured-but-disabled external bot from receiving outbound syncs.
        $chatIntegrationIds = array_keys(config('chat_integrations', []));

        $settings = IntegrationSetting::where('workspace_id', $workspaceId)
            ->where('enabled', true)
            ->whereIn('integration_id', $chatIntegrationIds)
            ->get();

        foreach ($settings as $setting) {
            $adapter = ChatAdapterFactory::create($setting);
            if ($adapter) {
                $chat->adapter($setting->integration_id, $adapter);

                // Some settings use OpenCompany IDs such as github_chat while
                // Chatogrator reports canonical adapter names such as github.
                // Register both so inbound and outbound paths resolve the same
                // adapter instance regardless of which name they start with.
                if ($adapter->name() !== $setting->integration_id) {
                    $chat->adapter($adapter->name(), $adapter);
                }
            }
        }

        // Register handlers
        $bridge = app(ChatBridge::class);

        $chat->onNewMention(fn ($thread, $message) => $bridge->handleInbound($thread, $message, $workspaceId));
        $chat->onSubscribedMessage(fn ($thread, $message) => $bridge->handleInbound($thread, $message, $workspaceId));
        $chat->onAction(fn ($event) => $bridge->handleAction($event, $workspaceId));
        $chat->onSlashCommand(fn ($event) => $bridge->handleSlashCommand($event, $workspaceId));

        $this->instances[$workspaceId] = $chat;

        return $chat;
    }

    public function forCurrentWorkspace(): Chat
    {
        return $this->forWorkspace(workspace());
    }

    /**
     * Clear cached instance for a workspace (e.g. after config change).
     */
    public function forget(string $workspaceId): void
    {
        unset($this->instances[$workspaceId]);
    }
}

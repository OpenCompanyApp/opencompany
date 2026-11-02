<?php

namespace App\Services\Integrations;

use App\Domain\Ai\Codex\CodexTokenStore;
use App\Models\IntegrationSetting;
use App\Models\McpServer;
use App\Services\Integrations\Data\IntegrationDescriptor;

/**
 * Produces the workspace integration directory consumed by settings and tools.
 *
 * Merges the app-owned static entries (AI providers, Telegram, chat adapters)
 * with enabled workspace MCP servers. The integration package system was
 * deleted (2026-11-02); external systems become Connections in the new model
 * (docs/planning/connections-events-routines.md).
 */
class IntegrationDirectory
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $settings = IntegrationSetting::forWorkspace()->default()->get()->keyBy('integration_id');
        $available = IntegrationSetting::getAvailableIntegrations();

        $integrations = [];

        // Static entries are the app-owned AI providers and chat channels.
        foreach ($available as $id => $info) {
            $integrations[] = $this->staticDescriptor($id, $info, $settings->get($id))->toArray();
        }

        // MCP servers are workspace records. Model/tool
        // access still flows through the same integration-style catalog because
        // permissions are managed at the app/tool slug layer.
        foreach (McpServer::forWorkspace()->where('enabled', true)->get() as $server) {
            $integrations[] = (new IntegrationDescriptor([
                'id' => IntegrationIdentity::forMcp($server->slug),
                'configId' => 'mcp_'.$server->slug,
                'entryType' => IntegrationIdentity::MCP,
                'source' => 'workspace_mcp',
                'cardKey' => IntegrationIdentity::forMcp($server->slug),
                'name' => $server->name,
                'description' => $server->description ?? 'Remote MCP server',
                'icon' => $server->icon,
                'enabled' => true,
                'configured' => true,
                'configurable' => false,
                'type' => 'mcp',
                'badge' => 'mcp',
                'mcpServerId' => $server->id,
                'toolCount' => count($server->discovered_tools ?? []),
                'url' => $server->url,
            ]))->toArray();
        }

        return $integrations;
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function staticDescriptor(string $id, array $info, ?IntegrationSetting $setting): IntegrationDescriptor
    {
        if ($id === 'codex') {
            $codexToken = CodexTokenStore::current();

            return new IntegrationDescriptor([
                'id' => IntegrationIdentity::forAiProvider($id),
                'configId' => $id,
                'entryType' => IntegrationIdentity::AI_PROVIDER,
                'source' => 'static_ai_provider',
                'cardKey' => IntegrationIdentity::forAiProvider($id),
                'name' => $info['name'],
                'description' => $info['description'],
                'icon' => $info['icon'],
                'category' => $info['category'] ?? null,
                'models' => $info['models'] ?? null,
                'enabled' => $codexToken !== null && ! $codexToken->isExpired(),
                'configured' => $codexToken !== null,
                'configurable' => false,
                'authType' => 'oauth',
            ]);
        }

        $configFields = $info['config_fields'] ?? null;
        $entryType = ($info['category'] ?? null) === 'ai-models'
            ? IntegrationIdentity::AI_PROVIDER
            : (($info['category'] ?? null) === 'chat-platforms' ? IntegrationIdentity::CHAT : IntegrationIdentity::STATIC);
        $cardId = match ($entryType) {
            IntegrationIdentity::AI_PROVIDER => IntegrationIdentity::forAiProvider($id),
            IntegrationIdentity::CHAT => IntegrationIdentity::forChat($id),
            default => IntegrationIdentity::forStatic($id),
        };

        return new IntegrationDescriptor([
            'id' => $cardId,
            'configId' => $id,
            'entryType' => $entryType,
            'source' => $entryType === IntegrationIdentity::AI_PROVIDER ? 'static_ai_provider' : 'static_config',
            'cardKey' => $cardId,
            'name' => $info['name'],
            'description' => $info['description'],
            'icon' => $info['icon'],
            'category' => $info['category'] ?? null,
            'models' => $info['models'] ?? null,
            'defaultUrl' => $info['default_url'] ?? null,
            'enabled' => $setting ? $setting->enabled : false,
            'configured' => $setting ? $setting->hasValidConfig() : false,
            'configurable' => $configFields !== null,
            'configSchema' => $configFields ? IntegrationConfigResolver::buildStaticConfigSchema($configFields) : null,
        ]);
    }
}

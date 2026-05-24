<?php

namespace App\Services\Integrations;

use App\Domain\Ai\Codex\CodexTokenStore;
use App\Models\IntegrationSetting;
use App\Models\McpServer;
use App\Services\Integrations\Data\IntegrationDescriptor;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

/**
 * Produces the workspace integration directory consumed by settings and tools.
 *
 * This class intentionally merges three sources: legacy static app metadata,
 * package-provided configurable integrations, and enabled MCP servers. It should
 * describe OpenCompany availability/configuration state without copying package
 * tool schemas or hardcoding provider behavior that belongs in sibling packages.
 */
class IntegrationDirectory
{
    public function __construct(
        private ToolProviderRegistry $registry,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $settings = IntegrationSetting::forWorkspace()->default()->get()->keyBy('integration_id');
        $available = IntegrationSetting::getAvailableIntegrations();

        $integrations = [];

        // Static entries are the legacy OpenCompany catalog. Keep them until
        // each provider has moved to package metadata, but do not add new
        // provider-specific behavior here unless it is app-specific.
        foreach ($available as $id => $info) {
            $integrations[] = $this->staticDescriptor($id, $info, $settings->get($id))->toArray();
        }

        // Configurable package integrations expose their own settings schema.
        // OpenCompany only overlays workspace enablement/configuration state.
        foreach ($this->registry->all() as $provider) {
            if (! $provider instanceof ConfigurableIntegration) {
                continue;
            }

            $integrations[] = $this->configurableProviderDescriptor(
                $provider,
                $settings->get($provider->appName())
            )->toArray();
        }

        // Some packages are integration-like tool bundles with no user config
        // schema. They still need directory entries so agents and settings can
        // reason about availability.
        foreach ($this->registry->all() as $provider) {
            if ($provider instanceof ConfigurableIntegration || ! $provider->isIntegration()) {
                continue;
            }

            $meta = $provider->appMeta();
            /** @var IntegrationSetting|null $setting */
            $setting = $settings->get($provider->appName());
            $integrations[] = (new IntegrationDescriptor([
                'id' => $provider->appName(),
                'name' => $meta['description'] ?? $provider->appName(),
                'description' => $meta['label'] ?? '',
                'icon' => $meta['icon'] ?? 'ph:puzzle-piece',
                'logo' => $meta['logo'] ?? null,
                'category' => 'built-in-tools',
                'badge' => 'built-in',
                'enabled' => $setting?->enabled ?? false,
                'configured' => true,
                'configurable' => false,
            ]))->toArray();
        }

        // MCP servers are workspace records, not package providers. Model/tool
        // access still flows through the same integration-style catalog because
        // permissions are managed at the app/tool slug layer.
        foreach (McpServer::forWorkspace()->where('enabled', true)->get() as $server) {
            $integrations[] = (new IntegrationDescriptor([
                'id' => 'mcp_'.$server->slug,
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
                'id' => $id,
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

        return new IntegrationDescriptor([
            'id' => $id,
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

    private function configurableProviderDescriptor(ConfigurableIntegration $provider, ?IntegrationSetting $setting): IntegrationDescriptor
    {
        $meta = $provider->integrationMeta();

        return new IntegrationDescriptor([
            'id' => $provider->appName(),
            'name' => $meta['name'],
            'description' => $meta['description'],
            'icon' => $meta['icon'] ?? 'ph:puzzle-piece',
            'logo' => $meta['logo'] ?? null,
            'category' => $meta['category'] ?? 'data',
            'badge' => $meta['badge'] ?? null,
            'docsUrl' => $meta['docs_url'] ?? null,
            'enabled' => $setting ? $setting->enabled : false,
            'configured' => $setting ? $setting->hasValidConfig() : false,
            'configurable' => true,
            'configSchema' => ConfigSchemaNormalizer::normalize($provider->configSchema()),
        ]);
    }
}

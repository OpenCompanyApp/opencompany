<?php

namespace App\Agents\Tools;

use App\Agents\Tools\Providers\BuiltInToolProvider;
use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\AgentPermissionService;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class ToolRegistry
{
    /**
     * App groups that remain as direct AI tools.
     * Everything else is accessible only via lua_exec (code-first approach).
     */
    public const DIRECT_TOOL_GROUPS = ['tasks', 'system', 'agents', 'memory', 'lua'];

    /**
     * Apps that are external integrations (can be toggled per agent).
     * Built-in apps are always available.
     */
    public const INTEGRATION_APPS = ['telegram'];

    /** @var array<string, BuiltInToolProvider> */
    private array $builtInProviders = [];

    /** @var array<string, array<string, mixed>>|null Cached merged tool map */
    private ?array $effectiveToolMap = null;

    /** @var array<string, array<string, mixed>>|null Cached merged app groups */
    private ?array $effectiveAppGroups = null;

    /** @var string[]|null Cached merged integration apps */
    private ?array $effectiveIntegrationApps = null;

    /** @var array<string, string>|null Cached merged app icons */
    private ?array $effectiveAppIcons = null;

    /** @var array<string, string>|null Cached merged integration logos */
    private ?array $effectiveIntegrationLogos = null;

    private ?string $currentChannelId = null;

    private ?string $currentTaskId = null;

    public function __construct(
        private AgentPermissionService $permissionService,
        private ToolProviderRegistry $providerRegistry,
    ) {}

    /**
     * Register a built-in tool provider.
     */
    public function registerBuiltIn(BuiltInToolProvider $provider): void
    {
        $this->builtInProviders[$provider->groupName()] = $provider;
        // Invalidate caches
        $this->effectiveToolMap = null;
        $this->effectiveAppGroups = null;
        $this->effectiveAppIcons = null;
    }

    /**
     * Set the channel context for memory tool scope checks.
     */
    public function setChannelContext(?string $channelId): void
    {
        $this->currentChannelId = $channelId;
    }

    /**
     * Set the task context for tools that need the current task ID.
     */
    public function setTaskContext(?string $taskId): void
    {
        $this->currentTaskId = $taskId;
    }

    // ─── Effective (merged built-in + external) accessors ──────────────────

    /** @return array<string, array<string, mixed>> */
    private function getEffectiveToolMap(): array
    {
        if ($this->effectiveToolMap === null) {
            $this->effectiveToolMap = [];

            // Built-in providers
            foreach ($this->builtInProviders as $provider) {
                foreach ($provider->tools() as $slug => $meta) {
                    $this->effectiveToolMap[$slug] = $meta;
                }
            }

            // External integration providers
            foreach ($this->providerRegistry->all() as $provider) {
                foreach ($provider->tools() as $slug => $meta) {
                    $this->effectiveToolMap[$slug] = $meta;
                }
            }
        }

        return $this->effectiveToolMap;
    }

    /** @return array<string, array<string, mixed>> */
    private function getEffectiveAppGroups(): array
    {
        if ($this->effectiveAppGroups === null) {
            $this->effectiveAppGroups = [];

            // Built-in providers
            foreach ($this->builtInProviders as $provider) {
                $meta = $provider->groupMeta();
                $this->effectiveAppGroups[$provider->groupName()] = [
                    'tools' => array_keys($provider->tools()),
                    'label' => $meta['label'],
                    'description' => $meta['description'],
                ];
            }

            // External integration providers
            foreach ($this->providerRegistry->all() as $provider) {
                $meta = $provider->appMeta();
                $this->effectiveAppGroups[$provider->appName()] = [
                    'tools' => array_keys($provider->tools()),
                    'label' => $meta['label'],
                    'description' => $meta['description'],
                ];
            }
        }

        return $this->effectiveAppGroups;
    }

    /** @return string[] */
    public function getEffectiveIntegrationApps(): array
    {
        if ($this->effectiveIntegrationApps === null) {
            $this->effectiveIntegrationApps = self::INTEGRATION_APPS;
            foreach ($this->providerRegistry->all() as $provider) {
                if ($provider->isIntegration() && ! in_array($provider->appName(), $this->effectiveIntegrationApps)) {
                    $this->effectiveIntegrationApps[] = $provider->appName();
                }
            }
        }

        return $this->effectiveIntegrationApps;
    }

    /** @return array<string, string> */
    private function getEffectiveAppIcons(): array
    {
        if ($this->effectiveAppIcons === null) {
            $this->effectiveAppIcons = [];

            // Built-in providers
            foreach ($this->builtInProviders as $provider) {
                $this->effectiveAppIcons[$provider->groupName()] = $provider->groupIcon();
            }

            // External integration providers
            foreach ($this->providerRegistry->all() as $provider) {
                $meta = $provider->appMeta();
                $this->effectiveAppIcons[$provider->appName()] = $meta['icon'];
            }
        }

        return $this->effectiveAppIcons;
    }

    /** @return array<string, string> */
    private function getEffectiveIntegrationLogos(): array
    {
        if ($this->effectiveIntegrationLogos === null) {
            $this->effectiveIntegrationLogos = [];
            foreach ($this->providerRegistry->all() as $provider) {
                $meta = $provider->appMeta();
                if (isset($meta['logo'])) {
                    $this->effectiveIntegrationLogos[$provider->appName()] = $meta['logo'];
                }
            }
        }

        return $this->effectiveIntegrationLogos;
    }

    // ─── Tool lookup helpers ──────────────────────────────────────────────

    /**
     * Look up a tool's icon by its class basename (e.g. "SendChannelMessage").
     */
    public function getIconByClassName(string $className): string
    {
        foreach ($this->getEffectiveToolMap() as $meta) {
            if (class_basename($meta['class']) === $className) {
                return $meta['icon'] ?? 'ph:wrench';
            }
        }

        return 'ph:wrench';
    }

    /**
     * Look up a tool's icon and display name by its slug (e.g. "get_document").
     *
     * @return array{icon: string, name: string}
     */
    public function getToolMetaBySlug(string $slug): array
    {
        $map = $this->getEffectiveToolMap();
        $meta = $map[$slug] ?? null;

        return [
            'icon' => $meta['icon'] ?? 'ph:wrench',
            'name' => $meta['name'] ?? $slug,
        ];
    }

    // ─── Tool filtering and instantiation ──────────────────────────────────

    /**
     * Get tools available for a given agent, filtered by permissions.
     * Tools requiring approval are wrapped in ApprovalWrappedTool.
     *
     * @return array<\Laravel\Ai\Contracts\Tool>
     */
    public function getToolsForAgent(User $agent): array
    {
        $enabledIntegrations = $this->permissionService->getEnabledIntegrations($agent);
        $appLookup = $this->buildAppLookup();
        $tools = [];

        foreach ($this->getEffectiveToolMap() as $slug => $meta) {
            $app = $appLookup[$slug] ?? 'other';

            // Code-first: only direct tool groups are registered as AI tools
            if (! in_array($app, self::DIRECT_TOOL_GROUPS)) {
                continue;
            }

            // Skip tools from disabled integrations
            if (in_array($app, $this->getEffectiveIntegrationApps()) && ! in_array($app, $enabledIntegrations)) {
                continue;
            }

            $result = $this->permissionService->resolveToolPermission(
                $agent, $slug, $meta['type']
            );

            if (! $result['allowed']) {
                continue;
            }

            $tool = $this->instantiateTool($meta['class'], $agent, $slug);

            if ($result['requires_approval']) {
                $tool = new ApprovalWrappedTool($tool, $agent, $slug, $meta);
            }

            $tools[] = $tool;
        }

        return $tools;
    }

    /**
     * Get the slugs of tools available to a given agent.
     *
     * @return string[]
     */
    public function getToolSlugsForAgent(User $agent): array
    {
        $enabledIntegrations = $this->permissionService->getEnabledIntegrations($agent);
        $appLookup = $this->buildAppLookup();
        $slugs = [];

        foreach ($this->getEffectiveToolMap() as $slug => $meta) {
            $app = $appLookup[$slug] ?? 'other';

            // Code-first: only direct tool groups are registered as AI tools
            if (! in_array($app, self::DIRECT_TOOL_GROUPS)) {
                continue;
            }

            if (in_array($app, $this->getEffectiveIntegrationApps()) && ! in_array($app, $enabledIntegrations)) {
                continue;
            }

            $result = $this->permissionService->resolveToolPermission($agent, $slug, $meta['type']);
            if ($result['allowed']) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * Get metadata for ALL tools with permission status for a specific agent.
     * Used by the API to populate the capabilities tab.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllToolsMeta(User $agent): array
    {
        $appLookup = $this->buildAppLookup();
        $enabledIntegrations = $this->permissionService->getEnabledIntegrations($agent);

        $result = [];

        foreach ($this->getEffectiveToolMap() as $slug => $meta) {
            $app = $appLookup[$slug] ?? 'other';
            $isIntegration = in_array($app, $this->getEffectiveIntegrationApps());
            $integrationEnabled = ! $isIntegration || in_array($app, $enabledIntegrations);

            // Skip tools from non-workspace-enabled integrations entirely
            if ($isIntegration && ! $integrationEnabled) {
                continue;
            }

            $permission = $this->permissionService->resolveToolPermission(
                $agent, $slug, $meta['type']
            );

            $result[] = [
                'id' => $slug,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'type' => $meta['type'],
                'icon' => $meta['icon'],
                'app' => $app,
                'isIntegration' => $isIntegration,
                'enabled' => $permission['allowed'],
                'requiresApproval' => $permission['requires_approval'],
            ];
        }

        return $result;
    }

    /**
     * Get app group metadata for the frontend capabilities UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAppGroupsMeta(): array
    {
        $result = [];
        foreach ($this->getEffectiveAppGroups() as $name => $group) {
            $meta = [
                'name' => $name,
                'description' => $group['description'],
                'icon' => $this->getEffectiveAppIcons()[$name] ?? 'ph:puzzle-piece',
                'isIntegration' => in_array($name, $this->getEffectiveIntegrationApps()),
            ];

            if (isset($this->getEffectiveIntegrationLogos()[$name])) {
                $meta['logo'] = $this->getEffectiveIntegrationLogos()[$name];
            }

            $result[] = $meta;
        }

        return $result;
    }

    /**
     * Get metadata for integration apps only (for the UI integrations section).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getIntegrationAppsMeta(): array
    {
        return array_values(array_filter(
            $this->getAppGroupsMeta(),
            fn ($app) => $app['isIntegration']
        ));
    }

    /**
     * Get a full tool catalog with schemas, grouped by app.
     * Used by the Developer Tools page — not permission-filtered.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getToolCatalog(User $agent): array
    {
        $factory = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;
        $builtIn = [];
        $integrations = [];

        foreach ($this->getEffectiveAppGroups() as $appName => $group) {
            $isIntegration = in_array($appName, $this->getEffectiveIntegrationApps());
            $tools = [];

            foreach ($group['tools'] as $slug) {
                $meta = $this->getEffectiveToolMap()[$slug] ?? null;
                if (! $meta) {
                    continue;
                }

                $toolData = [
                    'slug' => $slug,
                    'name' => $meta['name'],
                    'description' => $meta['description'],
                    'type' => $meta['type'],
                    'icon' => $meta['icon'],
                    'parameters' => [],
                ];

                // Extract schema by instantiating the tool
                try {
                    $tool = $this->instantiateTool($meta['class'], $agent, $slug);
                    $toolData['fullDescription'] = $tool->description();

                    if ($tool instanceof \OpenCompany\IntegrationCore\Contracts\Tool) {
                        // New-style tool: parameters() returns plain array
                        foreach ($tool->parameters() as $paramName => $paramDef) {
                            $param = [
                                'name' => $paramName,
                                'type' => $paramDef['type'] ?? 'string',
                                'required' => $paramDef['required'] ?? false,
                            ];
                            if (! empty($paramDef['description'])) {
                                $param['description'] = $paramDef['description'];
                            }
                            if (! empty($paramDef['enum'])) {
                                $param['enum'] = $paramDef['enum'];
                            }
                            if (isset($paramDef['items'])) {
                                $param['items'] = $paramDef['items'];
                            }
                            if (isset($paramDef['properties'])) {
                                $param['properties'] = $paramDef['properties'];
                            }
                            $toolData['parameters'][] = $param;
                        }
                    } else {
                        // Legacy Laravel\Ai\Contracts\Tool: extract from JsonSchema
                        $schema = $tool->schema($factory);
                        if (! empty($schema)) {
                            $objectType = $factory->object($schema);
                            $serialized = $objectType->toArray();
                            $requiredParams = $serialized['required'] ?? [];

                            foreach ($serialized['properties'] ?? [] as $paramName => $paramSchema) {
                                $param = [
                                    'name' => $paramName,
                                    'type' => $paramSchema['type'] ?? 'string',
                                    'required' => in_array($paramName, $requiredParams),
                                ];
                                if (! empty($paramSchema['description'])) {
                                    $param['description'] = $paramSchema['description'];
                                }
                                if (! empty($paramSchema['enum'])) {
                                    $param['enum'] = $paramSchema['enum'];
                                }
                                if (isset($paramSchema['items'])) {
                                    $param['items'] = $paramSchema['items'];
                                }
                                if (isset($paramSchema['properties'])) {
                                    $param['properties'] = $paramSchema['properties'];
                                }
                                $toolData['parameters'][] = $param;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Tool failed to instantiate — include metadata without schema
                }

                $tools[] = $toolData;
            }

            if (empty($tools)) {
                continue;
            }

            $entry = [
                'name' => $appName,
                'description' => $group['description'],
                'icon' => $this->getEffectiveAppIcons()[$appName] ?? 'ph:puzzle-piece',
                'isIntegration' => $isIntegration,
                'tools' => $tools,
            ];

            if (isset($this->getEffectiveIntegrationLogos()[$appName])) {
                $entry['logo'] = $this->getEffectiveIntegrationLogos()[$appName];
            }

            if ($isIntegration) {
                $integrations[] = $entry;
            } else {
                $builtIn[] = $entry;
            }
        }

        return array_merge($integrations, $builtIn);
    }

    /**
     * Instantiate a specific tool by slug (for post-approval execution).
     */
    public function instantiateToolBySlug(string $slug, User $agent): \OpenCompany\IntegrationCore\Contracts\Tool|\Laravel\Ai\Contracts\Tool|null
    {
        if (! isset($this->getEffectiveToolMap()[$slug])) {
            return null;
        }

        return $this->instantiateTool($this->getEffectiveToolMap()[$slug]['class'], $agent, $slug);
    }

    /**
     * Build a compact app catalog string for the system prompt.
     * Code-first: only direct tool groups are listed as tools.
     * Everything else is accessible through lua_exec.
     */
    public function getAppCatalog(User $agent): string
    {
        $lines = [];

        // Section 1: Direct tools (the handful of AI-callable tools)
        $lines[] = '## Tools';
        $lines[] = '';

        foreach (self::DIRECT_TOOL_GROUPS as $appName) {
            $group = $this->getEffectiveAppGroups()[$appName] ?? null;
            if (! $group) {
                continue;
            }

            // Check which tools in this app the agent has access to
            $hasApproval = false;
            $hasAllowed = false;

            foreach ($group['tools'] as $slug) {
                if (! isset($this->getEffectiveToolMap()[$slug])) {
                    continue;
                }
                $result = $this->permissionService->resolveToolPermission(
                    $agent, $slug, $this->getEffectiveToolMap()[$slug]['type']
                );
                if ($result['allowed']) {
                    $hasAllowed = true;
                    if ($result['requires_approval']) {
                        $hasApproval = true;
                    }
                }
            }

            if (! $hasAllowed) {
                continue;
            }

            $approval = $hasApproval ? ' *' : '';
            $lines[] = "{$appName}: {$group['label']} — {$group['description']}{$approval}";
        }

        // Section 2: Lua API (everything else, accessible via lua_exec)
        $lines[] = '';
        $lines[] = '## Lua API (code-first)';
        $lines[] = '';
        $lines[] = 'All data operations and integrations are available through lua_exec.';
        $lines[] = 'Always call lua_read_doc(namespace) before writing code to look up function names and parameters.';
        $lines[] = '';
        $lines[] = app(\App\Services\LuaApiDocGenerator::class)->getNamespaceSummary($agent);

        return implode("\n", $lines);
    }

    /**
     * Instantiate a tool class via its provider.
     */
    private function instantiateTool(string $class, User $agent, string $slug = ''): \OpenCompany\IntegrationCore\Contracts\Tool|\Laravel\Ai\Contracts\Tool
    {
        $context = [
            'channel_id' => $this->currentChannelId,
            'task_id' => $this->currentTaskId,
            'tool_registry' => $this,
        ];

        // Check external integration providers first
        foreach ($this->providerRegistry->all() as $provider) {
            foreach ($provider->tools() as $toolSlug => $meta) {
                if ($meta['class'] === $class && ($slug === '' || $toolSlug === $slug)) {
                    return $provider->createTool($class, [
                        'agent' => $agent,
                        'timezone' => AppSetting::getValue('org_timezone', 'UTC'),
                        'tool_slug' => $toolSlug,
                    ]);
                }
            }
        }

        // Check built-in providers
        $appLookup = $this->buildAppLookup();
        $appName = $appLookup[$slug] ?? null;

        if ($appName !== null && isset($this->builtInProviders[$appName])) {
            return $this->builtInProviders[$appName]->createTool($class, $agent, $context);
        }

        // Fallback: search all built-in providers by class
        foreach ($this->builtInProviders as $provider) {
            foreach ($provider->tools() as $toolSlug => $meta) {
                if ($meta['class'] === $class) {
                    return $provider->createTool($class, $agent, $context);
                }
            }
        }

        throw new \RuntimeException("Unknown tool class: {$class}");
    }

    /**
     * Build reverse lookup: tool slug → app name.
     *
     * @return array<string, string>
     */
    private function buildAppLookup(): array
    {
        $lookup = [];
        foreach ($this->getEffectiveAppGroups() as $appName => $group) {
            foreach ($group['tools'] as $slug) {
                $lookup[$slug] = $appName;
            }
        }

        return $lookup;
    }
}

<?php

namespace App\Agents\Tools;

use App\Agents\Tools\Calendar\ManageCalendarEvent;
use App\Agents\Tools\Calendar\QueryCalendar;
use App\Agents\Tools\Charts\RenderSvg;
use App\Agents\Tools\Chat\DiscoverExternalChannels;
use App\Agents\Tools\Chat\ListChannels;
use App\Agents\Tools\Chat\ManageMessage;
use App\Agents\Tools\Chat\ReadChannel;
use App\Agents\Tools\Chat\SearchMessages;
use App\Agents\Tools\Chat\SendChannelMessage;
use App\Agents\Tools\Docs\CommentOnDocument;
use App\Agents\Tools\Docs\ManageDocument;
use App\Agents\Tools\Docs\SearchDocuments;
use App\Agents\Tools\Memory\RecallMemory;
use App\Agents\Tools\Memory\SaveMemory;
use App\Services\Memory\DocumentIndexingService;
use App\Services\Memory\MemoryScopeGuard;
use App\Agents\Tools\Lists\ManageListItem;
use App\Agents\Tools\Lists\ManageListStatus;
use App\Agents\Tools\Lists\QueryListItems;
use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Agents\Tools\System\GetToolInfo;
use App\Agents\Tools\System\Wait;
use App\Agents\Tools\System\WaitForApproval;
use App\Agents\Tools\Tables\ManageTable;
use App\Agents\Tools\Tables\ManageTableRows;
use App\Agents\Tools\Tables\QueryTable;
use App\Agents\Tools\Tasks\CreateTaskStep;
use App\Agents\Tools\Tasks\UpdateCurrentTask;
use App\Agents\Tools\Agents\ContactAgent;
use App\Agents\Tools\Workspace\ManageAgent;
use App\Agents\Tools\Workspace\ManageAgentPermissions;
use App\Agents\Tools\Workspace\ManageAutomation;
use App\Agents\Tools\Workspace\ManageChannel;
use App\Agents\Tools\Workspace\ManageIntegration;
use App\Agents\Tools\Workspace\ManageMcpServer;
use App\Agents\Tools\Workspace\QueryWorkspace;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\AgentPermissionService;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class ToolRegistry
{
    /**
     * App groups for compact system prompt catalog.
     * Maps app name => [tool slugs] + description.
     */
    public const APP_GROUPS = [
        // System & task management
        'tasks' => [
            'tools' => ['update_current_task', 'create_task_step'],
            'label' => 'update, log_step',
            'description' => 'Work progress tracking',
        ],
        'system' => [
            'tools' => ['wait', 'wait_for_approval'],
            'label' => 'wait, wait_for_approval',
            'description' => 'Execution control',
        ],

        // Internal apps
        'agents' => [
            'tools' => ['contact_agent'],
            'label' => 'ask, delegate, notify',
            'description' => 'Inter-agent communication',
        ],
        'memory' => [
            'tools' => ['save_memory', 'recall_memory'],
            'label' => 'save, recall',
            'description' => 'Long-term agent memory',
        ],
        'chat' => [
            'tools' => ['send_channel_message', 'read_channel', 'list_channels', 'manage_message', 'search_messages', 'discover_external_channels'],
            'label' => 'send, read, list, manage, search, discover',
            'description' => 'Channel messaging (incl. external: Telegram, Slack)',
        ],
        'docs' => [
            'tools' => ['search_documents', 'manage_document', 'comment_on_document'],
            'label' => 'search, manage, comment',
            'description' => 'Document workspace',
        ],
        'tables' => [
            'tools' => ['query_table', 'manage_table', 'manage_table_rows'],
            'label' => 'query, manage, rows',
            'description' => 'Structured data tables',
        ],
        'calendar' => [
            'tools' => ['query_calendar', 'manage_calendar_event'],
            'label' => 'query, manage',
            'description' => 'Events and scheduling',
        ],
        'lists' => [
            'tools' => ['query_list_items', 'manage_list_item', 'manage_list_status'],
            'label' => 'query, manage items, manage statuses',
            'description' => 'Kanban board items and workflow statuses',
        ],
        'workspace' => [
            'tools' => ['query_workspace', 'manage_agent', 'manage_agent_permissions', 'manage_integration', 'manage_mcp_server', 'manage_channel', 'manage_automation'],
            'label' => 'query, agents, permissions, integrations, mcp_servers, channels, automation',
            'description' => 'Workspace management',
        ],

        // Integrations & utilities
        'svg' => [
            'tools' => ['render_svg'],
            'label' => 'render',
            'description' => 'Render SVG markup to PNG images',
        ],
    ];

    /**
     * Apps that are external integrations (can be toggled per agent).
     * Built-in apps are always available.
     */
    public const INTEGRATION_APPS = [];

    /**
     * Icons for each app group.
     */
    private const APP_ICONS = [
        'agents' => 'ph:users-three',
        'memory' => 'ph:brain',
        'chat' => 'ph:chat-circle',
        'docs' => 'ph:file-text',
        'tables' => 'ph:table',
        'calendar' => 'ph:calendar',
        'lists' => 'ph:kanban',
        'tasks' => 'ph:list-checks',
        'svg' => 'ph:file-svg',
        'system' => 'ph:gear',
        'workspace' => 'ph:gear-six',
    ];

    /**
     * Colored brand logos for integration apps (Iconify logo set).
     */
    private const INTEGRATION_LOGOS = [];

    /**
     * Registry of all available tools with metadata.
     */
    private const TOOL_MAP = [
        // Agents
        'contact_agent' => [
            'class' => ContactAgent::class,
            'type' => 'write',
            'name' => 'Contact Agent',
            'description' => 'Send a message, ask a question, or delegate work to another agent.',
            'icon' => 'ph:users-three',
        ],
        // Memory
        'save_memory' => [
            'class' => SaveMemory::class,
            'type' => 'write',
            'name' => 'Save Memory',
            'description' => 'Save a durable memory that persists across conversations.',
            'icon' => 'ph:brain',
        ],
        'recall_memory' => [
            'class' => RecallMemory::class,
            'type' => 'read',
            'name' => 'Recall Memory',
            'description' => 'Search long-term memory for past information and learnings.',
            'icon' => 'ph:brain',
        ],
        // Chat
        'send_channel_message' => [
            'class' => SendChannelMessage::class,
            'type' => 'write',
            'name' => 'Send Channel Message',
            'description' => 'Send a message to any workspace channel, including external channels (Telegram, Slack). Messages to external channels are automatically delivered to the external platform.',
            'icon' => 'ph:chat-circle',
        ],
        'read_channel' => [
            'class' => ReadChannel::class,
            'type' => 'read',
            'name' => 'Read Channel',
            'description' => 'Read recent messages, threads, or pinned messages from a channel.',
            'icon' => 'ph:chat-dots',
        ],
        'list_channels' => [
            'class' => ListChannels::class,
            'type' => 'read',
            'name' => 'List Channels',
            'description' => 'List channels you have access to, including external (Telegram, Slack) channels.',
            'icon' => 'ph:list-bullets',
        ],
        'manage_message' => [
            'class' => ManageMessage::class,
            'type' => 'write',
            'name' => 'Manage Message',
            'description' => 'Edit, delete, pin, or add/remove reactions on a message. Syncs to external platforms.',
            'icon' => 'ph:chat-circle-dots',
        ],
        'search_messages' => [
            'class' => SearchMessages::class,
            'type' => 'read',
            'name' => 'Search Messages',
            'description' => 'Search messages across channels by keyword, with optional channel and author filtering.',
            'icon' => 'ph:magnifying-glass',
        ],
        'discover_external_channels' => [
            'class' => DiscoverExternalChannels::class,
            'type' => 'read',
            'name' => 'Discover External Channels',
            'description' => 'List, join, or leave external platform channels (Telegram, Discord).',
            'icon' => 'ph:globe',
        ],
        // Docs
        'search_documents' => [
            'class' => SearchDocuments::class,
            'type' => 'read',
            'name' => 'Search Documents',
            'description' => 'Search workspace documents by keyword or semantic similarity.',
            'icon' => 'ph:magnifying-glass',
        ],
        'manage_document' => [
            'class' => ManageDocument::class,
            'type' => 'write',
            'name' => 'Manage Document',
            'description' => 'Create, update, or delete a document or folder.',
            'icon' => 'ph:file-text',
        ],
        'comment_on_document' => [
            'class' => CommentOnDocument::class,
            'type' => 'write',
            'name' => 'Comment on Document',
            'description' => 'Add, resolve, or delete comments on a document.',
            'icon' => 'ph:chat-teardrop-text',
        ],
        // Tables
        'query_table' => [
            'class' => QueryTable::class,
            'type' => 'read',
            'name' => 'Query Table',
            'description' => 'List tables, get schema, or search and filter rows.',
            'icon' => 'ph:table',
        ],
        'manage_table' => [
            'class' => ManageTable::class,
            'type' => 'write',
            'name' => 'Manage Table',
            'description' => 'Create, update, or delete tables and columns.',
            'icon' => 'ph:table',
        ],
        'manage_table_rows' => [
            'class' => ManageTableRows::class,
            'type' => 'write',
            'name' => 'Manage Table Rows',
            'description' => 'Add, update, or delete rows in a data table.',
            'icon' => 'ph:rows',
        ],
        // Calendar
        'query_calendar' => [
            'class' => QueryCalendar::class,
            'type' => 'read',
            'name' => 'Query Calendar',
            'description' => 'List events by date range or view event details.',
            'icon' => 'ph:calendar',
        ],
        'manage_calendar_event' => [
            'class' => ManageCalendarEvent::class,
            'type' => 'write',
            'name' => 'Manage Calendar Event',
            'description' => 'Create, update, or delete calendar events with attendees.',
            'icon' => 'ph:calendar-plus',
        ],
        // Lists
        'query_list_items' => [
            'class' => QueryListItems::class,
            'type' => 'read',
            'name' => 'Query List Items',
            'description' => 'Browse, filter, and search kanban board items.',
            'icon' => 'ph:kanban',
        ],
        'manage_list_item' => [
            'class' => ManageListItem::class,
            'type' => 'write',
            'name' => 'Manage List Item',
            'description' => 'Create, update, or delete list items and their comments.',
            'icon' => 'ph:list-plus',
        ],
        'manage_list_status' => [
            'class' => ManageListStatus::class,
            'type' => 'write',
            'name' => 'Manage List Status',
            'description' => 'Create, update, or delete list statuses (workflow columns).',
            'icon' => 'ph:columns',
        ],
        // Tasks
        'update_current_task' => [
            'class' => UpdateCurrentTask::class,
            'type' => 'write',
            'name' => 'Update Current Task',
            'description' => 'Update your running task: rename, add/update steps, or set final status.',
            'icon' => 'ph:list-checks',
        ],
        'create_task_step' => [
            'class' => CreateTaskStep::class,
            'type' => 'write',
            'name' => 'Create Task Step',
            'description' => 'Log a progress step on a task you are working on.',
            'icon' => 'ph:list-checks',
        ],
        // SVG
        'render_svg' => [
            'class' => RenderSvg::class,
            'type' => 'write',
            'name' => 'Render SVG',
            'description' => 'Convert SVG markup to a PNG image.',
            'icon' => 'ph:file-svg',
        ],
        // Meta
        'get_tool_info' => [
            'class' => GetToolInfo::class,
            'type' => 'read',
            'name' => 'Get Tool Info',
            'description' => 'Get detailed parameter info for a tool or app before using it.',
            'icon' => 'ph:info',
        ],
        // Control Flow
        'wait_for_approval' => [
            'class' => WaitForApproval::class,
            'type' => 'write',
            'name' => 'Wait For Approval',
            'description' => 'Pause execution until a pending approval is decided.',
            'icon' => 'ph:pause-circle',
        ],
        'wait' => [
            'class' => Wait::class,
            'type' => 'write',
            'name' => 'Wait',
            'description' => 'Suspend execution for a specified number of minutes, then auto-resume.',
            'icon' => 'ph:timer',
        ],
        // Workspace Management
        'query_workspace' => [
            'class' => QueryWorkspace::class,
            'type' => 'read',
            'name' => 'Query Workspace',
            'description' => 'List agents, view agent details, permissions, integrations, and available models.',
            'icon' => 'ph:magnifying-glass',
        ],
        'manage_agent' => [
            'class' => ManageAgent::class,
            'type' => 'write',
            'name' => 'Manage Agent',
            'description' => 'Create, update, or delete agents and their identity files.',
            'icon' => 'ph:robot',
        ],
        'manage_agent_permissions' => [
            'class' => ManageAgentPermissions::class,
            'type' => 'write',
            'name' => 'Manage Agent Permissions',
            'description' => 'Update tool, channel, folder, and integration permissions for agents.',
            'icon' => 'ph:shield-check',
        ],
        'manage_integration' => [
            'class' => ManageIntegration::class,
            'type' => 'write',
            'name' => 'Manage Integration',
            'description' => 'Configure API keys, test connections, and set up webhooks for integrations.',
            'icon' => 'ph:plugs-connected',
        ],
        'manage_mcp_server' => [
            'class' => ManageMcpServer::class,
            'type' => 'write',
            'name' => 'Manage MCP Server',
            'description' => 'Add, configure, test, and remove remote MCP servers to expose their tools to agents.',
            'icon' => 'ph:plugs-connected',
        ],
        'manage_channel' => [
            'class' => ManageChannel::class,
            'type' => 'write',
            'name' => 'Manage Channel',
            'description' => 'Create channels and manage channel membership.',
            'icon' => 'ph:hash',
        ],
        'manage_automation' => [
            'class' => ManageAutomation::class,
            'type' => 'write',
            'name' => 'Manage Automation',
            'description' => 'Create and manage automation rules, list templates, and scheduled automations (cron jobs for agents).',
            'icon' => 'ph:lightning',
        ],
    ];

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

    public function __construct(
        private AgentPermissionService $permissionService,
        private ToolProviderRegistry $providerRegistry,
    ) {}

    /**
     * Set the channel context for memory tool scope checks.
     */
    public function setChannelContext(?string $channelId): void
    {
        $this->currentChannelId = $channelId;
    }

    // ─── Effective (merged static + dynamic) accessors ──────────────────

    /** @return array<string, array<string, mixed>> */
    private function getEffectiveToolMap(): array
    {
        if ($this->effectiveToolMap === null) {
            $this->effectiveToolMap = self::TOOL_MAP;
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
            $this->effectiveAppGroups = self::APP_GROUPS;
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
                if ($provider->isIntegration() && !in_array($provider->appName(), $this->effectiveIntegrationApps)) {
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
            $this->effectiveAppIcons = self::APP_ICONS;
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
            $this->effectiveIntegrationLogos = self::INTEGRATION_LOGOS;
            foreach ($this->providerRegistry->all() as $provider) {
                $meta = $provider->appMeta();
                if (isset($meta['logo'])) {
                    $this->effectiveIntegrationLogos[$provider->appName()] = $meta['logo'];
                }
            }
        }
        return $this->effectiveIntegrationLogos;
    }

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
            // Skip tools from disabled integrations
            $app = $appLookup[$slug] ?? 'other';
            if (in_array($app, $this->getEffectiveIntegrationApps()) && !in_array($app, $enabledIntegrations)) {
                continue;
            }

            $result = $this->permissionService->resolveToolPermission(
                $agent, $slug, $meta['type']
            );

            if (!$result['allowed']) {
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
            if (in_array($app, $this->getEffectiveIntegrationApps()) && !in_array($app, $enabledIntegrations)) {
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
            $integrationEnabled = !$isIntegration || in_array($app, $enabledIntegrations);

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
                'enabled' => $permission['allowed'] && $integrationEnabled,
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
     * Instantiate a specific tool by slug (for post-approval execution).
     */
    public function instantiateToolBySlug(string $slug, User $agent): ?\Laravel\Ai\Contracts\Tool
    {
        if (!isset($this->getEffectiveToolMap()[$slug])) {
            return null;
        }

        return $this->instantiateTool($this->getEffectiveToolMap()[$slug]['class'], $agent, $slug);
    }

    /**
     * Build a compact app catalog string for the system prompt.
     * Only includes apps that have at least one allowed tool for the agent.
     */
    public function getAppCatalog(User $agent): string
    {
        $enabledIntegrations = $this->permissionService->getEnabledIntegrations($agent);

        // Display order grouped by priority, null = section separator
        // Start with known apps, then append any dynamic provider apps
        $knownIntegrations = ['svg'];
        $providerApps = array_keys($this->providerRegistry->all());
        $integrations = array_unique(array_merge($providerApps, $knownIntegrations));

        $displayOrder = array_merge(
            // System & task management
            ['tasks', 'system', null],
            // Internal apps
            ['agents', 'memory', 'chat', 'docs', 'tables', 'calendar', 'lists', 'workspace', null],
            // Integrations & utilities (dynamic + static)
            $integrations,
        );

        $lines = [];
        $lastWasSeparator = true; // avoid leading blank line

        foreach ($displayOrder as $appName) {
            if ($appName === null) {
                if (!$lastWasSeparator) {
                    $lines[] = '';
                }
                $lastWasSeparator = true;
                continue;
            }

            $group = $this->getEffectiveAppGroups()[$appName] ?? null;
            if (!$group) {
                continue;
            }

            // Skip disabled integrations
            if (in_array($appName, $this->getEffectiveIntegrationApps()) && !in_array($appName, $enabledIntegrations)) {
                continue;
            }

            // Check which tools in this app the agent has access to
            $allowedSlugs = [];
            $hasApproval = false;

            foreach ($group['tools'] as $slug) {
                if (!isset($this->getEffectiveToolMap()[$slug])) {
                    continue;
                }
                $result = $this->permissionService->resolveToolPermission(
                    $agent, $slug, $this->getEffectiveToolMap()[$slug]['type']
                );
                if ($result['allowed']) {
                    $allowedSlugs[] = $slug;
                    if ($result['requires_approval']) {
                        $hasApproval = true;
                    }
                }
            }

            if (empty($allowedSlugs)) {
                continue;
            }

            $approval = $hasApproval ? ' *' : '';
            $lines[] = "{$appName}: {$group['label']} — {$group['description']}{$approval}";
            $lastWasSeparator = false;
        }

        // Trim trailing blank lines
        while (!empty($lines) && $lines[count($lines) - 1] === '') {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }

    /**
     * Get detailed info about a tool or app for the get_tool_info meta-tool.
     */
    public function getToolDetail(string $query, User $agent): string
    {
        // Check if query matches an app name
        $queryLower = strtolower(trim($query));
        if (isset($this->getEffectiveAppGroups()[$queryLower])) {
            return $this->getAppDetail($queryLower, $agent);
        }

        // Check if query matches a tool slug
        if (isset($this->getEffectiveToolMap()[$queryLower])) {
            return $this->getSingleToolDetail($queryLower, $agent);
        }

        // Fuzzy search — try to find a matching tool
        foreach ($this->getEffectiveToolMap() as $slug => $meta) {
            if (str_contains($slug, $queryLower) || str_contains(strtolower($meta['name']), $queryLower)) {
                return $this->getSingleToolDetail($slug, $agent);
            }
        }

        // List available apps
        $apps = implode(', ', array_keys($this->getEffectiveAppGroups()));
        $tools = implode(', ', array_keys($this->getEffectiveToolMap()));
        return "Not found: '{$query}'. Available apps: {$apps}. Or query a specific tool: {$tools}";
    }

    private function getAppDetail(string $appName, User $agent): string
    {
        $group = $this->getEffectiveAppGroups()[$appName];
        $lines = ["App: {$appName} — {$group['description']}", '', 'Tools:'];

        foreach ($group['tools'] as $slug) {
            if (!isset($this->getEffectiveToolMap()[$slug])) {
                continue;
            }
            $meta = $this->getEffectiveToolMap()[$slug];
            $perm = $this->permissionService->resolveToolPermission($agent, $slug, $meta['type']);

            if (!$perm['allowed']) {
                continue;
            }

            $approval = $perm['requires_approval'] ? ' (requires approval)' : '';
            $tool = $this->instantiateTool($meta['class'], $agent, $slug);
            $schema = $tool->schema(new \Illuminate\JsonSchema\JsonSchemaTypeFactory);

            $params = [];
            foreach ($schema as $paramName => $paramSchema) {
                $arr = method_exists($paramSchema, 'toArray') ? $paramSchema->toArray() : [];
                $required = !empty($arr['required']) ? ', required' : '';
                $desc = $arr['description'] ?? '';
                $params[] = "      {$paramName}{$required}" . ($desc ? " — {$desc}" : '');
            }

            $lines[] = "  {$slug} ({$meta['type']}){$approval} — {$meta['description']}";
            if (!empty($params)) {
                $lines[] = "    Params:";
                $lines = array_merge($lines, $params);
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function getSingleToolDetail(string $slug, User $agent): string
    {
        $meta = $this->getEffectiveToolMap()[$slug];
        $perm = $this->permissionService->resolveToolPermission($agent, $slug, $meta['type']);

        if (!$perm['allowed']) {
            return "You do not have permission to use '{$slug}'.";
        }

        $approval = $perm['requires_approval'] ? 'yes' : 'no';
        $tool = $this->instantiateTool($meta['class'], $agent, $slug);
        $schema = $tool->schema(new \Illuminate\JsonSchema\JsonSchemaTypeFactory);

        $lines = [
            "{$slug} — {$meta['description']}",
            "Type: {$meta['type']} | Requires approval: {$approval}",
            '',
            'Parameters:',
        ];

        foreach ($schema as $paramName => $paramSchema) {
            $arr = method_exists($paramSchema, 'toArray') ? $paramSchema->toArray() : [];
            $required = !empty($arr['required']) ? ' (required)' : '';
            $desc = $arr['description'] ?? '';
            $lines[] = "  {$paramName}{$required}" . ($desc ? " — {$desc}" : '');
        }

        return implode("\n", $lines);
    }

    /**
     * Instantiate a tool class with the appropriate constructor arguments.
     */
    private function instantiateTool(string $class, User $agent, string $slug = ''): \Laravel\Ai\Contracts\Tool
    {
        // Check if this tool belongs to an external provider
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

        // Built-in tools
        return match ($class) {
            // Inter-agent communication
            ContactAgent::class => new ContactAgent($agent, $this->permissionService, app(\App\Services\AgentCommunicationService::class)),
            // Memory tools
            SaveMemory::class => new SaveMemory($agent, app(AgentDocumentService::class), app(DocumentIndexingService::class), app(MemoryScopeGuard::class), $this->currentChannelId),
            RecallMemory::class => new RecallMemory($agent, app(DocumentIndexingService::class), app(AgentDocumentService::class), app(MemoryScopeGuard::class), $this->currentChannelId),
            // Tools needing permission service (channel/folder scoping)
            SendChannelMessage::class => new SendChannelMessage($agent, $this->permissionService),
            ReadChannel::class => new ReadChannel($agent, $this->permissionService),
            ManageMessage::class => new ManageMessage($agent, $this->permissionService),
            SearchMessages::class => new SearchMessages($agent, $this->permissionService),
            DiscoverExternalChannels::class => new DiscoverExternalChannels($agent, $this->permissionService),
            SearchDocuments::class => new SearchDocuments($agent, $this->permissionService, app(DocumentIndexingService::class)),
            ManageDocument::class => new ManageDocument($agent, $this->permissionService),
            CommentOnDocument::class => new CommentOnDocument($agent, $this->permissionService),
            ListChannels::class => new ListChannels($agent, $this->permissionService),
            GetToolInfo::class => new GetToolInfo($agent, $this),
            // Tools needing only agent
            QueryTable::class => new QueryTable(),
            ManageTable::class => new ManageTable($agent),
            ManageTableRows::class => new ManageTableRows($agent),
            QueryCalendar::class => new QueryCalendar(),
            ManageCalendarEvent::class => new ManageCalendarEvent($agent),
            QueryListItems::class => new QueryListItems(),
            ManageListItem::class => new ManageListItem($agent),
            ManageListStatus::class => new ManageListStatus(),
            UpdateCurrentTask::class => new UpdateCurrentTask($agent),
            CreateTaskStep::class => new CreateTaskStep($agent),
            RenderSvg::class => new RenderSvg(),
            WaitForApproval::class => new WaitForApproval($agent),
            Wait::class => new Wait($agent),
            // Workspace Management
            QueryWorkspace::class => new QueryWorkspace($this->permissionService, $this),
            ManageAgent::class => new ManageAgent($agent, app(AgentDocumentService::class), app(\App\Services\AgentAvatarService::class)),
            ManageAgentPermissions::class => new ManageAgentPermissions($agent, $this->permissionService),
            ManageIntegration::class => new ManageIntegration(),
            ManageMcpServer::class => new ManageMcpServer(),
            ManageChannel::class => new ManageChannel($agent),
            ManageAutomation::class => new ManageAutomation($agent),
            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
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

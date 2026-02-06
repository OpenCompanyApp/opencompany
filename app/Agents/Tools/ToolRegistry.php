<?php

namespace App\Agents\Tools;

use App\Agents\Tools\Plausible\PlausibleCreateGoal;
use App\Agents\Tools\Plausible\PlausibleCreateSite;
use App\Agents\Tools\Plausible\PlausibleDeleteGoal;
use App\Agents\Tools\Plausible\PlausibleDeleteSite;
use App\Agents\Tools\Plausible\PlausibleListGoals;
use App\Agents\Tools\Plausible\PlausibleListSites;
use App\Agents\Tools\Plausible\PlausibleQueryStats;
use App\Agents\Tools\Plausible\PlausibleRealtimeVisitors;
use App\Models\User;
use App\Services\AgentPermissionService;

class ToolRegistry
{
    /**
     * App groups for compact system prompt catalog.
     * Maps app name => [tool slugs] + description.
     */
    public const APP_GROUPS = [
        'chat' => [
            'tools' => ['send_channel_message', 'read_channel', 'list_channels', 'manage_message'],
            'label' => 'send, read, list, manage',
            'description' => 'Channel messaging and operations',
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
            'tools' => ['query_list_items', 'manage_list_item'],
            'label' => 'query, manage',
            'description' => 'Kanban board items',
        ],
        'tasks' => [
            'tools' => ['update_current_task', 'create_task_step'],
            'label' => 'update, log_step',
            'description' => 'Work progress tracking',
        ],
        'jpgraph_charts' => [
            'tools' => ['create_jpgraph_chart'],
            'label' => 'create',
            'description' => 'Generate chart images (JpGraph)',
        ],
        'svg' => [
            'tools' => ['render_svg'],
            'label' => 'render',
            'description' => 'Render SVG markup to PNG images',
        ],
        'telegram' => [
            'tools' => ['send_telegram_notification'],
            'label' => 'notify',
            'description' => 'External notifications',
        ],
        'plausible' => [
            'tools' => ['plausible_query_stats', 'plausible_realtime_visitors', 'plausible_list_sites', 'plausible_create_site', 'plausible_delete_site', 'plausible_list_goals', 'plausible_create_goal', 'plausible_delete_goal'],
            'label' => 'query, realtime, sites, goals',
            'description' => 'Website analytics',
        ],
        'system' => [
            'tools' => ['wait', 'wait_for_approval'],
            'label' => 'wait, wait_for_approval',
            'description' => 'Execution control',
        ],
    ];

    /**
     * Apps that are external integrations (can be toggled per agent).
     * Built-in apps are always available.
     */
    public const INTEGRATION_APPS = ['telegram', 'plausible', 'jpgraph_charts'];

    /**
     * Icons for each app group.
     */
    private const APP_ICONS = [
        'chat' => 'ph:chat-circle',
        'docs' => 'ph:file-text',
        'tables' => 'ph:table',
        'calendar' => 'ph:calendar',
        'lists' => 'ph:kanban',
        'tasks' => 'ph:list-checks',
        'jpgraph_charts' => 'ph:chart-bar',
        'svg' => 'ph:file-svg',
        'telegram' => 'ph:telegram-logo',
        'plausible' => 'ph:chart-line-up',
        'system' => 'ph:gear',
    ];

    /**
     * Colored brand logos for integration apps (Iconify logo set).
     */
    private const INTEGRATION_LOGOS = [
        'telegram' => 'logos:telegram',
        'plausible' => 'simple-icons:plausibleanalytics',
        'jpgraph_charts' => 'ph:chart-bar',
    ];

    /**
     * Registry of all available tools with metadata.
     */
    private const TOOL_MAP = [
        // Chat
        'send_channel_message' => [
            'class' => SendChannelMessage::class,
            'type' => 'write',
            'name' => 'Send Channel Message',
            'description' => 'Send a message to a channel in the workspace.',
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
            'description' => 'List channels in the workspace that you have access to.',
            'icon' => 'ph:list-bullets',
        ],
        'manage_message' => [
            'class' => ManageMessage::class,
            'type' => 'write',
            'name' => 'Manage Message',
            'description' => 'Delete, pin, or add/remove reactions on a message.',
            'icon' => 'ph:chat-circle-dots',
        ],
        // Docs
        'search_documents' => [
            'class' => SearchDocuments::class,
            'type' => 'read',
            'name' => 'Search Documents',
            'description' => 'Search workspace documents by keyword.',
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
        // Charts (JpGraph)
        'create_jpgraph_chart' => [
            'class' => CreateJpGraphChart::class,
            'type' => 'write',
            'name' => 'Create JpGraph Chart',
            'description' => 'Generate chart images (bar, line, pie, scatter, radar, stock, gantt, and 14 more types) from data.',
            'icon' => 'ph:chart-bar',
        ],
        // SVG
        'render_svg' => [
            'class' => RenderSvg::class,
            'type' => 'write',
            'name' => 'Render SVG',
            'description' => 'Convert SVG markup to a PNG image.',
            'icon' => 'ph:file-svg',
        ],
        // External
        'send_telegram_notification' => [
            'class' => SendTelegramNotification::class,
            'type' => 'write',
            'name' => 'Send Telegram Notification',
            'description' => 'Send a notification to a Telegram chat.',
            'icon' => 'ph:telegram-logo',
        ],
        // Plausible Analytics
        'plausible_query_stats' => [
            'class' => PlausibleQueryStats::class,
            'type' => 'read',
            'name' => 'Query Stats',
            'description' => 'Query website analytics (aggregate, timeseries, breakdown).',
            'icon' => 'ph:chart-line-up',
        ],
        'plausible_realtime_visitors' => [
            'class' => PlausibleRealtimeVisitors::class,
            'type' => 'read',
            'name' => 'Realtime Visitors',
            'description' => 'Get current realtime visitor count.',
            'icon' => 'ph:users',
        ],
        'plausible_list_sites' => [
            'class' => PlausibleListSites::class,
            'type' => 'read',
            'name' => 'List Sites',
            'description' => 'List all tracked websites.',
            'icon' => 'ph:globe',
        ],
        'plausible_create_site' => [
            'class' => PlausibleCreateSite::class,
            'type' => 'write',
            'name' => 'Create Site',
            'description' => 'Register a new website for tracking.',
            'icon' => 'ph:globe',
        ],
        'plausible_delete_site' => [
            'class' => PlausibleDeleteSite::class,
            'type' => 'write',
            'name' => 'Delete Site',
            'description' => 'Remove a website from tracking.',
            'icon' => 'ph:trash',
        ],
        'plausible_list_goals' => [
            'class' => PlausibleListGoals::class,
            'type' => 'read',
            'name' => 'List Goals',
            'description' => 'List conversion goals for a site.',
            'icon' => 'ph:target',
        ],
        'plausible_create_goal' => [
            'class' => PlausibleCreateGoal::class,
            'type' => 'write',
            'name' => 'Create Goal',
            'description' => 'Create a conversion goal (page or event).',
            'icon' => 'ph:target',
        ],
        'plausible_delete_goal' => [
            'class' => PlausibleDeleteGoal::class,
            'type' => 'write',
            'name' => 'Delete Goal',
            'description' => 'Delete a conversion goal.',
            'icon' => 'ph:trash',
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
    ];

    public function __construct(
        private AgentPermissionService $permissionService,
    ) {}

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

        foreach (self::TOOL_MAP as $slug => $meta) {
            // Skip tools from disabled integrations
            $app = $appLookup[$slug] ?? 'other';
            if (in_array($app, self::INTEGRATION_APPS) && !in_array($app, $enabledIntegrations)) {
                continue;
            }

            $result = $this->permissionService->resolveToolPermission(
                $agent, $slug, $meta['type']
            );

            if (!$result['allowed']) {
                continue;
            }

            $tool = $this->instantiateTool($meta['class'], $agent);

            if ($result['requires_approval']) {
                $tool = new ApprovalWrappedTool($tool, $agent, $slug);
            }

            $tools[] = $tool;
        }

        return $tools;
    }

    /**
     * Get the slugs of tools available to a given agent.
     */
    public function getToolSlugsForAgent(User $agent): array
    {
        $enabledIntegrations = $this->permissionService->getEnabledIntegrations($agent);
        $appLookup = $this->buildAppLookup();
        $slugs = [];

        foreach (self::TOOL_MAP as $slug => $meta) {
            $app = $appLookup[$slug] ?? 'other';
            if (in_array($app, self::INTEGRATION_APPS) && !in_array($app, $enabledIntegrations)) {
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
     */
    public function getAllToolsMeta(User $agent): array
    {
        $appLookup = $this->buildAppLookup();
        $enabledIntegrations = $this->permissionService->getEnabledIntegrations($agent);

        $result = [];

        foreach (self::TOOL_MAP as $slug => $meta) {
            $app = $appLookup[$slug] ?? 'other';
            $isIntegration = in_array($app, self::INTEGRATION_APPS);
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
     */
    public function getAppGroupsMeta(): array
    {
        $result = [];
        foreach (self::APP_GROUPS as $name => $group) {
            $meta = [
                'name' => $name,
                'description' => $group['description'],
                'icon' => self::APP_ICONS[$name] ?? 'ph:puzzle-piece',
                'isIntegration' => in_array($name, self::INTEGRATION_APPS),
            ];

            if (isset(self::INTEGRATION_LOGOS[$name])) {
                $meta['logo'] = self::INTEGRATION_LOGOS[$name];
            }

            $result[] = $meta;
        }

        return $result;
    }

    /**
     * Get metadata for integration apps only (for the UI integrations section).
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
        if (!isset(self::TOOL_MAP[$slug])) {
            return null;
        }

        return $this->instantiateTool(self::TOOL_MAP[$slug]['class'], $agent);
    }

    /**
     * Build a compact app catalog string for the system prompt.
     * Only includes apps that have at least one allowed tool for the agent.
     */
    public function getAppCatalog(User $agent): string
    {
        $enabledIntegrations = $this->permissionService->getEnabledIntegrations($agent);
        $lines = [];

        foreach (self::APP_GROUPS as $appName => $group) {
            // Skip disabled integrations
            if (in_array($appName, self::INTEGRATION_APPS) && !in_array($appName, $enabledIntegrations)) {
                continue;
            }

            // Check which tools in this app the agent has access to
            $allowedSlugs = [];
            $hasApproval = false;

            foreach ($group['tools'] as $slug) {
                if (!isset(self::TOOL_MAP[$slug])) {
                    continue;
                }
                $result = $this->permissionService->resolveToolPermission(
                    $agent, $slug, self::TOOL_MAP[$slug]['type']
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
        if (isset(self::APP_GROUPS[$queryLower])) {
            return $this->getAppDetail($queryLower, $agent);
        }

        // Check if query matches a tool slug
        if (isset(self::TOOL_MAP[$queryLower])) {
            return $this->getSingleToolDetail($queryLower, $agent);
        }

        // Fuzzy search — try to find a matching tool
        foreach (self::TOOL_MAP as $slug => $meta) {
            if (str_contains($slug, $queryLower) || str_contains(strtolower($meta['name']), $queryLower)) {
                return $this->getSingleToolDetail($slug, $agent);
            }
        }

        // List available apps
        $apps = implode(', ', array_keys(self::APP_GROUPS));
        $tools = implode(', ', array_keys(self::TOOL_MAP));
        return "Not found: '{$query}'. Available apps: {$apps}. Or query a specific tool: {$tools}";
    }

    private function getAppDetail(string $appName, User $agent): string
    {
        $group = self::APP_GROUPS[$appName];
        $lines = ["App: {$appName} — {$group['description']}", '', 'Tools:'];

        foreach ($group['tools'] as $slug) {
            if (!isset(self::TOOL_MAP[$slug])) {
                continue;
            }
            $meta = self::TOOL_MAP[$slug];
            $perm = $this->permissionService->resolveToolPermission($agent, $slug, $meta['type']);

            if (!$perm['allowed']) {
                continue;
            }

            $approval = $perm['requires_approval'] ? ' (requires approval)' : '';
            $tool = $this->instantiateTool($meta['class'], $agent);
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
        $meta = self::TOOL_MAP[$slug];
        $perm = $this->permissionService->resolveToolPermission($agent, $slug, $meta['type']);

        if (!$perm['allowed']) {
            return "You do not have permission to use '{$slug}'.";
        }

        $approval = $perm['requires_approval'] ? 'yes' : 'no';
        $tool = $this->instantiateTool($meta['class'], $agent);
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
    private function instantiateTool(string $class, User $agent): \Laravel\Ai\Contracts\Tool
    {
        return match ($class) {
            // Tools needing permission service (channel/folder scoping)
            SendChannelMessage::class => new SendChannelMessage($agent, $this->permissionService),
            ReadChannel::class => new ReadChannel($agent, $this->permissionService),
            ManageMessage::class => new ManageMessage($agent, $this->permissionService),
            SearchDocuments::class => new SearchDocuments($agent, $this->permissionService),
            ManageDocument::class => new ManageDocument($agent, $this->permissionService),
            CommentOnDocument::class => new CommentOnDocument($agent, $this->permissionService),
            ListChannels::class => new ListChannels($agent, $this->permissionService),
            GetToolInfo::class => new GetToolInfo($agent, $this),
            // Tools needing only agent
            QueryTable::class => new QueryTable($agent),
            ManageTable::class => new ManageTable($agent),
            ManageTableRows::class => new ManageTableRows($agent),
            QueryCalendar::class => new QueryCalendar($agent),
            ManageCalendarEvent::class => new ManageCalendarEvent($agent),
            QueryListItems::class => new QueryListItems($agent),
            ManageListItem::class => new ManageListItem($agent),
            UpdateCurrentTask::class => new UpdateCurrentTask($agent),
            CreateTaskStep::class => new CreateTaskStep($agent),
            CreateJpGraphChart::class => new CreateJpGraphChart($agent),
            RenderSvg::class => new RenderSvg($agent),
            SendTelegramNotification::class => new SendTelegramNotification($agent, $this->permissionService),
            PlausibleQueryStats::class => new PlausibleQueryStats($agent),
            PlausibleRealtimeVisitors::class => new PlausibleRealtimeVisitors($agent),
            PlausibleListSites::class => new PlausibleListSites($agent),
            PlausibleCreateSite::class => new PlausibleCreateSite($agent),
            PlausibleDeleteSite::class => new PlausibleDeleteSite($agent),
            PlausibleListGoals::class => new PlausibleListGoals($agent),
            PlausibleCreateGoal::class => new PlausibleCreateGoal($agent),
            PlausibleDeleteGoal::class => new PlausibleDeleteGoal($agent),
            WaitForApproval::class => new WaitForApproval($agent),
            Wait::class => new Wait($agent),
            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
    }

    /**
     * Build reverse lookup: tool slug → app name.
     */
    private function buildAppLookup(): array
    {
        $lookup = [];
        foreach (self::APP_GROUPS as $appName => $group) {
            foreach ($group['tools'] as $slug) {
                $lookup[$slug] = $appName;
            }
        }

        return $lookup;
    }
}

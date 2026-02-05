<?php

namespace App\Agents\Tools;

use App\Models\User;
use App\Services\AgentPermissionService;

class ToolRegistry
{
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
        'create_task_step' => [
            'class' => CreateTaskStep::class,
            'type' => 'write',
            'name' => 'Create Task Step',
            'description' => 'Log a progress step on a task you are working on.',
            'icon' => 'ph:list-checks',
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
        $tools = [];

        foreach (self::TOOL_MAP as $slug => $meta) {
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
     * Get metadata for ALL tools with permission status for a specific agent.
     * Used by the API to populate the capabilities tab.
     */
    public function getAllToolsMeta(User $agent): array
    {
        $result = [];

        foreach (self::TOOL_MAP as $slug => $meta) {
            $permission = $this->permissionService->resolveToolPermission(
                $agent, $slug, $meta['type']
            );

            $result[] = [
                'id' => $slug,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'type' => $meta['type'],
                'icon' => $meta['icon'],
                'enabled' => $permission['allowed'],
                'requiresApproval' => $permission['requires_approval'],
            ];
        }

        return $result;
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
            // Tools needing only agent
            QueryTable::class => new QueryTable($agent),
            ManageTable::class => new ManageTable($agent),
            ManageTableRows::class => new ManageTableRows($agent),
            QueryCalendar::class => new QueryCalendar($agent),
            ManageCalendarEvent::class => new ManageCalendarEvent($agent),
            QueryListItems::class => new QueryListItems($agent),
            ManageListItem::class => new ManageListItem($agent),
            CreateTaskStep::class => new CreateTaskStep($agent),
            WaitForApproval::class => new WaitForApproval($agent),
            Wait::class => new Wait($agent),
            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
    }
}

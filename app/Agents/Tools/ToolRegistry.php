<?php

namespace App\Agents\Tools;

use App\Agents\Tools\Agents\ContactAgent;
use App\Agents\Tools\Calendar\CreateCalendarEvent;
use App\Agents\Tools\Calendar\DeleteCalendarEvent;
use App\Agents\Tools\Calendar\GetCalendarEvent;
use App\Agents\Tools\Calendar\ListCalendarEvents;
use App\Agents\Tools\Calendar\RemoveCalendarAttendee;
use App\Agents\Tools\Calendar\UpdateCalendarAttendee;
use App\Agents\Tools\Calendar\UpdateCalendarEvent;
use App\Agents\Tools\Charts\RenderSvg;
use App\Agents\Tools\Chat\AddMessageReaction;
use App\Agents\Tools\Chat\DeleteMessage;
use App\Agents\Tools\Chat\EditMessage;
use App\Agents\Tools\Chat\JoinExternalChannel;
use App\Agents\Tools\Chat\LeaveExternalChannel;
use App\Agents\Tools\Chat\ListChannels;
use App\Agents\Tools\Chat\ListExternalChannels;
use App\Agents\Tools\Chat\PinMessage;
use App\Agents\Tools\Chat\ReadPinnedMessages;
use App\Agents\Tools\Chat\ReadRecentMessages;
use App\Agents\Tools\Chat\ReadThread;
use App\Agents\Tools\Chat\RemoveMessageReaction;
use App\Agents\Tools\Chat\SearchMessages;
use App\Agents\Tools\Chat\SendChannelMessage;
use App\Agents\Tools\Docs\AddDocumentComment;
use App\Agents\Tools\Docs\CreateDocument;
use App\Agents\Tools\Docs\DeleteDocument;
use App\Agents\Tools\Docs\DeleteDocumentComment;
use App\Agents\Tools\Docs\GetDocument;
use App\Agents\Tools\Docs\GetDocumentTree;
use App\Agents\Tools\Docs\ListDocumentAttachments;
use App\Agents\Tools\Docs\ListDocumentComments;
use App\Agents\Tools\Docs\ListDocuments;
use App\Agents\Tools\Docs\ListDocumentVersions;
use App\Agents\Tools\Docs\ResolveDocumentComment;
use App\Agents\Tools\Docs\RestoreDocumentVersion;
use App\Agents\Tools\Docs\SearchDocuments;
use App\Agents\Tools\Docs\UpdateDocument;
use App\Agents\Tools\Lists\AddListItemComment;
use App\Agents\Tools\Lists\CreateListItem;
use App\Agents\Tools\Lists\CreateListStatus;
use App\Agents\Tools\Lists\DeleteListItem;
use App\Agents\Tools\Lists\DeleteListItemComment;
use App\Agents\Tools\Lists\DeleteListStatus;
use App\Agents\Tools\Lists\GetListItem;
use App\Agents\Tools\Lists\ListAllItems;
use App\Agents\Tools\Lists\ListItemsByAssignee;
use App\Agents\Tools\Lists\ListItemsByStatus;
use App\Agents\Tools\Lists\ListItemStatuses;
use App\Agents\Tools\Lists\ListProjects;
use App\Agents\Tools\Lists\UpdateListItem;
use App\Agents\Tools\Lists\UpdateListStatus;
use App\Agents\Tools\Memory\RecallMemory;
use App\Agents\Tools\Memory\SaveMemory;
use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Agents\Tools\System\Wait;
use App\Agents\Tools\System\WaitForApproval;
use App\Agents\Tools\Tables\AddTableColumn;
use App\Agents\Tools\Tables\AddTableRow;
use App\Agents\Tools\Tables\BulkAddTableRows;
use App\Agents\Tools\Tables\BulkDeleteTableRows;
use App\Agents\Tools\Tables\CreateTable;
use App\Agents\Tools\Tables\CreateTableView;
use App\Agents\Tools\Tables\DeleteTable;
use App\Agents\Tools\Tables\DeleteTableColumn;
use App\Agents\Tools\Tables\DeleteTableRow;
use App\Agents\Tools\Tables\DeleteTableView;
use App\Agents\Tools\Tables\GetTable;
use App\Agents\Tools\Tables\GetTableRows;
use App\Agents\Tools\Tables\ListTables;
use App\Agents\Tools\Tables\ListTableViews;
use App\Agents\Tools\Tables\ReorderTableColumns;
use App\Agents\Tools\Tables\SearchTableRows;
use App\Agents\Tools\Tables\UpdateTable;
use App\Agents\Tools\Tables\UpdateTableColumn;
use App\Agents\Tools\Tables\UpdateTableRow;
use App\Agents\Tools\Tables\UpdateTableView;
use App\Agents\Tools\Tasks\AddTaskStep;
use App\Agents\Tools\Tasks\CreateTaskStep;
use App\Agents\Tools\Tasks\SetTaskStatus;
use App\Agents\Tools\Tasks\UpdateTask;
use App\Agents\Tools\Tasks\UpdateTaskStep;
use App\Agents\Tools\Workspace\AddChannelMember;
use App\Agents\Tools\Workspace\AddMcpServer;
use App\Agents\Tools\Workspace\CreateAgent;
use App\Agents\Tools\Workspace\CreateAutomationRule;
use App\Agents\Tools\Workspace\CreateChannel;
use App\Agents\Tools\Workspace\CreateItemTemplate;
use App\Agents\Tools\Workspace\CreateSchedule;
use App\Agents\Tools\Workspace\DeleteAgent;
use App\Agents\Tools\Workspace\DeleteAutomationRule;
use App\Agents\Tools\Workspace\DeleteItemTemplate;
use App\Agents\Tools\Workspace\DeleteSchedule;
use App\Agents\Tools\Workspace\DisableSchedule;
use App\Agents\Tools\Workspace\DiscoverMcpTools;
use App\Agents\Tools\Workspace\EnableSchedule;
use App\Agents\Tools\Workspace\GetAgentDetails;
use App\Agents\Tools\Workspace\GetAgentPermissions;
use App\Agents\Tools\Workspace\GetIntegrationConfig;
use App\Agents\Tools\Workspace\GetIntegrationSetup;
use App\Agents\Tools\Workspace\LinkExternalUser;
use App\Agents\Tools\Workspace\ListAgents;
use App\Agents\Tools\Workspace\ListAutomationRules;
use App\Agents\Tools\Workspace\ListAvailableModels;
use App\Agents\Tools\Workspace\ListIntegrations;
use App\Agents\Tools\Workspace\ListMcpServers;
use App\Agents\Tools\Workspace\ListMembers;
use App\Agents\Tools\Workspace\ListSchedules;
use App\Agents\Tools\Workspace\ReadAgentIdentityFile;
use App\Agents\Tools\Workspace\RemoveChannelMember;
use App\Agents\Tools\Workspace\RemoveMcpServer;
use App\Agents\Tools\Workspace\SetupIntegrationWebhook;
use App\Agents\Tools\Workspace\TestIntegrationConnection;
use App\Agents\Tools\Workspace\TestMcpServer;
use App\Agents\Tools\Workspace\TriggerSchedule;
use App\Agents\Tools\Workspace\UpdateAgent;
use App\Agents\Tools\Workspace\UpdateAgentIdentityFile;
use App\Agents\Tools\Workspace\UpdateAgentChannelAccess;
use App\Agents\Tools\Workspace\UpdateAgentFolderAccess;
use App\Agents\Tools\Workspace\UpdateAgentIntegrationAccess;
use App\Agents\Tools\Workspace\UpdateAgentToolPermissions;
use App\Agents\Tools\Workspace\UpdateAutomationRule;
use App\Agents\Tools\Workspace\UpdateIntegrationConfig;
use App\Agents\Tools\Workspace\UpdateItemTemplate;
use App\Agents\Tools\Workspace\UpdateMcpServer;
use App\Agents\Tools\Workspace\UpdateSchedule;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\AgentAvatarService;
use App\Services\AgentDocumentService;
use App\Services\AgentPermissionService;
use App\Services\Memory\DocumentIndexingService;
use App\Services\Memory\MemoryScopeGuard;
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
            'tools' => ['update_task', 'add_task_step', 'update_task_step', 'set_task_status', 'create_task_step'],
            'label' => 'update, add_step, update_step, set_status',
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
            'tools' => ['send_channel_message', 'read_recent_messages', 'read_thread', 'read_pinned_messages', 'list_channels', 'edit_message', 'delete_message', 'pin_message', 'add_message_reaction', 'remove_message_reaction', 'search_messages', 'list_external_channels', 'join_external_channel', 'leave_external_channel'],
            'label' => 'send, read, list, edit, delete, pin, react, search, external',
            'description' => 'Channel messaging (incl. external: Telegram, Slack)',
        ],
        'docs' => [
            'tools' => ['list_documents', 'get_document', 'get_document_tree', 'search_documents', 'create_document', 'update_document', 'delete_document', 'add_document_comment', 'list_document_comments', 'resolve_document_comment', 'delete_document_comment', 'list_document_versions', 'restore_document_version', 'list_document_attachments'],
            'label' => 'list, get, tree, search, create, update, delete, comment',
            'description' => 'Document workspace',
        ],
        'tables' => [
            'tools' => ['list_tables', 'get_table', 'get_table_rows', 'search_table_rows', 'create_table', 'update_table', 'delete_table', 'add_table_column', 'update_table_column', 'delete_table_column', 'reorder_table_columns', 'add_table_row', 'update_table_row', 'delete_table_row', 'bulk_add_table_rows', 'bulk_delete_table_rows', 'list_table_views', 'create_table_view', 'update_table_view', 'delete_table_view'],
            'label' => 'list, get, search, create, update, delete, columns, rows, views',
            'description' => 'Structured data tables',
        ],
        'calendar' => [
            'tools' => ['list_calendar_events', 'get_calendar_event', 'create_calendar_event', 'update_calendar_event', 'delete_calendar_event', 'update_calendar_attendee', 'remove_calendar_attendee'],
            'label' => 'list, get, create, update, delete, attendees',
            'description' => 'Events and scheduling',
        ],
        'lists' => [
            'tools' => ['list_all_items', 'get_list_item', 'list_items_by_status', 'list_items_by_assignee', 'list_projects', 'list_item_statuses', 'create_list_item', 'update_list_item', 'delete_list_item', 'add_list_item_comment', 'delete_list_item_comment', 'create_list_status', 'update_list_status', 'delete_list_status'],
            'label' => 'list, get, filter, projects, statuses, create, update, delete, comments',
            'description' => 'Kanban board items and workflow statuses',
        ],
        'workspace' => [
            'tools' => [
                'list_agents', 'list_members', 'get_agent_details', 'get_agent_permissions', 'list_integrations', 'get_integration_config', 'list_available_models', 'list_automation_rules',
                'create_agent', 'update_agent', 'delete_agent', 'read_agent_identity_file', 'update_agent_identity_file',
                'update_agent_tool_permissions', 'update_agent_channel_access', 'update_agent_folder_access', 'update_agent_integration_access',
                'get_integration_setup', 'update_integration_config', 'test_integration_connection', 'setup_integration_webhook', 'link_external_user',
                'list_mcp_servers', 'add_mcp_server', 'update_mcp_server', 'remove_mcp_server', 'test_mcp_server', 'discover_mcp_tools',
                'create_channel', 'add_channel_member', 'remove_channel_member',
                'create_automation_rule', 'update_automation_rule', 'delete_automation_rule',
                'create_item_template', 'update_item_template', 'delete_item_template',
                'create_schedule', 'update_schedule', 'delete_schedule', 'list_schedules', 'enable_schedule', 'disable_schedule', 'trigger_schedule',
            ],
            'label' => 'agents, members, permissions, integrations, mcp, channels, automation, schedules',
            'description' => 'Workspace management',
        ],

        // Integrations & utilities
        'svg' => [
            'tools' => ['render_svg'],
            'label' => 'render',
            'description' => 'Render SVG markup to PNG images',
        ],
        'lua' => [
            'tools' => ['lua_list_docs', 'lua_search_docs', 'lua_read_doc', 'lua_exec'],
            'label' => 'list_docs, search_docs, read_doc, exec',
            'description' => 'Lua scripting API reference and code execution',
        ],
    ];

    /**
     * Apps that are external integrations (can be toggled per agent).
     * Built-in apps are always available.
     */
    public const INTEGRATION_APPS = ['telegram'];

    /**
     * App groups that remain as direct AI tools.
     * Everything else is accessible only via lua_exec (code-first approach).
     */
    public const DIRECT_TOOL_GROUPS = ['tasks', 'system', 'agents', 'memory', 'lua'];

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
        'lua' => 'ph:code',
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
        'read_recent_messages' => [
            'class' => ReadRecentMessages::class,
            'type' => 'read',
            'name' => 'Read Recent Messages',
            'description' => 'Read the most recent messages from a channel.',
            'icon' => 'ph:chat-dots',
        ],
        'read_thread' => [
            'class' => ReadThread::class,
            'type' => 'read',
            'name' => 'Read Thread',
            'description' => 'Read a message thread (replies) by thread root message ID.',
            'icon' => 'ph:chat-dots',
        ],
        'read_pinned_messages' => [
            'class' => ReadPinnedMessages::class,
            'type' => 'read',
            'name' => 'Read Pinned Messages',
            'description' => 'Read pinned messages from a channel.',
            'icon' => 'ph:push-pin',
        ],
        'list_channels' => [
            'class' => ListChannels::class,
            'type' => 'read',
            'name' => 'List Channels',
            'description' => 'List channels you have access to, including external (Telegram, Slack) channels.',
            'icon' => 'ph:list-bullets',
        ],
        'edit_message' => [
            'class' => EditMessage::class,
            'type' => 'write',
            'name' => 'Edit Message',
            'description' => 'Edit a message\'s content.',
            'icon' => 'ph:pencil-simple',
        ],
        'delete_message' => [
            'class' => DeleteMessage::class,
            'type' => 'write',
            'name' => 'Delete Message',
            'description' => 'Delete a message.',
            'icon' => 'ph:trash',
        ],
        'pin_message' => [
            'class' => PinMessage::class,
            'type' => 'write',
            'name' => 'Pin Message',
            'description' => 'Toggle pin status on a message.',
            'icon' => 'ph:push-pin',
        ],
        'add_message_reaction' => [
            'class' => AddMessageReaction::class,
            'type' => 'write',
            'name' => 'Add Message Reaction',
            'description' => 'Add an emoji reaction to a message.',
            'icon' => 'ph:smiley',
        ],
        'remove_message_reaction' => [
            'class' => RemoveMessageReaction::class,
            'type' => 'write',
            'name' => 'Remove Message Reaction',
            'description' => 'Remove an emoji reaction from a message.',
            'icon' => 'ph:smiley',
        ],
        'search_messages' => [
            'class' => SearchMessages::class,
            'type' => 'read',
            'name' => 'Search Messages',
            'description' => 'Search messages across channels by keyword, with optional channel and author filtering.',
            'icon' => 'ph:magnifying-glass',
        ],
        'list_external_channels' => [
            'class' => ListExternalChannels::class,
            'type' => 'read',
            'name' => 'List External Channels',
            'description' => 'List available channels on external platforms (Telegram, Discord).',
            'icon' => 'ph:globe',
        ],
        'join_external_channel' => [
            'class' => JoinExternalChannel::class,
            'type' => 'write',
            'name' => 'Join External Channel',
            'description' => 'Join an external platform channel to start receiving messages.',
            'icon' => 'ph:sign-in',
        ],
        'leave_external_channel' => [
            'class' => LeaveExternalChannel::class,
            'type' => 'write',
            'name' => 'Leave External Channel',
            'description' => 'Leave an external platform channel.',
            'icon' => 'ph:sign-out',
        ],
        // Docs
        'list_documents' => [
            'class' => ListDocuments::class,
            'type' => 'read',
            'name' => 'List Documents',
            'description' => 'List documents and folders, optionally filtered by parent folder.',
            'icon' => 'ph:folder-open',
        ],
        'get_document' => [
            'class' => GetDocument::class,
            'type' => 'read',
            'name' => 'Get Document',
            'description' => 'Get full details and content of a specific document.',
            'icon' => 'ph:file-text',
        ],
        'get_document_tree' => [
            'class' => GetDocumentTree::class,
            'type' => 'read',
            'name' => 'Get Document Tree',
            'description' => 'Get the full document tree hierarchy.',
            'icon' => 'ph:tree-structure',
        ],
        'search_documents' => [
            'class' => SearchDocuments::class,
            'type' => 'read',
            'name' => 'Search Documents',
            'description' => 'Search workspace documents by keyword or semantic similarity.',
            'icon' => 'ph:magnifying-glass',
        ],
        'create_document' => [
            'class' => CreateDocument::class,
            'type' => 'write',
            'name' => 'Create Document',
            'description' => 'Create a new document or folder.',
            'icon' => 'ph:file-plus',
        ],
        'update_document' => [
            'class' => UpdateDocument::class,
            'type' => 'write',
            'name' => 'Update Document',
            'description' => 'Update a document\'s title, content, or parent folder.',
            'icon' => 'ph:file-text',
        ],
        'delete_document' => [
            'class' => DeleteDocument::class,
            'type' => 'write',
            'name' => 'Delete Document',
            'description' => 'Delete a document or folder.',
            'icon' => 'ph:file-minus',
        ],
        'add_document_comment' => [
            'class' => AddDocumentComment::class,
            'type' => 'write',
            'name' => 'Add Document Comment',
            'description' => 'Add a comment to a document.',
            'icon' => 'ph:chat-teardrop-text',
        ],
        'resolve_document_comment' => [
            'class' => ResolveDocumentComment::class,
            'type' => 'write',
            'name' => 'Resolve Document Comment',
            'description' => 'Mark a document comment as resolved.',
            'icon' => 'ph:chat-teardrop-text',
        ],
        'delete_document_comment' => [
            'class' => DeleteDocumentComment::class,
            'type' => 'write',
            'name' => 'Delete Document Comment',
            'description' => 'Delete a comment from a document.',
            'icon' => 'ph:chat-teardrop-text',
        ],
        'list_document_comments' => [
            'class' => ListDocumentComments::class,
            'type' => 'read',
            'name' => 'List Document Comments',
            'description' => 'List comments on a document with author and resolved status.',
            'icon' => 'ph:chat-teardrop-text',
        ],
        'list_document_versions' => [
            'class' => ListDocumentVersions::class,
            'type' => 'read',
            'name' => 'List Document Versions',
            'description' => 'List version history for a document.',
            'icon' => 'ph:clock-counter-clockwise',
        ],
        'restore_document_version' => [
            'class' => RestoreDocumentVersion::class,
            'type' => 'write',
            'name' => 'Restore Document Version',
            'description' => 'Restore a document to a previous version.',
            'icon' => 'ph:clock-counter-clockwise',
        ],
        'list_document_attachments' => [
            'class' => ListDocumentAttachments::class,
            'type' => 'read',
            'name' => 'List Document Attachments',
            'description' => 'List file attachments on a document.',
            'icon' => 'ph:paperclip',
        ],
        // Tables — Read
        'list_tables' => [
            'class' => ListTables::class,
            'type' => 'read',
            'name' => 'List Tables',
            'description' => 'List all data tables in the workspace.',
            'icon' => 'ph:table',
        ],
        'get_table' => [
            'class' => GetTable::class,
            'type' => 'read',
            'name' => 'Get Table',
            'description' => 'Get a table structure and columns.',
            'icon' => 'ph:table',
        ],
        'get_table_rows' => [
            'class' => GetTableRows::class,
            'type' => 'read',
            'name' => 'Get Table Rows',
            'description' => 'Retrieve rows from a data table.',
            'icon' => 'ph:table',
        ],
        'search_table_rows' => [
            'class' => SearchTableRows::class,
            'type' => 'read',
            'name' => 'Search Table Rows',
            'description' => 'Search rows by matching a term against row data.',
            'icon' => 'ph:magnifying-glass',
        ],
        // Tables — Write (structure)
        'create_table' => [
            'class' => CreateTable::class,
            'type' => 'write',
            'name' => 'Create Table',
            'description' => 'Create a new data table.',
            'icon' => 'ph:table',
        ],
        'update_table' => [
            'class' => UpdateTable::class,
            'type' => 'write',
            'name' => 'Update Table',
            'description' => 'Update a table name or description.',
            'icon' => 'ph:table',
        ],
        'delete_table' => [
            'class' => DeleteTable::class,
            'type' => 'write',
            'name' => 'Delete Table',
            'description' => 'Delete a table and all its data.',
            'icon' => 'ph:table',
        ],
        'add_table_column' => [
            'class' => AddTableColumn::class,
            'type' => 'write',
            'name' => 'Add Table Column',
            'description' => 'Add a column to a table.',
            'icon' => 'ph:table',
        ],
        'update_table_column' => [
            'class' => UpdateTableColumn::class,
            'type' => 'write',
            'name' => 'Update Table Column',
            'description' => 'Update a column name, type, or options.',
            'icon' => 'ph:table',
        ],
        'delete_table_column' => [
            'class' => DeleteTableColumn::class,
            'type' => 'write',
            'name' => 'Delete Table Column',
            'description' => 'Delete a column from a table.',
            'icon' => 'ph:table',
        ],
        // Tables — Write (rows)
        'add_table_row' => [
            'class' => AddTableRow::class,
            'type' => 'write',
            'name' => 'Add Table Row',
            'description' => 'Add a row to a data table.',
            'icon' => 'ph:rows',
        ],
        'update_table_row' => [
            'class' => UpdateTableRow::class,
            'type' => 'write',
            'name' => 'Update Table Row',
            'description' => 'Update an existing row.',
            'icon' => 'ph:rows',
        ],
        'delete_table_row' => [
            'class' => DeleteTableRow::class,
            'type' => 'write',
            'name' => 'Delete Table Row',
            'description' => 'Delete a row from a table.',
            'icon' => 'ph:rows',
        ],
        'bulk_add_table_rows' => [
            'class' => BulkAddTableRows::class,
            'type' => 'write',
            'name' => 'Bulk Add Table Rows',
            'description' => 'Add multiple rows at once.',
            'icon' => 'ph:rows',
        ],
        'bulk_delete_table_rows' => [
            'class' => BulkDeleteTableRows::class,
            'type' => 'write',
            'name' => 'Bulk Delete Table Rows',
            'description' => 'Delete multiple rows at once.',
            'icon' => 'ph:rows',
        ],
        'reorder_table_columns' => [
            'class' => ReorderTableColumns::class,
            'type' => 'write',
            'name' => 'Reorder Table Columns',
            'description' => 'Reorder columns in a table.',
            'icon' => 'ph:arrows-down-up',
        ],
        'list_table_views' => [
            'class' => ListTableViews::class,
            'type' => 'read',
            'name' => 'List Table Views',
            'description' => 'List saved views for a table.',
            'icon' => 'ph:eye',
        ],
        'create_table_view' => [
            'class' => CreateTableView::class,
            'type' => 'write',
            'name' => 'Create Table View',
            'description' => 'Create a saved table view (grid, kanban, gallery, calendar).',
            'icon' => 'ph:eye',
        ],
        'update_table_view' => [
            'class' => UpdateTableView::class,
            'type' => 'write',
            'name' => 'Update Table View',
            'description' => 'Update a saved table view.',
            'icon' => 'ph:eye',
        ],
        'delete_table_view' => [
            'class' => DeleteTableView::class,
            'type' => 'write',
            'name' => 'Delete Table View',
            'description' => 'Delete a saved table view.',
            'icon' => 'ph:eye',
        ],
        // Calendar
        'list_calendar_events' => [
            'class' => ListCalendarEvents::class,
            'type' => 'read',
            'name' => 'List Calendar Events',
            'description' => 'List events by date range, optionally filtered by user.',
            'icon' => 'ph:calendar',
        ],
        'get_calendar_event' => [
            'class' => GetCalendarEvent::class,
            'type' => 'read',
            'name' => 'Get Calendar Event',
            'description' => 'Get detailed information about a specific calendar event.',
            'icon' => 'ph:calendar',
        ],
        'create_calendar_event' => [
            'class' => CreateCalendarEvent::class,
            'type' => 'write',
            'name' => 'Create Calendar Event',
            'description' => 'Create a new calendar event with attendees.',
            'icon' => 'ph:calendar-plus',
        ],
        'update_calendar_event' => [
            'class' => UpdateCalendarEvent::class,
            'type' => 'write',
            'name' => 'Update Calendar Event',
            'description' => 'Update an existing calendar event.',
            'icon' => 'ph:calendar-plus',
        ],
        'delete_calendar_event' => [
            'class' => DeleteCalendarEvent::class,
            'type' => 'write',
            'name' => 'Delete Calendar Event',
            'description' => 'Delete a calendar event.',
            'icon' => 'ph:calendar-minus',
        ],
        'update_calendar_attendee' => [
            'class' => UpdateCalendarAttendee::class,
            'type' => 'write',
            'name' => 'Update Calendar Attendee',
            'description' => 'Update an attendee\'s RSVP status on a calendar event.',
            'icon' => 'ph:user-check',
        ],
        'remove_calendar_attendee' => [
            'class' => RemoveCalendarAttendee::class,
            'type' => 'write',
            'name' => 'Remove Calendar Attendee',
            'description' => 'Remove an attendee from a calendar event.',
            'icon' => 'ph:user-minus',
        ],
        // Lists — Read
        'list_all_items' => [
            'class' => ListAllItems::class,
            'type' => 'read',
            'name' => 'List All Items',
            'description' => 'List all kanban board items, optionally filtered by parent project.',
            'icon' => 'ph:kanban',
        ],
        'get_list_item' => [
            'class' => GetListItem::class,
            'type' => 'read',
            'name' => 'Get List Item',
            'description' => 'Get full details of a single list item.',
            'icon' => 'ph:kanban',
        ],
        'list_items_by_status' => [
            'class' => ListItemsByStatus::class,
            'type' => 'read',
            'name' => 'List Items by Status',
            'description' => 'List items filtered by a specific status.',
            'icon' => 'ph:kanban',
        ],
        'list_items_by_assignee' => [
            'class' => ListItemsByAssignee::class,
            'type' => 'read',
            'name' => 'List Items by Assignee',
            'description' => 'List items assigned to a specific user.',
            'icon' => 'ph:kanban',
        ],
        'list_projects' => [
            'class' => ListProjects::class,
            'type' => 'read',
            'name' => 'List Projects',
            'description' => 'List projects (folders) on the kanban board.',
            'icon' => 'ph:kanban',
        ],
        'list_item_statuses' => [
            'class' => ListItemStatuses::class,
            'type' => 'read',
            'name' => 'List Item Statuses',
            'description' => 'List available workflow statuses and their slugs.',
            'icon' => 'ph:columns',
        ],
        // Lists — Write (items)
        'create_list_item' => [
            'class' => CreateListItem::class,
            'type' => 'write',
            'name' => 'Create List Item',
            'description' => 'Create a new list item or project/folder.',
            'icon' => 'ph:list-plus',
        ],
        'update_list_item' => [
            'class' => UpdateListItem::class,
            'type' => 'write',
            'name' => 'Update List Item',
            'description' => 'Update an existing list item.',
            'icon' => 'ph:list-plus',
        ],
        'delete_list_item' => [
            'class' => DeleteListItem::class,
            'type' => 'write',
            'name' => 'Delete List Item',
            'description' => 'Delete a list item.',
            'icon' => 'ph:list-plus',
        ],
        'add_list_item_comment' => [
            'class' => AddListItemComment::class,
            'type' => 'write',
            'name' => 'Add List Item Comment',
            'description' => 'Add a comment to a list item.',
            'icon' => 'ph:chat-teardrop-text',
        ],
        'delete_list_item_comment' => [
            'class' => DeleteListItemComment::class,
            'type' => 'write',
            'name' => 'Delete List Item Comment',
            'description' => 'Delete a comment from a list item.',
            'icon' => 'ph:chat-teardrop-text',
        ],
        // Lists — Write (statuses)
        'create_list_status' => [
            'class' => CreateListStatus::class,
            'type' => 'write',
            'name' => 'Create List Status',
            'description' => 'Create a new workflow status column.',
            'icon' => 'ph:columns',
        ],
        'update_list_status' => [
            'class' => UpdateListStatus::class,
            'type' => 'write',
            'name' => 'Update List Status',
            'description' => 'Update an existing workflow status.',
            'icon' => 'ph:columns',
        ],
        'delete_list_status' => [
            'class' => DeleteListStatus::class,
            'type' => 'write',
            'name' => 'Delete List Status',
            'description' => 'Delete a workflow status column.',
            'icon' => 'ph:columns',
        ],
        // Tasks
        'update_task' => [
            'class' => UpdateTask::class,
            'type' => 'write',
            'name' => 'Update Task',
            'description' => 'Update a task\'s title or description.',
            'icon' => 'ph:list-checks',
        ],
        'add_task_step' => [
            'class' => AddTaskStep::class,
            'type' => 'write',
            'name' => 'Add Task Step',
            'description' => 'Add a progress step to a task.',
            'icon' => 'ph:list-checks',
        ],
        'update_task_step' => [
            'class' => UpdateTaskStep::class,
            'type' => 'write',
            'name' => 'Update Task Step',
            'description' => 'Update the description of a task step.',
            'icon' => 'ph:list-checks',
        ],
        'set_task_status' => [
            'class' => SetTaskStatus::class,
            'type' => 'write',
            'name' => 'Set Task Status',
            'description' => 'Set a task as completed or failed.',
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
        // Lua scripting
        'lua_list_docs' => [
            'class' => Lua\LuaListDocs::class,
            'type' => 'read',
            'name' => 'List Lua API Docs',
            'description' => 'List available Lua scripting API namespaces and functions.',
            'icon' => 'ph:list-bullets',
        ],
        'lua_search_docs' => [
            'class' => Lua\LuaSearchDocs::class,
            'type' => 'read',
            'name' => 'Search Lua API Docs',
            'description' => 'Search the Lua scripting API documentation by keyword.',
            'icon' => 'ph:magnifying-glass',
        ],
        'lua_read_doc' => [
            'class' => Lua\LuaReadDoc::class,
            'type' => 'read',
            'name' => 'Read Lua API Doc',
            'description' => 'Read detailed Lua API documentation for a namespace, function, or guide.',
            'icon' => 'ph:book-open-text',
        ],
        'lua_exec' => [
            'class' => Lua\LuaExec::class,
            'type' => 'write',
            'name' => 'Execute Lua Code',
            'description' => 'Execute Lua code in a sandboxed environment and return the output.',
            'icon' => 'ph:play',
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
        // Workspace — Query
        'list_agents' => [
            'class' => ListAgents::class,
            'type' => 'read',
            'name' => 'List Agents',
            'description' => 'List all agents in the workspace.',
            'icon' => 'ph:robot',
        ],
        'list_members' => [
            'class' => ListMembers::class,
            'type' => 'read',
            'name' => 'List Members',
            'description' => 'List all human members of the workspace.',
            'icon' => 'ph:users',
        ],
        'get_agent_details' => [
            'class' => GetAgentDetails::class,
            'type' => 'read',
            'name' => 'Get Agent Details',
            'description' => 'Get detailed information about a specific agent.',
            'icon' => 'ph:robot',
        ],
        'get_agent_permissions' => [
            'class' => GetAgentPermissions::class,
            'type' => 'read',
            'name' => 'Get Agent Permissions',
            'description' => 'View an agent\'s tool, channel, folder, and integration permissions.',
            'icon' => 'ph:shield-check',
        ],
        'list_integrations' => [
            'class' => ListIntegrations::class,
            'type' => 'read',
            'name' => 'List Integrations',
            'description' => 'List configured integrations and their status.',
            'icon' => 'ph:plugs-connected',
        ],
        'get_integration_config' => [
            'class' => GetIntegrationConfig::class,
            'type' => 'read',
            'name' => 'Get Integration Config',
            'description' => 'Get configuration details for a specific integration.',
            'icon' => 'ph:plugs-connected',
        ],
        'list_available_models' => [
            'class' => ListAvailableModels::class,
            'type' => 'read',
            'name' => 'List Available Models',
            'description' => 'List available AI models that agents can use.',
            'icon' => 'ph:brain',
        ],
        'list_automation_rules' => [
            'class' => ListAutomationRules::class,
            'type' => 'read',
            'name' => 'List Automation Rules',
            'description' => 'List all automation rules in the workspace.',
            'icon' => 'ph:lightning',
        ],
        // Workspace — Agent management
        'create_agent' => [
            'class' => CreateAgent::class,
            'type' => 'write',
            'name' => 'Create Agent',
            'description' => 'Create a new agent in the workspace.',
            'icon' => 'ph:robot',
        ],
        'update_agent' => [
            'class' => UpdateAgent::class,
            'type' => 'write',
            'name' => 'Update Agent',
            'description' => 'Update an agent\'s name, brain, status, or behavior mode.',
            'icon' => 'ph:robot',
        ],
        'delete_agent' => [
            'class' => DeleteAgent::class,
            'type' => 'write',
            'name' => 'Delete Agent',
            'description' => 'Delete an agent from the workspace.',
            'icon' => 'ph:robot',
        ],
        'read_agent_identity_file' => [
            'class' => ReadAgentIdentityFile::class,
            'type' => 'read',
            'name' => 'Read Agent Identity File',
            'description' => 'Read an agent\'s identity file (IDENTITY, SOUL, TOOLS, etc.).',
            'icon' => 'ph:file-text',
        ],
        'update_agent_identity_file' => [
            'class' => UpdateAgentIdentityFile::class,
            'type' => 'write',
            'name' => 'Update Agent Identity File',
            'description' => 'Update an agent\'s identity file content.',
            'icon' => 'ph:file-text',
        ],
        // Workspace — Permissions
        'update_agent_tool_permissions' => [
            'class' => UpdateAgentToolPermissions::class,
            'type' => 'write',
            'name' => 'Update Agent Tool Permissions',
            'description' => 'Update tool permissions for an agent.',
            'icon' => 'ph:shield-check',
        ],
        'update_agent_channel_access' => [
            'class' => UpdateAgentChannelAccess::class,
            'type' => 'write',
            'name' => 'Update Agent Channel Access',
            'description' => 'Update channel access for an agent.',
            'icon' => 'ph:shield-check',
        ],
        'update_agent_folder_access' => [
            'class' => UpdateAgentFolderAccess::class,
            'type' => 'write',
            'name' => 'Update Agent Folder Access',
            'description' => 'Update folder access for an agent.',
            'icon' => 'ph:shield-check',
        ],
        'update_agent_integration_access' => [
            'class' => UpdateAgentIntegrationAccess::class,
            'type' => 'write',
            'name' => 'Update Agent Integration Access',
            'description' => 'Update integration access for an agent.',
            'icon' => 'ph:shield-check',
        ],
        // Workspace — Integration management
        'get_integration_setup' => [
            'class' => GetIntegrationSetup::class,
            'type' => 'read',
            'name' => 'Get Integration Setup',
            'description' => 'Get setup information and schema for an integration.',
            'icon' => 'ph:plugs-connected',
        ],
        'update_integration_config' => [
            'class' => UpdateIntegrationConfig::class,
            'type' => 'write',
            'name' => 'Update Integration Config',
            'description' => 'Update configuration settings for an integration.',
            'icon' => 'ph:plugs-connected',
        ],
        'test_integration_connection' => [
            'class' => TestIntegrationConnection::class,
            'type' => 'write',
            'name' => 'Test Integration Connection',
            'description' => 'Test connectivity to an integration.',
            'icon' => 'ph:plugs-connected',
        ],
        'setup_integration_webhook' => [
            'class' => SetupIntegrationWebhook::class,
            'type' => 'write',
            'name' => 'Setup Integration Webhook',
            'description' => 'Configure a webhook for an integration.',
            'icon' => 'ph:plugs-connected',
        ],
        'link_external_user' => [
            'class' => LinkExternalUser::class,
            'type' => 'write',
            'name' => 'Link External User',
            'description' => 'Link an external identity to a workspace user.',
            'icon' => 'ph:link',
        ],
        // Workspace — MCP servers
        'list_mcp_servers' => [
            'class' => ListMcpServers::class,
            'type' => 'read',
            'name' => 'List MCP Servers',
            'description' => 'List all configured MCP servers.',
            'icon' => 'ph:plugs-connected',
        ],
        'add_mcp_server' => [
            'class' => AddMcpServer::class,
            'type' => 'write',
            'name' => 'Add MCP Server',
            'description' => 'Add a new MCP server and auto-discover its tools.',
            'icon' => 'ph:plugs-connected',
        ],
        'update_mcp_server' => [
            'class' => UpdateMcpServer::class,
            'type' => 'write',
            'name' => 'Update MCP Server',
            'description' => 'Update an MCP server\'s configuration.',
            'icon' => 'ph:plugs-connected',
        ],
        'remove_mcp_server' => [
            'class' => RemoveMcpServer::class,
            'type' => 'write',
            'name' => 'Remove MCP Server',
            'description' => 'Remove an MCP server and its tools.',
            'icon' => 'ph:plugs-connected',
        ],
        'test_mcp_server' => [
            'class' => TestMcpServer::class,
            'type' => 'write',
            'name' => 'Test MCP Server',
            'description' => 'Test connection to an MCP server.',
            'icon' => 'ph:plugs-connected',
        ],
        'discover_mcp_tools' => [
            'class' => DiscoverMcpTools::class,
            'type' => 'write',
            'name' => 'Discover MCP Tools',
            'description' => 'Refresh tool discovery for an MCP server.',
            'icon' => 'ph:plugs-connected',
        ],
        // Workspace — Channels
        'create_channel' => [
            'class' => CreateChannel::class,
            'type' => 'write',
            'name' => 'Create Channel',
            'description' => 'Create a new channel.',
            'icon' => 'ph:hash',
        ],
        'add_channel_member' => [
            'class' => AddChannelMember::class,
            'type' => 'write',
            'name' => 'Add Channel Member',
            'description' => 'Add a user to a channel.',
            'icon' => 'ph:user-plus',
        ],
        'remove_channel_member' => [
            'class' => RemoveChannelMember::class,
            'type' => 'write',
            'name' => 'Remove Channel Member',
            'description' => 'Remove a user from a channel.',
            'icon' => 'ph:user-minus',
        ],
        // Workspace — Automation
        'create_automation_rule' => [
            'class' => CreateAutomationRule::class,
            'type' => 'write',
            'name' => 'Create Automation Rule',
            'description' => 'Create a new automation rule.',
            'icon' => 'ph:lightning',
        ],
        'update_automation_rule' => [
            'class' => UpdateAutomationRule::class,
            'type' => 'write',
            'name' => 'Update Automation Rule',
            'description' => 'Update an automation rule.',
            'icon' => 'ph:lightning',
        ],
        'delete_automation_rule' => [
            'class' => DeleteAutomationRule::class,
            'type' => 'write',
            'name' => 'Delete Automation Rule',
            'description' => 'Delete an automation rule.',
            'icon' => 'ph:lightning',
        ],
        'create_item_template' => [
            'class' => CreateItemTemplate::class,
            'type' => 'write',
            'name' => 'Create Item Template',
            'description' => 'Create a list item template for automation.',
            'icon' => 'ph:copy',
        ],
        'update_item_template' => [
            'class' => UpdateItemTemplate::class,
            'type' => 'write',
            'name' => 'Update Item Template',
            'description' => 'Update a list item template.',
            'icon' => 'ph:copy',
        ],
        'delete_item_template' => [
            'class' => DeleteItemTemplate::class,
            'type' => 'write',
            'name' => 'Delete Item Template',
            'description' => 'Delete a list item template.',
            'icon' => 'ph:copy',
        ],
        'create_schedule' => [
            'class' => CreateSchedule::class,
            'type' => 'write',
            'name' => 'Create Schedule',
            'description' => 'Create a scheduled agent task (cron job).',
            'icon' => 'ph:clock',
        ],
        'update_schedule' => [
            'class' => UpdateSchedule::class,
            'type' => 'write',
            'name' => 'Update Schedule',
            'description' => 'Update a scheduled task.',
            'icon' => 'ph:clock',
        ],
        'delete_schedule' => [
            'class' => DeleteSchedule::class,
            'type' => 'write',
            'name' => 'Delete Schedule',
            'description' => 'Delete a scheduled task.',
            'icon' => 'ph:clock',
        ],
        'list_schedules' => [
            'class' => ListSchedules::class,
            'type' => 'read',
            'name' => 'List Schedules',
            'description' => 'List all scheduled tasks and their status.',
            'icon' => 'ph:clock',
        ],
        'enable_schedule' => [
            'class' => EnableSchedule::class,
            'type' => 'write',
            'name' => 'Enable Schedule',
            'description' => 'Enable a scheduled task.',
            'icon' => 'ph:play',
        ],
        'disable_schedule' => [
            'class' => DisableSchedule::class,
            'type' => 'write',
            'name' => 'Disable Schedule',
            'description' => 'Disable a scheduled task.',
            'icon' => 'ph:pause',
        ],
        'trigger_schedule' => [
            'class' => TriggerSchedule::class,
            'type' => 'write',
            'name' => 'Trigger Schedule',
            'description' => 'Immediately trigger a scheduled task.',
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
            $app = $appLookup[$slug] ?? 'other';

            // Code-first: only direct tool groups are registered as AI tools
            if (!in_array($app, self::DIRECT_TOOL_GROUPS)) {
                continue;
            }

            // Skip tools from disabled integrations
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

            // Code-first: only direct tool groups are registered as AI tools
            if (!in_array($app, self::DIRECT_TOOL_GROUPS)) {
                continue;
            }

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

            // Skip tools from non-workspace-enabled integrations entirely
            if ($isIntegration && !$integrationEnabled) {
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
        $appLookup = $this->buildAppLookup();
        $factory = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;
        $builtIn = [];
        $integrations = [];

        foreach ($this->getEffectiveAppGroups() as $appName => $group) {
            $isIntegration = in_array($appName, $this->getEffectiveIntegrationApps());
            $tools = [];

            foreach ($group['tools'] as $slug) {
                $meta = $this->getEffectiveToolMap()[$slug] ?? null;
                if (!$meta) {
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

                    $schema = $tool->schema($factory);
                    if (!empty($schema)) {
                        // Wrap in ObjectType to get proper required array
                        $objectType = $factory->object($schema);
                        $serialized = $objectType->toArray();
                        $requiredParams = $serialized['required'] ?? [];

                        foreach ($serialized['properties'] ?? [] as $paramName => $paramSchema) {
                            $param = [
                                'name' => $paramName,
                                'type' => $paramSchema['type'] ?? 'string',
                                'required' => in_array($paramName, $requiredParams),
                            ];
                            if (!empty($paramSchema['description'])) {
                                $param['description'] = $paramSchema['description'];
                            }
                            if (!empty($paramSchema['enum'])) {
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
    public function instantiateToolBySlug(string $slug, User $agent): ?\Laravel\Ai\Contracts\Tool
    {
        if (!isset($this->getEffectiveToolMap()[$slug])) {
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
            if (!$group) {
                continue;
            }

            // Check which tools in this app the agent has access to
            $hasApproval = false;
            $hasAllowed = false;

            foreach ($group['tools'] as $slug) {
                if (!isset($this->getEffectiveToolMap()[$slug])) {
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

            if (!$hasAllowed) {
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
        $lines[] = 'Use lua_read_doc(namespace) for function signatures and parameters.';
        $lines[] = '';
        $lines[] = app(\App\Services\LuaApiDocGenerator::class)->getNamespaceSummary($agent);

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
            // Chat tools needing permission service
            SendChannelMessage::class => new SendChannelMessage($agent, $this->permissionService),
            ReadRecentMessages::class => new ReadRecentMessages($agent, $this->permissionService),
            ReadThread::class => new ReadThread($agent, $this->permissionService),
            ReadPinnedMessages::class => new ReadPinnedMessages($agent, $this->permissionService),
            ListChannels::class => new ListChannels($agent, $this->permissionService),
            EditMessage::class => new EditMessage($agent, $this->permissionService),
            DeleteMessage::class => new DeleteMessage($agent, $this->permissionService),
            PinMessage::class => new PinMessage($agent, $this->permissionService),
            AddMessageReaction::class => new AddMessageReaction($agent, $this->permissionService),
            RemoveMessageReaction::class => new RemoveMessageReaction($agent, $this->permissionService),
            SearchMessages::class => new SearchMessages($agent, $this->permissionService),
            ListExternalChannels::class => new ListExternalChannels($agent, $this->permissionService),
            JoinExternalChannel::class => new JoinExternalChannel($agent, $this->permissionService),
            LeaveExternalChannel::class => new LeaveExternalChannel($agent, $this->permissionService),
            // Docs tools needing permission service
            ListDocuments::class => new ListDocuments($agent, $this->permissionService),
            GetDocument::class => new GetDocument($agent, $this->permissionService),
            GetDocumentTree::class => new GetDocumentTree($agent, $this->permissionService),
            SearchDocuments::class => new SearchDocuments($agent, $this->permissionService, app(DocumentIndexingService::class)),
            CreateDocument::class => new CreateDocument($agent, $this->permissionService),
            UpdateDocument::class => new UpdateDocument($agent, $this->permissionService),
            DeleteDocument::class => new DeleteDocument($agent, $this->permissionService),
            AddDocumentComment::class => new AddDocumentComment($agent, $this->permissionService),
            ResolveDocumentComment::class => new ResolveDocumentComment($agent, $this->permissionService),
            DeleteDocumentComment::class => new DeleteDocumentComment($agent, $this->permissionService),
            ListDocumentComments::class => new ListDocumentComments($agent, $this->permissionService),
            ListDocumentVersions::class => new ListDocumentVersions($agent, $this->permissionService),
            RestoreDocumentVersion::class => new RestoreDocumentVersion($agent, $this->permissionService),
            ListDocumentAttachments::class => new ListDocumentAttachments($agent, $this->permissionService),
            // Tools needing only agent
            ListTables::class => new ListTables($agent),
            GetTable::class => new GetTable($agent),
            GetTableRows::class => new GetTableRows($agent),
            SearchTableRows::class => new SearchTableRows($agent),
            CreateTable::class => new CreateTable($agent),
            UpdateTable::class => new UpdateTable($agent),
            DeleteTable::class => new DeleteTable($agent),
            AddTableColumn::class => new AddTableColumn($agent),
            UpdateTableColumn::class => new UpdateTableColumn($agent),
            DeleteTableColumn::class => new DeleteTableColumn($agent),
            AddTableRow::class => new AddTableRow($agent),
            UpdateTableRow::class => new UpdateTableRow($agent),
            DeleteTableRow::class => new DeleteTableRow($agent),
            BulkAddTableRows::class => new BulkAddTableRows($agent),
            BulkDeleteTableRows::class => new BulkDeleteTableRows($agent),
            ReorderTableColumns::class => new ReorderTableColumns($agent),
            ListTableViews::class => new ListTableViews($agent),
            CreateTableView::class => new CreateTableView($agent),
            UpdateTableView::class => new UpdateTableView($agent),
            DeleteTableView::class => new DeleteTableView($agent),
            ListCalendarEvents::class => new ListCalendarEvents($agent),
            GetCalendarEvent::class => new GetCalendarEvent($agent),
            CreateCalendarEvent::class => new CreateCalendarEvent($agent),
            UpdateCalendarEvent::class => new UpdateCalendarEvent($agent),
            DeleteCalendarEvent::class => new DeleteCalendarEvent($agent),
            UpdateCalendarAttendee::class => new UpdateCalendarAttendee($agent),
            RemoveCalendarAttendee::class => new RemoveCalendarAttendee($agent),
            ListAllItems::class => new ListAllItems($agent),
            GetListItem::class => new GetListItem($agent),
            ListItemsByStatus::class => new ListItemsByStatus($agent),
            ListItemsByAssignee::class => new ListItemsByAssignee($agent),
            ListProjects::class => new ListProjects($agent),
            ListItemStatuses::class => new ListItemStatuses($agent),
            CreateListItem::class => new CreateListItem($agent),
            UpdateListItem::class => new UpdateListItem($agent),
            DeleteListItem::class => new DeleteListItem($agent),
            AddListItemComment::class => new AddListItemComment($agent),
            DeleteListItemComment::class => new DeleteListItemComment($agent),
            CreateListStatus::class => new CreateListStatus($agent),
            UpdateListStatus::class => new UpdateListStatus($agent),
            DeleteListStatus::class => new DeleteListStatus($agent),
            UpdateTask::class => new UpdateTask($agent),
            AddTaskStep::class => new AddTaskStep($agent),
            UpdateTaskStep::class => new UpdateTaskStep($agent),
            SetTaskStatus::class => new SetTaskStatus($agent),
            CreateTaskStep::class => new CreateTaskStep($agent),
            RenderSvg::class => new RenderSvg(),
            // Lua scripting
            Lua\LuaListDocs::class => new Lua\LuaListDocs(app(\App\Services\LuaApiDocGenerator::class), $agent),
            Lua\LuaSearchDocs::class => new Lua\LuaSearchDocs(app(\App\Services\LuaApiDocGenerator::class), $agent),
            Lua\LuaReadDoc::class => new Lua\LuaReadDoc(app(\App\Services\LuaApiDocGenerator::class), $agent),
            Lua\LuaExec::class => new Lua\LuaExec(
                app(\App\Services\LuaSandboxService::class),
                $this,
                app(\App\Services\LuaApiDocGenerator::class),
                $agent,
            ),
            WaitForApproval::class => new WaitForApproval($agent),
            Wait::class => new Wait($agent),
            // Workspace — Query
            ListAgents::class => new ListAgents(),
            ListMembers::class => new ListMembers(),
            GetAgentDetails::class => new GetAgentDetails(),
            GetAgentPermissions::class => new GetAgentPermissions($this->permissionService, $this),
            ListIntegrations::class => new ListIntegrations(),
            GetIntegrationConfig::class => new GetIntegrationConfig($agent),
            ListAvailableModels::class => new ListAvailableModels(),
            ListAutomationRules::class => new ListAutomationRules(),
            // Workspace — Agent management
            CreateAgent::class => new CreateAgent($agent, app(AgentDocumentService::class), app(AgentAvatarService::class)),
            UpdateAgent::class => new UpdateAgent($agent),
            DeleteAgent::class => new DeleteAgent($agent, app(AgentDocumentService::class)),
            ReadAgentIdentityFile::class => new ReadAgentIdentityFile($agent, app(AgentDocumentService::class)),
            UpdateAgentIdentityFile::class => new UpdateAgentIdentityFile($agent, app(AgentDocumentService::class)),
            // Workspace — Permissions
            UpdateAgentToolPermissions::class => new UpdateAgentToolPermissions($agent, $this->permissionService),
            UpdateAgentChannelAccess::class => new UpdateAgentChannelAccess($agent, $this->permissionService),
            UpdateAgentFolderAccess::class => new UpdateAgentFolderAccess($agent, $this->permissionService),
            UpdateAgentIntegrationAccess::class => new UpdateAgentIntegrationAccess($agent, $this->permissionService),
            // Workspace — Integration management
            GetIntegrationSetup::class => new GetIntegrationSetup($agent),
            UpdateIntegrationConfig::class => new UpdateIntegrationConfig($agent),
            TestIntegrationConnection::class => new TestIntegrationConnection($agent),
            SetupIntegrationWebhook::class => new SetupIntegrationWebhook($agent),
            LinkExternalUser::class => new LinkExternalUser($agent),
            // Workspace — MCP servers
            ListMcpServers::class => new ListMcpServers($agent),
            AddMcpServer::class => new AddMcpServer($agent),
            UpdateMcpServer::class => new UpdateMcpServer($agent),
            RemoveMcpServer::class => new RemoveMcpServer($agent),
            TestMcpServer::class => new TestMcpServer($agent),
            DiscoverMcpTools::class => new DiscoverMcpTools($agent),
            // Workspace — Channels
            CreateChannel::class => new CreateChannel($agent),
            AddChannelMember::class => new AddChannelMember($agent),
            RemoveChannelMember::class => new RemoveChannelMember($agent),
            // Workspace — Automation
            CreateAutomationRule::class => new CreateAutomationRule($agent),
            UpdateAutomationRule::class => new UpdateAutomationRule($agent),
            DeleteAutomationRule::class => new DeleteAutomationRule($agent),
            CreateItemTemplate::class => new CreateItemTemplate($agent),
            UpdateItemTemplate::class => new UpdateItemTemplate($agent),
            DeleteItemTemplate::class => new DeleteItemTemplate($agent),
            CreateSchedule::class => new CreateSchedule($agent),
            UpdateSchedule::class => new UpdateSchedule($agent),
            DeleteSchedule::class => new DeleteSchedule($agent),
            ListSchedules::class => new ListSchedules($agent),
            EnableSchedule::class => new EnableSchedule($agent),
            DisableSchedule::class => new DisableSchedule($agent),
            TriggerSchedule::class => new TriggerSchedule($agent),
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

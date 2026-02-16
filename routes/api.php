<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AutomationRuleController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\DirectMessageController;
use App\Http\Controllers\Api\DocumentAttachmentController;
use App\Http\Controllers\Api\DocumentCommentController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentVersionController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\ListItemController;
use App\Http\Controllers\Api\ListItemCommentController;
use App\Http\Controllers\Api\ListStatusController;
use App\Http\Controllers\Api\ListTemplateController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\CalendarEventAttendeeController;
use App\Http\Controllers\Api\CalendarFeedController;
use App\Http\Controllers\Api\DataTableController;
use App\Http\Controllers\Api\DataTableColumnController;
use App\Http\Controllers\Api\DataTableRowController;
use App\Http\Controllers\Api\DataTableViewController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AgentPermissionController;
use App\Http\Controllers\Api\DmController;
use App\Http\Controllers\Api\CodexAuthController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\ScheduledAutomationController;
use App\Http\Controllers\Api\McpServerController;
use App\Http\Controllers\Api\PrismServerController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Api\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

// ─── Webhooks (external, no auth, no workspace) ───────────────────
Route::post('/webhooks/telegram', [TelegramWebhookController::class, 'handle']);

// ─── Invitation acceptance (no workspace middleware needed) ───────
Route::post('/invitations/{token}/accept', [\App\Http\Controllers\Api\InvitationController::class, 'accept']);

// ─── Workspace creation (auth required, no workspace context) ─────
Route::post('/workspaces', [WorkspaceController::class, 'store']);

// ─── All authenticated + workspace-scoped routes ──────────────────
Route::middleware('resolve.workspace')->group(function () {

    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/agents', [UserController::class, 'agents']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::get('/users/{id}/activity', [UserController::class, 'activity']);
    Route::patch('/users/{id}', [UserController::class, 'update']);
    Route::patch('/users/{id}/presence', [UserController::class, 'updatePresence']);

    // Channels
    Route::get('/channels', [ChannelController::class, 'index']);
    Route::post('/channels', [ChannelController::class, 'store']);
    Route::get('/channels/{id}', [ChannelController::class, 'show']);
    Route::get('/channels/{id}/pinned', [ChannelController::class, 'pinned']);
    Route::post('/channels/{id}/members', [ChannelController::class, 'addMember']);
    Route::delete('/channels/{channelId}/members/{userId}', [ChannelController::class, 'removeMember']);
    Route::post('/channels/{id}/read', [ChannelController::class, 'markRead']);
    Route::post('/channels/{id}/typing', [ChannelController::class, 'typing']);
    Route::post('/channels/{id}/compact', [MessageController::class, 'compact']);

    // Messages
    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::delete('/messages/{id}', [MessageController::class, 'destroy']);
    Route::post('/messages/attachments', [MessageController::class, 'uploadAttachment']);
    Route::post('/messages/{id}/reactions', [MessageController::class, 'addReaction']);
    Route::delete('/messages/{messageId}/reactions/{reactionId}', [MessageController::class, 'removeReaction']);
    Route::get('/messages/{id}/thread', [MessageController::class, 'thread']);
    Route::post('/messages/{id}/pin', [MessageController::class, 'pin']);

    // List Statuses
    Route::get('/list-statuses', [ListStatusController::class, 'index']);
    Route::post('/list-statuses', [ListStatusController::class, 'store']);
    Route::post('/list-statuses/reorder', [ListStatusController::class, 'reorder']);
    Route::patch('/list-statuses/{id}', [ListStatusController::class, 'update']);
    Route::delete('/list-statuses/{id}', [ListStatusController::class, 'destroy']);

    // List Items (formerly Tasks - kanban board items)
    Route::get('/list-items', [ListItemController::class, 'index']);
    Route::post('/list-items', [ListItemController::class, 'store']);
    Route::post('/list-items/reorder', [ListItemController::class, 'reorder']);
    Route::get('/list-items/{id}', [ListItemController::class, 'show']);
    Route::patch('/list-items/{id}', [ListItemController::class, 'update']);
    Route::delete('/list-items/{id}', [ListItemController::class, 'destroy']);

    // List Item Comments
    Route::get('/list-items/{listItemId}/comments', [ListItemCommentController::class, 'index']);
    Route::post('/list-items/{listItemId}/comments', [ListItemCommentController::class, 'store']);
    Route::delete('/list-items/{listItemId}/comments/{commentId}', [ListItemCommentController::class, 'destroy']);

    // List Templates
    Route::get('/list-templates', [ListTemplateController::class, 'index']);
    Route::post('/list-templates', [ListTemplateController::class, 'store']);
    Route::patch('/list-templates/{id}', [ListTemplateController::class, 'update']);
    Route::delete('/list-templates/{id}', [ListTemplateController::class, 'destroy']);
    Route::post('/list-templates/{id}/create-item', [ListTemplateController::class, 'createListItem']);

    // Tasks (discrete work items - cases)
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{id}', [TaskController::class, 'show'])->whereUuid('id');
    Route::patch('/tasks/{id}', [TaskController::class, 'update'])->whereUuid('id');
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy'])->whereUuid('id');

    // Task Lifecycle
    Route::post('/tasks/{id}/start', [TaskController::class, 'start'])->whereUuid('id');
    Route::post('/tasks/{id}/pause', [TaskController::class, 'pause'])->whereUuid('id');
    Route::post('/tasks/{id}/resume', [TaskController::class, 'resume'])->whereUuid('id');
    Route::post('/tasks/{id}/complete', [TaskController::class, 'complete'])->whereUuid('id');
    Route::post('/tasks/{id}/fail', [TaskController::class, 'fail'])->whereUuid('id');
    Route::post('/tasks/{id}/cancel', [TaskController::class, 'cancel'])->whereUuid('id');

    // Task Steps
    Route::get('/tasks/{id}/steps', [TaskController::class, 'steps'])->whereUuid('id');
    Route::post('/tasks/{id}/steps', [TaskController::class, 'addStep'])->whereUuid('id');
    Route::patch('/tasks/{taskId}/steps/{stepId}', [TaskController::class, 'updateStep'])->whereUuid('taskId')->whereUuid('stepId');
    Route::post('/tasks/{taskId}/steps/{stepId}/complete', [TaskController::class, 'completeStep'])->whereUuid('taskId')->whereUuid('stepId');

    // Automation Rules (for list items)
    Route::get('/automation-rules', [AutomationRuleController::class, 'index']);
    Route::post('/automation-rules', [AutomationRuleController::class, 'store']);
    Route::patch('/automation-rules/{id}', [AutomationRuleController::class, 'update']);
    Route::delete('/automation-rules/{id}', [AutomationRuleController::class, 'destroy']);

    // Scheduled Automations
    Route::get('/scheduled-automations', [ScheduledAutomationController::class, 'index']);
    Route::get('/scheduled-automations/preview-schedule', [ScheduledAutomationController::class, 'previewSchedule']);
    Route::post('/scheduled-automations', [ScheduledAutomationController::class, 'store']);
    Route::get('/scheduled-automations/{id}', [ScheduledAutomationController::class, 'show']);
    Route::patch('/scheduled-automations/{id}', [ScheduledAutomationController::class, 'update']);
    Route::delete('/scheduled-automations/{id}', [ScheduledAutomationController::class, 'destroy']);
    Route::get('/scheduled-automations/{id}/runs', [ScheduledAutomationController::class, 'runs']);
    Route::post('/scheduled-automations/{id}/run', [ScheduledAutomationController::class, 'triggerRun']);

    // Documents
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::get('/documents/search', [DocumentController::class, 'search']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/documents/{id}', [DocumentController::class, 'show']);
    Route::patch('/documents/{id}', [DocumentController::class, 'update']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

    // Document Comments
    Route::get('/documents/{documentId}/comments', [DocumentCommentController::class, 'index']);
    Route::post('/documents/{documentId}/comments', [DocumentCommentController::class, 'store']);
    Route::patch('/documents/{documentId}/comments/{commentId}', [DocumentCommentController::class, 'update']);
    Route::delete('/documents/{documentId}/comments/{commentId}', [DocumentCommentController::class, 'destroy']);

    // Document Versions
    Route::get('/documents/{documentId}/versions', [DocumentVersionController::class, 'index']);
    Route::post('/documents/{documentId}/versions/{versionId}/restore', [DocumentVersionController::class, 'restore']);

    // Document Attachments
    Route::get('/documents/{documentId}/attachments', [DocumentAttachmentController::class, 'index']);
    Route::post('/documents/{documentId}/attachments', [DocumentAttachmentController::class, 'store']);
    Route::delete('/documents/{documentId}/attachments/{attachmentId}', [DocumentAttachmentController::class, 'destroy']);

    // Approvals
    Route::get('/approvals', [ApprovalController::class, 'index']);
    Route::get('/approvals/{id}', [ApprovalController::class, 'show']);
    Route::post('/approvals', [ApprovalController::class, 'store']);
    Route::patch('/approvals/{id}', [ApprovalController::class, 'update']);

    // Activities
    Route::get('/activities', [ActivityController::class, 'index']);

    // Stats
    Route::get('/stats', [StatsController::class, 'index']);
    Route::patch('/stats', [StatsController::class, 'update']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::patch('/notifications/{id}', [NotificationController::class, 'update']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::get('/notifications/count', [NotificationController::class, 'count']);

    // Direct Messages
    Route::get('/direct-messages', [DirectMessageController::class, 'index']);
    Route::post('/direct-messages', [DirectMessageController::class, 'store']);
    Route::get('/direct-messages/unread-count', [DirectMessageController::class, 'unreadCount']);
    Route::get('/direct-messages/{id}', [DirectMessageController::class, 'show']);
    Route::post('/direct-messages/{id}/read', [DirectMessageController::class, 'markRead']);

    // DM Conversation API (for frontend)
    Route::get('/dm/{userId}', [DmController::class, 'show']);
    Route::post('/dm/{userId}', [DmController::class, 'store']);
    Route::post('/dm/{userId}/read', [DmController::class, 'markRead']);

    // Search
    Route::get('/search', [SearchController::class, 'index']);

    // Calendar Events
    Route::get('/calendar/events/export.ics', [CalendarEventController::class, 'export']);
    Route::post('/calendar/events/import', [CalendarEventController::class, 'import']);
    Route::post('/calendar/events/import-url', [CalendarEventController::class, 'importFromUrl']);

    // Calendar Feeds
    Route::get('/calendar/feeds', [CalendarFeedController::class, 'index']);
    Route::post('/calendar/feeds', [CalendarFeedController::class, 'store']);
    Route::delete('/calendar/feeds/{id}', [CalendarFeedController::class, 'destroy']);
    Route::get('/calendar/events', [CalendarEventController::class, 'index']);
    Route::post('/calendar/events', [CalendarEventController::class, 'store']);
    Route::get('/calendar/events/{id}', [CalendarEventController::class, 'show']);
    Route::patch('/calendar/events/{id}', [CalendarEventController::class, 'update']);
    Route::delete('/calendar/events/{id}', [CalendarEventController::class, 'destroy']);

    // Calendar Event Attendees
    Route::post('/calendar/events/{eventId}/attendees', [CalendarEventAttendeeController::class, 'store']);
    Route::patch('/calendar/events/{eventId}/attendees/{attendeeId}', [CalendarEventAttendeeController::class, 'update']);
    Route::delete('/calendar/events/{eventId}/attendees/{attendeeId}', [CalendarEventAttendeeController::class, 'destroy']);

    // Data Tables
    Route::get('/tables', [DataTableController::class, 'index']);
    Route::post('/tables', [DataTableController::class, 'store']);
    Route::get('/tables/{id}', [DataTableController::class, 'show']);
    Route::patch('/tables/{id}', [DataTableController::class, 'update']);
    Route::delete('/tables/{id}', [DataTableController::class, 'destroy']);

    // Data Table Columns
    Route::get('/tables/{tableId}/columns', [DataTableColumnController::class, 'index']);
    Route::post('/tables/{tableId}/columns', [DataTableColumnController::class, 'store']);
    Route::patch('/tables/{tableId}/columns/{columnId}', [DataTableColumnController::class, 'update']);
    Route::delete('/tables/{tableId}/columns/{columnId}', [DataTableColumnController::class, 'destroy']);
    Route::post('/tables/{tableId}/columns/reorder', [DataTableColumnController::class, 'reorder']);

    // Data Table Rows
    Route::get('/tables/{tableId}/rows', [DataTableRowController::class, 'index']);
    Route::post('/tables/{tableId}/rows', [DataTableRowController::class, 'store']);
    Route::get('/tables/{tableId}/rows/{rowId}', [DataTableRowController::class, 'show']);
    Route::patch('/tables/{tableId}/rows/{rowId}', [DataTableRowController::class, 'update']);
    Route::delete('/tables/{tableId}/rows/{rowId}', [DataTableRowController::class, 'destroy']);
    Route::post('/tables/{tableId}/rows/bulk', [DataTableRowController::class, 'bulkCreate']);
    Route::post('/tables/{tableId}/rows/bulk-delete', [DataTableRowController::class, 'bulkDelete']);

    // Data Table Views
    Route::get('/tables/{tableId}/views', [DataTableViewController::class, 'index']);
    Route::post('/tables/{tableId}/views', [DataTableViewController::class, 'store']);
    Route::patch('/tables/{tableId}/views/{viewId}', [DataTableViewController::class, 'update']);
    Route::delete('/tables/{tableId}/views/{viewId}', [DataTableViewController::class, 'destroy']);

    // Agents
    Route::get('/agents', [AgentController::class, 'index']);
    Route::post('/agents', [AgentController::class, 'store']);
    Route::get('/agents/{id}', [AgentController::class, 'show']);
    Route::patch('/agents/{id}', [AgentController::class, 'update']);
    Route::delete('/agents/{id}', [AgentController::class, 'destroy']);
    Route::get('/agents/{id}/identity', [AgentController::class, 'identityFiles']);
    Route::put('/agents/{id}/identity/{fileType}', [AgentController::class, 'updateIdentityFile']);

    // Agent Permissions
    Route::get('/agents/{id}/permissions', [AgentPermissionController::class, 'index']);
    Route::put('/agents/{id}/permissions/tools', [AgentPermissionController::class, 'updateTools']);
    Route::put('/agents/{id}/permissions/channels', [AgentPermissionController::class, 'updateChannels']);
    Route::put('/agents/{id}/permissions/folders', [AgentPermissionController::class, 'updateFolders']);
    Route::put('/agents/{id}/permissions/integrations', [AgentPermissionController::class, 'updateIntegrations']);

    // Integrations (read-only for all members)
    Route::get('/integrations', [IntegrationController::class, 'index']);
    Route::get('/integrations/models', [IntegrationController::class, 'enabledModels']);
    Route::get('/integrations/all-providers', [IntegrationController::class, 'allProviders']);
    Route::get('/integrations/embedding-models', [IntegrationController::class, 'embeddingModels']);
    Route::get('/integrations/reranking-models', [IntegrationController::class, 'rerankingModels']);

    // Codex Auth (OAuth)
    Route::get('/integrations/codex/auth/status', [CodexAuthController::class, 'status']);
    Route::post('/integrations/codex/auth/device', [CodexAuthController::class, 'device']);
    Route::post('/integrations/codex/auth/device/poll', [CodexAuthController::class, 'devicePoll']);
    Route::post('/integrations/codex/auth/logout', [CodexAuthController::class, 'logout']);
    Route::post('/integrations/codex/test', [CodexAuthController::class, 'test']);

    // Workspace management
    Route::get('/workspace', [WorkspaceController::class, 'show']);
    Route::patch('/workspace', [WorkspaceController::class, 'update']);

    // Workspace members
    Route::get('/workspace/members', [WorkspaceMemberController::class, 'index']);

    // ─── Admin-only routes ────────────────────────────────────────
    Route::middleware('workspace.admin')->group(function () {
        // Settings
        Route::get('/settings', [SettingController::class, 'index']);
        Route::patch('/settings', [SettingController::class, 'update']);
        Route::post('/settings/danger-action', [SettingController::class, 'dangerAction']);

        // Integration config (admin-only)
        Route::get('/integrations/{id}/config', [IntegrationController::class, 'showConfig']);
        Route::put('/integrations/{id}/config', [IntegrationController::class, 'updateConfig']);
        Route::post('/integrations/{id}/test', [IntegrationController::class, 'testConnection']);
        Route::post('/integrations/{id}/disconnect', [IntegrationController::class, 'disconnect']);
        Route::post('/integrations/{id}/fetch-models', [IntegrationController::class, 'fetchModels']);
        Route::post('/integrations/{id}/setup-webhook', [IntegrationController::class, 'setupWebhook']);
        Route::get('/integrations/external-identities', [IntegrationController::class, 'externalIdentities']);
        Route::post('/integrations/link-user', [IntegrationController::class, 'linkExternalUser']);
        Route::delete('/integrations/link-user/{identityId}', [IntegrationController::class, 'unlinkExternalUser']);

        // Ollama (admin-only)
        Route::get('/integrations/ollama/status', [IntegrationController::class, 'ollamaModelStatus']);
        Route::post('/integrations/ollama/pull', [IntegrationController::class, 'ollamaPullModel']);

        // MCP Servers (admin-only)
        Route::get('/mcp-servers', [McpServerController::class, 'index']);
        Route::post('/mcp-servers', [McpServerController::class, 'store']);
        Route::get('/mcp-servers/{id}', [McpServerController::class, 'show']);
        Route::patch('/mcp-servers/{id}', [McpServerController::class, 'update']);
        Route::delete('/mcp-servers/{id}', [McpServerController::class, 'destroy']);
        Route::post('/mcp-servers/{id}/test', [McpServerController::class, 'testConnection']);
        Route::post('/mcp-servers/{id}/discover', [McpServerController::class, 'discoverTools']);

        // Prism Server (admin-only)
        Route::get('/prism-server/config', [PrismServerController::class, 'config']);
        Route::put('/prism-server/config', [PrismServerController::class, 'updateConfig']);
        Route::get('/prism-server/api-keys', [PrismServerController::class, 'apiKeys']);
        Route::post('/prism-server/api-keys', [PrismServerController::class, 'createApiKey']);
        Route::delete('/prism-server/api-keys/{id}', [PrismServerController::class, 'deleteApiKey']);

        // Workspace member management (admin-only)
        Route::post('/workspace/members/invite', [WorkspaceMemberController::class, 'invite']);
        Route::patch('/workspace/members/{id}/role', [WorkspaceMemberController::class, 'updateRole']);
        Route::delete('/workspace/members/{id}', [WorkspaceMemberController::class, 'remove']);
        Route::post('/workspace/invitations/{id}/resend', [WorkspaceMemberController::class, 'resendInvite']);
        Route::delete('/workspace/invitations/{id}', [WorkspaceMemberController::class, 'cancelInvite']);
    });
});

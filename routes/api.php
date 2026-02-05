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
use App\Http\Controllers\Api\ListTemplateController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\CalendarEventAttendeeController;
use App\Http\Controllers\Api\DataTableController;
use App\Http\Controllers\Api\DataTableColumnController;
use App\Http\Controllers\Api\DataTableRowController;
use App\Http\Controllers\Api\DataTableViewController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\DmController;
use App\Http\Controllers\Api\IntegrationController;
use Illuminate\Support\Facades\Route;

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

// Messages
Route::get('/messages', [MessageController::class, 'index']);
Route::post('/messages', [MessageController::class, 'store']);
Route::delete('/messages/{id}', [MessageController::class, 'destroy']);
Route::post('/messages/attachments', [MessageController::class, 'uploadAttachment']);
Route::post('/messages/{id}/reactions', [MessageController::class, 'addReaction']);
Route::delete('/messages/{messageId}/reactions/{reactionId}', [MessageController::class, 'removeReaction']);
Route::get('/messages/{id}/thread', [MessageController::class, 'thread']);
Route::post('/messages/{id}/pin', [MessageController::class, 'pin']);

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
Route::get('/tasks/{id}', [TaskController::class, 'show']);
Route::patch('/tasks/{id}', [TaskController::class, 'update']);
Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);

// Task Lifecycle
Route::post('/tasks/{id}/start', [TaskController::class, 'start']);
Route::post('/tasks/{id}/pause', [TaskController::class, 'pause']);
Route::post('/tasks/{id}/resume', [TaskController::class, 'resume']);
Route::post('/tasks/{id}/complete', [TaskController::class, 'complete']);
Route::post('/tasks/{id}/fail', [TaskController::class, 'fail']);
Route::post('/tasks/{id}/cancel', [TaskController::class, 'cancel']);

// Task Steps
Route::get('/tasks/{id}/steps', [TaskController::class, 'steps']);
Route::post('/tasks/{id}/steps', [TaskController::class, 'addStep']);
Route::patch('/tasks/{taskId}/steps/{stepId}', [TaskController::class, 'updateStep']);
Route::post('/tasks/{taskId}/steps/{stepId}/complete', [TaskController::class, 'completeStep']);

// Automation Rules (for list items)
Route::get('/automation-rules', [AutomationRuleController::class, 'index']);
Route::post('/automation-rules', [AutomationRuleController::class, 'store']);
Route::patch('/automation-rules/{id}', [AutomationRuleController::class, 'update']);
Route::delete('/automation-rules/{id}', [AutomationRuleController::class, 'destroy']);

// Documents
Route::get('/documents', [DocumentController::class, 'index']);
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

// Integrations
Route::get('/integrations', [IntegrationController::class, 'index']);
Route::get('/integrations/models', [IntegrationController::class, 'enabledModels']);
Route::get('/integrations/{id}/config', [IntegrationController::class, 'showConfig']);
Route::put('/integrations/{id}/config', [IntegrationController::class, 'updateConfig']);
Route::post('/integrations/{id}/test', [IntegrationController::class, 'testConnection']);

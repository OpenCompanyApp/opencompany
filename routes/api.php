<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AutomationRuleController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\DirectMessageController;
use App\Http\Controllers\Api\DocumentAttachmentController;
use App\Http\Controllers\Api\DocumentCommentController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentVersionController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TaskCommentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskTemplateController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Users
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/agents', [UserController::class, 'agents']);
Route::get('/users/{id}', [UserController::class, 'show']);
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

// Tasks
Route::get('/tasks', [TaskController::class, 'index']);
Route::post('/tasks', [TaskController::class, 'store']);
Route::post('/tasks/reorder', [TaskController::class, 'reorder']);
Route::get('/tasks/{id}', [TaskController::class, 'show']);
Route::patch('/tasks/{id}', [TaskController::class, 'update']);
Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);

// Task Comments
Route::get('/tasks/{taskId}/comments', [TaskCommentController::class, 'index']);
Route::post('/tasks/{taskId}/comments', [TaskCommentController::class, 'store']);
Route::delete('/tasks/{taskId}/comments/{commentId}', [TaskCommentController::class, 'destroy']);

// Task Templates
Route::get('/task-templates', [TaskTemplateController::class, 'index']);
Route::post('/task-templates', [TaskTemplateController::class, 'store']);
Route::patch('/task-templates/{id}', [TaskTemplateController::class, 'update']);
Route::delete('/task-templates/{id}', [TaskTemplateController::class, 'destroy']);
Route::post('/task-templates/{id}/create-task', [TaskTemplateController::class, 'createTask']);

// Automation Rules
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

// Credits
Route::get('/credits', [CreditController::class, 'index']);

// Direct Messages
Route::get('/direct-messages', [DirectMessageController::class, 'index']);
Route::post('/direct-messages', [DirectMessageController::class, 'store']);
Route::get('/direct-messages/unread-count', [DirectMessageController::class, 'unreadCount']);
Route::get('/direct-messages/{id}', [DirectMessageController::class, 'show']);
Route::post('/direct-messages/{id}/read', [DirectMessageController::class, 'markRead']);

// Search
Route::get('/search', [SearchController::class, 'index']);

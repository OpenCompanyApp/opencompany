<?php

namespace App\Domain\Chat\Telegram\Application;

use App\Events\TaskUpdated;
use App\Jobs\AgentRespondJob;
use App\Models\ApprovalRequest;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Task;
use App\Models\TelegramConversation;
use App\Models\TelegramDelivery;
use App\Models\TelegramInteraction;
use App\Models\TelegramSubscription;
use App\Models\Workspace;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Routes OpenCompany operational events to Telegram subscription lanes.
 *
 * Telegram subscriptions are configured from chat commands and Mini App panels,
 * but the actual delivery remains app-owned here. The router intentionally
 * sends compact status cards only; dense task inspection stays in OpenCompany
 * or the Telegram Mini App so chat notifications do not leak task internals.
 */
class TelegramNotificationRouter
{
    public function handleApprovalNeeded(ApprovalRequest $approval): void
    {
        $approval = $approval->fresh(['channel', 'requester']) ?? $approval;
        $workspaceId = $approval->channel?->workspace_id ?: $approval->requester?->workspace_id;
        if (! $workspaceId) {
            return;
        }

        TelegramSubscription::where('workspace_id', $workspaceId)
            ->where('event_type', 'approval_needed')
            ->where('severity', 'high')
            ->where('enabled', true)
            ->get()
            ->each(fn (TelegramSubscription $subscription) => $this->notifyApprovalNeededSubscription($subscription, $approval, $workspaceId));
    }

    public function handleTaskUpdated(TaskUpdated $event): void
    {
        $task = $event->task->fresh(['agent', 'requester']);
        if (! $task) {
            return;
        }

        $this->refreshTaskStatusCards($task, $event->action);

        if (in_array($event->action, ['completed', 'failed', 'cancelled'], true)) {
            $this->dispatchNextQueuedTelegramLaneTask($task);
        }

        $eventType = match ($event->action) {
            'completed' => 'task_completed',
            'failed' => 'task_failed',
            default => null,
        };

        if (! $eventType) {
            return;
        }

        $severity = $eventType === 'task_failed' ? 'high' : 'normal';

        TelegramSubscription::where('workspace_id', $task->workspace_id)
            ->where('event_type', $eventType)
            ->where('severity', $severity)
            ->where('enabled', true)
            ->get()
            ->each(fn (TelegramSubscription $subscription) => $this->notifySubscription($subscription, $task, $eventType, $severity));

        if ($eventType === 'task_failed' && $task->source === Task::SOURCE_AUTOMATION) {
            $this->handleAutomationFailure($task);
        }
    }

    private function dispatchNextQueuedTelegramLaneTask(Task $finishedTask): void
    {
        if (! $finishedTask->channel_id) {
            return;
        }

        $channel = Channel::where('workspace_id', $finishedTask->workspace_id)
            ->where('id', $finishedTask->channel_id)
            ->where('external_provider', 'telegram')
            ->first();

        if (! $channel) {
            return;
        }

        $activeExists = Task::where('workspace_id', $finishedTask->workspace_id)
            ->where('channel_id', $channel->id)
            ->where('id', '!=', $finishedTask->id)
            ->whereIn('status', [Task::STATUS_ACTIVE, Task::STATUS_PAUSED])
            ->exists();

        if ($activeExists) {
            return;
        }

        $nextTask = Task::with(['agent', 'triggerMessage'])
            ->where('workspace_id', $finishedTask->workspace_id)
            ->where('channel_id', $channel->id)
            ->where('source', Task::SOURCE_CHAT)
            ->where('status', Task::STATUS_PENDING)
            ->whereNotNull('trigger_message_id')
            ->oldest()
            ->get()
            ->first(function (Task $task) {
                $queue = is_array($task->context) ? ($task->context['telegram_lane_queue'] ?? null) : null;

                return is_array($queue) && empty($queue['dispatched_at']);
            });

        if (! $nextTask || ! $nextTask->agent || ! $nextTask->triggerMessage) {
            return;
        }

        $context = $nextTask->context ?? [];
        $queue = is_array($context['telegram_lane_queue'] ?? null) ? $context['telegram_lane_queue'] : [];
        $queue['dispatched_at'] = now()->toIso8601String();
        $queue['dispatched_after_task_id'] = $finishedTask->id;
        $context['telegram_lane_queue'] = $queue;
        $nextTask->update(['context' => $context]);

        AgentRespondJob::dispatch($nextTask->triggerMessage, $nextTask->agent, $channel->id, $nextTask->id);
    }

    private function refreshTaskStatusCards(Task $task, string $action): void
    {
        if (! in_array($action, ['started', 'progress', 'retrying', 'completed', 'failed', 'cancelled'], true)) {
            return;
        }

        $deliveries = TelegramDelivery::where('workspace_id', $task->workspace_id)
            ->where('source_type', Task::class)
            ->where('source_id', $task->id)
            ->where('status', 'sent')
            ->whereNotNull('telegram_message_id')
            ->whereIn('renderer_version', [
                TelegramCardRenderer::RUN_CARD_VERSION,
                TelegramCardRenderer::TASK_CARD_VERSION,
                TelegramCardRenderer::TASK_CARD_UPDATED_VERSION,
            ])
            ->latest('sent_at')
            ->latest('created_at')
            ->limit(12)
            ->get()
            ->unique(fn (TelegramDelivery $delivery) => implode(':', [
                $delivery->chat_id,
                $delivery->topic_id ?: '-',
                $delivery->direct_messages_topic_id ?: '-',
                $delivery->telegram_message_id,
            ]))
            ->take(3)
            ->values();

        if ($deliveries->isEmpty()) {
            return;
        }

        $task = $task->fresh(['agent', 'steps', 'workspace']) ?? $task;
        $text = app(TelegramCardRenderer::class)->cardHtml(
            app(TelegramCardRenderer::class)->taskCardText($task)
        );

        foreach ($deliveries as $previousDelivery) {
            $replyMarkup = $this->taskReplyMarkup($previousDelivery->integration_setting_id, $task);
            $previousPayload = is_array($previousDelivery->request_payload) ? $previousDelivery->request_payload : [];
            if (($previousPayload['text'] ?? null) === $text && ($previousPayload['reply_markup'] ?? null) == $replyMarkup) {
                continue;
            }

            $alreadyRendered = TelegramDelivery::where('workspace_id', $task->workspace_id)
                ->where('source_type', Task::class)
                ->where('source_id', $task->id)
                ->where('telegram_message_id', $previousDelivery->telegram_message_id)
                ->where('renderer_version', TelegramCardRenderer::TASK_CARD_UPDATED_VERSION)
                ->whereIn('status', ['pending', 'sent'])
                ->where('request_payload->lifecycle_action', $action)
                ->where('request_payload->text', $text)
                ->exists();
            if ($alreadyRendered) {
                continue;
            }

            $delivery = TelegramDelivery::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $task->workspace_id,
                'integration_setting_id' => $previousDelivery->integration_setting_id,
                'source_type' => Task::class,
                'source_id' => $task->id,
                'chat_id' => $previousDelivery->chat_id,
                'topic_id' => $previousDelivery->topic_id,
                'direct_messages_topic_id' => $previousDelivery->direct_messages_topic_id,
                'telegram_message_id' => $previousDelivery->telegram_message_id,
                'parse_mode' => 'HTML',
                'renderer_version' => TelegramCardRenderer::TASK_CARD_UPDATED_VERSION,
                'status' => 'pending',
                'previous_delivery_id' => $previousDelivery->id,
                'request_payload' => [
                    'method' => 'editMessageText',
                    'message_id' => $previousDelivery->telegram_message_id,
                    'task_id' => $task->id,
                    'lifecycle_action' => $action,
                    'text' => $text,
                    'reply_markup' => $replyMarkup,
                    'buttons' => $this->taskButtonLabels($task),
                ],
            ]);

            try {
                $result = app(TelegramService::class)->editMessageText(
                    $previousDelivery->chat_id,
                    (int) $previousDelivery->telegram_message_id,
                    $text,
                    $replyMarkup,
                );

                $delivery->update([
                    'status' => 'sent',
                    'parse_mode' => $this->parseModeFromResult($result),
                    'response_payload' => $result,
                    'attempts' => 1,
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $this->markDeliveryFailed($delivery, $e);
            }
        }
    }

    /**
     * @return array{inline_keyboard: list<list<array{text: string, callback_data?: string, url?: string}>>}
     */
    private function taskReplyMarkup(string $integrationSettingId, Task $task): array
    {
        $rows = [];
        $stateButtons = [];

        if (in_array($task->status, [Task::STATUS_PENDING, Task::STATUS_ACTIVE], true)) {
            $stateButtons[] = ['text' => 'Pause', 'callback_data' => $this->taskInteraction($integrationSettingId, $task, 'pause')->token];
        }
        if ($task->status === Task::STATUS_PAUSED) {
            $stateButtons[] = ['text' => 'Resume', 'callback_data' => $this->taskInteraction($integrationSettingId, $task, 'resume')->token];
        }
        if (! in_array($task->status, [Task::STATUS_COMPLETED, Task::STATUS_FAILED, Task::STATUS_CANCELLED], true)) {
            $stateButtons[] = ['text' => 'Cancel', 'callback_data' => $this->taskInteraction($integrationSettingId, $task, 'cancel')->token];
        }
        if ($stateButtons !== []) {
            $rows[] = $stateButtons;
        }

        if ($url = $this->taskWebUrl($task)) {
            $rows[] = [[
                'text' => 'Open',
                'url' => $url,
            ]];
        }

        return ['inline_keyboard' => $rows];
    }

    private function taskInteraction(string $integrationSettingId, Task $task, string $action): TelegramInteraction
    {
        $existing = TelegramInteraction::where('workspace_id', $task->workspace_id)
            ->where('integration_setting_id', $integrationSettingId)
            ->where('interaction_type', 'task')
            ->where('target_type', Task::class)
            ->where('target_id', $task->id)
            ->where('payload->action', $action)
            ->where(function ($query) {
                $query->whereNull('final_status')->orWhere('final_status', '');
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('expires_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $task->workspace_id,
            'integration_setting_id' => $integrationSettingId,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'task',
            'target_type' => Task::class,
            'target_id' => $task->id,
            'payload' => [
                'action' => $action,
                'task_id' => $task->id,
            ],
            'allowed_actor_rule' => [
                'type' => 'linked_workspace_member',
            ],
            'expires_at' => now()->addMinutes(30),
        ]));
    }

    /**
     * @return list<string>
     */
    private function taskButtonLabels(Task $task): array
    {
        $buttons = [];
        if (in_array($task->status, [Task::STATUS_PENDING, Task::STATUS_ACTIVE], true)) {
            $buttons[] = 'pause';
        }
        if ($task->status === Task::STATUS_PAUSED) {
            $buttons[] = 'resume';
        }
        if (! in_array($task->status, [Task::STATUS_COMPLETED, Task::STATUS_FAILED, Task::STATUS_CANCELLED], true)) {
            $buttons[] = 'cancel';
        }
        $buttons[] = 'open';

        return $buttons;
    }

    private function taskWebUrl(Task $task): ?string
    {
        $workspace = $task->workspace ?: Workspace::find($task->workspace_id);
        if (! $workspace) {
            return null;
        }

        return rtrim((string) config('app.url'), '/')."/w/{$workspace->slug}/tasks/{$task->id}";
    }

    private function handleAutomationFailure(Task $task): void
    {
        $automationId = is_array($task->context) ? (string) ($task->context['automation_id'] ?? '') : '';
        if ($automationId === '') {
            return;
        }

        $automation = Automation::where('workspace_id', $task->workspace_id)
            ->where('id', $automationId)
            ->first();

        if (! $automation) {
            return;
        }

        TelegramSubscription::where('workspace_id', $task->workspace_id)
            ->where('event_type', 'automation_failed')
            ->where('severity', 'high')
            ->where('enabled', true)
            ->get()
            ->each(fn (TelegramSubscription $subscription) => $this->notifyAutomationFailureSubscription($subscription, $automation, $task));
    }

    private function notifyAutomationFailureSubscription(TelegramSubscription $subscription, Automation $automation, Task $task): void
    {
        if ($subscription->scope_type !== 'telegram_conversation') {
            return;
        }

        if ($this->isSnoozed($subscription)) {
            return;
        }

        $conversation = TelegramConversation::where('workspace_id', $task->workspace_id)
            ->where('integration_setting_id', $subscription->integration_setting_id)
            ->where('id', $subscription->scope_id)
            ->whereNull('archived_at')
            ->first();

        if (! $conversation) {
            return;
        }

        $mode = $this->notificationMode($subscription);
        if (in_array($mode, ['digest', 'digest-only', 'batched'], true)) {
            $this->recordDeferredAutomationDelivery($subscription, $automation, $task, $conversation, $mode);

            return;
        }

        $text = $this->automationFailureText($automation, $task);
        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $task->workspace_id,
            'integration_setting_id' => $subscription->integration_setting_id,
            'source_type' => Automation::class,
            'source_id' => $automation->id,
            'chat_id' => $conversation->chat_id,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'parse_mode' => 'HTML',
            'renderer_version' => 'telegram-automation-notification:v1',
            'status' => 'pending',
            'request_payload' => [
                'method' => 'sendMessage',
                'conversation_id' => $conversation->id,
                'event_type' => 'automation_failed',
                'severity' => 'high',
                'automation_id' => $automation->id,
                'task_id' => $task->id,
                'text' => $text,
            ],
        ]);

        try {
            $result = app(TelegramService::class)->sendMessage(
                $conversation->chat_id,
                $text,
                messageThreadId: TelegramService::messageThreadIdForTopic($conversation->topic_id),
                directMessagesTopicId: TelegramService::directMessagesTopicId($conversation->direct_messages_topic_id),
                disableNotification: $mode === 'silent',
            );

            $delivery->update([
                'status' => 'sent',
                'telegram_message_id' => isset($result['message_id']) ? (string) $result['message_id'] : null,
                'parse_mode' => $this->parseModeFromResult($result),
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $updates = [
                'status' => 'failed',
                'provider_error_code' => $e::class,
                'provider_error_message' => Str::limit($e->getMessage(), 2000),
                'attempts' => 1,
            ];

            if ($e instanceof TelegramRateLimitException) {
                $updates['response_payload'] = [
                    'retry_after' => $e->retryAfter,
                    'retry_at' => now()->addSeconds($e->retryAfter)->toIso8601String(),
                ];
            }

            $delivery->update($updates);

            Log::warning('Telegram automation notification failed', [
                'workspace_id' => $task->workspace_id,
                'automation_id' => $automation->id,
                'task_id' => $task->id,
                'subscription_id' => $subscription->id,
                'retry_after' => $e instanceof TelegramRateLimitException ? $e->retryAfter : null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyApprovalNeededSubscription(TelegramSubscription $subscription, ApprovalRequest $approval, string $workspaceId): void
    {
        if ($subscription->scope_type !== 'telegram_conversation') {
            return;
        }

        if ($this->isSnoozed($subscription)) {
            return;
        }

        $conversation = TelegramConversation::where('workspace_id', $workspaceId)
            ->where('integration_setting_id', $subscription->integration_setting_id)
            ->where('id', $subscription->scope_id)
            ->whereNull('archived_at')
            ->first();

        if (! $conversation) {
            return;
        }

        $mode = $this->notificationMode($subscription);
        if (in_array($mode, ['digest', 'digest-only', 'batched'], true)) {
            $this->recordDeferredApprovalDelivery($subscription, $approval, $conversation, $mode);

            return;
        }

        $text = app(TelegramApprovalRenderer::class)->pendingHtml($approval, null);
        $inspect = $this->approvalInteraction($subscription, $approval, 'inspect');
        $approve = $this->approvalInteraction($subscription, $approval, 'approve');
        $reject = $this->approvalInteraction($subscription, $approval, 'reject');
        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => 'Inspect', 'callback_data' => $inspect->token],
                ],
                [
                    ['text' => 'Approve', 'callback_data' => $approve->token],
                    ['text' => 'Reject', 'callback_data' => $reject->token],
                ],
            ],
        ];

        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspaceId,
            'integration_setting_id' => $subscription->integration_setting_id,
            'source_type' => ApprovalRequest::class,
            'source_id' => $approval->id,
            'chat_id' => $conversation->chat_id,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'parse_mode' => 'HTML',
            'renderer_version' => TelegramApprovalRenderer::PENDING_VERSION,
            'status' => 'pending',
            'request_payload' => [
                'method' => 'sendMessage',
                'conversation_id' => $conversation->id,
                'event_type' => 'approval_needed',
                'severity' => 'high',
                'approval_id' => $approval->id,
                'buttons' => ['inspect', 'approve', 'reject'],
                'text' => $text,
            ],
        ]);

        try {
            $result = app(TelegramService::class)->sendMessage(
                $conversation->chat_id,
                $text,
                $replyMarkup,
                messageThreadId: TelegramService::messageThreadIdForTopic($conversation->topic_id),
                directMessagesTopicId: TelegramService::directMessagesTopicId($conversation->direct_messages_topic_id),
                disableNotification: $mode === 'silent',
            );

            $delivery->update([
                'status' => 'sent',
                'telegram_message_id' => isset($result['message_id']) ? (string) $result['message_id'] : null,
                'parse_mode' => $this->parseModeFromResult($result),
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => 'failed',
                'provider_error_code' => $e::class,
                'provider_error_message' => Str::limit($e->getMessage(), 2000),
                'attempts' => 1,
            ]);

            Log::warning('Telegram approval notification failed', [
                'workspace_id' => $workspaceId,
                'approval_id' => $approval->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifySubscription(TelegramSubscription $subscription, Task $task, string $eventType, string $severity): void
    {
        if ($subscription->scope_type !== 'telegram_conversation') {
            return;
        }

        if ($this->isSnoozed($subscription)) {
            return;
        }

        $conversation = TelegramConversation::where('workspace_id', $task->workspace_id)
            ->where('integration_setting_id', $subscription->integration_setting_id)
            ->where('id', $subscription->scope_id)
            ->whereNull('archived_at')
            ->first();

        if (! $conversation) {
            return;
        }

        $mode = $this->notificationMode($subscription);
        if (in_array($mode, ['digest', 'digest-only', 'batched'], true)) {
            $this->recordDeferredDelivery($subscription, $task, $conversation, $eventType, $severity, $mode);

            return;
        }

        $text = $this->taskNotificationText($task, $eventType, $severity);
        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $task->workspace_id,
            'integration_setting_id' => $subscription->integration_setting_id,
            'source_type' => Task::class,
            'source_id' => $task->id,
            'chat_id' => $conversation->chat_id,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'parse_mode' => 'HTML',
            'renderer_version' => 'telegram-task-notification:v1',
            'status' => 'pending',
            'request_payload' => [
                'method' => 'sendMessage',
                'conversation_id' => $conversation->id,
                'event_type' => $eventType,
                'severity' => $severity,
                'task_id' => $task->id,
                'text' => $text,
            ],
        ]);

        try {
            $result = app(TelegramService::class)->sendMessage(
                $conversation->chat_id,
                $text,
                messageThreadId: TelegramService::messageThreadIdForTopic($conversation->topic_id),
                directMessagesTopicId: TelegramService::directMessagesTopicId($conversation->direct_messages_topic_id),
                disableNotification: $mode === 'silent',
            );

            $delivery->update([
                'status' => 'sent',
                'telegram_message_id' => isset($result['message_id']) ? (string) $result['message_id'] : null,
                'parse_mode' => $this->parseModeFromResult($result),
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $updates = [
                'status' => 'failed',
                'provider_error_code' => $e::class,
                'provider_error_message' => Str::limit($e->getMessage(), 2000),
                'attempts' => 1,
            ];

            if ($e instanceof TelegramRateLimitException) {
                $updates['response_payload'] = [
                    'retry_after' => $e->retryAfter,
                    'retry_at' => now()->addSeconds($e->retryAfter)->toIso8601String(),
                ];
            }

            $delivery->update($updates);

            Log::warning('Telegram task notification failed', [
                'workspace_id' => $task->workspace_id,
                'task_id' => $task->id,
                'subscription_id' => $subscription->id,
                'retry_after' => $e instanceof TelegramRateLimitException ? $e->retryAfter : null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isSnoozed(TelegramSubscription $subscription): bool
    {
        $snooze = TelegramSubscription::where('workspace_id', $subscription->workspace_id)
            ->where('integration_setting_id', $subscription->integration_setting_id)
            ->where('scope_type', $subscription->scope_type)
            ->where('scope_id', $subscription->scope_id)
            ->where('event_type', 'all_notifications')
            ->where('enabled', true)
            ->first();

        $until = $snooze?->filters['snoozed_until'] ?? null;

        return is_string($until) && now()->lessThan($until);
    }

    private function notificationMode(TelegramSubscription $subscription): string
    {
        $mode = $subscription->filters['mode'] ?? 'immediate';

        return is_string($mode) ? strtolower($mode) : 'immediate';
    }

    private function recordDeferredDelivery(
        TelegramSubscription $subscription,
        Task $task,
        TelegramConversation $conversation,
        string $eventType,
        string $severity,
        string $mode
    ): void {
        TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $task->workspace_id,
            'integration_setting_id' => $subscription->integration_setting_id,
            'source_type' => Task::class,
            'source_id' => $task->id,
            'chat_id' => $conversation->chat_id,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'renderer_version' => 'telegram-task-notification:v1',
            'status' => 'deferred',
            'request_payload' => [
                'method' => 'deferNotification',
                'subscription_id' => $subscription->id,
                'event_type' => $eventType,
                'severity' => $severity,
                'mode' => $mode,
                'task_id' => $task->id,
            ],
        ]);
    }

    private function recordDeferredAutomationDelivery(
        TelegramSubscription $subscription,
        Automation $automation,
        Task $task,
        TelegramConversation $conversation,
        string $mode
    ): void {
        TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $task->workspace_id,
            'integration_setting_id' => $subscription->integration_setting_id,
            'source_type' => Automation::class,
            'source_id' => $automation->id,
            'chat_id' => $conversation->chat_id,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'renderer_version' => 'telegram-automation-notification:v1',
            'status' => 'deferred',
            'request_payload' => [
                'method' => 'deferNotification',
                'subscription_id' => $subscription->id,
                'event_type' => 'automation_failed',
                'severity' => 'high',
                'mode' => $mode,
                'automation_id' => $automation->id,
                'task_id' => $task->id,
            ],
        ]);
    }

    private function recordDeferredApprovalDelivery(
        TelegramSubscription $subscription,
        ApprovalRequest $approval,
        TelegramConversation $conversation,
        string $mode
    ): void {
        TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $conversation->workspace_id,
            'integration_setting_id' => $subscription->integration_setting_id,
            'source_type' => ApprovalRequest::class,
            'source_id' => $approval->id,
            'chat_id' => $conversation->chat_id,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'renderer_version' => TelegramApprovalRenderer::DEFERRED_NOTIFICATION_VERSION,
            'status' => 'deferred',
            'request_payload' => [
                'method' => 'deferNotification',
                'subscription_id' => $subscription->id,
                'event_type' => 'approval_needed',
                'severity' => 'high',
                'mode' => $mode,
                'approval_id' => $approval->id,
            ],
        ]);
    }

    private function approvalInteraction(TelegramSubscription $subscription, ApprovalRequest $approval, string $action): TelegramInteraction
    {
        return TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $subscription->workspace_id,
            'integration_setting_id' => $subscription->integration_setting_id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'approval',
            'target_type' => ApprovalRequest::class,
            'target_id' => $approval->id,
            'payload' => [
                'action' => $action,
                'approval_id' => $approval->id,
            ],
            'allowed_actor_rule' => [
                'type' => 'workspace_member_or_linked_telegram_user',
            ],
            'expires_at' => now()->addMinutes(30),
        ]));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function parseModeFromResult(array $result): string
    {
        return ($result['_opencompany_parse_mode_fallback'] ?? false) ? 'plain_text_fallback' : 'HTML';
    }

    private function markDeliveryFailed(TelegramDelivery $delivery, \Throwable $e): void
    {
        $updates = [
            'status' => 'failed',
            'provider_error_code' => $e::class,
            'provider_error_message' => Str::limit($e->getMessage(), 2000),
            'attempts' => 1,
        ];

        if ($e instanceof TelegramRateLimitException) {
            $updates['response_payload'] = [
                'retry_after' => $e->retryAfter,
                'retry_at' => now()->addSeconds($e->retryAfter)->toIso8601String(),
            ];
        }

        $delivery->update($updates);
    }

    private function automationFailureText(Automation $automation, Task $task): string
    {
        $name = htmlspecialchars($automation->name, ENT_QUOTES, 'UTF-8');
        $agent = htmlspecialchars($task->agent?->name ?? $automation->agent?->name ?? 'unassigned', ENT_QUOTES, 'UTF-8');
        $schedule = htmlspecialchars($automation->cron_expression ?: 'manual', ENT_QUOTES, 'UTF-8');
        $failures = max((int) $automation->consecutive_failures, 1);

        $lines = [
            '<b>Automation failed</b>',
            '',
            "<b>{$name}</b>",
            "Agent: {$agent}",
            "Schedule: {$schedule}",
            "Consecutive failures: {$failures}",
        ];

        $error = is_array($task->result) ? ($task->result['error'] ?? null) : null;
        if (! is_string($error) || $error === '') {
            $error = is_array($automation->last_result) ? ($automation->last_result['error'] ?? null) : null;
        }

        if (is_string($error) && $error !== '') {
            $lines[] = 'Error: '.htmlspecialchars(Str::limit($error, 220), ENT_QUOTES, 'UTF-8');
        }

        return implode("\n", $lines);
    }

    private function taskNotificationText(Task $task, string $eventType, string $severity): string
    {
        $title = htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8');
        $agent = htmlspecialchars($task->agent?->name ?? 'unassigned', ENT_QUOTES, 'UTF-8');
        $status = $eventType === 'task_failed' ? 'Task failed' : 'Task completed';

        $lines = [
            "<b>{$status}</b>",
            '',
            "<b>{$title}</b>",
            "Agent: {$agent}",
            "Severity: {$severity}",
        ];

        $error = is_array($task->result) ? ($task->result['error'] ?? null) : null;
        if (is_string($error) && $error !== '') {
            $lines[] = 'Error: '.htmlspecialchars(Str::limit($error, 180), ENT_QUOTES, 'UTF-8');
        }

        return implode("\n", $lines);
    }
}

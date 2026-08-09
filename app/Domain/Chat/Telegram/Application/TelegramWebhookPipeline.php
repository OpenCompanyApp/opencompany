<?php

namespace App\Domain\Chat\Telegram\Application;

use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Jobs\AgentRespondJob;
use App\Jobs\CompactConversationJob;
use App\Jobs\EnrichTelegramMediaJob;
use App\Jobs\RunAutomationJob;
use App\Models\ApprovalRequest;
use App\Models\Automation;
use App\Models\CalendarEvent;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\IntegrationSetting;
use App\Models\ListItem;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageReaction;
use App\Models\Task;
use App\Models\TelegramConversation;
use App\Models\TelegramDelivery;
use App\Models\TelegramInteraction;
use App\Models\TelegramSubscription;
use App\Models\TelegramUpdateReceipt;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Models\Workspace;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceMember;
use App\Services\ApprovalExecutionService;
use App\Services\FileSystemService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * OpenCompany-owned Telegram chat webhook runtime.
 *
 * This class is the first app-owned replacement for Chatogrator's Telegram
 * webhook path. It verifies that the already-resolved workspace has a matching
 * Telegram integration setting, stores every update receipt for idempotency and
 * diagnostics, maps Telegram chats/topics into OpenCompany channels, handles
 * current commands/callbacks, and dispatches normal agent response jobs.
 *
 * Chatogrator remains available for the other providers. Telegram-specific
 * routing, callbacks, conversations, and receipts live under the short Chat
 * domain because Telegram is a chat app provider, not a standalone business
 * domain, a generic integration package, or a Collaboration subdomain.
 */
class TelegramWebhookPipeline
{
    public function handle(Request $request, Workspace $workspace): Response
    {
        $setting = $this->resolveSetting($request, $workspace);
        if (! $setting) {
            return new Response('Unauthorized', 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return new Response('Invalid JSON', 400);
        }

        $receipt = $this->receiptFor($payload, $setting, $workspace);
        if ($receipt->status === 'processed') {
            $this->recordDuplicateReceipt($receipt);

            return new Response('', 200);
        }

        try {
            $receipt->update(['status' => 'processing']);

            $conversation = $this->processPayload($payload, $setting, $workspace, $receipt);

            $receipt->update([
                'status' => 'processed',
                'telegram_conversation_id' => $conversation?->id,
                'processed_at' => now(),
                'error_class' => null,
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $receipt->update([
                'status' => 'failed',
                'error_class' => $e::class,
                'error_message' => Str::limit($e->getMessage(), 2000),
            ]);

            Log::error('Telegram webhook pipeline failed', [
                'workspace_id' => $workspace->id,
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Telegram retries on non-2xx responses. Once the update is durably
        // stored, app-side failures should be inspected locally rather than
        // replayed by Telegram without operator control.
        return new Response('', 200);
    }

    /**
     * Re-run a durably stored Telegram update under operator control.
     *
     * Telegram itself will retry any non-2xx webhook response, so the normal
     * webhook path always acknowledges after persistence. This method is the
     * explicit repair path for receipts that failed after they were stored or
     * that were left in a stale intermediate state by process termination.
     */
    public function replayReceipt(TelegramUpdateReceipt $receipt): ?TelegramConversation
    {
        if ($receipt->status === 'processed') {
            throw new \RuntimeException('Processed Telegram receipts are not replayed automatically.');
        }

        $payload = $receipt->payload ?? [];
        if (! is_array($payload)) {
            throw new \RuntimeException('Telegram receipt payload is not replayable.');
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! hash_equals($receipt->payload_hash, hash('sha256', $encoded ?: ''))) {
            throw new \RuntimeException('Telegram receipt payload hash does not match stored payload.');
        }

        $setting = $receipt->integrationSetting;
        $workspace = $receipt->workspace;
        if (! $setting || ! $workspace || $setting->workspace_id !== $workspace->id) {
            throw new \RuntimeException('Telegram receipt is missing a valid workspace integration setting.');
        }

        $receipt->update([
            'status' => 'processing',
            'retry_count' => $receipt->retry_count + 1,
            'error_class' => null,
            'error_message' => null,
        ]);

        try {
            app()->instance('currentWorkspace', $workspace);
            $conversation = $this->processPayload($payload, $setting, $workspace, $receipt);

            $receipt->update([
                'status' => 'processed',
                'telegram_conversation_id' => $conversation?->id,
                'processed_at' => now(),
                'error_class' => null,
                'error_message' => null,
            ]);

            return $conversation;
        } catch (\Throwable $e) {
            $receipt->update([
                'status' => 'failed',
                'error_class' => $e::class,
                'error_message' => Str::limit($e->getMessage(), 2000),
            ]);

            throw $e;
        }
    }

    private function resolveSetting(Request $request, Workspace $workspace): ?IntegrationSetting
    {
        $secret = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if (! is_string($secret) || $secret === '') {
            return null;
        }

        return IntegrationSetting::where('workspace_id', $workspace->id)
            ->where('integration_id', 'telegram')
            ->where('enabled', true)
            ->get()
            ->first(fn (IntegrationSetting $setting) => hash_equals(
                (string) $setting->getConfigValue('webhook_secret', ''),
                $secret
            ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function receiptFor(array $payload, IntegrationSetting $setting, Workspace $workspace): TelegramUpdateReceipt
    {
        $updateId = (string) ($payload['update_id'] ?? 'missing:'.hash('sha256', json_encode($payload)));
        $updateType = $this->detectUpdateType($payload);
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return TelegramUpdateReceipt::firstOrCreate(
            [
                'integration_setting_id' => $setting->id,
                'update_id' => $updateId,
            ],
            [
                'id' => Str::uuid()->toString(),
                'workspace_id' => $workspace->id,
                'update_type' => $updateType,
                        'payload_hash' => hash('sha256', $encoded ?: ''),
                        'payload' => $payload,
                        'diagnostics' => [],
                        'status' => 'received',
                        'duplicate_count' => 0,
                        'received_at' => now(),
                    ],
        );
    }

    /**
     * Track duplicate Telegram update deliveries without reprocessing them.
     *
     * Telegram retries are normal during network/provider uncertainty, and
     * OpenCompany intentionally treats already-processed receipts as no-ops.
     * Counting those no-ops gives operators a real dedupe metric instead of
     * hiding retry pressure behind a generic 200 response.
     */
    private function recordDuplicateReceipt(TelegramUpdateReceipt $receipt): void
    {
        TelegramUpdateReceipt::whereKey($receipt->id)->update([
            'duplicate_count' => DB::raw('duplicate_count + 1'),
            'last_duplicate_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function detectUpdateType(array $payload): string
    {
        foreach ([
            'message',
            'edited_message',
            'channel_post',
            'edited_channel_post',
            'inline_query',
            'chosen_inline_result',
            'callback_query',
            'shipping_query',
            'pre_checkout_query',
            'message_reaction',
            'message_reaction_count',
            'chat_member',
            'my_chat_member',
            'business_connection',
            'business_message',
            'edited_business_message',
            'deleted_business_messages',
            'guest_message',
            'purchased_paid_media',
            'poll',
            'poll_answer',
            'chat_join_request',
            'chat_boost',
            'removed_chat_boost',
            'managed_bot',
        ] as $key) {
            if (array_key_exists($key, $payload)) {
                return $key;
            }
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processPayload(
        array $payload,
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramUpdateReceipt $receipt
    ): ?TelegramConversation {
        if (isset($payload['message']) && is_array($payload['message'])) {
            return $this->handleMessage($payload['message'], $setting, $workspace, $receipt, edited: false);
        }

        if (isset($payload['edited_message']) && is_array($payload['edited_message'])) {
            return $this->handleEditedMessage($payload['edited_message'], $setting, $workspace);
        }

        if (isset($payload['channel_post']) && is_array($payload['channel_post'])) {
            return $this->handleMessage($payload['channel_post'], $setting, $workspace, $receipt, edited: false);
        }

        if (isset($payload['edited_channel_post']) && is_array($payload['edited_channel_post'])) {
            return $this->handleEditedMessage($payload['edited_channel_post'], $setting, $workspace);
        }

        if (isset($payload['callback_query']) && is_array($payload['callback_query'])) {
            return $this->handleCallbackQuery($payload['callback_query'], $setting, $workspace);
        }

        if (isset($payload['inline_query']) && is_array($payload['inline_query'])) {
            return $this->handleInlineQuery($payload['inline_query'], $setting, $workspace);
        }

        if (isset($payload['chosen_inline_result']) && is_array($payload['chosen_inline_result'])) {
            return $this->rememberInlineResult($payload['chosen_inline_result'], $setting);
        }

        if (isset($payload['message_reaction']) && is_array($payload['message_reaction'])) {
            return $this->handleMessageReaction($payload['message_reaction'], $setting, $workspace);
        }

        if (isset($payload['message_reaction_count']) && is_array($payload['message_reaction_count'])) {
            return $this->rememberConversationFromReaction($payload['message_reaction_count'], $setting, $workspace);
        }

        if (isset($payload['my_chat_member']) && is_array($payload['my_chat_member'])) {
            return $this->handleChatMemberUpdate($payload['my_chat_member'], $setting, $workspace, ownBotStatus: true);
        }

        if (isset($payload['chat_member']) && is_array($payload['chat_member'])) {
            return $this->handleChatMemberUpdate($payload['chat_member'], $setting, $workspace, ownBotStatus: false);
        }

        if (isset($payload['guest_message']) && is_array($payload['guest_message']) && ! config('telegram.guest_mode_enabled')) {
            return $this->rememberDisabledModernUpdate('guest_message', $payload['guest_message'], $setting, $workspace, 'guest_mode_enabled');
        }

        if (isset($payload['guest_message']) && is_array($payload['guest_message'])) {
            return $this->handleGuestMessage($payload['guest_message'], $setting, $workspace);
        }

        foreach ([
            'business_connection',
            'business_message',
            'edited_business_message',
            'deleted_business_messages',
            'shipping_query',
            'pre_checkout_query',
            'purchased_paid_media',
            'poll',
            'poll_answer',
            'chat_join_request',
            'chat_boost',
            'removed_chat_boost',
            'managed_bot',
        ] as $type) {
            if (isset($payload[$type]) && is_array($payload[$type])) {
                return $this->rememberUnsupportedModernUpdate($type, $payload[$type], $setting, $workspace);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleMessage(
        array $message,
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramUpdateReceipt $receipt,
        bool $edited
    ): ?TelegramConversation {
        $from = $message['from'] ?? [];
        $fromId = (string) ($from['id'] ?? 'unknown');

        if ($fromId !== 'unknown' && $fromId === (string) $setting->getConfigValue('bot_user_id', '')) {
            return null;
        }

        $conversation = $this->resolveConversation($message, $setting, $workspace);

        if ($this->isBotCommand($message)) {
            if (! $this->shouldHandleCommand($message, $setting, $conversation)) {
                return $conversation;
            }

            $this->handleCommand($message, $setting, $workspace, $conversation);

            return $conversation;
        }

        if (! $this->isUserAllowed($setting, $fromId)) {
            $this->sendText($setting, $conversation, 'You are not authorized to use this bot. Contact your administrator.');

            return $conversation;
        }

        if ($conversation->mode === 'ignored') {
            return $conversation;
        }

        $shouldDispatch = $this->shouldDispatchMessage($message, $setting, $conversation);
        if (! $shouldDispatch && (! $conversation->observed_context_enabled || ! config('telegram.group_observer_enabled', true))) {
            return $conversation;
        }

        $channel = $this->channelForConversation($conversation, $message);
        $conversation->update(['channel_id' => $channel->id]);

        $user = $this->resolveUser($workspace, $from);
        ChannelMember::firstOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $user->id],
            ['id' => Str::uuid()->toString(), 'joined_at' => now()],
        );

        $agent = $this->resolveAgent($workspace, $setting, $conversation);
        if ($agent) {
            ChannelMember::firstOrCreate(
                ['channel_id' => $channel->id, 'user_id' => $agent->id],
                ['id' => Str::uuid()->toString(), 'joined_at' => now()],
            );
        }

        $replyToId = $this->resolveReplyToMessageId($message, $conversation, $channel);
        $content = $this->messageText($message);
        $externalMessageId = $this->externalMessageId($message);
        $internalMessage = $externalMessageId
            ? $this->messageForTelegramExternalId($conversation, $channel, $externalMessageId)
            : null;
        $batchedTextSplit = false;

        if (! $internalMessage && $this->isBatchableTextSplit($message, $conversation, $shouldDispatch)) {
            $internalMessage = $this->batchablePreviousTextMessage($channel, $user);
            if ($internalMessage) {
                $this->appendTelegramTextSplit($internalMessage, $content, $externalMessageId);
                $batchedTextSplit = true;
            }
        }

        $createdLogicalMessage = ! $internalMessage;

        if ($internalMessage) {
            if ($batchedTextSplit) {
                $this->rememberTelegramMessageMapping($conversation, $channel, $internalMessage, $externalMessageId, 'text_split');
                $this->refreshBatchedTextTask($internalMessage, $content, $externalMessageId);
            } else {
                $this->mergeMediaGroupCaption($internalMessage, $message);
                $this->rememberTelegramMessageMapping($conversation, $channel, $internalMessage, $externalMessageId, 'alias');
            }
        } else {
            $internalMessage = Message::create([
                'id' => Str::uuid()->toString(),
                'content' => $content,
                'channel_id' => $channel->id,
                'author_id' => $user->id,
                'reply_to_id' => $replyToId,
                'timestamp' => now(),
                'source' => $shouldDispatch ? 'telegram' : 'telegram_observed',
                'external_message_id' => $externalMessageId,
            ]);
            $this->rememberTelegramMessageMapping($conversation, $channel, $internalMessage, $externalMessageId, 'primary');
        }

        if (! $batchedTextSplit && config('telegram.media_ingestion_enabled', true)) {
            $this->captureMedia($message, $workspace, $user, $internalMessage, $setting, $conversation, $receipt);
        }

        if ($createdLogicalMessage) {
            broadcast(new MessageSent($internalMessage));
        }
        $channel->update(['last_message_at' => now()]);

        if ($agent && ! $edited && $shouldDispatch && $createdLogicalMessage) {
            try {
                app(TelegramService::class)->sendChatAction(
                    $conversation->chat_id,
                    messageThreadId: TelegramService::messageThreadIdForTopic($conversation->topic_id),
                );
            } catch (\Throwable) {
                // Typing is a transient Telegram nicety and must not block the
                // source-of-truth OpenCompany message or task creation.
            }

            $laneQueueState = $this->laneQueueState($workspace, $channel->id);
            $shouldRunNow = $laneQueueState['open_count'] === 0;
            $task = Task::createPending($internalMessage, $agent, $channel->id);
            if ($this->isBatchableTextSplit($message, $conversation, $shouldDispatch)) {
                $context = $task->context ?? [];
                $context['telegram_text_batch'] = [
                    'window_seconds' => $this->textBatchWindowSeconds(),
                    'message_ids' => array_values(array_filter([$externalMessageId])),
                    'message_count' => 1,
                    'started_at' => now()->toIso8601String(),
                ];
                $task->update([
                    'context' => $context,
                    'description' => $internalMessage->content,
                    'title' => $this->taskTitleFromMessageContent($internalMessage->content),
                ]);
            }

            if ($laneQueueState['open_count'] > 0) {
                $context = $task->context ?? [];
                $context['telegram_lane_queue'] = $laneQueueState;
                $task->update(['context' => $context]);
            }

            if ($shouldRunNow) {
                $this->sendDraftPreview($setting, $conversation, $task);
            }
            $this->sendTaskCard(
                $setting,
                $conversation,
                $task->loadMissing('agent'),
                $this->linkedTelegramMessageActor($workspace, $message),
                TelegramCardRenderer::RUN_CARD_VERSION,
            );
            if ($shouldRunNow) {
                $job = AgentRespondJob::dispatch($internalMessage, $agent, $channel->id, $task->id);
                if ($this->isBatchableTextSplit($message, $conversation, $shouldDispatch)) {
                    $job->delay(now()->addSeconds($this->textBatchWindowSeconds()));
                }
            }
        }

        return $conversation;
    }

    /**
     * Summarize in-flight work before queuing another Telegram lane request.
     *
     * Telegram lanes run one agent job at a time. A phone chat looks flaky when
     * multiple prompts dispatch concurrently, so this state is computed before
     * task creation and stored on queued tasks. The completion listener later
     * starts the next queued task after the active one reaches a terminal state.
     *
     * @return array{open_count: int, active_count: int, paused_count: int, pending_count: int, ahead_task_id: ?string, ahead_title: ?string}
     */
    private function laneQueueState(Workspace $workspace, string $channelId): array
    {
        $openTasks = Task::where('workspace_id', $workspace->id)
            ->where('channel_id', $channelId)
            ->whereIn('status', [Task::STATUS_ACTIVE, Task::STATUS_PAUSED, Task::STATUS_PENDING])
            ->orderByRaw(
                'CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END',
                [Task::STATUS_ACTIVE, Task::STATUS_PAUSED],
            )
            ->oldest()
            ->get(['id', 'title', 'status']);
        $ahead = $openTasks->first();

        return [
            'open_count' => $openTasks->count(),
            'active_count' => $openTasks->where('status', Task::STATUS_ACTIVE)->count(),
            'paused_count' => $openTasks->where('status', Task::STATUS_PAUSED)->count(),
            'pending_count' => $openTasks->where('status', Task::STATUS_PENDING)->count(),
            'ahead_task_id' => $ahead?->id,
            'ahead_title' => $ahead?->title,
        ];
    }

    /**
     * Telegram sends each album item as a separate update, but OpenCompany
     * should treat one media_group_id as one logical user prompt. Individual
     * Telegram message IDs remain captured on attachments; the message itself
     * gets a stable media-group external ID so later album items attach to the
     * same OpenCompany message and do not start extra agent runs.
     *
     * @param  array<string, mixed>  $message
     */
    private function externalMessageId(array $message): ?string
    {
        if (isset($message['media_group_id']) && is_scalar($message['media_group_id'])) {
            return 'media_group:'.(string) $message['media_group_id'];
        }

        return isset($message['message_id']) ? (string) $message['message_id'] : null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function mergeMediaGroupCaption(Message $internalMessage, array $message): void
    {
        if (! isset($message['media_group_id']) || ! is_string($message['caption'] ?? null)) {
            return;
        }

        $caption = trim((string) $message['caption']);
        if ($caption === '' || str_contains($internalMessage->content, $caption)) {
            return;
        }

        $internalMessage->update([
            'content' => trim($internalMessage->content."\n\n".$caption),
        ]);
    }

    private function messageForTelegramExternalId(TelegramConversation $conversation, Channel $channel, string $externalMessageId): ?Message
    {
        $mappingQuery = DB::table('telegram_message_mappings')
            ->where('integration_setting_id', $conversation->integration_setting_id)
            ->where('chat_id', $conversation->chat_id)
            ->where('telegram_message_id', $externalMessageId);

        $conversation->topic_id === null
            ? $mappingQuery->whereNull('topic_id')
            : $mappingQuery->where('topic_id', $conversation->topic_id);
        $conversation->direct_messages_topic_id === null
            ? $mappingQuery->whereNull('direct_messages_topic_id')
            : $mappingQuery->where('direct_messages_topic_id', $conversation->direct_messages_topic_id);

        $mappedMessageId = $mappingQuery->value('message_id');

        if (is_string($mappedMessageId) && $mappedMessageId !== '') {
            return Message::where('channel_id', $channel->id)->where('id', $mappedMessageId)->first();
        }

        return Message::where('channel_id', $channel->id)->where('external_message_id', $externalMessageId)->first();
    }

    private function rememberTelegramMessageMapping(
        TelegramConversation $conversation,
        Channel $channel,
        Message $message,
        ?string $externalMessageId,
        string $mappingType
    ): void {
        if ($externalMessageId === null || $externalMessageId === '') {
            return;
        }

        $keys = [
            'integration_setting_id' => $conversation->integration_setting_id,
            'chat_id' => $conversation->chat_id,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'telegram_message_id' => $externalMessageId,
        ];
        $values = [
            'workspace_id' => $conversation->workspace_id,
            'telegram_conversation_id' => $conversation->id,
            'channel_id' => $channel->id,
            'message_id' => $message->id,
            'mapping_type' => $mappingType,
            'updated_at' => now(),
        ];

        if (DB::table('telegram_message_mappings')->where($keys)->exists()) {
            DB::table('telegram_message_mappings')->where($keys)->update($values);

            return;
        }

        DB::table('telegram_message_mappings')->insert([
            ...$keys,
            ...$values,
                'id' => Str::uuid()->toString(),
                'created_at' => now(),
        ]);
    }

    /**
     * Telegram mobile users often split one thought over several quick sends.
     * Batch only the low-risk case: private plain-text messages from the same
     * actor, before the delayed agent job has started and without reply/media
     * semantics that would make each message a distinct operation.
     *
     * @param  array<string, mixed>  $message
     */
    private function isBatchableTextSplit(array $message, TelegramConversation $conversation, bool $shouldDispatch): bool
    {
        if (! $shouldDispatch || ! (bool) config('telegram.text_batching_enabled', true)) {
            return false;
        }

        if ($conversation->chat_type !== 'private' || $this->textBatchWindowSeconds() < 1) {
            return false;
        }

        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '' || str_starts_with($text, '/') || isset($message['reply_to_message'])) {
            return false;
        }

        foreach ([
            'caption',
            'media_group_id',
            'photo',
            'document',
            'voice',
            'audio',
            'video',
            'video_note',
            'animation',
            'sticker',
            'location',
            'venue',
            'contact',
            'poll',
            'checklist',
        ] as $key) {
            if (array_key_exists($key, $message)) {
                return false;
            }
        }

        return true;
    }

    private function textBatchWindowSeconds(): int
    {
        return max(0, (int) config('telegram.text_batch_window_seconds', 3));
    }

    private function batchablePreviousTextMessage(Channel $channel, User $user): ?Message
    {
        $candidate = Message::where('channel_id', $channel->id)
            ->where('author_id', $user->id)
            ->where('source', 'telegram')
            ->whereNull('reply_to_id')
            ->where('created_at', '>=', now()->subSeconds($this->textBatchWindowSeconds()))
            ->doesntHave('attachments')
            ->latest('created_at')
            ->first();

        if (! $candidate) {
            return null;
        }

        $task = Task::where('trigger_message_id', $candidate->id)
            ->where('status', Task::STATUS_PENDING)
            ->latest()
            ->first();
        $batch = is_array($task?->context) ? ($task->context['telegram_text_batch'] ?? null) : null;

        return is_array($batch) ? $candidate : null;
    }

    private function appendTelegramTextSplit(Message $message, string $text, ?string $externalMessageId): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $message->update([
            'content' => trim($message->content."\n".$text),
            'external_message_id' => $message->external_message_id ?: $externalMessageId,
            'timestamp' => now(),
        ]);
    }

    private function refreshBatchedTextTask(Message $message, string $latestText, ?string $externalMessageId): void
    {
        $task = Task::where('trigger_message_id', $message->id)
            ->where('status', Task::STATUS_PENDING)
            ->latest()
            ->first();
        if (! $task) {
            return;
        }

        $context = $task->context ?? [];
        $batch = is_array($context['telegram_text_batch'] ?? null) ? $context['telegram_text_batch'] : [];
        $messageIds = is_array($batch['message_ids'] ?? null) ? $batch['message_ids'] : [];
        if ($externalMessageId !== null && $externalMessageId !== '') {
            $messageIds[] = $externalMessageId;
        }
        $batch['message_ids'] = array_values(array_unique(array_filter($messageIds)));
        $batch['message_count'] = max((int) ($batch['message_count'] ?? 1), count($batch['message_ids']));
        $batch['last_part_at'] = now()->toIso8601String();
        $batch['last_part'] = Str::limit(trim($latestText), 160);
        $context['telegram_text_batch'] = $batch;

        $task->update([
            'title' => $this->taskTitleFromMessageContent($message->content),
            'description' => $message->content,
            'context' => $context,
        ]);

        event(new TaskUpdated($task->fresh(['agent', 'steps', 'workspace']) ?? $task, 'progress'));
    }

    /**
     * Keep Telegram-created task titles stable even when phone users split one
     * thought over several quick messages. The full merged prompt remains in
     * the description; the title is only the first readable line so cards never
     * grow a multi-line header after batching updates.
     */
    private function taskTitleFromMessageContent(string $content): string
    {
        $lines = preg_split('/\\r\\n|\\n|\\r/', trim($content)) ?: [];
        foreach ($lines as $line) {
            $title = trim($line);
            if ($title !== '') {
                return Str::limit($title, 80);
            }
        }

        return 'Telegram message';
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleEditedMessage(array $message, IntegrationSetting $setting, Workspace $workspace): ?TelegramConversation
    {
        $conversation = $this->resolveConversation($message, $setting, $workspace);
        $channel = $conversation->channel_id ? Channel::find($conversation->channel_id) : null;
        if (! $channel || ! isset($message['message_id'])) {
            return $conversation;
        }

        $local = Message::where('channel_id', $channel->id)
            ->where('external_message_id', (string) $message['message_id'])
            ->first();

        if ($local) {
            $local->update(['content' => $this->messageText($message)]);
        }

        return $conversation;
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     */
    private function handleCallbackQuery(array $callbackQuery, IntegrationSetting $setting, Workspace $workspace): ?TelegramConversation
    {
        $message = is_array($callbackQuery['message'] ?? null) ? $callbackQuery['message'] : [];
        $conversation = $message ? $this->resolveConversation($message, $setting, $workspace) : null;
        $callbackQueryId = (string) ($callbackQuery['id'] ?? '');

        if ($callbackQueryId !== '') {
            try {
                app(TelegramService::class)->answerCallbackQuery($callbackQueryId);
            } catch (\Throwable) {
                // Non-critical; continue resolving the action.
            }
        }

        $data = (string) ($callbackQuery['data'] ?? $callbackQuery['callback_data'] ?? '');
        if ($data === '') {
            return $conversation;
        }

        $interaction = TelegramInteraction::where('token', $data)
            ->where('workspace_id', $workspace->id)
            ->where('integration_setting_id', $setting->id)
            ->first();

        if ($interaction) {
            $this->handlePersistedInteraction($interaction, $callbackQuery, $setting, $workspace, $message);

            return $conversation;
        }

        // Legacy approval buttons once carried approve:{approvalId} directly.
        // Treat them as stale; every mutating callback must now resolve through
        // a persisted TelegramInteraction token so forwarded or forged payloads
        // cannot decide workspace state.
        if (str_contains($data, ':')) {
            [$action, $value] = explode(':', $data, 2);
            if (in_array($action, ['approve', 'reject'], true)) {
                $this->answerCallback($callbackQuery, 'This approval button is no longer valid. Use the latest approval card.');
            }
        }

        return $conversation;
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     * @param  array<string, mixed>  $message
     */
    private function handlePersistedInteraction(
        TelegramInteraction $interaction,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        array $message
    ): void {
        if ($interaction->resolved_at) {
            $this->answerCallback($callbackQuery, 'This action was already resolved.');

            return;
        }

        if (! $interaction->hasValidPayloadChecksum()) {
            $interaction->update(['resolved_at' => now(), 'final_status' => 'tampered']);
            $this->answerCallback($callbackQuery, 'This action is no longer valid. Refresh and try again.');

            return;
        }

        if ($interaction->expires_at && $interaction->expires_at->isPast()) {
            $interaction->update(['resolved_at' => now(), 'final_status' => 'expired']);
            $this->answerCallback($callbackQuery, 'This action expired.');

            return;
        }

        if ($interaction->interaction_type === 'approval') {
            $payload = $interaction->payload ?? [];
            $action = (string) ($payload['action'] ?? '');
            $approvalId = (string) ($interaction->target_id ?? $payload['approval_id'] ?? '');

            if ($action === 'inspect' && $approvalId !== '') {
                if (! $this->linkedTelegramActor($workspace, $callbackQuery)) {
                    $this->answerCallback($callbackQuery, 'Link your Telegram account before inspecting approvals.');

                    return;
                }

                $approval = ApprovalRequest::with('requester')->find($approvalId);
                if ($approval) {
                    $conversation = $message ? $this->resolveConversation($message, $setting, $workspace) : null;
                    if ($conversation) {
                        $this->sendText(
                            $setting,
                            $conversation,
                            app(TelegramApprovalRenderer::class)->inspectText($approval),
                            rendererVersion: TelegramApprovalRenderer::INSPECT_VERSION,
                        );
                    }
                    $this->answerCallback($callbackQuery, 'Approval details sent.');
                }

                return;
            }

            if (in_array($action, ['approve', 'reject'], true) && $approvalId !== '') {
                $this->handleApprovalAction($action, $approvalId, $callbackQuery, $setting, $workspace, $message, $interaction);
            }
        }

        if ($interaction->interaction_type === 'task') {
            $this->handleTaskInteraction($interaction, $callbackQuery, $setting, $workspace, $message);
        }

        if ($interaction->interaction_type === 'command') {
            $this->handleCommandInteraction($interaction, $callbackQuery, $setting, $workspace, $message);
        }

        if ($interaction->interaction_type === 'agent') {
            $this->handleAgentInteraction($interaction, $callbackQuery, $setting, $workspace, $message);
        }

        if ($interaction->interaction_type === 'calendar_draft') {
            $this->handleCalendarInteraction($interaction, $callbackQuery, $setting, $workspace, $message);
        }

        if ($interaction->interaction_type === 'list_draft') {
            $this->handleListDraftInteraction($interaction, $callbackQuery, $setting, $workspace, $message);
        }

        if ($interaction->interaction_type === 'table_row_draft') {
            $this->handleTableRowDraftInteraction($interaction, $callbackQuery, $setting, $workspace, $message);
        }

        if ($interaction->interaction_type === 'table_clarify') {
            $this->handleTableClarifyInteraction($interaction, $callbackQuery, $setting, $workspace, $message);
        }

        if ($interaction->interaction_type === 'media_capture') {
            $this->handleMediaCaptureInteraction($interaction, $callbackQuery, $setting, $workspace, $message);
        }

        if ($interaction->interaction_type === 'notification_snooze') {
            $this->handleNotificationSnoozeInteraction($interaction, $callbackQuery, $setting, $workspace);
        }
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     * @param  array<string, mixed>  $message
     */
    private function handleCommandInteraction(
        TelegramInteraction $interaction,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        array $message
    ): void {
        $conversation = $message ? $this->resolveConversation($message, $setting, $workspace) : null;
        if (! $conversation) {
            return;
        }

        $payload = $interaction->payload ?? [];
        $command = (string) ($payload['command'] ?? '');
        $from = is_array($callbackQuery['from'] ?? null) ? $callbackQuery['from'] : [];
        $messageWithActor = array_merge($message, ['from' => $from]);

        if ($this->isDirectResourceCommand($command) && ! $this->directResourceCommandsEnabled()) {
            $this->sendRemovedCommandNotice($setting, $workspace, $conversation, $command);
            $this->answerCallback($callbackQuery, 'That shortcut was removed.');

            return;
        }

        match ($command) {
            '/link' => $this->sendText($setting, $conversation, $this->linkText($messageWithActor, $setting, $workspace, $conversation), rendererVersion: TelegramCardRenderer::LINK_VERSION),
            '/agents' => $this->sendText(
                $setting,
                $conversation,
                $this->agentsText($workspace),
                $this->agentsReplyMarkup($setting, $workspace),
                TelegramCardRenderer::AGENTS_VERSION,
            ),
            '/tasks' => $this->sendText($setting, $conversation, $this->tasksText($workspace), rendererVersion: TelegramCardRenderer::TASKS_VERSION),
            '/approvals' => $this->sendText($setting, $conversation, $this->approvalsText($workspace), rendererVersion: TelegramCardRenderer::APPROVALS_VERSION),
            '/dashboard' => $this->sendText($setting, $conversation, $this->dashboardText($workspace), rendererVersion: TelegramCardRenderer::DASHBOARD_VERSION),
            '/files' => $this->sendText($setting, $conversation, $this->filesText($workspace), rendererVersion: TelegramCardRenderer::FILES_VERSION),
            '/docs' => $this->sendText($setting, $conversation, $this->docsText($workspace), rendererVersion: TelegramCardRenderer::DOCS_VERSION),
            '/lists' => $this->sendText($setting, $conversation, $this->listsText($workspace), rendererVersion: TelegramCardRenderer::LISTS_VERSION),
            '/tables' => $this->sendText($setting, $conversation, $this->tablesText($workspace), rendererVersion: TelegramCardRenderer::TABLES_VERSION),
            '/automation' => $this->sendAutomationCommand($setting, $workspace, $conversation, $messageWithActor, 'list'),
            '/settings' => $this->sendSettingsCommand($setting, $workspace, $conversation, $messageWithActor),
            default => $this->sendText($setting, $conversation, $this->helpText(), rendererVersion: TelegramCardRenderer::HELP_VERSION),
        };

        $this->answerCallback($callbackQuery, 'Opened '.$this->commandLabel($command).'.');
    }

    /**
     * Snooze lane notifications from a digest card using the same subscription
     * state as the `/snooze` command. The callback remains linked-user gated
     * because digest cards can be forwarded or pressed from shared group chats.
     *
     * @param  array<string, mixed>  $callbackQuery
     */
    private function handleNotificationSnoozeInteraction(
        TelegramInteraction $interaction,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace
    ): void {
        $actor = $this->linkedTelegramActor($workspace, $callbackQuery);
        if (! $actor) {
            $this->answerCallback($callbackQuery, 'Link your Telegram account before snoozing notifications.');

            return;
        }

        $payload = $interaction->payload ?? [];
        $conversation = TelegramConversation::query()
            ->where('workspace_id', $workspace->id)
            ->where('integration_setting_id', $setting->id)
            ->where('id', (string) ($interaction->target_id ?? $payload['conversation_id'] ?? ''))
            ->whereNull('archived_at')
            ->first();

        if (! $conversation) {
            $interaction->update(['resolved_at' => now(), 'resolved_by_id' => $actor->id, 'final_status' => 'missing']);
            $this->answerCallback($callbackQuery, 'Telegram lane not found.');

            return;
        }

        $duration = (string) ($payload['duration'] ?? '2h');
        $snoozedUntil = $this->parseSnoozeUntil($duration) ?? Carbon::now((string) config('app.timezone', 'UTC'))->addHours(2);
        $subscription = TelegramSubscription::firstOrNew([
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'scope_type' => 'telegram_conversation',
            'scope_id' => $conversation->id,
            'event_type' => 'all_notifications',
            'severity' => 'normal',
        ]);

        $subscription->fill([
            'id' => $subscription->id ?: Str::uuid()->toString(),
            'filters' => ['snoozed_until' => $snoozedUntil->toIso8601String()],
            'timezone' => (string) config('app.timezone', 'UTC'),
            'enabled' => true,
        ])->save();

        $interaction->update([
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'final_status' => 'snoozed',
        ]);

        $this->answerCallback(
            $callbackQuery,
            'Snoozed until '.$snoozedUntil->timezone((string) config('app.timezone', 'UTC'))->format('D M j H:i').'.',
        );
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     * @param  array<string, mixed>  $message
     */
    private function handleAgentInteraction(
        TelegramInteraction $interaction,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        array $message
    ): void {
        $conversation = $message ? $this->resolveConversation($message, $setting, $workspace) : null;
        if (! $conversation) {
            return;
        }

        $actor = $this->linkedTelegramActor($workspace, $callbackQuery);
        if (! $actor) {
            $this->answerCallback($callbackQuery, 'Link your Telegram account before changing this lane agent.');

            return;
        }

        $payload = $interaction->payload ?? [];
        $agent = User::where('workspace_id', $workspace->id)
            ->where('type', 'agent')
            ->where('id', $interaction->target_id ?? $payload['agent_id'] ?? '')
            ->first();

        if (! $agent) {
            $interaction->update(['resolved_at' => now(), 'resolved_by_id' => $actor->id, 'final_status' => 'missing']);
            $this->answerCallback($callbackQuery, 'Agent not found.');

            return;
        }

        $conversation->update([
            'default_agent_id' => $agent->id,
            'archived_at' => null,
        ]);

        $interaction->update([
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'final_status' => 'selected',
        ]);

        $this->answerCallback($callbackQuery, "Selected {$agent->name}.");
        $this->sendText($setting, $conversation, "{$agent->name} is now active in this lane.\n\nSend a message here to talk to {$agent->name}.");
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     * @param  array<string, mixed>  $message
     */
    private function handleCalendarInteraction(
        TelegramInteraction $interaction,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        array $message
    ): void {
        $conversation = $message ? $this->resolveConversation($message, $setting, $workspace) : null;
        if (! $conversation) {
            return;
        }

        $actor = $this->linkedTelegramActor($workspace, $callbackQuery);
        if (! $actor) {
            $this->answerCallback($callbackQuery, 'Link your Telegram account before creating calendar events.');

            return;
        }

        $payload = $interaction->payload ?? [];
        $action = (string) ($payload['action'] ?? '');
        $event = is_array($payload['event'] ?? null) ? $payload['event'] : [];

        if ($action === 'cancel') {
            $interaction->update([
                'resolved_at' => now(),
                'resolved_by_id' => $actor->id,
                'final_status' => 'cancelled',
            ]);
            $this->answerCallback($callbackQuery, 'Calendar draft cancelled.');
            $this->sendText($setting, $conversation, 'Calendar draft cancelled.');

            return;
        }

        if ($action !== 'create' || $event === []) {
            $this->answerCallback($callbackQuery, 'Calendar draft is invalid.');

            return;
        }

        $calendarEvent = CalendarEvent::create([
            'workspace_id' => $workspace->id,
            'title' => (string) ($event['title'] ?? 'Untitled event'),
            'description' => $event['description'] ?? null,
            'start_at' => Carbon::parse((string) $event['start_at']),
            'end_at' => Carbon::parse((string) $event['end_at']),
            'all_day' => false,
            'location' => $event['location'] ?? null,
            'created_by' => $actor->id,
        ]);

        $interaction->update([
            'target_type' => CalendarEvent::class,
            'target_id' => $calendarEvent->id,
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'final_status' => 'created',
        ]);

        $this->answerCallback($callbackQuery, 'Calendar event created.');
        $this->sendText($setting, $conversation, "Calendar event created: {$calendarEvent->title}");
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     * @param  array<string, mixed>  $message
     */
    private function handleListDraftInteraction(
        TelegramInteraction $interaction,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        array $message
    ): void {
        $conversation = $message ? $this->resolveConversation($message, $setting, $workspace) : null;
        if (! $conversation) {
            return;
        }

        $actor = $this->linkedTelegramActor($workspace, $callbackQuery);
        if (! $actor) {
            $this->answerCallback($callbackQuery, 'Link your Telegram account before creating list items.');

            return;
        }

        $payload = $interaction->payload ?? [];
        $action = (string) ($payload['action'] ?? '');
        $draft = is_array($payload['draft'] ?? null) ? $payload['draft'] : [];

        if ($action === 'cancel') {
            $interaction->update([
                'resolved_at' => now(),
                'resolved_by_id' => $actor->id,
                'final_status' => 'cancelled',
            ]);
            $this->answerCallback($callbackQuery, 'List item draft cancelled.');
            $this->sendText($setting, $conversation, 'List item draft cancelled.');

            return;
        }

        if ($action !== 'create' || trim((string) ($draft['title'] ?? '')) === '') {
            $this->answerCallback($callbackQuery, 'List item draft is invalid.');

            return;
        }

        $project = $this->resolveListDraftProject($workspace, $actor, (string) ($draft['project_id'] ?? ''));
        $status = (string) ($draft['status'] ?? 'backlog');
        $maxPosition = ListItem::where('workspace_id', $workspace->id)
            ->where('parent_id', $project->id)
            ->where('is_folder', false)
            ->where('status', $status)
            ->max('position') ?? 0;

        $item = ListItem::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'parent_id' => $project->id,
            'is_folder' => false,
            'title' => trim((string) $draft['title']),
            'description' => (string) ($draft['description'] ?? 'Created from Telegram.'),
            'status' => $status,
            'priority' => (string) ($draft['priority'] ?? 'medium'),
            'creator_id' => $actor->id,
            'channel_id' => $conversation->channel_id,
            'position' => $maxPosition + 1,
        ]);

        $interaction->update([
            'target_type' => ListItem::class,
            'target_id' => $item->id,
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'final_status' => 'created',
        ]);

        $this->answerCallback($callbackQuery, 'List item created.');
        $this->sendText($setting, $conversation, "List item created: {$item->title}\nProject: {$project->title}");
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     * @param  array<string, mixed>  $message
     */
    private function handleTableRowDraftInteraction(
        TelegramInteraction $interaction,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        array $message
    ): void {
        $conversation = $message ? $this->resolveConversation($message, $setting, $workspace) : null;
        if (! $conversation) {
            return;
        }

        $actor = $this->linkedTelegramActor($workspace, $callbackQuery);
        if (! $actor) {
            $this->answerCallback($callbackQuery, 'Link your Telegram account before creating table rows.');

            return;
        }

        $payload = $interaction->payload ?? [];
        $action = (string) ($payload['action'] ?? '');
        $draft = is_array($payload['draft'] ?? null) ? $payload['draft'] : [];

        if ($action === 'cancel') {
            $interaction->update([
                'resolved_at' => now(),
                'resolved_by_id' => $actor->id,
                'final_status' => 'cancelled',
            ]);
            $this->answerCallback($callbackQuery, 'Table row draft cancelled.');
            $this->sendText($setting, $conversation, 'Table row draft cancelled.');

            return;
        }

        $table = DataTable::where('workspace_id', $workspace->id)
            ->where('id', (string) ($draft['table_id'] ?? ''))
            ->first();
        $data = is_array($draft['data'] ?? null) ? $draft['data'] : [];

        if ($action !== 'create' || ! $table || $data === []) {
            $this->answerCallback($callbackQuery, 'Table row draft is invalid.');

            return;
        }

        $row = $table->rows()->create([
            'data' => $data,
            'created_by' => $actor->id,
        ]);

        $interaction->update([
            'target_type' => DataTableRow::class,
            'target_id' => $row->id,
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'final_status' => 'created',
        ]);

        $this->answerCallback($callbackQuery, 'Table row created.');
        $this->sendText($setting, $conversation, "Table row created: {$table->name}");
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     * @param  array<string, mixed>  $message
     */
    private function handleTableClarifyInteraction(
        TelegramInteraction $interaction,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        array $message
    ): void {
        $conversation = $message ? $this->resolveConversation($message, $setting, $workspace) : null;
        if (! $conversation) {
            return;
        }

        $actor = $this->linkedTelegramActor($workspace, $callbackQuery);
        if (! $actor) {
            $this->answerCallback($callbackQuery, 'Link your Telegram account before choosing a table.');

            return;
        }

        $payload = $interaction->payload ?? [];
        $action = (string) ($payload['action'] ?? '');
        if ($action === 'cancel') {
            $interaction->update([
                'resolved_at' => now(),
                'resolved_by_id' => $actor->id,
                'final_status' => 'cancelled',
            ]);
            $this->answerCallback($callbackQuery, 'Table selection cancelled.');

            return;
        }

        $table = DataTable::where('workspace_id', $workspace->id)
            ->where('id', (string) ($payload['table_id'] ?? ''))
            ->with('columns')
            ->first();
        $rowInput = trim((string) ($payload['row_input'] ?? ''));
        if ($action !== 'select' || ! $table || $rowInput === '') {
            $this->answerCallback($callbackQuery, 'Table selection is invalid.');

            return;
        }

        $data = $this->parseTableDraftData($table, $rowInput);
        if ($data === []) {
            $this->answerCallback($callbackQuery, 'Could not parse row data for this table.');

            return;
        }

        $draft = [
            'table_id' => $table->id,
            'table_name' => $table->name,
            'data' => $data,
        ];
        $create = $this->tableRowDraftInteraction($setting, $workspace, 'create', $draft);
        $cancel = $this->tableRowDraftInteraction($setting, $workspace, 'cancel', $draft);

        $interaction->update([
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'final_status' => 'selected',
        ]);

        $this->answerCallback($callbackQuery, 'Table selected.');
        $this->sendText(
            $setting,
            $conversation,
            app(TelegramCardRenderer::class)->tableRowDraftText($draft),
            [
                'inline_keyboard' => [
                    [
                        ['text' => 'Create', 'callback_data' => $create->token],
                        ['text' => 'Cancel', 'callback_data' => $cancel->token],
                    ],
                ],
            ],
            TelegramCardRenderer::TABLE_ROW_DRAFT_VERSION,
        );
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     * @param  array<string, mixed>  $message
     */
    private function handleMediaCaptureInteraction(
        TelegramInteraction $interaction,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        array $message
    ): void {
        $conversation = $message ? $this->resolveConversation($message, $setting, $workspace) : null;
        if (! $conversation) {
            return;
        }

        $payload = $interaction->payload ?? [];
        $action = (string) ($payload['action'] ?? '');

        if ($action === 'files') {
            $interaction->update(['resolved_at' => now(), 'final_status' => 'opened_files']);
            $this->answerCallback($callbackQuery, 'Files opened.');
            $this->sendText($setting, $conversation, $this->filesText($workspace), rendererVersion: TelegramCardRenderer::FILES_VERSION);

            return;
        }

        if ($action === 'dismiss') {
            $interaction->update(['resolved_at' => now(), 'final_status' => 'dismissed']);
            $this->answerCallback($callbackQuery, 'Dismissed.');

            return;
        }

        if (! in_array($action, ['summarize', 'create_doc', 'choose_doc', 'attach_to_doc', 'change_folder', 'move_to_folder', 'move_to_root'], true)) {
            $this->answerCallback($callbackQuery, 'Unknown media action.');

            return;
        }

        $actor = $this->linkedTelegramActor($workspace, $callbackQuery);
        if (! $actor) {
            $this->answerCallback($callbackQuery, 'Link your Telegram account before using captured files.');

            return;
        }

        if ($action === 'change_folder') {
            $this->handleMediaCaptureFolderPicker($interaction, $payload, $callbackQuery, $setting, $workspace, $conversation, $actor);

            return;
        }

        if ($action === 'choose_doc') {
            $this->handleMediaCaptureDocumentPicker($interaction, $payload, $callbackQuery, $setting, $workspace, $conversation, $actor);

            return;
        }

        if ($action === 'attach_to_doc') {
            $this->handleMediaCaptureAttachToDocument($interaction, $payload, $callbackQuery, $setting, $workspace, $conversation, $actor);

            return;
        }

        if (in_array($action, ['move_to_folder', 'move_to_root'], true)) {
            $this->handleMediaCaptureMoveFiles($interaction, $payload, $callbackQuery, $setting, $workspace, $conversation, $actor);

            return;
        }

        if ($action === 'create_doc') {
            $this->handleMediaCaptureCreateDocument($interaction, $payload, $callbackQuery, $setting, $workspace, $conversation, $actor);

            return;
        }

        $sourceMessage = Message::whereHas('channel', fn ($channel) => $channel->where('workspace_id', $workspace->id))
            ->where('id', (string) ($interaction->target_id ?? $payload['message_id'] ?? ''))
            ->first();
        $agent = $this->resolveAgent($workspace, $setting, $conversation);

        if (! $sourceMessage || ! $agent || ! $conversation->channel_id) {
            $this->answerCallback($callbackQuery, 'No agent session found for this file.');

            return;
        }

        $task = Task::createPending($sourceMessage, $agent, $conversation->channel_id);
        AgentRespondJob::dispatch($sourceMessage, $agent, $conversation->channel_id, $task->id);

        $interaction->update([
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'final_status' => 'summarize_queued',
        ]);

        $this->answerCallback($callbackQuery, 'Summary queued.');
        $this->sendTaskCard($setting, $conversation, $task->loadMissing('agent'), $actor, TelegramCardRenderer::RUN_CARD_VERSION);
    }

    /**
     * Send a Telegram-native destination chooser for a captured file batch.
     *
     * The original capture button is resolved after opening this picker; each
     * folder row receives a fresh token so forwarded or stale cards cannot move
     * files without passing the same linked-member and workspace checks again.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $callbackQuery
     */
    private function handleMediaCaptureFolderPicker(
        TelegramInteraction $interaction,
        array $payload,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        User $actor
    ): void {
        $files = $this->capturedMediaFiles($workspace, $payload)->all();
        $sourceMessage = Message::whereHas('channel', fn ($channel) => $channel->where('workspace_id', $workspace->id))
            ->where('id', (string) ($interaction->target_id ?? $payload['message_id'] ?? ''))
            ->first();
        if ($files === [] || ! $sourceMessage) {
            $this->answerCallback($callbackQuery, 'Captured files are no longer available.');

            return;
        }

        $folders = $this->mediaCaptureDestinationFolders($workspace, $files);
        $fileIds = array_map(fn (WorkspaceFile $file): string => $file->id, $files);
        $rows = [];
        $rows[] = [[
            'text' => 'Top level',
            'callback_data' => $this->mediaCaptureInteraction(
                $setting,
                $sourceMessage,
                $fileIds,
                'move_to_root',
            )->token,
        ]];

        foreach ($folders->chunk(2) as $chunk) {
            $row = [];
            foreach ($chunk as $folder) {
                $row[] = [
                    'text' => Str::limit($folder->name, 28, ''),
                    'callback_data' => $this->mediaCaptureInteraction(
                        $setting,
                        $sourceMessage,
                        $fileIds,
                        'move_to_folder',
                        ['folder_id' => $folder->id],
                    )->token,
                ];
            }
            $rows[] = $row;
        }

        $interaction->update([
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'final_status' => 'folder_picker_opened',
        ]);

        $this->answerCallback($callbackQuery, 'Choose a folder.');
        $this->sendText(
            $setting,
            $conversation,
            app(TelegramCardRenderer::class)->mediaFolderPickerText($files, $folders),
            ['inline_keyboard' => $rows],
            TelegramCardRenderer::MEDIA_FOLDER_PICKER_VERSION,
        );
    }

    /**
     * Move captured Telegram files after the user chooses a destination folder.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $callbackQuery
     */
    private function handleMediaCaptureMoveFiles(
        TelegramInteraction $interaction,
        array $payload,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        User $actor
    ): void {
        $files = $this->capturedMediaFiles($workspace, $payload);
        if ($files->isEmpty()) {
            $this->answerCallback($callbackQuery, 'Captured files are no longer available.');

            return;
        }

        $folderId = ($payload['action'] ?? null) === 'move_to_root' ? null : (string) ($payload['folder_id'] ?? '');
        $folder = null;
        if ($folderId !== null && $folderId !== '') {
            $folder = WorkspaceFile::where('workspace_id', $workspace->id)
                ->where('id', $folderId)
                ->where('is_folder', true)
                ->first();

            if (! $folder) {
                $this->answerCallback($callbackQuery, 'Folder is no longer available.');

                return;
            }
        }

        foreach ($files as $file) {
            app(FileSystemService::class)->moveFile($file, $folder?->id);
        }

        $destination = $folder ? $folder->getVirtualPath() : 'Top level';
        $interaction->update([
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'final_status' => 'moved',
        ]);

        $this->answerCallback($callbackQuery, 'Files moved.');
        $this->sendText($setting, $conversation, "Moved {$files->count()} Telegram file(s)\nDestination: {$destination}");
    }

    /**
     * Send a recent-document picker for attaching captured Telegram files.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $callbackQuery
     */
    private function handleMediaCaptureDocumentPicker(
        TelegramInteraction $interaction,
        array $payload,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        User $actor
    ): void {
        $files = $this->capturedMediaFiles($workspace, $payload)->all();
        $sourceMessage = Message::whereHas('channel', fn ($channel) => $channel->where('workspace_id', $workspace->id))
            ->where('id', (string) ($interaction->target_id ?? $payload['message_id'] ?? ''))
            ->first();

        if ($files === [] || ! $sourceMessage) {
            $this->answerCallback($callbackQuery, 'Captured files are no longer available.');

            return;
        }

        $documents = $this->mediaCaptureDestinationDocuments($workspace);
        $fileIds = array_map(fn (WorkspaceFile $file): string => $file->id, $files);
        $rows = [];
        foreach ($documents->chunk(1) as $chunk) {
            foreach ($chunk as $document) {
                $rows[] = [[
                    'text' => Str::limit($document->title, 42, ''),
                    'callback_data' => $this->mediaCaptureInteraction(
                        $setting,
                        $sourceMessage,
                        $fileIds,
                        'attach_to_doc',
                        ['document_id' => $document->id],
                    )->token,
                ]];
            }
        }

        $interaction->update([
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'final_status' => 'document_picker_opened',
        ]);

        $this->answerCallback($callbackQuery, $documents->isEmpty() ? 'No recent docs found.' : 'Choose a document.');
        $this->sendText(
            $setting,
            $conversation,
            app(TelegramCardRenderer::class)->mediaDocumentPickerText($files, $documents),
            $rows === [] ? null : ['inline_keyboard' => $rows],
            TelegramCardRenderer::MEDIA_DOCUMENT_PICKER_VERSION,
        );
    }

    /**
     * Attach captured Telegram files to an existing workspace document.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $callbackQuery
     */
    private function handleMediaCaptureAttachToDocument(
        TelegramInteraction $interaction,
        array $payload,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        User $actor
    ): void {
        $files = $this->capturedMediaFiles($workspace, $payload);
        $documentId = (string) ($payload['document_id'] ?? '');
        $document = Document::where('workspace_id', $workspace->id)
            ->where('is_folder', false)
            ->where('id', $documentId)
            ->first();
        $sourceMessage = Message::whereHas('channel', fn ($channel) => $channel->where('workspace_id', $workspace->id))
            ->where('id', (string) ($interaction->target_id ?? $payload['message_id'] ?? ''))
            ->first();

        if ($files->isEmpty() || ! $document || ! $sourceMessage) {
            $this->answerCallback($callbackQuery, 'Document or captured files are no longer available.');

            return;
        }

        $attached = $this->attachCapturedFilesToDocument($document, $files->all(), $actor);
        if ($attached > 0) {
            $document->update([
                'content' => $this->documentContentWithTelegramCapture($document, $sourceMessage, $files->all()),
            ]);
        }

        $interaction->update([
            'target_type' => Document::class,
            'target_id' => $document->id,
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'final_status' => 'attached_to_doc',
        ]);

        $this->answerCallback($callbackQuery, $attached > 0 ? 'Files added to document.' : 'Files were already attached.');
        $this->sendText($setting, $conversation, "Files added to doc: {$document->title}\nAttached: {$attached}");
    }

    /**
     * Load captured files from callback payload under the active workspace.
     *
     * @param  array<string, mixed>  $payload
     * @return \Illuminate\Support\Collection<int, WorkspaceFile>
     */
    private function capturedMediaFiles(Workspace $workspace, array $payload): \Illuminate\Support\Collection
    {
        $fileIds = collect($payload['workspace_file_ids'] ?? [])
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        return WorkspaceFile::where('workspace_id', $workspace->id)
            ->where('is_folder', false)
            ->whereIn('id', $fileIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Document>
     */
    private function mediaCaptureDestinationDocuments(Workspace $workspace): \Illuminate\Support\Collection
    {
        return Document::where('workspace_id', $workspace->id)
            ->where('is_folder', false)
            ->where('is_system', false)
            ->latest('updated_at')
            ->orderBy('title')
            ->limit(6)
            ->get();
    }

    /**
     * Return a bounded set of existing workspace folders suitable for Telegram.
     *
     * @param  list<WorkspaceFile>  $files
     * @return \Illuminate\Support\Collection<int, WorkspaceFile>
     */
    private function mediaCaptureDestinationFolders(Workspace $workspace, array $files): \Illuminate\Support\Collection
    {
        $currentParentIds = collect($files)
            ->map(fn (WorkspaceFile $file): ?string => $file->parent_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return WorkspaceFile::where('workspace_id', $workspace->id)
            ->where('is_folder', true)
            ->when($currentParentIds !== [], fn (Builder $query) => $query->whereNotIn('id', $currentParentIds))
            ->latest('updated_at')
            ->orderBy('name')
            ->limit(6)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $callbackQuery
     */
    private function handleMediaCaptureCreateDocument(
        TelegramInteraction $interaction,
        array $payload,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        User $actor
    ): void {
        $sourceMessage = Message::whereHas('channel', fn ($channel) => $channel->where('workspace_id', $workspace->id))
            ->where('id', (string) ($interaction->target_id ?? $payload['message_id'] ?? ''))
            ->first();
        $fileIds = collect($payload['workspace_file_ids'] ?? [])
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();
        $files = WorkspaceFile::where('workspace_id', $workspace->id)
            ->whereIn('id', $fileIds)
            ->orderBy('name')
            ->get();

        if (! $sourceMessage || $files->isEmpty()) {
            $this->answerCallback($callbackQuery, 'Captured files are no longer available.');

            return;
        }

        $title = Str::limit('Telegram capture - '.$files->first()->name, 120, '');
        $content = $this->mediaCaptureDocumentContent($sourceMessage, $files->all());
        $document = Document::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'title' => $title,
            'content' => $content,
            'content_format' => 'markdown',
            'author_id' => $actor->id,
            'is_folder' => false,
            'icon' => 'paperclip',
        ]);

        foreach ($files as $file) {
            $this->attachCapturedFilesToDocument($document, [$file], $actor);
        }

        $interaction->update([
            'target_type' => Document::class,
            'target_id' => $document->id,
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
            'final_status' => 'doc_created',
        ]);

        $this->answerCallback($callbackQuery, 'Document created.');
        $this->sendText($setting, $conversation, "Document created: {$document->title}\nFiles: ".$files->count());
    }

    /**
     * @param  list<WorkspaceFile>  $files
     */
    private function attachCapturedFilesToDocument(Document $document, array $files, User $actor): int
    {
        $attached = 0;

        foreach ($files as $file) {
            $url = "/api/files/{$file->id}/download";
            $exists = DocumentAttachment::where('document_id', $document->id)
                ->where('url', $url)
                ->exists();

            if ($exists) {
                continue;
            }

            DocumentAttachment::create([
                'id' => Str::uuid()->toString(),
                'document_id' => $document->id,
                'filename' => basename($file->storage_path),
                'original_name' => $file->name,
                'mime_type' => $file->mime_type ?? 'application/octet-stream',
                'size' => $file->size ?? 0,
                'url' => $url,
                'uploaded_by_id' => $actor->id,
            ]);

            $attached++;
        }

        return $attached;
    }

    /**
     * @param  list<WorkspaceFile>  $files
     */
    private function documentContentWithTelegramCapture(Document $document, Message $message, array $files): string
    {
        $content = trim((string) $document->content);
        $section = $this->mediaCaptureDocumentContent($message, $files);

        return trim($content."\n\n---\n\n".$section);
    }

    /**
     * @param  list<WorkspaceFile>  $files
     */
    private function mediaCaptureDocumentContent(Message $message, array $files): string
    {
        $lines = [
            '# Telegram capture',
            '',
            'Captured from Telegram into OpenCompany.',
            '',
        ];

        if (trim($message->content) !== '') {
            $lines[] = '## Message';
            $lines[] = '';
            foreach (explode("\n", trim($message->content)) as $line) {
                $lines[] = '> '.$line;
            }
            $lines[] = '';
        }

        $lines[] = '## Files';
        $lines[] = '';
        foreach ($files as $file) {
            $details = trim(implode(', ', array_filter([
                $file->mime_type,
                $file->size ? number_format((int) $file->size).' bytes' : null,
            ])));
            $suffix = $details !== '' ? " ({$details})" : '';
            $lines[] = "- [{$file->name}](/api/files/{$file->id}/download){$suffix}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     * @param  array<string, mixed>  $message
     */
    private function handleApprovalAction(
        string $action,
        string $approvalId,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        array $message,
        TelegramInteraction $interaction
    ): void {
        $approval = ApprovalRequest::with(['channel', 'requester'])->find($approvalId);
        if (! $approval || $approval->status !== 'pending') {
            $this->answerCallback($callbackQuery, 'This approval has already been decided.');

            return;
        }

        $approvalWorkspaceId = $approval->channel?->workspace_id ?? $approval->requester?->workspace_id;
        if ($approvalWorkspaceId !== $workspace->id) {
            $this->answerCallback($callbackQuery, 'You are not authorized for this approval.');

            return;
        }

        $responder = $this->linkedTelegramActor($workspace, $callbackQuery);
        if (! $responder) {
            $this->answerCallback($callbackQuery, 'Link your Telegram account before approving workspace actions.');

            return;
        }

        $status = $action === 'approve' ? 'approved' : 'rejected';

        $approvalService = app(ApprovalExecutionService::class);
        if (! $approvalService->resolve($approval, $status, $responder)) {
            $this->answerCallback($callbackQuery, 'This approval has already been decided.');

            return;
        }

        $interaction?->update([
            'resolved_at' => now(),
            'resolved_by_id' => $responder->id,
            'final_status' => $status,
        ]);

        $this->answerCallback($callbackQuery, ucfirst($status).'.');

        $resolvedApproval = $approval->fresh(['requester', 'respondedBy']) ?? $approval;
        $this->editApprovalMessage($setting, $message, $resolvedApproval, $status, $responder);
        $this->notifyApprovalResolution($setting, $workspace, $resolvedApproval, $status, $responder);
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     * @param  array<string, mixed>  $message
     */
    private function handleTaskInteraction(
        TelegramInteraction $interaction,
        array $callbackQuery,
        IntegrationSetting $setting,
        Workspace $workspace,
        array $message
    ): void {
        $responder = $this->linkedTelegramActor($workspace, $callbackQuery);
        if (! $responder) {
            $this->answerCallback($callbackQuery, 'Link your Telegram account before changing tasks.');

            return;
        }

        $payload = $interaction->payload ?? [];
        $action = (string) ($payload['action'] ?? '');
        $task = Task::where('workspace_id', $workspace->id)
            ->where('id', $interaction->target_id ?? $payload['task_id'] ?? '')
            ->first();

        if (! $task) {
            $interaction->update(['resolved_at' => now(), 'resolved_by_id' => $responder->id, 'final_status' => 'missing']);
            $this->answerCallback($callbackQuery, 'Task not found.');

            return;
        }

        match ($action) {
            'pause' => $task->pause(),
            'resume' => $task->resume(),
            'cancel' => $task->cancel(),
            default => null,
        };
        if ($action === 'cancel') {
            event(new TaskUpdated($task->fresh() ?? $task, 'cancelled'));
        }

        $interaction->update([
            'resolved_at' => now(),
            'resolved_by_id' => $responder->id,
            'final_status' => $action,
        ]);

        $this->answerCallback($callbackQuery, ucfirst($action).'.');
        $this->editTaskMessage($setting, $message, $task->fresh(['agent', 'steps', 'workspace']), $responder);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function editTaskMessage(IntegrationSetting $setting, array $message, Task $task, User $actor): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $messageId = isset($message['message_id']) ? (int) $message['message_id'] : null;
        if ($chatId === '' || ! $messageId) {
            return;
        }

        $text = $this->telegramCardHtml(app(TelegramCardRenderer::class)->taskCardText($task));
        $replyMarkup = $this->taskReplyMarkup($setting, $task);
        $previousDelivery = TelegramDelivery::where('workspace_id', $task->workspace_id)
            ->where('integration_setting_id', $setting->id)
            ->where('source_type', Task::class)
            ->where('source_id', $task->id)
            ->where('status', 'sent')
            ->where('telegram_message_id', (string) $messageId)
            ->latest('sent_at')
            ->latest('created_at')
            ->first();

        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $task->workspace_id,
            'integration_setting_id' => $setting->id,
            'source_type' => Task::class,
            'source_id' => $task->id,
            'chat_id' => $chatId,
            'topic_id' => $previousDelivery?->topic_id,
            'direct_messages_topic_id' => $previousDelivery?->direct_messages_topic_id,
            'telegram_message_id' => (string) $messageId,
            'parse_mode' => 'HTML',
            'renderer_version' => TelegramCardRenderer::TASK_CARD_UPDATED_VERSION,
            'status' => 'pending',
            'previous_delivery_id' => $previousDelivery?->id,
            'request_payload' => [
                'method' => 'editMessageText',
                'message_id' => $messageId,
                'task_id' => $task->id,
                'resolved_by_id' => $actor->id,
                'text' => $text,
                'reply_markup' => $replyMarkup,
                'buttons' => $this->taskButtonLabels($task),
            ],
        ]);

        try {
            $result = app(TelegramService::class)->editMessageText(
                $chatId,
                $messageId,
                $text,
                $replyMarkup,
            );

            $delivery->update([
                'status' => 'sent',
                'parse_mode' => ($result['_opencompany_parse_mode_fallback'] ?? false) ? 'plain_text_fallback' : 'HTML',
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markDeliveryFailed($delivery, $e);

            Log::warning('Failed to edit Telegram task card', [
                'workspace_id' => $setting->workspace_id,
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     */
    private function answerCallback(array $callbackQuery, string $text): void
    {
        $callbackQueryId = (string) ($callbackQuery['id'] ?? '');
        if ($callbackQueryId === '') {
            return;
        }

        try {
            app(TelegramService::class)->answerCallbackQuery($callbackQueryId, $text);
        } catch (\Throwable) {
            // Non-critical callback acknowledgement path.
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function editApprovalMessage(
        IntegrationSetting $setting,
        array $message,
        ApprovalRequest $approval,
        string $status,
        User $responder
    ): void {
        $previousDelivery = TelegramDelivery::where('workspace_id', $setting->workspace_id)
            ->where('integration_setting_id', $setting->id)
            ->where('source_type', ApprovalRequest::class)
            ->where('source_id', $approval->id)
            ->where('status', 'sent')
            ->where('renderer_version', TelegramApprovalRenderer::PENDING_VERSION)
            ->whereNotNull('telegram_message_id')
            ->latest('sent_at')
            ->latest('created_at')
            ->first();

        // Prefer the recorded outbound delivery over the callback envelope. The
        // callback message can be absent or stale, while the delivery row is the
        // app-owned reconciliation record for the original approval card.
        $chatId = (string) ($previousDelivery?->chat_id ?: ($message['chat']['id'] ?? ''));
        $messageId = $previousDelivery?->telegram_message_id
            ? (int) $previousDelivery->telegram_message_id
            : (isset($message['message_id']) ? (int) $message['message_id'] : null);
        if ($chatId === '' || ! $messageId) {
            return;
        }

        $text = app(TelegramApprovalRenderer::class)->resolvedHtml($approval, $status, $responder);
        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $setting->workspace_id,
            'integration_setting_id' => $setting->id,
            'source_type' => ApprovalRequest::class,
            'source_id' => $approval->id,
            'chat_id' => $chatId,
            'topic_id' => $previousDelivery?->topic_id,
            'direct_messages_topic_id' => $previousDelivery?->direct_messages_topic_id,
            'telegram_message_id' => (string) $messageId,
            'parse_mode' => 'HTML',
            'renderer_version' => TelegramApprovalRenderer::RESOLVED_VERSION,
            'status' => 'pending',
            'previous_delivery_id' => $previousDelivery?->id,
            'request_payload' => [
                'method' => 'editMessageText',
                'approval_id' => $approval->id,
                'status' => $status,
                'message_id' => $messageId,
                'buttons' => [],
                'text' => $text,
            ],
        ]);

        try {
            $result = app(TelegramService::class)->editMessageText(
                $chatId,
                $messageId,
                $text,
                ['inline_keyboard' => []],
            );

            $delivery->update([
                'status' => 'sent',
                'parse_mode' => ($result['_opencompany_parse_mode_fallback'] ?? false) ? 'plain_text_fallback' : 'HTML',
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

            Log::warning('Failed to edit Telegram approval message', [
                'workspace_id' => $setting->workspace_id,
                'approval_id' => $approval->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyApprovalResolution(
        IntegrationSetting $setting,
        Workspace $workspace,
        ApprovalRequest $approval,
        string $status,
        User $responder
    ): void {
        if (! $approval->channel_id) {
            return;
        }

        $conversation = TelegramConversation::where('workspace_id', $workspace->id)
            ->where('integration_setting_id', $setting->id)
            ->where('channel_id', $approval->channel_id)
            ->whereNull('archived_at')
            ->latest('last_seen_at')
            ->latest('created_at')
            ->first();

        if (! $conversation) {
            return;
        }

        $this->sendText(
            $setting,
            $conversation,
            app(TelegramApprovalRenderer::class)->resolutionNotificationText($approval, $status, $responder),
            rendererVersion: TelegramApprovalRenderer::RESOLUTION_NOTIFICATION_VERSION,
        );
    }

    /**
     * @param  array<string, mixed>  $reaction
     */
    private function rememberConversationFromReaction(
        array $reaction,
        IntegrationSetting $setting,
        Workspace $workspace
    ): ?TelegramConversation {
        $chat = is_array($reaction['chat'] ?? null) ? $reaction['chat'] : [];
        if ($chat === []) {
            return null;
        }

        return $this->resolveConversation(['chat' => $chat], $setting, $workspace);
    }

    /**
     * Apply Telegram's replace/clear reaction semantics to OpenCompany's local
     * message reactions. Unlike Slack-style add/remove events, Telegram sends
     * the complete new reaction set for the actor on the message, so existing
     * local reactions by that actor must be replaced atomically.
     *
     * @param  array<string, mixed>  $reaction
     */
    private function handleMessageReaction(
        array $reaction,
        IntegrationSetting $setting,
        Workspace $workspace
    ): ?TelegramConversation {
        $conversation = $this->rememberConversationFromReaction($reaction, $setting, $workspace);
        if (! $conversation || ! $conversation->channel_id || ! isset($reaction['message_id'])) {
            return $conversation;
        }

        $message = Message::where('channel_id', $conversation->channel_id)
            ->where('external_message_id', (string) $reaction['message_id'])
            ->first();

        $telegramUser = is_array($reaction['user'] ?? null) ? $reaction['user'] : [];
        if (! $message || $telegramUser === []) {
            return $conversation;
        }

        $actor = $this->resolveUser($workspace, $telegramUser);
        ChannelMember::firstOrCreate(
            ['channel_id' => $conversation->channel_id, 'user_id' => $actor->id],
            ['id' => Str::uuid()->toString(), 'joined_at' => now()],
        );

        MessageReaction::where('message_id', $message->id)
            ->where('user_id', $actor->id)
            ->delete();

        foreach ($this->reactionEmojis($reaction['new_reaction'] ?? []) as $emoji) {
            MessageReaction::firstOrCreate(
                [
                    'message_id' => $message->id,
                    'user_id' => $actor->id,
                    'emoji' => $emoji,
                ],
                ['id' => Str::uuid()->toString()],
            );
        }

        return $conversation;
    }

    /**
     * @return list<string>
     */
    private function reactionEmojis(mixed $reactions): array
    {
        if (! is_array($reactions)) {
            return [];
        }

        return collect($reactions)
            ->filter(fn ($reaction) => is_array($reaction) && ($reaction['type'] ?? null) === 'emoji' && is_string($reaction['emoji'] ?? null))
            ->map(fn (array $reaction) => $reaction['emoji'])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $memberUpdate
     */
    private function handleChatMemberUpdate(
        array $memberUpdate,
        IntegrationSetting $setting,
        Workspace $workspace,
        bool $ownBotStatus
    ): ?TelegramConversation {
        $conversation = $this->rememberConversationFromChatPayload($memberUpdate, $setting, $workspace);
        $newMember = is_array($memberUpdate['new_chat_member'] ?? null) ? $memberUpdate['new_chat_member'] : [];
        $newStatus = (string) ($newMember['status'] ?? 'unknown');

        $profile = app(TelegramSetupService::class)->profileFor($setting);
        $capabilities = $profile->capabilities ?? [];
        $capabilities['last_chat_member_update'] = [
            'own_bot_status' => $ownBotStatus,
            'chat_id' => $conversation?->chat_id,
            'status' => $newStatus,
            'at' => now()->toIso8601String(),
        ];

        $updates = [
            'capabilities' => $capabilities,
            'last_health_checked_at' => now(),
        ];

        if ($ownBotStatus && in_array($newStatus, ['kicked', 'left', 'restricted'], true)) {
            $updates['health_status'] = 'degraded';
            $updates['last_health_error'] = "Telegram bot status in chat {$conversation?->chat_id}: {$newStatus}";
        }

        if ($ownBotStatus && in_array($newStatus, ['member', 'administrator'], true)) {
            $updates['health_status'] = 'healthy';
            $updates['last_health_error'] = null;
        }

        $profile->update($updates);

        return $conversation;
    }

    /**
     * Answer Telegram inline-mode queries with safe OpenCompany launcher cards.
     *
     * Inline queries are not workspace chat messages and may be invoked from chats
     * OpenCompany cannot see. The response therefore only offers text launchers
     * and authenticated deep-link instructions; actual workspace mutation remains
     * behind normal linked-user commands, Mini App panels, or web auth.
     *
     * @param  array<string, mixed>  $query
     */
    private function handleInlineQuery(array $query, IntegrationSetting $setting, Workspace $workspace): ?TelegramConversation
    {
        $inlineQueryId = isset($query['id']) && is_scalar($query['id']) ? (string) $query['id'] : '';
        $from = is_array($query['from'] ?? null) ? $query['from'] : [];
        $externalId = isset($from['id']) && is_scalar($from['id']) ? (string) $from['id'] : '';

        if ($inlineQueryId === '') {
            $this->rememberInlineQuery($setting, $query, 'missing_inline_query_id', answered: false);

            return null;
        }

        $linked = $externalId !== '' ? UserExternalIdentity::resolveUser('telegram', $externalId) : null;
        $isLinkedMember = $linked && $this->belongsToWorkspace($linked, $workspace);
        $results = $this->inlineQueryResults($setting, $workspace, (bool) $isLinkedMember);
        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => $externalId !== '' ? "inline:{$externalId}" : 'inline:unknown',
            'parse_mode' => 'HTML',
            'renderer_version' => 'telegram-inline-query:v1',
            'status' => 'pending',
            'request_payload' => [
                'method' => 'answerInlineQuery',
                'inline_query_id' => $inlineQueryId,
                'results' => $results,
                'cache_time' => 0,
                'is_personal' => true,
                'linked_workspace_member' => (bool) $isLinkedMember,
            ],
        ]);

        try {
            $result = app(TelegramService::class)->answerInlineQuery($inlineQueryId, $results);
            $delivery->update([
                'status' => 'sent',
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);
            $this->rememberInlineQuery($setting, $query, 'answered', answered: true, linked: (bool) $isLinkedMember);
        } catch (\Throwable $e) {
            $this->markDeliveryFailed($delivery, $e);
            $this->rememberInlineQuery($setting, $query, 'answer_failed', answered: false, linked: (bool) $isLinkedMember);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inlineQueryResults(IntegrationSetting $setting, Workspace $workspace, bool $linked): array
    {
        $botUsername = (string) $setting->getConfigValue('bot_username', 'OpenCompanyBot');
        $startText = $botUsername !== '' ? "Open @{$botUsername} and run /start." : 'Open the OpenCompany bot and run /start.';
        $linkText = $botUsername !== '' ? "Open @{$botUsername} and run /link." : 'Open the OpenCompany bot and run /link.';

        if (! $linked) {
            return [[
                'type' => 'article',
                'id' => 'opencompany-link',
                'title' => 'Link OpenCompany',
                'description' => 'Connect your Telegram identity before using workspace actions.',
                'input_message_content' => [
                    'message_text' => "OpenCompany\n\n{$linkText}",
                    'parse_mode' => 'HTML',
                ],
            ]];
        }

        return [
            [
                'type' => 'article',
                'id' => 'opencompany-agent-home',
                'title' => 'OpenCompany',
                'description' => "Talk to the active agent for {$workspace->name}.",
                'input_message_content' => [
                    'message_text' => "OpenCompany\n\n{$startText}",
                    'parse_mode' => 'HTML',
                ],
            ],
            [
                'type' => 'article',
                'id' => 'opencompany-approvals',
                'title' => 'OpenCompany approvals',
                'description' => 'Review decisions the agent is waiting on.',
                'input_message_content' => [
                    'message_text' => "OpenCompany approvals\n\n{$startText} Then use /approvals.",
                    'parse_mode' => 'HTML',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function rememberInlineQuery(
        IntegrationSetting $setting,
        array $query,
        string $status,
        bool $answered,
        bool $linked = false
    ): void {
        $profile = app(TelegramSetupService::class)->profileFor($setting);
        $from = is_array($query['from'] ?? null) ? $query['from'] : [];
        $capabilities = $profile->capabilities ?? [];
        $capabilities['last_inline_query'] = [
            'status' => $status,
            'answered' => $answered,
            'linked_workspace_member' => $linked,
            'query_id' => isset($query['id']) && is_scalar($query['id']) ? (string) $query['id'] : null,
            'from_id' => isset($from['id']) && is_scalar($from['id']) ? (string) $from['id'] : null,
            'query' => isset($query['query']) && is_scalar($query['query']) ? Str::limit((string) $query['query'], 200) : '',
            'at' => now()->toIso8601String(),
        ];

        $profile->update([
            'capabilities' => $capabilities,
            'last_health_checked_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function rememberInlineResult(array $result, IntegrationSetting $setting): ?TelegramConversation
    {
        $profile = app(TelegramSetupService::class)->profileFor($setting);
        $from = is_array($result['from'] ?? null) ? $result['from'] : [];
        $capabilities = $profile->capabilities ?? [];
        $capabilities['last_chosen_inline_result'] = [
            'result_id' => isset($result['result_id']) && is_scalar($result['result_id']) ? (string) $result['result_id'] : null,
            'from_id' => isset($from['id']) && is_scalar($from['id']) ? (string) $from['id'] : null,
            'inline_message_id' => isset($result['inline_message_id']) && is_scalar($result['inline_message_id']) ? (string) $result['inline_message_id'] : null,
            'query' => isset($result['query']) && is_scalar($result['query']) ? Str::limit((string) $result['query'], 200) : '',
            'at' => now()->toIso8601String(),
        ];

        $profile->update([
            'capabilities' => $capabilities,
            'last_health_checked_at' => now(),
        ]);

        return null;
    }

    /**
     * Handle Telegram guest mode without merging it into linked-user chat.
     *
     * Guest messages can come from chats where the bot is not a member. Until a
     * workspace admin explicitly enables richer guest workflows, OpenCompany only
     * sends a bounded informational answer through answerGuestQuery, records the
     * delivery, and updates diagnostics. It deliberately does not create
     * OpenCompany messages, shadow users, or agent tasks.
     *
     * @param  array<string, mixed>  $message
     */
    private function handleGuestMessage(array $message, IntegrationSetting $setting, Workspace $workspace): ?TelegramConversation
    {
        $conversation = $this->rememberConversationFromChatPayload($message, $setting, $workspace);
        $guestQueryId = isset($message['guest_query_id']) && is_scalar($message['guest_query_id'])
            ? (string) $message['guest_query_id']
            : '';

        if ($guestQueryId === '') {
            $this->rememberGuestMessage($setting, $conversation, $message, 'missing_guest_query_id');

            return $conversation;
        }

        if (! $this->reserveGuestReplySlot($setting, $message)) {
            $this->rememberGuestMessage($setting, $conversation, $message, 'rate_limited', answered: false);

            return $conversation;
        }

        $text = TelegramService::markdownToTelegramHtml((string) config('telegram.guest_reply_text'));
        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'chat_id' => $conversation?->chat_id ?: 'guest',
            'topic_id' => $conversation?->topic_id,
            'direct_messages_topic_id' => $conversation?->direct_messages_topic_id,
            'parse_mode' => 'HTML',
            'renderer_version' => 'telegram-guest-reply:v1',
            'status' => 'pending',
            'request_payload' => [
                'method' => 'answerGuestQuery',
                'guest_query_id' => $guestQueryId,
                'text' => $text,
                'title' => 'OpenCompany',
            ],
        ]);

        try {
            $result = app(TelegramService::class)->answerGuestQuery($guestQueryId, $text, 'OpenCompany');
            $delivery->update([
                'status' => 'sent',
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);
            $this->rememberGuestMessage($setting, $conversation, $message, 'answered', answered: true);
        } catch (\Throwable $e) {
            $this->markDeliveryFailed($delivery, $e);
            $this->rememberGuestMessage($setting, $conversation, $message, 'answer_failed', answered: false);
        }

        return $conversation;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function reserveGuestReplySlot(IntegrationSetting $setting, array $message): bool
    {
        $actorId = $this->guestActorId($message);
        $seconds = max(1, (int) config('telegram.guest_rate_limit_seconds', 60));

        return Cache::add("telegram:guest-reply:{$setting->id}:{$actorId}", true, now()->addSeconds($seconds));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function guestActorId(array $message): string
    {
        foreach (['guest_bot_caller_user', 'from'] as $key) {
            $user = is_array($message[$key] ?? null) ? $message[$key] : [];
            if (isset($user['id']) && is_scalar($user['id'])) {
                return 'user:'.(string) $user['id'];
            }
        }

        $chat = is_array($message['guest_bot_caller_chat'] ?? null)
            ? $message['guest_bot_caller_chat']
            : (is_array($message['chat'] ?? null) ? $message['chat'] : []);

        if (isset($chat['id']) && is_scalar($chat['id'])) {
            return 'chat:'.(string) $chat['id'];
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function rememberGuestMessage(
        IntegrationSetting $setting,
        ?TelegramConversation $conversation,
        array $message,
        string $status,
        bool $answered = false
    ): void {
        $profile = app(TelegramSetupService::class)->profileFor($setting);
        $capabilities = $profile->capabilities ?? [];
        $capabilities['last_guest_message'] = [
            'status' => $status,
            'answered' => $answered,
            'chat_id' => $conversation?->chat_id,
            'guest_query_id' => isset($message['guest_query_id']) && is_scalar($message['guest_query_id']) ? (string) $message['guest_query_id'] : null,
            'actor' => $this->guestActorId($message),
            'at' => now()->toIso8601String(),
        ];

        $profile->update([
            'capabilities' => $capabilities,
            'last_health_checked_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function rememberUnsupportedModernUpdate(
        string $type,
        array $update,
        IntegrationSetting $setting,
        Workspace $workspace
    ): ?TelegramConversation {
        $conversation = $this->rememberConversationFromChatPayload($update, $setting, $workspace);
        $profile = app(TelegramSetupService::class)->profileFor($setting);
        $capabilities = $profile->capabilities ?? [];
        $capabilities['last_unsupported_update'] = [
            'type' => $type,
            'chat_id' => $conversation?->chat_id,
            'at' => now()->toIso8601String(),
        ];

        $profile->update([
            'capabilities' => $capabilities,
            'last_health_checked_at' => now(),
        ]);

        return $conversation;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function rememberDisabledModernUpdate(
        string $type,
        array $update,
        IntegrationSetting $setting,
        Workspace $workspace,
        string $featureFlag
    ): ?TelegramConversation {
        $conversation = $this->rememberConversationFromChatPayload($update, $setting, $workspace);
        $profile = app(TelegramSetupService::class)->profileFor($setting);
        $capabilities = $profile->capabilities ?? [];
        $capabilities['last_disabled_update'] = [
            'type' => $type,
            'feature_flag' => $featureFlag,
            'chat_id' => $conversation?->chat_id,
            'at' => now()->toIso8601String(),
        ];

        $profile->update([
            'capabilities' => $capabilities,
            'last_health_checked_at' => now(),
        ]);

        return $conversation;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rememberConversationFromChatPayload(
        array $payload,
        IntegrationSetting $setting,
        Workspace $workspace
    ): ?TelegramConversation {
        $chat = is_array($payload['chat'] ?? null) ? $payload['chat'] : [];
        if ($chat === []) {
            return null;
        }

        return $this->resolveConversation(['chat' => $chat], $setting, $workspace);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function resolveConversation(array $message, IntegrationSetting $setting, Workspace $workspace): TelegramConversation
    {
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $chatId = (string) ($chat['id'] ?? '');
        $topicId = isset($message['message_thread_id']) ? (string) $message['message_thread_id'] : null;
        $directMessagesTopicId = config('telegram.private_topics_enabled', true) && isset($message['direct_messages_topic_id'])
            ? (string) $message['direct_messages_topic_id']
            : null;

        $conversation = TelegramConversation::firstOrCreate(
            [
                'integration_setting_id' => $setting->id,
                'chat_id' => $chatId,
                'topic_id' => $topicId,
                'direct_messages_topic_id' => $directMessagesTopicId,
            ],
            [
                'id' => Str::uuid()->toString(),
                'workspace_id' => $workspace->id,
                'chat_type' => (string) ($chat['type'] ?? 'private'),
                'title' => $this->chatTitle($chat),
                'username' => isset($chat['username']) ? (string) $chat['username'] : null,
                'default_agent_id' => $setting->getConfigValue('default_agent_id'),
                'mode' => (string) $setting->getConfigValue('default_mode', 'command_center'),
                'last_seen_at' => now(),
            ],
        );

        $conversation->fill([
            'chat_type' => (string) ($chat['type'] ?? $conversation->chat_type),
            'title' => $this->chatTitle($chat) ?: $conversation->title,
            'username' => isset($chat['username']) ? (string) $chat['username'] : $conversation->username,
            'last_seen_at' => now(),
        ])->save();

        return $conversation;
    }

    /**
     * @param  array<string, mixed>  $chat
     */
    private function chatTitle(array $chat): ?string
    {
        $title = $chat['title'] ?? null;
        if (is_string($title) && $title !== '') {
            return $title;
        }

        $name = trim(((string) ($chat['first_name'] ?? '')).' '.((string) ($chat['last_name'] ?? '')));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function channelForConversation(TelegramConversation $conversation, array $message): Channel
    {
        if ($conversation->channel_id && ($existing = Channel::find($conversation->channel_id))) {
            return $existing;
        }

        $externalId = $conversation->topic_id
            ? "{$conversation->chat_id}:{$conversation->topic_id}"
            : $conversation->chat_id;

        $channel = Channel::firstOrCreate(
            [
                'external_provider' => 'telegram',
                'external_id' => $externalId,
                'workspace_id' => $conversation->workspace_id,
            ],
            [
                'id' => Str::uuid()->toString(),
                'name' => $conversation->title ?: 'Telegram: '.$conversation->chat_id,
                'type' => 'external',
                'workspace_id' => $conversation->workspace_id,
                'external_config' => [
                    'adapter' => 'telegram',
                    'thread_id' => $conversation->topic_id
                        ? "telegram:{$conversation->chat_id}:{$conversation->topic_id}"
                        : "telegram:{$conversation->chat_id}",
                    'chat_id' => $conversation->chat_id,
                    'topic_id' => $conversation->topic_id,
                    'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
                    'chat_type' => $conversation->chat_type,
                ],
            ],
        );

        return $channel;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function isBotCommand(array $message): bool
    {
        $text = (string) ($message['text'] ?? '');
        if ($text === '') {
            return false;
        }

        foreach (($message['entities'] ?? []) as $entity) {
            if (($entity['type'] ?? '') === 'bot_command' && (int) ($entity['offset'] ?? -1) === 0) {
                return true;
            }
        }

        return str_starts_with($text, '/');
    }

    /**
     * Telegram groups can contain several bots. A command explicitly addressed
     * to another bot, such as /status@OtherBot, must not be handled by
     * OpenCompany. Unqualified commands are still accepted because Telegram may
     * deliver them to the current bot depending on privacy/admin settings.
     *
     * @param  array<string, mixed>  $message
     */
    private function shouldHandleCommand(array $message, IntegrationSetting $setting, TelegramConversation $conversation): bool
    {
        if ($conversation->chat_type === 'private') {
            return true;
        }

        $text = trim((string) ($message['text'] ?? ''));
        [$command] = array_pad(explode(' ', $text, 2), 1, '');
        if (! str_contains($command, '@')) {
            return true;
        }

        $botUsername = (string) $setting->getConfigValue('bot_username', '');
        if ($botUsername === '') {
            return false;
        }

        [, $target] = explode('@', $command, 2);

        return strtolower($target) === strtolower($botUsername);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleCommand(
        array $message,
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation
    ): void {
        $text = trim((string) ($message['text'] ?? ''));
        [$command, $argument] = array_pad(explode(' ', $text, 2), 2, '');

        if (str_contains($command, '@')) {
            $command = substr($command, 0, strpos($command, '@'));
        }

        if ($this->isDirectResourceCommand($command) && ! $this->directResourceCommandsEnabled()) {
            $this->sendRemovedCommandNotice($setting, $workspace, $conversation, $command);

            return;
        }

        match ($command) {
            '/start' => $this->handleStartCommand($setting, $workspace, $conversation, $message),
            '/link' => $this->sendText($setting, $conversation, $this->linkText($message, $setting, $workspace, $conversation), rendererVersion: TelegramCardRenderer::LINK_VERSION),
            '/status' => $this->sendText($setting, $conversation, $this->statusText($workspace, $conversation), rendererVersion: TelegramCardRenderer::STATUS_VERSION),
            '/health' => $this->sendText($setting, $conversation, $this->healthText($workspace, $setting), rendererVersion: TelegramCardRenderer::HEALTH_VERSION),
            '/compact' => $this->handleCompactCommand($setting, $workspace, $conversation),
            '/agents' => $this->sendText(
                $setting,
                $conversation,
                $this->agentsText($workspace),
                $this->agentsReplyMarkup($setting, $workspace),
                TelegramCardRenderer::AGENTS_VERSION,
            ),
            '/tasks' => $this->sendText($setting, $conversation, $this->tasksText($workspace), rendererVersion: TelegramCardRenderer::TASKS_VERSION),
            '/task' => $this->handleTaskCommand($setting, $workspace, $conversation, $message, $argument),
            '/workload' => $this->sendText($setting, $conversation, $this->workloadText($workspace), rendererVersion: TelegramCardRenderer::WORKLOAD_VERSION),
            '/dashboard' => $this->sendText($setting, $conversation, $this->dashboardText($workspace), rendererVersion: TelegramCardRenderer::DASHBOARD_VERSION),
            '/topic' => $this->sendText($setting, $conversation, $this->topicText($workspace, $message, $conversation, $argument), rendererVersion: TelegramCardRenderer::TOPIC_VERSION),
            '/approvals' => $this->sendText($setting, $conversation, $this->approvalsText($workspace), rendererVersion: TelegramCardRenderer::APPROVALS_VERSION),
            '/activity' => $this->sendText($setting, $conversation, $this->activityText($workspace), rendererVersion: TelegramCardRenderer::ACTIVITY_VERSION),
            '/files' => $this->sendText($setting, $conversation, $this->filesText($workspace), rendererVersion: TelegramCardRenderer::FILES_VERSION),
            '/docs' => $this->sendText($setting, $conversation, $this->docsText($workspace), rendererVersion: TelegramCardRenderer::DOCS_VERSION),
            '/lists' => $this->handleListsCommand($setting, $workspace, $conversation, $message, $argument),
            '/tables' => $this->handleTablesCommand($setting, $workspace, $conversation, $message, $argument),
            '/automation' => $this->sendAutomationCommand($setting, $workspace, $conversation, $message, $argument),
            '/calendar' => $this->handleCalendarCommand($setting, $workspace, $conversation, $message, $argument),
            '/digest' => $this->sendText($setting, $conversation, $this->digestText($workspace, $setting, $conversation, $argument), rendererVersion: TelegramCardRenderer::DIGEST_COMMAND_VERSION),
            '/notify' => $this->sendText($setting, $conversation, $this->notifyText($workspace, $setting, $conversation, $argument), rendererVersion: TelegramCardRenderer::NOTIFY_COMMAND_VERSION),
            '/snooze' => $this->sendText($setting, $conversation, $this->snoozeText($workspace, $setting, $conversation, $argument), rendererVersion: TelegramCardRenderer::SNOOZE_COMMAND_VERSION),
            '/cancel' => $this->sendText($setting, $conversation, $this->cancelText($workspace, $conversation), rendererVersion: TelegramCardRenderer::CANCEL_COMMAND_VERSION),
            '/resume' => $this->sendText($setting, $conversation, $this->resumeText($workspace, $conversation), rendererVersion: TelegramCardRenderer::RESUME_COMMAND_VERSION),
            '/settings' => $this->sendSettingsCommand($setting, $workspace, $conversation, $message),
            '/help' => $this->sendText($setting, $conversation, $this->helpText(), rendererVersion: TelegramCardRenderer::HELP_VERSION),
            default => $this->sendText($setting, $conversation, "Unknown command: {$command}\n\n".$this->helpText(), rendererVersion: TelegramCardRenderer::HELP_VERSION),
        };
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function startText(array $message): string
    {
        return "Welcome to OpenCompany.\n\n"
            .'Use /status to see workspace state or /agents to choose an agent.';
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleStartCommand(
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        array $message
    ): void {
        $linked = $this->linkedTelegramMessageActor($workspace, $message) !== null;
        $renderer = app(TelegramCardRenderer::class);

        $this->sendText(
            $setting,
            $conversation,
            $renderer->commandCenterText($workspace, $conversation, $linked),
            $renderer->inlineKeyboard($this->commandCenterButtonRows($setting, $workspace, $conversation, $linked)),
            TelegramCardRenderer::COMMAND_CENTER_VERSION,
        );
    }

    /**
     * @return list<list<array{text: string, callback_data?: string, web_app?: array{url: string}}>>
     */
    private function commandCenterButtonRows(IntegrationSetting $setting, Workspace $workspace, TelegramConversation $conversation, bool $linked): array
    {
        $rows = [
            [
                $this->commandButton($setting, $workspace, 'Switch agent', '/agents'),
                $this->commandButton($setting, $workspace, 'Lane', '/topic'),
            ],
            [
                $this->commandButton($setting, $workspace, 'Status', '/status'),
                $this->commandButton($setting, $workspace, 'Approvals', '/approvals'),
            ],
        ];

        if ($conversation->channel_id && $this->laneHasActiveWork($workspace, $conversation)) {
            $rows[] = [
                $this->commandButton($setting, $workspace, 'Stop work', '/cancel'),
                $this->commandButton($setting, $workspace, 'Resume', '/resume'),
            ];
        }

        $miniAppButton = $this->miniAppButton($setting);
        if ($miniAppButton) {
            $rows[] = [$miniAppButton];
        }

        if (! $linked) {
            array_unshift($rows, [
                $this->commandButton($setting, $workspace, 'Link account', '/link'),
            ]);
        }

        return $rows;
    }

    private function laneHasActiveWork(Workspace $workspace, TelegramConversation $conversation): bool
    {
        return Task::where('workspace_id', $workspace->id)
            ->where('channel_id', $conversation->channel_id)
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_ACTIVE, Task::STATUS_PAUSED])
            ->exists();
    }

    /**
     * @return array{text: string, callback_data: string}
     */
    private function commandButton(IntegrationSetting $setting, Workspace $workspace, string $label, string $command): array
    {
        return [
            'text' => $label,
            'callback_data' => $this->commandInteraction($setting, $workspace, $command)->token,
        ];
    }

    /**
     * @return array{text: string, web_app: array{url: string}}|null
     */
    private function miniAppButton(IntegrationSetting $setting): ?array
    {
        $miniAppUrl = $setting->getConfigValue('mini_app_url');
        if (! config('telegram.mini_app_enabled') || ! is_string($miniAppUrl) || ! str_starts_with($miniAppUrl, 'https://')) {
            return null;
        }

        return [
            'text' => (string) $setting->getConfigValue('mini_app_button_text', 'Open app'),
            'web_app' => [
                'url' => $miniAppUrl,
            ],
        ];
    }

    private function commandInteraction(IntegrationSetting $setting, Workspace $workspace, string $command): TelegramInteraction
    {
        return TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'command',
            'payload' => [
                'command' => $command,
            ],
            'allowed_actor_rule' => [
                'type' => 'telegram_lane_command',
            ],
            'expires_at' => now()->addDay(),
        ]));
    }

    private function commandLabel(string $command): string
    {
        return match ($command) {
            '/link' => 'linking',
            '/agents' => 'agents',
            '/tasks' => 'tasks',
            '/approvals' => 'approvals',
            '/dashboard' => 'dashboard',
            '/files' => 'files',
            '/automation' => 'automations',
            '/settings' => 'settings',
            default => 'command',
        };
    }

    /**
     * The reset keeps Telegram as an agent shell by default. The old direct
     * module commands are still implemented for operators who explicitly opt
     * into them, but normal users should ask the current agent to work with
     * files, docs, tables, calendars, automations, and historical workload.
     */
    private function directResourceCommandsEnabled(): bool
    {
        return filter_var(config('telegram.direct_resource_commands_enabled', false), FILTER_VALIDATE_BOOL);
    }

    private function isDirectResourceCommand(string $command): bool
    {
        return in_array($command, [
            '/tasks',
            '/task',
            '/workload',
            '/dashboard',
            '/activity',
            '/files',
            '/docs',
            '/lists',
            '/tables',
            '/automation',
            '/calendar',
            '/digest',
            '/notify',
            '/snooze',
        ], true);
    }

    private function sendRemovedCommandNotice(
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        string $command
    ): void {
        $this->sendText(
            $setting,
            $conversation,
            $this->removedCommandText($setting, $workspace, $conversation, $command),
            rendererVersion: TelegramCardRenderer::NOTICE_VERSION,
        );
    }

    private function removedCommandText(
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        string $command
    ): string {
        $agent = $this->resolveAgent($workspace, $setting, $conversation);
        $agentLabel = $agent?->name ?? 'the active agent';

        return implode("\n", [
            'Command removed',
            '',
            "{$command} is no longer part of the Telegram workflow.",
            "Send a normal message to {$agentLabel} instead.",
        ]);
    }

    private function statusText(Workspace $workspace, TelegramConversation $conversation): string
    {
        return app(TelegramCardRenderer::class)->statusText($workspace, $conversation);
    }

    private function linkText(
        array $message,
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation
    ): string {
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $userId = (string) ($from['id'] ?? 'unknown');

        $linked = $userId !== 'unknown'
            ? UserExternalIdentity::resolveUser('telegram', $userId)
            : null;

        if ($linked && $this->belongsToWorkspace($linked, $workspace)) {
            return app(TelegramCardRenderer::class)->linkedIdentityText($userId, $linked);
        }

        $expiresAt = now()->addMinutes(15);
        $displayName = trim(((string) ($from['first_name'] ?? '')).' '.((string) ($from['last_name'] ?? '')));
        if ($displayName === '') {
            $displayName = (string) ($from['username'] ?? "Telegram {$userId}");
        }

        $interaction = TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'identity_link',
            'payload' => [
                'telegram_user_id' => $userId,
                'telegram_username' => $from['username'] ?? null,
                'display_name' => $displayName,
                'chat_id' => $conversation->chat_id,
                'conversation_id' => $conversation->id,
            ],
            'allowed_actor_rule' => [
                'type' => 'authenticated_workspace_member',
            ],
            'expires_at' => $expiresAt,
        ]));

        $url = URL::temporarySignedRoute('telegram.link.claim', $expiresAt, [
            'token' => $interaction->token,
        ]);

        return app(TelegramCardRenderer::class)->identityLinkText($userId, $url);
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     */
    private function linkedTelegramActor(Workspace $workspace, array $callbackQuery): ?User
    {
        $from = is_array($callbackQuery['from'] ?? null) ? $callbackQuery['from'] : [];
        $externalId = (string) ($from['id'] ?? '');

        if ($externalId === '') {
            return null;
        }

        $linked = UserExternalIdentity::resolveUser('telegram', $externalId);

        return $linked && $this->belongsToWorkspace($linked, $workspace)
            ? $linked
            : null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function linkedTelegramMessageActor(Workspace $workspace, array $message): ?User
    {
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $externalId = (string) ($from['id'] ?? '');

        if ($externalId === '') {
            return null;
        }

        $linked = UserExternalIdentity::resolveUser('telegram', $externalId);

        return $linked && $this->belongsToWorkspace($linked, $workspace)
            ? $linked
            : null;
    }

    private function healthText(Workspace $workspace, IntegrationSetting $setting): string
    {
        return app(TelegramCardRenderer::class)->healthText($workspace, $setting);
    }

    private function agentsText(Workspace $workspace): string
    {
        return app(TelegramCardRenderer::class)->agentsText($workspace);
    }

    /**
     * @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>}
     */
    private function agentsReplyMarkup(IntegrationSetting $setting, Workspace $workspace): array
    {
        $agents = User::where('workspace_id', $workspace->id)
            ->where('type', 'agent')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'workspace_id']);

        if ($agents->isEmpty()) {
            return ['inline_keyboard' => []];
        }

        return [
            'inline_keyboard' => $agents
                ->map(fn (User $agent) => [
                    'text' => Str::limit($agent->name, 24, ''),
                    'callback_data' => $this->agentInteraction($setting, $agent)->token,
                ])
                ->chunk(2)
                ->map(fn ($row) => $row->values()->all())
                ->values()
                ->all(),
        ];
    }

    private function agentInteraction(IntegrationSetting $setting, User $agent): TelegramInteraction
    {
        $attributes = [
            'id' => Str::uuid()->toString(),
            'workspace_id' => $agent->workspace_id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'agent',
            'payload' => [
                'action' => 'select_default_agent',
                'agent_id' => $agent->id,
            ],
            'allowed_actor_rule' => [
                'type' => 'linked_workspace_member',
            ],
            'expires_at' => now()->addHours(6),
        ];

        if (Str::isUuid((string) $agent->id)) {
            $attributes['target_type'] = User::class;
            $attributes['target_id'] = $agent->id;
        }

        return TelegramInteraction::create(TelegramInteraction::withPayloadChecksum($attributes));
    }

    private function tasksText(Workspace $workspace): string
    {
        return app(TelegramCardRenderer::class)->tasksText($workspace);
    }

    private function taskText(Workspace $workspace, string $argument): string
    {
        $id = trim($argument);
        if ($id === '') {
            return 'Usage: /task <id>';
        }

        $task = $this->findTaskForTelegram($workspace, $id);

        if (! $task) {
            return "Task not found: {$id}";
        }

        return app(TelegramCardRenderer::class)->taskCardText($task);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleTaskCommand(
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        array $message,
        string $argument
    ): void {
        $id = trim($argument);
        if ($id === '') {
            $this->sendText($setting, $conversation, 'Usage: /task <id>');

            return;
        }

        $task = $this->findTaskForTelegram($workspace, $id);

        if (! $task) {
            $this->sendText($setting, $conversation, "Task not found: {$id}");

            return;
        }

        $this->sendTaskCard(
            $setting,
            $conversation,
            $task,
            $this->linkedTelegramMessageActor($workspace, $message),
            TelegramCardRenderer::TASK_CARD_VERSION,
            $this->taskText($workspace, $argument),
        );
    }

    private function findTaskForTelegram(Workspace $workspace, string $id): ?Task
    {
        $query = Task::where('workspace_id', $workspace->id)
            ->with(['agent', 'steps', 'workspace']);

        // PostgreSQL UUID columns cannot be compared to arbitrary short
        // prefixes directly. Treat full UUIDs as exact matches and short IDs as
        // explicit text-prefix lookups so Telegram's compact task references
        // remain ergonomic without throwing database errors.
        if (Str::isUuid($id)) {
            return $query->where('id', $id)->first();
        }

        return $query->whereRaw('CAST(id AS TEXT) LIKE ?', [$id.'%'])->first();
    }

    private function sendTaskCard(
        IntegrationSetting $setting,
        TelegramConversation $conversation,
        Task $task,
        ?User $actor,
        string $rendererVersion,
        ?string $fallbackText = null
    ): void {
        $replyMarkup = $this->taskReplyMarkup($setting, $task, $actor !== null);
        $text = $this->telegramCardHtml(app(TelegramCardRenderer::class)->taskCardText($task));
        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $task->workspace_id,
            'integration_setting_id' => $setting->id,
            'source_type' => Task::class,
            'source_id' => $task->id,
            'chat_id' => $conversation->chat_id,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'parse_mode' => 'HTML',
            'renderer_version' => $rendererVersion,
            'status' => 'pending',
            'request_payload' => [
                'method' => 'sendMessage',
                'text' => $text,
                'reply_markup' => $replyMarkup,
                'task_id' => $task->id,
                'buttons' => $actor ? $this->taskButtonLabels($task) : [],
            ],
        ]);

        try {
            $result = app(TelegramService::class)->sendMessage(
                $conversation->chat_id,
                $text,
                $replyMarkup,
                messageThreadId: TelegramService::messageThreadIdForTopic($conversation->topic_id),
                directMessagesTopicId: TelegramService::directMessagesTopicId($conversation->direct_messages_topic_id),
            );

            $delivery->update([
                'status' => 'sent',
                'telegram_message_id' => isset($result['message_id']) ? (string) $result['message_id'] : null,
                'parse_mode' => ($result['_opencompany_parse_mode_fallback'] ?? false) ? 'plain_text_fallback' : 'HTML',
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markDeliveryFailed($delivery, $e);

            if ($fallbackText !== null) {
                $this->sendText($setting, $conversation, $fallbackText);
            }
        }
    }

    private function sendDraftPreview(IntegrationSetting $setting, TelegramConversation $conversation, Task $task): void
    {
        if (! config('telegram.draft_streaming_enabled', false) || $conversation->chat_type !== 'private') {
            return;
        }

        $draftId = $this->draftIdForTask($task);
        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $task->workspace_id,
            'integration_setting_id' => $setting->id,
            'source_type' => Task::class,
            'source_id' => $task->id,
            'chat_id' => $conversation->chat_id,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'parse_mode' => 'HTML',
            'renderer_version' => 'telegram-draft-stream:v1',
            'status' => 'pending',
            'request_payload' => [
                'method' => 'sendMessageDraft',
                'draft_id' => $draftId,
                'text' => '',
                'task_id' => $task->id,
            ],
        ]);

        try {
            $result = app(TelegramService::class)->sendMessageDraft(
                $conversation->chat_id,
                $draftId,
                messageThreadId: TelegramService::messageThreadIdForTopic($conversation->topic_id),
            );

            $delivery->update([
                'status' => 'sent',
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markDeliveryFailed($delivery, $e);
        }
    }

    private function draftIdForTask(Task $task): int
    {
        $draftId = (int) sprintf('%u', crc32($task->id));

        return max(1, $draftId);
    }

    /**
     * @return array<string, mixed>
     */
    private function taskReplyMarkup(IntegrationSetting $setting, Task $task, bool $allowStateActions = true): array
    {
        $stateButtons = [];
        if ($allowStateActions && in_array($task->status, [Task::STATUS_PENDING, Task::STATUS_ACTIVE], true)) {
            $stateButtons[] = ['text' => 'Pause', 'callback_data' => $this->taskInteraction($setting, $task, 'pause')->token];
        }
        if ($allowStateActions && $task->status === Task::STATUS_PAUSED) {
            $stateButtons[] = ['text' => 'Resume', 'callback_data' => $this->taskInteraction($setting, $task, 'resume')->token];
        }
        if ($allowStateActions && ! in_array($task->status, [Task::STATUS_COMPLETED, Task::STATUS_FAILED, Task::STATUS_CANCELLED], true)) {
            $stateButtons[] = ['text' => 'Cancel', 'callback_data' => $this->taskInteraction($setting, $task, 'cancel')->token];
        }

        $rows = [];
        if ($stateButtons !== []) {
            $rows[] = $stateButtons;
        }

        if ($url = $this->taskWebUrl($task)) {
            $rows[] = [[
                'text' => 'Open',
                'url' => $url,
            ]];
        }

        return [
            'inline_keyboard' => $rows,
        ];
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
        $workspace = $task->workspace;
        if (! $workspace) {
            return null;
        }

        return rtrim((string) config('app.url'), '/')."/w/{$workspace->slug}/tasks/{$task->id}";
    }

    private function taskInteraction(IntegrationSetting $setting, Task $task, string $action): TelegramInteraction
    {
        $existing = TelegramInteraction::where('workspace_id', $task->workspace_id)
            ->where('integration_setting_id', $setting->id)
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
            'integration_setting_id' => $setting->id,
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

    private function workloadText(Workspace $workspace): string
    {
        return app(TelegramCardRenderer::class)->workloadText($workspace);
    }

    private function dashboardText(Workspace $workspace): string
    {
        return app(TelegramCardRenderer::class)->dashboardText($workspace);
    }

    private function topicText(
        Workspace $workspace,
        array $message,
        TelegramConversation $conversation,
        string $argument
    ): string {
        $parts = preg_split('/\s+/', trim($argument)) ?: [];
        $action = strtolower($parts[0] ?? 'status');

        if ($action === '' || $action === 'status') {
            return $this->topicStatusText($conversation);
        }

        if (! $this->linkedTelegramMessageActor($workspace, $message)) {
            return app(TelegramCardRenderer::class)->topicLinkRequiredText();
        }

        return match ($action) {
            'mention' => $this->updateTopicMode($conversation, 'command_center', false, null, app(TelegramCardRenderer::class)->topicModeUpdatedText('mention')),
            'free' => $this->updateTopicMode($conversation, 'free_response', false, null, app(TelegramCardRenderer::class)->topicModeUpdatedText('free')),
            'observe' => config('telegram.group_observer_enabled', true)
                ? $this->updateTopicMode($conversation, 'observed', true, null, app(TelegramCardRenderer::class)->topicModeUpdatedText('observe'))
                : app(TelegramCardRenderer::class)->topicModeUpdatedText('observe_disabled'),
            'ignore' => $this->updateTopicMode($conversation, 'ignored', false, now(), app(TelegramCardRenderer::class)->topicModeUpdatedText('ignore')),
            'archive' => $this->updateTopicMode($conversation, $conversation->mode, $conversation->observed_context_enabled, now(), app(TelegramCardRenderer::class)->topicModeUpdatedText('archive')),
            'agent' => $this->topicAgentText($workspace, $conversation, trim(implode(' ', array_slice($parts, 1)))),
            default => app(TelegramCardRenderer::class)->topicUnknownActionText($action),
        };
    }

    private function topicStatusText(TelegramConversation $conversation): string
    {
        return app(TelegramCardRenderer::class)->topicStatusText($conversation);
    }

    private function topicHelpText(): string
    {
        return app(TelegramCardRenderer::class)->topicHelpText();
    }

    private function updateTopicMode(
        TelegramConversation $conversation,
        string $mode,
        bool $observedContext,
        mixed $archivedAt,
        string $response
    ): string {
        $conversation->update([
            'mode' => $mode,
            'observed_context_enabled' => $observedContext,
            'archived_at' => $archivedAt,
        ]);

        return $response;
    }

    private function topicAgentText(Workspace $workspace, TelegramConversation $conversation, string $target): string
    {
        if ($target === '') {
            return app(TelegramCardRenderer::class)->topicAgentUsageText();
        }

        $agent = $this->whereTelegramTarget(
            User::where('workspace_id', $workspace->id)
                ->where('type', 'agent'),
            $target
        )
            ->first();

        if (! $agent) {
            return app(TelegramCardRenderer::class)->topicAgentNotFoundText($target);
        }

        $conversation->update([
            'default_agent_id' => $agent->id,
            'archived_at' => null,
        ]);

        return app(TelegramCardRenderer::class)->topicAgentUpdatedText($agent);
    }

    /**
     * Apply Telegram's ergonomic target matching without unsafe UUID casts.
     *
     * Command arguments often contain short IDs or names typed on a phone.
     * PostgreSQL UUID columns reject arbitrary text comparisons, so exact UUID
     * equality is only used for full UUIDs and all prefix matching is done
     * through an explicit text cast.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function whereTelegramTarget(Builder $query, string $target, string $nameColumn = 'name'): Builder
    {
        return $query->where(function (Builder $candidate) use ($target, $nameColumn): void {
            if (Str::isUuid($target)) {
                $candidate->where('id', $target);
            } else {
                $candidate->whereRaw('CAST(id AS TEXT) LIKE ?', [$target.'%']);
            }

            $candidate->orWhere($nameColumn, 'like', '%'.$target.'%');
        });
    }

    private function approvalsText(Workspace $workspace): string
    {
        return app(TelegramCardRenderer::class)->approvalsText($workspace);
    }

    private function activityText(Workspace $workspace): string
    {
        return app(TelegramCardRenderer::class)->activityText($workspace);
    }

    private function filesText(Workspace $workspace): string
    {
        return app(TelegramCardRenderer::class)->filesText($workspace);
    }

    private function docsText(Workspace $workspace): string
    {
        return app(TelegramCardRenderer::class)->docsText($workspace);
    }

    private function listsText(Workspace $workspace): string
    {
        return app(TelegramCardRenderer::class)->listsText($workspace);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleListsCommand(
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        array $message,
        string $argument
    ): void {
        $argument = trim($argument);
        [$action, $title] = array_pad(preg_split('/\s+/', $argument, 2) ?: [], 2, '');
        $action = strtolower($action);

        if (! in_array($action, ['add', 'create'], true)) {
            $this->sendText($setting, $conversation, $this->listsText($workspace), rendererVersion: TelegramCardRenderer::LISTS_VERSION);

            return;
        }

        $title = trim($title);
        if ($title === '') {
            $this->sendText($setting, $conversation, 'Usage: /lists add <item title>');

            return;
        }

        if (! $this->linkedTelegramMessageActor($workspace, $message)) {
            $this->sendText($setting, $conversation, 'Link your Telegram account before creating list items.');

            return;
        }

        $project = ListItem::where('workspace_id', $workspace->id)
            ->where('is_folder', true)
            ->orderBy('position')
            ->orderBy('created_at')
            ->first();
        $draft = [
            'title' => Str::limit($title, 180, ''),
            'description' => 'Created from Telegram.',
            'status' => 'backlog',
            'priority' => 'medium',
            'project_id' => $project?->id,
            'project_title' => $project?->title ?? 'Telegram Inbox',
        ];

        $create = $this->listDraftInteraction($setting, $workspace, 'create', $draft);
        $cancel = $this->listDraftInteraction($setting, $workspace, 'cancel', $draft);

        $this->sendText(
            $setting,
            $conversation,
            app(TelegramCardRenderer::class)->listDraftText($draft),
            [
                'inline_keyboard' => [
                    [
                        ['text' => 'Create', 'callback_data' => $create->token],
                        ['text' => 'Cancel', 'callback_data' => $cancel->token],
                    ],
                ],
            ],
            TelegramCardRenderer::LIST_DRAFT_VERSION,
        );
    }

    /**
     * @param  array{title: string, description: string, status: string, priority: string, project_id: ?string, project_title: string}  $draft
     */
    private function listDraftInteraction(IntegrationSetting $setting, Workspace $workspace, string $action, array $draft): TelegramInteraction
    {
        return TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'list_draft',
            'target_type' => ListItem::class,
            'payload' => [
                'action' => $action,
                'draft' => $draft,
            ],
            'allowed_actor_rule' => [
                'type' => 'linked_workspace_member',
            ],
            'expires_at' => now()->addMinutes(30),
        ]));
    }

    private function resolveListDraftProject(Workspace $workspace, User $actor, string $projectId): ListItem
    {
        if ($projectId !== '') {
            $project = ListItem::where('workspace_id', $workspace->id)
                ->where('is_folder', true)
                ->where('id', $projectId)
                ->first();
            if ($project) {
                return $project;
            }
        }

        return ListItem::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'title' => 'Telegram Inbox',
                'is_folder' => true,
            ],
            [
                'id' => Str::uuid()->toString(),
                'description' => 'List items captured from Telegram.',
                'status' => 'backlog',
                'priority' => 'medium',
                'creator_id' => $actor->id,
                'position' => ((int) ListItem::where('workspace_id', $workspace->id)->where('is_folder', true)->max('position')) + 1,
            ],
        );
    }

    private function tablesText(Workspace $workspace): string
    {
        return app(TelegramCardRenderer::class)->tablesText($workspace);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleTablesCommand(
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        array $message,
        string $argument
    ): void {
        $argument = trim($argument);
        [$action, $rest] = array_pad(preg_split('/\s+/', $argument, 2) ?: [], 2, '');
        $action = strtolower($action);

        if (! in_array($action, ['add', 'create'], true)) {
            $this->sendText($setting, $conversation, $this->tablesText($workspace), rendererVersion: TelegramCardRenderer::TABLES_VERSION);

            return;
        }

        if (! $this->linkedTelegramMessageActor($workspace, $message)) {
            $this->sendText($setting, $conversation, 'Link your Telegram account before creating table rows.');

            return;
        }

        $target = $this->resolveTableDraftTarget($workspace, trim($rest));
        if (! $target) {
            if ($this->sendTableClarifyCard($setting, $workspace, $conversation, trim($rest))) {
                return;
            }

            $this->sendText($setting, $conversation, 'Usage: /tables add <table> <field>=<value>');

            return;
        }

        [$table, $rowInput] = $target;
        $data = $this->parseTableDraftData($table, $rowInput);
        if ($data === []) {
            $this->sendText($setting, $conversation, 'Could not parse row data. Use /tables add <table> <field>=<value>.');

            return;
        }

        $draft = [
            'table_id' => $table->id,
            'table_name' => $table->name,
            'data' => $data,
        ];
        $create = $this->tableRowDraftInteraction($setting, $workspace, 'create', $draft);
        $cancel = $this->tableRowDraftInteraction($setting, $workspace, 'cancel', $draft);

        $this->sendText(
            $setting,
            $conversation,
            app(TelegramCardRenderer::class)->tableRowDraftText($draft),
            [
                'inline_keyboard' => [
                    [
                        ['text' => 'Create', 'callback_data' => $create->token],
                        ['text' => 'Cancel', 'callback_data' => $cancel->token],
                    ],
                ],
            ],
            TelegramCardRenderer::TABLE_ROW_DRAFT_VERSION,
        );
    }

    private function sendTableClarifyCard(
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        string $rowInput
    ): bool {
        if ($rowInput === '') {
            return false;
        }

        $tables = DataTable::where('workspace_id', $workspace->id)
            ->withCount('columns')
            ->orderBy('name')
            ->limit(6)
            ->get();

        if ($tables->count() < 2) {
            return false;
        }

        $buttons = [];
        foreach ($tables->chunk(2) as $chunk) {
            $row = [];
            foreach ($chunk as $table) {
                $row[] = [
                    'text' => Str::limit($table->name, 32, ''),
                    'callback_data' => $this->tableClarifyInteraction($setting, $workspace, $table, $rowInput, 'select')->token,
                ];
            }
            $buttons[] = $row;
        }
        $buttons[] = [[
            'text' => 'Cancel',
            'callback_data' => $this->tableClarifyCancelInteraction($setting, $workspace, $rowInput)->token,
        ]];

        $this->sendText(
            $setting,
            $conversation,
            app(TelegramCardRenderer::class)->tableClarifyText($rowInput),
            ['inline_keyboard' => $buttons],
            TelegramCardRenderer::TABLE_CLARIFY_VERSION,
        );

        return true;
    }

    /**
     * @return array{0: DataTable, 1: string}|null
     */
    private function resolveTableDraftTarget(Workspace $workspace, string $input): ?array
    {
        $tables = DataTable::where('workspace_id', $workspace->id)
            ->with('columns')
            ->get()
            ->sortByDesc(fn (DataTable $table): int => strlen($table->name));

        foreach ($tables as $table) {
            foreach ([$table->name, $table->id] as $needle) {
                $needle = (string) $needle;
                if ($needle !== '' && str_starts_with(strtolower($input), strtolower($needle))) {
                    return [$table, trim(substr($input, strlen($needle)))];
                }
            }
        }

        if ($tables->count() === 1) {
            return [$tables->first(), $input];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTableDraftData(DataTable $table, string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return [];
        }

        $data = [];
        if (str_contains($input, '=')) {
            foreach (preg_split('/\s*;\s*/', $input) ?: [] as $pair) {
                if (! str_contains($pair, '=')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $pair, 2));
                if ($key !== '') {
                    $data[$key] = trim($value, " \t\n\r\0\x0B\"'");
                }
            }

            return $data;
        }

        $firstColumn = $table->columns->first();

        return $firstColumn ? [$firstColumn->name => $input] : [];
    }

    /**
     * @param  array{table_id: string, table_name: string, data: array<string, mixed>}  $draft
     */
    private function tableRowDraftInteraction(IntegrationSetting $setting, Workspace $workspace, string $action, array $draft): TelegramInteraction
    {
        return TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'table_row_draft',
            'target_type' => DataTable::class,
            'target_id' => $draft['table_id'],
            'payload' => [
                'action' => $action,
                'draft' => $draft,
            ],
            'allowed_actor_rule' => [
                'type' => 'linked_workspace_member',
            ],
            'expires_at' => now()->addMinutes(30),
        ]));
    }

    private function tableClarifyInteraction(
        IntegrationSetting $setting,
        Workspace $workspace,
        DataTable $table,
        string $rowInput,
        string $action
    ): TelegramInteraction {
        $payload = [
            'action' => $action,
            'table_id' => $table->id,
            'table_name' => $table->name,
            'row_input' => $rowInput,
        ];

        return TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'table_clarify',
            'target_type' => DataTable::class,
            'target_id' => $table->id,
            'payload' => $payload,
            'allowed_actor_rule' => [
                'type' => 'linked_workspace_member',
            ],
            'expires_at' => now()->addMinutes(15),
        ]));
    }

    private function tableClarifyCancelInteraction(IntegrationSetting $setting, Workspace $workspace, string $rowInput): TelegramInteraction
    {
        $payload = [
            'action' => 'cancel',
            'row_input' => $rowInput,
        ];

        return TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'table_clarify',
            'payload' => $payload,
            'allowed_actor_rule' => [
                'type' => 'linked_workspace_member',
            ],
            'expires_at' => now()->addMinutes(15),
        ]));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function sendAutomationCommand(
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        array $message,
        string $argument
    ): void {
        $card = $this->automationCard($workspace, $message, $argument);

        $this->sendText(
            $setting,
            $conversation,
            $card['text'],
            rendererVersion: $card['renderer_version'],
        );
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{text: string, renderer_version: string}
     */
    private function automationCard(Workspace $workspace, array $message, string $argument): array
    {
        $argument = trim($argument);
        [$action, $target] = array_pad(preg_split('/\s+/', $argument, 2) ?: [], 2, '');
        $action = strtolower($action ?: 'list');
        if ($argument !== '' && ! in_array($action, ['list', 'status', 'run', 'pause', 'resume', 'history', 'failures'], true)) {
            $action = 'status';
            $target = $argument;
        }
        $query = Automation::where('workspace_id', $workspace->id)->with(['agent', 'workspace']);

        if (in_array($action, ['status', 'run', 'pause', 'resume', 'history'], true)) {
            $automation = $this->whereTelegramTarget(clone $query, $target)->first();

            if (! $automation) {
                return [
                    'text' => "Automation not found: {$target}",
                    'renderer_version' => TelegramCardRenderer::AUTOMATIONS_VERSION,
                ];
            }

            if (in_array($action, ['run', 'pause', 'resume'], true) && ! $this->linkedTelegramMessageActor($workspace, $message)) {
                return [
                    'text' => 'Link your Telegram account before changing automations.',
                    'renderer_version' => TelegramCardRenderer::AUTOMATION_ACTION_VERSION,
                ];
            }

            if ($action === 'run') {
                RunAutomationJob::dispatch($automation);

                return [
                    'text' => "Automation run queued: {$automation->name}",
                    'renderer_version' => TelegramCardRenderer::AUTOMATION_ACTION_VERSION,
                ];
            }

            if ($action === 'pause') {
                $automation->update(['is_active' => false]);

                return [
                    'text' => "Automation paused: {$automation->name}",
                    'renderer_version' => TelegramCardRenderer::AUTOMATION_ACTION_VERSION,
                ];
            }

            if ($action === 'resume') {
                $automation->update([
                    'is_active' => true,
                    'consecutive_failures' => 0,
                    'next_run_at' => $automation->computeNextRunAt(),
                ]);

                return [
                    'text' => "Automation resumed: {$automation->name}",
                    'renderer_version' => TelegramCardRenderer::AUTOMATION_ACTION_VERSION,
                ];
            }

            if ($action === 'history') {
                return [
                    'text' => app(TelegramCardRenderer::class)->automationHistoryText($workspace, $automation),
                    'renderer_version' => TelegramCardRenderer::AUTOMATION_HISTORY_VERSION,
                ];
            }

            return [
                'text' => app(TelegramCardRenderer::class)->automationStatusText($automation),
                'renderer_version' => TelegramCardRenderer::AUTOMATION_STATUS_VERSION,
            ];
        }

        if ($action === 'failures') {
            return [
                'text' => app(TelegramCardRenderer::class)->automationFailuresText($workspace),
                'renderer_version' => TelegramCardRenderer::AUTOMATION_FAILURES_VERSION,
            ];
        }

        return [
            'text' => app(TelegramCardRenderer::class)->automationsText($workspace),
            'renderer_version' => TelegramCardRenderer::AUTOMATIONS_VERSION,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleCalendarCommand(
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        array $message,
        string $argument
    ): void {
        $argument = trim($argument);
        $action = strtolower((preg_split('/\s+/', $argument, 2) ?: [''])[0] ?? '');
        if (in_array($action, ['add', 'create'], true)) {
            if (! $this->linkedTelegramMessageActor($workspace, $message)) {
                $this->sendText($setting, $conversation, 'Link your Telegram account before creating calendar events.');

                return;
            }

            $draft = $this->parseCalendarDraft($argument);
            if (! is_array($draft)) {
                $this->sendText($setting, $conversation, $draft);

                return;
            }

            $create = $this->calendarDraftInteraction($setting, $workspace, 'create', $draft);
            $cancel = $this->calendarDraftInteraction($setting, $workspace, 'cancel', $draft);

            $this->sendText(
                $setting,
                $conversation,
                app(TelegramCardRenderer::class)->calendarDraftText($draft),
                [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Create event', 'callback_data' => $create->token],
                            ['text' => 'Cancel', 'callback_data' => $cancel->token],
                        ],
                    ],
                ],
                TelegramCardRenderer::CALENDAR_DRAFT_VERSION,
            );

            return;
        }

        $this->sendText(
            $setting,
            $conversation,
            app(TelegramCardRenderer::class)->calendarText($workspace, $argument),
            rendererVersion: TelegramCardRenderer::CALENDAR_VERSION,
        );
    }

    /**
     * @return array{title: string, start_at: string, end_at: string, timezone: string}|string
     */
    private function parseCalendarDraft(string $argument): array|string
    {
        if (! preg_match('/^(?:add|create)\s+(\d{4}-\d{2}-\d{2})\s+((?:[01]\d|2[0-3]):[0-5]\d)\s+(.+)$/i', trim($argument), $matches)) {
            return 'Usage: /calendar add YYYY-MM-DD HH:MM Event title';
        }

        try {
            $timezone = (string) config('app.timezone', 'UTC');
            $start = Carbon::createFromFormat('Y-m-d H:i', "{$matches[1]} {$matches[2]}", $timezone);
            if (! $start) {
                return 'Usage: /calendar add YYYY-MM-DD HH:MM Event title';
            }
        } catch (\Throwable) {
            return 'Usage: /calendar add YYYY-MM-DD HH:MM Event title';
        }

        return [
            'title' => trim($matches[3]),
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->copy()->addHour()->toIso8601String(),
            'timezone' => $timezone,
        ];
    }

    /**
     * @param  array{title: string, start_at: string, end_at: string, timezone: string}  $draft
     */
    private function calendarDraftInteraction(IntegrationSetting $setting, Workspace $workspace, string $action, array $draft): TelegramInteraction
    {
        return TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'calendar_draft',
            'target_type' => CalendarEvent::class,
            'payload' => [
                'action' => $action,
                'event' => $draft,
            ],
            'allowed_actor_rule' => [
                'type' => 'linked_workspace_member',
            ],
            'expires_at' => now()->addMinutes(30),
        ]));
    }

    private function digestText(Workspace $workspace, IntegrationSetting $setting, TelegramConversation $conversation, string $argument): string
    {
        $subscription = TelegramSubscription::firstOrNew([
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'scope_type' => 'telegram_conversation',
            'scope_id' => $conversation->id,
            'event_type' => 'workspace_digest',
            'severity' => 'normal',
        ]);

        $parts = preg_split('/\s+/', trim($argument)) ?: [];
        $mode = strtolower($parts[0] ?? '') ?: 'daily';
        $filters = $subscription->filters ?? [];

        if ($mode === 'time') {
            $time = $parts[1] ?? '';
            if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
                return app(TelegramCardRenderer::class)->digestUsageText();
            }

            $filters['time'] = $time;
            $subscription->fill([
                'id' => $subscription->id ?: Str::uuid()->toString(),
                'filters' => $filters,
                'schedule' => $subscription->schedule ?: 'daily',
                'timezone' => (string) config('app.timezone', 'UTC'),
                'enabled' => true,
            ])->save();

            return app(TelegramCardRenderer::class)->digestTimeText($time);
        }

        if (! in_array($mode, ['daily', 'weekly', 'off'], true)) {
            return app(TelegramCardRenderer::class)->digestUsageText();
        }

        $subscription->fill([
            'id' => $subscription->id ?: Str::uuid()->toString(),
            'filters' => $filters,
            'schedule' => $mode === 'off' ? null : $mode,
            'timezone' => (string) config('app.timezone', 'UTC'),
            'enabled' => $mode !== 'off',
        ])->save();

        return app(TelegramCardRenderer::class)->digestModeText($mode);
    }

    private function snoozeText(Workspace $workspace, IntegrationSetting $setting, TelegramConversation $conversation, string $argument): string
    {
        $value = strtolower(trim($argument));
        if ($value === '') {
            return app(TelegramCardRenderer::class)->snoozeUsageText();
        }

        $subscription = TelegramSubscription::firstOrNew([
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'scope_type' => 'telegram_conversation',
            'scope_id' => $conversation->id,
            'event_type' => 'all_notifications',
            'severity' => 'normal',
        ]);

        if (in_array($value, ['off', 'clear', 'resume'], true)) {
            $subscription->fill([
                'id' => $subscription->id ?: Str::uuid()->toString(),
                'filters' => ['snoozed_until' => null],
                'timezone' => (string) config('app.timezone', 'UTC'),
                'enabled' => false,
            ])->save();

            return app(TelegramCardRenderer::class)->snoozeClearedText();
        }

        $snoozedUntil = $this->parseSnoozeUntil($value);
        if (! $snoozedUntil) {
            return app(TelegramCardRenderer::class)->snoozeUsageText();
        }

        $subscription->fill([
            'id' => $subscription->id ?: Str::uuid()->toString(),
            'filters' => ['snoozed_until' => $snoozedUntil->toIso8601String()],
            'timezone' => (string) config('app.timezone', 'UTC'),
            'enabled' => true,
        ])->save();

        return app(TelegramCardRenderer::class)->snoozedText($snoozedUntil);
    }

    private function parseSnoozeUntil(string $value): ?Carbon
    {
        $now = Carbon::now((string) config('app.timezone', 'UTC'));

        if ($value === 'tomorrow') {
            return $now->copy()->addDay()->startOfDay()->setTime(9, 0);
        }

        if (! preg_match('/^(\d+)(m|h|d)$/', $value, $matches)) {
            return null;
        }

        $amount = (int) $matches[1];
        if ($amount < 1) {
            return null;
        }

        return match ($matches[2]) {
            'm' => $now->copy()->addMinutes($amount),
            'h' => $now->copy()->addHours($amount),
            'd' => $now->copy()->addDays($amount),
            default => null,
        };
    }

    private function notifyText(Workspace $workspace, IntegrationSetting $setting, TelegramConversation $conversation, string $argument): string
    {
        $parts = preg_split('/\s+/', trim($argument)) ?: [];
        $eventType = $parts[0] ?? 'task_failed';
        $severity = $parts[1] ?? 'normal';
        $mode = strtolower($parts[2] ?? 'immediate');

        if (! in_array($mode, ['immediate', 'silent', 'digest', 'digest-only', 'batched', 'off'], true)) {
            return app(TelegramCardRenderer::class)->notifyUsageText();
        }

        $subscription = TelegramSubscription::firstOrNew([
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'scope_type' => 'telegram_conversation',
            'scope_id' => $conversation->id,
            'event_type' => $eventType,
            'severity' => $severity,
        ]);
        $filters = $subscription->filters ?? [];
        $filters['mode'] = $mode === 'off' ? 'off' : $mode;

        $subscription->fill([
            'id' => $subscription->id ?: Str::uuid()->toString(),
            'filters' => $filters,
            'timezone' => (string) config('app.timezone', 'UTC'),
            'enabled' => $mode !== 'off',
        ])->save();

        return app(TelegramCardRenderer::class)->notifyModeText($eventType, $severity, $mode);
    }

    private function cancelText(Workspace $workspace, TelegramConversation $conversation): string
    {
        if (! $conversation->channel_id) {
            return app(TelegramCardRenderer::class)->cancelText(0, false);
        }

        $tasks = Task::where('workspace_id', $workspace->id)
            ->where('channel_id', $conversation->channel_id)
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_ACTIVE, Task::STATUS_PAUSED])
            ->get();

        foreach ($tasks as $task) {
            $task->cancel();
            event(new TaskUpdated($task->fresh(['agent', 'steps', 'workspace']) ?? $task, 'cancelled'));
        }

        return app(TelegramCardRenderer::class)->cancelText($tasks->count(), true);
    }

    private function resumeText(Workspace $workspace, TelegramConversation $conversation): string
    {
        if (! $conversation->channel_id) {
            return app(TelegramCardRenderer::class)->resumeText(0, false);
        }

        $count = Task::where('workspace_id', $workspace->id)
            ->where('channel_id', $conversation->channel_id)
            ->where('status', Task::STATUS_PAUSED)
            ->update(['status' => Task::STATUS_ACTIVE]);

        return app(TelegramCardRenderer::class)->resumeText($count, true);
    }

    private function settingsText(IntegrationSetting $setting, TelegramConversation $conversation): string
    {
        return app(TelegramCardRenderer::class)->settingsText($setting, $conversation);
    }

    /**
     * Keep operational Telegram settings behind the same workspace admin
     * boundary as the web app. Normal users get redirected back to the
     * agent-first chat loop instead of seeing internal lane/config details.
     *
     * @param  array<string, mixed>  $message
     */
    private function sendSettingsCommand(
        IntegrationSetting $setting,
        Workspace $workspace,
        TelegramConversation $conversation,
        array $message
    ): void {
        $actor = $this->linkedTelegramMessageActor($workspace, $message);
        if (! $actor?->isWorkspaceAdmin($workspace)) {
            $this->sendText(
                $setting,
                $conversation,
                "Settings live in OpenCompany.\n\nUse /start for Telegram controls or ask the active agent.",
                rendererVersion: TelegramCardRenderer::NOTICE_VERSION,
            );

            return;
        }

        $this->sendText(
            $setting,
            $conversation,
            $this->settingsText($setting, $conversation),
            rendererVersion: TelegramCardRenderer::SETTINGS_VERSION,
        );
    }

    private function helpText(): string
    {
        return app(TelegramCardRenderer::class)->helpText();
    }

    private function handleCompactCommand(IntegrationSetting $setting, Workspace $workspace, TelegramConversation $conversation): void
    {
        if (! $conversation->channel_id) {
            $this->sendText($setting, $conversation, 'No conversation history in this lane yet.');

            return;
        }

        $agent = $this->resolveAgent($workspace, $setting, $conversation);
        if (! $agent) {
            $this->sendText($setting, $conversation, 'No agent found in this lane.');

            return;
        }

        $this->sendText($setting, $conversation, 'Compacting conversation memory...');
        CompactConversationJob::dispatch($conversation->channel_id, $agent, $conversation->chat_id);
    }

    /**
     * Send a Telegram command/card response and keep a delivery record for
     * debugging. Telegram has no historical fetch API for bot messages, so even
     * command-center replies should leave enough local state to inspect failures.
     *
     * @param  array<string, mixed>|null  $replyMarkup
     */
    private function sendText(
        IntegrationSetting $setting,
        TelegramConversation $conversation,
        string $text,
        ?array $replyMarkup = null,
        ?string $rendererVersion = null
    ): void {
        $telegramText = $this->telegramCardHtml($text);
        $rendererVersion ??= TelegramCardRenderer::NOTICE_VERSION;
        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $setting->workspace_id,
            'integration_setting_id' => $setting->id,
            'chat_id' => $conversation->chat_id,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'parse_mode' => 'HTML',
            'renderer_version' => $rendererVersion,
            'status' => 'pending',
            'request_payload' => [
                'method' => 'sendMessage',
                'conversation_id' => $conversation->id,
                'text' => $telegramText,
                'reply_markup' => $replyMarkup,
            ],
        ]);

        try {
            $result = app(TelegramService::class)->sendMessage(
                $conversation->chat_id,
                $telegramText,
                $replyMarkup,
                messageThreadId: TelegramService::messageThreadIdForTopic($conversation->topic_id),
                directMessagesTopicId: TelegramService::directMessagesTopicId($conversation->direct_messages_topic_id),
            );

            $delivery->update([
                'status' => 'sent',
                'telegram_message_id' => isset($result['message_id']) ? (string) $result['message_id'] : null,
                'parse_mode' => ($result['_opencompany_parse_mode_fallback'] ?? false) ? 'plain_text_fallback' : 'HTML',
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markDeliveryFailed($delivery, $e);

            Log::warning('Telegram command response failed', [
                'workspace_id' => $setting->workspace_id,
                'conversation_id' => $conversation->id,
                'retry_after' => $e instanceof TelegramRateLimitException ? $e->retryAfter : null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Convert renderer-owned plain card text into compact Telegram HTML.
     *
     * Most Telegram cards are authored as newline-oriented text because that is
     * easy to test and safe to compose from model/runtime data. Telegram Web
     * collapses that into dense paragraphs unless we add explicit visual
     * hierarchy. This formatter keeps the renderer API simple while producing a
     * consistent chat shape: bold title, bold labels, bullet rows, and escaped
     * user/runtime values.
     */
    private function telegramCardHtml(string $text): string
    {
        return app(TelegramCardRenderer::class)->cardHtml($text);
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

    /**
     * @param  array<string, mixed>  $message
     */
    private function shouldDispatchMessage(array $message, IntegrationSetting $setting, TelegramConversation $conversation): bool
    {
        if ($conversation->chat_type === 'private') {
            return true;
        }

        if ($conversation->mode === 'free_response') {
            return true;
        }

        if ((bool) $setting->getConfigValue('free_response', false)) {
            return true;
        }

        if ($this->isReplyToBot($message, (string) $setting->getConfigValue('bot_user_id', ''))) {
            return true;
        }

        return $this->hasBotMention($message, (string) $setting->getConfigValue('bot_username', ''));
    }

    /**
     * A reply to the OpenCompany bot in a group/topic is an explicit
     * continuation signal even when the user does not mention the bot in the new
     * message body.
     *
     * @param  array<string, mixed>  $message
     */
    private function isReplyToBot(array $message, string $botUserId): bool
    {
        if ($botUserId === '') {
            return false;
        }

        $reply = is_array($message['reply_to_message'] ?? null) ? $message['reply_to_message'] : [];
        $from = is_array($reply['from'] ?? null) ? $reply['from'] : [];
        $fromId = isset($from['id']) ? (string) $from['id'] : '';

        return $fromId !== '' && hash_equals($botUserId, $fromId);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function hasBotMention(array $message, string $botUsername): bool
    {
        if ($botUsername === '') {
            return false;
        }

        $text = (string) ($message['text'] ?? $message['caption'] ?? '');
        foreach (($message['entities'] ?? $message['caption_entities'] ?? []) as $entity) {
            if (($entity['type'] ?? '') !== 'mention') {
                continue;
            }

            $mention = mb_substr($text, (int) ($entity['offset'] ?? 0), (int) ($entity['length'] ?? 0));
            if (strtolower($mention) === '@'.strtolower($botUsername)) {
                return true;
            }
        }

        return false;
    }

    private function isUserAllowed(IntegrationSetting $setting, string $telegramUserId): bool
    {
        $allowed = $setting->getConfigValue('allowed_users', [])
            ?: $setting->getConfigValue('allowed_telegram_users', []);

        if (empty($allowed)) {
            return true;
        }

        return in_array($telegramUserId, array_map('strval', $allowed), true);
    }

    /**
     * @param  array<string, mixed>  $from
     */
    private function resolveUser(Workspace $workspace, array $from): User
    {
        $externalId = (string) ($from['id'] ?? '0');

        $linked = UserExternalIdentity::resolveUser('telegram', $externalId);
        if ($linked && $this->belongsToWorkspace($linked, $workspace)) {
            return $linked;
        }

        $name = trim(((string) ($from['first_name'] ?? '')).' '.((string) ($from['last_name'] ?? '')));
        $displayName = $name !== '' ? $name : ((string) ($from['username'] ?? 'User'));

        return User::firstOrCreate(
            ['email' => "telegram-{$workspace->id}-{$externalId}@external.opencompany"],
            [
                'id' => Str::uuid()->toString(),
                'name' => "{$displayName} (Telegram)",
                'type' => 'human',
                'presence' => 'online',
                'password' => bcrypt(Str::random(32)),
                'is_ephemeral' => true,
            ],
        );
    }

    private function belongsToWorkspace(User $user, Workspace $workspace): bool
    {
        if ($user->type === 'agent') {
            return $user->workspace_id === $workspace->id;
        }

        return WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function resolveAgent(Workspace $workspace, IntegrationSetting $setting, TelegramConversation $conversation): ?User
    {
        $agentId = $conversation->default_agent_id ?: $setting->getConfigValue('default_agent_id');
        $agent = $agentId ? User::find($agentId) : null;

        if ($agent && $agent->type === 'agent' && $agent->workspace_id === $workspace->id) {
            return $agent;
        }

        if ($conversation->channel_id) {
            $agentIds = ChannelMember::where('channel_id', $conversation->channel_id)->pluck('user_id');
            $agent = User::where('type', 'agent')
                ->where('workspace_id', $workspace->id)
                ->whereIn('id', $agentIds)
                ->first();

            if ($agent) {
                return $agent;
            }
        }

        return User::where('type', 'agent')
            ->where('workspace_id', $workspace->id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function resolveReplyToMessageId(array $message, TelegramConversation $conversation, Channel $channel): ?string
    {
        $replyToMessage = is_array($message['reply_to_message'] ?? null) ? $message['reply_to_message'] : null;
        $replyToExternalId = $replyToMessage && isset($replyToMessage['message_id'])
            ? (string) $replyToMessage['message_id']
            : null;

        if (! $replyToExternalId) {
            return null;
        }

        $mappingQuery = DB::table('telegram_message_mappings')
            ->where('integration_setting_id', $conversation->integration_setting_id)
            ->where('chat_id', $conversation->chat_id)
            ->where('telegram_message_id', $replyToExternalId);

        $conversation->topic_id === null
            ? $mappingQuery->whereNull('topic_id')
            : $mappingQuery->where('topic_id', $conversation->topic_id);
        $conversation->direct_messages_topic_id === null
            ? $mappingQuery->whereNull('direct_messages_topic_id')
            : $mappingQuery->where('direct_messages_topic_id', $conversation->direct_messages_topic_id);

        $mappedMessageId = $mappingQuery->value('message_id');

        if (is_string($mappedMessageId) && $mappedMessageId !== '') {
            return Message::where('channel_id', $channel->id)
                ->where('id', $mappedMessageId)
                ->value('id');
        }

        return Message::where('external_message_id', $replyToExternalId)
            ->where('channel_id', $channel->id)
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function messageText(array $message): string
    {
        $text = (string) ($message['text'] ?? $message['caption'] ?? '');
        $context = $this->telegramMessageContextLines($message);
        if ($text !== '') {
            return trim($text.($context === [] ? '' : "\n\n".implode("\n", $context)));
        }

        $base = match (true) {
            isset($message['checklist']) && is_array($message['checklist']) => $this->checklistText($message['checklist']),
            isset($message['paid_media']) && is_array($message['paid_media']) => $this->paidMediaText($message['paid_media']),
            isset($message['live_photo']) && is_array($message['live_photo']) => $this->livePhotoText($message['live_photo']),
            isset($message['managed_bot_created']) && is_array($message['managed_bot_created']) => $this->managedBotCreatedText($message['managed_bot_created']),
            isset($message['poll_option_added']) && is_array($message['poll_option_added']) => $this->pollOptionAddedText($message['poll_option_added']),
            isset($message['poll_option_deleted']) && is_array($message['poll_option_deleted']) => $this->pollOptionDeletedText($message['poll_option_deleted']),
            isset($message['chat_owner_left']) && is_array($message['chat_owner_left']) => $this->chatOwnerLeftText($message['chat_owner_left']),
            isset($message['chat_owner_changed']) && is_array($message['chat_owner_changed']) => $this->chatOwnerChangedText($message['chat_owner_changed']),
            isset($message['photo']) => '[Telegram photo]',
            isset($message['document']) => '[Telegram document]',
            isset($message['voice']) => '[Telegram voice message]',
            isset($message['audio']) => '[Telegram audio]',
            isset($message['video']) => '[Telegram video]',
            isset($message['video_note']) => '[Telegram video note]',
            isset($message['animation']) => '[Telegram animation]',
            isset($message['sticker']) => '[Telegram sticker]',
            isset($message['story']) && is_array($message['story']) => $this->storyText($message['story']),
            isset($message['location']) && is_array($message['location']) => $this->locationText($message['location']),
            isset($message['venue']) && is_array($message['venue']) => $this->venueText($message['venue']),
            isset($message['contact']) && is_array($message['contact']) => $this->contactText($message['contact']),
            isset($message['poll']) && is_array($message['poll']) => $this->pollText($message['poll']),
            default => '[Telegram message]',
        };

        return trim($base.($context === [] ? '' : "\n".implode("\n", $context)));
    }

    /**
     * Preserve modern Telegram message metadata in the OpenCompany prompt text.
     *
     * Telegram continues to add message-level affordances such as effects,
     * Stars-backed paid posts, suggested posts, and checklist reply context. The
     * raw update remains in telegram_update_receipts, but the normalized message
     * should still carry enough human-readable context for agents and operators.
     *
     * @param  array<string, mixed>  $message
     * @return list<string>
     */
    private function telegramMessageContextLines(array $message): array
    {
        $lines = [];

        if (isset($message['effect_id']) && is_scalar($message['effect_id'])) {
            $lines[] = 'Telegram effect: '.(string) $message['effect_id'];
        }

        if (($message['is_paid_post'] ?? false) === true) {
            $lines[] = 'Telegram paid post';
        }

        if (isset($message['paid_star_count']) && is_numeric($message['paid_star_count'])) {
            $lines[] = 'Telegram paid stars: '.(string) $message['paid_star_count'];
        }

        if (isset($message['suggested_post_info']) && is_array($message['suggested_post_info'])) {
            $lines[] = $this->suggestedPostText($message['suggested_post_info']);
        }

        if (isset($message['reply_to_checklist_task_id']) && is_scalar($message['reply_to_checklist_task_id'])) {
            $lines[] = 'Reply to checklist task: '.(string) $message['reply_to_checklist_task_id'];
        }

        if (isset($message['reply_to_story']) && is_array($message['reply_to_story'])) {
            $lines[] = 'Reply to '.$this->storyText($message['reply_to_story']);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $checklist
     */
    private function checklistText(array $checklist): string
    {
        $lines = ['Telegram checklist'];
        if (isset($checklist['title']) && is_string($checklist['title'])) {
            $lines[] = 'Title: '.$checklist['title'];
        }

        $tasks = is_array($checklist['tasks'] ?? null) ? $checklist['tasks'] : [];
        foreach (array_slice($tasks, 0, 20) as $task) {
            if (! is_array($task)) {
                continue;
            }

            $text = trim((string) ($task['text'] ?? 'Task'));
            $state = ($task['is_checked'] ?? false) === true ? '[x]' : '[ ]';
            $lines[] = "{$state} {$text}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $paidMedia
     */
    private function paidMediaText(array $paidMedia): string
    {
        $lines = ['Telegram paid media'];
        if (isset($paidMedia['star_count']) && is_numeric($paidMedia['star_count'])) {
            $lines[] = 'Stars: '.(string) $paidMedia['star_count'];
        }

        $items = is_array($paidMedia['paid_media'] ?? null) ? $paidMedia['paid_media'] : [];
        if ($items !== []) {
            $types = collect($items)
                ->filter(fn ($item) => is_array($item) && isset($item['type']))
                ->map(fn (array $item): string => (string) $item['type'])
                ->values()
                ->all();

            $lines[] = 'Items: '.count($items).($types === [] ? '' : ' ('.implode(', ', $types).')');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $livePhoto
     */
    private function livePhotoText(array $livePhoto): string
    {
        $lines = ['Telegram live photo'];
        foreach (['width' => 'Width', 'height' => 'Height', 'duration' => 'Duration'] as $key => $label) {
            if (isset($livePhoto[$key]) && is_scalar($livePhoto[$key])) {
                $lines[] = "{$label}: ".(string) $livePhoto[$key];
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $managedBotCreated
     */
    private function managedBotCreatedText(array $managedBotCreated): string
    {
        $lines = ['Telegram managed bot created'];
        $bot = is_array($managedBotCreated['bot'] ?? null) ? $managedBotCreated['bot'] : [];
        $botLabel = $this->telegramUserLabel($bot);
        if ($botLabel !== null) {
            $lines[] = 'Bot: '.$botLabel;
        }

        if (isset($managedBotCreated['manage_url']) && is_string($managedBotCreated['manage_url'])) {
            $lines[] = 'Manage URL: '.$managedBotCreated['manage_url'];
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $pollOptionAdded
     */
    private function pollOptionAddedText(array $pollOptionAdded): string
    {
        $lines = ['Telegram poll option added'];
        if (isset($pollOptionAdded['poll_id']) && is_scalar($pollOptionAdded['poll_id'])) {
            $lines[] = 'Poll ID: '.(string) $pollOptionAdded['poll_id'];
        }

        $option = is_array($pollOptionAdded['option'] ?? null) ? $pollOptionAdded['option'] : [];
        if (isset($option['text']) && is_string($option['text'])) {
            $lines[] = 'Option: '.$option['text'];
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $pollOptionDeleted
     */
    private function pollOptionDeletedText(array $pollOptionDeleted): string
    {
        $lines = ['Telegram poll option deleted'];
        if (isset($pollOptionDeleted['poll_id']) && is_scalar($pollOptionDeleted['poll_id'])) {
            $lines[] = 'Poll ID: '.(string) $pollOptionDeleted['poll_id'];
        }
        if (isset($pollOptionDeleted['option_id']) && is_scalar($pollOptionDeleted['option_id'])) {
            $lines[] = 'Option ID: '.(string) $pollOptionDeleted['option_id'];
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $chatOwnerLeft
     */
    private function chatOwnerLeftText(array $chatOwnerLeft): string
    {
        $lines = ['Telegram chat owner left'];
        $user = is_array($chatOwnerLeft['old_owner'] ?? null) ? $chatOwnerLeft['old_owner'] : [];
        $label = $this->telegramUserLabel($user);
        if ($label !== null) {
            $lines[] = 'Previous owner: '.$label;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $chatOwnerChanged
     */
    private function chatOwnerChangedText(array $chatOwnerChanged): string
    {
        $lines = ['Telegram chat owner changed'];
        $oldOwner = is_array($chatOwnerChanged['old_owner'] ?? null) ? $chatOwnerChanged['old_owner'] : [];
        $newOwner = is_array($chatOwnerChanged['new_owner'] ?? null) ? $chatOwnerChanged['new_owner'] : [];

        $oldLabel = $this->telegramUserLabel($oldOwner);
        if ($oldLabel !== null) {
            $lines[] = 'Previous owner: '.$oldLabel;
        }

        $newLabel = $this->telegramUserLabel($newOwner);
        if ($newLabel !== null) {
            $lines[] = 'New owner: '.$newLabel;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function telegramUserLabel(array $user): ?string
    {
        $name = trim(((string) ($user['first_name'] ?? '')).' '.((string) ($user['last_name'] ?? '')));
        $username = isset($user['username']) && is_string($user['username']) ? '@'.$user['username'] : null;
        $id = isset($user['id']) && is_scalar($user['id']) ? (string) $user['id'] : null;

        $label = collect([$name !== '' ? $name : null, $username, $id ? "({$id})" : null])
            ->filter()
            ->implode(' ');

        return $label !== '' ? $label : null;
    }

    /**
     * @param  array<string, mixed>  $story
     */
    private function storyText(array $story): string
    {
        $chat = is_array($story['chat'] ?? null) ? $story['chat'] : [];
        $chatLabel = $this->chatTitle($chat) ?: (isset($chat['id']) ? (string) $chat['id'] : 'unknown chat');
        $storyId = isset($story['id']) ? (string) $story['id'] : 'unknown';

        return "Telegram story {$storyId} from {$chatLabel}";
    }

    /**
     * @param  array<string, mixed>  $suggestedPost
     */
    private function suggestedPostText(array $suggestedPost): string
    {
        $lines = ['Telegram suggested post'];

        if (isset($suggestedPost['state']) && is_scalar($suggestedPost['state'])) {
            $lines[] = 'State: '.(string) $suggestedPost['state'];
        }

        if (isset($suggestedPost['price']) && is_array($suggestedPost['price'])) {
            $currency = (string) ($suggestedPost['price']['currency'] ?? 'XTR');
            $amount = isset($suggestedPost['price']['amount']) ? (string) $suggestedPost['price']['amount'] : null;
            if ($amount !== null) {
                $lines[] = "Price: {$amount} {$currency}";
            }
        }

        if (isset($suggestedPost['send_date']) && is_numeric($suggestedPost['send_date'])) {
            $lines[] = 'Send date: '.Carbon::createFromTimestamp((int) $suggestedPost['send_date'])->toIso8601String();
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $location
     */
    private function locationText(array $location): string
    {
        $latitude = $location['latitude'] ?? null;
        $longitude = $location['longitude'] ?? null;
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return '[Telegram location]';
        }

        $lat = (string) $latitude;
        $lon = (string) $longitude;

        return implode("\n", [
            'Telegram location',
            "Latitude: {$lat}",
            "Longitude: {$lon}",
            "Map: https://maps.google.com/?q={$lat},{$lon}",
        ]);
    }

    /**
     * @param  array<string, mixed>  $venue
     */
    private function venueText(array $venue): string
    {
        $lines = ['Telegram venue'];
        if (isset($venue['title']) && is_string($venue['title'])) {
            $lines[] = 'Title: '.$venue['title'];
        }
        if (isset($venue['address']) && is_string($venue['address'])) {
            $lines[] = 'Address: '.$venue['address'];
        }

        $location = is_array($venue['location'] ?? null) ? $venue['location'] : [];
        $locationText = $this->locationText($location);
        if ($locationText !== '[Telegram location]') {
            $lines[] = '';
            $lines[] = $locationText;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function contactText(array $contact): string
    {
        $firstName = trim((string) ($contact['first_name'] ?? ''));
        $lastName = trim((string) ($contact['last_name'] ?? ''));
        $name = trim($firstName.' '.$lastName);

        $lines = ['Telegram contact'];
        if ($name !== '') {
            $lines[] = "Name: {$name}";
        }
        if (isset($contact['phone_number']) && is_string($contact['phone_number'])) {
            $lines[] = 'Phone: '.$contact['phone_number'];
        }
        if (isset($contact['user_id'])) {
            $lines[] = 'Telegram user ID: '.(string) $contact['user_id'];
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $poll
     */
    private function pollText(array $poll): string
    {
        $lines = ['Telegram poll'];
        if (isset($poll['question']) && is_string($poll['question'])) {
            $lines[] = 'Question: '.$poll['question'];
        }

        $options = is_array($poll['options'] ?? null) ? $poll['options'] : [];
        foreach (array_slice($options, 0, 10) as $option) {
            if (! is_array($option)) {
                continue;
            }

            $label = (string) ($option['text'] ?? 'Option');
            $votes = isset($option['voter_count']) ? ' ('.((int) $option['voter_count']).' votes)' : '';
            $lines[] = '- '.$label.$votes;
        }

        return implode("\n", $lines);
    }

    /**
     * Download Telegram media into Workspace Files and attach the captured file
     * to the OpenCompany message.
     *
     * Telegram cannot be treated as a durable file store for OpenCompany: bots
     * receive transient file IDs and must call getFile before fetching bytes.
     * Capture is best-effort and isolated from message ingestion so a temporary
     * file failure never prevents the chat prompt from being recorded.
     *
     * @param  array<string, mixed>  $message
     */
    private function captureMedia(
        array $message,
        Workspace $workspace,
        User $user,
        Message $internalMessage,
        IntegrationSetting $setting,
        TelegramConversation $conversation,
        TelegramUpdateReceipt $receipt
    ): void {
        $items = $this->telegramMediaItems($message);
        if ($items === []) {
            return;
        }

        $capturedFiles = [];

        try {
            $folder = app(FileSystemService::class)->createFolder(
                $workspace->id,
                null,
                'Telegram Captures',
                $user->id,
            );
        } catch (\Throwable $e) {
            Log::warning('Telegram media capture folder unavailable', [
                'workspace_id' => $workspace->id,
                'message_id' => $internalMessage->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($items as $item) {
            try {
                $fileInfo = app(TelegramService::class)->getFile($item['file_id']);
                $filePath = (string) ($fileInfo['file_path'] ?? '');
                if ($filePath === '') {
                    throw new \RuntimeException('Telegram getFile did not return file_path.');
                }

                $bytes = app(TelegramService::class)->downloadFile($filePath);
                $workspaceFile = app(FileSystemService::class)->writeBinaryFile(
                    $workspace->id,
                    $folder->id,
                    $this->telegramMediaFilename($message, $item),
                    $bytes,
                    $user->id,
                    $item['mime_type'],
                    metadata: [
                        'source' => 'telegram',
                        'telegram_file_id' => $item['file_id'],
                        'telegram_file_unique_id' => $item['file_unique_id'],
                        'telegram_file_path' => $filePath,
                        'telegram_kind' => $item['kind'],
                        'telegram_message_id' => $message['message_id'] ?? null,
                        'telegram_media_group_id' => $message['media_group_id'] ?? null,
                        'telegram_enrichment' => $this->telegramMediaEnrichmentMetadata($item),
                    ],
                );

                MessageAttachment::create([
                    'id' => Str::uuid()->toString(),
                    'message_id' => $internalMessage->id,
                    'filename' => basename($workspaceFile->storage_path),
                    'original_name' => $workspaceFile->name,
                    'mime_type' => $workspaceFile->mime_type ?? 'application/octet-stream',
                    'size' => $workspaceFile->size ?? strlen($bytes),
                    'url' => "/api/files/{$workspaceFile->id}/download",
                    'uploaded_by_id' => $user->id,
                ]);

                $capturedFiles[] = $workspaceFile;
                $this->dispatchTelegramMediaEnrichment($workspaceFile);
            } catch (\Throwable $e) {
                Log::warning('Telegram media capture failed', [
                    'workspace_id' => $workspace->id,
                    'message_id' => $internalMessage->id,
                    'telegram_file_id' => $item['file_id'],
                    'error' => $e->getMessage(),
                ]);
                $this->recordReceiptDiagnostic($receipt, 'media_capture_failures', [
                    'message_id' => $internalMessage->id,
                    'telegram_message_id' => $message['message_id'] ?? null,
                    'telegram_file_id' => $item['file_id'],
                    'telegram_kind' => $item['kind'],
                    'error_class' => $e::class,
                    'error_message' => Str::limit($e->getMessage(), 500),
                ]);
            }
        }

        if ($capturedFiles !== []) {
            $this->sendMediaCaptureCard($setting, $conversation, $internalMessage, $capturedFiles);
        }
    }

    /**
     * Store bounded per-receipt diagnostics for failures that do not fail the
     * whole webhook update.
     *
     * Media capture is intentionally best-effort, so the receipt can still be
     * processed while individual files fail. Persisting those local failures on
     * the receipt makes them visible to operations metrics and replay tools
     * instead of leaving them only in logs.
     *
     * @param  array<string, mixed>  $entry
     */
    private function recordReceiptDiagnostic(TelegramUpdateReceipt $receipt, string $bucket, array $entry): void
    {
        $fresh = $receipt->fresh();
        if (! $fresh) {
            return;
        }

        $diagnostics = $fresh->diagnostics ?? [];
        $items = is_array($diagnostics[$bucket] ?? null) ? $diagnostics[$bucket] : [];
        $items[] = array_merge($entry, ['recorded_at' => now()->toISOString()]);
        $diagnostics[$bucket] = array_slice($items, -10);

        $fresh->forceFill(['diagnostics' => $diagnostics])->save();
    }

    /**
     * Queue optional AI enrichment only after the workspace file is durable.
     *
     * The enrichment worker reads this metadata contract again before doing any
     * model work, so dispatch is safe to skip for files where enrichment is
     * disabled, unsupported, or already represented by cached/completed state.
     */
    private function dispatchTelegramMediaEnrichment(WorkspaceFile $workspaceFile): void
    {
        $enrichment = is_array($workspaceFile->metadata)
            ? ($workspaceFile->metadata['telegram_enrichment'] ?? null)
            : null;

        if (! is_array($enrichment) || ($enrichment['status'] ?? null) !== 'queued') {
            return;
        }

        if (! in_array($enrichment['type'] ?? null, ['transcription', 'description'], true)) {
            return;
        }

        EnrichTelegramMediaJob::dispatch($workspaceFile);
    }

    /**
     * Describe optional AI enrichment work for captured Telegram media.
     *
     * The capture path is deliberately synchronous and bounded to file download
     * plus durable metadata. Transcription and visual description can be picked
     * up by a later worker from this metadata without re-reading Telegram update
     * payloads, and `file_unique_id` gives image/sticker descriptions a stable
     * cache key across chats and repeated sends.
     *
     * @param  array{kind: string, file_id: string, file_unique_id: ?string, file_name: ?string, mime_type: ?string}  $item
     * @return array{status: string, type: string|null, cache_key: string|null, reason?: string}
     */
    private function telegramMediaEnrichmentMetadata(array $item): array
    {
        if (! (bool) config('telegram.media_enrichment_enabled', true)) {
            return [
                'status' => 'disabled',
                'type' => null,
                'cache_key' => null,
                'reason' => 'telegram_media_enrichment_disabled',
            ];
        }

        $kind = $item['kind'];
        $type = match (true) {
            in_array($kind, ['voice', 'audio'], true) => 'transcription',
            in_array($kind, ['photo', 'sticker', 'live_photo', 'paid_media_photo', 'paid_media_live_photo'], true) => 'description',
            default => null,
        };

        if ($type === null) {
            return [
                'status' => 'not_applicable',
                'type' => null,
                'cache_key' => null,
            ];
        }

        $uniqueId = $item['file_unique_id'] ?: $item['file_id'];

        return [
            'status' => 'queued',
            'type' => $type,
            'cache_key' => "telegram_media_{$type}:{$uniqueId}",
        ];
    }

    /**
     * Send a compact Telegram-native receipt after files have been captured.
     *
     * The card keeps post-capture actions behind opaque callback tokens. Asking
     * an agent to summarize is identity-gated again at click time, because the
     * original media sender and the button clicker may differ in group chats.
     *
     * @param  list<WorkspaceFile>  $capturedFiles
     */
    private function sendMediaCaptureCard(
        IntegrationSetting $setting,
        TelegramConversation $conversation,
        Message $message,
        array $capturedFiles
    ): void {
        $fileIds = array_map(fn (WorkspaceFile $file): string => $file->id, $capturedFiles);
        $summarize = $this->mediaCaptureInteraction($setting, $message, $fileIds, 'summarize');
        $files = $this->mediaCaptureInteraction($setting, $message, $fileIds, 'files');
        $dismiss = $this->mediaCaptureInteraction($setting, $message, $fileIds, 'dismiss');
        $workspace = Workspace::find($conversation->workspace_id);

        $renderer = app(TelegramCardRenderer::class);
        $keyboard = [
            [
                ['text' => 'Ask agent', 'callback_data' => $summarize->token],
            ],
        ];

        if ($workspace) {
            $keyboard[] = [
                $this->commandButton($setting, $workspace, 'Switch agent', '/agents'),
                $this->commandButton($setting, $workspace, 'Topic', '/topic'),
            ];
        }

        $keyboard[] = [
            ['text' => 'Open files', 'callback_data' => $files->token],
            ['text' => 'Done', 'callback_data' => $dismiss->token],
        ];

        $this->sendText(
            $setting,
            $conversation,
            $renderer->mediaCaptureText($capturedFiles),
            [
                'inline_keyboard' => $keyboard,
            ],
            TelegramCardRenderer::MEDIA_CAPTURE_VERSION,
        );
    }

    /**
     * Create a one-shot callback token for a captured Telegram media batch.
     *
     * @param  list<string>  $workspaceFileIds
     */
    private function mediaCaptureInteraction(
        IntegrationSetting $setting,
        Message $message,
        array $workspaceFileIds,
        string $action,
        array $extraPayload = []
    ): TelegramInteraction {
        return TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $setting->workspace_id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'media_capture',
            'target_type' => Message::class,
            'target_id' => $message->id,
            'payload' => array_merge([
                'action' => $action,
                'message_id' => $message->id,
                'workspace_file_ids' => $workspaceFileIds,
            ], $extraPayload),
            'allowed_actor_rule' => [
                'type' => in_array($action, ['summarize', 'create_doc', 'choose_doc', 'attach_to_doc', 'change_folder', 'move_to_folder', 'move_to_root'], true) ? 'linked_workspace_member' : 'telegram_lane_command',
            ],
            'expires_at' => now()->addHours(6),
        ]));
    }

    /**
     * @param  array<string, mixed>  $message
     * @return list<array{kind: string, file_id: string, file_unique_id: ?string, file_name: ?string, mime_type: ?string}>
     */
    private function telegramMediaItems(array $message): array
    {
        $items = [];

        if (isset($message['photo']) && is_array($message['photo']) && $message['photo'] !== []) {
            $photo = collect($message['photo'])->sortBy('file_size')->last();
            if (is_array($photo) && isset($photo['file_id'])) {
                $items[] = [
                    'kind' => 'photo',
                    'file_id' => (string) $photo['file_id'],
                    'file_unique_id' => isset($photo['file_unique_id']) ? (string) $photo['file_unique_id'] : null,
                    'file_name' => null,
                    'mime_type' => 'image/jpeg',
                ];
            }
        }

        foreach ([
            'document' => 'application/octet-stream',
            'voice' => 'audio/ogg',
            'audio' => 'audio/mpeg',
            'video' => 'video/mp4',
            'video_note' => 'video/mp4',
            'animation' => 'video/mp4',
            'sticker' => 'image/webp',
        ] as $key => $fallbackMimeType) {
            $media = is_array($message[$key] ?? null) ? $message[$key] : null;
            if (! $media || ! isset($media['file_id'])) {
                continue;
            }

            $items[] = [
                'kind' => $key,
                'file_id' => (string) $media['file_id'],
                'file_unique_id' => isset($media['file_unique_id']) ? (string) $media['file_unique_id'] : null,
                'file_name' => isset($media['file_name']) ? (string) $media['file_name'] : null,
                'mime_type' => isset($media['mime_type']) ? (string) $media['mime_type'] : $fallbackMimeType,
            ];
        }

        if (isset($message['live_photo']) && is_array($message['live_photo'])) {
            foreach ($this->livePhotoMediaItems($message['live_photo']) as $item) {
                $items[] = $item;
            }
        }

        if (isset($message['paid_media']) && is_array($message['paid_media'])) {
            foreach ($this->paidMediaItems($message['paid_media']) as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $livePhoto
     * @return list<array{kind: string, file_id: string, file_unique_id: ?string, file_name: ?string, mime_type: ?string}>
     */
    private function livePhotoMediaItems(array $livePhoto): array
    {
        $items = [];

        $photo = is_array($livePhoto['photo'] ?? null) ? collect($livePhoto['photo'])->sortBy('file_size')->last() : null;
        if (is_array($photo) && isset($photo['file_id'])) {
            $items[] = [
                'kind' => 'live_photo',
                'file_id' => (string) $photo['file_id'],
                'file_unique_id' => isset($photo['file_unique_id']) ? (string) $photo['file_unique_id'] : null,
                'file_name' => null,
                'mime_type' => 'image/jpeg',
            ];
        }

        $video = is_array($livePhoto['video'] ?? null) ? $livePhoto['video'] : null;
        if ($video && isset($video['file_id'])) {
            $items[] = [
                'kind' => 'live_photo_video',
                'file_id' => (string) $video['file_id'],
                'file_unique_id' => isset($video['file_unique_id']) ? (string) $video['file_unique_id'] : null,
                'file_name' => isset($video['file_name']) ? (string) $video['file_name'] : null,
                'mime_type' => isset($video['mime_type']) ? (string) $video['mime_type'] : 'video/mp4',
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $paidMedia
     * @return list<array{kind: string, file_id: string, file_unique_id: ?string, file_name: ?string, mime_type: ?string}>
     */
    private function paidMediaItems(array $paidMedia): array
    {
        $items = [];
        $mediaItems = is_array($paidMedia['paid_media'] ?? null) ? $paidMedia['paid_media'] : [];

        foreach ($mediaItems as $index => $media) {
            if (! is_array($media)) {
                continue;
            }

            if (($media['type'] ?? null) === 'photo' && isset($media['photo']) && is_array($media['photo'])) {
                $photo = collect($media['photo'])->sortBy('file_size')->last();
                if (is_array($photo) && isset($photo['file_id'])) {
                    $items[] = [
                        'kind' => 'paid_media_photo',
                        'file_id' => (string) $photo['file_id'],
                        'file_unique_id' => isset($photo['file_unique_id']) ? (string) $photo['file_unique_id'] : null,
                        'file_name' => 'paid-media-'.$index.'.jpg',
                        'mime_type' => 'image/jpeg',
                    ];
                }
            }

            if (($media['type'] ?? null) === 'video' && isset($media['video']) && is_array($media['video']) && isset($media['video']['file_id'])) {
                $video = $media['video'];
                $items[] = [
                    'kind' => 'paid_media_video',
                    'file_id' => (string) $video['file_id'],
                    'file_unique_id' => isset($video['file_unique_id']) ? (string) $video['file_unique_id'] : null,
                    'file_name' => isset($video['file_name']) ? (string) $video['file_name'] : 'paid-media-'.$index.'.mp4',
                    'mime_type' => isset($video['mime_type']) ? (string) $video['mime_type'] : 'video/mp4',
                ];
            }

            if (($media['type'] ?? null) === 'live_photo' && isset($media['live_photo']) && is_array($media['live_photo'])) {
                foreach ($this->livePhotoMediaItems($media['live_photo']) as $item) {
                    $items[] = [
                        ...$item,
                        'kind' => 'paid_media_'.$item['kind'],
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array{kind: string, file_id: string, file_unique_id: ?string, file_name: ?string, mime_type: ?string}  $item
     */
    private function telegramMediaFilename(array $message, array $item): string
    {
        $messageId = (string) ($message['message_id'] ?? Str::random(8));
        $name = $item['file_name'] ?: match ($item['kind']) {
            'photo' => 'photo.jpg',
            'voice' => 'voice.ogg',
            'audio' => 'audio.mp3',
            'video' => 'video.mp4',
            'video_note' => 'video-note.mp4',
            'animation' => 'animation.mp4',
            'sticker' => 'sticker.webp',
            'live_photo' => 'live-photo.jpg',
            'live_photo_video' => 'live-photo-video.mp4',
            'paid_media_photo' => 'paid-media.jpg',
            'paid_media_video' => 'paid-media.mp4',
            'paid_media_live_photo' => 'paid-media-live-photo.jpg',
            'paid_media_live_photo_video' => 'paid-media-live-photo-video.mp4',
            default => 'file.bin',
        };

        $name = trim((string) preg_replace('/[^\w.\- ]+/', '_', basename($name)));

        return "telegram-{$messageId}-".($name !== '' ? $name : 'file.bin');
    }
}

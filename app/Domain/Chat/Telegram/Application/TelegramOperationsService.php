<?php

namespace App\Domain\Chat\Telegram\Application;

use App\Models\Channel;
use App\Models\IntegrationSetting;
use App\Models\TelegramConversation;
use App\Models\TelegramDelivery;
use App\Models\TelegramIntegrationProfile;
use App\Models\TelegramInteraction;
use App\Models\TelegramUpdateReceipt;
use App\Models\WorkspaceFile;
use App\Services\TelegramService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Operator-facing Telegram repair and observability actions.
 *
 * Webhook delivery is intentionally acknowledged after durable persistence, so
 * failed receipts and delivery attempts need an explicit app-owned repair path.
 * This service keeps those actions inside the Chat Telegram domain and uses the
 * stored receipt/delivery rows as the source of truth.
 */
class TelegramOperationsService
{
    /**
     * Return compact health counters for the last day and current repair queue.
     *
     * @return array<string, mixed>
     */
    public function metrics(IntegrationSetting $setting): array
    {
        $since = now()->subDay();

        $receiptQuery = TelegramUpdateReceipt::where('workspace_id', $setting->workspace_id)
            ->where('integration_setting_id', $setting->id);
        $deliveryQuery = TelegramDelivery::where('workspace_id', $setting->workspace_id)
            ->where('integration_setting_id', $setting->id);

        $recentReceipts = (clone $receiptQuery)->where('received_at', '>=', $since);
        $recentDeliveries = (clone $deliveryQuery)->where('created_at', '>=', $since);
        $rateLimitedDeliveries = $this->rateLimitedDeliveries(clone $deliveryQuery);
        $processedReceiptTimings = $this->processedReceiptTimings(clone $recentReceipts);

        return [
            'receipts_24h' => (clone $recentReceipts)->count(),
            'processed_receipts_24h' => (clone $recentReceipts)->where('status', 'processed')->count(),
            'failed_receipts_24h' => (clone $recentReceipts)->where('status', 'failed')->count(),
            'update_type_counts_24h' => (clone $recentReceipts)
                ->selectRaw('update_type, count(*) as aggregate')
                ->groupBy('update_type')
                ->pluck('aggregate', 'update_type')
                ->all(),
            'duplicate_update_hits' => (int) (clone $receiptQuery)->sum('duplicate_count'),
            'duplicate_update_hits_24h' => (int) (clone $receiptQuery)
                ->where('last_duplicate_at', '>=', $since)
                ->sum('duplicate_count'),
            'deduped_receipts_24h' => (clone $receiptQuery)
                ->where('last_duplicate_at', '>=', $since)
                ->count(),
            'average_processing_delay_ms_24h' => $this->average($processedReceiptTimings),
            'p95_processing_delay_ms_24h' => $this->percentile($processedReceiptTimings, 95),
            'stale_processing_receipts' => (clone $receiptQuery)
                ->where('status', 'processing')
                ->where('updated_at', '<=', now()->subMinutes(5))
                ->count(),
            'deliveries_24h' => (clone $recentDeliveries)->count(),
            'sent_deliveries_24h' => (clone $recentDeliveries)->where('status', 'sent')->count(),
            'plain_text_fallback_deliveries_24h' => (clone $recentDeliveries)
                ->where('status', 'sent')
                ->where('parse_mode', 'plain_text_fallback')
                ->count(),
            'failed_deliveries_24h' => (clone $recentDeliveries)->where('status', 'failed')->count(),
            'pending_deliveries' => (clone $deliveryQuery)->where('status', 'pending')->count(),
            'retryable_receipts' => (clone $receiptQuery)
                ->whereIn('status', ['failed', 'received', 'processing'])
                ->count(),
            'retryable_deliveries' => (clone $deliveryQuery)
                ->whereIn('status', ['failed', 'pending'])
                ->count(),
            'rate_limited_deliveries' => (clone $rateLimitedDeliveries)->count(),
            'next_rate_limit_retry_at' => $this->nextRateLimitRetryAt(clone $rateLimitedDeliveries),
            'callback_interactions_24h' => (clone $this->resolvedInteractions($setting))
                ->where('resolved_at', '>=', $since)
                ->count(),
            'callback_failures_24h' => (clone $this->failedInteractions($setting))
                ->where('resolved_at', '>=', $since)
                ->count(),
            'media_capture_failures_24h' => $this->mediaCaptureFailures(clone $recentReceipts),
            'media_enrichment_failures_24h' => $this->mediaEnrichmentFailures($setting),
            'expired_open_interactions' => $this->expiredOpenInteractions($setting)->count(),
            'orphaned_conversations' => $this->orphanedConversations($setting)->count(),
            'broken_conversation_mappings' => $this->brokenConversationMappings($setting)->count(),
            'stale_health_profile' => $this->hasStaleHealthProfile($setting),
            'delivery_failures_by_error' => (clone $deliveryQuery)
                ->where('status', 'failed')
                ->selectRaw('provider_error_code, count(*) as aggregate')
                ->groupBy('provider_error_code')
                ->pluck('aggregate', 'provider_error_code')
                ->all(),
        ];
    }

    /**
     * Return recent Telegram receipts and delivery attempts in an API-safe
     * shape for admin diagnostics. Payload bodies and webhook secrets stay out
     * of this surface; operators get enough identifiers, status, renderer, and
     * error context to replay or retry the durable row through the repair
     * endpoints.
     *
     * @return array{receipts: list<array<string, mixed>>, deliveries: list<array<string, mixed>>}
     */
    public function logs(IntegrationSetting $setting, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $receipts = TelegramUpdateReceipt::where('workspace_id', $setting->workspace_id)
            ->where('integration_setting_id', $setting->id)
            ->latest('received_at')
            ->limit($limit)
            ->get()
            ->map(fn (TelegramUpdateReceipt $receipt) => [
                'id' => $receipt->id,
                'update_id' => $receipt->update_id,
                'update_type' => $receipt->update_type,
                'status' => $receipt->status,
                'telegram_conversation_id' => $receipt->telegram_conversation_id,
                'error_class' => $receipt->error_class,
                'error_message' => $receipt->error_message,
                'retry_count' => $receipt->retry_count,
                'duplicate_count' => $receipt->duplicate_count,
                'last_duplicate_at' => $receipt->last_duplicate_at?->toIso8601String(),
                'processing_duration_ms' => $this->receiptProcessingDuration($receipt),
                'diagnostic_counts' => $this->diagnosticCounts($receipt),
                'received_at' => $receipt->received_at?->toIso8601String(),
                'processed_at' => $receipt->processed_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $deliveries = TelegramDelivery::where('workspace_id', $setting->workspace_id)
            ->where('integration_setting_id', $setting->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (TelegramDelivery $delivery) => [
                'id' => $delivery->id,
                'source_type' => $delivery->source_type,
                'source_id' => $delivery->source_id,
                'chat_id' => $delivery->chat_id,
                'topic_id' => $delivery->topic_id,
                'direct_messages_topic_id' => $delivery->direct_messages_topic_id,
                'telegram_message_id' => $delivery->telegram_message_id,
                'method' => is_array($delivery->request_payload) ? ($delivery->request_payload['method'] ?? null) : null,
                'renderer_version' => $delivery->renderer_version,
                'parse_mode' => $delivery->parse_mode,
                'status' => $delivery->status,
                'attempts' => $delivery->attempts,
                'provider_error_code' => $delivery->provider_error_code,
                'provider_error_message' => $delivery->provider_error_message,
                'sent_at' => $delivery->sent_at?->toIso8601String(),
                'created_at' => $delivery->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'receipts' => $receipts,
            'deliveries' => $deliveries,
        ];
    }

    /**
     * Return the repair queue as explicit buckets so admin UI and Mini App
     * panels can show exactly which local Telegram state is stale before an
     * operator runs a mutation.
     *
     * @return array<string, mixed>
     */
    public function repairCandidates(IntegrationSetting $setting): array
    {
        return [
            'expired_open_interactions' => $this->expiredOpenInteractions($setting)
                ->latest('expires_at')
                ->limit(50)
                ->get(['id', 'interaction_type', 'target_type', 'target_id', 'expires_at'])
                ->map(fn (TelegramInteraction $interaction) => [
                    'id' => $interaction->id,
                    'interaction_type' => $interaction->interaction_type,
                    'target_type' => $interaction->target_type,
                    'target_id' => $interaction->target_id,
                    'expires_at' => $interaction->expires_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'orphaned_conversations' => $this->orphanedConversations($setting)
                ->latest('updated_at')
                ->limit(50)
                ->get()
                ->map(fn (TelegramConversation $conversation) => $this->conversationRepairShape($conversation, 'missing_channel'))
                ->values()
                ->all(),
            'broken_conversation_mappings' => $this->brokenConversationMappings($setting)
                ->map(fn (TelegramConversation $conversation) => $this->conversationRepairShape($conversation, 'channel_metadata_drift'))
                ->values()
                ->all(),
            'stale_health_profile' => $this->staleHealthProfileCandidate($setting),
        ];
    }

    /**
     * Repair local, workspace-scoped Telegram runtime rows that are safe to fix
     * without calling Telegram: expired callback tokens are closed, missing
     * channel mappings are recreated, and drifted channel metadata is brought
     * back in line with the persisted Telegram conversation.
     *
     * @param  list<string>  $actions
     * @return array<string, mixed>
     */
    public function repairLocalState(IntegrationSetting $setting, array $actions = []): array
    {
        $actions = $actions === [] ? ['expire_interactions', 'repair_conversations'] : $actions;
        $result = [
            'expired_interactions' => 0,
            'created_channels' => 0,
            'repaired_channel_mappings' => 0,
            'detached_invalid_mappings' => 0,
            'webhook_repair' => [
                'attempted' => false,
                'success' => null,
                'status' => null,
                'webhook_url' => null,
                'error' => null,
            ],
        ];

        if (in_array('expire_interactions', $actions, true)) {
            $result['expired_interactions'] = $this->expiredOpenInteractions($setting)->update([
                'resolved_at' => now(),
                'final_status' => 'expired',
            ]);
        }

        if (in_array('repair_conversations', $actions, true)) {
            foreach ($this->orphanedConversations($setting)->get() as $conversation) {
                $channel = $this->createConversationChannel($conversation);
                $conversation->update(['channel_id' => $channel->id]);
                $result['created_channels']++;
            }

            foreach ($this->brokenConversationMappings($setting) as $conversation) {
                $channel = $conversation->channel;
                if (! $channel || $channel->workspace_id !== $conversation->workspace_id) {
                    $conversation->update(['channel_id' => null]);
                    $result['detached_invalid_mappings']++;

                    continue;
                }

                $channel->update($this->channelAttributesForConversation($conversation));
                $result['repaired_channel_mappings']++;
            }
        }

        if (in_array('repair_webhook', $actions, true)) {
            $result['webhook_repair']['attempted'] = true;

            try {
                $setup = app(TelegramSetupService::class)->setupWebhook($setting);
                $profile = $setup['profile'] ?? null;

                $result['webhook_repair']['success'] = true;
                $result['webhook_repair']['status'] = $profile instanceof TelegramIntegrationProfile
                    ? $profile->health_status
                    : null;
                $result['webhook_repair']['webhook_url'] = is_string($setup['webhookUrl'] ?? null)
                    ? $setup['webhookUrl']
                    : null;
            } catch (\Throwable $e) {
                $result['webhook_repair']['success'] = false;
                $result['webhook_repair']['error'] = Str::limit($e->getMessage(), 500);
            }
        }

        return $result;
    }

    public function replayReceipt(TelegramUpdateReceipt $receipt): TelegramUpdateReceipt
    {
        app(TelegramWebhookPipeline::class)->replayReceipt($receipt);

        return $receipt->fresh();
    }

    public function retryDelivery(TelegramDelivery $delivery): TelegramDelivery
    {
        if (! in_array($delivery->status, ['failed', 'pending'], true)) {
            throw new \RuntimeException('Only failed or pending Telegram deliveries can be retried.');
        }

        $setting = $delivery->integrationSetting;
        if (! $setting) {
            throw new \RuntimeException('Telegram delivery is missing its integration setting.');
        }

        if ($workspace = $delivery->workspace) {
            app()->instance('currentWorkspace', $workspace);
        }

        $payload = $delivery->request_payload ?? [];
        if (! is_array($payload)) {
            throw new \RuntimeException('Telegram delivery request payload is not retryable.');
        }

        $delivery->update([
            'status' => 'pending',
            'provider_error_code' => null,
            'provider_error_message' => null,
        ]);

        try {
            $result = $this->sendStoredPayload($delivery, $payload);

            $delivery->update([
                'status' => 'sent',
                'telegram_message_id' => isset($result['message_id'])
                    ? (string) $result['message_id']
                    : $delivery->telegram_message_id,
                'parse_mode' => $this->parseModeFromResult($delivery, $result),
                'response_payload' => $result,
                'attempts' => $delivery->attempts + 1,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $updates = [
                'status' => 'failed',
                'provider_error_code' => $e::class,
                'provider_error_message' => Str::limit($e->getMessage(), 2000),
                'attempts' => $delivery->attempts + 1,
            ];

            if ($e instanceof TelegramRateLimitException) {
                $updates['response_payload'] = [
                    'retry_after' => $e->retryAfter,
                    'retry_at' => now()->addSeconds($e->retryAfter)->toIso8601String(),
                ];
            }

            $delivery->update($updates);

            throw $e;
        }

        return $delivery->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendStoredPayload(TelegramDelivery $delivery, array $payload): array
    {
        $telegram = app(TelegramService::class);
        $threadId = TelegramService::messageThreadIdForTopic($delivery->topic_id);

        return match ((string) ($payload['method'] ?? '')) {
            'sendMessage' => $telegram->sendMessage(
                $delivery->chat_id,
                $this->requiredString($payload, 'text'),
                $this->optionalArray($payload, 'reply_markup'),
                messageThreadId: $threadId,
                directMessagesTopicId: TelegramService::directMessagesTopicId($delivery->direct_messages_topic_id),
            ),
            'sendMessageDraft' => $telegram->sendMessageDraft(
                $delivery->chat_id,
                (int) $this->requiredString($payload, 'draft_id'),
                $this->optionalString($payload, 'text'),
                $threadId,
            ),
            'answerGuestQuery' => $telegram->answerGuestQuery(
                $this->requiredString($payload, 'guest_query_id'),
                $this->requiredString($payload, 'text'),
                $this->optionalString($payload, 'title') ?: null,
            ),
            'answerInlineQuery' => $telegram->answerInlineQuery(
                $this->requiredString($payload, 'inline_query_id'),
                $this->requiredArray($payload, 'results'),
                (int) ($payload['cache_time'] ?? 0),
                (bool) ($payload['is_personal'] ?? true),
            ),
            'editMessageText' => $telegram->editMessageText(
                $delivery->chat_id,
                (int) $this->requiredString($payload, 'message_id'),
                (string) ($payload['text'] ?? TelegramService::markdownToTelegramHtml((string) ($delivery->source?->content ?? ''))),
            ),
            'deleteMessage' => $telegram->deleteMessage(
                $delivery->chat_id,
                (int) $this->requiredString($payload, 'message_id'),
            ),
            'pinChatMessage' => $telegram->pinChatMessage(
                $delivery->chat_id,
                (int) $this->requiredString($payload, 'message_id'),
            ),
            'setMessageReaction' => $telegram->setMessageReaction(
                $delivery->chat_id,
                (int) $this->requiredString($payload, 'message_id'),
                $this->requiredString($payload, 'emoji'),
            ),
            default => throw new \RuntimeException('Telegram delivery method is not retryable.'),
        };
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function parseModeFromResult(TelegramDelivery $delivery, array $result): ?string
    {
        if (($result['_opencompany_parse_mode_fallback'] ?? false) === true) {
            return 'plain_text_fallback';
        }

        return $delivery->parse_mode;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (! is_scalar($value) || (string) $value === '') {
            throw new \RuntimeException("Telegram delivery payload is missing {$key}.");
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function optionalString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requiredArray(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        if (! is_array($value)) {
            throw new \RuntimeException("Telegram delivery payload is missing {$key}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function optionalArray(array $payload, string $key): ?array
    {
        return isset($payload[$key]) && is_array($payload[$key]) ? $payload[$key] : null;
    }

    /**
     * @param  Builder<TelegramDelivery>  $query
     * @return Builder<TelegramDelivery>
     */
    private function rateLimitedDeliveries(Builder $query): Builder
    {
        return $query
            ->where('status', 'failed')
            ->where(function (Builder $query): void {
                $query
                    ->where('provider_error_code', TelegramRateLimitException::class)
                    ->orWhereNotNull('response_payload->retry_after');
            });
    }

    /**
     * @param  Builder<TelegramDelivery>  $query
     */
    private function nextRateLimitRetryAt(Builder $query): ?string
    {
        return $query
            ->get(['response_payload'])
            ->pluck('response_payload.retry_at')
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->sort()
            ->first();
    }

    /**
     * @param  Builder<TelegramUpdateReceipt>  $query
     * @return list<int>
     */
    private function processedReceiptTimings(Builder $query): array
    {
        return $query
            ->whereNotNull('processed_at')
            ->get(['received_at', 'processed_at'])
            ->map(fn (TelegramUpdateReceipt $receipt): ?int => $this->receiptProcessingDuration($receipt))
            ->filter(fn (?int $milliseconds): bool => $milliseconds !== null)
            ->values()
            ->all();
    }

    private function receiptProcessingDuration(TelegramUpdateReceipt $receipt): ?int
    {
        if (! $receipt->received_at || ! $receipt->processed_at) {
            return null;
        }

        return max(0, $receipt->received_at->diffInMilliseconds($receipt->processed_at, false));
    }

    /**
     * @param  list<int>  $values
     */
    private function average(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        return (int) round(array_sum($values) / count($values));
    }

    /**
     * @param  list<int>  $values
     */
    private function percentile(array $values, int $percentile): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;

        return $values[max(0, min($index, count($values) - 1))];
    }

    /**
     * @param  Builder<TelegramUpdateReceipt>  $query
     */
    private function mediaCaptureFailures(Builder $query): int
    {
        return $query
            ->get(['diagnostics'])
            ->sum(function (TelegramUpdateReceipt $receipt): int {
                $failures = is_array($receipt->diagnostics)
                    ? ($receipt->diagnostics['media_capture_failures'] ?? [])
                    : [];

                return is_array($failures) ? count($failures) : 0;
            });
    }

    private function mediaEnrichmentFailures(IntegrationSetting $setting): int
    {
        return WorkspaceFile::where('workspace_id', $setting->workspace_id)
            ->where('updated_at', '>=', now()->subDay())
            ->where('metadata->source', 'telegram')
            ->where('metadata->telegram_enrichment->status', 'failed')
            ->count();
    }

    /** @return Builder<TelegramInteraction> */
    private function resolvedInteractions(IntegrationSetting $setting): Builder
    {
        return TelegramInteraction::where('workspace_id', $setting->workspace_id)
            ->where('integration_setting_id', $setting->id)
            ->whereNotNull('resolved_at');
    }

    /** @return Builder<TelegramInteraction> */
    private function failedInteractions(IntegrationSetting $setting): Builder
    {
        return $this->resolvedInteractions($setting)
            ->whereIn('final_status', ['tampered', 'expired', 'missing', 'unauthorized']);
    }

    /**
     * @return array<string, int>
     */
    private function diagnosticCounts(TelegramUpdateReceipt $receipt): array
    {
        if (! is_array($receipt->diagnostics)) {
            return [];
        }

        $counts = [];
        foreach ($receipt->diagnostics as $key => $items) {
            $counts[(string) $key] = is_array($items) ? count($items) : 1;
        }

        return $counts;
    }

    /** @return Builder<TelegramInteraction> */
    private function expiredOpenInteractions(IntegrationSetting $setting): Builder
    {
        return TelegramInteraction::where('workspace_id', $setting->workspace_id)
            ->where('integration_setting_id', $setting->id)
            ->whereNull('resolved_at')
            ->where(function (Builder $query): void {
                $query->whereNull('final_status')->orWhere('final_status', '');
            })
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /** @return Builder<TelegramConversation> */
    private function orphanedConversations(IntegrationSetting $setting): Builder
    {
        return TelegramConversation::where('workspace_id', $setting->workspace_id)
            ->where('integration_setting_id', $setting->id)
            ->whereNull('channel_id')
            ->whereNull('archived_at');
    }

    /** @return Collection<int, TelegramConversation> */
    private function brokenConversationMappings(IntegrationSetting $setting)
    {
        return TelegramConversation::where('workspace_id', $setting->workspace_id)
            ->where('integration_setting_id', $setting->id)
            ->whereNotNull('channel_id')
            ->with('channel')
            ->get()
            ->filter(fn (TelegramConversation $conversation): bool => $this->conversationHasBrokenChannelMapping($conversation))
            ->values();
    }

    private function conversationHasBrokenChannelMapping(TelegramConversation $conversation): bool
    {
        $channel = $conversation->channel;
        if (! $channel) {
            return true;
        }

        $expected = $this->channelAttributesForConversation($conversation);

        return $channel->workspace_id !== $conversation->workspace_id
            || $channel->type !== $expected['type']
            || $channel->external_provider !== $expected['external_provider']
            || $channel->external_id !== $expected['external_id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationRepairShape(TelegramConversation $conversation, string $reason): array
    {
        return [
            'id' => $conversation->id,
            'reason' => $reason,
            'chat_id' => $conversation->chat_id,
            'chat_type' => $conversation->chat_type,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'channel_id' => $conversation->channel_id,
            'expected_external_id' => $this->conversationExternalId($conversation),
        ];
    }

    /** @return array{name: string, type: string, external_provider: string, external_id: string, external_config: array<string, mixed>} */
    private function channelAttributesForConversation(TelegramConversation $conversation): array
    {
        return [
            'name' => $this->conversationChannelName($conversation),
            'type' => 'external',
            'external_provider' => 'telegram',
            'external_id' => $this->conversationExternalId($conversation),
            'external_config' => [
                'chat_id' => $conversation->chat_id,
                'chat_type' => $conversation->chat_type,
                'topic_id' => $conversation->topic_id,
                'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            ],
        ];
    }

    private function createConversationChannel(TelegramConversation $conversation): Channel
    {
        return Channel::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $conversation->workspace_id,
            ...$this->channelAttributesForConversation($conversation),
        ]);
    }

    private function conversationExternalId(TelegramConversation $conversation): string
    {
        if ($conversation->topic_id) {
            return "{$conversation->chat_id}:{$conversation->topic_id}";
        }

        if ($conversation->direct_messages_topic_id) {
            return "{$conversation->chat_id}:dm:{$conversation->direct_messages_topic_id}";
        }

        return $conversation->chat_id;
    }

    private function conversationChannelName(TelegramConversation $conversation): string
    {
        $label = $conversation->title ?: $conversation->username ?: $conversation->chat_id;
        if ($conversation->topic_id) {
            return "Telegram: {$label} / topic {$conversation->topic_id}";
        }

        if ($conversation->direct_messages_topic_id) {
            return "Telegram: {$label} / DM topic {$conversation->direct_messages_topic_id}";
        }

        return "Telegram: {$label}";
    }

    private function hasStaleHealthProfile(IntegrationSetting $setting): bool
    {
        $profile = $this->profileForSetting($setting);

        if (! $profile) {
            return true;
        }

        $expectedWebhookUrl = rtrim((string) config('app.url'), '/').'/api/webhooks/chat/telegram';

        return $profile->webhook_url !== $expectedWebhookUrl
            || ! $profile->last_health_checked_at
            || $profile->last_health_checked_at->lte(now()->subMinutes(15));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function staleHealthProfileCandidate(IntegrationSetting $setting): ?array
    {
        if (! $this->hasStaleHealthProfile($setting)) {
            return null;
        }

        $profile = $this->profileForSetting($setting);
        $expectedWebhookUrl = rtrim((string) config('app.url'), '/').'/api/webhooks/chat/telegram';

        return [
            'reason' => $profile ? 'stale_or_mismatched_profile' : 'missing_profile',
            'expected_webhook_url' => $expectedWebhookUrl,
            'current_webhook_url' => $profile?->webhook_url,
            'health_status' => $profile?->health_status,
            'last_health_checked_at' => $profile?->last_health_checked_at?->toIso8601String(),
        ];
    }

    private function profileForSetting(IntegrationSetting $setting): ?TelegramIntegrationProfile
    {
        return TelegramIntegrationProfile::where('workspace_id', $setting->workspace_id)
            ->where('integration_setting_id', $setting->id)
            ->first();
    }
}

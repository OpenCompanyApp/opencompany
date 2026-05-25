<?php

namespace App\Domain\Chat\Telegram\Application;

use App\Models\Channel;
use App\Models\IntegrationSetting;
use App\Models\Message;
use App\Models\TelegramDelivery;
use App\Models\Workspace;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * App-owned outbound sync for Telegram chat channels.
 *
 * This service is the outbound counterpart to TelegramWebhookPipeline. It keeps
 * Telegram delivery behavior inside OpenCompany's Chat domain rather than
 * relying on Chatogrator's Telegram adapter. Chatogrator remains the
 * provider abstraction for other chat apps while Telegram-specific delivery
 * records, topic IDs, and Bot API semantics are tracked here.
 */
class TelegramOutboundSync
{
    public function sendMessage(Message $message): void
    {
        $channel = $message->channel;
        $setting = $this->settingFor($channel);
        if (! $channel || ! $setting) {
            return;
        }

        $target = $this->targetFromChannel($channel);
        $authorName = $message->author->name ?? 'System';
        $text = '<b>'.htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8')."</b>\n"
            .TelegramService::markdownToTelegramHtml($message->content);

        $delivery = $this->createDelivery($message, $setting, $target, [
            'method' => 'sendMessage',
            'text' => $text,
        ]);

        try {
            $this->bindWorkspace($channel);
            $result = app(TelegramService::class)->sendMessage(
                $target['chat_id'],
                $text,
                replyMarkup: null,
                replyToMessageId: null,
                messageThreadId: TelegramService::messageThreadIdForTopic($target['topic_id']),
                directMessagesTopicId: TelegramService::directMessagesTopicId($target['direct_messages_topic_id']),
            );

            $telegramMessageId = isset($result['message_id']) ? (string) $result['message_id'] : null;
            $delivery->update([
                'status' => 'sent',
                'telegram_message_id' => $telegramMessageId,
                'parse_mode' => $this->parseModeFromResult($result),
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);

            if ($telegramMessageId) {
                $message->update(['external_message_id' => $telegramMessageId]);
            }
        } catch (\Throwable $e) {
            $this->markFailed($delivery, $e);
        }
    }

    public function editMessage(Message $message): void
    {
        $channel = $message->channel;
        $setting = $this->settingFor($channel);
        if (! $channel || ! $setting || ! $message->external_message_id) {
            return;
        }

        $target = $this->targetFromChannel($channel);
        $text = TelegramService::markdownToTelegramHtml($message->content);
        $delivery = $this->createDelivery($message, $setting, $target, [
            'method' => 'editMessageText',
            'message_id' => $message->external_message_id,
            'text' => $text,
        ]);

        try {
            $this->bindWorkspace($channel);
            $result = app(TelegramService::class)->editMessageText(
                $target['chat_id'],
                (int) $message->external_message_id,
                $text,
            );

            $delivery->update([
                'status' => 'sent',
                'parse_mode' => $this->parseModeFromResult($result),
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($delivery, $e);
        }
    }

    public function deleteMessage(Message $message): void
    {
        $this->messageAction($message, 'deleteMessage', fn (TelegramService $telegram, array $target) => $telegram->deleteMessage(
            $target['chat_id'],
            (int) $message->external_message_id,
        ));
    }

    public function pinMessage(Message $message): void
    {
        $this->messageAction($message, 'pinChatMessage', fn (TelegramService $telegram, array $target) => $telegram->pinChatMessage(
            $target['chat_id'],
            (int) $message->external_message_id,
        ));
    }

    public function addReaction(Message $message, string $emoji): void
    {
        $this->messageAction($message, 'setMessageReaction', fn (TelegramService $telegram, array $target) => $telegram->setMessageReaction(
            $target['chat_id'],
            (int) $message->external_message_id,
            $emoji,
        ), ['emoji' => $emoji]);
    }

    /**
     * @param  callable(TelegramService, array{chat_id: string, topic_id: ?string, direct_messages_topic_id: ?string}): array<string, mixed>  $callback
     * @param  array<string, mixed>  $extraPayload
     */
    private function messageAction(Message $message, string $method, callable $callback, array $extraPayload = []): void
    {
        $channel = $message->channel;
        $setting = $this->settingFor($channel);
        if (! $channel || ! $setting || ! $message->external_message_id) {
            return;
        }

        $target = $this->targetFromChannel($channel);
        $delivery = $this->createDelivery($message, $setting, $target, [
            'method' => $method,
            'message_id' => $message->external_message_id,
            ...$extraPayload,
        ]);

        try {
            $this->bindWorkspace($channel);
            $result = $callback(app(TelegramService::class), $target);

            $delivery->update([
                'status' => 'sent',
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($delivery, $e);
        }
    }

    private function settingFor(?Channel $channel): ?IntegrationSetting
    {
        if (! $channel || $channel->external_provider !== 'telegram') {
            return null;
        }

        return IntegrationSetting::where('workspace_id', $channel->workspace_id)
            ->where('integration_id', 'telegram')
            ->where('enabled', true)
            ->default()
            ->first();
    }

    private function bindWorkspace(Channel $channel): void
    {
        if ($workspace = Workspace::find($channel->workspace_id)) {
            app()->instance('currentWorkspace', $workspace);
        }
    }

    /**
     * @return array{chat_id: string, topic_id: ?string, direct_messages_topic_id: ?string}
     */
    private function targetFromChannel(Channel $channel): array
    {
        $config = $channel->external_config ?? [];
        $chatId = (string) ($config['chat_id'] ?? $channel->external_id);
        $topicId = isset($config['topic_id']) ? (string) $config['topic_id'] : null;

        if (! isset($config['chat_id']) && str_contains($chatId, ':')) {
            [$chatId, $topicId] = explode(':', $chatId, 2);
        }

        return [
            'chat_id' => $chatId,
            'topic_id' => $topicId !== '' ? $topicId : null,
            'direct_messages_topic_id' => isset($config['direct_messages_topic_id'])
                ? (string) $config['direct_messages_topic_id']
                : null,
        ];
    }

    /**
     * @param  array{chat_id: string, topic_id: ?string, direct_messages_topic_id: ?string}  $target
     * @param  array<string, mixed>  $payload
     */
    private function createDelivery(Message $message, IntegrationSetting $setting, array $target, array $payload): TelegramDelivery
    {
        return TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $message->channel->workspace_id,
            'integration_setting_id' => $setting->id,
            'source_type' => Message::class,
            'source_id' => $message->id,
            'chat_id' => $target['chat_id'],
            'topic_id' => $target['topic_id'],
            'direct_messages_topic_id' => $target['direct_messages_topic_id'],
            'parse_mode' => 'HTML',
            'renderer_version' => 'telegram-outbound-sync:v1',
            'status' => 'pending',
            'request_payload' => $payload,
        ]);
    }

    private function markFailed(TelegramDelivery $delivery, \Throwable $e): void
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

        Log::error('Telegram outbound sync failed', [
            'delivery_id' => $delivery->id,
            'source_id' => $delivery->source_id,
            'retry_after' => $e instanceof TelegramRateLimitException ? $e->retryAfter : null,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function parseModeFromResult(array $result): string
    {
        return ($result['_opencompany_parse_mode_fallback'] ?? false) ? 'plain_text_fallback' : 'HTML';
    }
}

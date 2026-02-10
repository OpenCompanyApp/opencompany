<?php

namespace App\Agents\Tools\Telegram;

use App\Models\Channel;
use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\TelegramService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SendTelegramNotification implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Send a notification message to a Telegram chat. Use this for alerts, updates, or important notifications that should be delivered via Telegram.';
    }

    public function handle(Request $request): string
    {
        try {
            $channelId = $request['channelId'];
            $content = $request['content'];

            // Check channel access permission
            $channelAccess = $this->permissionService->canAccessChannel($this->agent, $channelId);
            if (!$channelAccess['allowed']) {
                return "Error: You do not have permission to send notifications to this channel.";
            }

            $channel = Channel::where('id', $channelId)
                ->where('external_provider', 'telegram')
                ->first();

            if (!$channel) {
                return "Error: Channel '{$channelId}' is not a Telegram channel.";
            }

            $chatId = $channel->external_id;
            if (!$chatId) {
                return "Error: Channel has no Telegram chat ID configured.";
            }

            $telegram = app(TelegramService::class);
            if (!$telegram->isConfigured()) {
                return "Error: Telegram integration is not configured.";
            }

            $agentName = htmlspecialchars($this->agent->name, ENT_QUOTES, 'UTF-8');
            $formattedContent = TelegramService::markdownToTelegramHtml($content);
            $text = "<b>📢 {$agentName}</b>\n{$formattedContent}";
            $telegram->sendMessage($chatId, $text);

            return "Notification sent successfully to Telegram channel '{$channel->name}'.";
        } catch (\Throwable $e) {
            return "Error sending Telegram notification: {$e->getMessage()}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'channelId' => $schema
                ->string()
                ->description('The UUID of a Telegram-linked external channel to send the notification to.')
                ->required(),
            'content' => $schema
                ->string()
                ->description('The notification message content to send.')
                ->required(),
        ];
    }
}

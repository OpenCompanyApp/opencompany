<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Jobs\AgentRespondJob;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\IntegrationSetting;
use App\Models\Message;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Services\ApprovalExecutionService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        // Verify webhook secret
        $setting = IntegrationSetting::where('integration_id', 'telegram')
            ->where('enabled', true)
            ->first();

        if (!$setting) {
            abort(404);
        }

        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        $expectedSecret = $setting->getConfigValue('webhook_secret');

        if (!$expectedSecret || $secretToken !== $expectedSecret) {
            abort(403);
        }

        $update = $request->all();

        try {
            if (isset($update['message'])) {
                $this->handleMessage($update['message'], $setting);
            } elseif (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            }
        } catch (\Throwable $e) {
            Log::error('Telegram webhook error', [
                'error' => $e->getMessage(),
                'update_id' => $update['update_id'] ?? null,
            ]);
        }

        return response('ok', 200);
    }

    /**
     * Handle an incoming text message from Telegram.
     */
    private function handleMessage(array $message, IntegrationSetting $setting): void
    {
        $chatId = (string) ($message['chat']['id'] ?? null);
        $text = $message['text'] ?? null;
        $from = $message['from'] ?? [];
        $telegramMessageId = $message['message_id'] ?? null;

        if (!$chatId || !$text) {
            return;
        }

        // Deduplication: atomic lock prevents race condition when Telegram retries
        if ($telegramMessageId) {
            $lockKey = "telegram_msg:{$chatId}:{$telegramMessageId}";
            $lock = Cache::lock($lockKey, 300); // 5 min TTL
            if (!$lock->get()) {
                Log::info('Telegram dedup: skipping duplicate', ['message_id' => $telegramMessageId]);
                return;
            }
            Log::info('Telegram: processing message', ['message_id' => $telegramMessageId]);
        }

        // Handle /start command — always allowed, returns user ID
        if ($text === '/start') {
            $telegram = app(TelegramService::class);
            $userId = $from['id'] ?? 'unknown';
            $telegram->sendMessage($chatId,
                "Welcome to OpenCompany!\n\n"
                . "Your Telegram User ID is: <code>{$userId}</code>\n\n"
                . "Share this with your administrator to get access."
            );
            return;
        }

        // Check allowed users whitelist
        $allowedUsers = $setting->getConfigValue('allowed_telegram_users', []);
        if (!empty($allowedUsers)) {
            $telegramUserId = (string) ($from['id'] ?? 0);
            if (!in_array($telegramUserId, $allowedUsers)) {
                $telegram = app(TelegramService::class);
                $telegram->sendMessage($chatId, 'You are not authorized to use this bot. Contact your administrator.');
                return;
            }
        }

        // Find or create the external channel for this chat
        $channel = Channel::firstOrCreate(
            ['external_provider' => 'telegram', 'external_id' => $chatId],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Telegram: ' . ($from['first_name'] ?? 'Unknown'),
                'type' => 'external',
                'external_config' => [
                    'telegram_user_id' => $from['id'] ?? null,
                    'username' => $from['username'] ?? null,
                    'first_name' => $from['first_name'] ?? null,
                ],
            ]
        );

        // Resolve the real system user or create an ephemeral shadow
        $user = $this->resolveTelegramUser($from);

        // Ensure the user is a channel member
        ChannelMember::firstOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $user->id],
            ['id' => Str::uuid()->toString(), 'joined_at' => now()]
        );

        // Ensure the default agent is a channel member
        $defaultAgentId = $setting->getConfigValue('default_agent_id');
        $agent = $defaultAgentId ? User::find($defaultAgentId) : null;

        if (!$agent) {
            // Fallback: find first available agent
            $agent = User::where('type', 'agent')->first();
        }

        if ($agent) {
            ChannelMember::firstOrCreate(
                ['channel_id' => $channel->id, 'user_id' => $agent->id],
                ['id' => Str::uuid()->toString(), 'joined_at' => now()]
            );
        }

        // Create the message
        $internalMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $text,
            'channel_id' => $channel->id,
            'author_id' => $user->id,
            'timestamp' => now(),
            'source' => 'telegram',
        ]);

        broadcast(new MessageSent($internalMessage));
        $channel->update(['last_message_at' => now()]);

        // Show typing indicator and dispatch agent response
        if ($agent) {
            $telegram = app(TelegramService::class);
            try {
                $telegram->sendChatAction($chatId);
            } catch (\Throwable) {
                // Non-critical, continue
            }

            AgentRespondJob::dispatch($internalMessage, $agent, $channel->id)->afterResponse();
        }
    }

    /**
     * Handle a callback query (approval button press).
     */
    private function handleCallbackQuery(array $callbackQuery): void
    {
        $data = $callbackQuery['data'] ?? '';
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? null);
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $callbackQueryId = $callbackQuery['id'] ?? null;

        if (!$callbackQueryId) {
            return;
        }

        $telegram = app(TelegramService::class);

        // Parse: "approve:{uuid}" or "reject:{uuid}"
        if (!preg_match('/^(approve|reject):(.+)$/', $data, $matches)) {
            $telegram->answerCallbackQuery($callbackQueryId, 'Unknown action.');
            return;
        }

        $action = $matches[1];
        $approvalId = $matches[2];
        $status = $action === 'approve' ? 'approved' : 'rejected';

        $approval = ApprovalRequest::find($approvalId);
        if (!$approval || $approval->status !== 'pending') {
            $telegram->answerCallbackQuery($callbackQueryId, 'This approval has already been decided.');
            return;
        }

        // Resolve responder — linked system user or ephemeral shadow
        $from = $callbackQuery['from'] ?? [];
        $responder = $this->resolveTelegramUser($from);
        $responderId = $responder->id;

        // Update the approval
        $approval->update([
            'status' => $status,
            'responded_by_id' => $responderId,
            'responded_at' => now(),
        ]);

        // Execute the post-approval logic
        $agent = $approval->requester;
        $agentIsWaiting = $agent
            && $agent->type === 'agent'
            && $agent->awaiting_approval_id === $approval->id;

        $approvalService = app(ApprovalExecutionService::class);

        if ($status === 'approved' && $approval->tool_execution_context) {
            $approvalService->executeApprovedTool($approval, $agentIsWaiting);
        } elseif ($status === 'rejected' && $agentIsWaiting) {
            $approvalService->handleRejectedTool($approval);
        }

        // Acknowledge the button press
        $telegram->answerCallbackQuery($callbackQueryId, ucfirst($status) . '!');

        // Update the message to show result and remove buttons
        if ($chatId && $messageId) {
            $responderName = $from['first_name'] ?? 'User';
            $statusEmoji = $status === 'approved' ? '✅' : '❌';

            try {
                $telegram->editMessageText(
                    $chatId,
                    $messageId,
                    "{$statusEmoji} <b>" . ucfirst($status) . "</b>\n\n"
                    . "<b>{$approval->title}</b>\n"
                    . "By: {$responderName}"
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to edit Telegram approval message', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Resolve the system user for a Telegram sender.
     * Checks for a linked user via external identities first,
     * then falls back to an ephemeral shadow user.
     */
    private function resolveTelegramUser(array $from): User
    {
        $telegramUserId = (string) ($from['id'] ?? 0);
        $firstName = $from['first_name'] ?? 'User';

        // Try to find a real user linked via external identity
        $linkedUser = UserExternalIdentity::resolveUser('telegram', $telegramUserId);
        if ($linkedUser) {
            return $linkedUser;
        }

        // Fall back to ephemeral shadow user
        return User::firstOrCreate(
            ['email' => "telegram-{$telegramUserId}@external.opencompany"],
            [
                'id' => Str::uuid()->toString(),
                'name' => "{$firstName} (Telegram)",
                'type' => 'human',
                'presence' => 'online',
                'password' => bcrypt(Str::random(32)),
                'is_ephemeral' => true,
            ]
        );
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Jobs\AgentRespondJob;
use App\Jobs\CompactConversationJob;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\ConversationSummary;
use App\Models\IntegrationSetting;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Models\Workspace;
use App\Services\ApprovalExecutionService;
use App\Services\TelegramService;
use App\Services\WorkspaceStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Legacy Telegram webhook handler.
 *
 * New chat adapters can flow through ChatWebhookController/Chatogrator, but this
 * controller still owns the direct Telegram path. It resolves workspace by
 * webhook secret, creates external channels/messages, handles approval buttons,
 * and queues agent replies without relying on normal web middleware.
 */
class TelegramWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        // Webhooks do not include a workspace ID. Match the Telegram secret
        // across enabled settings, then bind that workspace for the rest of the
        // request. The secret is encrypted, so this comparison happens in PHP.
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

        $setting = IntegrationSetting::where('integration_id', 'telegram')
            ->where('enabled', true)
            ->get()
            ->first(function ($s) use ($secretToken) {
                return $s->getConfigValue('webhook_secret') === $secretToken;
            });

        if (! $setting || ! $secretToken) {
            abort(403);
        }

        // Set workspace context from the integration's workspace because this
        // route bypasses ResolveWorkspace.
        if ($setting->workspace_id) {
            $workspace = Workspace::find($setting->workspace_id);
            if ($workspace) {
                app()->instance('currentWorkspace', $workspace);
            }
        }

        $update = $request->all();

        try {
            if (isset($update['message'])) {
                $this->handleMessage($update['message'], $setting);
            } elseif (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query'], $setting);
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
     *
     * @param  array<string, mixed>  $message
     */
    private function handleMessage(array $message, IntegrationSetting $setting): void
    {
        $chatId = (string) ($message['chat']['id'] ?? null);
        $text = $message['text'] ?? null;
        $from = $message['from'] ?? [];
        $telegramMessageId = $message['message_id'] ?? null;

        if (! $chatId || ! $text) {
            return;
        }

        // Deduplicate Telegram retries. Telegram can resend the same update if
        // the webhook response is slow, and we must not create duplicate chat
        // messages or duplicate agent jobs.
        if ($telegramMessageId) {
            $lockKey = "telegram_msg:{$chatId}:{$telegramMessageId}";
            $lock = Cache::lock($lockKey, 300); // 5 min TTL
            if (! $lock->get()) {
                Log::info('Telegram dedup: skipping duplicate', ['message_id' => $telegramMessageId]);

                return;
            }
            Log::info('Telegram: processing message', ['message_id' => $telegramMessageId]);
        }

        // /start is always allowed so users can discover the provider-native ID
        // an admin needs for allowlisting/linking.
        if ($text === '/start') {
            $telegram = app(TelegramService::class);
            $userId = $from['id'] ?? 'unknown';
            $telegram->sendMessage($chatId,
                "Welcome to OpenCompany!\n\n"
                ."Your Telegram User ID is: <code>{$userId}</code>\n\n"
                .'Share this with your administrator to get access.'
            );

            return;
        }

        // Handle /compact command — compact conversation memory
        if (str_starts_with($text, '/compact')) {
            $this->handleCompactCommand($chatId, $setting);

            return;
        }

        // Handle /status command — show workspace overview
        if ($text === '/status') {
            $this->handleStatusCommand($chatId, $setting);

            return;
        }

        // Enforce allowlist before creating channel/message records for the
        // sender. Unauthorized users should not populate workspace history.
        $allowedUsers = $setting->getConfigValue('allowed_telegram_users', []);
        if (! empty($allowedUsers)) {
            $telegramUserId = (string) ($from['id'] ?? 0);
            if (! in_array($telegramUserId, $allowedUsers)) {
                $telegram = app(TelegramService::class);
                $telegram->sendMessage($chatId, 'You are not authorized to use this bot. Contact your administrator.');

                return;
            }
        }

        // External channel identity is workspace-scoped because the same
        // Telegram chat ID can theoretically appear in different workspaces.
        $channel = Channel::firstOrCreate(
            ['external_provider' => 'telegram', 'external_id' => $chatId, 'workspace_id' => $setting->workspace_id],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Telegram: '.($from['first_name'] ?? 'Unknown'),
                'type' => 'external',
                'workspace_id' => $setting->workspace_id,
                'external_config' => [
                    'telegram_user_id' => $from['id'] ?? null,
                    'username' => $from['username'] ?? null,
                    'first_name' => $from['first_name'] ?? null,
                ],
            ]
        );

        // Resolve the real system user or create an ephemeral shadow that can be
        // merged later via IntegrationController::linkExternalUser.
        $user = $this->resolveTelegramUser($from);

        // Ensure the user is a channel member
        ChannelMember::firstOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $user->id],
            ['id' => Str::uuid()->toString(), 'joined_at' => now()]
        );

        // Ensure the default agent is a channel member
        $defaultAgentId = $setting->getConfigValue('default_agent_id');
        $agent = $defaultAgentId ? User::find($defaultAgentId) : null;

        if (! $agent) {
            // Fallback should ideally be workspace-scoped. Preserve existing
            // behavior for now, but keep this visible for a future tightening.
            $agent = User::where('type', 'agent')->first();
        }

        if ($agent) {
            ChannelMember::firstOrCreate(
                ['channel_id' => $channel->id, 'user_id' => $agent->id],
                ['id' => Str::uuid()->toString(), 'joined_at' => now()]
            );
        }

        // Reply threading is best effort. If the referenced Telegram message
        // was never stored locally, the inbound message is still accepted.
        $replyToId = null;
        if (isset($message['reply_to_message']['message_id'])) {
            $replyToId = Message::where('external_message_id', (string) $message['reply_to_message']['message_id'])
                ->where('channel_id', $channel->id)
                ->value('id');
        }

        // Create the message
        $internalMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => $text,
            'channel_id' => $channel->id,
            'author_id' => $user->id,
            'reply_to_id' => $replyToId,
            'timestamp' => now(),
            'source' => 'telegram',
            'external_message_id' => $telegramMessageId ? (string) $telegramMessageId : null,
        ]);

        broadcast(new MessageSent($internalMessage));
        $channel->update(['last_message_at' => now()]);

        // Queue the agent response after storing and broadcasting the inbound
        // message so retries can find a durable trigger message/task.
        if ($agent) {
            $telegram = app(TelegramService::class);
            try {
                $telegram->sendChatAction($chatId);
            } catch (\Throwable) {
                // Non-critical, continue
            }

            $task = Task::createPending($internalMessage, $agent, $channel->id);
            AgentRespondJob::dispatch($internalMessage, $agent, $channel->id, $task->id);
        }
    }

    /**
     * Handle the /compact command — compact conversation memory for this chat.
     *
     * Dispatches compaction as a queued job to avoid hitting the 30-second
     * webhook timeout (the LLM summarization call can be slow).
     */
    private function handleCompactCommand(string $chatId, IntegrationSetting $setting): void
    {
        $telegram = app(TelegramService::class);

        $channel = Channel::where('external_provider', 'telegram')
            ->where('external_id', $chatId)
            ->first();

        if (! $channel) {
            $telegram->sendMessage($chatId, 'No conversation history in this channel yet.');

            return;
        }

        // Compact the most relevant agent for this external channel: prefer the
        // configured default, otherwise an agent member already in the channel.
        $defaultAgentId = $setting->getConfigValue('default_agent_id');
        $agent = $defaultAgentId ? User::find($defaultAgentId) : null;

        if (! $agent || $agent->type !== 'agent') {
            $agentIds = ChannelMember::where('channel_id', $channel->id)->pluck('user_id');
            $agent = User::where('type', 'agent')->whereIn('id', $agentIds)->first();
        }

        if (! $agent) {
            $telegram->sendMessage($chatId, 'No agent found in this channel.');

            return;
        }

        $telegram->sendMessage($chatId, '🧠 Compacting conversation memory…');

        CompactConversationJob::dispatch($channel->id, $agent, $chatId);
    }

    /**
     * Handle the /status command — show workspace overview.
     */
    private function handleStatusCommand(string $chatId, IntegrationSetting $setting): void
    {
        $telegram = app(TelegramService::class);

        try {
            $status = app(WorkspaceStatusService::class)->gather($setting->workspace_id);

            $lines = ["<b>Workspace Status</b>\n"];

            // Status output is rendered as Telegram HTML, not Markdown.
            $lines[] = "🤖 <b>Agents</b>: {$status['agents_online']}/{$status['agents_total']} online";
            foreach ($status['agents'] as $a) {
                $icon = match ($a['status']) {
                    'working' => '🟢',
                    'idle' => '🟡',
                    default => '⚫',
                };
                $line = "   {$icon} {$a['name']} — {$a['status']}";
                if ($a['current_task']) {
                    $line .= ' · '.Str::limit($a['current_task'], 30);
                }
                $lines[] = $line;
            }

            // Tasks
            $lines[] = "\n📋 <b>Tasks</b>";
            $lines[] = "   Active: {$status['tasks_active']}";
            $lines[] = "   Completed today: {$status['tasks_today']}";
            $lines[] = "   Total completed: {$status['tasks_completed']}";

            // Messages
            $lines[] = "\n💬 <b>Messages</b>";
            $lines[] = "   Today: {$status['messages_today']}";
            $lines[] = "   Total: {$status['messages_total']}";

            // Include this conversation's compaction state when the chat has
            // already been bridged into an OpenCompany channel.
            $channel = Channel::where('external_provider', 'telegram')
                ->where('external_id', $chatId)
                ->first();

            if ($channel) {
                $summary = ConversationSummary::where('channel_id', $channel->id)
                    ->latest()
                    ->first();

                if ($summary) {
                    $lines[] = "\n🧠 <b>This conversation</b>";
                    $lines[] = "   Compactions: {$summary->compaction_count}";
                    $lines[] = '   Context tokens: ~'.number_format($summary->tokens_after);
                }
            }

            $lines[] = "\n🕐 ".now()->utc()->format('Y-m-d H:i').' UTC';

            $telegram->sendMessage($chatId, implode("\n", $lines));
        } catch (\Throwable $e) {
            Log::error('Telegram /status failed', ['error' => $e->getMessage()]);
            $telegram->sendMessage($chatId, 'Failed to get status: '.Str::limit($e->getMessage(), 100));
        }
    }

    /**
     * Handle a callback query (approval button press).
     *
     * @param  array<string, mixed>  $callbackQuery
     */
    private function handleCallbackQuery(array $callbackQuery, ?IntegrationSetting $setting = null): void
    {
        $data = $callbackQuery['data'] ?? '';
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? null);
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $callbackQueryId = $callbackQuery['id'] ?? null;

        if (! $callbackQueryId) {
            return;
        }

        $telegram = app(TelegramService::class);

        // Parse approval action IDs generated by approval message buttons.
        if (! preg_match('/^(approve|reject):(.+)$/', $data, $matches)) {
            try {
                $telegram->answerCallbackQuery($callbackQueryId, 'Unknown action.');
            } catch (\Throwable) {
            }

            return;
        }

        $action = $matches[1];
        $approvalId = $matches[2];
        $status = $action === 'approve' ? 'approved' : 'rejected';

        $approval = ApprovalRequest::find($approvalId);
        if (! $approval || $approval->status !== 'pending') {
            try {
                $telegram->answerCallbackQuery($callbackQueryId, 'This approval has already been decided.');
            } catch (\Throwable) {
            }

            return;
        }

        // Resolve responder to a local user so ApprovalRequest keeps an audit
        // trail even when the Telegram sender is not yet linked to a real user.
        $from = $callbackQuery['from'] ?? [];
        $responder = $this->resolveTelegramUser($from);
        $responderId = $responder->id;

        // Update the approval
        $approval->update([
            'status' => $status,
            'responded_by_id' => $responderId,
            'responded_at' => now(),
        ]);

        // Execute the approved tool/access flow after persisting the human
        // decision so failures still leave an auditable approval outcome.
        /** @var User|null $agent */
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

        // Acknowledge the button press, but ignore expired callback failures.
        try {
            $telegram->answerCallbackQuery($callbackQueryId, ucfirst($status).'!');
        } catch (\Throwable) {
        }

        // Edit the original approval message to show the final decision and
        // remove actionable buttons from the Telegram thread.
        if ($chatId && $messageId) {
            $responderName = $from['first_name'] ?? 'User';
            $statusEmoji = $status === 'approved' ? '✅' : '❌';

            try {
                $telegram->editMessageText(
                    $chatId,
                    $messageId,
                    "{$statusEmoji} <b>".ucfirst($status)."</b>\n\n"
                    ."<b>{$approval->title}</b>\n"
                    ."By: {$responderName}"
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
     *
     * @param  array<string, mixed>  $from
     */
    private function resolveTelegramUser(array $from): User
    {
        $telegramUserId = (string) ($from['id'] ?? 0);
        $firstName = $from['first_name'] ?? 'User';

        // Try to find a real user linked via external identity first; this keeps
        // messages authored by the actual OpenCompany user after linking.
        $linkedUser = UserExternalIdentity::resolveUser('telegram', $telegramUserId);
        if ($linkedUser) {
            return $linkedUser;
        }

        // Shadow users preserve message history until an admin links the
        // provider identity to a real user.
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

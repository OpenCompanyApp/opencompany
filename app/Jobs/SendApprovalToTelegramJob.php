<?php

namespace App\Jobs;

use App\Domain\Chat\Telegram\Application\TelegramApprovalRenderer;
use App\Models\ApprovalRequest;
use App\Models\IntegrationSetting;
use App\Models\TelegramDelivery;
use App\Models\TelegramInteraction;
use App\Models\Workspace;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sends an approval request to a Telegram chat with inline decision buttons.
 *
 * Callback handling lives in the webhook controller; this job only formats the
 * pending approval safely and delivers it through the workspace Telegram config.
 */
class SendApprovalToTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 15;

    public function __construct(
        private ApprovalRequest $approval,
        private string $chatId,
    ) {}

    public function handle(): void
    {
        $this->approval->loadMissing('requester', 'channel');
        $setting = $this->telegramSetting();

        if (! $setting) {
            Log::warning('Cannot send approval to Telegram without workspace Telegram setting', [
                'approval_id' => $this->approval->id,
                'chat_id' => $this->chatId,
            ]);

            return;
        }

        $workspace = Workspace::find($setting->workspace_id);
        if ($workspace) {
            app()->instance('currentWorkspace', $workspace);
        }

        $text = app(TelegramApprovalRenderer::class)->pendingHtml($this->approval, $workspace);

        $inspect = $this->interaction($setting, 'inspect');
        $approve = $this->interaction($setting, 'approve');
        $reject = $this->interaction($setting, 'reject');

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
            'workspace_id' => $setting->workspace_id,
            'integration_setting_id' => $setting->id,
            'source_type' => ApprovalRequest::class,
            'source_id' => $this->approval->id,
            'chat_id' => $this->chatId,
            'parse_mode' => 'HTML',
            'renderer_version' => TelegramApprovalRenderer::PENDING_VERSION,
            'status' => 'pending',
            'request_payload' => [
                'method' => 'sendMessage',
                'approval_id' => $this->approval->id,
                'buttons' => ['inspect', 'approve', 'reject'],
            ],
        ]);

        try {
            $result = app(TelegramService::class)->sendMessage($this->chatId, $text, $replyMarkup);
            $delivery->update([
                'status' => 'sent',
                'telegram_message_id' => isset($result['message_id']) ? (string) $result['message_id'] : null,
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

            Log::error('Failed to send approval to Telegram', [
                'approval_id' => $this->approval->id,
                'chat_id' => $this->chatId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function telegramSetting(): ?IntegrationSetting
    {
        $workspaceId = $this->approval->channel?->workspace_id
            ?: $this->approval->requester?->workspace_id;

        if (! $workspaceId) {
            return null;
        }

        return IntegrationSetting::where('workspace_id', $workspaceId)
            ->where('integration_id', 'telegram')
            ->where('enabled', true)
            ->default()
            ->first();
    }

    private function interaction(IntegrationSetting $setting, string $action): TelegramInteraction
    {
        return TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $setting->workspace_id,
            'integration_setting_id' => $setting->id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'approval',
            'target_type' => ApprovalRequest::class,
            'target_id' => $this->approval->id,
            'payload' => [
                'action' => $action,
                'approval_id' => $this->approval->id,
            ],
            'allowed_actor_rule' => [
                'type' => 'workspace_member_or_linked_telegram_user',
            ],
            'expires_at' => now()->addMinutes(30),
        ]));
    }
}

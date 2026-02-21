<?php

namespace App\Jobs;

use App\Models\ApprovalRequest;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        $this->approval->loadMissing('requester');

        $requesterName = htmlspecialchars($this->approval->requester->name ?? 'Unknown', ENT_QUOTES, 'UTF-8');
        $type = ucfirst($this->approval->type ?? 'action');
        $title = htmlspecialchars($this->approval->title, ENT_QUOTES, 'UTF-8');

        $text = "🔔 <b>Approval Required</b>\n\n"
            . "<b>{$title}</b>\n"
            . "Type: {$type}\n"
            . "From: {$requesterName}\n";

        if ($this->approval->description) {
            $text .= "\n" . htmlspecialchars($this->approval->description, ENT_QUOTES, 'UTF-8');
        }

        $replyMarkup = [
            'inline_keyboard' => [[
                ['text' => '✅ Approve', 'callback_data' => "approve:{$this->approval->id}"],
                ['text' => '❌ Reject', 'callback_data' => "reject:{$this->approval->id}"],
            ]],
        ];

        try {
            app(TelegramService::class)->sendMessage($this->chatId, $text, $replyMarkup);
        } catch (\Throwable $e) {
            Log::error('Failed to send approval to Telegram', [
                'approval_id' => $this->approval->id,
                'chat_id' => $this->chatId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

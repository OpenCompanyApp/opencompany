<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Durable receipt for one Telegram update.
 *
 * Telegram does not let bots refetch arbitrary historical updates. Persisting
 * each received update before dispatch gives OpenCompany idempotency, replay
 * diagnostics, and a trustworthy audit trail for inbound messages, callbacks,
 * reactions, edits, topic changes, and future Telegram update types.
 */
class TelegramUpdateReceipt extends Model
{
    use BelongsToWorkspace;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'integration_setting_id',
        'update_id',
        'update_type',
        'payload_hash',
        'payload',
        'diagnostics',
        'status',
        'telegram_conversation_id',
        'error_class',
        'error_message',
        'retry_count',
        'duplicate_count',
        'last_duplicate_at',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'diagnostics' => 'array',
            'duplicate_count' => 'integer',
            'last_duplicate_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<IntegrationSetting, $this> */
    public function integrationSetting(): BelongsTo
    {
        return $this->belongsTo(IntegrationSetting::class);
    }

    /** @return BelongsTo<TelegramConversation, $this> */
    public function telegramConversation(): BelongsTo
    {
        return $this->belongsTo(TelegramConversation::class);
    }
}

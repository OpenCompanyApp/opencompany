<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Outbound Telegram delivery attempt tied to an OpenCompany source object.
 *
 * Delivery rows are the reconciliation layer between local messages, tasks,
 * approvals, digests, and Telegram message IDs. They also preserve renderer
 * version and provider errors so failed sends can be diagnosed and retried
 * without guessing what payload was attempted.
 */
class TelegramDelivery extends Model
{
    use BelongsToWorkspace;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'integration_setting_id',
        'source_type',
        'source_id',
        'chat_id',
        'topic_id',
        'direct_messages_topic_id',
        'telegram_message_id',
        'media_group_id',
        'parse_mode',
        'renderer_version',
        'status',
        'previous_delivery_id',
        'request_payload',
        'response_payload',
        'provider_error_code',
        'provider_error_message',
        'attempts',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<IntegrationSetting, $this> */
    public function integrationSetting(): BelongsTo
    {
        return $this->belongsTo(IntegrationSetting::class);
    }

    /** @return BelongsTo<TelegramDelivery, $this> */
    public function previousDelivery(): BelongsTo
    {
        return $this->belongsTo(TelegramDelivery::class, 'previous_delivery_id');
    }
}

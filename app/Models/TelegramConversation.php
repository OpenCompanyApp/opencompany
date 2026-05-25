<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Telegram chat/topic lane mapped to an OpenCompany channel.
 *
 * Telegram chat IDs, forum topic IDs, private-topic IDs, and direct-message
 * topic IDs are provider-native routing identifiers. OpenCompany stores them
 * separately from Channel so multiple Telegram lanes can safely map into normal
 * workspace conversations without losing provider-specific send/edit metadata.
 */
class TelegramConversation extends Model
{
    use BelongsToWorkspace;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'integration_setting_id',
        'chat_id',
        'chat_type',
        'title',
        'username',
        'topic_id',
        'direct_messages_topic_id',
        'channel_id',
        'default_agent_id',
        'mode',
        'observed_context_enabled',
        'notification_policy',
        'last_seen_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'observed_context_enabled' => 'boolean',
            'notification_policy' => 'array',
            'last_seen_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<IntegrationSetting, $this> */
    public function integrationSetting(): BelongsTo
    {
        return $this->belongsTo(IntegrationSetting::class);
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function defaultAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_agent_id');
    }
}

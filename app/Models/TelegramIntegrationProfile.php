<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Workspace-local Telegram bot profile and health snapshot.
 *
 * This model is OpenCompany's durable record of the connected Telegram bot. It
 * does not own secrets; encrypted token/secret material stays on the related
 * IntegrationSetting. The profile stores provider capabilities, webhook
 * posture, command/profile sync status, and default runtime policy so setup and
 * diagnostics can be inspected without calling Telegram on every page load.
 */
class TelegramIntegrationProfile extends Model
{
    use BelongsToWorkspace;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'integration_setting_id',
        'bot_id',
        'bot_username',
        'capabilities',
        'webhook_url',
        'webhook_secret_fingerprint',
        'allowed_updates',
        'command_sync_status',
        'profile_sync_status',
        'pending_update_count',
        'default_mode',
        'default_agent_id',
        'notification_policy',
        'health_status',
        'last_health_error',
        'last_health_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'allowed_updates' => 'array',
            'notification_policy' => 'array',
            'last_health_checked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<IntegrationSetting, $this> */
    public function integrationSetting(): BelongsTo
    {
        return $this->belongsTo(IntegrationSetting::class);
    }

    /** @return BelongsTo<User, $this> */
    public function defaultAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_agent_id');
    }
}

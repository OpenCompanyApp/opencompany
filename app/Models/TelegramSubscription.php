<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification or digest subscription for a Telegram user, chat, or topic.
 *
 * Subscriptions keep alert routing outside hardcoded command logic. Later
 * phases can add task, approval, automation, digest, and workload routing while
 * reusing this per-scope policy record.
 */
class TelegramSubscription extends Model
{
    use BelongsToWorkspace;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'integration_setting_id',
        'scope_type',
        'scope_id',
        'event_type',
        'severity',
        'filters',
        'schedule',
        'timezone',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<IntegrationSetting, $this> */
    public function integrationSetting(): BelongsTo
    {
        return $this->belongsTo(IntegrationSetting::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Workspace-owned inbound webhook endpoint.
 *
 * These records back the Integrations page webhook UI. They intentionally only
 * own endpoint metadata, secret verification, and receipt diagnostics; delivery
 * into agents/channels/tasks is a separate workflow layer so unauthenticated
 * vendor callbacks cannot mutate workspace data without an explicit processor.
 *
 * @property string $workspace_id
 * @property string $name
 * @property bool $enabled
 * @property string $target_type
 * @property string|null $target_id
 * @property string $secret
 * @property array<string, mixed>|null $last_payload
 */
class IntegrationWebhook extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'name',
        'enabled',
        'target_type',
        'target_id',
        'secret',
        'last_triggered_at',
        'call_count',
        'last_payload',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'secret' => 'encrypted',
            'last_triggered_at' => 'datetime',
            'call_count' => 'integer',
            'last_payload' => 'encrypted:array',
        ];
    }
}

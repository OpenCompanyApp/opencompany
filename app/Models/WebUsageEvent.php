<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Durable audit row for app-owned web search/fetch activity.
 *
 * Rows intentionally store routing and safety metadata only. Response bodies,
 * snippets, raw HTML, and provider credentials are excluded so this table can
 * support diagnostics and accounting without becoming a content archive.
 */
class WebUsageEvent extends Model
{
    use BelongsToWorkspace;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'agent_id',
        'user_id',
        'capability',
        'provider',
        'request_hash',
        'query',
        'url',
        'final_url',
        'status_code',
        'content_type',
        'result_count',
        'bytes',
        'cache_hit',
        'success',
        'error',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'cache_hit' => 'boolean',
            'success' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}

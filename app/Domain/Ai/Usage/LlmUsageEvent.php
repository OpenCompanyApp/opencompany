<?php

namespace App\Domain\Ai\Usage;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Normalized ledger row for one LLM generation.
 *
 * Tasks keep human-visible runtime results. This table owns accounting facts:
 * token counts, requested/resolved model identity, estimated catalog cost, and
 * provider-reported actual cost when an API exposes it.
 */
class LlmUsageEvent extends Model
{
    use BelongsToWorkspace;
    use HasUuids;

    protected $table = 'llm_usage_events';

    protected $fillable = [
        'workspace_id',
        'agent_id',
        'task_id',
        'purpose',
        'status',
        'requested_provider',
        'requested_model',
        'resolved_provider',
        'resolved_model',
        'provider_generation_id',
        'prompt_tokens',
        'completion_tokens',
        'cache_read_tokens',
        'cache_write_tokens',
        'reasoning_tokens',
        'tool_calls_count',
        'generation_time_ms',
        'estimated_cost_usd',
        'actual_cost_usd',
        'cost_source',
        'raw_usage',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cache_read_tokens' => 'integer',
            'cache_write_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'tool_calls_count' => 'integer',
            'generation_time_ms' => 'integer',
            'estimated_cost_usd' => 'decimal:8',
            'actual_cost_usd' => 'decimal:8',
            'raw_usage' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}

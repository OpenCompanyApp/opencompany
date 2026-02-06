<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSettings extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'agent_config_id',
        'behavior_mode',
        'security_mode',
        'ask_mode',
        'cost_limit_daily',
        'max_tokens_per_request',
        'reserve_tokens',
        'reserve_tokens_floor',
        'keep_recent_tokens',
        'soft_threshold_tokens',
        'pruning_ttl_minutes',
        'auto_allow_skills',
        'reset_policy',
    ];

    protected function casts(): array
    {
        return [
            'cost_limit_daily' => 'decimal:2',
            'max_tokens_per_request' => 'integer',
            'reserve_tokens' => 'integer',
            'reserve_tokens_floor' => 'integer',
            'keep_recent_tokens' => 'integer',
            'soft_threshold_tokens' => 'integer',
            'pruning_ttl_minutes' => 'integer',
            'auto_allow_skills' => 'boolean',
            'reset_policy' => 'array',
        ];
    }

    public function toArray()
    {
        $array = parent::toArray();

        $array['agentConfigId'] = $this->agent_config_id;
        $array['behaviorMode'] = $this->behavior_mode;
        $array['securityMode'] = $this->security_mode;
        $array['askMode'] = $this->ask_mode;
        $array['costLimitDaily'] = $this->cost_limit_daily;
        $array['maxTokensPerRequest'] = $this->max_tokens_per_request;
        $array['reserveTokens'] = $this->reserve_tokens;
        $array['reserveTokensFloor'] = $this->reserve_tokens_floor;
        $array['keepRecentTokens'] = $this->keep_recent_tokens;
        $array['softThresholdTokens'] = $this->soft_threshold_tokens;
        $array['pruningTtlMinutes'] = $this->pruning_ttl_minutes;
        $array['autoAllowSkills'] = $this->auto_allow_skills;
        $array['resetPolicy'] = $this->reset_policy;

        return $array;
    }

    // Relationships

    public function agentConfiguration(): BelongsTo
    {
        return $this->belongsTo(AgentConfiguration::class, 'agent_config_id');
    }

    // Helpers

    public function isAutonomous(): bool
    {
        return $this->behavior_mode === 'autonomous';
    }

    public function isWithinCostLimit(float $dailySpend): bool
    {
        if ($this->cost_limit_daily === null) {
            return true;
        }

        return $dailySpend < (float) $this->cost_limit_daily;
    }
}

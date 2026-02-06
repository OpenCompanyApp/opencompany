<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AgentConfiguration extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'personality',
        'instructions',
        'identity',
        'tool_notes',
    ];

    protected function casts(): array
    {
        return [
            'identity' => 'array',
        ];
    }

    public function toArray()
    {
        $array = parent::toArray();

        $array['userId'] = $this->user_id;
        $array['toolNotes'] = $this->tool_notes;

        return $array;
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(AgentSettings::class, 'agent_config_id');
    }
}

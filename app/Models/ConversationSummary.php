<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationSummary extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'channel_id',
        'agent_id',
        'summary',
        'tokens_before',
        'tokens_after',
        'compaction_count',
        'flush_count',
        'messages_summarized',
        'last_message_id',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToWorkspace;

class ConversationSummary extends Model
{
    use HasUuids, BelongsToWorkspace;

    protected $fillable = [
        'id',
        'workspace_id',
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

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}

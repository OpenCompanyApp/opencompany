<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compacted memory state for one agent in one channel.
 *
 * The summary replaces older verbatim messages in prompts. Failure/circuit
 * fields prevent repeated failing compaction attempts from blocking normal
 * agent responses.
 */
class ConversationSummary extends Model
{
    use BelongsToWorkspace, HasUuids;

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
        'compaction_failure_count',
        'last_compaction_failed_at',
        'compaction_circuit_open_until',
        'last_compaction_error',
    ];

    protected $casts = [
        'last_compaction_failed_at' => 'datetime',
        'compaction_circuit_open_until' => 'datetime',
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

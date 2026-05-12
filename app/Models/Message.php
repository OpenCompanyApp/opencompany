<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chat message stored in an OpenCompany channel.
 *
 * Messages can originate from humans, agents, automations, delegation callbacks,
 * or external chat adapters. The source and external_message_id fields are used
 * by bridge/listener code to prevent echo loops and to sync edits/deletes.
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'content',
        'author_id',
        'channel_id',
        'reply_to_id',
        'is_approval_request',
        'approval_request_id',
        'is_pinned',
        'pinned_by_id',
        'pinned_at',
        'timestamp',
        'source',
        'external_message_id',
    ];

    protected function casts(): array
    {
        return [
            'is_approval_request' => 'boolean',
            'is_pinned' => 'boolean',
            'pinned_at' => 'datetime',
            'timestamp' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    /** @return HasMany<Message, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    /** @return BelongsTo<ApprovalRequest, $this> */
    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by_id');
    }

    /** @return HasMany<MessageReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    /** @return HasMany<MessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /**
     * Resolve a message by full UUID or short ID prefix (e.g. "a4d0ed").
     */
    public static function resolveByShortId(string $id): ?self
    {
        // Agent tools often refer to message IDs in shortened msg:abc123 form.
        // Prefer exact UUID lookup, then fall back to prefix matching.
        $id = preg_replace('/^msg:/', '', $id);

        return static::find($id)
            ?? static::where('id', 'LIKE', $id.'%')->first();
    }
}

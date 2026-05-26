<?php

namespace App\Models;

use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Human approval record for tool execution or access expansion.
 *
 * tool_execution_context stores the exact tool/scope parameters that may be
 * executed after approval. Treat it as sensitive runtime data: it explains what
 * the human approved and lets ApprovalExecutionService resume the waiting agent.
 *
 * @property array<string, mixed>|null $tool_execution_context
 * @property-read User|null $requester
 * @property-read User|null $respondedBy
 */
class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'type',
        'title',
        'description',
        'requester_id',
        'amount',
        'status',
        'responded_by_id',
        'responded_at',
        'tool_execution_context',
        'channel_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'responded_at' => 'datetime',
            'tool_execution_context' => 'array',
        ];
    }

    /**
     * Serialize approval records for APIs without exposing tool secrets.
     *
     * The raw `tool_execution_context` attribute remains available on the model
     * instance for ApprovalExecutionService. Only array/JSON presentation is
     * redacted so humans can inspect approval history safely.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        if (array_key_exists('title', $array)) {
            $array['title'] = self::redactSecretValue($array['title']);
        }
        if (array_key_exists('description', $array)) {
            $array['description'] = self::redactSecretValue($array['description']);
        }
        if (array_key_exists('tool_execution_context', $array)) {
            $array['tool_execution_context'] = self::redactSecretValue($array['tool_execution_context']);
        }

        return $array;
    }

    public static function redactSecretValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $item) {
                $keyString = (string) $key;
                $redacted[$key] = preg_match('/(secret|token|auth|api[_-]?key|authorization|password|credential|bearer|cookie|session)/i', $keyString) === 1
                    ? '[redacted]'
                    : self::redactSecretValue($item);
            }

            return $redacted;
        }

        if (is_string($value)) {
            $redacted = preg_replace('/\b(authorization\s*:\s*(?:bearer|basic)\s+)[^\r\n,;]+/i', '$1[redacted]', $value) ?? $value;
            $redacted = preg_replace('/\b(secret|token|auth|api[_-]?key|authorization|password|credential|bearer|cookie|session)(\s*[:=]\s*)([^\s,&;]+)/i', '$1$2[redacted]', $redacted) ?? $redacted;
            $redacted = preg_replace('/\b(bearer|basic)\s+([A-Za-z0-9._~+\/=-]+(?:\s+[A-Za-z0-9._~+\/=-]+)?)/i', '$1 [redacted]', $redacted) ?? $redacted;
            $redacted = preg_replace('/([?&](?:access_token|client_secret|secret|token|auth|api[_-]?key|authorization|password|credential|bearer|session)=)([^&\s]+)/i', '$1[redacted]', $redacted) ?? $redacted;

            if (preg_match('/(sk-[A-Za-z0-9_-]{12,}|[A-Za-z0-9_\/+=-]{32,})/', $redacted) === 1) {
                return '[redacted]';
            }

            return $redacted;
        }

        return $value;
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<User, $this> */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_id');
    }
}

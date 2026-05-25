<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Opaque Telegram callback token for buttons and structured choices.
 *
 * Telegram callback_data is user-controlled input once it leaves OpenCompany.
 * Store a short opaque token in Telegram and keep the real target/action data
 * here so every button press re-loads workspace, actor, expiry, and policy
 * state before mutating approvals, tasks, files, automations, or resources.
 */
class TelegramInteraction extends Model
{
    use BelongsToWorkspace;

    public const CHECKSUM_VERSION = 'v2';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'integration_setting_id',
        'token',
        'interaction_type',
        'target_type',
        'target_id',
        'payload',
        'allowed_actor_rule',
        'expires_at',
        'resolved_at',
        'resolved_by_id',
        'final_status',
        'payload_checksum',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'allowed_actor_rule' => 'array',
            'expires_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public static function newToken(): string
    {
        return 'tg_'.Str::random(24);
    }

    /**
     * Attach a versioned integrity checksum to a new interaction row.
     *
     * Telegram only receives the opaque token, but the server-side payload still
     * decides what a button press means. The checksum lets callback handling
     * detect accidental or malicious database-side mutation of target/action
     * data before any workspace state is changed.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function withPayloadChecksum(array $attributes): array
    {
        $rule = is_array($attributes['allowed_actor_rule'] ?? null)
            ? $attributes['allowed_actor_rule']
            : [];
        $rule['checksum_version'] = self::CHECKSUM_VERSION;
        $attributes['allowed_actor_rule'] = $rule;
        $attributes['payload_checksum'] = self::payloadChecksumFor($attributes);

        return $attributes;
    }

    public function hasValidPayloadChecksum(): bool
    {
        $rule = is_array($this->allowed_actor_rule) ? $this->allowed_actor_rule : [];
        if (($rule['checksum_version'] ?? null) !== self::CHECKSUM_VERSION) {
            return true;
        }

        if (! is_string($this->payload_checksum) || $this->payload_checksum === '') {
            return false;
        }

        return hash_equals($this->payload_checksum, self::payloadChecksumFor([
            'workspace_id' => $this->workspace_id,
            'integration_setting_id' => $this->integration_setting_id,
            'interaction_type' => $this->interaction_type,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'payload' => $this->payload,
            'allowed_actor_rule' => $this->allowed_actor_rule,
            'expires_at' => $this->expires_at,
        ]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function payloadChecksumFor(array $attributes): string
    {
        $material = [
            'workspace_id' => $attributes['workspace_id'] ?? null,
            'integration_setting_id' => $attributes['integration_setting_id'] ?? null,
            'interaction_type' => $attributes['interaction_type'] ?? null,
            'target_type' => $attributes['target_type'] ?? null,
            'target_id' => $attributes['target_id'] ?? null,
            'payload' => $attributes['payload'] ?? [],
            'allowed_actor_rule' => $attributes['allowed_actor_rule'] ?? [],
            'expires_at' => self::normalizeChecksumValue($attributes['expires_at'] ?? null),
        ];

        return hash_hmac(
            'sha256',
            json_encode(self::canonicalize($material), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            (string) config('app.key', 'opencompany'),
        );
    }

    private static function normalizeChecksumValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->getTimestamp();
        }

        return $value;
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $canonical = [];
        foreach ($value as $key => $item) {
            $canonical[$key] = self::canonicalize($item);
        }

        ksort($canonical);

        return $canonical;
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<IntegrationSetting, $this> */
    public function integrationSetting(): BelongsTo
    {
        return $this->belongsTo(IntegrationSetting::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}

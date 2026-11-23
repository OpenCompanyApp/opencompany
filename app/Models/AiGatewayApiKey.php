<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Workspace-scoped bearer key for OpenCompany's AI Gateway.
 *
 * Only a SHA-256 hash is stored. The plain-text key is returned once at
 * creation time and then used by external OpenAI-compatible clients.
 *
 * Every key (GIN-1203; ROADMAP Phase 0 P0 item 7):
 * - expires: `expires_at` is always set at creation, and a key without one
 *   never authenticates (fail closed);
 * - is scoped: `scopes.actions` lists the gateway operations it may call and
 *   `scopes.models` the model ids (or "*") it may use;
 * - is owned: `created_by_user_id` names the human who created it;
 * - is revoked, not deleted: `revoked_at` keeps the row for the audit trail.
 * Whether a call is allowed is decided by AuthorizationService
 * (`ai_gateway.invoke`), not here.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $name
 * @property string $key_hash
 * @property string $key_prefix
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property array<string, mixed>|null $scopes
 * @property string|null $created_by_user_id
 * @property Carbon|null $revoked_at
 */
class AiGatewayApiKey extends Model
{
    use BelongsToWorkspace;
    use HasUuids;

    protected $fillable = [
        'workspace_id',
        'name',
        'key_hash',
        'key_prefix',
        'last_used_at',
        'expires_at',
        'scopes',
        'created_by_user_id',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'scopes' => 'array',
        ];
    }

    /**
     * Create a key. Callers must pass an expiry; scopes default to every
     * operation and every model the workspace exposes (explicit "*").
     *
     * @param  array{actions?: list<string>, models?: list<string>}|null  $scopes
     * @return array{key: self, plainTextKey: string}
     */
    public static function generateKey(
        string $name,
        ?string $workspaceId = null,
        ?\DateTimeInterface $expiresAt = null,
        ?array $scopes = null,
        ?string $createdByUserId = null,
    ): array {
        $plainText = 'ocai_live_'.bin2hex(random_bytes(24));

        $key = static::create([
            'workspace_id' => $workspaceId ?? workspace()->id,
            'name' => $name,
            'key_hash' => hash('sha256', $plainText),
            'key_prefix' => substr($plainText, 0, 18),
            // Invariant: no open-ended keys. A caller that forgets the expiry
            // gets the configured default lifetime, never null.
            'expires_at' => $expiresAt ?? now()->addDays(max(1, (int) config('authorization.ai_gateway.default_key_days', 90))),
            'scopes' => $scopes ?? self::fullScopes(),
            'created_by_user_id' => $createdByUserId,
        ]);

        return ['key' => $key, 'plainTextKey' => $plainText];
    }

    public static function findByPlainText(string $plainText): ?self
    {
        return static::where('key_hash', hash('sha256', $plainText))->first();
    }

    /**
     * Every operation and every exposed model.
     *
     * @return array{actions: list<string>, models: list<string>}
     */
    public static function fullScopes(): array
    {
        return ['actions' => ['models.list', 'chat.completions', 'embeddings'], 'models' => ['*']];
    }

    /**
     * Scopes as lists of strings (missing parts are empty: fail closed).
     *
     * @return array{actions: list<string>, models: list<string>}
     */
    public function normalizedScopes(): array
    {
        $scopes = is_array($this->scopes) ? $this->scopes : [];

        return [
            'actions' => array_values(array_map('strval', (array) ($scopes['actions'] ?? []))),
            'models' => array_values(array_map('strval', (array) ($scopes['models'] ?? []))),
        ];
    }

    /** Expired, or never given an expiry (fail closed). */
    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function getMaskedKeyAttribute(): string
    {
        return $this->key_prefix.str_repeat('*', 28).'...';
    }
}

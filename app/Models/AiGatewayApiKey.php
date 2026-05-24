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
 * @property string $id
 * @property string $workspace_id
 * @property string $name
 * @property string $key_hash
 * @property string $key_prefix
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
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
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return array{key: self, plainTextKey: string}
     */
    public static function generateKey(string $name, ?string $workspaceId = null): array
    {
        $plainText = 'ocai_live_'.bin2hex(random_bytes(24));

        $key = static::create([
            'workspace_id' => $workspaceId ?? workspace()->id,
            'name' => $name,
            'key_hash' => hash('sha256', $plainText),
            'key_prefix' => substr($plainText, 0, 18),
        ]);

        return ['key' => $key, 'plainTextKey' => $plainText];
    }

    public static function findByPlainText(string $plainText): ?self
    {
        return static::where('key_hash', hash('sha256', $plainText))->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function getMaskedKeyAttribute(): string
    {
        return $this->key_prefix.str_repeat('*', 28).'...';
    }
}

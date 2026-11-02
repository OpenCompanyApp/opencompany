<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An authenticated link from a workspace to an external system
 * (docs/planning/connections-events-routines.md, "Connections").
 *
 * Owns non-secret settings and ownership. Secrets are never stored here; they
 * live in VaultSecret and are read only through the CredentialBroker.
 * `personal` connections belong to one human (owner_user_id); `shared`
 * connections are workspace credentials. Agents use a connection only with a
 * ConnectionGrant (mode read or act).
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $kind
 * @property string $name
 * @property string $ownership
 * @property string|null $owner_user_id
 * @property string $status
 * @property array<string, mixed>|null $settings
 * @property string|null $created_by_id
 */
class Connection extends Model
{
    use BelongsToWorkspace, HasUuids;

    public const OWNERSHIP = ['personal', 'shared'];

    /** Mirrors the column default so a freshly created model is consistent. */
    protected $attributes = ['status' => 'active'];

    protected $fillable = ['workspace_id', 'kind', 'name', 'ownership', 'owner_user_id', 'status', 'settings', 'created_by_id'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /** @return HasMany<ConnectionGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(ConnectionGrant::class);
    }
}

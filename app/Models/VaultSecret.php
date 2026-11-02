<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One encrypted secret belonging to a Connection.
 *
 * Invariant: `ciphertext` is written and read only by App\Domain\Connections\Vault
 * and is hidden from serialization, so a secret can never leak into an API
 * response, a prompt, the VFS, mruby globals or logs by accident.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $connection_id
 * @property string $name
 * @property string $ciphertext
 * @property int $version
 */
class VaultSecret extends Model
{
    use HasUuids;

    protected $fillable = ['workspace_id', 'connection_id', 'name', 'ciphertext', 'version'];

    protected $hidden = ['ciphertext'];
}

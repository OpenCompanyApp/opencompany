<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Lets one agent use one Connection in `read` or `act` mode. Scoped through
 * the connection's workspace; never matched across workspaces.
 *
 * @property string $id
 * @property string $connection_id
 * @property string $agent_id
 * @property string $mode
 */
class ConnectionGrant extends Model
{
    use HasUuids;

    public const MODES = ['read', 'act'];

    protected $fillable = ['connection_id', 'agent_id', 'mode', 'granted_by_id'];
}

<?php

namespace App\Domain\Connections;

use App\Models\Connection;
use App\Models\ConnectionGrant;
use App\Models\User;
use App\Models\VaultSecret;

/**
 * The only place connection secrets are decrypted for use.
 *
 * Invariants (each one a regression lesson from the deleted package system):
 * - Resolution is per call. Nothing is cached in the container, a static or a
 *   client singleton, so a long-running worker can never carry one
 *   workspace's credential into another workspace's call.
 * - The connection must belong to the acting agent's workspace.
 * - The agent needs a ConnectionGrant; `act` is required for mutations.
 * - Personal connections are additionally usable only by agents the owner
 *   granted (the grant row is that consent).
 * - Callers inject the secret at the network/tool boundary; it must never be
 *   returned to a model, written to a prompt, the VFS, mruby or logs.
 */
class CredentialBroker
{
    public function __construct(private Vault $vault) {}

    /**
     * @throws ConnectionAccessDenied
     */
    public function secretFor(User $agent, Connection $connection, string $name, string $mode = 'read'): string
    {
        if ($connection->workspace_id !== $agent->workspace_id) {
            throw new ConnectionAccessDenied('Connection is outside the agent workspace.');
        }
        if ($connection->status !== 'active') {
            throw new ConnectionAccessDenied('Connection is not active.');
        }

        $grant = ConnectionGrant::query()
            ->where('connection_id', $connection->id)
            ->where('agent_id', $agent->id)
            ->first();
        if ($grant === null || ($mode === 'act' && $grant->mode !== 'act')) {
            throw new ConnectionAccessDenied("Agent has no {$mode} grant for this connection.");
        }

        $secret = VaultSecret::query()
            ->where('connection_id', $connection->id)
            ->where('workspace_id', $connection->workspace_id)
            ->where('name', $name)
            ->first();
        if ($secret === null) {
            throw new ConnectionAccessDenied("Connection has no secret named {$name}.");
        }

        return $this->vault->decrypt($secret);
    }
}

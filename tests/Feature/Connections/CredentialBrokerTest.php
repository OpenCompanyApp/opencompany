<?php

namespace Tests\Feature\Connections;

use App\Domain\Connections\ConnectionAccessDenied;
use App\Domain\Connections\CredentialBroker;
use App\Domain\Connections\Vault;
use App\Models\Connection;
use App\Models\ConnectionGrant;
use App\Models\User;
use App\Models\VaultSecret;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Connections keep secrets in the vault and hand them out only through the
 * credential broker, per call, per workspace, per grant.
 */
class CredentialBrokerTest extends TestCase
{
    use RefreshDatabase;

    private function connection(Workspace $workspace, string $secret, string $ownership = 'shared', ?User $owner = null): Connection
    {
        $connection = Connection::create(['workspace_id' => $workspace->id, 'kind' => 'inbound_webhook', 'name' => 'Svc',
            'ownership' => $ownership, 'owner_user_id' => $owner?->id]);
        app(Vault::class)->put($connection, 'api_key', $secret);

        return $connection;
    }

    private function grant(Connection $connection, User $agent, string $mode): void
    {
        ConnectionGrant::create(['connection_id' => $connection->id, 'agent_id' => $agent->id, 'mode' => $mode]);
    }

    // Regression carried over from the deleted package system (credential
    // singletons): two workspaces, same connection kind, sequential calls in
    // ONE process must each receive their own workspace's secret.
    public function test_sequential_calls_in_one_process_never_share_credentials_across_workspaces(): void
    {
        $other = Workspace::create(['name' => 'Other', 'slug' => 'other-broker']);
        $agentA = User::factory()->agent()->create(['workspace_id' => $this->workspace->id]);
        $agentB = User::factory()->agent()->create(['workspace_id' => $other->id]);
        $connA = $this->connection($this->workspace, 'secret-a');
        $connB = $this->connection($other, 'secret-b');
        $this->grant($connA, $agentA, 'read');
        $this->grant($connB, $agentB, 'read');
        $broker = app(CredentialBroker::class);

        $this->assertSame('secret-a', $broker->secretFor($agentA, $connA, 'api_key'));
        $this->assertSame('secret-b', $broker->secretFor($agentB, $connB, 'api_key'));
        $this->assertSame('secret-a', $broker->secretFor($agentA, $connA, 'api_key'));

        $this->expectException(ConnectionAccessDenied::class);
        $broker->secretFor($agentB, $connA, 'api_key');
    }

    public function test_grants_gate_use_and_act_mode(): void
    {
        $agent = User::factory()->agent()->create();
        $connection = $this->connection($this->workspace, 'secret');
        $broker = app(CredentialBroker::class);

        try {
            $broker->secretFor($agent, $connection, 'api_key');
            $this->fail('No grant must mean no secret.');
        } catch (ConnectionAccessDenied) {
        }

        $this->grant($connection, $agent, 'read');
        $this->assertSame('secret', $broker->secretFor($agent, $connection, 'api_key'));
        $this->expectException(ConnectionAccessDenied::class);
        $broker->secretFor($agent, $connection, 'api_key', 'act');
    }

    public function test_secrets_are_encrypted_at_rest_and_never_serialized(): void
    {
        $connection = $this->connection($this->workspace, 'plain-secret');
        $row = VaultSecret::query()->where('connection_id', $connection->id)->sole();

        $this->assertStringNotContainsString('plain-secret', $row->ciphertext);
        $this->assertArrayNotHasKey('ciphertext', $row->toArray());
        $this->assertSame(1, $row->version);
        app(Vault::class)->put($connection, 'api_key', 'rotated');
        $this->assertSame(2, $row->fresh()->version);
    }
}

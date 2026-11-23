<?php

namespace Tests\Feature\Authorization;

use App\Domain\Authorization\Application\AuthorizationLedger;
use App\Domain\Authorization\Engine\Decision;
use App\Domain\Authorization\Engine\PolicyEngine;
use App\Models\AuthorizationLedgerEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The authorization ledger is append-only, hash-chained per workspace,
 * redacts secrets, and is readable only with `audit.read` (GIN-1204;
 * ROADMAP P0 item 9).
 */
class AuthorizationLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_entries_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $entry = $this->ledger()->recordChange($this->workspace->id, 'member.role.change', ['type' => 'human', 'id' => 'u1'], null);

        $this->expectException(\LogicException::class);
        $entry->update(['action' => 'nothing.happened']);
    }

    public function test_deletes_are_refused_too(): void
    {
        $entry = $this->ledger()->recordChange($this->workspace->id, 'member.remove', ['type' => 'human', 'id' => 'u1'], null);

        $this->expectException(\LogicException::class);
        $entry->delete();
    }

    public function test_chain_verifies_and_detects_tampering(): void
    {
        $ledger = $this->ledger();
        $decision = new Decision(Decision::DENY, 'channel_not_member', 'nope', ['deny:channel_not_member'], PolicyEngine::VERSION);
        $ledger->recordDecision($this->workspace->id, 'channel.read', $decision, ['type' => 'human', 'id' => 'u1'], null, ['type' => 'channel', 'id' => 'c1'], ['context' => ['b' => 1, 'a' => 2]]);
        $ledger->recordChange($this->workspace->id, 'agent.grants.replace', ['type' => 'human', 'id' => 'u2'], ['type' => 'agent', 'id' => 'a1'], ['before' => [], 'after' => ['x:allow']]);
        $third = $ledger->recordChange($this->workspace->id, 'member.remove', ['type' => 'human', 'id' => 'u2'], null);

        $this->assertSame(['ok' => true, 'checked' => 3, 'broken_at' => null], $ledger->verify($this->workspace->id));
        $this->assertSame(3, $third->sequence);

        // A raw database edit (bypassing the model) breaks the chain.
        DB::table('authorization_ledger_entries')->where('sequence', 2)->update(['actor_id' => 'someone-else']);
        $this->assertSame(['ok' => false, 'checked' => 1, 'broken_at' => 2], $ledger->verify($this->workspace->id));
    }

    public function test_chains_are_per_workspace(): void
    {
        $other = Workspace::create(['id' => (string) Str::uuid(), 'name' => 'B', 'slug' => 'b-'.Str::random(5)]);
        $this->ledger()->recordChange($this->workspace->id, 'a', ['type' => 'human', 'id' => 'u1'], null);
        $first = $this->ledger()->recordChange($other->id, 'b', ['type' => 'human', 'id' => 'u1'], null);

        $this->assertSame(1, $first->sequence);
        $this->assertNull($first->prev_hash);
    }

    public function test_secrets_are_redacted(): void
    {
        $entry = $this->ledger()->recordChange($this->workspace->id, 'ai_gateway.key.create', ['type' => 'human', 'id' => 'u1'], null, [
            'key_prefix' => 'ocai_live_abc',
            'api_key' => 'ocai_live_secret',
            'nested' => ['password' => 'hunter2', 'Authorization' => 'Bearer x', 'note' => 'fine'],
        ]);

        $this->assertSame('ocai_live_abc', $entry->details['key_prefix']);
        $this->assertSame('[redacted]', $entry->details['api_key']);
        $this->assertSame(['password' => '[redacted]', 'Authorization' => '[redacted]', 'note' => 'fine'], $entry->details['nested']);
    }

    public function test_audit_endpoint_needs_audit_read_and_filters(): void
    {
        $admin = User::factory()->create(['type' => 'human']);
        $member = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $member->id, 'role' => 'member']);

        // The member's refused attempt is itself recorded.
        $this->actingAs($member)->getJson('/api/authorization/ledger')->assertForbidden();
        $this->ledger()->recordChange($this->workspace->id, 'member.role.change', ['type' => 'human', 'id' => 'u9'], null);

        $this->actingAs($admin)->getJson('/api/authorization/ledger?action=audit.read&effect=deny')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.actor_id', $member->id);
        $this->actingAs($admin)->getJson('/api/authorization/ledger?action=member.*')
            ->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($admin)->getJson('/api/authorization/ledger/verify')->assertOk()->assertJsonPath('ok', true);
    }

    private function ledger(): AuthorizationLedger
    {
        return app(AuthorizationLedger::class);
    }
}

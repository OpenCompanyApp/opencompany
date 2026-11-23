<?php

namespace Tests\Feature\Authorization;

use App\Domain\Ai\Gateway\AiGateway;
use App\Models\AiGatewayApiKey;
use App\Models\AuthorizationLedgerEntry;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * AI Gateway keys are scoped, owned, always expire and are revoked rather
 * than deleted (GIN-1203; ROADMAP P0 item 7). Scopes are enforced per call
 * before any provider is contacted; the plaintext key never reaches the
 * ledger.
 */
class AiGatewayKeyScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        IntegrationSetting::create([
            'id' => 'ai-gateway-setting',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'ai-gateway',
            'enabled' => true,
            'config' => ['enabled_models' => ['openai:gpt-4o', 'openai:gpt-4o-mini']],
        ]);

        // No provider may be called when a scope check fails.
        $gateway = Mockery::mock(AiGateway::class);
        $gateway->shouldNotReceive('chatCompletion');
        $gateway->shouldNotReceive('embeddings');
        $this->app->instance(AiGateway::class, $gateway);
    }

    public function test_created_keys_always_expire_are_owned_and_scoped(): void
    {
        $admin = User::factory()->create(['type' => 'human']);

        $created = $this->actingAs($admin)->postJson('/api/ai-gateway/api-keys', ['name' => 'CI'])->assertCreated()->json();
        $key = AiGatewayApiKey::findOrFail($created['id']);
        $this->assertNotNull($key->expires_at);
        $this->assertTrue($key->expires_at->between(now()->addDays(89), now()->addDays(91)));
        $this->assertSame($admin->id, $key->created_by_user_id);
        $this->assertSame(['models.list', 'chat.completions', 'embeddings'], $key->normalizedScopes()['actions']);

        $this->actingAs($admin)->postJson('/api/ai-gateway/api-keys', ['name' => 'Too long', 'expires_in_days' => 9999])->assertStatus(422);

        // The ledger records the creation without the plaintext key.
        $entry = AuthorizationLedgerEntry::query()->where('action', 'ai_gateway.key.create')->sole();
        $this->assertStringNotContainsString($created['key'], json_encode($entry->details));
    }

    public function test_members_cannot_manage_keys(): void
    {
        $member = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $member->id, 'role' => 'member']);

        $this->actingAs($member)->postJson('/api/ai-gateway/api-keys', ['name' => 'x'])->assertForbidden();
        $this->assertSame(0, AiGatewayApiKey::count());
    }

    public function test_model_and_operation_scopes_are_enforced(): void
    {
        $plain = AiGatewayApiKey::generateKey('Scoped', $this->workspace->id, now()->addDay(), [
            'actions' => ['models.list', 'chat.completions'],
            'models' => ['openai:gpt-4o-mini'],
        ])['plainTextKey'];

        $this->withToken($plain)->getJson('/api/ai-gateway/v1/models')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 'openai:gpt-4o-mini');

        $this->withToken($plain)->postJson('/api/ai-gateway/v1/chat/completions', [
            'model' => 'openai:gpt-4o', 'messages' => [['role' => 'user', 'content' => 'hi']],
        ])->assertForbidden()->assertJsonPath('error.code', 'api_key_scope_model');

        $this->withToken($plain)->postJson('/api/ai-gateway/v1/embeddings', ['model' => 'openai:gpt-4o-mini', 'input' => 'x'])
            ->assertForbidden()->assertJsonPath('error.code', 'api_key_scope_action');
    }

    public function test_revoked_expired_and_open_ended_keys_never_authenticate(): void
    {
        $admin = User::factory()->create(['type' => 'human']);
        $result = AiGatewayApiKey::generateKey('Revoke me', $this->workspace->id, now()->addDay());

        $this->actingAs($admin)->deleteJson("/api/ai-gateway/api-keys/{$result['key']->id}")->assertOk();
        $this->assertNotNull($result['key']->fresh()->revoked_at, 'revocation keeps the row for audit');
        $this->withToken($result['plainTextKey'])->getJson('/api/ai-gateway/v1/models')->assertUnauthorized();

        $expired = AiGatewayApiKey::generateKey('Old', $this->workspace->id, now()->addDay());
        $expired['key']->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->withToken($expired['plainTextKey'])->getJson('/api/ai-gateway/v1/models')->assertUnauthorized();

        // A legacy key without an expiry fails closed.
        $legacy = AiGatewayApiKey::generateKey('Legacy', $this->workspace->id, now()->addDay());
        $legacy['key']->forceFill(['expires_at' => null])->save();
        $this->withToken($legacy['plainTextKey'])->getJson('/api/ai-gateway/v1/models')->assertUnauthorized();
    }
}

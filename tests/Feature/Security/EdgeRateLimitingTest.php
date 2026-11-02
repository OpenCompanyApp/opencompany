<?php

namespace Tests\Feature\Security;

use App\Models\AiGatewayApiKey;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Protects the named edge limiters (config/rate_limits.php) on /api/*.
 *
 * Each test shrinks one budget so the limit is reachable in a few requests,
 * then asserts the 429 and the bucket key: per user, per gateway key, per
 * IP + endpoint. Regression target: the P0 audit finding that /api/*,
 * webhooks, the Telegram Mini App and the AI Gateway had no throttling at all.
 */
class EdgeRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    // Authenticated API traffic is limited per user, so one noisy member hits
    // 429 without draining a teammate's budget.
    public function test_authenticated_api_is_throttled_per_user(): void
    {
        config(['rate_limits.api.per_minute' => 2]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)->getJson('/api/users')->assertOk();
        $this->actingAs($alice)->getJson('/api/users')->assertOk();
        $this->actingAs($alice)->getJson('/api/users')->assertStatus(429);

        $this->actingAs($bob)->getJson('/api/users')->assertOk();
    }

    // Unauthenticated callers on first-party API routes share a tighter IP
    // budget and cannot probe endpoints indefinitely.
    public function test_guest_api_requests_are_throttled_by_ip(): void
    {
        config(['rate_limits.api.guest_per_minute' => 1]);

        $this->getJson('/api/users')->assertUnauthorized();
        $this->getJson('/api/users')->assertStatus(429);
    }

    // The gateway budget is per API key (cost guard) and the 429 keeps the
    // OpenAI error envelope that SDK clients parse.
    public function test_ai_gateway_is_throttled_per_api_key_with_openai_error_shape(): void
    {
        config(['rate_limits.ai_gateway.per_minute' => 2]);
        $this->enableGateway();

        $first = AiGatewayApiKey::generateKey('Client A', $this->workspace->id)['plainTextKey'];
        $second = AiGatewayApiKey::generateKey('Client B', $this->workspace->id)['plainTextKey'];

        $this->withToken($first)->getJson('/api/ai-gateway/v1/models')->assertOk();
        $this->withToken($first)->getJson('/api/ai-gateway/v1/models')->assertOk();
        $this->withToken($first)->getJson('/api/ai-gateway/v1/models')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'rate_limit_exceeded');

        $this->withToken($second)->getJson('/api/ai-gateway/v1/models')->assertOk();
    }

    // Key guessing from one IP is cut off after the auth-failure budget, and
    // a valid key from the same IP stays blocked until the window passes, so
    // interleaving a real key cannot reset the counter.
    public function test_ai_gateway_blocks_ip_after_repeated_auth_failures(): void
    {
        config([
            'rate_limits.ai_gateway.auth_failures_per_minute' => 2,
            'rate_limits.ai_gateway.guest_per_minute' => 100,
        ]);
        $this->enableGateway();
        $valid = AiGatewayApiKey::generateKey('Client', $this->workspace->id)['plainTextKey'];

        $this->withToken('oc-wrong-1')->getJson('/api/ai-gateway/v1/models')->assertUnauthorized();
        $this->withToken('oc-wrong-2')->getJson('/api/ai-gateway/v1/models')->assertUnauthorized();
        $this->withToken('oc-wrong-3')->getJson('/api/ai-gateway/v1/models')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limit_exceeded');
        $this->withToken($valid)->getJson('/api/ai-gateway/v1/models')->assertStatus(429);
    }

    // With no TRUSTED_PROXIES, spoofed X-Forwarded-For must not mint fresh
    // IP buckets. Once the operator trusts the proxy, the forwarded client IP
    // becomes the key. Regression for the old trustProxies(at: '*').
    public function test_forwarded_for_only_changes_ip_buckets_when_proxy_is_trusted(): void
    {
        config(['rate_limits.ai_gateway.auth_failures_per_minute' => 1]);

        $this->withHeader('X-Forwarded-For', '203.0.113.1')->getJson('/api/ai-gateway/v1/models')->assertUnauthorized();
        $this->withHeader('X-Forwarded-For', '203.0.113.2')->getJson('/api/ai-gateway/v1/models')->assertStatus(429);

        config(['trustedproxy.proxies' => '127.0.0.1']);

        $this->withHeader('X-Forwarded-For', '203.0.113.3')->getJson('/api/ai-gateway/v1/models')->assertUnauthorized();
    }

    // Chat webhooks are limited per IP + adapter: a flood on one adapter
    // returns 429 but does not consume another adapter's budget.
    public function test_chat_webhooks_are_throttled_per_adapter(): void
    {
        config(['rate_limits.chat_webhooks.per_minute' => 1]);

        $this->postJson('/api/webhooks/chat/telegram', [])->assertUnauthorized();
        $this->postJson('/api/webhooks/chat/telegram', [])->assertStatus(429);

        $this->postJson('/api/webhooks/chat/slack', [])->assertUnauthorized();
    }

    // Mini App endpoints are throttled before init_data verification, so
    // invalid-signature floods are also bounded. The per-IP ceiling holds even
    // when the claimed Telegram user id rotates.
    public function test_telegram_mini_app_is_throttled_per_ip_even_when_user_id_rotates(): void
    {
        config([
            'rate_limits.telegram_mini_app.per_user_per_minute' => 100,
            'rate_limits.telegram_mini_app.per_ip_per_minute' => 2,
        ]);

        foreach ([1, 2] as $id) {
            $this->postJson('/api/telegram/mini-app/workspaces', ['init_data' => $this->fakeInitData($id)])
                ->assertUnauthorized();
        }

        $this->postJson('/api/telegram/mini-app/workspaces', ['init_data' => $this->fakeInitData(3)])
            ->assertStatus(429);
    }

    private function fakeInitData(int $telegramUserId): string
    {
        return http_build_query([
            'user' => json_encode(['id' => $telegramUserId]),
            'auth_date' => (string) time(),
            'hash' => str_repeat('0', 64),
        ]);
    }

    private function enableGateway(): void
    {
        IntegrationSetting::create([
            'id' => 'ai-gateway-setting',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'ai-gateway',
            'enabled' => true,
            'config' => ['enabled_models' => ['openai:gpt-4o']],
        ]);
    }
}

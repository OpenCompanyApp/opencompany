<?php

namespace Tests\Feature;

use App\Models\IntegrationWebhook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers persisted workspace webhooks backing the integrations page.
 */
class IntegrationWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_workspace_admin_can_list_empty_webhooks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/integration-webhooks')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_workspace_webhooks_can_be_created_listed_updated_deleted_and_triggered(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user)->postJson('/api/integration-webhooks', [
            'name' => 'GitHub PR Notifications',
            'targetType' => 'agent',
            'targetId' => 'agent-1',
        ])->assertCreated();

        $id = $create->json('webhook.id');
        $secret = $create->json('webhook.secret');

        $this->actingAs($user)
            ->getJson('/api/integration-webhooks')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.secret', null);

        $this->actingAs($user)->patchJson("/api/integration-webhooks/{$id}", [
            'name' => 'GitHub Review Notifications',
            'targetType' => 'channel',
            'targetId' => 'channel-1',
        ])->assertOk()
            ->assertJsonPath('webhook.name', 'GitHub Review Notifications')
            ->assertJsonPath('webhook.targetType', 'channel');

        $this->postJson("/api/webhooks/{$id}?secret={$secret}", ['event' => 'opened'])
            ->assertAccepted();

        $webhook = IntegrationWebhook::findOrFail($id);
        $this->assertSame(1, $webhook->call_count);
        $this->assertSame('opened', $webhook->last_payload['event']);

        $this->actingAs($user)->deleteJson("/api/integration-webhooks/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('integration_webhooks', ['id' => $id]);
    }

    public function test_incoming_webhook_requires_its_secret(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user)->postJson('/api/integration-webhooks', [
            'name' => 'Stripe Events',
            'targetType' => 'task',
        ])->assertCreated();

        $id = $create->json('webhook.id');

        $this->postJson("/api/webhooks/{$id}", ['event' => 'invoice.created'])
            ->assertUnauthorized();

        $this->assertSame(0, IntegrationWebhook::findOrFail($id)->call_count);
    }
}

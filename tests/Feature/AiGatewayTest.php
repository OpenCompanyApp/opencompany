<?php

namespace Tests\Feature;

use App\Domain\Ai\Gateway\AiGateway;
use App\Models\AiGatewayApiKey;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use Mockery;
use Tests\TestCase;

/**
 * Covers OpenCompany's app-owned AI Gateway protocol boundary.
 */
class AiGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_requires_bearer_api_key(): void
    {
        $this->getJson('/api/ai-gateway/v1/models')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_api_key');
    }

    public function test_admin_can_configure_gateway_and_create_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/ai-gateway/config', [
                'enabled' => true,
                'enabled_models' => ['openai:gpt-4o'],
            ])
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('enabled_models.0', 'openai:gpt-4o');

        $this->actingAs($user)
            ->postJson('/api/ai-gateway/api-keys', ['name' => 'Local client'])
            ->assertCreated()
            ->assertJsonStructure(['id', 'name', 'key', 'masked_key', 'created_at'])
            ->assertJsonPath('name', 'Local client');
    }

    public function test_models_endpoint_exposes_enabled_workspace_models(): void
    {
        $plainTextKey = $this->createGatewayKey();
        $this->enableGateway(['openai:gpt-4o']);

        $this->withToken($plainTextKey)
            ->getJson('/api/ai-gateway/v1/models')
            ->assertOk()
            ->assertJsonPath('object', 'list')
            ->assertJsonPath('data.0.id', 'openai:gpt-4o');
    }

    public function test_chat_completions_return_openai_compatible_payload(): void
    {
        $plainTextKey = $this->createGatewayKey();
        $this->enableGateway(['openai:gpt-4o']);

        $gateway = Mockery::mock(AiGateway::class);
        $gateway->shouldReceive('chatCompletion')
            ->once()
            ->with('openai:gpt-4o', Mockery::type('array'), 20, 0.2)
            ->andReturn(new TextResponse(
                text: 'hello from OpenCompany',
                usage: new Usage(promptTokens: 5, completionTokens: 3),
                meta: new Meta(provider: 'openai', model: 'gpt-4o'),
            ));
        $this->app->instance(AiGateway::class, $gateway);

        $this->withToken($plainTextKey)
            ->postJson('/api/ai-gateway/v1/chat/completions', [
                'model' => 'openai:gpt-4o',
                'messages' => [['role' => 'user', 'content' => 'hello']],
                'max_tokens' => 20,
                'temperature' => 0.2,
            ])
            ->assertOk()
            ->assertJsonPath('object', 'chat.completion')
            ->assertJsonPath('choices.0.message.content', 'hello from OpenCompany')
            ->assertJsonPath('usage.total_tokens', 8);
    }

    private function createGatewayKey(): string
    {
        return AiGatewayApiKey::generateKey('Test client', $this->workspace->id)['plainTextKey'];
    }

    /**
     * @param  list<string>  $models
     */
    private function enableGateway(array $models): void
    {
        IntegrationSetting::create([
            'id' => 'ai-gateway-setting',
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'ai-gateway',
            'enabled' => true,
            'config' => ['enabled_models' => $models],
        ]);
    }
}

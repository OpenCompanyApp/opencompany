<?php

namespace Tests\Feature;

use App\Agents\Providers\DynamicProviderResolver;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Ai\AiManager;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Providers\OpenRouterProvider;
use OpenCompany\PrismRelay\Bridge\LaravelAi\RelayTextGateway;
use Tests\TestCase;

class DynamicProviderResolverTest extends TestCase
{
    use RefreshDatabase;

    private DynamicProviderResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DynamicProviderResolver();
    }

    public function test_resolves_standard_provider(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);

        $result = $this->resolver->resolve($agent);

        $this->assertEquals('anthropic', $result['provider']);
        $this->assertEquals('claude-sonnet-4-5-20250929', $result['model']);
    }

    public function test_resolves_openai_provider(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'openai:gpt-4o',
        ]);

        $result = $this->resolver->resolve($agent);

        $this->assertEquals('openai', $result['provider']);
        $this->assertEquals('gpt-4o', $result['model']);

        $provider = app(AiManager::class)->textProvider('openai');

        $this->assertInstanceOf(OpenAiProvider::class, $provider);
        $this->assertInstanceOf(OpenAiGateway::class, $provider->textGateway());
    }

    public function test_resolves_z_provider_with_integration(): void
    {
        IntegrationSetting::create([
            'id' => 'int-1',
            'integration_id' => 'z',
            'enabled' => true,
            'workspace_id' => $this->workspace->id,
            'config' => [
                'api_key' => 'test-api-key',
                'url' => 'https://api.z.ai/api/coding/paas/v4',
            ],
        ]);

        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'z:glm-5.1',
        ]);

        $result = $this->resolver->resolve($agent);

        $this->assertEquals('z', $result['provider']);
        $this->assertEquals('glm-5.1', $result['model']);

        // Verify Prism config was registered on the provider variant key
        $this->assertNotNull(config('prism.providers.z'));
        $this->assertEquals('test-api-key', config('prism.providers.z.api_key'));

        // Verify AI SDK config was registered with custom driver
        $this->assertNotNull(config('ai.providers.z'));
        $this->assertEquals('z', config('ai.providers.z.driver'));

        $provider = app(AiManager::class)->textProvider('z');

        $this->assertInstanceOf(OpenRouterProvider::class, $provider);
        $this->assertInstanceOf(RelayTextGateway::class, $provider->textGateway());
    }

    public function test_throws_for_unconfigured_z_provider(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'z-api:glm-5.1',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("not configured");

        $this->resolver->resolve($agent);
    }

    public function test_throws_for_unknown_provider(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'nonexistent:some-model',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown provider");

        $this->resolver->resolve($agent);
    }

    public function test_defaults_to_z_when_no_brain(): void
    {
        IntegrationSetting::create([
            'id' => 'int-1',
            'integration_id' => 'z',
            'enabled' => true,
            'workspace_id' => $this->workspace->id,
            'config' => [
                'api_key' => 'test-key',
                'url' => 'https://api.z.ai/api/coding/paas/v4',
            ],
        ]);

        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => null,
        ]);

        $result = $this->resolver->resolve($agent);

        $this->assertEquals('z', $result['provider']);
        $this->assertEquals('glm-5.1', $result['model']);
    }
}

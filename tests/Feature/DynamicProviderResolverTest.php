<?php

namespace Tests\Feature;

use App\Agents\Providers\DynamicProviderResolver;
use App\Ai\Gateways\CachingTextGateway;
use App\Ai\Gateways\UnsupportedTextGateway;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\DeepSeek\DeepSeekGateway;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Providers\DeepSeekProvider;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Providers\Provider;
use OpenCompany\PrismRelay\Registry\RelayRegistry;
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
        $this->assertInstanceOf(CachingTextGateway::class, $provider->textGateway());
        $this->assertInstanceOf(OpenAiGateway::class, $provider->textGateway()->inner());
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

        $this->assertNotNull(config('ai.providers.z'));
        $this->assertEquals('z', config('ai.providers.z.driver'));
        $this->assertEquals('test-api-key', config('ai.providers.z.key'));
        $this->assertEquals('test-api-key', config('prism.providers.z.api_key'));
        $this->assertEquals('https://api.z.ai/api/coding/paas/v4', config('prism.providers.z.url'));

        $provider = app(AiManager::class)->textProvider('z');

        $this->assertInstanceOf(DeepSeekProvider::class, $provider);
        $this->assertInstanceOf(CachingTextGateway::class, $provider->textGateway());
        $this->assertInstanceOf(DeepSeekGateway::class, $provider->textGateway()->inner());
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

    public function test_all_relay_registry_providers_are_registered_with_laravel_ai(): void
    {
        $manager = app(AiManager::class);

        foreach (app(RelayRegistry::class)->registrationNames() as $providerName) {
            $provider = $manager->textProvider($providerName);

            $this->assertInstanceOf(TextProvider::class, $provider, "Provider [{$providerName}] was not registered.");
            $this->assertInstanceOf(CachingTextGateway::class, $provider->textGateway(), "Provider [{$providerName}] is not cache-decorated.");
        }
    }

    public function test_supported_relay_registry_providers_are_available_in_integration_catalog(): void
    {
        $available = IntegrationSetting::getAvailableIntegrations();
        $registry = app(RelayRegistry::class);

        foreach ($registry->canonicalProviders() as $providerName) {
            $driver = $registry->driver($providerName);
            $url = $registry->url($providerName);

            if (in_array($driver, ['unsupported', 'external-process', 'google-vertex', 'amazon-bedrock'], true)
                || ($driver === 'openai-compatible' && trim($url) === '')) {
                $this->assertArrayNotHasKey($providerName, $available, "Unsupported provider [{$providerName}] should not be enableable.");

                continue;
            }

            $this->assertArrayHasKey($providerName, $available, "Provider [{$providerName}] is missing from integrations.");
            $this->assertSame('ai-models', $available[$providerName]['category'] ?? null);
        }
    }

    public function test_blank_registry_urls_do_not_override_gateway_defaults(): void
    {
        $provider = app(AiManager::class)->textProvider('cohere');

        $this->assertInstanceOf(Provider::class, $provider);
        $this->assertArrayNotHasKey('url', $provider->additionalConfiguration());
    }

    public function test_generated_openai_compatible_provider_uses_laravel_ai_gateway(): void
    {
        config(['ai.providers.mimo' => [
            'driver' => 'mimo',
            'key' => 'test-key',
            'url' => 'https://token-plan-sgp.xiaomimimo.com/v1',
        ]]);

        $provider = app(AiManager::class)->textProvider('mimo');

        $this->assertInstanceOf(DeepSeekProvider::class, $provider);
        $this->assertInstanceOf(CachingTextGateway::class, $provider->textGateway());
        $this->assertInstanceOf(DeepSeekGateway::class, $provider->textGateway()->inner());
    }

    public function test_opencode_go_provider_is_registered_without_prism_gateway(): void
    {
        $provider = app(AiManager::class)->textProvider('opencode-go');

        $this->assertInstanceOf(CachingTextGateway::class, $provider->textGateway());
        $this->assertInstanceOf(DeepSeekGateway::class, $provider->textGateway()->inner());
    }

    public function test_unsupported_transport_provider_keeps_clear_runtime_error(): void
    {
        $provider = app(AiManager::class)->textProvider('custom');

        $this->assertInstanceOf(CachingTextGateway::class, $provider->textGateway());
        $this->assertInstanceOf(UnsupportedTextGateway::class, $provider->textGateway()->inner());
    }
}

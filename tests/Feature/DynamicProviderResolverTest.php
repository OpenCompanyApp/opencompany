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

/**
 * Verifies provider/model resolution across relay registry and workspace config.
 */
class DynamicProviderResolverTest extends TestCase
{
    use RefreshDatabase;

    private DynamicProviderResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DynamicProviderResolver;
    }

    public function test_resolves_standard_provider(): void
    {
        config(['prism.providers.anthropic.api_key' => 'test-key']);

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
        config(['prism.providers.openai.api_key' => 'test-key']);

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
        $registry = app(RelayRegistry::class);
        $providerName = 'z';
        $model = (string) ($registry->provider($providerName)['default_model'] ?? 'default');
        $url = $registry->url($providerName);

        IntegrationSetting::create([
            'id' => 'int-1',
            'integration_id' => $providerName,
            'enabled' => true,
            'workspace_id' => $this->workspace->id,
            'config' => [
                'api_key' => 'test-api-key',
                'url' => $url,
            ],
        ]);

        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => "{$providerName}:{$model}",
        ]);

        $result = $this->resolver->resolve($agent);

        $this->assertEquals($providerName, $result['provider']);
        $this->assertEquals($model, $result['model']);

        $this->assertNotNull(config("ai.providers.{$providerName}"));
        $this->assertEquals($providerName, config("ai.providers.{$providerName}.driver"));
        $this->assertEquals('test-api-key', config("ai.providers.{$providerName}.key"));
        $this->assertEquals('test-api-key', config("prism.providers.{$providerName}.api_key"));
        $this->assertEquals($url, config("prism.providers.{$providerName}.url"));

        $provider = app(AiManager::class)->textProvider($providerName);

        $this->assertInstanceOf(DeepSeekProvider::class, $provider);
        $this->assertInstanceOf(CachingTextGateway::class, $provider->textGateway());
        $this->assertInstanceOf(DeepSeekGateway::class, $provider->textGateway()->inner());
    }

    public function test_legacy_glm_coding_brain_resolves_to_current_glm_provider(): void
    {
        $registry = app(RelayRegistry::class);

        IntegrationSetting::create([
            'id' => 'int-1',
            'integration_id' => 'z',
            'enabled' => true,
            'workspace_id' => $this->workspace->id,
            'config' => [
                'api_key' => 'test-api-key',
                'url' => $registry->url('z'),
            ],
        ]);

        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'glm-coding:glm-4.7',
            'workspace_id' => $this->workspace->id,
        ]);

        $result = $this->resolver->resolve($agent);

        $this->assertSame('z', $result['provider']);
        $this->assertSame('glm-5.1', $result['model']);
    }

    public function test_throws_for_unconfigured_z_provider(): void
    {
        $registry = app(RelayRegistry::class);
        $providerName = 'z-api';
        $model = (string) ($registry->provider($providerName)['default_model'] ?? 'default');

        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => "{$providerName}:{$model}",
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not configured');

        $this->resolver->resolve($agent);
    }

    public function test_throws_for_unknown_provider(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'nonexistent:some-model',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown provider');

        $this->resolver->resolve($agent);
    }

    public function test_defaults_to_z_when_no_brain(): void
    {
        $registry = app(RelayRegistry::class);
        $defaultProvider = (string) config('ai.default_for_agents');
        $defaultModel = (string) ($registry->provider($defaultProvider)['default_model'] ?? 'default');

        IntegrationSetting::create([
            'id' => 'int-1',
            'integration_id' => $defaultProvider,
            'enabled' => true,
            'workspace_id' => $this->workspace->id,
            'config' => [
                'api_key' => 'test-key',
                'url' => $registry->url($defaultProvider),
            ],
        ]);

        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => null,
        ]);

        $result = $this->resolver->resolve($agent);

        $this->assertEquals($defaultProvider, $result['provider']);
        $this->assertEquals($defaultModel, $result['model']);
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

            if (! $registry->laravelAiRuntimeSupported($providerName, $url)) {
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
        $registry = app(RelayRegistry::class);
        $providerName = 'mimo';

        config(['ai.providers.mimo' => [
            'driver' => $providerName,
            'key' => 'test-key',
            'url' => $registry->url($providerName),
        ]]);

        $provider = app(AiManager::class)->textProvider($providerName);

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

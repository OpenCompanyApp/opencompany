<?php

namespace Tests\Feature;

use App\Agents\Providers\DynamicProviderResolver;
use App\Ai\Gateways\CachingTextGateway;
use App\Domain\Ai\Catalog\AiCatalog;
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
use Tests\TestCase;

/**
 * Verifies provider/model resolution across the AI catalog and workspace config.
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
        config(['ai.providers.anthropic.api_key' => 'test-key']);

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
        config(['ai.providers.openai.api_key' => 'test-key']);

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
        $catalog = app(AiCatalog::class);
        $providerName = 'z';
        $model = $catalog->defaultModel($providerName);
        $url = $catalog->provider($providerName)?->defaultUrl;

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
        $this->assertEquals('test-api-key', config("ai.providers.{$providerName}.api_key"));
        $this->assertEquals($url, config("ai.providers.{$providerName}.url"));

        $provider = app(AiManager::class)->textProvider($providerName);

        $this->assertInstanceOf(DeepSeekProvider::class, $provider);
        $this->assertInstanceOf(CachingTextGateway::class, $provider->textGateway());
        $this->assertInstanceOf(DeepSeekGateway::class, $provider->textGateway()->inner());
    }

    public function test_legacy_glm_coding_brain_resolves_to_current_glm_provider(): void
    {
        $catalog = app(AiCatalog::class);

        IntegrationSetting::create([
            'id' => 'int-1',
            'integration_id' => 'z',
            'enabled' => true,
            'workspace_id' => $this->workspace->id,
            'config' => [
                'api_key' => 'test-api-key',
                'url' => $catalog->provider('z')?->defaultUrl,
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
        $catalog = app(AiCatalog::class);
        $providerName = 'z-api';
        $model = $catalog->defaultModel($providerName);

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
        $catalog = app(AiCatalog::class);
        $defaultProvider = (string) config('ai.default_for_agents');
        $defaultModel = $catalog->defaultModel($defaultProvider);

        IntegrationSetting::create([
            'id' => 'int-1',
            'integration_id' => $defaultProvider,
            'enabled' => true,
            'workspace_id' => $this->workspace->id,
            'config' => [
                'api_key' => 'test-key',
                'url' => $catalog->provider($defaultProvider)?->defaultUrl,
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

    public function test_all_catalog_providers_are_registered_with_laravel_ai(): void
    {
        $manager = app(AiManager::class);

        foreach (app(AiCatalog::class)->registrationNames() as $providerName) {
            $provider = $manager->textProvider($providerName);

            $this->assertInstanceOf(TextProvider::class, $provider, "Provider [{$providerName}] was not registered.");
            $this->assertInstanceOf(CachingTextGateway::class, $provider->textGateway(), "Provider [{$providerName}] is not cache-decorated.");
        }
    }

    public function test_catalog_providers_are_available_in_integration_catalog(): void
    {
        $available = IntegrationSetting::getAvailableIntegrations();

        foreach (app(AiCatalog::class)->providers() as $provider) {
            if ($provider->authMode === 'oauth') {
                $this->assertArrayHasKey($provider->id, $available, "OAuth provider [{$provider->id}] should remain visible for its dedicated auth flow.");
            } else {
                $this->assertArrayHasKey($provider->id, $available, "Provider [{$provider->id}] is missing from integrations.");
                $this->assertSame('ai-models', $available[$provider->id]['category'] ?? null);
            }
        }
    }

    public function test_catalog_default_urls_are_registered_with_gateway_config(): void
    {
        $provider = app(AiManager::class)->textProvider('cohere');

        $this->assertSame('https://api.cohere.com/v2', $provider->additionalConfiguration()['url'] ?? null);
    }

    public function test_openai_compatible_catalog_provider_uses_laravel_ai_gateway(): void
    {
        $providerName = 'mimo';

        config(['ai.providers.mimo' => [
            'driver' => $providerName,
            'key' => 'test-key',
            'url' => app(AiCatalog::class)->provider($providerName)?->defaultUrl,
        ]]);

        $provider = app(AiManager::class)->textProvider($providerName);

        $this->assertInstanceOf(DeepSeekProvider::class, $provider);
        $this->assertInstanceOf(CachingTextGateway::class, $provider->textGateway());
        $this->assertInstanceOf(DeepSeekGateway::class, $provider->textGateway()->inner());
    }
}

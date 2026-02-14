<?php

namespace Tests\Feature;

use App\Agents\Providers\DynamicProviderResolver;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
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
    }

    public function test_resolves_glm_provider_with_integration(): void
    {
        IntegrationSetting::create([
            'id' => 'int-1',
            'integration_id' => 'glm-coding',
            'enabled' => true,
            'config' => [
                'api_key' => 'test-api-key',
                'url' => 'https://api.z.ai/api/coding/paas/v4',
            ],
        ]);

        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'glm-coding:glm-4.7',
        ]);

        $result = $this->resolver->resolve($agent);

        $this->assertEquals('glm-coding', $result['provider']);
        $this->assertEquals('glm-4.7', $result['model']);

        // Verify Prism config was registered on the provider variant key
        $this->assertNotNull(config('prism.providers.glm-coding'));
        $this->assertEquals('test-api-key', config('prism.providers.glm-coding.api_key'));

        // Verify AI SDK config was registered with custom driver
        $this->assertNotNull(config('ai.providers.glm-coding'));
        $this->assertEquals('glm-coding', config('ai.providers.glm-coding.driver'));
    }

    public function test_throws_for_unconfigured_glm_provider(): void
    {
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'glm:glm-4-plus',
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

    public function test_defaults_to_glm_coding_when_no_brain(): void
    {
        IntegrationSetting::create([
            'id' => 'int-1',
            'integration_id' => 'glm-coding',
            'enabled' => true,
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

        $this->assertEquals('glm-coding', $result['provider']);
        $this->assertEquals('glm-4.7', $result['model']);
    }
}

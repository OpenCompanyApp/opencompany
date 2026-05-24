<?php

namespace Tests\Feature\Domain\Web;

use App\Domain\Web\Support\WebCredentialResolver;
use App\Models\IntegrationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebCredentialResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_workspace_setting_blocks_config_fallback_credentials(): void
    {
        config(['web.providers.tavily.api_key' => 'fallback-key']);

        IntegrationSetting::query()->create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'web.tavily',
            'config' => ['api_key' => 'stored-key'],
            'enabled' => false,
            'is_default' => true,
        ]);

        $resolver = app(WebCredentialResolver::class);

        $this->assertSame('', $resolver->apiKey('tavily'));
        $this->assertFalse($resolver->isConfigured('tavily'));
    }

    public function test_workspace_credential_takes_precedence_over_config_fallback(): void
    {
        config(['web.providers.tavily.api_key' => 'fallback-key']);

        IntegrationSetting::query()->create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'web.tavily',
            'config' => ['api_key' => 'stored-key'],
            'enabled' => true,
            'is_default' => true,
        ]);

        $this->assertSame('stored-key', app(WebCredentialResolver::class)->apiKey('tavily'));
    }
}

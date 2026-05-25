<?php

namespace Tests\Feature\Domain\Web;

use App\Domain\Web\Managers\WebFetchProviderManager;
use App\Domain\Web\Managers\WebSearchProviderManager;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebLiveSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_fetch_live_smoke_is_opt_in(): void
    {
        $this->skipUnlessLiveEnabled();

        $response = app(WebFetchProviderManager::class)->fetch(new WebFetchRequest(
            url: 'https://example.com',
            mode: 'metadata',
            noCache: true,
            workspaceId: $this->workspace->id,
        ));

        $this->assertSame('direct', $response->provider);
        $this->assertSame(200, $response->statusCode);
    }

    public function test_tavily_search_live_smoke_is_credential_gated(): void
    {
        $this->skipUnlessLiveEnabled();

        $apiKey = getenv('TAVILY_API_KEY') ?: '';
        if ($apiKey === '') {
            $this->markTestSkipped('TAVILY_API_KEY is required for Tavily live smoke test.');
        }

        config(['web.providers.tavily.api_key' => $apiKey]);

        $response = app(WebSearchProviderManager::class)->search(new WebSearchRequest(
            query: 'OpenCompany web search fetch tools',
            provider: 'tavily',
            maxResults: 3,
            noCache: true,
            workspaceId: $this->workspace->id,
        ));

        $this->assertSame('tavily', $response->provider);
        $this->assertNotEmpty($response->results);
    }

    private function skipUnlessLiveEnabled(): void
    {
        if (! filter_var(getenv('OPENCOMPANY_WEB_LIVE_TESTS') ?: false, FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set OPENCOMPANY_WEB_LIVE_TESTS=true to run live provider smoke tests.');
        }
    }
}

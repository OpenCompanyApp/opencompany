<?php

namespace Tests\Feature\Domain\Web;

use App\Domain\Web\Managers\WebFetchProviderManager;
use App\Domain\Web\Managers\WebSearchProviderManager;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebProviderManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(WebRequestGuard::class, fn () => new class extends WebRequestGuard
        {
            protected function resolveIpAddresses(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
    }

    public function test_search_manager_uses_fallback_provider_after_failure(): void
    {
        config([
            'web.search.default_provider' => 'tavily',
            'web.search.fallback_providers' => ['exa'],
            'web.providers.tavily.api_key' => 'tavily-key',
            'web.providers.exa.api_key' => 'exa-key',
        ]);

        Http::fake([
            'https://api.tavily.com/search' => Http::response(['error' => 'down'], 500),
            'https://api.exa.ai/search' => Http::response([
                'results' => [[
                    'title' => 'Exa Result',
                    'url' => 'https://example.com/result',
                    'text' => 'Fallback result.',
                ]],
            ]),
        ]);

        $response = app(WebSearchProviderManager::class)->search(new WebSearchRequest(
            query: 'fallback search',
            workspaceId: $this->workspace->id,
        ));

        $this->assertSame('exa', $response->provider);
        $this->assertSame('Exa Result', $response->results[0]->title);
        $this->assertDatabaseHas('web_usage_events', ['provider' => 'tavily', 'success' => false]);
        $this->assertDatabaseHas('web_usage_events', ['provider' => 'exa', 'success' => true]);
    }

    public function test_search_manager_returns_workspace_scoped_cache_hit(): void
    {
        config([
            'web.search.default_provider' => 'tavily',
            'web.providers.tavily.api_key' => 'tavily-key',
        ]);

        Http::fake([
            'https://api.tavily.com/search' => Http::response([
                'results' => [[
                    'title' => 'Cached Result',
                    'url' => 'https://example.com/cached',
                    'content' => 'Cached snippet.',
                ]],
            ]),
        ]);

        $request = new WebSearchRequest(query: 'cached search', workspaceId: $this->workspace->id);
        app(WebSearchProviderManager::class)->search($request);
        $cached = app(WebSearchProviderManager::class)->search($request);

        $this->assertTrue($cached->cacheHit);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('web_usage_events', ['provider' => 'tavily', 'cache_hit' => true]);
    }

    public function test_fetch_manager_normalizes_markdown_from_provider_fetch(): void
    {
        config([
            'web.fetch.allow_external' => true,
            'web.providers.tavily.api_key' => 'tavily-key',
        ]);

        Http::fake([
            'https://api.tavily.com/extract' => Http::response([
                'results' => [[
                    'url' => 'https://example.com/page',
                    'title' => 'Remote Page',
                    'raw_content' => "# Remote Page\n\n## Remote Section\n\nProvider content.",
                ]],
            ]),
        ]);

        $response = app(WebFetchProviderManager::class)->fetch(new WebFetchRequest(
            url: 'https://example.com/page',
            provider: 'tavily',
            workspaceId: $this->workspace->id,
        ));

        $this->assertSame('Remote Section', $response->outline[1]['title']);
        $this->assertArrayHasKey('remote-section', $response->sections);
    }
}

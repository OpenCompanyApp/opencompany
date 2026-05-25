<?php

namespace Tests\Unit\Domain\Web;

use App\Domain\Web\Providers\AnthropicNativeSearchProvider;
use App\Domain\Web\Providers\BraveProvider;
use App\Domain\Web\Providers\ExaProvider;
use App\Domain\Web\Providers\FirecrawlProvider;
use App\Domain\Web\Providers\JinaProvider;
use App\Domain\Web\Providers\OpenAiNativeSearchProvider;
use App\Domain\Web\Providers\ParallelProvider;
use App\Domain\Web\Providers\PerplexityProvider;
use App\Domain\Web\Providers\SearxngProvider;
use App\Domain\Web\Providers\TavilyProvider;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebProviderAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'web.providers.brave.api_key' => 'brave-key',
            'web.providers.exa.api_key' => 'exa-key',
            'web.providers.firecrawl.api_key' => 'firecrawl-key',
            'web.providers.openai_native.api_key' => 'openai-key',
            'web.providers.anthropic_native.api_key' => 'anthropic-key',
            'web.providers.parallel.api_key' => 'parallel-key',
            'web.providers.perplexity.api_key' => 'perplexity-key',
            'web.providers.searxng.base_url' => 'https://search.test',
            'web.providers.tavily.api_key' => 'tavily-key',
        ]);

        $this->app->bind(WebRequestGuard::class, fn () => new class extends WebRequestGuard
        {
            protected function resolveIpAddresses(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
    }

    public function test_search_adapters_normalize_success_payloads(): void
    {
        Http::fake([
            'https://api.search.brave.com/res/v1/web/search*' => Http::response(['web' => ['results' => [['title' => 'Brave Result', 'url' => 'https://example.com/brave', 'description' => '<b>Snippet</b>']]]]),
            'https://api.firecrawl.dev/v1/search' => Http::response(['data' => [['title' => 'Firecrawl Result', 'url' => 'https://example.com/firecrawl', 'description' => 'Snippet']]]),
            'https://api.exa.ai/search' => Http::response(['results' => [['title' => 'Exa Result', 'url' => 'https://example.com/exa', 'text' => 'Snippet']]]),
            'https://s.jina.ai/*' => Http::response('[Jina Result](https://example.com/jina) Snippet'),
            'https://api.parallel.ai/v1beta/search' => Http::response(['results' => [['title' => 'Parallel Result', 'url' => 'https://example.com/parallel', 'description' => 'Snippet']]]),
            'https://api.perplexity.ai/search' => Http::response(['results' => [['title' => 'Perplexity Result', 'url' => 'https://example.com/perplexity', 'snippet' => 'Snippet']]]),
            'https://search.test/search*' => Http::response(['results' => [['title' => 'SearXNG Result', 'url' => 'https://example.com/searxng', 'content' => 'Snippet']]]),
            'https://api.tavily.com/search' => Http::response(['results' => [['title' => 'Tavily Result', 'url' => 'https://example.com/tavily', 'content' => 'Snippet']]]),
        ]);

        $providers = [
            [app(BraveProvider::class), 'Brave Result'],
            [app(FirecrawlProvider::class), 'Firecrawl Result'],
            [app(ExaProvider::class), 'Exa Result'],
            [app(JinaProvider::class), 'Jina Result'],
            [app(ParallelProvider::class), 'Parallel Result'],
            [app(PerplexityProvider::class), 'Perplexity Result'],
            [app(SearxngProvider::class), 'SearXNG Result'],
            [app(TavilyProvider::class), 'Tavily Result'],
        ];

        foreach ($providers as [$provider, $expectedTitle]) {
            $response = $provider->search(new WebSearchRequest(query: 'adapter query'));

            $this->assertSame($expectedTitle, $response->results[0]->title);
        }
    }

    public function test_fetch_adapters_normalize_success_payloads(): void
    {
        Http::fake([
            'https://api.firecrawl.dev/v1/scrape' => Http::response(['data' => ['markdown' => 'Firecrawl content', 'metadata' => ['title' => 'Firecrawl Page', 'sourceURL' => 'https://example.com/firecrawl']]]),
            'https://api.exa.ai/contents' => Http::response(['results' => [['title' => 'Exa Page', 'url' => 'https://example.com/exa', 'text' => 'Exa content']]]),
            'https://r.jina.ai/*' => Http::response('Jina content'),
            'https://api.parallel.ai/v1beta/extract' => Http::response(['results' => [['title' => 'Parallel Page', 'url' => 'https://example.com/parallel', 'content' => 'Parallel content']]]),
            'https://api.tavily.com/extract' => Http::response(['results' => [['title' => 'Tavily Page', 'url' => 'https://example.com/tavily', 'raw_content' => 'Tavily content']]]),
        ]);

        $providers = [
            [app(FirecrawlProvider::class), 'Firecrawl content'],
            [app(ExaProvider::class), 'Exa content'],
            [app(JinaProvider::class), 'Jina content'],
            [app(ParallelProvider::class), 'Parallel content'],
            [app(TavilyProvider::class), 'Tavily content'],
        ];

        foreach ($providers as [$provider, $expectedContent]) {
            $response = $provider->fetch(new WebFetchRequest(url: 'https://example.com/page'));

            $this->assertStringContainsString($expectedContent, $response->content);
        }
    }

    public function test_native_model_search_adapters_extract_citations(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_123',
                'output_text' => 'OpenAI answer.',
                'output' => [[
                    'content' => [[
                        'text' => 'OpenAI answer.',
                        'annotations' => [[
                            'type' => 'url_citation',
                            'title' => 'OpenAI Citation',
                            'url' => 'https://example.com/openai',
                        ]],
                    ]],
                ]],
            ]),
            'https://api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_123',
                'content' => [[
                    'type' => 'text',
                    'text' => 'Anthropic answer.',
                    'citations' => [[
                        'title' => 'Anthropic Citation',
                        'url' => 'https://example.com/anthropic',
                    ]],
                ]],
            ]),
        ]);

        $openAi = app(OpenAiNativeSearchProvider::class)->search(new WebSearchRequest(query: 'native search'));
        $anthropic = app(AnthropicNativeSearchProvider::class)->search(new WebSearchRequest(query: 'native search'));

        $this->assertSame('OpenAI Citation', $openAi->results[0]->title);
        $this->assertSame('Anthropic Citation', $anthropic->results[0]->title);
    }
}

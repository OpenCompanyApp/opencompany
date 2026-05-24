<?php

namespace Tests\Unit\Domain\Web;

use App\Domain\Web\Exceptions\WebProviderException;
use App\Domain\Web\Providers\AnthropicNativeSearchProvider;
use App\Domain\Web\Providers\BraveProvider;
use App\Domain\Web\Providers\ExaProvider;
use App\Domain\Web\Providers\Fetch\ZaiReaderFetchProvider;
use App\Domain\Web\Providers\FirecrawlProvider;
use App\Domain\Web\Providers\JinaProvider;
use App\Domain\Web\Providers\OpenAiNativeSearchProvider;
use App\Domain\Web\Providers\ParallelProvider;
use App\Domain\Web\Providers\PerplexityProvider;
use App\Domain\Web\Providers\Search\ZaiMcpSearchProvider;
use App\Domain\Web\Providers\SearxngProvider;
use App\Domain\Web\Providers\TavilyProvider;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Domain\Web\Support\StreamableMcpToolInvoker;
use App\Domain\Web\Support\WebCredentialResolver;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class WebProviderAdapterFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCredentials();

        $this->app->bind(WebRequestGuard::class, fn () => new class extends WebRequestGuard
        {
            protected function resolveIpAddresses(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
    }

    public function test_search_adapters_surface_provider_http_errors(): void
    {
        $cases = [
            [BraveProvider::class, 'https://api.search.brave.com/res/v1/web/search*'],
            [FirecrawlProvider::class, 'https://api.firecrawl.dev/v1/search'],
            [ExaProvider::class, 'https://api.exa.ai/search'],
            [ParallelProvider::class, 'https://api.parallel.ai/v1beta/search'],
            [PerplexityProvider::class, 'https://api.perplexity.ai/search'],
            [SearxngProvider::class, 'https://search.test/search*'],
            [TavilyProvider::class, 'https://api.tavily.com/search'],
            [OpenAiNativeSearchProvider::class, 'https://api.openai.com/v1/responses'],
            [AnthropicNativeSearchProvider::class, 'https://api.anthropic.com/v1/messages'],
        ];

        foreach ($cases as [$class, $url]) {
            Http::fake([$url => Http::response(['error' => 'provider down'], 500)]);

            try {
                app($class)->search(new WebSearchRequest(query: 'provider error'));
                $this->fail("{$class} did not surface provider HTTP failure.");
            } catch (WebProviderException $e) {
                $this->assertStringContainsString('failed with HTTP 500', $e->getMessage());
            }
        }
    }

    public function test_fetch_adapters_surface_provider_http_errors(): void
    {
        $cases = [
            [FirecrawlProvider::class, 'https://api.firecrawl.dev/v1/scrape'],
            [ExaProvider::class, 'https://api.exa.ai/contents'],
            [ParallelProvider::class, 'https://api.parallel.ai/v1beta/extract'],
            [TavilyProvider::class, 'https://api.tavily.com/extract'],
            [ZaiReaderFetchProvider::class, 'https://api.z.ai/api/coding/paas/v4/reader'],
        ];

        foreach ($cases as [$class, $url]) {
            Http::fake([$url => Http::response(['error' => 'provider down'], 500)]);

            try {
                app($class)->fetch(new WebFetchRequest(url: 'https://example.com/page'));
                $this->fail("{$class} did not surface provider HTTP failure.");
            } catch (WebProviderException $e) {
                $this->assertStringContainsString('failed', strtolower($e->getMessage()));
            }
        }
    }

    public function test_search_adapters_handle_malformed_success_payloads_without_fabricating_results(): void
    {
        $cases = [
            [BraveProvider::class, 'https://api.search.brave.com/res/v1/web/search*'],
            [FirecrawlProvider::class, 'https://api.firecrawl.dev/v1/search'],
            [ExaProvider::class, 'https://api.exa.ai/search'],
            [ParallelProvider::class, 'https://api.parallel.ai/v1beta/search'],
            [PerplexityProvider::class, 'https://api.perplexity.ai/search'],
            [SearxngProvider::class, 'https://search.test/search*'],
            [TavilyProvider::class, 'https://api.tavily.com/search'],
            [OpenAiNativeSearchProvider::class, 'https://api.openai.com/v1/responses'],
            [AnthropicNativeSearchProvider::class, 'https://api.anthropic.com/v1/messages'],
        ];

        foreach ($cases as [$class, $url]) {
            Http::fake([$url => Http::response(['unexpected' => 'shape'])]);

            $response = app($class)->search(new WebSearchRequest(query: 'malformed payload'));

            $this->assertContains(count($response->results), [0, 1], "{$class} returned an unexpected malformed-payload result count.");
            if ($response->results !== []) {
                $this->assertStringContainsString('native web search response', strtolower($response->results[0]->title));
            }
        }
    }

    public function test_fetch_adapters_handle_malformed_success_payloads_without_fabricating_content(): void
    {
        $cases = [
            [FirecrawlProvider::class, 'https://api.firecrawl.dev/v1/scrape'],
            [ExaProvider::class, 'https://api.exa.ai/contents'],
            [ParallelProvider::class, 'https://api.parallel.ai/v1beta/extract'],
            [TavilyProvider::class, 'https://api.tavily.com/extract'],
        ];

        foreach ($cases as [$class, $url]) {
            Http::fake([$url => Http::response(['unexpected' => 'shape'])]);

            $response = app($class)->fetch(new WebFetchRequest(url: 'https://example.com/page'));

            $this->assertSame('', $response->content, "{$class} fabricated content from a malformed payload.");
        }
    }

    public function test_credentialed_search_adapters_are_unavailable_without_credentials(): void
    {
        $this->clearCredentials();

        $providers = [
            BraveProvider::class,
            ExaProvider::class,
            FirecrawlProvider::class,
            ParallelProvider::class,
            PerplexityProvider::class,
            TavilyProvider::class,
            OpenAiNativeSearchProvider::class,
            AnthropicNativeSearchProvider::class,
        ];

        foreach ($providers as $provider) {
            $instance = app($provider);

            $this->assertFalse($instance->isAvailable(), "{$provider} reported available without credentials.");

            try {
                $instance->search(new WebSearchRequest(query: 'needs key'));
                $this->fail("{$provider} did not reject missing credentials.");
            } catch (WebProviderException $e) {
                $this->assertStringContainsString('not configured', strtolower($e->getMessage()));
            }
        }

        $zai = new ZaiMcpSearchProvider(
            app(WebCredentialResolver::class),
            Mockery::mock(StreamableMcpToolInvoker::class),
        );

        $this->assertFalse($zai->isAvailable());

        try {
            $zai->search(new WebSearchRequest(query: 'needs key'));
            $this->fail('Z.AI search did not reject missing credentials.');
        } catch (WebProviderException $e) {
            $this->assertStringContainsString('not configured', strtolower($e->getMessage()));
        }
    }

    public function test_credentialed_fetch_adapters_are_unavailable_without_credentials(): void
    {
        $this->clearCredentials();

        $providers = [
            ExaProvider::class,
            FirecrawlProvider::class,
            ParallelProvider::class,
            TavilyProvider::class,
            ZaiReaderFetchProvider::class,
        ];

        foreach ($providers as $provider) {
            $instance = app($provider);

            $this->assertFalse($instance->isAvailable(), "{$provider} reported available without credentials.");

            try {
                $instance->fetch(new WebFetchRequest(url: 'https://example.com/page'));
                $this->fail("{$provider} did not reject missing credentials.");
            } catch (WebProviderException $e) {
                $this->assertStringContainsString('not configured', strtolower($e->getMessage()));
            }
        }
    }

    public function test_zai_search_surfaces_remote_mcp_rate_limits_and_tolerates_malformed_chat_fallback(): void
    {
        config(['web.providers.zai.api_key' => 'zai-key']);

        $rateLimitedInvoker = Mockery::mock(StreamableMcpToolInvoker::class);
        $rateLimitedInvoker->shouldReceive('call')->once()->andThrow(new \RuntimeException('rate limit 429'));

        $this->expectException(WebProviderException::class);
        $this->expectExceptionMessage('rate limited');

        (new ZaiMcpSearchProvider(app(WebCredentialResolver::class), $rateLimitedInvoker))
            ->search(new WebSearchRequest(query: 'rate limit'));
    }

    public function test_zai_search_empty_mcp_and_malformed_chat_returns_empty_results(): void
    {
        config(['web.providers.zai.api_key' => 'zai-key']);

        $invoker = Mockery::mock(StreamableMcpToolInvoker::class);
        $invoker->shouldReceive('call')->once()->andReturn([]);

        Http::fake([
            'https://api.z.ai/api/coding/paas/v4/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'not json and not pipe delimited']]],
            ]),
        ]);

        $response = (new ZaiMcpSearchProvider(app(WebCredentialResolver::class), $invoker))
            ->search(new WebSearchRequest(query: 'malformed chat', includeAnswer: true));

        $this->assertSame([], $response->results);
        $this->assertNull($response->answer);
    }

    public function test_jina_and_searxng_availability_rules_do_not_require_api_keys(): void
    {
        $this->clearCredentials();
        config(['web.providers.searxng.base_url' => null]);

        $this->assertTrue(app(JinaProvider::class)->isAvailable());
        $this->assertFalse(app(SearxngProvider::class)->isAvailable());
    }

    private function configureCredentials(): void
    {
        config([
            'web.providers.brave.api_key' => 'brave-key',
            'web.providers.exa.api_key' => 'exa-key',
            'web.providers.firecrawl.api_key' => 'firecrawl-key',
            'web.providers.parallel.api_key' => 'parallel-key',
            'web.providers.perplexity.api_key' => 'perplexity-key',
            'web.providers.tavily.api_key' => 'tavily-key',
            'web.providers.openai_native.api_key' => 'openai-key',
            'web.providers.anthropic_native.api_key' => 'anthropic-key',
            'web.providers.zai.api_key' => 'zai-key',
            'web.providers.searxng.base_url' => 'https://search.test',
        ]);
    }

    private function clearCredentials(): void
    {
        config([
            'web.providers.brave.api_key' => null,
            'web.providers.exa.api_key' => null,
            'web.providers.firecrawl.api_key' => null,
            'web.providers.parallel.api_key' => null,
            'web.providers.perplexity.api_key' => null,
            'web.providers.tavily.api_key' => null,
            'web.providers.openai_native.api_key' => null,
            'web.providers.anthropic_native.api_key' => null,
            'web.providers.zai.api_key' => null,
        ]);
    }
}

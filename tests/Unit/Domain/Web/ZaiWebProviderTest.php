<?php

namespace Tests\Unit\Domain\Web;

use App\Domain\Web\Extraction\MarkdownPageExtractor;
use App\Domain\Web\Providers\Fetch\ZaiReaderFetchProvider;
use App\Domain\Web\Providers\Search\ZaiMcpSearchProvider;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Domain\Web\Support\StreamableMcpToolInvoker;
use App\Domain\Web\Support\WebCredentialResolver;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ZaiWebProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_zai_search_uses_remote_mcp_results(): void
    {
        config(['web.providers.zai.api_key' => 'zai-key']);

        $invoker = Mockery::mock(StreamableMcpToolInvoker::class);
        $invoker->shouldReceive('call')
            ->once()
            ->andReturn([[
                'title' => 'Z.AI Search Result',
                'link' => 'https://example.com/result',
                'content' => 'A useful result.',
                'media' => 'example.com',
                'publish_date' => '2026-05-24',
            ]]);

        $provider = new ZaiMcpSearchProvider(app(WebCredentialResolver::class), $invoker);
        $response = $provider->search(new WebSearchRequest(query: 'adapter based web search'));

        $this->assertSame('zai', $response->provider);
        $this->assertSame('Z.AI Search Result', $response->results[0]->title);
        $this->assertSame('remote_mcp', $response->metadata['transport']);
    }

    public function test_zai_search_falls_back_to_coding_chat_search(): void
    {
        config([
            'web.providers.zai.api_key' => 'zai-key',
            'web.providers.zai.base_url' => 'https://api.z.ai/api/coding/paas/v4',
        ]);

        $invoker = Mockery::mock(StreamableMcpToolInvoker::class);
        $invoker->shouldReceive('call')->once()->andReturn([]);

        Http::fake([
            'https://api.z.ai/api/coding/paas/v4/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'answer' => 'Use a provider manager with fallbacks.',
                            'results' => [[
                                'title' => 'Provider Manager Notes',
                                'url' => 'https://example.com/providers',
                                'source' => 'example.com',
                                'published_at' => '2026-05-24',
                                'snippet' => 'Fallback providers keep the tool flexible.',
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);

        $provider = new ZaiMcpSearchProvider(app(WebCredentialResolver::class), $invoker);
        $response = $provider->search(new WebSearchRequest(query: 'zai', includeAnswer: true));

        $this->assertSame('Use a provider manager with fallbacks.', $response->answer);
        $this->assertSame('Provider Manager Notes', $response->results[0]->title);
        $this->assertSame('chat_search', $response->metadata['transport']);
    }

    public function test_zai_chat_search_parses_json_fences_and_line_fallback(): void
    {
        config([
            'web.providers.zai.api_key' => 'zai-key',
            'web.providers.zai.base_url' => 'https://api.z.ai/api/coding/paas/v4',
        ]);

        $invoker = Mockery::mock(StreamableMcpToolInvoker::class);
        $invoker->shouldReceive('call')->twice()->andReturn([]);

        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'content' => "```json\n{\"answer\":\"Fenced answer\",\"results\":[{\"title\":\"Fenced Result\",\"url\":\"https://example.com/fenced\",\"source\":\"example.com\",\"snippet\":\"Snippet\"}]}\n```",
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => [
                        'content' => 'Line Result | https://example.com/line | example.com | 2026-05-24',
                    ],
                ]],
            ]);

        $provider = new ZaiMcpSearchProvider(app(WebCredentialResolver::class), $invoker);
        $fenced = $provider->search(new WebSearchRequest(query: 'fenced search', includeAnswer: true));
        $line = $provider->search(new WebSearchRequest(query: 'line search', includeAnswer: true));

        $this->assertSame('Fenced answer', $fenced->answer);
        $this->assertSame('Fenced Result', $fenced->results[0]->title);
        $this->assertSame('Line Result', $line->results[0]->title);
    }

    public function test_zai_reader_fetch_normalizes_markdown_sections(): void
    {
        config([
            'web.providers.zai.api_key' => 'zai-key',
            'web.providers.zai.base_url' => 'https://api.z.ai/api/coding/paas/v4',
        ]);

        Http::fake([
            'https://api.z.ai/api/coding/paas/v4/reader' => Http::response([
                'request_id' => 'req_123',
                'model' => 'reader',
                'reader_result' => [
                    'title' => 'Reader Title',
                    'url' => 'https://example.com/page',
                    'content' => "# Reader Title\n\n## Details\n\nReader content.",
                    'metadata' => ['site' => 'example'],
                ],
            ]),
        ]);

        $provider = new ZaiReaderFetchProvider(
            app(WebCredentialResolver::class),
            new class extends WebRequestGuard
            {
                protected function resolveIpAddresses(string $host): array
                {
                    return ['93.184.216.34'];
                }
            },
            app(MarkdownPageExtractor::class),
        );

        $response = $provider->fetch(new WebFetchRequest(url: 'https://example.com/page'));

        $this->assertSame('zai_reader', $response->extractionMethod);
        $this->assertSame('Details', $response->outline[1]['title']);
        $this->assertArrayHasKey('details', $response->sections);
    }
}

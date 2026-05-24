<?php

namespace Tests\Unit\Domain\Web;

use App\Domain\Web\Extraction\HtmlPageExtractor;
use App\Domain\Web\Extraction\MarkdownPageExtractor;
use App\Domain\Web\Providers\Fetch\DirectFetchProvider;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DirectFetchProviderTest extends TestCase
{
    public function test_sends_browser_like_headers_and_extracts_html(): void
    {
        Http::fake([
            'https://example.com/page' => Http::response('<html><head><title>Example</title><meta name="description" content="Demo"></head><body><nav>Skip</nav><main><h1>Hello</h1><p>Useful content.</p></main></body></html>', 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]),
        ]);

        $provider = new DirectFetchProvider(
            new class extends WebRequestGuard
            {
                protected function resolveIpAddresses(string $host): array
                {
                    return ['93.184.216.34'];
                }
            },
            new HtmlPageExtractor(new MarkdownPageExtractor),
        );

        $response = $provider->fetch(new WebFetchRequest('https://example.com/page'));

        $this->assertSame('Example', $response->title);
        $this->assertStringContainsString('Useful content.', $response->content);
        $this->assertStringNotContainsString('Skip', $response->content);
        $this->assertSame('html_dom', $response->extractionMethod);

        Http::assertSent(fn ($request) => $request->hasHeader('User-Agent', config('web.fetch.user_agent'))
            && $request->hasHeader('Accept-Encoding', 'gzip, deflate')
            && $request->hasHeader('Upgrade-Insecure-Requests', '1'));
    }

    public function test_rejects_responses_over_configured_byte_limit(): void
    {
        config(['web.fetch.max_bytes' => 4]);

        Http::fake([
            'https://example.com/large' => Http::response('too large', 200, [
                'Content-Type' => 'text/plain',
            ]),
        ]);

        $provider = new DirectFetchProvider(
            new class extends WebRequestGuard
            {
                protected function resolveIpAddresses(string $host): array
                {
                    return ['93.184.216.34'];
                }
            },
            new HtmlPageExtractor(new MarkdownPageExtractor),
        );

        $this->expectExceptionMessage('maximum allowed size');

        $provider->fetch(new WebFetchRequest('https://example.com/large'));
    }

    public function test_decodes_gzip_and_deflate_bodies(): void
    {
        Http::fake([
            'https://example.com/gzip' => Http::response(gzencode('gzip body'), 200, [
                'Content-Type' => 'text/plain',
                'Content-Encoding' => 'gzip',
            ]),
            'https://example.com/deflate' => Http::response(gzdeflate('deflate body'), 200, [
                'Content-Type' => 'text/plain',
                'Content-Encoding' => 'deflate',
            ]),
        ]);

        $provider = new DirectFetchProvider(
            new class extends WebRequestGuard
            {
                protected function resolveIpAddresses(string $host): array
                {
                    return ['93.184.216.34'];
                }
            },
            new HtmlPageExtractor(new MarkdownPageExtractor),
        );

        $this->assertSame('gzip body', $provider->fetch(new WebFetchRequest('https://example.com/gzip'))->content);
        $this->assertSame('deflate body', $provider->fetch(new WebFetchRequest('https://example.com/deflate'))->content);
    }
}

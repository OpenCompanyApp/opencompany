<?php

namespace Tests\Unit\Domain\Web;

use App\Domain\Web\Extraction\HtmlPageExtractor;
use App\Domain\Web\Extraction\MarkdownPageExtractor;
use App\Domain\Web\Providers\Fetch\DirectFetchProvider;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Domain\Web\Support\WebCredentialResolver;
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
            new class extends WebRequestGuard {
                protected function resolveIpAddresses(string $host): array
                {
                    return ['93.184.216.34'];
                }
            },
            new HtmlPageExtractor(new MarkdownPageExtractor),
            app(WebCredentialResolver::class),
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
}

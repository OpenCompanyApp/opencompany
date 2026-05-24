<?php

namespace App\Domain\Web\Providers\Fetch;

use App\Domain\Web\Contracts\WebFetchProvider;
use App\Domain\Web\Enums\WebCapability;
use App\Domain\Web\Exceptions\WebFetchPermanentException;
use App\Domain\Web\Exceptions\WebProviderException;
use App\Domain\Web\Extraction\HtmlPageExtractor;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Domain\Web\Support\WebCredentialResolver;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebFetchResponse;
use Illuminate\Support\Facades\Http;

class DirectFetchProvider implements WebFetchProvider
{
    public function __construct(
        private WebRequestGuard $guard,
        private HtmlPageExtractor $extractor,
        private WebCredentialResolver $credentials,
    ) {}

    public function id(): string
    {
        return 'direct';
    }

    public function label(): string
    {
        return 'Direct Fetch';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function supports(WebCapability $capability): bool
    {
        return $capability === WebCapability::Fetch;
    }

    public function fetch(WebFetchRequest $request): WebFetchResponse
    {
        $this->guard->assertSafePublicUrl($request->url);

        $timeout = $request->timeoutSeconds ?? (int) config('web.fetch.timeout_seconds', 30);
        $response = Http::withHeaders($this->headers())
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => ['track_redirects' => true, 'max' => 5]])
            ->get($request->url);

        $body = $response->body();
        $finalUrl = (string) ($response->handlerStats()['url'] ?? $request->url);
        $this->guard->assertSafePublicUrl($finalUrl);

        $maxBytes = (int) config('web.fetch.max_bytes', 10485760);
        if (strlen($body) > $maxBytes) {
            throw new WebProviderException('Fetched page exceeds the maximum allowed size.');
        }

        if (! $response->successful()) {
            $class = $this->isPermanentStatus($response->status())
                ? WebFetchPermanentException::class
                : WebProviderException::class;

            throw new $class("Direct fetch failed ({$response->status()}) for {$request->url}.");
        }

        $contentType = strtolower(trim(explode(';', $response->header('content-type') ?? 'text/plain')[0]));

        if (str_contains($contentType, 'html') || $contentType === 'application/xhtml+xml') {
            $page = $this->extractor->extract($body, $finalUrl);

            return new WebFetchResponse(
                provider: $this->id(),
                url: $request->url,
                finalUrl: $finalUrl,
                statusCode: $response->status(),
                contentType: $contentType,
                format: $request->format,
                title: $page->title,
                metadata: $page->metadata,
                outline: $page->outline,
                sections: $page->sections,
                content: $page->fullContent,
                rawHtml: $body,
                extractionMethod: 'html_dom',
                meta: ['bytes' => strlen($body), 'headers' => $this->headers()],
            );
        }

        if (str_starts_with($contentType, 'text/')) {
            $content = trim(mb_convert_encoding($body, 'UTF-8', 'UTF-8'));

            return new WebFetchResponse(
                provider: $this->id(),
                url: $request->url,
                finalUrl: $finalUrl,
                statusCode: $response->status(),
                contentType: $contentType,
                format: 'text',
                title: null,
                metadata: [],
                outline: [],
                sections: ['full' => $content],
                content: $content,
                extractionMethod: 'plain_text',
                meta: ['bytes' => strlen($body), 'headers' => $this->headers()],
            );
        }

        throw new WebProviderException("Unsupported content type for direct fetch: {$contentType}");
    }

    public function headers(): array
    {
        return [
            'User-Agent' => (string) config('web.fetch.user_agent'),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.8,*/*;q=0.7',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    private function isPermanentStatus(int $status): bool
    {
        return $status >= 400 && $status < 500 && ! in_array($status, [408, 429], true);
    }
}

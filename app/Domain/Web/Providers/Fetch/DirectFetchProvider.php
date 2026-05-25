<?php

namespace App\Domain\Web\Providers\Fetch;

use App\Domain\Web\Contracts\WebFetchProvider;
use App\Domain\Web\Enums\WebCapability;
use App\Domain\Web\Exceptions\WebFetchPermanentException;
use App\Domain\Web\Exceptions\WebProviderException;
use App\Domain\Web\Extraction\HtmlPageExtractor;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebFetchResponse;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;

class DirectFetchProvider implements WebFetchProvider
{
    public function __construct(
        private WebRequestGuard $guard,
        private HtmlPageExtractor $extractor,
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
        $this->guard->assertSafePublicUrl($request->url, allowConfiguredPrivateHosts: true);

        $timeout = $request->timeoutSeconds ?? (int) config('web.fetch.timeout_seconds', 30);
        $response = Http::withHeaders($this->headers())
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => ['track_redirects' => true, 'max' => 5]])
            ->get($request->url);

        $body = $this->decodeBody($response->body(), (string) $response->header('content-encoding'));
        $finalUrl = (string) ($response->handlerStats()['url'] ?? $request->url);
        $this->guard->assertSafePublicUrl($finalUrl, allowConfiguredPrivateHosts: true);

        $maxBytes = $this->maxBytes();
        if (strlen($body) > $maxBytes) {
            throw new WebProviderException('Fetched page exceeds the maximum allowed size.');
        }

        if (! $response->successful()) {
            $class = $this->isPermanentStatus($response->status())
                ? WebFetchPermanentException::class
                : WebProviderException::class;

            throw new $class("Direct fetch failed ({$response->status()}) for {$request->url}.");
        }

        $contentTypeHeader = trim((string) $response->header('content-type'));
        $contentType = strtolower(trim(explode(';', $contentTypeHeader !== '' ? $contentTypeHeader : 'text/plain')[0]));

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

    /**
     * @return array<string, string>
     */
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

    private function decodeBody(string $body, string $encoding): string
    {
        $encoding = strtolower(trim($encoding));

        if ($encoding === 'gzip' || $encoding === 'x-gzip') {
            $decoded = gzdecode($body);

            return is_string($decoded) ? $decoded : $body;
        }

        if ($encoding === 'deflate') {
            $decoded = @gzinflate($body);
            if (is_string($decoded)) {
                return $decoded;
            }

            $decoded = @gzuncompress($body);

            return is_string($decoded) ? $decoded : $body;
        }

        return $body;
    }

    private function maxBytes(): int
    {
        $default = (int) config('web.fetch.max_bytes', 10485760);
        $value = app()->bound('currentWorkspace') ? AppSetting::getValue('web_fetch_max_bytes', $default) : $default;

        return max(1, (int) $value);
    }
}

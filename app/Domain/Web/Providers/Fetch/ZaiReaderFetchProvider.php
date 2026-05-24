<?php

namespace App\Domain\Web\Providers\Fetch;

use App\Domain\Web\Contracts\WebFetchProvider;
use App\Domain\Web\Enums\WebCapability;
use App\Domain\Web\Exceptions\WebProviderException;
use App\Domain\Web\Extraction\MarkdownPageExtractor;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Domain\Web\Support\WebCredentialResolver;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebFetchResponse;
use Illuminate\Support\Facades\Http;

class ZaiReaderFetchProvider implements WebFetchProvider
{
    public function __construct(
        private WebCredentialResolver $credentials,
        private WebRequestGuard $guard,
        private MarkdownPageExtractor $extractor,
    ) {}

    public function id(): string
    {
        return 'zai';
    }

    public function label(): string
    {
        return 'Z.AI';
    }

    public function isAvailable(): bool
    {
        return $this->credentials->apiKey($this->id()) !== '';
    }

    public function supports(WebCapability $capability): bool
    {
        return $capability === WebCapability::Fetch;
    }

    public function fetch(WebFetchRequest $request): WebFetchResponse
    {
        $apiKey = $this->credentials->apiKey($this->id());
        if ($apiKey === '') {
            throw new WebProviderException('Z.AI web reader is not configured.');
        }

        $this->guard->assertSafePublicUrl($request->url);
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout($request->timeoutSeconds ?? 30)
            ->post(rtrim($this->baseUrl(), '/').'/reader', [
                'url' => $request->url,
                'timeout' => $request->timeoutSeconds ?? 30,
                'return_format' => $request->format === 'text' ? 'text' : 'markdown',
                'no_cache' => false,
                'retain_images' => true,
                'with_links_summary' => true,
            ]);

        if (! $response->successful() || ! is_array($response->json())) {
            throw new WebProviderException("Z.AI reader failed ({$response->status()}): ".substr($response->body(), 0, 1000));
        }

        $reader = $response->json('reader_result');
        if (! is_array($reader)) {
            throw new WebProviderException('Z.AI reader returned no reader_result payload.');
        }

        $content = trim((string) ($reader['content'] ?? ''));
        $title = $this->nullableString($reader['title'] ?? null);
        $finalUrl = $this->nullableString($reader['url'] ?? null) ?? $request->url;
        $metadata = is_array($reader['metadata'] ?? null) ? $reader['metadata'] : [];
        if (($description = $this->nullableString($reader['description'] ?? null)) !== null) {
            $metadata['description'] ??= $description;
        }
        $metadata['title'] ??= $title;
        $metadata['canonical_url'] ??= $finalUrl;

        $page = $this->extractor->extract($content, $title, $metadata);

        return new WebFetchResponse($this->id(), $request->url, $finalUrl, $response->status(), 'text/markdown', $request->format === 'text' ? 'text' : 'markdown', $page->title, $page->metadata, $page->outline, $page->sections, $page->fullContent, extractionMethod: 'zai_reader', meta: ['transport' => 'rest', 'model' => $this->nullableString($response->json('model')), 'request_id' => $this->nullableString($response->json('request_id'))]);
    }

    private function baseUrl(): string
    {
        return $this->credentials->baseUrl($this->id(), 'base_url') ?? 'https://api.z.ai/api/coding/paas/v4';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

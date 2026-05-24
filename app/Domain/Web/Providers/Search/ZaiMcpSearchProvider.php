<?php

namespace App\Domain\Web\Providers\Search;

use App\Domain\Web\Contracts\WebSearchProvider;
use App\Domain\Web\Enums\WebCapability;
use App\Domain\Web\Exceptions\WebProviderException;
use App\Domain\Web\Support\StreamableMcpToolInvoker;
use App\Domain\Web\Support\WebCredentialResolver;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Domain\Web\ValueObjects\WebSearchResponse;
use App\Domain\Web\ValueObjects\WebSearchResult;
use Illuminate\Support\Facades\Http;

class ZaiMcpSearchProvider implements WebSearchProvider
{
    public function __construct(
        private WebCredentialResolver $credentials,
        private StreamableMcpToolInvoker $invoker,
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
        return $capability === WebCapability::Search;
    }

    public function search(WebSearchRequest $request): WebSearchResponse
    {
        $apiKey = $this->credentials->apiKey($this->id());
        if ($apiKey === '') {
            throw new WebProviderException('Z.AI web search is not configured.');
        }

        $answer = null;
        $transport = 'remote_mcp';
        $results = $this->searchViaMcp($request, $apiKey);

        if ($results === [] || $this->shouldPreferChatSearch($request) || ($request->includeAnswer && trim((string) $answer) === '')) {
            ['results' => $chatResults, 'answer' => $chatAnswer] = $this->searchViaChatSearch($request, $apiKey);
            if ($chatResults !== []) {
                $results = $chatResults;
                $transport = 'chat_search';
            }
            if (trim((string) $chatAnswer) !== '') {
                $answer = $chatAnswer;
            }
        }

        return new WebSearchResponse($this->id(), $request->query, array_slice($results, 0, $request->maxResults), $answer, [
            'transport' => $transport,
            'remote_url' => $this->remoteUrl(),
        ]);
    }

    /**
     * @return list<WebSearchResult>
     */
    private function searchViaMcp(WebSearchRequest $request, string $apiKey): array
    {
        $arguments = [
            'search_query' => $request->query,
            'content_size' => $request->searchDepth === 'advanced' ? 'high' : 'medium',
        ];
        if ($request->allowedDomains !== []) {
            $arguments['search_domain_filter'] = implode(',', $request->allowedDomains);
        }

        try {
            $payload = $this->invoker->call($this->remoteUrl(), 'web_search_prime', $arguments, ['Authorization' => 'Bearer '.$apiKey]);
        } catch (\Throwable $e) {
            if ($this->isRateLimitError($e->getMessage())) {
                throw new WebProviderException('Z.AI web search is rate limited. Please retry shortly.', 0, $e);
            }

            return [];
        }

        if (! is_array($payload)) {
            return [];
        }

        $results = [];
        foreach ($payload as $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = (string) ($item['link'] ?? '');
            if ($url === '' || $this->isBlockedDomain($url, $request->blockedDomains)) {
                continue;
            }
            $results[] = new WebSearchResult((string) ($item['title'] ?? $url), $url, $request->includeSnippets ? trim((string) ($item['content'] ?? '')) : '', publishedAt: isset($item['publish_date']) ? (string) $item['publish_date'] : null, source: (string) ($item['media'] ?? 'zai'));
        }

        return $results;
    }

    /**
     * @return array{results: list<WebSearchResult>, answer: ?string}
     */
    private function searchViaChatSearch(WebSearchRequest $request, string $apiKey): array
    {
        $webSearch = [
            'enable' => true,
            'search_engine' => 'search-prime',
            'search_result' => true,
            'count' => max(1, min(10, $request->maxResults)),
            'content_size' => $request->searchDepth === 'advanced' ? 'high' : 'medium',
        ];
        if ($request->allowedDomains !== []) {
            $webSearch['search_domain_filter'] = implode(',', $request->allowedDomains);
        }

        $instruction = sprintf(
            'Search the web for: %s. Return only valid JSON with keys "answer" and "results". "answer" should be a concise summary when requested, otherwise null. "results" should be an array of up to %d objects with keys: title, url, source, published_at, snippet. Do not wrap the JSON in markdown fences.',
            $request->query,
            max(1, min(10, $request->maxResults)),
        );
        if (! $request->includeAnswer) {
            $instruction .= ' Set "answer" to null.';
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(60)
            ->post(rtrim($this->chatBaseUrl(), '/').'/chat/completions', [
                'model' => (string) ($this->credentials->providerConfig($this->id())['chat_model'] ?? 'glm-5.1'),
                'messages' => [['role' => 'user', 'content' => $instruction]],
                'tools' => [['type' => 'web_search', 'web_search' => $webSearch]],
                'temperature' => 0,
            ]);

        if (! $response->successful()) {
            return ['results' => [], 'answer' => null];
        }

        $content = (string) ($response->json('choices.0.message.content') ?? '');

        return $this->parseChatSearchPayload($content) ?? $this->parseLineFallback($content, $request);
    }

    /**
     * @return ?array{results: list<WebSearchResult>, answer: ?string}
     */
    private function parseChatSearchPayload(string $content): ?array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return null;
        }
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        $data = json_decode($trimmed, true);
        if (! is_array($data)) {
            return null;
        }

        $results = [];
        foreach (is_array($data['results'] ?? null) ? $data['results'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = trim((string) ($item['url'] ?? ''));
            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $results[] = new WebSearchResult(trim((string) ($item['title'] ?? $url)) ?: $url, $url, trim((string) ($item['snippet'] ?? '')), publishedAt: ($publishedAt = trim((string) ($item['published_at'] ?? ''))) !== '' ? $publishedAt : null, source: ($source = trim((string) ($item['source'] ?? ''))) !== '' ? $source : 'zai');
        }

        $answer = is_string($data['answer'] ?? null) && trim($data['answer']) !== '' ? trim($data['answer']) : null;

        return ['results' => $results, 'answer' => $answer];
    }

    /**
     * @return array{results: list<WebSearchResult>, answer: ?string}
     */
    private function parseLineFallback(string $content, WebSearchRequest $request): array
    {
        $results = [];
        foreach (preg_split("/\r\n|\n|\r/", trim($content)) ?: [] as $line) {
            $parts = array_map('trim', explode(' | ', trim($line)));
            if (count($parts) < 4) {
                continue;
            }
            [$title, $url, $source, $publishedAt] = array_pad($parts, 4, '');
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && ! $this->isBlockedDomain($url, $request->blockedDomains)) {
                $results[] = new WebSearchResult($title !== '' ? $title : $url, $url, '', publishedAt: $publishedAt !== '' ? $publishedAt : null, source: $source !== '' ? $source : 'zai');
            }
        }

        return ['results' => $results, 'answer' => null];
    }

    private function shouldPreferChatSearch(WebSearchRequest $request): bool
    {
        $wordCount = count(array_filter(preg_split('/\s+/', trim($request->query)) ?: []));

        return $request->includeAnswer || $wordCount <= 1 || mb_strlen(trim($request->query)) < 16;
    }

    private function isRateLimitError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'rate limit') || str_contains($message, '1302') || str_contains($message, '-429') || str_contains($message, '429');
    }

    /**
     * @param  list<string>  $blockedDomains
     */
    private function isBlockedDomain(string $url, array $blockedDomains): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach ($blockedDomains as $domain) {
            $domain = strtolower(trim($domain));
            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain))) {
                return true;
            }
        }

        return false;
    }

    private function remoteUrl(): string
    {
        return $this->credentials->baseUrl($this->id(), 'remote_url') ?? 'https://api.z.ai/api/mcp/web_search_prime/mcp';
    }

    private function chatBaseUrl(): string
    {
        return $this->credentials->baseUrl($this->id(), 'base_url') ?? 'https://api.z.ai/api/coding/paas/v4';
    }
}

<?php

namespace App\Domain\Web\Providers;

use App\Domain\Web\Contracts\WebSearchProvider;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Domain\Web\ValueObjects\WebSearchResponse;
use App\Domain\Web\ValueObjects\WebSearchResult;

class AnthropicNativeSearchProvider extends AbstractWebProvider implements WebSearchProvider
{
    public function id(): string
    {
        return 'anthropic_native';
    }

    public function search(WebSearchRequest $request): WebSearchResponse
    {
        $tool = [
            'type' => 'web_search_20250305',
            'name' => 'web_search',
            'max_uses' => (int) ($this->providerConfig()['max_uses'] ?? 5),
        ];
        if ($request->allowedDomains !== []) {
            $tool['allowed_domains'] = $request->allowedDomains;
        } elseif ($request->blockedDomains !== []) {
            $tool['blocked_domains'] = $request->blockedDomains;
        }

        $data = $this->postJson($this->baseUrl(fallback: 'https://api.anthropic.com').'/v1/messages', [
            'model' => (string) ($this->providerConfig()['model'] ?? 'claude-sonnet-4-20250514'),
            'max_tokens' => 2048,
            'messages' => [['role' => 'user', 'content' => 'Search the web and cite sources for: '.$request->query]],
            'tools' => [$tool],
        ], [
            'x-api-key' => $this->apiKey(),
            'anthropic-version' => '2023-06-01',
        ], $request->timeoutSeconds ?? 30);

        $answer = $this->extractText($data);
        $results = $this->extractCitations($data, $answer, $request);
        if ($results === []) {
            $results[] = new WebSearchResult('Anthropic native web search response', '', $this->limit($answer, $request->outputLimitChars));
        }

        return new WebSearchResponse($this->id(), $request->query, $results, $this->limit($answer, $request->outputLimitChars), ['id' => $data['id'] ?? null, 'usage' => $data['usage'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractText(array $data): string
    {
        $parts = [];
        foreach (is_array($data['content'] ?? null) ? $data['content'] : [] as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $parts[] = $block['text'];
            }
        }

        return trim(implode("\n\n", $parts));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<WebSearchResult>
     */
    private function extractCitations(array $data, string $answer, WebSearchRequest $request): array
    {
        $results = [];
        foreach (is_array($data['content'] ?? null) ? $data['content'] : [] as $block) {
            foreach (is_array($block['citations'] ?? null) ? $block['citations'] : [] as $citation) {
                if (is_array($citation)) {
                    $url = $this->string($citation, 'url');
                    if ($url !== '') {
                        $results[$url] = new WebSearchResult($this->string($citation, 'title', $url), $url, $request->includeSnippets ? $this->limit($answer, 700) : '', source: $this->sourceUrl($url));
                    }
                }
            }
        }

        return array_values($results);
    }
}

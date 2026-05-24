<?php

namespace App\Domain\Web\Providers;

use App\Domain\Web\Contracts\WebSearchProvider;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Domain\Web\ValueObjects\WebSearchResponse;
use App\Domain\Web\ValueObjects\WebSearchResult;

class OpenAiNativeSearchProvider extends AbstractWebProvider implements WebSearchProvider
{
    public function id(): string
    {
        return 'openai_native';
    }

    public function search(WebSearchRequest $request): WebSearchResponse
    {
        $tool = [
            'type' => 'web_search',
            'external_web_access' => ($request->mode ?? 'cached') !== 'cached',
        ];
        if ($request->allowedDomains !== []) {
            $tool['filters'] = ['allowed_domains' => $request->allowedDomains];
        }

        $data = $this->postJson($this->baseUrl(fallback: 'https://api.openai.com/v1').'/responses', [
            'model' => (string) ($this->providerConfig()['model'] ?? 'gpt-5'),
            'tools' => [$tool],
            'tool_choice' => 'auto',
            'include' => ['web_search_call.action.sources'],
            'input' => 'Search the web for this query and return the most relevant cited findings: '.$request->query,
        ], ['Authorization' => 'Bearer '.$this->apiKey()], $request->timeoutSeconds ?? 30);

        $answer = $this->extractText($data);
        $results = $this->extractCitations($data, $answer, $request);
        if ($results === []) {
            $results[] = new WebSearchResult('OpenAI native web search response', '', $this->limit($answer, $request->outputLimitChars));
        }

        return new WebSearchResponse($this->id(), $request->query, $results, $this->limit($answer, $request->outputLimitChars), ['id' => $data['id'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractText(array $data): string
    {
        if (is_string($data['output_text'] ?? null)) {
            return $data['output_text'];
        }

        $parts = [];
        foreach (is_array($data['output'] ?? null) ? $data['output'] : [] as $item) {
            foreach (is_array($item['content'] ?? null) ? $item['content'] : [] as $content) {
                if (is_array($content) && is_string($content['text'] ?? null)) {
                    $parts[] = $content['text'];
                }
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
        foreach (is_array($data['output'] ?? null) ? $data['output'] : [] as $item) {
            foreach (is_array($item['content'] ?? null) ? $item['content'] : [] as $content) {
                foreach (is_array($content['annotations'] ?? null) ? $content['annotations'] : [] as $annotation) {
                    if (! is_array($annotation) || ($annotation['type'] ?? null) !== 'url_citation') {
                        continue;
                    }
                    $url = $this->string($annotation, 'url');
                    if ($url !== '') {
                        $results[$url] = new WebSearchResult($this->string($annotation, 'title', $url), $url, $request->includeSnippets ? $this->limit($answer, 700) : '', source: $this->sourceUrl($url));
                    }
                }
            }
        }

        return array_values($results);
    }
}

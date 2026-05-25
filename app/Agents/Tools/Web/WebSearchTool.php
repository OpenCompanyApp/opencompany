<?php

namespace App\Agents\Tools\Web;

use App\Domain\Web\Managers\WebSearchProviderManager;
use App\Domain\Web\Support\WebToolFormatter;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class WebSearchTool implements Tool
{
    public function __construct(
        private User $agent,
        private WebSearchProviderManager $providers,
        private WebToolFormatter $formatter,
    ) {}

    public function description(): string
    {
        return 'Search the web with an adapter-backed provider. Use this to discover relevant sources before fetching them. Prefer web_fetch metadata, outline, or section mode after selecting a result.';
    }

    public function handle(Request $request): string
    {
        try {
            $search = new WebSearchRequest(
                query: trim((string) ($request['query'] ?? '')),
                provider: $this->nullableString($request['provider'] ?? null),
                maxResults: max(1, min(20, (int) ($request['max_results'] ?? $request['maxResults'] ?? $this->settingValue('web_search_max_results', config('web.search.max_results', 8))))),
                allowedDomains: $this->stringList($request['allowed_domains'] ?? $request['allowedDomains'] ?? $this->settingValue('web_allowed_domains', [])),
                blockedDomains: $this->stringList($request['blocked_domains'] ?? $request['blockedDomains'] ?? $this->settingValue('web_blocked_domains', [])),
                searchDepth: (string) ($request['search_depth'] ?? $request['searchDepth'] ?? 'basic'),
                includeSnippets: filter_var($request['include_snippets'] ?? $request['includeSnippets'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
                includeAnswer: filter_var($request['include_answer'] ?? $request['includeAnswer'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                mode: $this->nullableString($request['mode'] ?? null),
                country: $this->nullableString($request['country'] ?? $this->settingValue('web_country', null)),
                language: $this->nullableString($request['language'] ?? $this->settingValue('web_language', null)),
                recency: $this->nullableString($request['recency'] ?? $this->settingValue('web_recency', null)),
                timeoutSeconds: isset($request['timeout_seconds']) ? (int) $request['timeout_seconds'] : (isset($request['timeoutSeconds']) ? (int) $request['timeoutSeconds'] : null),
                outputLimitChars: (int) ($request['output_limit_chars'] ?? $request['outputLimitChars'] ?? config('web.search.output_limit_chars', 60000)),
                noCache: filter_var($request['no_cache'] ?? $request['noCache'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                workspaceId: app()->bound('currentWorkspace') ? workspace()->id : null,
                agentId: $this->agent->id,
            );

            return $this->formatter->search($this->providers->search($search));
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Search query.')->required(),
            'provider' => $schema->string()->description('Optional provider override, e.g. tavily, zai, exa, brave, firecrawl, jina, searxng, perplexity, openai_native, anthropic_native.'),
            'max_results' => $schema->integer()->description('Maximum number of search results.'),
            'allowed_domains' => $schema->array()->description('Optional domain allowlist.'),
            'blocked_domains' => $schema->array()->description('Optional domain blocklist.'),
            'search_depth' => $schema->string()->description('Search depth: basic or advanced.'),
            'include_snippets' => $schema->boolean()->description('Include result snippets when supported.'),
            'include_answer' => $schema->boolean()->description('Request an answer/summary when supported.'),
            'mode' => $schema->string()->description('Provider-specific mode/depth hint: auto, fast, deep, basic, or advanced.'),
            'country' => $schema->string()->description('Optional country hint.'),
            'language' => $schema->string()->description('Optional language hint.'),
            'recency' => $schema->string()->description('Optional recency hint, provider-specific.'),
            'timeout_seconds' => $schema->integer()->description('Optional timeout in seconds.'),
            'output_limit_chars' => $schema->integer()->description('Maximum provider answer/content characters.'),
            'no_cache' => $schema->boolean()->description('Bypass cached results.'),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(fn ($item) => is_string($item) ? trim($item) : '', $value))));
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        return app()->bound('currentWorkspace') ? AppSetting::getValue($key, $default) : $default;
    }
}

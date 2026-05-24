<?php

namespace App\Agents\Tools\Web;

use App\Domain\Web\Exceptions\WebFetchPermanentException;
use App\Domain\Web\Extraction\MarkdownPageExtractor;
use App\Domain\Web\Managers\WebFetchProviderManager;
use App\Domain\Web\Support\WebToolFormatter;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebFetchResponse;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class WebFetchTool implements Tool
{
    public function __construct(
        private User $agent,
        private WebFetchProviderManager $providers,
        private WebToolFormatter $formatter,
        private MarkdownPageExtractor $markdown,
    ) {}

    public function description(): string
    {
        return 'Fetch and extract a web page with provider selection and context-aware modes: metadata, outline, main, full, section, match, and chunk.';
    }

    public function handle(Request $request): string
    {
        try {
            $fetch = new WebFetchRequest(
                url: trim((string) ($request['url'] ?? '')),
                provider: $this->nullableString($request['provider'] ?? null),
                mode: (string) ($request['mode'] ?? 'main'),
                format: (string) ($request['format'] ?? 'markdown'),
                maxChars: max(50, min(50000, (int) ($request['max_chars'] ?? $request['maxChars'] ?? $this->settingValue('web_fetch_max_chars', config('web.fetch.max_chars', 12000))))),
                summarize: filter_var($request['summarize'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                prompt: $this->nullableString($request['prompt'] ?? null),
                heading: $this->nullableString($request['heading'] ?? null),
                sectionId: $this->nullableString($request['section_id'] ?? $request['sectionId'] ?? null),
                match: $this->nullableString($request['match'] ?? null),
                startAfter: $this->nullableString($request['start_after'] ?? $request['startAfter'] ?? null),
                endBefore: $this->nullableString($request['end_before'] ?? $request['endBefore'] ?? null),
                chunkToken: $this->nullableString($request['chunk_token'] ?? $request['chunkToken'] ?? null),
                timeoutSeconds: isset($request['timeout_seconds']) ? (int) $request['timeout_seconds'] : (isset($request['timeoutSeconds']) ? (int) $request['timeoutSeconds'] : (isset($request['timeout']) ? (int) $request['timeout'] : null)),
                strategy: (string) ($request['strategy'] ?? 'auto'),
                includeMetadata: filter_var($request['include_metadata'] ?? $request['includeMetadata'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
                includeOutline: filter_var($request['include_outline'] ?? $request['includeOutline'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
                outputLimitChars: (int) ($request['output_limit_chars'] ?? $request['outputLimitChars'] ?? config('web.fetch.output_limit_chars', 100000)),
                noCache: filter_var($request['no_cache'] ?? $request['noCache'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                workspaceId: app()->bound('currentWorkspace') ? workspace()->id : null,
                agentId: $this->agent->id,
            );

            $fetch = $fetch->normalized();
            $base = $this->providers->fetch($fetch);
            $selected = $this->selectContent($base, $fetch);
            $selectedContent = $selected['content'];

            if ($fetch->format === 'text' && ! in_array($selected['source'], ['metadata', 'outline'], true)) {
                $selectedContent = $this->markdown->markdownToPlainText($selectedContent);
            }

            [$content, $truncated, $nextChunkToken] = $this->sliceContent($selectedContent, $fetch->maxChars, $fetch, $selected['source']);
            $response = $base->withContent(
                $fetch->format === 'html' && $selected['source'] === 'content'
                    ? ($base->rawHtml ?? $content)
                    : $content,
                $truncated,
                $nextChunkToken,
                ['selected_source' => $selected['source']],
            );

            return $this->formatter->fetch($response, $fetch->mode, $fetch->includeMetadata, $fetch->includeOutline);
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->description('The page URL to fetch.')->required(),
            'provider' => $schema->string()->description('Optional provider override, e.g. direct, jina, firecrawl, tavily, exa, parallel, zai.'),
            'mode' => $schema->string()->description('Fetch mode: metadata, outline, main, full, section, match, or chunk.'),
            'format' => $schema->string()->description('Preferred output format: markdown, text, or html.'),
            'max_chars' => $schema->integer()->description('Maximum content characters before chunking.'),
            'summarize' => $schema->boolean()->description('Reserved for future summarization.'),
            'prompt' => $schema->string()->description('Optional focus note for future extraction flows.'),
            'heading' => $schema->string()->description('Section heading when mode=section.'),
            'section_id' => $schema->string()->description('Section id when mode=section. Use ids returned by outline mode.'),
            'match' => $schema->string()->description('Phrase to match when mode=match.'),
            'start_after' => $schema->string()->description('Reserved boundary field.'),
            'end_before' => $schema->string()->description('Reserved boundary field.'),
            'chunk_token' => $schema->string()->description('Opaque token from previous truncated response when mode=chunk.'),
            'timeout_seconds' => $schema->integer()->description('Optional request timeout in seconds.'),
            'strategy' => $schema->string()->description('Provider strategy: auto, direct_only, or provider_only.'),
            'include_metadata' => $schema->boolean()->description('Include page metadata.'),
            'include_outline' => $schema->boolean()->description('Include outline information.'),
            'output_limit_chars' => $schema->integer()->description('Maximum provider content characters.'),
            'no_cache' => $schema->boolean()->description('Bypass cached result.'),
        ];
    }

    /**
     * @return array{content: string, source: string}
     */
    private function selectContent(WebFetchResponse $response, WebFetchRequest $request): array
    {
        return match ($request->mode) {
            'metadata' => ['content' => '', 'source' => 'metadata'],
            'outline' => ['content' => '', 'source' => 'outline'],
            'main', 'full' => ['content' => $this->baseContentForFormat($response, $request), 'source' => 'content'],
            'section' => $this->selectSection($response, $request),
            'match' => $this->selectMatches($response, $request),
            'chunk' => $this->selectChunkSource($response, $request),
            default => ['content' => $response->content, 'source' => 'content'],
        };
    }

    /**
     * @return array{content: string, source: string}
     */
    private function selectSection(WebFetchResponse $response, WebFetchRequest $request): array
    {
        if ($request->sectionId !== null && isset($response->sections[$request->sectionId])) {
            return ['content' => $response->sections[$request->sectionId], 'source' => 'section:'.$request->sectionId];
        }

        if ($request->sectionId !== null) {
            $requested = $this->slugify($request->sectionId);
            foreach (array_keys($response->sections) as $sectionId) {
                if ($this->slugify($sectionId) === $requested) {
                    return ['content' => $response->sections[$sectionId], 'source' => 'section:'.$sectionId];
                }
            }
        }

        if ($request->heading !== null) {
            foreach ($response->outline as $entry) {
                if (strcasecmp($entry['title'], $request->heading) === 0 && isset($response->sections[$entry['id']])) {
                    return ['content' => $response->sections[$entry['id']], 'source' => 'section:'.$entry['id']];
                }
            }
        }

        $available = array_keys($response->sections);
        throw new WebFetchPermanentException('Requested section was not found. Use outline mode first to inspect available section ids and headings.'.($available === [] ? '' : ' Available section ids: '.implode(', ', array_slice($available, 0, 12))));
    }

    /**
     * @return array{content: string, source: string}
     */
    private function selectMatches(WebFetchResponse $response, WebFetchRequest $request): array
    {
        if ($request->match === null || trim($request->match) === '') {
            throw new WebFetchPermanentException('match is required when mode=match.');
        }

        $blocks = [];
        foreach ($response->sections as $sectionId => $content) {
            if (stripos($content, $request->match) !== false) {
                $blocks[] = '## '.($this->sectionHeading($response, $sectionId) ?? $sectionId)."\n\n".$content;
            }
        }

        if ($blocks === []) {
            throw new WebFetchPermanentException("No matching content found for '{$request->match}'.");
        }

        return ['content' => implode("\n\n", $blocks), 'source' => 'match:'.$request->match];
    }

    /**
     * @return array{content: string, source: string}
     */
    private function selectChunkSource(WebFetchResponse $response, WebFetchRequest $request): array
    {
        if ($request->chunkToken === null) {
            throw new WebFetchPermanentException('chunk_token is required when mode=chunk.');
        }

        $token = $this->decodeChunkToken($request->chunkToken);
        $source = (string) ($token['source'] ?? 'content');

        if ($source === 'content') {
            return ['content' => $this->baseContentForFormat($response, $request), 'source' => 'content'];
        }

        if (str_starts_with($source, 'section:')) {
            $sectionId = substr($source, strlen('section:'));
            if (isset($response->sections[$sectionId])) {
                return ['content' => $response->sections[$sectionId], 'source' => $source];
            }
        }

        if (str_starts_with($source, 'match:')) {
            return $this->selectMatches($response, new WebFetchRequest(url: $request->url, match: substr($source, strlen('match:'))));
        }

        throw new WebFetchPermanentException('Chunk token could not be resolved against the current page content.');
    }

    /**
     * @return array{0: string, 1: bool, 2: ?string}
     */
    private function sliceContent(string $content, int $maxChars, WebFetchRequest $request, string $source): array
    {
        if (in_array($request->mode, ['metadata', 'outline'], true)) {
            return ['', false, null];
        }

        $offset = 0;
        if ($request->mode === 'chunk' && $request->chunkToken !== null) {
            $offset = max(0, (int) ($this->decodeChunkToken($request->chunkToken)['offset'] ?? 0));
        }

        if (mb_strlen($content) <= $offset + $maxChars) {
            return [mb_substr($content, $offset), false, null];
        }

        return [mb_substr($content, $offset, $maxChars), true, $this->encodeChunkToken(['source' => $source, 'offset' => $offset + $maxChars])];
    }

    private function baseContentForFormat(WebFetchResponse $response, WebFetchRequest $request): string
    {
        if ($request->format === 'html' && is_string($response->rawHtml) && $response->rawHtml !== '') {
            return $response->rawHtml;
        }

        return $request->format === 'text' ? $this->markdown->markdownToPlainText($response->content) : $response->content;
    }

    /**
     * @return array{source?: string, offset?: int}
     */
    private function decodeChunkToken(string $token): array
    {
        $normalized = strtr($token, '-_', '+/');
        if (($padding = strlen($normalized) % 4) !== 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        $data = is_string($decoded) ? json_decode($decoded, true) : null;
        if (! is_array($data)) {
            throw new \RuntimeException('Invalid chunk token.');
        }

        return $data;
    }

    /**
     * @param  array{source: string, offset: int}  $payload
     */
    private function encodeChunkToken(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function sectionHeading(WebFetchResponse $response, string $sectionId): ?string
    {
        foreach ($response->outline as $entry) {
            if ($entry['id'] === $sectionId) {
                return $entry['title'];
            }
        }

        return null;
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? $value;

        return trim($value, '-');
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        return app()->bound('currentWorkspace') ? AppSetting::getValue($key, $default) : $default;
    }
}

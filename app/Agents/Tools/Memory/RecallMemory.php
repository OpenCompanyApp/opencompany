<?php

namespace App\Agents\Tools\Memory;

use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\Memory\DocumentIndexingService;
use App\Services\Memory\MemoryScopeGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RecallMemory implements Tool
{
    private const DEFAULT_MAX_CHARS = 4000;

    public function __construct(
        private User $agent,
        private DocumentIndexingService $indexer,
        private AgentDocumentService $docService,
        private ?MemoryScopeGuard $scopeGuard = null,
        private ?string $channelId = null,
    ) {}

    public function description(): string
    {
        return 'Search your long-term memory by query, or browse a specific day\'s log by date.';
    }

    public function handle(Request $request): string
    {
        if ($this->scopeGuard && !$this->scopeGuard->canUseMemoryTools($this->agent, $this->channelId)) {
            return $this->scopeGuard->denialMessage('recall_memory');
        }

        $query = $request['query'] ?? null;
        $date = $request['date'] ?? null;
        $maxChars = $request['max_chars'] ?? null;

        if (!$query && !$date) {
            return 'Error: Provide either "query" for semantic search or "date" (YYYY-MM-DD) to browse a specific day\'s log.';
        }

        if ($date && !$this->isValidDate($date)) {
            return 'Error: Invalid date format. Use YYYY-MM-DD (e.g., "2026-02-14").';
        }

        if ($date) {
            return $this->browseByDate($date, $maxChars);
        }

        return $this->searchByQuery($query, $request['limit'] ?? 6, $maxChars);
    }

    private function browseByDate(string $date, ?int $maxChars): string
    {
        $doc = $this->docService->getMemoryLog($this->agent, $date);

        if (!$doc || empty(trim($doc->content ?? ''))) {
            return "No memory log found for {$date}.";
        }

        $content = $doc->content;
        $contentLength = mb_strlen($content);
        $budget = $maxChars ?? self::DEFAULT_MAX_CHARS;
        $entryCount = substr_count($content, '---') + 1;

        if ($contentLength <= $budget) {
            return "[Memory log for {$date} — {$entryCount} entries, {$contentLength} chars]\n\n{$content}";
        }

        // Over budget without explicit max_chars → reject so agent can decide
        if ($maxChars === null) {
            return "Log for {$date} is {$contentLength} characters (~{$entryCount} entries). Too large to return in full.\n"
                . "Call again with max_chars to truncate, e.g.: recall_memory(date: \"{$date}\", max_chars: 3000)";
        }

        // Explicit max_chars → truncate
        $truncated = mb_substr($content, 0, $maxChars);
        $remaining = $contentLength - $maxChars;

        return "[Showing first {$maxChars} chars of {$contentLength} total — {$entryCount} entries on this day]\n\n"
            . $truncated . "...\n\n"
            . "[Truncated — {$remaining} chars omitted]";
    }

    private function searchByQuery(string $query, int $limit, ?int $maxChars): string
    {
        $results = $this->indexer->search(
            query: $query,
            collection: 'memory',
            agentId: $this->agent->id,
            limit: $limit,
            minSimilarity: config('memory.search.min_similarity', 0.5),
        );

        if ($results->isEmpty()) {
            return "No memories found matching '{$query}'.";
        }

        $maxSnippet = 700;
        $maxTotal = $maxChars ?? self::DEFAULT_MAX_CHARS;
        $totalChars = 0;
        $output = [];

        foreach ($results as $chunk) {
            $snippet = Str::limit($chunk->content, $maxSnippet);
            $meta = is_array($chunk->metadata) ? $chunk->metadata : [];
            $date = $meta['updated_at'] ?? 'unknown date';
            $similarity = isset($chunk->similarity) ? round($chunk->similarity * 100) : 0;
            $entry = "**{$date}** ({$similarity}% match)\n{$snippet}";

            if ($totalChars + strlen($entry) > $maxTotal) {
                break;
            }
            $output[] = $entry;
            $totalChars += strlen($entry);
        }

        return 'Found ' . count($output) . " memory/memories:\n\n" . implode("\n\n---\n\n", $output);
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parts = explode('-', $date);

        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('Semantic search query. Required unless date is provided.'),
            'date' => $schema
                ->string()
                ->description('Browse a specific day\'s log (YYYY-MM-DD format). Returns raw content instead of semantic search.'),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of results for search mode. Default: 6.'),
            'max_chars' => $schema
                ->integer()
                ->description('Maximum characters to return. Default: 4000. Use when a date log is too large.'),
        ];
    }
}

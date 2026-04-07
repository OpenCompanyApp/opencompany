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
        return 'Search your long-term memory by query, load a topic or peer file, or browse a specific day\'s log by date.';
    }

    public function handle(Request $request): string
    {
        if ($this->scopeGuard && !$this->scopeGuard->canUseMemoryTools($this->agent, $this->channelId)) {
            return $this->scopeGuard->denialMessage('recall_memory');
        }

        $query = $request['query'] ?? null;
        $date = $request['date'] ?? null;
        $topic = $request['topic'] ?? null;
        $peer = $request['peer'] ?? null;
        $maxChars = $request['max_chars'] ?? null;

        // Direct loads first
        if ($topic) {
            return $this->loadTopic($topic);
        }

        if ($peer) {
            return $this->loadPeer($peer);
        }

        if ($date) {
            if (!$this->isValidDate($date)) {
                return 'Error: Invalid date format. Use YYYY-MM-DD (e.g., "2026-02-14").';
            }

            return $this->browseByDate($date, $maxChars);
        }

        if ($query) {
            return $this->searchByQuery($query, $request['limit'] ?? 6, $maxChars);
        }

        return 'Error: Provide "query" for semantic search, "topic" to load a topic file, "peer" to load peer notes, or "date" (YYYY-MM-DD) to browse a log.';
    }

    private function loadTopic(string $slug): string
    {
        $doc = $this->docService->getMemoryTopicFile($this->agent, $slug);

        if (!$doc) {
            return "Topic '{$slug}' not found. Check MEMORY.md index for available topics.";
        }

        return "[Topic: {$slug}.md]\n\n{$doc->content}";
    }

    private function loadPeer(string $peerId): string
    {
        // Try user first, then agent
        $doc = $this->docService->getPeerMemory($this->agent, $peerId, 'user')
            ?? $this->docService->getPeerMemory($this->agent, $peerId, 'agent');

        if (!$doc) {
            $peer = User::find($peerId);
            $name = $peer?->name ?? $peerId;
            return "No peer memory found for '{$name}'. Save one with save_memory(target: \"peer\", peer_id: \"{$peerId}\", peer_type: \"user\"|\"agent\").";
        }

        $peer = User::find($peerId);
        $name = $peer?->name ?? $peerId;

        return "[Peer memory: {$name}]\n\n{$doc->content}";
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
        // Search across topic, peer, and memory collections
        $results = collect();

        foreach (['topic', 'peer', 'memory'] as $collection) {
            $collectionResults = $this->indexer->search(
                query: $query,
                collection: $collection,
                agentId: $this->agent->id,
                limit: $limit,
                minSimilarity: config('memory.search.min_similarity', 0.5),
            );
            $results = $results->merge($collectionResults);
        }

        // Sort by similarity descending and take top results
        $results = $results->sortByDesc('similarity')->take($limit);

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
            $collection = $chunk->collection ?? 'unknown';
            $similarity = isset($chunk->similarity) ? round($chunk->similarity * 100) : 0;
            $entry = "**{$date}** [{$collection}] ({$similarity}% match)\n{$snippet}";

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
                ->description('Semantic search query. Searches across topics, peer notes, and daily logs.'),
            'date' => $schema
                ->string()
                ->description('Browse a specific day\'s log (YYYY-MM-DD format). Returns raw content.'),
            'topic' => $schema
                ->string()
                ->description('Load a specific topic file by slug (e.g., "vue3-migration"). Fast direct load.'),
            'peer' => $schema
                ->string()
                ->description('Load peer notes for a specific user or agent ID.'),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of results for search mode. Default: 6.'),
            'max_chars' => $schema
                ->integer()
                ->description('Maximum characters to return. Default: 4000. Use when a date log is too large.'),
        ];
    }
}

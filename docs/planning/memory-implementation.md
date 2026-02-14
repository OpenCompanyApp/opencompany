# Memory, Compaction & Embeddings — Implementation Guide

> Phased implementation plan for agent memory, document embeddings, conversation compaction, and hybrid search. Each phase is a standalone PR-able unit. Later phases depend on earlier ones.

**Status**: Complete (all 6 phases implemented)
**Config**: `config/memory.php` (comprehensive: embedding, chunking, search, reranking, compaction, memory_flush, context_windows, scope)
**Reference**: OpenClaw memory system (`inspiration/openclaw/src/memory/`, `inspiration/openclaw/docs/concepts/memory.md`; updated for v2026.2.9)

### Implementation Summary

All phases are built and operational. Key files:

| Phase | What | Key Files |
|-------|------|-----------|
| 1 | pgvector, chunking, embeddings | `app/Services/Memory/ChunkingService.php`, `EmbeddingService.php`, `app/Models/DocumentChunk.php`, `EmbeddingCache.php` |
| 2 | Document indexing + observer | `app/Services/Memory/DocumentIndexingService.php`, `app/Observers/DocumentObserver.php`, `app/Jobs/IndexDocumentJob.php` |
| 3 | SaveMemory + RecallMemory tools | `app/Agents/Tools/Memory/SaveMemory.php`, `RecallMemory.php` |
| 4 | Conversation compaction | `app/Services/Memory/ConversationCompactionService.php`, `app/Jobs/CompactConversationJob.php`, `app/Models/ConversationSummary.php` |
| 5 | Pre-compaction memory flush | `app/Services/Memory/MemoryFlushService.php` (hooked into `AgentRespondJob`) |
| 6 | Hybrid search (BM25 + vector) | `app/Services/Memory/HybridSearchService.php`, tsvector column on `document_chunks` |

Bonus services (not in original plan): `TokenEstimator`, `ModelContextRegistry`, `MemoryScopeGuard`, `RerankingService` (cross-encoder reranking via Ollama).

Migrations: `create_document_chunks_table`, `create_embedding_cache_table`, `create_conversation_summaries_table`, `add_flush_count_to_conversation_summaries_table`, `add_search_vector_to_document_chunks`.

Artisan commands: `memory:status`, `memory:index-documents [--fresh]`.

---

## Memory Model: Short-Term vs Long-Term

Agents have two distinct memory systems, mirroring how human memory works:

### Short-Term Memory (STM) — The Conversation Context

- **What**: The current conversation messages loaded into the context window
- **Scope**: Single channel, single session
- **Lifetime**: Ephemeral — exists only while the context window holds it
- **Managed by**: `ChannelConversationLoader` (Phase 4: compaction keeps it within budget)
- **Storage**: `messages` table → loaded into context window at prompt time
- **Capacity**: Limited by model context window (e.g. 128K tokens)
- **When full**: Older messages are summarized into a `ConversationSummary` and replaced

### Long-Term Memory (LTM) — Durable Memories

- **What**: Explicitly saved facts, preferences, decisions, learnings
- **Scope**: Per-agent, accessible across all conversations
- **Lifetime**: Permanent — persists until explicitly deleted
- **Managed by**: `SaveMemory` / `RecallMemory` tools (Phase 3)
- **Storage**: `agents/*/memory/YYYY-MM-DD.md` documents → chunked & embedded in `document_chunks`
- **Capacity**: Unlimited (PostgreSQL + pgvector)
- **Retrieval**: Semantic search (vector similarity), not loaded by default — agents must actively recall

### The Bridge: STM → LTM Promotion (Phase 5)

Before conversation compaction discards older messages, the **Memory Flush** gives the agent a silent turn to review what's about to be lost and `save_memory` anything important. This is the automatic promotion path from short-term to long-term memory.

```
┌─────────────────────────────────────────────────────────┐
│                    Agent Execution                       │
│                                                         │
│  ┌──────────────────┐    flush     ┌──────────────────┐ │
│  │  Short-Term (STM) │ ─────────► │  Long-Term (LTM)  │ │
│  │                    │  Phase 5   │                    │ │
│  │  Conversation      │            │  Saved memories    │ │
│  │  messages in       │            │  in document_chunks│ │
│  │  context window    │            │  (pgvector)        │ │
│  │                    │            │                    │ │
│  │  Compacted when    │  recall    │  Recalled via      │ │
│  │  approaching limit │ ◄───────── │  semantic search   │ │
│  │  (Phase 4)         │  Phase 3   │  on demand         │ │
│  └──────────────────┘            └──────────────────┘ │
│                                                         │
│  ┌──────────────────────────────────────────────────┐   │
│  │              Document Knowledge Base              │   │
│  │  Shared workspace docs, indexed with embeddings   │   │
│  │  Searchable via SearchDocuments (semantic mode)    │   │
│  │  (Phase 2)                                        │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

---

## Phase Dependencies

```
Phase 1 (Foundation)
  ├── Phase 2 (Document Embeddings)          — Document Knowledge Base
  │     └── Phase 6 (Hybrid Search)          — upgrades all search
  ├── Phase 3 (Agent LTM Tools)              — SaveMemory + RecallMemory
  └── Phase 4 (Conversation Compaction)      — STM management
        └── Phase 5 (STM → LTM Flush)       — requires Phase 3 + 4
```

---

## Phase 1: Foundation — pgvector, Chunking & Embedding Services

### Goal
Install pgvector, create the storage tables, and build the core chunking and embedding services that all later phases depend on.

### Database

**Migration: `create_document_chunks_table`**

```php
// Enable pgvector extension
DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

Schema::create('document_chunks', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('document_id');              // FK → documents.id
    $table->text('content');                     // The chunk text
    $table->string('content_hash', 64);          // SHA256 of content (for dedup)
    $table->vector('embedding', 1536);           // pgvector column
    $table->string('collection')->default('general'); // 'general', 'memory', 'identity'
    $table->string('agent_id')->nullable();      // Scoping: null = shared, set = agent-private
    $table->integer('chunk_index')->default(0);  // Position within the source document
    $table->jsonb('metadata')->nullable();       // Title, path, dates, etc.
    $table->timestamps();

    $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
    $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();

    // Indexes
    $table->index(['collection', 'agent_id']);
    $table->index('content_hash');
    $table->index('document_id');
});

// HNSW index for fast cosine similarity search
DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');
```

**Migration: `create_embedding_cache_table`**

```php
Schema::create('embedding_cache', function (Blueprint $table) {
    $table->string('id', 64)->primary();   // SHA256(provider + model + content)
    $table->string('provider', 50);         // e.g. 'openai'
    $table->string('model', 100);           // e.g. 'text-embedding-3-small'
    $table->vector('embedding', 1536);
    $table->timestamps();
});
```

### Models

**`app/Models/DocumentChunk.php`**

```php
class DocumentChunk extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'document_id', 'content', 'content_hash', 'embedding',
        'collection', 'agent_id', 'chunk_index', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
```

**`app/Models/EmbeddingCache.php`**

```php
class EmbeddingCache extends Model
{
    protected $table = 'embedding_cache';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'provider', 'model', 'embedding'];

    protected function casts(): array
    {
        return ['embedding' => 'array'];
    }

    /**
     * Generate a cache key from provider + model + content.
     */
    public static function cacheKey(string $provider, string $model, string $content): string
    {
        return hash('sha256', "{$provider}:{$model}:{$content}");
    }
}
```

### Services

**`app/Services/Memory/ChunkingService.php`**

Splits markdown text into overlapping chunks. Configuration comes from `config('memory.chunking')`.

```php
class ChunkingService
{
    /**
     * Split text into overlapping chunks.
     *
     * @return array<int, string>  Ordered list of chunk strings
     */
    public function chunk(string $text): array
    {
        $maxSize  = config('memory.chunking.max_chunk_size', 512);  // tokens approx
        $overlap  = config('memory.chunking.chunk_overlap', 64);
        $separator = config('memory.chunking.separator', "\n\n");

        // 1. Split on separator (paragraph breaks)
        $paragraphs = array_filter(explode($separator, $text), fn ($p) => trim($p) !== '');

        // 2. Greedily merge paragraphs into chunks of ~maxSize tokens
        //    Estimate tokens as wordcount * 1.3 (conservative for English)
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $para) {
            $candidate = $current === '' ? $para : $current . $separator . $para;
            if ($this->estimateTokens($candidate) > $maxSize && $current !== '') {
                $chunks[] = trim($current);
                // Overlap: keep the last ~overlap tokens worth of text
                $current = $this->takeTrailing($current, $overlap) . $separator . $para;
            } else {
                $current = $candidate;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(str_word_count($text) * 1.3);
    }

    private function takeTrailing(string $text, int $tokenCount): string
    {
        $words = explode(' ', $text);
        $wordCount = (int) ceil($tokenCount / 1.3);
        return implode(' ', array_slice($words, -$wordCount));
    }
}
```

**`app/Services/Memory/EmbeddingService.php`**

Generates embeddings via OpenAI (or other providers), with a database cache layer.

```php
class EmbeddingService
{
    /**
     * Get the embedding for a single text.
     * Returns from cache if available, otherwise calls the API and caches.
     *
     * @return array<float>  Vector of floats (1536 dimensions)
     */
    public function embed(string $text): array
    {
        $provider = config('memory.embedding.provider', 'openai');
        $model    = config('memory.embedding.model', 'text-embedding-3-small');

        $cacheKey = EmbeddingCache::cacheKey($provider, $model, $text);
        $cached   = EmbeddingCache::find($cacheKey);

        if ($cached) {
            return $cached->embedding;
        }

        $embedding = $this->callProvider($provider, $model, $text);

        EmbeddingCache::create([
            'id'        => $cacheKey,
            'provider'  => $provider,
            'model'     => $model,
            'embedding' => $embedding,
        ]);

        return $embedding;
    }

    /**
     * Batch embed multiple texts.
     * Checks cache first, only calls API for uncached texts.
     *
     * @return array<int, array<float>>  Embeddings in the same order as input
     */
    public function embedBatch(array $texts): array
    {
        $provider = config('memory.embedding.provider', 'openai');
        $model    = config('memory.embedding.model', 'text-embedding-3-small');

        $results = [];
        $uncached = [];

        foreach ($texts as $i => $text) {
            $cacheKey = EmbeddingCache::cacheKey($provider, $model, $text);
            $cached = EmbeddingCache::find($cacheKey);
            if ($cached) {
                $results[$i] = $cached->embedding;
            } else {
                $uncached[$i] = $text;
            }
        }

        if (!empty($uncached)) {
            $batchEmbeddings = $this->callProviderBatch($provider, $model, array_values($uncached));
            $j = 0;
            foreach ($uncached as $i => $text) {
                $embedding = $batchEmbeddings[$j++];
                $results[$i] = $embedding;

                EmbeddingCache::create([
                    'id'        => EmbeddingCache::cacheKey($provider, $model, $text),
                    'provider'  => $provider,
                    'model'     => $model,
                    'embedding' => $embedding,
                ]);
            }
        }

        ksort($results);
        return $results;
    }

    private function callProvider(string $provider, string $model, string $text): array
    {
        // OpenAI embeddings API call
        // Use Laravel HTTP client: Http::withToken(config('services.openai.api_key'))
        //   ->post('https://api.openai.com/v1/embeddings', [...])
        // Return the embedding vector
    }

    private function callProviderBatch(string $provider, string $model, array $texts): array
    {
        // OpenAI supports batch in a single request (up to 2048 inputs)
        // Return array of embedding vectors
    }
}
```

### Artisan Command

**`app/Console/Commands/MemoryStatus.php`**

```bash
php artisan memory:status
```

Displays:
- pgvector extension installed (yes/no)
- `document_chunks` row count, broken down by collection
- `embedding_cache` row count
- Config values from `config/memory.php`

### Config Updates

No changes needed --- `config/memory.php` already has the correct structure:
- `embedding.provider` = `openai`
- `embedding.model` = `text-embedding-3-small`
- `embedding.dimensions` = `1536`
- `chunking.max_chunk_size` = `512`
- `chunking.chunk_overlap` = `64`
- `chunking.separator` = `\n\n`

### Tests

| Test | What it verifies |
|------|-----------------|
| `ChunkingServiceTest` | Paragraphs split correctly, overlap works, empty input, single paragraph |
| `EmbeddingServiceTest` | Cache hit returns cached, cache miss calls API & caches, batch embedding |
| `MemoryStatusCommandTest` | Command runs without error, outputs expected info |
| Migration test | Tables created with correct columns, pgvector extension enabled |

### Files to Create

```
database/migrations/YYYY_MM_DD_000001_create_document_chunks_table.php
database/migrations/YYYY_MM_DD_000002_create_embedding_cache_table.php
app/Models/DocumentChunk.php
app/Models/EmbeddingCache.php
app/Services/Memory/ChunkingService.php
app/Services/Memory/EmbeddingService.php
app/Console/Commands/MemoryStatus.php
tests/Feature/Services/Memory/ChunkingServiceTest.php
tests/Feature/Services/Memory/EmbeddingServiceTest.php
```

### Design Notes

> **v2026.2.9 alignment:** OpenClaw changed batch embeddings to disabled by default (opt-in for backfills). Our `EmbeddingService.embedBatch()` remains available for `DocumentIndexingService.index()` in Phase 2 and the `memory:index-documents --fresh` backfill command, but individual embedding calls use `embed()`. This aligns with upstream's rationale: batch is mainly beneficial for large backfills; sync is adequate for incremental updates.

---

## Phase 2: Document Embeddings — Index, Observe, Search

### Goal
Automatically chunk and embed workspace documents so the `search_documents` tool can do semantic search in addition to keyword search.

### Depends On
Phase 1 (ChunkingService, EmbeddingService, DocumentChunk model)

### Services

**`app/Services/Memory/DocumentIndexingService.php`**

Orchestrates chunking + embedding + storage for a Document.

```php
class DocumentIndexingService
{
    public function __construct(
        private ChunkingService $chunker,
        private EmbeddingService $embedder,
    ) {}

    /**
     * Index a document: chunk its content, embed, and store as DocumentChunks.
     */
    public function index(Document $document, string $collection = 'general', ?string $agentId = null): void
    {
        // 1. Delete existing chunks for this document
        DocumentChunk::where('document_id', $document->id)->delete();

        if (empty(trim($document->content))) {
            return;
        }

        // 2. Chunk
        $chunks = $this->chunker->chunk($document->content);

        // 3. Embed all chunks in batch
        $embeddings = $this->embedder->embedBatch($chunks);

        // 4. Store
        foreach ($chunks as $i => $chunkText) {
            DocumentChunk::create([
                'document_id' => $document->id,
                'content'     => $chunkText,
                'content_hash' => hash('sha256', $chunkText),
                'embedding'   => $embeddings[$i],
                'collection'  => $collection,
                'agent_id'    => $agentId,
                'chunk_index' => $i,
                'metadata'    => [
                    'title'      => $document->title,
                    'updated_at' => $document->updated_at?->toIso8601String(),
                ],
            ]);
        }
    }

    /**
     * Remove all chunks for a document.
     */
    public function deindex(Document $document): void
    {
        DocumentChunk::where('document_id', $document->id)->delete();
    }

    /**
     * Semantic search across document chunks.
     *
     * @return Collection<DocumentChunk>  Ordered by similarity (highest first)
     */
    public function search(
        string $query,
        string $collection = 'general',
        ?string $agentId = null,
        int $limit = 10,
        float $minSimilarity = 0.5,
    ): Collection {
        $queryEmbedding = $this->embedder->embed($query);
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        $builder = DocumentChunk::query()
            ->where('collection', $collection)
            ->selectRaw('*, 1 - (embedding <=> ?) as similarity', [$vectorString])
            ->having('similarity', '>=', $minSimilarity)
            ->orderByDesc('similarity')
            ->limit($limit);

        if ($agentId !== null) {
            $builder->where('agent_id', $agentId);
        } else {
            $builder->whereNull('agent_id');
        }

        return $builder->get();
    }
}
```

### Observer

**`app/Observers/DocumentObserver.php`**

```php
class DocumentObserver
{
    /**
     * Auto-index when a non-folder document is saved/updated.
     */
    public function saved(Document $document): void
    {
        if ($document->is_folder) {
            return;
        }

        // Determine collection based on document location
        $collection = $this->resolveCollection($document);
        $agentId = $this->resolveAgentId($document);

        IndexDocumentJob::dispatch($document, $collection, $agentId);
    }

    public function deleted(Document $document): void
    {
        DocumentChunk::where('document_id', $document->id)->delete();
    }

    /**
     * Resolve collection type based on document's folder hierarchy.
     * - agents/{slug}/memory/* → 'memory'
     * - agents/{slug}/identity/* → 'identity'
     * - everything else → 'general'
     */
    private function resolveCollection(Document $document): string
    {
        // Walk up the parent chain to detect if inside agents/*/memory or agents/*/identity
        $parent = $document->parent;
        while ($parent) {
            if ($parent->title === 'memory' && $parent->is_folder) {
                return 'memory';
            }
            if ($parent->title === 'identity' && $parent->is_folder) {
                return 'identity';
            }
            $parent = $parent->parent;
        }
        return 'general';
    }

    /**
     * Resolve agent owner if this document lives under agents/{slug}/.
     */
    private function resolveAgentId(Document $document): ?string
    {
        // Walk up the parent chain to find the agent folder
        $parent = $document->parent;
        while ($parent) {
            if ($parent->parent?->title === 'agents') {
                // This is the agent folder — find the agent by slug
                $agent = User::where('is_agent', true)
                    ->whereRaw("LOWER(REPLACE(name, ' ', '-')) = ?", [strtolower($parent->title)])
                    ->first();
                return $agent?->id;
            }
            $parent = $parent->parent;
        }
        return null;
    }
}
```

Register in `AppServiceProvider::boot()`:
```php
Document::observe(DocumentObserver::class);
```

### Job

**`app/Jobs/IndexDocumentJob.php`**

```php
class IndexDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30];

    public function __construct(
        private Document $document,
        private string $collection = 'general',
        private ?string $agentId = null,
    ) {}

    public function handle(DocumentIndexingService $indexer): void
    {
        $indexer->index($this->document, $this->collection, $this->agentId);
    }
}
```

### SearchDocuments Tool Update

Add a `mode` parameter to the existing `SearchDocuments` tool (`app/Agents/Tools/Docs/SearchDocuments.php`):

```php
public function schema(JsonSchema $schema): array
{
    return [
        'query' => $schema->string()->description('The search query.')->required(),
        'mode'  => $schema->string()->description(
            'Search mode: "keyword" (ILIKE), "semantic" (vector similarity), or "auto" (semantic first, keyword fallback). Default: auto.'
        ),
        'limit' => $schema->integer()->description('Maximum number of results. Default: 5.'),
    ];
}
```

In `handle()`, when `mode` is `semantic` or `auto`:
1. Call `DocumentIndexingService::search($query, 'general', null, $limit)`
2. Map results to the same output format (title, snippet, ID)
3. If `auto` and semantic returns < 2 results, fall back to keyword search and merge

### Artisan Command

**`app/Console/Commands/MemoryIndexDocuments.php`**

```bash
php artisan memory:index-documents [--fresh]
```

- Iterates all non-folder documents
- Dispatches `IndexDocumentJob` for each
- `--fresh` flag deletes all existing chunks first
- Shows progress bar

### Tests

| Test | What it verifies |
|------|-----------------|
| `DocumentIndexingServiceTest` | Index creates chunks, deindex removes them, search returns ranked results |
| `DocumentObserverTest` | Save triggers index job, delete removes chunks, folders are skipped |
| `SearchDocumentsSemanticTest` | Semantic mode returns relevant results, auto fallback works |
| `IndexDocumentJobTest` | Job calls indexer with correct parameters |

### Files to Create/Modify

```
app/Services/Memory/DocumentIndexingService.php          (new)
app/Observers/DocumentObserver.php                        (new)
app/Jobs/IndexDocumentJob.php                             (new)
app/Console/Commands/MemoryIndexDocuments.php              (new)
app/Agents/Tools/Docs/SearchDocuments.php                 (modify — add mode param)
app/Providers/AppServiceProvider.php                      (modify — register observer)
tests/Feature/Services/Memory/DocumentIndexingServiceTest.php
tests/Feature/Observers/DocumentObserverTest.php
```

---

## Phase 3: Long-Term Memory (LTM) — Save & Recall Tools

### Goal
Give agents a **long-term memory** system: `save_memory` to persist durable memories that survive across conversations, and `recall_memory` to semantically search them on demand. Unlike short-term memory (the conversation context), LTM is permanent and must be explicitly saved and recalled.

### Depends On
Phase 1 (ChunkingService, EmbeddingService), Phase 2 (DocumentIndexingService)

### LTM File Layout (OpenClaw Pattern)

Agents have two LTM storage layers, mirroring OpenClaw's approach:

| File | Purpose | Loaded | Write pattern |
|------|---------|--------|---------------|
| `agents/{slug}/identity/MEMORY.md` | **Curated** long-term memory — high-value preferences, decisions, key facts | Always (part of system prompt via identity files) | Overwrite/update (curated) |
| `agents/{slug}/memory/YYYY-MM-DD.md` | **Daily logs** — running context, timestamped entries | Not auto-loaded; searchable via `recall_memory` | Append-only |

The key difference: `MEMORY.md` is always in the agent's system prompt (already loaded by `OpenCompanyAgent::instructions()`), so its contents are "always remembered." Daily logs must be actively recalled via semantic search.

The `save_memory` tool writes to daily logs by default (`target: "log"`). For high-value curated info, the agent can write to `MEMORY.md` (`target: "core"`), which updates the persistent identity file.

### Tools

**`app/Agents/Tools/Memory/SaveMemory.php`**

```php
class SaveMemory implements Tool
{
    public function __construct(
        private User $agent,
        private AgentDocumentService $docService,
        private DocumentIndexingService $indexer,
    ) {}

    public function description(): string
    {
        return 'Save a durable memory that persists across conversations. Use target "core" for high-value facts that should always be remembered (written to MEMORY.md), or "log" for timestamped daily entries (searchable via recall_memory).';
    }

    public function handle(Request $request): string
    {
        $content = $request['content'];
        $category = $request['category'] ?? 'general';
        $target = $request['target'] ?? 'log';

        if ($target === 'core') {
            // Write to MEMORY.md (curated, always in system prompt)
            $memoryFile = $this->docService->getIdentityFile($this->agent, 'MEMORY');
            if (!$memoryFile) {
                return 'Error: MEMORY.md not found for this agent.';
            }

            // Append to MEMORY.md under the appropriate section
            $newContent = $memoryFile->content . "\n\n### {$category}\n{$content}";
            $this->docService->updateIdentityFile($this->agent, 'MEMORY', $newContent);

            // Index for semantic search
            $this->indexer->index($memoryFile->fresh(), 'identity', $this->agent->id);

            return "Core memory saved to MEMORY.md (always loaded in system prompt).";
        }

        // Default: write to daily log
        $entry = "### [{$category}] " . now()->format('H:i') . "\n\n{$content}";
        $doc = $this->docService->createMemoryLog($this->agent, $entry);

        if (!$doc) {
            return 'Error: Could not save memory. Agent document structure may not be initialized.';
        }

        // Index the memory for semantic recall
        $this->indexer->index($doc, 'memory', $this->agent->id);

        return "Memory saved to {$doc->title} (recallable via recall_memory).";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()
                ->description('The memory content to save. Be specific and include context.')
                ->required(),
            'category' => $schema->string()
                ->description('Category tag: "preference", "decision", "learning", "fact", or "general". Default: general.'),
            'target' => $schema->string()
                ->description('Where to save: "core" writes to MEMORY.md (always loaded in your system prompt — use for high-value durable facts), "log" appends to daily log (searchable via recall_memory — use for running context). Default: log.'),
        ];
    }
}
```

**`app/Agents/Tools/Memory/RecallMemory.php`**

```php
class RecallMemory implements Tool
{
    public function __construct(
        private User $agent,
        private DocumentIndexingService $indexer,
    ) {}

    public function description(): string
    {
        return 'Search your long-term memory for relevant past information, decisions, and learnings.';
    }

    public function handle(Request $request): string
    {
        $query = $request['query'];
        $limit = $request['limit'] ?? 6;

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

        // Apply result clamping: max snippet chars = 700, max total injected = 4000
        $maxSnippet = 700;
        $maxTotal = 4000;
        $totalChars = 0;
        $output = [];

        foreach ($results as $chunk) {
            $snippet = Str::limit($chunk->content, $maxSnippet);
            $date = $chunk->metadata['updated_at'] ?? 'unknown date';
            $similarity = round($chunk->similarity * 100);
            $entry = "**{$date}** ({$similarity}% match)\n{$snippet}";

            if ($totalChars + strlen($entry) > $maxTotal) {
                break;
            }
            $output[] = $entry;
            $totalChars += strlen($entry);
        }

        return "Found " . count($output) . " memory/memories:\n\n" . implode("\n\n---\n\n", $output);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('What to search for in your memories.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum number of memories to return. Default: 6.'),
        ];
    }
}
```

### ToolRegistry Updates

In `app/Agents/Tools/ToolRegistry.php`:

1. Add to `APP_GROUPS`:
```php
'memory' => [
    'tools' => ['save_memory', 'recall_memory'],
    'label' => 'save, recall',
    'description' => 'Long-term agent memory',
],
```

2. Add to `APP_ICONS`:
```php
'memory' => 'ph:brain',
```

3. Add to `TOOL_MAP`:
```php
'save_memory' => [
    'class' => SaveMemory::class,
    'type' => 'write',
    'name' => 'Save Memory',
    'description' => 'Save a durable memory that persists across conversations.',
    'icon' => 'ph:brain',
],
'recall_memory' => [
    'class' => RecallMemory::class,
    'type' => 'read',
    'name' => 'Recall Memory',
    'description' => 'Search long-term memory for past information and learnings.',
    'icon' => 'ph:brain',
],
```

4. Add to `instantiateTool()` match block:
```php
SaveMemory::class => new SaveMemory($agent, app(AgentDocumentService::class), app(DocumentIndexingService::class)),
RecallMemory::class => new RecallMemory($agent, app(DocumentIndexingService::class)),
```

5. Add `'memory'` to the `$displayOrder` in `getAppCatalog()` (after `'agents'`):
```php
['agents', 'memory', 'chat', 'docs', 'tables', 'calendar', 'lists', 'workspace', null],
```

### System Prompt Update

Add memory usage guidance to `OpenCompanyAgent::instructions()`. Append to the system prompt after the MEMORY.md identity file section. See **Appendix B** for the complete system prompt text.

The system prompt covers:
1. STM vs LTM mental model (what the agent controls vs what's automatic)
2. `save_memory` with `target` ("core" vs "log") and clear guidance on when to use each
3. `recall_memory` with explicit triggers (prior work questions, complex tasks, referenced conversations)
4. A save/don't-save decision guide
5. Clear instruction: "If someone says remember this — save it immediately"

Key design choice (differs from OpenClaw): Our system prompt is **proactive about saving**, not just recall. OpenClaw's system prompt only mentions recall (`"Before answering anything about prior work... run memory_search"`), relying on docs and flush prompts for save behavior. Our agents don't have filesystem access and can't read docs on demand, so the system prompt must be self-contained.

### Backfill Command

**`app/Console/Commands/MemoryIndexLogs.php`**

```bash
php artisan memory:index-logs [--agent=slug]
```

- Finds all memory log documents under `agents/*/memory/`
- Dispatches `IndexDocumentJob` for each with `collection='memory'` and the correct `agent_id`
- Optional `--agent` flag to limit to a specific agent

### Tests

| Test | What it verifies |
|------|-----------------|
| `SaveMemoryTest` | Persists to daily log, indexes for recall, handles missing doc structure |
| `RecallMemoryTest` | Returns ranked results, respects agent scoping, applies result clamping |
| `MemoryIndexLogsCommandTest` | Backfill command finds logs and dispatches jobs |

### Files to Create/Modify

```
app/Agents/Tools/Memory/SaveMemory.php                   (new)
app/Agents/Tools/Memory/RecallMemory.php                  (new)
app/Agents/Tools/ToolRegistry.php                         (modify — register tools)
app/Agents/OpenCompanyAgent.php                           (modify — add memory guidance)
app/Console/Commands/MemoryIndexLogs.php                  (new)
tests/Feature/Tools/SaveMemoryTest.php
tests/Feature/Tools/RecallMemoryTest.php
```

---

## Phase 4: Short-Term Memory (STM) — Conversation Compaction

### Goal
Manage the agent's **short-term memory** (conversation context window) by summarizing older messages when conversations get long. Without compaction, STM simply drops older messages — compaction preserves them as compressed summaries. Summaries are cumulative --- each compaction builds on the previous summary.

### Depends On
Phase 1 (foundation only --- compaction doesn't require embeddings)

### Database

**Migration: `create_conversation_summaries_table`**

```php
Schema::create('conversation_summaries', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('channel_id');
    $table->string('agent_id');
    $table->longText('summary');                 // The cumulative summary text
    $table->integer('tokens_before')->default(0); // Token count before compaction
    $table->integer('tokens_after')->default(0);  // Token count after compaction
    $table->integer('compaction_count')->default(0); // How many compaction cycles
    $table->integer('messages_summarized')->default(0); // Total messages folded in
    $table->string('last_message_id')->nullable(); // Last message included in summary
    $table->integer('flush_count')->default(0);    // Pre-compaction flushes done (for Phase 5)
    $table->timestamps();

    $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
    $table->foreign('agent_id')->references('id')->on('users')->cascadeOnDelete();

    $table->unique(['channel_id', 'agent_id']);
});
```

### Config Updates

Add `compaction` section to `config/memory.php`:

```php
'compaction' => [
    'enabled' => env('MEMORY_COMPACTION_ENABLED', true),
    'threshold_ratio' => 0.75,        // Compact when at 75% of context window
    'keep_ratio' => 0.4,              // Keep the most recent 40% of messages
    'context_window' => (int) env('MEMORY_CONTEXT_WINDOW', 128000),  // Default token budget
    'summary_model' => env('MEMORY_SUMMARY_MODEL', 'claude-sonnet-4-5-20250929'),
    'summary_max_tokens' => 2000,
],
```

> **Design note (v2026.2.9):** OpenClaw added `memory.qmd.update.waitForBootSync` (default `false`) to control whether QMD initialization blocks gateway startup. This is not needed in our architecture — pgvector indexes are always available via PostgreSQL with no cold-start model download or index warm-up step.

### Model

**`app/Models/ConversationSummary.php`**

```php
class ConversationSummary extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'channel_id', 'agent_id', 'summary',
        'tokens_before', 'tokens_after', 'compaction_count',
        'messages_summarized', 'last_message_id', 'flush_count',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
```

### Service

**`app/Services/Memory/ConversationCompactionService.php`**

```php
class ConversationCompactionService
{
    /**
     * Check if compaction is needed for a channel/agent pair.
     */
    public function needsCompaction(string $channelId, User $agent, iterable $messages): bool
    {
        if (!config('memory.compaction.enabled', true)) {
            return false;
        }

        $totalTokens = $this->estimateTokens($messages);
        $threshold = config('memory.compaction.context_window', 128000)
                   * config('memory.compaction.threshold_ratio', 0.75);

        return $totalTokens > $threshold;
    }

    /**
     * Perform compaction: summarize older messages, return the summary.
     */
    public function compact(string $channelId, User $agent, array $messages): ConversationSummary
    {
        $keepRatio = config('memory.compaction.keep_ratio', 0.4);
        $keepCount = max(3, (int) ceil(count($messages) * $keepRatio));
        $splitIndex = count($messages) - $keepCount;

        // Split: older messages to summarize, recent messages to keep
        $toSummarize = array_slice($messages, 0, $splitIndex);

        // Get existing summary (cumulative)
        $existing = ConversationSummary::where('channel_id', $channelId)
            ->where('agent_id', $agent->id)
            ->first();

        // Build summarization prompt
        $previousSummary = $existing?->summary ?? '';
        $summaryText = $this->summarize($toSummarize, $previousSummary);

        $tokensBefore = $this->estimateTokens($messages);

        // Upsert summary
        $summary = ConversationSummary::updateOrCreate(
            ['channel_id' => $channelId, 'agent_id' => $agent->id],
            [
                'summary' => $summaryText,
                'tokens_before' => $tokensBefore,
                'tokens_after' => $this->estimateTokenCount($summaryText),
                'compaction_count' => ($existing?->compaction_count ?? 0) + 1,
                'messages_summarized' => ($existing?->messages_summarized ?? 0) + count($toSummarize),
                'last_message_id' => end($toSummarize)?->id ?? $existing?->last_message_id,
            ]
        );

        return $summary;
    }

    /**
     * Summarize messages using an LLM call.
     */
    private function summarize(array $messages, string $previousSummary): string
    {
        $prompt = "You are summarizing a conversation for an AI agent's context window.\n\n";

        if ($previousSummary) {
            $prompt .= "Previous summary of even older messages:\n{$previousSummary}\n\n";
        }

        $prompt .= "Messages to summarize:\n";
        foreach ($messages as $msg) {
            $role = $msg->role->value ?? 'unknown';
            $content = $msg->content ?? '';
            $prompt .= "[{$role}]: {$content}\n";
        }

        $prompt .= "\nCreate a concise summary that captures:\n";
        $prompt .= "- Key topics discussed\n- Decisions made\n- Action items\n- Important context\n";
        $prompt .= "- User preferences expressed\n\n";
        $prompt .= "Be factual and specific. Preserve names, dates, and technical details.";

        // Call LLM for summarization
        // Use the configured summary model
        $model = config('memory.compaction.summary_model');
        $maxTokens = config('memory.compaction.summary_max_tokens', 2000);

        // Use Laravel AI SDK to generate summary
        // Return the summary text
    }

    private function estimateTokens(iterable $messages): int
    {
        $total = 0;
        foreach ($messages as $msg) {
            $content = $msg->content ?? '';
            $total += (int) ceil(str_word_count($content) * 1.3);
        }
        return $total;
    }

    private function estimateTokenCount(string $text): int
    {
        return (int) ceil(str_word_count($text) * 1.3);
    }
}
```

### ChannelConversationLoader Update

Modify `app/Agents/Conversations/ChannelConversationLoader.php` to:

1. **Prepend existing summary** as the first message if one exists
2. **Only load messages after** the last summarized message
3. **Dispatch compaction job** when approaching the threshold

```php
class ChannelConversationLoader
{
    private const DEFAULT_LIMIT = 50;  // Increase from 20 to load more for compaction context

    public function __construct(
        private ConversationCompactionService $compactionService,
    ) {}

    public function load(string $channelId, User $agent, int $limit = self::DEFAULT_LIMIT): iterable
    {
        $sdkMessages = [];

        // 1. Check for existing summary
        $summary = ConversationSummary::where('channel_id', $channelId)
            ->where('agent_id', $agent->id)
            ->first();

        if ($summary && !empty($summary->summary)) {
            // Prepend summary as a system-style user message
            $sdkMessages[] = new UserMessage(
                "[Conversation Summary — {$summary->messages_summarized} prior messages, "
                . "{$summary->compaction_count} compaction(s)]\n\n{$summary->summary}"
            );
        }

        // 2. Load messages (after last summarized message if applicable)
        $query = Message::where('channel_id', $channelId)
            ->orderBy('created_at', 'desc')
            ->take($limit);

        if ($summary?->last_message_id) {
            $lastMsg = Message::find($summary->last_message_id);
            if ($lastMsg) {
                $query->where('created_at', '>', $lastMsg->created_at);
            }
        }

        $messages = $query->get()->reverse()->values();

        foreach ($messages as $message) {
            if (empty($message->content)) {
                continue;
            }

            if ($message->author_id === $agent->id) {
                $sdkMessages[] = new AssistantMessage($message->content);
            } else {
                $author = $message->author;
                $authorName = $author->name ?? 'User';
                $sdkMessages[] = new UserMessage("[{$authorName}]: {$message->content}");
            }
        }

        // 3. Check if compaction is needed (dispatch async)
        if ($this->compactionService->needsCompaction($channelId, $agent, $sdkMessages)) {
            CompactConversationJob::dispatch($channelId, $agent);
        }

        return $sdkMessages;
    }
}
```

### Job

**`app/Jobs/CompactConversationJob.php`**

```php
class CompactConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        private string $channelId,
        private User $agent,
    ) {}

    public function handle(ConversationCompactionService $compactor): void
    {
        // Load all raw messages for this channel
        $messages = Message::where('channel_id', $this->channelId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Convert to SDK message format for the compactor
        $sdkMessages = [];
        foreach ($messages as $msg) {
            if (empty($msg->content)) continue;
            if ($msg->author_id === $this->agent->id) {
                $sdkMessages[] = new AssistantMessage($msg->content);
            } else {
                $sdkMessages[] = new UserMessage($msg->content);
            }
        }

        $compactor->compact($this->channelId, $this->agent, $sdkMessages);
    }
}
```

### Tests

| Test | What it verifies |
|------|-----------------|
| `ConversationCompactionServiceTest` | Threshold detection, message splitting at keep_ratio, summary generation, cumulative summaries |
| `ChannelConversationLoaderTest` | Summary prepended, only post-summary messages loaded, compaction job dispatched |
| `CompactConversationJobTest` | Job loads messages, calls compactor, stores summary |

### Files to Create/Modify

```
database/migrations/YYYY_MM_DD_000003_create_conversation_summaries_table.php
app/Models/ConversationSummary.php                                (new)
app/Services/Memory/ConversationCompactionService.php             (new)
app/Jobs/CompactConversationJob.php                               (new)
app/Agents/Conversations/ChannelConversationLoader.php            (modify)
config/memory.php                                                 (modify — add compaction section)
tests/Feature/Services/Memory/ConversationCompactionServiceTest.php
tests/Feature/Agents/Conversations/ChannelConversationLoaderTest.php
```

---

## Phase 5: STM → LTM Promotion — Pre-Compaction Memory Flush

### Goal
**Bridge short-term and long-term memory.** Before compaction summarizes (and lossy-compresses) older messages from STM, give the agent a silent turn to promote important information to LTM via `save_memory`. This ensures key facts, preferences, and decisions survive compaction intact rather than being compressed into a summary.

### Depends On
Phase 3 (SaveMemory tool), Phase 4 (ConversationCompactionService)

### Config Updates

Add `memory_flush` section to `config/memory.php`:

```php
'memory_flush' => [
    'enabled' => env('MEMORY_FLUSH_ENABLED', true),
    'soft_threshold_tokens' => 4000,  // Trigger flush this many tokens before compaction threshold
    'max_flushes_per_cycle' => 1,     // Prevent repeated flushes per compaction cycle
],
```

### Service

**`app/Services/Memory/MemoryFlushService.php`**

```php
class MemoryFlushService
{
    public function __construct(
        private ConversationCompactionService $compactionService,
    ) {}

    /**
     * Check if a memory flush should be triggered.
     * Flush happens when we're within soft_threshold_tokens of the compaction threshold,
     * AND we haven't already flushed for this compaction cycle.
     */
    public function shouldFlush(string $channelId, User $agent, iterable $messages): bool
    {
        if (!config('memory.memory_flush.enabled', true)) {
            return false;
        }

        $summary = ConversationSummary::where('channel_id', $channelId)
            ->where('agent_id', $agent->id)
            ->first();

        $maxFlushes = config('memory.memory_flush.max_flushes_per_cycle', 1);
        if ($summary && $summary->flush_count >= $maxFlushes) {
            return false;
        }

        $totalTokens = $this->estimateTokens($messages);
        $compactionThreshold = config('memory.compaction.context_window', 128000)
                             * config('memory.compaction.threshold_ratio', 0.75);
        $softThreshold = $compactionThreshold - config('memory.memory_flush.soft_threshold_tokens', 4000);

        return $totalTokens > $softThreshold && $totalTokens < $compactionThreshold;
    }

    /**
     * Execute a silent memory flush: run the agent with a flush prompt
     * that instructs it to save important memories.
     */
    public function flush(string $channelId, User $agent): void
    {
        $flushPrompt = $this->buildFlushPrompt();

        // Create a transient agent instance with the flush prompt
        $agentInstance = OpenCompanyAgent::for($agent, $channelId);

        // Run a silent prompt — the agent should use save_memory tool calls
        // but the text response is discarded (not posted to the channel)
        $response = $agentInstance->prompt($flushPrompt);

        // Increment flush count to prevent re-flushing
        ConversationSummary::where('channel_id', $channelId)
            ->where('agent_id', $agent->id)
            ->increment('flush_count');

        Log::info('Memory flush completed', [
            'agent' => $agent->name,
            'channel' => $channelId,
            'tool_calls' => $response->toolCalls->count(),
        ]);
    }

    private function buildFlushPrompt(): string
    {
        return <<<'PROMPT'
        Pre-compaction memory flush. Your conversation context is about to be compacted
        (older messages will be summarized and compressed).

        Review the conversation for durable context worth preserving. Use save_memory
        (target: "log") to save important observations, decisions, preferences, or
        learnings to your daily log before they are compressed.

        Only save to target: "core" if you discovered truly high-value permanent facts
        (user preferences, key decisions) that should always be in your system prompt.

        If nothing needs saving, respond with exactly: [FLUSH_COMPLETE]
        PROMPT;
    }

    private function estimateTokens(iterable $messages): int
    {
        $total = 0;
        foreach ($messages as $msg) {
            $content = $msg->content ?? '';
            $total += (int) ceil(str_word_count($content) * 1.3);
        }
        return $total;
    }
}
```

### AgentRespondJob Integration

Hook into `app/Jobs/AgentRespondJob.php` before the `prompt()` call (line 136):

```php
// === Memory flush check (before prompting) ===
try {
    $flushService = app(MemoryFlushService::class);
    $currentMessages = $agentInstance->messages();
    if ($flushService->shouldFlush($this->channelId, $this->agent, $currentMessages)) {
        $flushStep = $task->addStep('Flushing memories before compaction', 'action');
        $flushStep->start();
        $flushService->flush($this->channelId, $this->agent);
        $flushStep->complete();
    }
} catch (\Throwable $e) {
    Log::warning('Memory flush failed', ['error' => $e->getMessage()]);
}
// === End memory flush ===

$response = $agentInstance->prompt($this->userMessage->content);
```

### Tests

| Test | What it verifies |
|------|-----------------|
| `MemoryFlushServiceTest` | Detects soft threshold, respects max_flushes_per_cycle, builds correct prompt |
| `AgentRespondJobFlushTest` | Flush triggered before prompt when threshold met, not triggered when below threshold |

### Files to Create/Modify

```
app/Services/Memory/MemoryFlushService.php                (new)
app/Jobs/AgentRespondJob.php                              (modify — add flush hook)
config/memory.php                                         (modify — add memory_flush section)
tests/Feature/Services/Memory/MemoryFlushServiceTest.php
tests/Feature/Jobs/AgentRespondJobFlushTest.php
```

---

## Phase 6: Hybrid Search (BM25 + Vector)

### Goal
Combine vector similarity search with PostgreSQL full-text search (BM25-equivalent) for more robust retrieval. Some queries work better with exact keyword matching, others with semantic understanding --- hybrid search gives the best of both.

### Depends On
Phase 1 (DocumentChunk model), Phase 2 (DocumentIndexingService), Phase 3 (RecallMemory tool)

### Database

**Migration: `add_search_vector_to_document_chunks`**

```php
// Add tsvector column with GIN index
Schema::table('document_chunks', function (Blueprint $table) {
    $table->addColumn('tsvector', 'search_vector')->nullable();
});

// Create GIN index for fast full-text search
DB::statement('CREATE INDEX document_chunks_search_vector_idx ON document_chunks USING GIN (search_vector)');

// Create trigger to auto-populate search_vector on insert/update
DB::statement("
    CREATE OR REPLACE FUNCTION document_chunks_search_vector_update() RETURNS trigger AS $$
    BEGIN
        NEW.search_vector := to_tsvector('english', COALESCE(NEW.content, ''));
        RETURN NEW;
    END
    $$ LANGUAGE plpgsql;
");

DB::statement("
    CREATE TRIGGER document_chunks_search_vector_trigger
    BEFORE INSERT OR UPDATE OF content ON document_chunks
    FOR EACH ROW EXECUTE FUNCTION document_chunks_search_vector_update();
");

// Backfill existing rows
DB::statement("UPDATE document_chunks SET search_vector = to_tsvector('english', COALESCE(content, ''))");
```

### Service

**`app/Services/Memory/HybridSearchService.php`**

```php
class HybridSearchService
{
    public function __construct(
        private EmbeddingService $embedder,
    ) {}

    /**
     * Hybrid search: combine vector similarity with full-text search.
     *
     * @return Collection  Ranked results with combined scores
     */
    public function search(
        string $query,
        string $collection = 'general',
        ?string $agentId = null,
        int $limit = 6,
        float $minSimilarity = 0.5,
    ): Collection {
        $semanticWeight = config('memory.search.hybrid_weights.semantic', 0.7);
        $keywordWeight  = config('memory.search.hybrid_weights.keyword', 0.3);
        $maxSnippetChars = 700;
        $maxInjectedChars = 4000;

        // 1. Vector search
        $vectorResults = $this->vectorSearch($query, $collection, $agentId, $limit * 2, $minSimilarity);

        // 2. Full-text search
        $ftsResults = $this->ftsSearch($query, $collection, $agentId, $limit * 2);

        // 3. Merge by chunk ID with weighted scores
        $merged = $this->mergeResults($vectorResults, $ftsResults, $semanticWeight, $keywordWeight);

        // 4. Sort by combined score, apply limits
        $ranked = $merged->sortByDesc('score')->take($limit);

        // 5. Apply result clamping
        $totalChars = 0;
        $clamped = $ranked->filter(function ($result) use ($maxSnippetChars, $maxInjectedChars, &$totalChars) {
            $snippet = Str::limit($result['content'], $maxSnippetChars);
            if ($totalChars + strlen($snippet) > $maxInjectedChars) {
                return false;
            }
            $totalChars += strlen($snippet);
            return true;
        });

        return $clamped->values();
    }

    private function vectorSearch(
        string $query,
        string $collection,
        ?string $agentId,
        int $limit,
        float $minSimilarity,
    ): Collection {
        $queryEmbedding = $this->embedder->embed($query);
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        $builder = DocumentChunk::query()
            ->where('collection', $collection)
            ->selectRaw('id, document_id, content, metadata, 1 - (embedding <=> ?) as vector_score', [$vectorString])
            ->having('vector_score', '>=', $minSimilarity)
            ->orderByDesc('vector_score')
            ->limit($limit);

        if ($agentId !== null) {
            $builder->where('agent_id', $agentId);
        } else {
            $builder->whereNull('agent_id');
        }

        return $builder->get();
    }

    private function ftsSearch(
        string $query,
        string $collection,
        ?string $agentId,
        int $limit,
    ): Collection {
        $tsQuery = $this->buildTsQuery($query);

        $builder = DocumentChunk::query()
            ->where('collection', $collection)
            ->whereRaw('search_vector @@ to_tsquery(\'english\', ?)', [$tsQuery])
            ->selectRaw(
                'id, document_id, content, metadata, ts_rank(search_vector, to_tsquery(\'english\', ?)) as fts_score',
                [$tsQuery]
            )
            ->orderByDesc('fts_score')
            ->limit($limit);

        if ($agentId !== null) {
            $builder->where('agent_id', $agentId);
        } else {
            $builder->whereNull('agent_id');
        }

        return $builder->get();
    }

    /**
     * Merge vector and FTS results by chunk ID with weighted scoring.
     * Normalizes scores to [0, 1] range before combining.
     */
    private function mergeResults(
        Collection $vectorResults,
        Collection $ftsResults,
        float $semanticWeight,
        float $keywordWeight,
    ): Collection {
        $merged = collect();

        // Normalize vector scores
        $maxVector = $vectorResults->max('vector_score') ?: 1;
        $vectorNormalized = $vectorResults->keyBy('id')->map(fn ($r) => [
            'id'          => $r->id,
            'document_id' => $r->document_id,
            'content'     => $r->content,
            'metadata'    => $r->metadata,
            'vector_score' => $r->vector_score / $maxVector,
            'fts_score'   => 0,
        ]);

        // Normalize FTS scores
        $maxFts = $ftsResults->max('fts_score') ?: 1;
        foreach ($ftsResults as $result) {
            $normalizedFts = $result->fts_score / $maxFts;

            if ($vectorNormalized->has($result->id)) {
                // Merge: chunk appears in both results
                $existing = $vectorNormalized->get($result->id);
                $existing['fts_score'] = $normalizedFts;
                $vectorNormalized->put($result->id, $existing);
            } else {
                $vectorNormalized->put($result->id, [
                    'id'          => $result->id,
                    'document_id' => $result->document_id,
                    'content'     => $result->content,
                    'metadata'    => $result->metadata,
                    'vector_score' => 0,
                    'fts_score'   => $normalizedFts,
                ]);
            }
        }

        // Calculate combined scores
        return $vectorNormalized->map(function ($r) use ($semanticWeight, $keywordWeight) {
            $r['score'] = ($r['vector_score'] * $semanticWeight) + ($r['fts_score'] * $keywordWeight);
            return $r;
        })->values();
    }

    /**
     * Convert a natural language query to a PostgreSQL tsquery.
     * Splits on spaces, joins with & (AND).
     */
    private function buildTsQuery(string $query): string
    {
        $words = array_filter(explode(' ', trim($query)), fn ($w) => strlen($w) > 1);
        return implode(' & ', array_map(fn ($w) => preg_replace('/[^a-zA-Z0-9]/', '', $w), $words));
    }
}
```

### Integration

1. **Replace vector-only search in `DocumentIndexingService::search()`** with a call to `HybridSearchService::search()`, or make `DocumentIndexingService` delegate to `HybridSearchService` internally.

2. **Update `RecallMemory` tool** to use `HybridSearchService` instead of `DocumentIndexingService::search()`:

```php
// Before (Phase 3):
$results = $this->indexer->search($query, 'memory', $this->agent->id, $limit);

// After (Phase 6):
$results = $this->hybridSearch->search($query, 'memory', $this->agent->id, $limit);
```

3. **Update `SearchDocuments` tool** semantic mode to use `HybridSearchService`.

### Tests

| Test | What it verifies |
|------|-----------------|
| `HybridSearchServiceTest` | Vector-only results, FTS-only results, merged results with correct weighting |
| `ScoreNormalizationTest` | Scores normalized to [0,1], combined scores correct |
| `ResultClampingTest` | maxResults, maxSnippetChars, maxInjectedChars all enforced |
| `TsQueryBuildingTest` | Natural language converted to valid tsquery |
| Migration test | tsvector column created, GIN index exists, trigger fires on insert/update |

### Files to Create/Modify

```
database/migrations/YYYY_MM_DD_000004_add_search_vector_to_document_chunks.php
app/Services/Memory/HybridSearchService.php                       (new)
app/Services/Memory/DocumentIndexingService.php                   (modify — delegate to hybrid)
app/Agents/Tools/Memory/RecallMemory.php                          (modify — use hybrid search)
app/Agents/Tools/Docs/SearchDocuments.php                         (modify — use hybrid in semantic mode)
tests/Feature/Services/Memory/HybridSearchServiceTest.php
```

---

## Summary

| Phase | Memory Type | What | Key Files | Depends On |
|-------|-------------|------|-----------|------------|
| 1 | Infrastructure | pgvector + ChunkingService + EmbeddingService | `Services/Memory/Chunking*.php`, `Services/Memory/Embedding*.php` | --- |
| 2 | Knowledge Base | Document indexing + observer + semantic search | `Services/Memory/DocumentIndexing*.php`, `Observers/DocumentObserver.php` | Phase 1 |
| 3 | **LTM** | SaveMemory + RecallMemory agent tools | `Tools/Memory/SaveMemory.php`, `Tools/Memory/RecallMemory.php` | Phase 1, 2 |
| 4 | **STM** | Conversation compaction + summaries | `Services/Memory/ConversationCompaction*.php`, `ChannelConversationLoader.php` | Phase 1 |
| 5 | **STM → LTM** | Pre-compaction memory flush | `Services/Memory/MemoryFlushService.php`, `AgentRespondJob.php` | Phase 3, 4 |
| 6 | Infrastructure | Hybrid search (BM25 + vector) | `Services/Memory/HybridSearchService.php` | Phase 1, 2, 3 |

### OpenClaw Patterns Adapted

| Pattern | OpenClaw | OpenCompany Adaptation |
|---------|----------|----------------------|
| Storage | SQLite per agent with sqlite-vec | Single PostgreSQL + pgvector, scoped by `agent_id` |
| Chunking | 400 tokens, 80 overlap | 512 tokens, 64 overlap (from `config/memory.php`) |
| Embedding | Pluggable, OpenAI primary | OpenAI text-embedding-3-small, cache by SHA256 |
| Hybrid weights | 0.7 vector + 0.3 BM25 | Same (from `config/memory.php`) |
| Compaction | Chunk-based, 0.4 keep ratio | Same, with cumulative summaries |
| Memory flush | Silent turn with NO_REPLY | Silent turn with `[FLUSH_COMPLETE]`, response discarded |
| Result clamping | maxResults=6, maxSnippetChars=700, maxInjectedChars=4000 | Same |
| Batch embeddings | Disabled by default; opt-in for large backfills | Always available via `embedBatch()`; used in bulk indexing jobs |
| Memory init timing | QMD eager-initialized on gateway startup (non-blocking) | Lazy via Laravel DI container (pgvector always warm) |
| Memory scope | Per-agent directory isolation + collection scoping (QMD `-c <name>` args) | Per-agent `agent_id` column + `collection` column scoping |
| Memory write tool | No dedicated save tool (uses filesystem write/edit directly) | Dedicated `save_memory` tool with `target` param |
| Memory read tools | `memory_search` (semantic) + `memory_get` (read by path/line) | `recall_memory` (semantic search over all LTM) |
| Memory files | `MEMORY.md` (curated) + `memory/YYYY-MM-DD.md` (daily logs) | Same layout under `agents/{slug}/` |
| MEMORY.md loading | Only in private sessions (scope: `chatType: "direct"`) | Always loaded via identity file system |
| Session memory hook | On `/new` command, saves transcript to `memory/YYYY-MM-DD-slug.md` | Not applicable (our sessions are persistent) |

---

## Appendix A: OpenClaw Memory Architecture — Deep Dive

> This appendix documents OpenClaw's exact memory implementation as a reference for our adaptation.
> Source: `inspiration/openclaw/` (v2026.2.9, Feb 2026)

### A.1 Memory Files & Layout

OpenClaw uses **plain Markdown in the agent workspace** as the source of truth:

| File | Purpose | When loaded | Write pattern |
|------|---------|-------------|---------------|
| `MEMORY.md` | Curated long-term memory | **Only in private/direct sessions** (never in group contexts) | Overwrite/update by agent (using `write`/`edit` tools) |
| `memory/YYYY-MM-DD.md` | Daily log entries | Today + yesterday loaded at session start | Append-only |
| `memory/YYYY-MM-DD-slug.md` | Session transcripts | Not auto-loaded; searchable via `memory_search` | Created by `session-memory` hook on `/new` command |

Key insight: **MEMORY.md is NOT always loaded.** OpenClaw explicitly skips it in group chats for privacy. Our system loads MEMORY.md always (via the identity file pipeline), which is simpler but means we should be careful about what agents store there.

### A.2 Memory Tools (Read-Only!)

**OpenClaw has NO dedicated `save_memory` tool.** Agents write to memory using the standard filesystem tools (`write`, `edit`, `exec`). The memory tools are read-only:

```typescript
// memory_search — Semantic search over MEMORY.md + memory/*.md
description: "Mandatory recall step: semantically search MEMORY.md + memory/*.md
(and optional session transcripts) before answering questions about prior work,
decisions, dates, people, preferences, or todos; returns top snippets with path + lines."

// memory_get — Read specific file content by path + line range
description: "Safe snippet read from MEMORY.md or memory/*.md with optional from/lines;
use after memory_search to pull only the needed lines and keep context small."
```

This means OpenClaw's agents must know the file layout and manually write to the correct paths. Our `save_memory` tool abstracts this complexity away, which is better for our multi-agent setup where agents shouldn't need filesystem knowledge.

### A.3 System Prompt — Memory Section

OpenClaw's system prompt includes a `## Memory Recall` section (only for non-subagent sessions that have memory tools enabled):

```
## Memory Recall
Before answering anything about prior work, decisions, dates, people, preferences,
or todos: run memory_search on MEMORY.md + memory/*.md; then use memory_get to pull
only the needed lines. If low confidence after search, say you checked.
Citations: include Source: <path#line> when it helps the user verify memory snippets.
```

This is notably **recall-focused** — it tells the agent when to search, not when to save. OpenClaw relies on the memory docs (`docs/concepts/memory.md`) and the flush prompt to drive save behavior.

### A.4 Memory Flush Prompts (Pre-Compaction)

OpenClaw's flush uses two prompts injected during the silent turn:

**System prompt append:**
```
Pre-compaction memory flush turn.
The session is near auto-compaction; capture durable memories to disk.
You may reply, but usually NO_REPLY is correct.
```

**User message (the flush trigger):**
```
Pre-compaction memory flush. Store durable memories now
(use memory/YYYY-MM-DD.md; create memory/ if needed).
If nothing to store, reply with NO_REPLY.
```

Key details:
- Flush targets **daily logs** (`memory/YYYY-MM-DD.md`), not MEMORY.md
- `NO_REPLY` is a sentinel token that suppresses delivery to the user
- One flush per compaction cycle (tracked via `memoryFlushCompactionCount` in session store)
- Flush is **skipped** for read-only sandboxed workspaces and CLI providers
- Soft threshold: triggers 4000 tokens before compaction would fire

### A.5 Compaction

OpenClaw's compaction is handled by the embedded Pi agent runtime, not by OpenClaw itself:

- **Trigger**: `contextTokens > contextWindow - reserveTokens`
- **Output**: A `compaction` entry in the JSONL transcript with `firstKeptEntryId` and `tokensBefore`
- **Effect**: Future turns see compaction summary + messages after the kept entry
- **Config**: `reserveTokens: 16384`, `keepRecentTokens: 20000`
- **Safety floor**: Minimum 20000 tokens reserve to ensure room for pre-compaction flush
- **Boot sync**: `memory.qmd.update.waitForBootSync` (default `false`) — when `true`, QMD boot refresh blocks startup. Default non-blocking behavior means first searches may hit a partially warmed index.
- **Manual**: `/compact` command (optionally with focus instructions)
- **Compaction is persistent** in the JSONL transcript, unlike session pruning (which is in-memory only)

### A.6 Session-Memory Hook

When the user runs `/new` (start a new session), OpenClaw's `session-memory` hook:

1. Reads the last N messages (default 15) from the previous session
2. Uses LLM to generate a descriptive slug (e.g., "api-design", "bug-fix")
3. Saves to `memory/YYYY-MM-DD-slug.md` with session metadata
4. This is separate from the pre-compaction flush — it's a session-end memory capture

### A.7 Workspace Files Loaded Into System Prompt

OpenClaw loads these workspace files as "Project Context" in the system prompt:

```
IDENTITY.md → Agent identity and personality
SOUL.md     → Persona and tone guidance
USER.md     → User preferences and context
AGENTS.md   → Multi-agent awareness
TOOLS.md    → External tool guidance
MEMORY.md   → Curated long-term memory (private sessions only!)
HEARTBEAT.md → Heartbeat prompt config
BOOTSTRAP.md → First-run bootstrapping (only for brand new workspaces)
```

For subagent sessions, only `AGENTS.md` and `TOOLS.md` are loaded (privacy/scope restriction).

### A.8 Thesis Verification

**Claim 1: "If the agent saves memory, isn't it always in MEMORY.md?"**

**Partially incorrect.** In OpenClaw, agents write to TWO locations:
- `MEMORY.md` — for curated, high-value, durable facts (preferences, key decisions)
- `memory/YYYY-MM-DD.md` — for daily running context, timestamped observations

The **memory flush explicitly targets daily logs**, not MEMORY.md: `"Store durable memories now (use memory/YYYY-MM-DD.md)"`. OpenClaw's docs say: "Decisions, preferences, and durable facts go to MEMORY.md. Day-to-day notes and running context go to memory/YYYY-MM-DD.md."

**Claim 2: "Short-term memory is like summaries of compactions?"**

**Correct.** STM is the conversation context window. When it fills up:
1. Pre-compaction flush saves important context to LTM (daily logs)
2. Compaction summarizes older messages into a persistent summary entry
3. Future turns see: compaction summary + recent messages

The compaction summary IS the compressed form of STM. It's lossy — hence why the flush exists to promote key info to LTM before compression.

### A.9 v2026.2.9 Changes

The following changes were introduced in OpenClaw v2026.2.9 (tag `v2026.2.9`, Feb 2026):

**1. Config migration: top-level → agents.defaults.memorySearch**
`memorySearch` config moved from top-level to `agents.defaults.memorySearch`. A legacy migration rule auto-migrates old configs and logs a deprecation warning. OpenClaw's `docs/concepts/memory.md` now explicitly states: *"Configure memory search under `agents.defaults.memorySearch` (not top-level `memorySearch`)."* Per-agent overrides take precedence over the new default location.

**2. QMD eager initialization**
New `server-startup-memory.ts` module. The QMD memory manager is now initialized immediately on gateway startup (fire-and-forget, non-blocking) instead of lazily on first `memory_search` call. Update/embed timers are armed immediately. This addresses the documented "first search may be slow" problem by warming up GGUF models during startup. Boot refresh runs in background by default; set `memory.qmd.update.waitForBootSync = true` for blocking behavior.

**3. Collection scoping**
New `buildCollectionFilterArgs()` method in `QmdMemoryManager`. QMD queries are now scoped to managed collections via `-c <name>` CLI args. If no managed collections are configured, the query returns empty results and logs a warning instead of searching undefined scope. Prevents accidental data exposure from misconfigured setups.

**4. Model cache reuse**
New `symlinkSharedModels()` method. Symlinks the shared `~/.cache/qmd/models/` directory into each agent-specific `XDG_CACHE_HOME` path. This solves the problem of agent isolation (per-agent `XDG_CACHE_HOME` override) causing re-downloads of ~2.1 GB GGUF models. Result: per-agent index isolation + globally shared ML models. Cross-platform: handles `XDG_CACHE_HOME` on Linux/macOS and `LOCALAPPDATA` on Windows. Not directly applicable to our architecture (we use API-based embeddings, not local models).

**5. Batch embeddings default off**
`agents.defaults.memorySearch.remote.batch.enabled` default changed from `true` to `false`. Batch API is now opt-in. Rationale: synchronous embedding is adequate for incremental updates; batch is mainly beneficial for large backfills. Providers supporting batch: OpenAI Batch API, Gemini async embeddings, Voyage AI.

**6. ChatType unification (`dm` → `direct`)**
Session key parsing in `QmdMemoryManager.extractAgentIdFromSessionKey()` now accepts both `"direct"` and `"dm"` for backward compatibility. New sessions use `":direct:"` in generated keys. The QMD scope default rule uses `chatType: "direct"`. This is a semantic rename — `"direct"` is clearer than `"dm"`.

> Note: Utility consolidation (commit ec910a235, `formatError` → `formatErrorMessage`) is an internal refactor with no architectural impact.

---

## Appendix B: Complete System Prompt for Memory

> This is the full memory-related system prompt text to inject into `OpenCompanyAgent::instructions()`.
> It should be appended after the identity files are loaded, so the agent already has MEMORY.md context.

```
## Memory System

You have two types of memory:

### Short-Term Memory (STM)
Your current conversation context. You can see recent messages directly. Older messages
are automatically summarized when the context window fills up. **You don't manage STM** —
the system handles it for you.

### Long-Term Memory (LTM)
Durable memories that persist across all conversations. You manage LTM explicitly:

- **MEMORY.md** (core memory) — Already loaded in your system prompt above. Contains
  curated, high-value facts. You can add to it via `save_memory` with `target: "core"`.
- **Daily logs** — Timestamped entries searchable via `recall_memory`. Written via
  `save_memory` with `target: "log"` (default).

### save_memory

Persist information to your long-term memory. Two targets:

| Target | Storage | Loaded | Best for |
|--------|---------|--------|----------|
| `"core"` | MEMORY.md | Always (system prompt) | User preferences, key decisions, organizational knowledge, durable facts |
| `"log"` (default) | Daily log | On demand via `recall_memory` | Running context, timestamped observations, session learnings |

Guidelines:
- Be specific: include who, what, why, and when
- Prefer `"core"` only for truly durable facts that should always be in context
- Prefer `"log"` for most saves — keeps MEMORY.md focused and manageable
- If someone says "remember this" — save it immediately (do not rely on conversation context)
- Use categories: preference, decision, learning, fact, general

### recall_memory

Semantically search your daily logs for past context. Use this:
- Before answering questions about prior work, decisions, or preferences
- At the start of complex tasks to gather relevant history
- When a user references something from a previous conversation
- When you have low confidence and need more context

If recall_memory returns nothing relevant, tell the user you checked but found no prior context.

### When to save vs when not to

**Save:**
- User expresses a preference or working style
- An important decision is made (with reasoning)
- Key facts about a project, person, or the organization
- Learnings or insights from the current conversation
- Anything the user explicitly asks you to remember

**Don't save:**
- Transient, obvious, or trivial information
- Information already in MEMORY.md
- Raw conversation snippets without context
- Temporary task state that won't matter later
```

### Updated Phase 5 Flush Prompt

The flush prompt should align with OpenClaw's proven approach — target daily logs, be concise:

```php
private function buildFlushPrompt(): string
{
    return <<<'PROMPT'
    Pre-compaction memory flush. Your conversation context is about to be compacted
    (older messages will be summarized and compressed).

    Review the conversation for durable context worth preserving. Use save_memory
    (target: "log") to save important observations, decisions, preferences, or
    learnings to your daily log before they are compressed.

    Only save to target: "core" if you discovered truly high-value permanent facts
    (user preferences, key decisions) that should always be in your system prompt.

    If nothing needs saving, respond with exactly: [FLUSH_COMPLETE]
    PROMPT;
}
```

# Making Embeddings Optional — Implementation Plan

Date: 2026-04-07
Status: Planning. Current code still requires embeddings on the write path: `DocumentIndexingService::index()` calls `EmbeddingService::embedBatch()`, `document_chunks.embedding` is non-null in the base migration, and `pgvector` remains a composer dependency.
Related: Identity + Memory Refactor, Dream/VFS doc

---

## Goal

Allow OpenCompany to run without any embedding API. When disabled, the system uses FTS (full-text search) + VFS tools (`grep`, `glob`) for retrieval. No OpenAI API calls, no pgvector dependency required, no per-query embedding cost.

This is useful for:
- Self-hosted / air-gapped deployments without external API access
- Cost-sensitive workspaces that only need exact-match retrieval
- Workspaces where VFS tools (`grep_files`, `glob_files`) provide sufficient retrieval
- Testing and development without configuring embedding providers

---

## Current State

Embeddings are hard-required in two places:

1. **Write path** — `DocumentIndexingService::index()` unconditionally calls `EmbeddingService::embedBatch()`. Every document save, memory save, and memory edit triggers embedding API calls. If the API is unreachable, the job fails after 3 retries.

2. **Read path** — `DocumentIndexingService::vectorSearch()` embeds the query and uses pgvector `<=>` cosine distance. Already has try/catch on the embed call and falls back to FTS-only.

The **read path already degrades gracefully**. The **write path does not**.

---

## Architecture: With vs Without Embeddings

### With Embeddings (Current)

```
Document saved
    → IndexDocumentJob
        → ChunkingService::chunk()
        → EmbeddingService::embedBatch()  ← API call, costs money
        → Store chunks with embedding vectors
        → HNSW index created lazily

Search query
    → EmbeddingService::embed(query)     ← API call, costs money
    → Vector cosine search (70% weight)
    → FTS tsvector search (30% weight)
    → RRF merge
    → Optional reranking
```

- Semantic similarity: "refund policy" matches "return process"
- Cost: API call per chunk on write, API call per query on search
- Infrastructure: pgvector extension, HNSW index, embedding cache table
- External dependency: OpenAI API (or Ollama)

### Without Embeddings (Target)

```
Document saved
    → IndexDocumentJob
        → ChunkingService::chunk()
        → Store chunks WITHOUT embedding vectors
        → tsvector trigger fires automatically on INSERT

Search query
    → FTS tsvector search (100% weight)
    → No vector search
    → Optional reranking still works (text-based)
```

- Exact match / keyword search via PostgreSQL FTS
- Cost: zero API calls
- Infrastructure: standard PostgreSQL (no pgvector needed)
- No external dependency
- VFS tools (`grep_files`, `glob_files`) complement FTS for structured retrieval

### Quality Tradeoff

| Query Type | With Embeddings | Without (FTS + VFS) |
|---|---|---|
| "refund policy" | Matches "return process" (semantic) | Only matches "refund" or "policy" (keyword) |
| "invoice-1234" | Matches, but fuzzy | Exact match via `grep_files` or FTS — **better** |
| "Q1 revenue report" | Finds semantically similar | Finds docs with those keywords |
| "latest status report" | May return stale similar docs | `list_files` by date + `read_file` — **better** |
| "how do we handle errors" | Matches error-handling docs | Matches "error" or "handle" — **worse** |

For exact-lookup-heavy workflows, FTS + VFS is sufficient. For conceptual/broad queries, embeddings add significant value.

---

## Changes Required

### 1. Config — New Toggle

**`config/memory.php`** — Add `enabled` flag:

```php
'embedding' => [
    'enabled' => env('MEMORY_EMBEDDINGS_ENABLED', true),
    'provider' => env('MEMORY_EMBEDDING_PROVIDER', 'openai'),
    'model' => env('MEMORY_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'dimensions' => (int) env('MEMORY_EMBEDDING_DIMENSIONS', 1536),
],
```

**`.env.example`** — Add:

```
MEMORY_EMBEDDINGS_ENABLED=true
```

**`app/Models/AppSetting.php`** — Add to memory defaults (around line 155):

```php
'memory_embeddings_enabled' => config('memory.embedding.enabled', true),
```

### 2. Database — Nullable Embedding Column

The `embedding` column on `document_chunks` is currently `NOT NULL` on PostgreSQL. Must be made nullable.

**New migration** `database/migrations/2026_04_XX_make_embedding_nullable.php`:

```php
public function up(): void
{
    if (DB::getDriverName() === 'pgsql') {
        DB::statement('ALTER TABLE document_chunks ALTER COLUMN embedding DROP NOT NULL');
    }
}

public function down(): void
{
    if (DB::getDriverName() === 'pgsql') {
        DB::statement('ALTER TABLE document_chunks ALTER COLUMN embedding SET NOT NULL');
    }
}
```

### 3. Core Service — DocumentIndexingService

**`app/Services/Memory/DocumentIndexingService.php`**

#### `index()` — Skip embedding when disabled (around line 88):

```php
$embeddingsEnabled = AppSetting::getValue('memory_embeddings_enabled')
    ?? config('memory.embedding.enabled', true);

if ($embeddingsEnabled) {
    $embeddings = $this->embedder->embedBatch($chunks);
} else {
    $embeddings = array_fill(0, count($chunks), null);
}

// Store chunks (embedding will be null when disabled)
foreach ($chunks as $i => $chunk) {
    DocumentChunk::create([
        'document_id' => $document->id,
        'content' => $chunk,
        'embedding' => $embeddings[$i],
        'collection' => $collection,
        'agent_id' => $agentId,
        'metadata' => [...],
    ]);
}

// Only create HNSW index when embeddings exist
if ($embeddingsEnabled && !empty($embeddings[0])) {
    $this->ensureVectorIndex(count($embeddings[0]));
}
```

#### `vectorSearch()` — Handle null embeddings (around line 200):

The `<=>` cosine distance operator returns NULL for rows with null embedding, which won't pass the `>= minSimilarity` filter. But add an explicit guard for clarity:

```php
// Add to the query builder chain:
->whereNotNull('embedding')
```

This ensures rows without embeddings are excluded from vector search instead of causing issues. When all chunks have null embeddings, vector search returns empty results and FTS takes over.

#### `search()` — Explicit FTS-only when disabled:

```php
$embeddingsEnabled = AppSetting::getValue('memory_embeddings_enabled')
    ?? config('memory.embedding.enabled', true);

if (!$embeddingsEnabled) {
    return $this->ftsSearch($query, $collection, $agentId, $limit, $scopeByAgent);
}

// Normal hybrid path
$vectorResults = $this->vectorSearch(...);
$ftsResults = $this->ftsSearch(...);
```

### 4. Settings Controller — Guard Embedding Actions

**`app/Http/Controllers/Api/SettingController.php`**

- `update()` (line ~60) — Skip embedding model change detection and reset when embeddings disabled
- `dangerAction()` (line ~88) — Return error for `reset_embeddings` / `clear_embedding_cache` when disabled
- `getEmbeddingHealth()` (line ~329) — Return `{ enabled: false }` when disabled instead of querying chunks

### 5. Console Commands

**`app/Console/Commands/MemoryIndexDocuments.php`**:

```php
$embeddingsEnabled = AppSetting::getValue('memory_embeddings_enabled')
    ?? config('memory.embedding.enabled', true);

if ($this->option('fresh') && !$embeddingsEnabled) {
    $this->components->warn('Embeddings are disabled. Running text-only index (no vectors).');
}
```

Note: the command should still run — it creates chunks with FTS tsvector data even without embeddings. Just skip the embedding step.

**`app/Console/Commands/MemoryStatus.php`**:

```php
if (!$embeddingsEnabled) {
    $this->line('Embeddings: <fg=yellow>Disabled</>');
    // Skip embedding stats, show chunk counts only
}
```

### 6. Frontend — Conditional Embedding UI

**`resources/js/Pages/Settings.vue`**:

Add `memory_embeddings_enabled` to the reactive defaults and hydration.

**`resources/js/Components/settings/MemorySettings.vue`**:

- Add a toggle field for `memory_embeddings_enabled` at the top
- Wrap the "Embedding Model" picker in `v-if="memorySettingsData.memory_embeddings_enabled"`
- Wrap "Search Reranking" in the same conditional (reranking works without embeddings but is less useful)

**`resources/js/Components/settings/DebugSettings.vue`**:

- Conditionally hide the "Embedding" details section when disabled
- Show "Embeddings: Disabled" in health cards instead of stats

**`resources/js/Components/settings/DangerZoneSettings.vue`**:

- Filter out `reset_embeddings` and `clear_embedding_cache` from the actions array when disabled

### 7. TypeScript Types

Add `memory_embeddings_enabled: boolean` to the settings data type definition.

---

## What Works Without Changes

These already handle embedding failures gracefully:

| Component | Why it works |
|---|---|
| `DocumentIndexingService::vectorSearch()` | Try/catch on `embed()`, returns empty on failure |
| `DocumentIndexingService::ftsSearch()` | Pure PostgreSQL FTS, no embedding dependency |
| `DocumentIndexingService::mergeWithRRF()` | Pure math, handles either side being empty |
| `DocumentIndexingService::deindex()` | Just deletes chunks |
| `RerankingService` | Text-based LLM reranking, independent of embeddings |
| `SearchDocuments` tool | try/catch + keyword fallback in auto mode |
| `DocumentController::search()` | try/catch + LIKE fallback |
| `RecallMemory` (date/topic/peer) | Direct document load, no search |
| `ForgetMemory` tool | Only calls deindex() |
| `MemoryScopeGuard` | Access control, no search dependency |
| `ConversationCompactionService` | Summarization, no embeddings |
| `MemoryFlushService` | Pre-compaction flush, no embeddings |
| Tsvector trigger | Fires on INSERT regardless of embeddings |

---

## Migration Path for Existing Deployments

For workspaces that have embeddings and want to disable them:

1. Set `MEMORY_EMBEDDINGS_ENABLED=false` in `.env` (or toggle in Settings UI)
2. Run `php artisan migrate` (nullable embedding column)
3. Existing chunks keep their embeddings — they still work in search
4. New/updated documents get chunks without embeddings
5. Over time, vector search returns fewer results as stale chunks age out
6. Optional: run "Reset Embeddings" to clear all chunks, then reindex (text-only)

For workspaces starting fresh without embeddings:

1. Set `MEMORY_EMBEDDINGS_ENABLED=false` in `.env`
2. pgvector extension doesn't need to be installed
3. All retrieval goes through FTS + VFS tools

---

## Files Changed Summary

| File | Change | Effort |
|---|---|---|
| `config/memory.php` | Add `enabled` key | Trivial |
| `.env.example` | Add `MEMORY_EMBEDDINGS_ENABLED=true` | Trivial |
| `app/Models/AppSetting.php` | Add to defaults | Trivial |
| `database/migrations/*_make_embedding_nullable.php` | New migration | Trivial |
| `app/Services/Memory/DocumentIndexingService.php` | Skip embed in `index()`, FTS-only in `search()`, whereNotNull in `vectorSearch()` | Small |
| `app/Http/Controllers/Api/SettingController.php` | Guard embedding actions, return disabled status | Small |
| `app/Console/Commands/MemoryIndexDocuments.php` | Info message when disabled | Trivial |
| `app/Console/Commands/MemoryStatus.php` | Skip embedding sections | Trivial |
| `resources/js/Pages/Settings.vue` | Add toggle to defaults/hydration | Trivial |
| `resources/js/Components/settings/MemorySettings.vue` | Conditional embedding UI | Small |
| `resources/js/Components/settings/DebugSettings.vue` | Conditional embedding stats | Trivial |
| `resources/js/Components/settings/DangerZoneSettings.vue` | Filter embedding actions | Trivial |
| TypeScript types | Add `memory_embeddings_enabled` | Trivial |

**Total effort: ~1 day.** The backend changes are ~30 lines. The frontend changes are mostly conditionals.

---

## Relationship to Other Planning Docs

- **Dream/VFS doc** — Proposed unix-style VFS tools (`grep`, `glob`, `head`) become more important when embeddings are disabled. Current app file tools already provide path-based list/read/search operations, but not the exact unix-style tools in that plan.
- **Memory refactor** — The refactor has landed and added topic, peer, log, and core memory structure. These get chunked and FTS-indexed under the current observer/indexing flow, but writes still fail if embedding generation fails before chunk storage.
- **Temporal decay** (from Dream doc) — Still applies to FTS results via the same scoring mechanism.

The recommended order from the current baseline: ship this embedding toggle first, then add unix-style VFS retrieval tools if needed, then Dream consolidation.

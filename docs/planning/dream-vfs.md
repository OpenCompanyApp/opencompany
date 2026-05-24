# Dream (Memory Consolidation) + VFS (Virtual Filesystem) for Agent Retrieval

Date: 2026-04-07
Status: Planning. The identity/memory folder refactor and app file tools now exist; Dream consolidation, temporal decay, cache pruning, and unix-style `grep`/`glob`/`head` VFS tools remain proposed enhancements.
Depends on: Identity + Memory Refactor (implemented baseline)

---

## Context

This document covers two complementary enhancements to the agent memory system:

1. **Dream** — Offline memory consolidation: pruning, merging, and curating long-term memory that grows unboundedly over time.
2. **VFS** — Virtual filesystem tools (`grep`, `glob`, `head`) alongside existing RAG, giving agents unix-like retrieval capabilities.

Both are designed to layer on top of the implemented **Identity + Memory Refactor** (which introduced `topics/`, `logs/`, `peers/` directories, `edit_memory`, and `forget_memory` tools). Current app file tools provide `list_files`, `read_file`, `search_files`, and write/move/copy/delete operations over `WorkspaceFile` virtual paths; this document's unix-style `grep`/`glob`/`head` tools and Dream lifecycle management are still roadmap items.

---

## Part 1: Dream — Memory Consolidation

### The Problem

After the memory refactor, the folder structure is:

```
agents/{slug}/memory/
  ├── MEMORY.md          (core facts + index, injected in private agent/user contexts)
  ├── topics/{slug}.md   (curated knowledge files)
  ├── logs/YYYY-MM-DD.md (daily append-only logs)
  └── peers/
      ├── users/{id}.md
      └── agents/{id}.md
```

But there is still no lifecycle management:

- **Logs grow forever** — `memory/logs/` accumulates a new file every day. A 6-month-old agent has ~180 daily logs.
- **No temporal decay** — a 6-month-old log ranks equally with yesterday's in search.
- **No contradiction resolution** — conflicting facts coexist in separate log files.
- **Topics can go stale** — a topic file about "Q1 planning" becomes outdated but is never revisited.
- **Peers accumulate noise** — first-impression notes persist even after the relationship evolves.
- **Embedding cache grows** — `embedding_cache` table has no eviction.
- **No importance scoring** — all chunks weighted equally regardless of value.

### What "Dreaming" Means

From cognitive science: offline memory consolidation during sleep. The brain reviews experiences, extracts patterns, strengthens important memories, and prunes noise.

Claude Code has a `dream` task type (background auto-dream). Our own planning doc (`context-management-redesign.md:495-509`) proposes:

> *"Add a low-priority background process that periodically consolidates working memory into durable memory or small priority notes."*

Triggers: idle time, session count, elapsed wall time, after compaction.
Guardrails: lock to avoid concurrent consolidators, strict size budgets, skip during active context pressure.

### The Dream Pipeline

```
┌──────────────────────────────────────────────────────────────┐
│                    TRIGGER                                    │
│  • Scheduled (weekly per agent)                               │
│  • After N conversations                                      │
│  • Idle detection (no messages in X hours)                    │
│  • Manual artisan command                                     │
└─────────────┬────────────────────────────────────────────────┘
              │
              ▼
┌──────────────────────────────────────────────────────────────┐
│                 PHASE 1: GATHER                               │
│                                                              │
│  1. Collect logs older than threshold (default: 7 days)      │
│  2. Scan topic files for staleness (not updated in N days)   │
│  3. Scan peer files for contradictions                       │
│  4. Load access patterns (which memories are actually used)  │
└─────────────┬────────────────────────────────────────────────┘
              │
              ▼
┌──────────────────────────────────────────────────────────────┐
│                 PHASE 2: CONSOLIDATE                          │
│                                                              │
│  LLM call per memory category:                               │
│                                                              │
│  Logs → Extract key facts, decisions, learnings              │
│         Discard: temporal noise, superseded info, small talk  │
│         Output: consolidated summary per week                │
│                                                              │
│  Topics → Review for accuracy, merge overlapping topics      │
│           Flag contradictions, update stale sections          │
│           Output: updated topic files or merge candidates    │
│                                                              │
│  Peers → Review relationship evolution                       │
│          Merge contradictory observations                    │
│          Output: updated peer files                          │
└─────────────┬────────────────────────────────────────────────┘
              │
              ▼
┌──────────────────────────────────────────────────────────────┐
│                 PHASE 3: RESTRUCTURE                          │
│                                                              │
│  1. Write consolidated weekly summary to logs/consolidated/  │
│     (e.g., logs/consolidated/2026-W14.md)                    │
│  2. Archive or delete raw daily logs that were consolidated  │
│  3. Update topic files with corrected/merged content         │
│  4. Update peer files with resolved observations             │
│  5. Update MEMORY.md index if topics changed                 │
│  6. De-index removed chunks, re-index updated files          │
│  7. Prune embedding cache entries for deleted content        │
└─────────────┬────────────────────────────────────────────────┘
              │
              ▼
┌──────────────────────────────────────────────────────────────┐
│                 PHASE 4: REPORT                               │
│                                                              │
│  Log: agent, files processed, chunks added/removed,          │
│       tokens saved, consolidation count                      │
│  Optional: notify agent on next conversation                 │
└──────────────────────────────────────────────────────────────┘
```

### Tiered Implementation

#### Tier 1: Quick Wins (Days)

**1a. Temporal decay on retrieval** — Drop-in to `DocumentIndexingService::search()`:

```php
// After RRF scoring, before sorting:
$hoursOld = $chunk->created_at->diffInHours(now());
$chunk->similarity *= (0.995 ** $hoursOld);
```

Old memories don't disappear but naturally rank lower. One loop in the search results.

**1b. Embedding cache pruning** — Add to `config/memory.php`:

```php
'embedding_cache' => [
    'max_entries' => env('MEMORY_EMBEDDING_CACHE_MAX', 50_000),
],
```

New method on `EmbeddingService` or `DocumentIndexingService`:

```php
public static function pruneEmbeddingCache(): int
{
    $max = config('memory.embedding_cache.max_entries', 50_000);
    $count = DB::table('embedding_cache')->count();
    if ($count <= $max) return 0;

    $deleted = DB::table('embedding_cache')
        ->orderBy('updated_at', 'asc')
        ->limit($count - $max)
        ->delete();

    return $deleted;
}
```

Call from `DocumentIndexingService::index()` after writing new cache entries.

#### Tier 2: Consolidation Job (Week)

New files:
- `app/Jobs/ConsolidateMemoryJob.php` — queued job
- `app/Services/Memory/MemoryConsolidationService.php` — core logic
- `app/Console/Commands/MemoryConsolidate.php` — artisan command

**ConsolidateMemoryJob:**

```php
class ConsolidateMemoryJob implements ShouldQueue
{
    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        private User $agent,
        private string $scope = 'logs', // 'logs', 'topics', 'peers', 'all'
    ) {}

    public function handle(MemoryConsolidationService $consolidator): void
    {
        // Set workspace context
        app()->instance('currentWorkspace', $this->agent->workspace);

        // Acquire lock to prevent concurrent consolidation
        $lock = Cache::lock("dream:{$this->agent->id}", 300);
        if (!$lock->get()) return;

        try {
            match ($this->scope) {
                'logs'    => $consolidator->consolidateLogs($this->agent),
                'topics'  => $consolidator->consolidateTopics($this->agent),
                'peers'   => $consolidator->consolidatePeers($this->agent),
                'all'     => $consolidator->consolidateAll($this->agent),
            };
        } finally {
            $lock->release();
        }
    }
}
```

**MemoryConsolidationService — consolidateLogs():**

```php
public function consolidateLogs(User $agent): void
{
    $threshold = config('memory.consolidation.log_threshold_days', 7);

    // 1. Find daily logs older than threshold
    $logsFolder = $this->docService->getLogsFolder($agent);
    $oldLogs = Document::where('parent_id', $logsFolder->id)
        ->where('title', 'regexp_match', '^\d{4}-\d{2}-\d{2}\.md$')
        ->where('created_at', '<', now()->subDays($threshold))
        ->orderBy('created_at', 'asc')
        ->get();

    if ($oldLogs->isEmpty()) return;

    // 2. Group by week
    $grouped = $oldLogs->groupBy(fn ($doc) =>
        Carbon::parse($doc->title)->format('o-\WW') // ISO week
    );

    // 3. Consolidate each week
    foreach ($grouped as $weekKey => $weekLogs) {
        $combinedContent = $weekLogs->map(fn ($doc) =>
            "## {$doc->title}\n\n{$doc->content}"
        )->implode("\n\n");

        $summary = $this->llmConsolidate($agent, $combinedContent, 'logs');

        // 4. Write consolidated file
        $consolidatedDoc = $this->docService->createConsolidatedLog(
            $agent, $weekKey, $summary
        );

        // 5. Index the consolidated version
        $this->indexer->index($consolidatedDoc, 'memory', $agent->id);

        // 6. De-index and archive raw logs
        foreach ($weekLogs as $log) {
            $this->indexer->deindex($log);
            $log->update(['parent_id' => $archivedFolder->id]);
            // Or: $log->delete(); if no archive needed
        }
    }

    // 7. Prune orphaned embedding cache
    DocumentIndexingService::pruneEmbeddingCache();
}
```

**LLM consolidation prompt (logs):**

```
You are consolidating an AI agent's daily memory logs from the past week.
Raw logs contain unstructured observations, task notes, user interactions,
and working context — much of it is noise.

Your task:
1. Extract DURABLE facts: decisions made, preferences learned, technical
   discoveries, relationship notes.
2. DISCARD: greetings, status updates about tasks that completed,
   transient debugging notes, anything superseded by later entries.
3. RESOLVE contradictions: if an earlier entry conflicts with a later one,
   keep the later version and note the change.
4. Preserve SPECIFICS: names, dates, amounts, IDs. Do not generalize.
5. Organize by category: decisions, preferences, learnings, relationships.

Output a clean markdown summary. Be concise — the goal is to reduce volume
by 70-80% while keeping everything important.
```

**Configuration additions to `config/memory.php`:**

```php
'consolidation' => [
    'enabled' => env('MEMORY_CONSOLIDATION_ENABLED', true),
    'log_threshold_days' => (int) env('MEMORY_CONSOLIDATION_LOG_DAYS', 7),
    'topic_staleness_days' => (int) env('MEMORY_CONSOLIDATION_TOPIC_DAYS', 30),
    'schedule' => env('MEMORY_CONSOLIDATION_SCHEDULE', 'weekly'),
    'max_consolidation_tokens' => 20_000,
],
```

**Scheduling in `routes/console.php`:**

```php
if (config('memory.consolidation.enabled')) {
    $schedule = config('memory.consolidation.schedule', 'weekly');
    $schedule->command('memory:consolidate')->{$schedule}();
}
```

#### Tier 3: Advanced Consolidation (Weeks)

**3a. Importance scoring** — Add `importance` column to `document_chunks`:

```php
// Migration: add_importance_to_document_chunks.php
$table->float('importance')->default(0.5);

// Updated search scoring:
$hoursOld = $chunk->created_at->diffInHours(now());
$decay = 0.995 ** $hoursOld;
$chunk->similarity *= ($chunk->importance * 0.6 + 0.4) * $decay;
```

Importance is set during save:
- `save_memory(target: "core")` → importance 0.9
- `save_memory(target: "topic")` → importance 0.7
- `save_memory(target: "log")` → importance 0.4
- `save_memory(target: "peer")` → importance 0.6

**3b. Access frequency tracking** — Add `access_count` column:

```php
$table->unsignedInteger('access_count')->default(0);
$table->timestamp('last_accessed_at')->nullable();
```

Incremented when a chunk appears in search results. Dreams can then prioritize keeping frequently-accessed memories and pruning untouched ones.

**3c. Topic staleness detection** — The consolidation job scans topics not updated in N days:

```php
public function consolidateTopics(User $agent): void
{
    $stalenessDays = config('memory.consolidation.topic_staleness_days', 30);

    $staleTopics = $this->docService->listMemoryTopics($agent)
        ->filter(fn ($topic) =>
            $topic->updated_at->lt(now()->subDays($stalenessDays))
        );

    foreach ($staleTopics as $topic) {
        $review = $this->llmConsolidate($agent, $topic->content, 'topic');

        // LLM returns: KEEP, UPDATE, MERGE_WITH:{other_slug}, ARCHIVE
        $action = $review['action'];

        match ($action) {
            'KEEP'    => null, // no change
            'UPDATE'  => $this->updateTopicFromConsolidation($agent, $topic, $review),
            'MERGE'   => $this->mergeTopics($agent, $topic, $review['merge_with']),
            'ARCHIVE' => $this->archiveTopic($agent, $topic),
        };
    }
}
```

**3d. Mem0-style auto-extract** — Post-response hook that automatically extracts facts from conversations without the agent needing to call `save_memory`:

```php
// In whatever job handles agent responses, after the response is sent:
if (config('memory.auto_extract.enabled')) {
    dispatch(new ExtractMemoryJob($agent, $channelId, $messages));
}
```

The extraction LLM call compares new facts against existing memories and outputs ADD/UPDATE/DELETE/NOOP for each. This handles contradiction resolution automatically and reduces reliance on the agent explicitly calling save_memory.

### What OpenClaw Does That We Should Adopt

| Feature | OpenClaw | How to adapt |
|---------|----------|-------------|
| Session retention days | `retentionDays` config | Add `memory.consolidation.log_retention_days` — delete logs older than N days after consolidation |
| Embedding cache pruning | `pruneEmbeddingCacheIfNeeded()` with size cap | Add to `EmbeddingService`, call after indexing |
| Stale file cleanup | Indexed files not on disk → deleted from index | Consolidation job de-indexes archived/deleted docs |
| Incremental delta tracking | `sessionDeltas` map | Skip for now — our logs are append-only daily files |

### Interaction with the Memory Refactor

The memory refactor creates the structure that Dream needs:

| Refactor provides | Dream uses it for |
|---|---|
| `memory/logs/` folder | Consolidation target — read old logs, write `logs/consolidated/{week}.md` |
| `memory/topics/` folder | Staleness scan — find topics not updated in N days |
| `memory/peers/` folder | Peer file review — resolve contradictions, merge observations |
| `forget_memory` tool | Dream can invoke this to remove outdated entries |
| `edit_memory` tool | Dream can invoke this to update stale topics/peers |
| Collection types (`topic`, `peer`) | Dream re-indexes after consolidation |
| MEMORY.md index | Dream updates the index when topics are merged/archived |

---

## Part 2: VFS — Virtual Filesystem Retrieval

### Why VFS Alongside RAG

Studies and practical experience (Claude Code, Cursor, OpenCode) show that giving agents filesystem primitives works remarkably well for retrieval, sometimes better than embedding-based RAG for exact lookups.

**Where VFS wins:**
- Exact term/ID/number lookup: `grep "invoice-1234" /docs/`
- Browsing before you know what you need: `list_files /reports/`
- Latest file discovery: `ls -lt /reports/` then `read_file`
- Complete datasets: `glob /meetings/2026-03*.md` → read all
- Precision matters more than recall

**Where RAG wins:**
- Broad conceptual queries: "what's our refund policy?"
- Cross-document synthesis: "compare Q1 vs Q2"
- Agent doesn't know the path structure

### What Already Exists

The system is surprisingly close to a full VFS:

**FileSystemService** (`app/Services/FileSystemService.php`):
- `resolveVirtualPath()` — `/reports/q1.pdf` → WorkspaceFile record
- `listDirectory()` — `ls` equivalent
- `readFileContents()` — `cat` equivalent
- `writeFile()` — create/overwrite with auto-mkdir
- `searchFiles()` — SQL LIKE by name/description
- `ensureFolderPath()` — `mkdir -p`

**Agent file tools** (`app/Agents/Tools/Files/`) — 10 tools:
- `list_files` (= `ls`), `read_file` (= `cat`), `get_file_info` (= `stat`)
- `search_files` (= `find` by name), `write_file`, `create_folder`
- `move_file`, `copy_file`, `delete_file`, `list_disks`

**Document tools** (`app/Agents/Tools/Docs/`):
- `search_documents` — keyword + semantic search
- `get_document_tree` — folder hierarchy
- `list_documents`, `get_document` — browse + read

### What's Missing

| Unix command | Current equivalent | Gap |
|---|---|---|
| `grep` | **None** | No content search across files. `search_files` matches names only. `search_documents` does keyword on Document.content but not WorkspaceFile contents. |
| `find`/`glob` | `search_files` (name only) | No pattern-based discovery (`**/*.csv`) |
| `head`/`tail` | **None** | No line-level navigation |
| `wc` | **None** | No line/word/char count |

### Implementation Plan

#### Option A: Thin VFS Tools (Recommended — Fastest)

Add 2-3 new tools in `app/Agents/Tools/Files/`:

**1. `GrepFiles`** — Content search across workspace files and documents

```php
class GrepFiles implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private FileSystemService $fileSystem,
    ) {}

    public function description(): string
    {
        return 'Search file contents by pattern. Returns matching files with line numbers and context. Supports both workspace files and documents.';
    }

    public function handle(Request $request): string
    {
        $pattern = $request['pattern'];
        $path = $request['path'] ?? '/';
        $include = $request['include'] ?? null; // glob filter
        $limit = $request['limit'] ?? 20;

        $results = [];

        // 1. Search WorkspaceFile contents (text files on disk)
        $fileResults = $this->grepWorkspaceFiles($pattern, $path, $include, $limit);
        $results = array_merge($results, $fileResults);

        // 2. Search Document contents (DB-stored)
        if (str_starts_with($path, '/docs') || $path === '/') {
            $docResults = $this->grepDocuments($pattern, $limit - count($results));
            $results = array_merge($results, $docResults);
        }

        if (empty($results)) {
            return "No matches found for '{$pattern}'.";
        }

        return collect($results)->take($limit)
            ->map(fn ($r) => "{$r['path']}:{$r['line']}: {$r['context']}")
            ->implode("\n");
    }

    private function grepWorkspaceFiles(string $pattern, string $path, ?string $include, int $limit): array
    {
        // Resolve path to folder, get descendant text files,
        // load contents, apply preg_grep per line
        // Permission check via AgentPermissionService
    }

    private function grepDocuments(string $pattern, int $limit): array
    {
        // SQL: Document::where('content', 'regexp', $pattern)
        // or: DocumentChunk::where('content', 'regexp', $pattern)
        // Permission check via allowedFolderIds
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pattern' => $schema->string()->required()
                ->description('Regex pattern to search for (e.g., "invoice-1234", "error|warning").'),
            'path' => $schema->string()
                ->description('Virtual path to scope the search (e.g., "/docs/reports/"). Default: "/"'),
            'include' => $schema->string()
                ->description('File glob to include (e.g., "*.md", "*.csv"). Default: all text files.'),
            'limit' => $schema->integer()
                ->description('Maximum number of matches. Default: 20.'),
        ];
    }
}
```

**2. `GlobFiles`** — Pattern-based file discovery

```php
class GlobFiles implements Tool
{
    public function description(): string
    {
        return 'Find files matching a glob pattern. Returns file paths and metadata.';
    }

    public function handle(Request $request): string
    {
        $pattern = $request['pattern']; // e.g., "**/*.csv", "reports/2026-*.pdf"
        $path = $request['path'] ?? '/';

        // Query WorkspaceFile tree with name matching
        // + Document tree with title matching
        // Apply permission checks
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pattern' => $schema->string()->required()
                ->description('Glob pattern (e.g., "**/*.csv", "reports/*.md").'),
            'path' => $schema->string()
                ->description('Virtual path to scope the search. Default: "/"'),
        ];
    }
}
```

**3. `HeadFile`** — Line-level reading (optional, nice-to-have)

```php
class HeadFile implements Tool
{
    public function description(): string
    {
        return 'Read the first N lines of a file. Useful for previewing large files before reading in full.';
    }

    public function handle(Request $request): string
    {
        $path = $request['path'];
        $lines = $request['lines'] ?? 50;
        $offset = $request['offset'] ?? 0;

        // Read file contents, split by line, slice
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required()
                ->description('Virtual file path.'),
            'lines' => $schema->integer()
                ->description('Number of lines to return. Default: 50.'),
            'offset' => $schema->integer()
                ->description('Line number to start from (0-based). Default: 0.'),
        ];
    }
}
```

**Register in FilesToolProvider:**

```php
'grep_files' => [
    'class' => GrepFiles::class,
    'type' => 'read',
    'name' => 'Grep Files',
    'description' => 'Search file contents by pattern with line-level results.',
    'icon' => 'ph:magnifying-glass',
],
'glob_files' => [
    'class' => GlobFiles::class,
    'type' => 'read',
    'name' => 'Glob Files',
    'description' => 'Find files matching a name pattern.',
    'icon' => 'ph:funnel',
],
'head_file' => [
    'class' => HeadFile::class,
    'type' => 'read',
    'name' => 'Head File',
    'description' => 'Preview the first lines of a file.',
    'icon' => 'ph:file-dotted',
],
```

#### Bridging the Two Trees

The main issue: **Documents** (content in DB) and **WorkspaceFiles** (content on disk) are separate models. For VFS, `GrepFiles` must search both:

- **WorkspaceFiles**: Load text file contents from storage, apply regex
- **Documents**: SQL `REGEXP` or `LIKE` on the `content` column
- **DocumentChunks**: For richer context, search chunks and return the parent document path

Proposed virtual namespace:

```
/                    → workspace root
/docs/...            → Document tree (DB content)
/files/...           → WorkspaceFile tree (disk content)
/agents/{slug}/...   → Agent document tree (memory, identity)
```

`GrepFiles` maps `/docs/` queries to Document search, `/files/` to WorkspaceFile search, `/` to both.

#### Future: Option B — Unified VFS Layer

If VFS tools get heavy usage, create a `VirtualFilesystemService` that abstracts over both trees:

```php
class VirtualFilesystemService
{
    public function ls(string $path): array;
    public function cat(string $path): string;
    public function grep(string $pattern, string $path, array $options = []): array;
    public function glob(string $pattern, string $path): array;
    public function stat(string $path): array;
    public function head(string $path, int $lines, int $offset = 0): string;
}
```

This unifies Documents + WorkspaceFiles under one API. Each method resolves the path to the appropriate backend. But this is over-engineering until usage patterns emerge — Option A is sufficient.

### How Dream Uses VFS

The consolidation job can use VFS tools internally to browse and analyze memory:

```php
// Instead of loading all logs into LLM context at once:
$oldLogs = $this->glob("memory/logs/2026-03-*.md");  // find March logs
foreach ($oldLogs as $log) {
    $content = $this->cat($log->path);
    // Process in chunks if large
}

// Find contradictions:
$duplicates = $this->grep("Project X deadline", "memory/");
```

The Dream job doesn't call agent tools directly — it uses the underlying services (`AgentDocumentService`, `DocumentIndexingService`, `FileSystemService`). But the VFS abstraction makes the consolidation logic cleaner.

---

## Part 3: Integration with Memory Refactor

### Dependency Order

```
1. Memory Refactor (implemented baseline) — created the folder structure and memory tools
2. Temporal decay (Tier 1a) — drop-in to existing search, works immediately
3. VFS tools (Option A) — builds on existing FileSystemService, independent of refactor
4. Consolidation job (Tier 2) — depends on refactor's new folder structure
5. Importance scoring (Tier 3) — additive, can layer on anytime
6. Auto-extract (Tier 3d) — works with both old and new structure
```

Steps 2 and 3 can be done independently now that the refactor baseline exists. Step 4 should build on the current `memory/logs`, `topics`, `peers`, and `MEMORY.md` structure.

### Config Additions

All new configuration in `config/memory.php`:

```php
// Temporal decay
'search' => [
    'temporal_decay_rate' => (float) env('MEMORY_TEMPORAL_DECAY', 0.995), // per hour
],

// Embedding cache
'embedding_cache' => [
    'max_entries' => (int) env('MEMORY_EMBEDDING_CACHE_MAX', 50_000),
],

// Consolidation (Dream)
'consolidation' => [
    'enabled' => env('MEMORY_CONSOLIDATION_ENABLED', true),
    'log_threshold_days' => (int) env('MEMORY_CONSOLIDATION_LOG_DAYS', 7),
    'topic_staleness_days' => (int) env('MEMORY_CONSOLIDATION_TOPIC_DAYS', 30),
    'schedule' => env('MEMORY_CONSOLIDATION_SCHEDULE', 'weekly'),
    'max_consolidation_tokens' => 20_000,
],
```

### New Files Summary

| File | Purpose |
|------|---------|
| `app/Services/Memory/MemoryConsolidationService.php` | Core consolidation logic (gather, consolidate, restructure) |
| `app/Jobs/ConsolidateMemoryJob.php` | Queued consolidation job with locking |
| `app/Console/Commands/MemoryConsolidate.php` | `memory:consolidate` artisan command |
| `app/Agents/Tools/Files/GrepFiles.php` | Content search across files and documents |
| `app/Agents/Tools/Files/GlobFiles.php` | Pattern-based file discovery |
| `app/Agents/Tools/Files/HeadFile.php` | Line-level file preview |

### Modified Files Summary

| File | Change |
|------|--------|
| `app/Services/Memory/DocumentIndexingService.php` | Add temporal decay to search scoring, add cache pruning |
| `app/Services/Memory/EmbeddingService.php` | Add `pruneEmbeddingCache()` method |
| `app/Agents/Tools/Providers/FilesToolProvider.php` | Register grep/glob/head tools |
| `config/memory.php` | Add consolidation, cache, and decay config |
| `routes/console.php` | Schedule consolidation command |

---

## Sources

**Dreaming / Consolidation:**
- Claude Code `dream` task type — background auto-dream
- `docs/ecosystem/kosmokrator/proposals/context-management-redesign.md:495-509` — Background Consolidation
- `docs/planning/memory-systems.md` — Memory & Learning Systems Research
- Mem0 — ADD/UPDATE/DELETE/NOOP auto-extraction
- Park et al. — `score = recency_decay + importance_rating + semantic_relevance`
- EvolveR — scored principles with `(successes + 1) / (uses + 2)`

**VFS / Filesystem Retrieval:**
- `inspiration/opencode/packages/opencode/src/tool/grep.ts` — ripgrep wrapper
- `inspiration/opencode/packages/opencode/src/tool/glob.ts` — file pattern matching
- `inspiration/openclaw/src/agents/sandbox/` — Docker sandbox with tool policies
- "Agentic RAG is overkill for many tasks" — agents with grep/find often outperform embedding-based retrieval for structured knowledge (SWE-Bench findings)

**OpenClaw patterns:**
- `inspiration/openclaw/src/memory/manager.ts` — embedding cache pruning, delta tracking
- `inspiration/openclaw/src/memory/qmd-manager.ts` — session retention, QMD subprocess
- `inspiration/openclaw/src/memory/sync-memory-files.ts` — stale file cleanup

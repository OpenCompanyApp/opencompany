# Memory, Compaction & Embeddings — Architecture Reference

> Architecture overview for agent memory, document embeddings, conversation compaction, and hybrid search.

**Status**: Complete (all 6 phases implemented). Current runtime note: the permanent `MEMORY.md` prompt section and memory tools are injected only in private agent/user contexts (`dm`, `agent`, or `external` channels), while public channels intentionally omit private memory context.
**Config**: `config/memory.php` (comprehensive: embedding, chunking, search, reranking, compaction, memory_flush, context_windows, scope)

### Implementation Summary

All phases are built and operational. Key files:

| Phase | What | Key Files |
|-------|------|-----------|
| 1 | pgvector, chunking, embeddings | `app/Services/Memory/ChunkingService.php`, `EmbeddingService.php`, `app/Models/DocumentChunk.php`, `EmbeddingCache.php` |
| 2 | Document indexing + observer | `app/Services/Memory/DocumentIndexingService.php`, `app/Observers/DocumentObserver.php`, `app/Jobs/IndexDocumentJob.php` |
| 3 | SaveMemory + RecallMemory tools | `app/Agents/Tools/Memory/SaveMemory.php`, `RecallMemory.php` |
| 4 | Conversation compaction | `app/Services/Memory/ConversationCompactionService.php`, `app/Jobs/CompactConversationJob.php`, `app/Models/ConversationSummary.php` |
| 5 | Pre-compaction memory flush | `app/Services/Memory/MemoryFlushService.php` (hooked into `AgentRespondJob`) |
| 6 | Hybrid search (full-text + vector) | `app/Services/Memory/DocumentIndexingService.php`, tsvector column on `document_chunks` |

Bonus services (not in original plan): `TokenEstimator`, `ModelContextRegistry`, `MemoryScopeGuard`, `RerankingService` (native Cohere/Jina reranking, Ollama Qwen3-style reranking, or LLM-based pointwise reranking through the configured AI provider).

Migrations: `create_document_chunks_table`, `create_embedding_cache_table`, `create_conversation_summaries_table`, `add_flush_count_to_conversation_summaries_table`, `add_search_vector_to_document_chunks`.

Artisan commands: `memory:status`, `memory:index-documents [--fresh]`.

---

## Memory Model: Short-Term vs Long-Term

Agents have two distinct memory systems, mirroring how human memory works:

### Short-Term Memory (STM) — The Conversation Context

- **What**: The current conversation messages loaded into the context window
- **Scope**: Single channel, single session
- **Lifetime**: Ephemeral — exists only while the context window holds it
- **Managed by**: `ChannelConversationLoader` (compaction keeps it within budget)
- **Storage**: `messages` table → loaded into context window at prompt time
- **Capacity**: Limited by model context window (e.g. 128K tokens)
- **When full**: Older messages are summarized into a `ConversationSummary` and replaced

### Long-Term Memory (LTM) — Durable Memories

- **What**: Explicitly saved facts, preferences, decisions, learnings
- **Scope**: Per-agent, accessible across all conversations
- **Lifetime**: Permanent — persists until explicitly deleted
- **Managed by**: `SaveMemory` / `RecallMemory` tools
- **Storage**: `agents/*/memory/logs/YYYY-MM-DD.md` documents, `agents/*/memory/topics/*.md`, peer files, and `MEMORY.md` → chunked & embedded in `document_chunks`
- **Capacity**: Unlimited (PostgreSQL + pgvector)
- **Retrieval**: Hybrid search (pgvector cosine + PostgreSQL full-text search merged with weighted RRF), not loaded by default except for private-channel `MEMORY.md` / peer-card prompt context — agents must actively recall topic/log content

### The Bridge: STM → LTM Promotion

Before conversation compaction discards older messages, the **Memory Flush** gives the agent a silent turn to review what's about to be lost and `save_memory` anything important. This is the automatic promotion path from short-term to long-term memory.

```
┌─────────────────────────────────────────────────────────┐
│                    Agent Execution                       │
│                                                         │
│  ┌──────────────────┐    flush     ┌──────────────────┐ │
│  │  Short-Term (STM) │ ─────────► │  Long-Term (LTM)  │ │
│  │                    │            │                    │ │
│  │  Conversation      │            │  Saved memories    │ │
│  │  messages in       │            │  in document_chunks│ │
│  │  context window    │            │  (pgvector)        │ │
│  │                    │            │                    │ │
│  │  Compacted when    │  recall    │  Recalled via      │ │
│  │  approaching limit │ ◄───────── │  semantic search   │ │
│  │                    │            │  on demand         │ │
│  └──────────────────┘            └──────────────────┘ │
│                                                         │
│  ┌──────────────────────────────────────────────────┐   │
│  │              Document Knowledge Base              │   │
│  │  Shared workspace docs, indexed with embeddings   │   │
│  │  Searchable via SearchDocuments (semantic mode)    │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

### Phase Dependencies

```
Phase 1 (Foundation)
  ├── Phase 2 (Document Embeddings)          — Document Knowledge Base
  │     └── Phase 6 (Hybrid Search)          — upgrades all search
  ├── Phase 3 (Agent LTM Tools)              — SaveMemory + RecallMemory
  └── Phase 4 (Conversation Compaction)      — STM management
        └── Phase 5 (STM → LTM Flush)       — requires Phase 3 + 4
```

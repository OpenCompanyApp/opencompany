# Memory Systems Research

## Current System

OpenCompany already has a solid two-tier memory architecture:

| Layer | What | How |
|-------|------|-----|
| **STM** | Conversation history | Auto-compacted when context fills (~75% threshold), summary stored in `conversation_summaries` |
| **LTM Core** | `MEMORY.md` | Always loaded in system prompt, agent-editable via `save_memory(target: "core")` |
| **LTM Logs** | Daily logs (`YYYY-MM-DD.md`) | On-demand via `recall_memory`, timestamped entries with category tags |
| **Retrieval** | Hybrid search | pgvector cosine similarity (70%) + PostgreSQL tsvector FTS (30%), merged via RRF |
| **Flush** | Pre-compaction promotion | Agent gets a silent turn to `save_memory` before STM is compressed |
| **Reranking** | Post-retrieval scoring | Ollama Qwen3-Reranker or LLM-based pointwise relevance |

Key services: `EmbeddingService`, `DocumentIndexingService`, `ChunkingService`, `ConversationCompactionService`, `MemoryFlushService`, `RerankingService`, `MemoryScopeGuard`, `ModelContextRegistry`.

### What's missing

1. No entity/relationship tracking (can't answer "what does user think about project X?")
2. No temporal decay on retrieval (old and new memories weighted equally)
3. No memory consolidation (daily logs grow unbounded, never summarized)
4. No contradiction resolution (conflicting facts coexist silently)
5. No cross-agent shared memory (each agent's memory is isolated)
6. No importance scoring (all memories treated equally)
7. No structured types (everything is free-text)

---

## Researched Systems

### 1. Graph-Based Memory

Stores knowledge as entities (nodes) and relationships (edges). Enables multi-hop reasoning across connected facts.

**Key implementations:**
- **Zep/Graphiti** — Temporal knowledge graph on Neo4j. Bi-temporal model tracks when facts were learned vs. when they're valid. Hybrid retrieval: embeddings + BM25 + graph traversal. 94.8% on Deep Memory Retrieval benchmark.
- **Microsoft GraphRAG** — 5x query speed over RAG, 40% infra cost reduction.
- **MAGMA** — Four orthogonal graphs (semantic, temporal, causal, entity). 45.5% higher reasoning accuracy, 95% fewer tokens.

**Strengths:** Multi-hop reasoning, relationship queries, contradiction detection, transparent reasoning paths.
**Weaknesses:** Requires graph DB infrastructure (Neo4j/FalkorDB), entity resolution is hard, higher cost for graph construction (LLM extraction calls).

### 2. Hierarchical Memory

Inspired by cognitive science: working memory (context window), episodic (experiences), semantic (facts), procedural (skills).

**Key implementations:**
- **MIRIX** — Six memory types including resource memory and knowledge vault. 85.4% on LOCOMO benchmark. Multimodal support.
- **CoALA Framework** — Theoretical reference architecture (working + long-term: procedural, semantic, episodic).
- **AWS AgentCore** — Managed service with strategy-based extraction and consolidation.

**Strengths:** Mirrors human cognition, natural separation of concerns, consolidation prevents unbounded growth.
**Weaknesses:** Complex to manage multiple stores, classification decisions require LLM calls.

### 3. Memory Agents (Self-Managing)

A dedicated agent actively manages what to store, retrieve, and organize.

**Key implementations:**
- **Letta/MemGPT** (29k stars) — Treats context window like OS RAM. Core memory (mutable system prompt section), archival memory (disk-like external store), recall memory (conversation history). Agent pages info in/out via tool calls.
- **A-MEM** (NeurIPS 2025) — Zettelkasten-inspired. New memories trigger structured notes with keywords/tags, link to existing memories, and can update old memories.
- **Mastra Observational Memory** — Two background agents: Observer (compresses history into dated logs) and Reflector (synthesizes into insights). No vector DB needed. 94.87% LongMemEval, 10x cost reduction.

**Strengths:** Maximum flexibility, self-organizing, handles novel situations.
**Weaknesses:** Requires capable models, more LLM calls, harder to debug.

### 4. Temporal Memory

Weights memories by recency, importance, and relevance.

**Key implementations:**
- **Park et al. Generative Agents** — `score = recency * decay^hours + importance * llm_rating + relevance * cosine_sim`
- **Graphiti bi-temporal** — Four timestamps per edge (created, expired, valid, invalid). Old facts invalidated but preserved.
- **Memoria Framework** — Exponential decay + normalized weights for knowledge triplets.

**Strengths:** Prevents information overload, natural prioritization of recent context.
**Weaknesses:** Important old memories can decay away, importance scoring costs LLM calls.

### 5. Structured Memory

Typed entities, schemas, and ontologies instead of free-text.

**Key implementations:**
- **Zep Entity Types** — Custom schemas (e.g., "Customer" with fields: name, company, plan_tier).
- **Hindsight** (91.4% LongMemEval) — Four structurally distinct networks: World (facts), Bank (experiences), Opinion (beliefs with confidence), Observation (entity summaries). Separates evidence from inference.
- **Tapestry** — Shared ontologies for multi-agent systems.

**Strengths:** Consistent organization, conflict detection, explainability, multi-agent coordination.
**Weaknesses:** Requires upfront schema design, less flexible for unexpected info.

### 6. Hybrid Approaches

Combine multiple backends: KV (state) + vector (recall) + graph (reasoning).

**Key implementations:**
- **Mem0** (41k stars, $24M raised) — Two-phase: extract candidates from conversation, then compare against existing memories and decide ADD/UPDATE/DELETE/NOOP. Graph variant adds entity extraction and conflict detection. 26% improvement over OpenAI memory, 90%+ token cost savings.
- **MemOS** — Memory operating system with composable "memory cubes" per user/project/agent.

**Strengths:** Best accuracy through complementary strategies, production-proven.
**Weaknesses:** Increased complexity, more infrastructure, more failure modes.

### 7. Memory Consolidation/Reflection

Periodic summarization, contradiction resolution, importance filtering.

**Key implementations:**
- **Mastra Observer/Reflector** — Background agents compress and synthesize. 10x cost reduction.
- **Mem0 consolidation** — Every new memory compared against existing; LLM decides ADD/UPDATE/DELETE/NOOP.
- **Hindsight CARA** — Reasoning dispositions (skepticism, literalism, empathy) during reflection. Beliefs updated with confidence scores.

**Strengths:** Bounds memory growth, improves retrieval quality over time, enables higher-order insights.
**Weaknesses:** Risk of losing details, additional LLM cost, timing matters.

---

## Comparison

| System | Complexity | Infra Cost | LLM Cost | Retrieval Quality | Multi-hop | Temporal | Self-healing |
|--------|-----------|-----------|----------|------------------|-----------|----------|-------------|
| Vector (current) | Low | Low (pgvector) | Low | Good | Poor | None | None |
| + Temporal decay | Low | Same | None | Better | Poor | Good | None |
| + Consolidation | Medium | Same | Medium | Much better | Poor | Good | Good |
| + Graph layer | High | High (Neo4j) | High | Excellent | Excellent | Good | Good |
| + Memory agent | Medium | Same | High | Very good | Medium | Good | Excellent |
| Full hybrid (Mem0-style) | High | High | High | Excellent | Excellent | Excellent | Excellent |

---

## Recommendations for OpenCompany

Ranked by impact-to-effort ratio, considering that OpenCompany is a multi-workspace SaaS with multiple agents per workspace and long-running user relationships.

### 1. Memory Consolidation (HIGH priority, MEDIUM effort)

**What:** A scheduled job that periodically consolidates old daily logs into distilled semantic facts. Similar to Mastra's Observer/Reflector pattern but simpler.

**Why:** Daily logs grow unbounded. After a few weeks, an agent has dozens of log files. `recall_memory` search degrades as noise increases. Consolidation compresses old logs into a curated set of facts, improving retrieval quality and bounding storage.

**How it fits:** Add a `ConsolidateMemoryJob` that runs weekly per agent. Reads logs older than 7 days, extracts key facts/preferences/decisions via LLM, appends distilled facts to a `CONSOLIDATED.md` or new log entries tagged as consolidated, then archives raw logs. Uses existing `AgentDocumentService` and `DocumentIndexingService`.

### 2. Temporal Decay on Retrieval (HIGH priority, LOW effort)

**What:** Weight `recall_memory` results by recency using an exponential decay function.

**Why:** Currently a memory from 6 months ago and one from yesterday have equal retrieval weight (only cosine similarity matters). Adding `score = semantic_score * recency_weight` would naturally prioritize recent context without losing old memories entirely.

**How it fits:** Modify `DocumentIndexingService::search()` to apply a decay multiplier based on document `created_at`. Formula: `recency = decay_rate ^ hours_since_creation` (e.g., 0.995^hours). Merge into the existing RRF scoring. Near-zero infrastructure change.

### 3. Mem0-Style Extract/Consolidate Pipeline (HIGH priority, MEDIUM effort)

**What:** Instead of relying on the agent to explicitly call `save_memory`, automatically extract memory candidates from every conversation and compare against existing memories (ADD/UPDATE/DELETE/NOOP).

**Why:** The current system depends on the agent choosing to save memories. Agents often forget, especially in fast-paced conversations. Automatic extraction catches what the agent misses. The UPDATE/DELETE decisions handle contradiction resolution for free.

**How it fits:** Post-conversation hook in `AgentRespondJob`. After the agent responds, a lightweight LLM call extracts candidate facts from the exchange. Each candidate is compared against existing memories via `recall_memory` search. An LLM decides the action. New memories are saved via `AgentDocumentService::createMemoryLog()`. This replaces or complements the current `MemoryFlushService`.

### 4. Entity/Relationship Tracking (MEDIUM priority, HIGH effort)

**What:** Extract entities (people, projects, preferences, decisions) and relationships from conversations and store them in a lightweight graph structure.

**Why:** Enables queries like "what does Rutger think about the new design?" or "what decisions were made about the API?" Currently these require the agent to have manually saved the right free-text memory with the right keywords.

**How it fits:** Two options:
- **Lightweight (recommended):** Store entities as structured JSON in the existing PostgreSQL (JSONB column on a new `memory_entities` table). No Neo4j needed. Entity extraction via LLM during the extract/consolidate pipeline from recommendation #3.
- **Full graph:** Add Neo4j/FalkorDB for proper graph traversal. Higher accuracy for multi-hop queries but significant infrastructure overhead.

The lightweight approach gives 80% of the value at 20% of the cost. Full graph can be added later if needed.

### 5. Cross-Agent Shared Memory (MEDIUM priority, LOW effort)

**What:** A workspace-level memory store that any agent in the workspace can read (and optionally write to).

**Why:** Currently each agent's memory is siloed. If Agent A learns that "the client prefers weekly reports on Monday," Agent B doesn't know this. Shared memory enables organizational knowledge.

**How it fits:** Add a `workspace` collection to `DocumentChunk` alongside the existing per-agent collections. New `save_memory(target: "shared")` option. `recall_memory` searches both agent-specific and workspace collections, with agent-specific results weighted higher.

### 6. Importance Scoring (LOW priority, LOW effort)

**What:** Rate each memory's importance (1-10) at save time and use it as a retrieval signal.

**Why:** Not all memories are equal. "User's name is Rutger" is more important than "user asked about the weather." Importance scoring prevents high-value facts from being drowned out by trivial ones.

**How it fits:** Add an `importance` field to memory log entries. Score at save time via a simple LLM call or heuristic (memories from flush get higher scores, explicit `save_memory` calls get medium, auto-extracted get variable). Factor into retrieval scoring alongside semantic similarity and recency.

---

## Implementation Roadmap

### Phase 1: Quick Wins (1-2 days each)
- Temporal decay on retrieval (#2)
- Importance scoring (#6)

### Phase 2: Core Upgrades (3-5 days each)
- Memory consolidation job (#1)
- Cross-agent shared memory (#5)

### Phase 3: Major Features (1-2 weeks)
- Mem0-style extract/consolidate pipeline (#3)
- Lightweight entity tracking (#4)

### Phase 4: Future (if needed)
- Full graph database (Neo4j) for multi-hop reasoning
- Memory agent (dedicated agent managing memory for other agents)
- Structured entity schemas (typed facts with validation)

---

## Key Files

| File | Role |
|------|------|
| `app/Services/Memory/DocumentIndexingService.php` | Hybrid search — add temporal decay here |
| `app/Services/Memory/MemoryFlushService.php` | Pre-compaction flush — extend or replace with extract/consolidate |
| `app/Services/Memory/ConversationCompactionService.php` | STM management |
| `app/Services/AgentDocumentService.php` | Memory log creation — add shared memory support |
| `app/Agents/Tools/Memory/SaveMemory.php` | Save tool — add importance scoring, shared target |
| `app/Agents/Tools/Memory/RecallMemory.php` | Recall tool — surface temporal/importance signals |
| `app/Jobs/AgentRespondJob.php` | Post-response hook point for auto-extraction |
| `config/memory.php` | Configuration for all memory subsystems |

## Sources

- [Mem0](https://mem0.ai/) — 41k stars, hybrid vector+graph, $24M raised
- [Letta/MemGPT](https://github.com/letta-ai/letta) — 29k stars, self-editing context window
- [Zep/Graphiti](https://github.com/getzep/graphiti) — Temporal knowledge graph on Neo4j
- [Hindsight](https://github.com/vectorize-io/hindsight) — 91.4% LongMemEval, four structured networks
- [A-MEM](https://github.com/agiresearch/A-mem) — NeurIPS 2025, Zettelkasten-inspired
- [MAGMA](https://arxiv.org/abs/2601.03236) — Multi-graph architecture, 95% token reduction
- [MIRIX](https://github.com/Mirix-AI/MIRIX) — Six memory types, multimodal
- [Mastra Observational Memory](https://venturebeat.com/data/observational-memory-cuts-ai-agent-costs-10x-and-outscores-rag-on-long) — 10x cost reduction
- [CoALA](https://arxiv.org/abs/2309.02427) — Cognitive agent architecture framework
- [Park et al. Generative Agents](https://dl.acm.org/doi/fullHtml/10.1145/3586183.3606763) — Recency/importance/relevance scoring
- [Memory in the Age of AI Agents (survey)](https://arxiv.org/abs/2512.13564)

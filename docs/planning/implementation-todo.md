# OpenCompany Agent System - Complete Implementation Todo

> **Comprehensive hierarchical task list for implementing OpenClaw-style agent system**
>
> Legend: `[x]` = Complete, `[ ]` = Todo, `[~]` = In Progress
> Dependencies shown as `← depends on: [task-id]`

---

## Technology Stack

> See [Technology Decisions](../architecture/technology-decisions.md) for detailed comparison and rationale.

| Component | Choice | Reason |
|-----------|--------|--------|
| **AI Framework** | **Laravel AI SDK (`laravel/ai`)** | Official first-party, full multimodal, comprehensive testing |

**Core Packages:**
- `laravel/ai` - Official Laravel AI SDK (agents, tools, embeddings, multimodal)

**Optional Packages:**
- `laravel/mcp` - Expose OpenCompany as MCP server for external AI clients

---

## Phase 0: Package Installation & Setup

> **Why:** Before building the agent system, we need the core AI package installed. Laravel AI SDK provides official first-party LLM integration. Laravel queues handle async task processing.

### 0.1 Install Core Packages
- [x] **0.1.1** Install Laravel AI SDK — ✅ `laravel/ai` v0.1.2 in composer.json (also `prism-php/prism` installed)
  - **What:** Official first-party Laravel package for AI/LLM integration with multiple providers
  - **Why:** Laravel AI SDK is the official package from the Laravel team. It supports agents, tools, streaming, embeddings, image generation, audio, and comprehensive testing utilities.
  - **Context:** We chose Laravel AI SDK over Prism (community package) for its first-party support, multimodal capabilities, and built-in testing.
  ```bash
  composer require laravel/ai
  ```

- [x] **0.1.2** Publish AI SDK config — ✅ config/ai.php exists
  - **What:** Creates `config/ai.php` with provider settings
  - **Why:** Need to configure API keys and provider-specific settings. Also enables adding custom providers like GLM via OpenAI-compatible endpoint.
  ```bash
  php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
  ```

- [x] **0.1.3** Configure providers in `config/ai.php` — ✅ DynamicProviderResolver + IntegrationSettings handle provider config
  - **What:** Set up API credentials for all LLM providers
  - **Why:** Anthropic/Claude is our primary LLM for agent tasks. OpenAI, Gemini, Groq, xAI are available as alternatives/fallbacks.
  - **Context:** GLM/Zhipu AI uses OpenAI-compatible endpoint with custom base URL. Provider failover is built-in.
  - Set `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, etc. in `.env`

### 0.2 Verify Setup
- [x] **0.2.1** Test Laravel AI SDK agent ← depends on: [0.1.3] — ✅ agents operational with OpenCompanyAgent + AgentRespondJob
  - **What:** Simple test to verify provider APIs are working
  - **Why:** Catch configuration errors early before building dependent features.
  - **Context:** Should return a response and log token usage.
  ```php
  use function Laravel\Ai\agent;

  $response = agent(
      instructions: 'You are a helpful assistant.',
  )->prompt('Hello, world!');
  ```

### 0.3 Optional: Install Extensions
- [ ] **0.3.1** Install Laravel MCP — NOT built (MCP Client integration exists for connecting TO external servers, but not exposing OpenCompany AS a server)
  - **What:** Expose OpenCompany workspace as MCP server for external AI clients
  - **Why:** Allows Claude Desktop, VS Code Copilot, and other MCP-compatible tools to interact with OpenCompany data.
  - **Context:** Provides tools (search_documents, create_task, send_message) and resources (documents, agent configs) via MCP protocol.
  ```bash
  composer require laravel/mcp
  ```

---

## Phase 1: Database Foundation

> **Why:** The database schema is the foundation of the agent system. Each table maps to a core concept from OpenClaw's architecture, translated to business-friendly naming.

### 1.1 Core Agent Tables
- [x] **1.1.1** Create `agent_configurations` migration — ✅ superseded: agent identity stored in Document-based files per agent; agent fields on `users` table
  - **What:** Stores the core identity and personality of each AI agent
  - **Why:** Agents need persistent personality (SOUL.md), instructions (AGENTS.md), and identity metadata. This is what makes each agent unique and consistent across sessions.
  - **Context:** In OpenClaw, these are markdown files in the workspace. We store them in DB for easier management via UI.
  - Fields: `id`, `user_id` (FK), `personality`, `instructions`, `identity`, `tool_notes`, `created_at`, `updated_at`
  - `personality` = TEXT (markdown, SOUL.md equivalent) - Agent's tone, boundaries, operating principles
  - `instructions` = TEXT (markdown, AGENTS.md equivalent) - Operating instructions, memory guidelines, skills
  - `identity` = JSON (`{name, emoji, type, avatar, description}`) - Visual identity for UI
  - `tool_notes` = TEXT (TOOLS.md equivalent) - Environment-specific tool notes (SSH hosts, device nicknames)

- [x] **1.1.2** Create `agent_capabilities` migration ← depends on: [1.1.1] — ✅ superseded by `agent_permissions` table + AgentPermission model
  - **What:** Junction table linking agents to their enabled capabilities/tools
  - **Why:** Different agents need different tools. A code assistant needs git/file access, while a research agent needs web search. Per-agent capability control enables safe, scoped tool access.
  - **Context:** This enables the "capabilities" tab in the agent settings UI where users can toggle tools on/off.
  - Fields: `id`, `agent_config_id` (FK), `capability_id` (FK), `enabled`, `requires_approval`, `notes`, `created_at`

- [x] **1.1.3** Create `capabilities` migration (master list) — ✅ superseded by ToolRegistry + AgentPermissionService (no separate capabilities table needed)
  - **What:** Master list of all available tools/capabilities in the system
  - **Why:** Centralizes tool definitions so new tools can be added system-wide and assigned to agents. Defines default approval requirements per tool type.
  - **Context:** Seeded with common tools. Each has an icon for UI display and category for grouping.
  - Fields: `id`, `name`, `description`, `icon`, `category`, `default_enabled`, `default_requires_approval`, `created_at`
  - Seed with: code_execution, file_operations, git_operations, api_requests, database_access, production_deployment

- [x] **1.1.4** Create `agent_settings` migration ← depends on: [1.1.1] — ✅ superseded by fields on `users` table (behavior_mode, brain, sleeping_until, etc.)
  - **What:** Runtime behavior settings for each agent (how autonomous, cost limits, when to reset)
  - **Why:** Different use cases need different autonomy levels. A production deployment agent should be strict (require approval for everything), while a dev assistant can be more autonomous.
  - **Context:** The OpenClaw fields enable sophisticated execution control - allowlisting commands, reserving context space, auto-pruning old data.
  - Fields: `id`, `agent_config_id` (FK), `behavior_mode` (enum: autonomous/supervised/strict), `cost_limit`, `reset_policy` (JSON), `created_at`, `updated_at`
  - `reset_policy` = `{mode: 'daily'|'idle'|'manual', dailyHour?: number, idleMinutes?: number}`
  - **OpenClaw fields (execution control):**
  - `security_mode` enum: deny/allowlist/full (default: allowlist) - Controls which commands can execute
  - `ask_mode` enum: off/on-miss/always (default: on-miss) - When to prompt user for approval
  - `reserve_tokens` INTEGER (default: 16384) - Tokens reserved for compaction operations
  - `reserve_tokens_floor` INTEGER (default: 20000) - Minimum safety floor for reserves
  - `keep_recent_tokens` INTEGER (default: 20000) - Tokens to keep after compaction
  - `pruning_ttl_minutes` INTEGER (default: 5) - How long before old tool results are pruned
  - `auto_allow_skills` BOOLEAN (default: true) - Auto-allow trusted tool binaries (jq, grep, etc.)
  - `soft_threshold_tokens` INTEGER (default: 4000) - Buffer before triggering memory flush

- [x] **1.1.5** Update `capabilities` migration with tool kind — ✅ superseded: tool classification handled by ToolRegistry APP_GROUPS
  - **What:** Classifies each tool by its operation type (read/edit/delete/execute/etc.)
  - **Why:** Enables intelligent approval rules - auto-approve reads but require approval for deletes. Different risk levels for different operation types.
  - **Context:** OpenClaw uses `inferToolKind()` to classify tools. We store it in DB for faster lookup.
  - `kind` enum: read/edit/delete/move/search/execute/fetch/other (default: other)

### 1.2 Memory & Session Tables

> **Why:** Agents need persistent memory across conversations. Sessions track the current conversation, while memories persist facts and learnings long-term.

- [ ] **1.2.1** Create `agent_sessions` migration ← depends on: [1.1.1] — NOT built (superseded by channel-based conversation; `agent_conversations` table exists but no formal sessions)
  - **What:** Tracks individual conversation sessions with an agent
  - **Why:** Sessions isolate conversations, track token usage for billing, and enable session reset policies. Token tracking is critical for knowing when to compact/prune.
  - **Context:** Each session has a lifecycle: active → archived → deleted. Compaction count tracks how many times context was compressed.
  - Fields: `id`, `agent_config_id` (FK), `session_key`, `started_at`, `last_activity_at`, `message_count`, `token_count`, `max_tokens`, `compaction_count`, `status` (active/archived/deleted), `created_at`
  - **OpenClaw fields (memory management):**
  - `memory_flush_at` TIMESTAMP NULL - When the last pre-compaction memory flush ran
  - `memory_flush_compaction_count` INTEGER (default: 0) - Prevents duplicate flushes per compaction cycle
  - `last_api_call_at` TIMESTAMP NULL - Used for TTL-based session pruning

- [ ] **1.2.2** Create `agent_session_messages` migration ← depends on: [1.2.1]
  - **What:** Individual messages within a session (user, assistant, tool results, system)
  - **Why:** Full conversation history enables context for the agent. Token counts per message enable precise context window management.
  - **Context:** The `compaction` type stores summaries when context is compressed. Metadata stores tool call IDs, timestamps, etc.
  - Fields: `id`, `session_id` (FK), `type` (enum: user/assistant/tool/system/compaction), `content`, `token_count`, `metadata` (JSON), `created_at`
  - **OpenClaw fields:**
  - `is_silent` BOOLEAN (default: false) - Messages starting with NO_REPLY token are silent (memory flushes, housekeeping)
  - `parent_message_id` UUID NULL (self-referencing FK) - Enables conversation branching for future features

- [ ] **1.2.3** Create `agent_memories` migration ← depends on: [1.1.1]
  - **What:** Long-term persistent memories that survive session resets (MEMORY.md equivalent)
  - **Why:** Agents need to remember facts, user preferences, and important context across sessions. This is the agent's "long-term memory."
  - **Context:** Categories help organize memories: facts (user's timezone), preferences (communication style), context (project details), notes (learnings).
  - Fields: `id`, `agent_config_id` (FK), `content`, `category` (enum: fact/preference/context/note), `source`, `created_at`
  - Index on `agent_config_id` + `created_at`

- [ ] **1.2.4** Create `agent_memory_daily_logs` migration ← depends on: [1.1.1]
  - **What:** Append-only daily logs of agent activity (memory/YYYY-MM-DD.md equivalent)
  - **Why:** Daily logs provide temporal context - agents can reference what happened yesterday. Useful for continuity without loading all history.
  - **Context:** OpenClaw loads today + yesterday's logs at session start. Append-only design makes concurrent writes safe.
  - Fields: `id`, `agent_config_id` (FK), `date`, `content` (TEXT, append-only daily log), `created_at`, `updated_at`
  - Unique constraint on `agent_config_id` + `date`

- [ ] **1.2.5** Create `agent_tool_allowlist` migration ← depends on: [1.1.1]
  - **What:** Stores command patterns that are pre-approved for execution without prompting
  - **Why:** Reduces friction for common operations. If an agent frequently runs `npm test`, users can allowlist it to skip approval prompts.
  - **Context:** Patterns can be exact matches or globs. Tracks last usage for auditing and auto-cleanup of stale patterns.
  - Fields: `id`, `agent_config_id` (FK), `pattern`, `last_used_at`, `last_used_command`, `created_at`
  - Index on `agent_config_id` + `pattern`

### 1.3 Subagent Tables

> **Why:** Agents need to spawn other agents for complex tasks. A code review agent might spawn a testing agent. These tables control who can spawn whom and track the parent-child relationships.

- [ ] **1.3.1** Create `subagent_spawn_permissions` migration ← depends on: [1.1.1]
  - **What:** Controls which agents can spawn which other agents and how many
  - **Why:** Security boundary - not all agents should spawn all agents. A junior agent shouldn't spawn production deployment agents. Also prevents runaway spawning (max_concurrent limit).
  - **Context:** OpenClaw has similar spawn control. `allowed_agents` can be ["*"] for all, or specific agent IDs.
  - Fields: `id`, `parent_agent_id` (FK to users), `allowed_agents` (JSON array or "*"), `max_concurrent`, `auto_archive_minutes`, `created_at`

- [ ] **1.3.2** Create `subagent_runs` migration ← depends on: [1.3.1]
  - **What:** Tracks each subagent execution - who spawned whom, why, and the result
  - **Why:** Audit trail for agent spawning. Enables monitoring, debugging, and cost tracking per spawn. The `label` helps identify purpose in UI.
  - **Context:** Status lifecycle: pending → running → success/error/timeout/cancelled. Runtime config stores parameters passed to child.
  - Fields: `id`, `parent_agent_id` (FK), `child_agent_id` (FK), `session_id` (FK), `task_description`, `label`, `status` (enum: pending/running/success/error/timeout/cancelled), `runtime_config` (JSON), `result` (JSON), `created_at`, `completed_at`

### 1.4 Run All Migrations

> **Why:** Execute all database changes to create the foundation. Must run before creating models or any dependent code.

- [x] **1.4.1** Run `php artisan migrate` ← depends on: [1.1.1-1.3.2] — ✅ 60+ migrations exist and run successfully
  - **What:** Execute all migration files to create tables
  - **Why:** Database must exist before models can query it.

- [x] **1.4.2** Verify all tables created correctly — ✅ all tables operational
  - **What:** Check that all tables, indexes, and constraints exist
  - **Why:** Catch any migration errors early. Use `php artisan migrate:status` and check foreign keys.

- [x] **1.4.3** Seed capabilities table with default capabilities — ✅ superseded: ToolRegistry provides capability list dynamically
  - **What:** Populate the `capabilities` table with our 6 default tools
  - **Why:** Agents need capabilities to choose from. These are system-wide definitions used by all agents.
  - **Context:** Default capabilities: code_execution, file_operations, git_operations, api_requests, database_access, production_deployment

### 1.5 Memory Search Infrastructure (OpenClaw)

> **Why:** Agents need to search their memories efficiently. OpenClaw uses hybrid search (vector embeddings + full-text) for best results. Vector search finds semantically similar content, FTS finds exact matches. Combined scoring gives the best of both.

#### 1.5.1 Vector Search Setup

- [ ] **1.5.1.1** Install pgvector extension
  - **What:** PostgreSQL extension for vector similarity search
  - **Why:** Enables storing embeddings as native VECTOR type and searching with operators like `<=>` (cosine distance). Much faster than application-level vector math.
  - **Context:** pgvector supports IVFFlat and HNSW indexes for scaling to millions of vectors.
  ```sql
  CREATE EXTENSION IF NOT EXISTS vector;
  ```

- [ ] **1.5.1.2** Create `memory_chunks` migration
  - **What:** Stores text chunks with their embeddings for semantic search
  - **Why:** Long memories are chunked (~400 tokens) for better retrieval. Each chunk has its own embedding. Source tracking (memory/session) enables filtering by origin.
  - **Context:** OpenClaw uses 400-token chunks with 80-token overlap. Content hash enables deduplication and change detection.
  - Fields: `id`, `agent_config_id` (FK), `source_type` (memory/session), `source_id`, `start_line`, `end_line`, `content_hash`, `text`, `embedding` VECTOR(1536), `created_at`
  - Index on embedding for vector similarity search

- [ ] **1.5.1.3** Create `embedding_cache` migration
  - **What:** Caches embeddings by content hash to avoid re-embedding unchanged text
  - **Why:** Embedding API calls cost money. If text hasn't changed (same hash), reuse the cached embedding. Saves ~90% of embedding costs during re-indexing.
  - **Context:** OpenClaw caches embeddings at provider+model+hash level. Same text, different model = different embedding.
  - Fields: `provider`, `model`, `content_hash`, `embedding` VECTOR(1536), `dims`, `created_at`
  - Primary key on (provider, model, content_hash)

#### 1.5.2 Full-Text Search Setup

- [ ] **1.5.2.1** Create PostgreSQL FTS index on memory_chunks
  - **What:** GIN index for full-text search on chunk content
  - **Why:** Vector search finds semantic similarity but misses exact matches. FTS catches exact keywords, names, and technical terms that embeddings might miss.
  - **Context:** PostgreSQL's built-in FTS is fast and requires no external service (unlike Elasticsearch).
  ```sql
  CREATE INDEX memory_chunks_fts ON memory_chunks USING gin(to_tsvector('english', text));
  ```

- [ ] **1.5.2.2** Create hybrid search function
  - **What:** SQL function that combines vector and FTS scores into final ranking
  - **Why:** Neither vector nor FTS alone is optimal. Combined scoring captures both semantic similarity and keyword relevance.
  - **Context:** OpenClaw uses 0.7 vector weight + 0.3 text weight. This weights semantic similarity higher but preserves exact match boost.
  - Combine vector similarity score with FTS rank
  - Default weights: 0.7 vector, 0.3 text

#### 1.5.3 Collection System (QMD)

- [ ] **1.5.3.1** Create `memory_collections` migration
  - **What:** Stores named collection definitions per agent for scoping memory search
  - **Why:** QMD uses collections to group indexed paths (memory-root, memory-alt, memory-dir). OpenCompany needs the same concept to scope searches to specific document subsets (identity files, memory logs, session transcripts, custom sets).
  - **Context:** Default collections created by AgentDocumentService during agent setup. Custom collections addable via API. Each agent has isolated collections.
  - Fields: `id` (UUID), `agent_config_id` (FK), `name` (string), `type` (enum: identity/memory/sessions/custom), `description` (nullable text), `created_at`, `updated_at`
  - Unique index on (`agent_config_id`, `name`)

- [ ] **1.5.3.2** Create `memory_collection_documents` pivot migration
  - **What:** Junction table linking collections to documents
  - **Why:** A document can belong to multiple collections (e.g., MEMORY.md in both memory-root and identity), and a collection contains multiple documents. Many-to-many relationship.
  - Fields: `id` (UUID), `collection_id` (FK), `document_id` (FK), `created_at`
  - Unique constraint on (`collection_id`, `document_id`)

#### 1.5.4 Result Clamping & Citation Support (QMD)

- [ ] **1.5.4.1** Add citation columns to `memory_chunks` migration
  - **What:** Add `document_id` (FK to documents) and `document_path` (text) columns to memory_chunks
  - **Why:** QMD returns citations in `path#Lstart-Lend` format. Storing the document reference and denormalized path in chunks enables generating citations without extra joins during search.
  - **Context:** `document_path` is denormalized from Document model's hierarchical path for fast citation generation.

- [ ] **1.5.4.2** Create `config/memory.php` configuration file
  - **What:** Configuration constants for QMD-equivalent search behavior
  - **Why:** Centralizes all memory search tuning parameters. Matches QMD's proven defaults while allowing per-deployment customization.
  - **Context:** Values derived from OpenClaw's QMD defaults.
  - Constants: `max_results` = 6, `max_snippet_chars` = 700, `max_injected_chars` = 4000, `timeout_ms` = 4000, `vector_weight` = 0.7, `text_weight` = 0.3, `chunk_size` = 400, `chunk_overlap` = 80, `periodic_interval` = 5 (minutes), `embedding_interval` = 60 (minutes), `debounce_seconds` = 15, `scope_default` = 'dm_only'

---

## Phase 2: Laravel Models

> **Why:** Eloquent models provide the ORM layer for all database operations. Models define relationships, casts, scopes, and business logic. Each model maps to a table from Phase 1.

### 2.1 Core Models

- [x] **2.1.1** Create `AgentConfiguration` model ← depends on: [1.4.1] — ✅ superseded by Document-based identity files + AgentDocumentService
  - **What:** Primary model for agent identity - personality, instructions, and visual identity
  - **Why:** Central model that all other agent-related models reference. Contains the agent's "soul" (personality) and "brain" (instructions).
  - **Context:** Uses soft deletes so deleted agents can be restored. Casts ensure JSON fields are handled as arrays.
  - Relationships: `belongsTo(User)`, `hasMany(AgentCapability)`, `hasOne(AgentSettings)`, `hasMany(AgentSession)`, `hasMany(AgentMemory)`
  - Casts: `identity` → array, `personality` → string, `instructions` → string

- [x] **2.1.2** Create `Capability` model ← depends on: [1.4.1] — ✅ superseded by ToolRegistry + AgentPermission model
  - **What:** System-wide capability/tool definitions
  - **Why:** Master list of available tools that agents can be granted. Includes tool kind for approval logic.
  - **Context:** Read-only from application perspective - admin-seeded. Agents reference these via AgentCapability pivot.
  - Relationships: `belongsToMany(AgentConfiguration)` through `agent_capabilities`

- [x] **2.1.3** Create `AgentCapability` model (pivot with extra fields) ← depends on: [2.1.1, 2.1.2] — ✅ superseded by AgentPermission model (scope-based: tool, channel, folder, integration)
  - **What:** Junction table linking agents to their enabled tools with per-agent settings
  - **Why:** Each agent can have different tool permissions. One agent might have code_execution with approval required, another without.
  - **Context:** The `notes` field stores agent-specific tool notes (e.g., "Use this for the staging server only").
  - Relationships: `belongsTo(AgentConfiguration)`, `belongsTo(Capability)`

- [x] **2.1.4** Create `AgentSettings` model ← depends on: [2.1.1] — ✅ superseded by fields on User model (behavior_mode, brain, sleeping_until, etc.)
  - **What:** Runtime behavior configuration for each agent
  - **Why:** Controls autonomy level, cost limits, context management, and security modes. Separating from AgentConfiguration keeps identity separate from behavior.
  - **Context:** Includes OpenClaw fields for reserve tokens, pruning TTL, security modes, etc.
  - Relationships: `belongsTo(AgentConfiguration)`
  - Casts: `reset_policy` → array, `behavior_mode` → enum

### 2.2 Memory Models

- [ ] **2.2.1** Create `AgentSession` model ← depends on: [2.1.1]
  - **What:** Represents a conversation session with an agent
  - **Why:** Sessions isolate conversations, track token usage, and enable context reset. The "active session" is the current conversation context.
  - **Context:** Status lifecycle: active → archived → deleted. Includes OpenClaw fields for memory flush tracking.
  - Relationships: `belongsTo(AgentConfiguration)`, `hasMany(AgentSessionMessage)`
  - Scopes: `active()`, `archived()`, `forAgent($agentId)`

- [ ] **2.2.2** Create `AgentSessionMessage` model ← depends on: [2.2.1]
  - **What:** Individual messages within a session (user input, assistant response, tool results)
  - **Why:** Full conversation history enables context loading. Token counts per message enable precise context window management.
  - **Context:** The `compaction` type stores summary when context is compressed. `is_silent` flag marks internal housekeeping messages.
  - Relationships: `belongsTo(AgentSession)`
  - Casts: `metadata` → array, `type` → enum

- [ ] **2.2.3** Create `AgentMemory` model ← depends on: [2.1.1]
  - **What:** Long-term persistent memories that survive session resets
  - **Why:** Agents need to remember facts, preferences, and context across sessions. This is the agent's durable memory bank.
  - **Context:** Categories: fact (user's timezone), preference (communication style), context (project details), note (learnings).
  - Relationships: `belongsTo(AgentConfiguration)`
  - Casts: `category` → enum

- [ ] **2.2.4** Create `AgentMemoryDailyLog` model ← depends on: [2.1.1]
  - **What:** Append-only daily activity logs (equivalent to OpenClaw's memory/YYYY-MM-DD.md)
  - **Why:** Daily logs provide temporal context. Agents can reference "what happened yesterday" without loading full history.
  - **Context:** `appendEntry()` method handles concurrent appends safely. One record per agent per day.
  - Relationships: `belongsTo(AgentConfiguration)`
  - Helper method: `appendEntry($content)`

### 2.3 Subagent Models

- [ ] **2.3.1** Create `SubagentSpawnPermission` model ← depends on: [2.1.1]
  - **What:** Controls which agents a parent agent can spawn
  - **Why:** Security boundary for agent hierarchies. Prevents unauthorized agent spawning and runaway processes.
  - **Context:** `allowed_agents` can be ["*"] for unrestricted, or specific agent IDs for limited access.
  - Relationships: `belongsTo(User, 'parent_agent_id')`
  - Casts: `allowed_agents` → array

- [ ] **2.3.2** Create `SubagentRun` model ← depends on: [2.3.1]
  - **What:** Tracks each subagent execution with parent-child relationship and results
  - **Why:** Audit trail and monitoring for spawned agents. Enables timeout tracking and result retrieval.
  - **Context:** Status enum handles full lifecycle. `result` JSON stores structured output from child agent.
  - Relationships: `belongsTo(User, 'parent_agent_id')`, `belongsTo(User, 'child_agent_id')`, `belongsTo(AgentSession)`
  - Casts: `runtime_config` → array, `result` → array, `status` → enum

### 2.4 Extend User Model

- [x] **2.4.1** Add relationships to User model ← depends on: [2.1.1-2.3.2] — ✅ User model has agent relationships (permissions, tasks, channels, documents, etc.)
  - **What:** Connect User model to agent-related models
  - **Why:** Users own agents. A user can have one agent configuration (if they are an agent user). Also tracks spawn permissions and runs.
  - **Context:** The `hasOne(AgentConfiguration)` is for "agent users" - users that are actually AI agents in the system.
  - `hasOne(AgentConfiguration)` - only for agent users
  - `hasOne(SubagentSpawnPermission, 'parent_agent_id')`
  - `hasMany(SubagentRun, 'parent_agent_id')`
  - `hasMany(SubagentRun, 'child_agent_id')`

- [x] **2.4.2** Add helper methods to User model — ✅ User model has isAgent(), agent-related scopes, permission helpers
  - **What:** Convenience methods for common agent operations
  - **Why:** Encapsulates agent-related logic in the model. `canSpawnAgent()` centralizes permission checking.
  - **Context:** These methods are used throughout controllers and services.
  - `isConfiguredAgent()` - checks if agent has configuration
  - `getActiveSession()` - returns current session
  - `canSpawnAgent($targetAgentId)` - checks spawn permission

---

## Phase 3: API Controllers

> **Why:** REST API layer that exposes agent functionality to the frontend. Each controller handles a specific domain (configuration, capabilities, settings, etc.) following Laravel resource conventions.

### 3.1 Agent Configuration Controller

- [x] **3.1.1** Create `AgentConfigurationController` ← depends on: [2.1.1] — ✅ superseded by AgentController with identity files API (GET/PUT /api/agents/{id}/identity/{fileType})
  - **What:** CRUD operations for agent personality, instructions, and identity
  - **Why:** Frontend needs to fetch and update agent configuration. Separate PATCH endpoints allow updating individual fields without sending the entire config.
  - **Context:** Personality and instructions are large text fields (markdown). Separate endpoints reduce payload size and enable autosave on specific fields.
  - `GET /api/agents/{id}/configuration` - get agent config
  - `PUT /api/agents/{id}/configuration` - update config
  - `PATCH /api/agents/{id}/personality` - update personality only
  - `PATCH /api/agents/{id}/instructions` - update instructions only
  - `PATCH /api/agents/{id}/identity` - update identity only
  - `PATCH /api/agents/{id}/tool-notes` - update tool notes only

### 3.2 Agent Capabilities Controller

- [x] **3.2.1** Create `AgentCapabilityController` ← depends on: [2.1.3] — ✅ superseded by AgentPermissionController (tool/channel/folder/integration permissions)
  - **What:** Manage which tools/capabilities are enabled for an agent
  - **Why:** Agents need different tools. This API enables the UI to toggle capabilities and set per-agent approval requirements.
  - **Context:** Bulk update is important for "save all changes" UX. Individual PATCH allows toggling single capability without affecting others.
  - `GET /api/agents/{id}/capabilities` - list agent capabilities
  - `PUT /api/agents/{id}/capabilities` - bulk update capabilities
  - `PATCH /api/agents/{id}/capabilities/{capabilityId}` - update single capability

- [x] **3.2.2** Create `CapabilityController` ← depends on: [2.1.2] — ✅ superseded: ToolRegistry provides tool list; AgentPermissionController serves capability data
  - **What:** Read-only access to system-wide capability definitions
  - **Why:** Frontend needs the master list of available capabilities to render the capability assignment UI.
  - **Context:** Capabilities are admin-seeded, not user-created. This is read-only.
  - `GET /api/capabilities` - list all available capabilities

### 3.3 Agent Settings Controller

- [x] **3.3.1** Create `AgentSettingsController` ← depends on: [2.1.4] — ✅ superseded: agent settings managed via AgentController (PATCH /api/agents/{id}) + Settings tab in Agent/Show.vue
  - **What:** Manage agent runtime behavior settings
  - **Why:** Users need to control agent autonomy, cost limits, and reset policies. Settings affect how the agent operates, not who it is.
  - **Context:** Includes OpenClaw settings (security_mode, ask_mode, reserve_tokens, etc.). Behavior mode enum: autonomous/supervised/strict.
  - `GET /api/agents/{id}/settings` - get agent settings
  - `PUT /api/agents/{id}/settings` - update all settings
  - `PATCH /api/agents/{id}/settings/behavior-mode` - update behavior mode
  - `PATCH /api/agents/{id}/settings/cost-limit` - update cost limit
  - `PATCH /api/agents/{id}/settings/reset-policy` - update reset policy

### 3.4 Agent Session Controller

- [ ] **3.4.1** Create `AgentSessionController` ← depends on: [2.2.1]
  - **What:** Manage conversation sessions and their messages
  - **Why:** Frontend needs to display session history, view messages, and create new sessions (context reset). Central to the chat experience.
  - **Context:** Creating a new session archives the current one and starts fresh. "Current session" is the active conversation context.
  - `GET /api/agents/{id}/sessions` - list sessions (paginated)
  - `GET /api/agents/{id}/sessions/current` - get current session
  - `POST /api/agents/{id}/sessions` - create new session (reset current)
  - `GET /api/sessions/{sessionId}` - get session details
  - `GET /api/sessions/{sessionId}/messages` - get session messages (paginated)
  - `DELETE /api/sessions/{sessionId}` - archive session

### 3.5 Agent Memory Controller

- [ ] **3.5.1** Create `AgentMemoryController` ← depends on: [2.2.3, 2.2.4]
  - **What:** Manage persistent memories and daily logs
  - **Why:** Users need to view, add, and delete memories. Daily logs provide activity history. Memory reset is a destructive action for "starting fresh."
  - **Context:** Memories persist across sessions - they're the agent's long-term knowledge. Daily logs are append-only and temporal.
  - `GET /api/agents/{id}/memories` - list persistent memories
  - `POST /api/agents/{id}/memories` - add memory entry
  - `DELETE /api/agents/{id}/memories/{memoryId}` - delete memory
  - `DELETE /api/agents/{id}/memories` - clear all memories (reset)
  - `GET /api/agents/{id}/daily-logs` - list daily logs
  - `GET /api/agents/{id}/daily-logs/{date}` - get specific day's log

### 3.6 Subagent Controller

- [ ] **3.6.1** Create `SubagentController` ← depends on: [2.3.1, 2.3.2]
  - **What:** Manage subagent spawning permissions and track spawned agent runs
  - **Why:** Enables agent hierarchies. Users need to configure which agents can spawn which, monitor running subagents, and cancel if needed.
  - **Context:** Spawn endpoint triggers the SubagentSpawnService. Cancel stops the running task.
  - `GET /api/agents/{id}/spawn-permissions` - get spawn permissions
  - `PUT /api/agents/{id}/spawn-permissions` - update permissions
  - `POST /api/agents/{id}/spawn` - spawn subagent
  - `GET /api/agents/{id}/subagent-runs` - list subagent runs
  - `GET /api/subagent-runs/{id}` - get run details
  - `POST /api/subagent-runs/{id}/cancel` - cancel running subagent

### 3.7 Register Routes

- [x] **3.7.1** Add all routes to `routes/api.php` ← depends on: [3.1.1-3.6.1] — ✅ comprehensive routes for all controllers (268 lines in routes/api.php)
  - **What:** Wire up all controller methods to URL routes
  - **Why:** Routes connect HTTP requests to controller actions. Must be registered before frontend can call the API.
  - **Context:** Group under `/api/agents` prefix. Auth middleware ensures only authenticated users access their agents.
  - Group under `agents` prefix
  - Apply auth middleware
  - Add rate limiting where appropriate

---

## Phase 3.5: Agent Execution Integration (Laravel AI SDK + Queues)

> **Why:** This phase connects the AI layer (Laravel AI SDK) with Laravel's queue system for async task processing. Tools give agents abilities. Queue jobs and services coordinate multi-step agent tasks with durability and approval gates.

### 3.5.1 Create Agent Tools

- [x] **3.5.1.1** Create `app/Agents/Tools/` directory — ✅ exists with 30+ tool classes across Chat/, Docs/, Lists/, Tables/, Calendar/, Tasks/, System/, Workspace/, Charts/, Telegram/, Agents/ subdirs
  - **What:** Directory for Laravel AI SDK tool definitions
  - **Why:** Organizes AI tools separately from services. Each tool class implements the SDK `Tool` contract.

- [x] **3.5.1.2** Create tool classes for agent capabilities — ✅ 30+ tools implemented
  - **What:** Laravel AI SDK `Tool` implementations for each capability type
  - **Why:** Tools are how agents interact with the system. Each tool wraps a system capability (documents, tasks, messaging, etc.) with parameter validation and execution logic.
  - **Context:** Tools implement the SDK `Tool` contract with `description()`, `handle()`, and `schema()` methods. Use `php artisan make:tool` to scaffold.
  - `SearchDocuments` - search workspace documents
  - `ReadDocument` / `UpdateDocument` - document CRUD
  - `CreateListItem` / `UpdateListItem` - list management
  - `SendMessage` - messaging
  - `CreateTaskStep` - task progress tracking
  - `CreateApproval` - request human approval
  - `QueryDataTable` - data table queries
  - `WebSearch` / `WebFetch` - web capabilities (SDK built-in)

- [x] **3.5.1.3** Create tool registry service — ✅ app/Agents/Tools/ToolRegistry.php with APP_GROUPS and getToolsForAgent()
  - **What:** Service that provides tools to agents based on their DB-stored capabilities
  - **Why:** Agents should only see tools they're allowed to use. The registry maps capability strings from the DB to tool class instances.
  - **Context:** Called by `OpenCompanyAgent::tools()` to resolve the tool list dynamically.
  ```php
  class ToolRegistry {
      private array $capabilityToolMap = [
          'documents'   => [SearchDocuments::class, ReadDocument::class, UpdateDocument::class],
          'lists'       => [CreateListItem::class, UpdateListItem::class],
          'messaging'   => [SendMessage::class],
          'tasks'       => [CreateTaskStep::class],
          'approvals'   => [CreateApproval::class],
          'web_search'  => [WebSearch::class],
          'web_fetch'   => [WebFetch::class],
      ];

      public function getToolsForAgent(User $agent): array
  }
  ```

### 3.5.2 Create Agent Jobs

> **Why:** Jobs are the building blocks of agent task execution. Each job does one thing: fetch config, execute AI, save message, etc. Jobs are retryable and queued for async processing.

- [x] **3.5.2.1** Create `app/Jobs/Agent/` directory — ✅ superseded: agent jobs live directly in app/Jobs/ (AgentRespondJob, ExecuteAgentTaskJob, etc.)
  - **What:** Directory for agent-specific job classes
  - **Why:** Organizes agent jobs separately from other system jobs. Each class handles one atomic operation.

- [x] **3.5.2.2** Create `FetchAgentConfigJob` — ✅ superseded: config fetching is inline in AgentRespondJob + OpenCompanyAgent
  - **What:** Load agent configuration and enabled tools from database
  - **Why:** Agent tasks need agent config to operate. This job fetches who the agent is and what they can do.
  - **Context:** Returns AgentConfiguration with relationships (capabilities, settings) loaded.
  - Fetch agent configuration from database
  - Return config with enabled tools

- [x] **3.5.2.3** Create `ExecuteAgentJob` ← depends on: [3.5.1.2] — ✅ implemented as AgentRespondJob + ExecuteAgentTaskJob in app/Jobs/
  - **What:** Execute Laravel AI SDK agent call with tools
  - **Why:** This is the core AI execution - send prompt to LLM, get response, handle tool calls. This job wraps `OpenCompanyAgent` for queued execution.
  - **Context:** Uses SDK's `#[MaxSteps]` attribute for multi-turn tool use. Token tracking is critical for billing and context management.
  - Execute agent prompt with tools
  - Handle streaming responses via `->stream()->broadcastOnQueue()`
  - Track token usage
  ```php
  class ExecuteAgentJob implements ShouldQueue {
      public function handle(): AgentResult {
          $config = app(DynamicProviderResolver::class)->resolveForAgent($this->agentUser);
          $agent = OpenCompanyAgent::for($this->agentUser);

          return $agent->prompt(
              $this->prompt,
              provider: $config['provider'],
              model: $config['model'],
          );
      }
  }
  ```

- [x] **3.5.2.4** Create `CreateApprovalRequestJob` — ✅ superseded: approval creation handled by ApprovalWrappedTool + SendApprovalToTelegramJob
  - **What:** Create an approval request record and notify users
  - **Why:** When agent wants to do something risky (database access, deployment), humans must approve. This job creates the approval request.
  - **Context:** Approval requests appear in the Approvals page. Users are notified via WebSocket.
  - Create approval record in database
  - Notify relevant users
  - Return approval request ID

- [x] **3.5.2.5** Create approval handling service — ✅ ApprovalExecutionService + WaitForApproval tool + ApprovalController
  - **What:** Service that polls/waits for approval decisions
  - **Why:** Agent execution must pause and wait for human decision. This service checks approval status and resumes execution when approved/rejected.
  - **Context:** Can use polling or event-based approach. Rejection cancels the task.
  - Check approval status
  - Resume execution when approved/rejected

- [x] **3.5.2.6** Create `ExecuteApprovedActionJob` — ✅ superseded: ApprovalExecutionService handles executing approved actions inline
  - **What:** Execute the action that was approved
  - **Why:** After approval, the original tool call needs to be executed. This job runs the approved action safely.
  - **Context:** Logs the execution for audit trail. Updates task status to completed.
  - Execute the approved action
  - Update task status

- [ ] **3.5.2.7** Create `SaveSessionMessageJob`
  - **What:** Persist messages to the session_messages table
  - **Why:** All messages (user, assistant, tool) must be saved for context loading and history. This job handles persistence and token count updates.
  - **Context:** Handles the `is_silent` flag for NO_REPLY messages.
  - Persist messages to agent_session_messages table
  - Update token counts

- [ ] **3.5.2.8** Create `MemoryFlushJob` ← depends on: [3.6.2.1]
  - **What:** Execute pre-compaction memory flush (OpenClaw)
  - **Why:** Before context compaction, the agent should save important information to durable memory. This job triggers a silent agent turn to do that.
  - **Context:** Uses NO_REPLY convention so output isn't shown to user. The agent is prompted to persist important context to MEMORY.md.
  - Execute pre-compaction memory flush
  - Return flush result for logging

- [ ] **3.5.2.9** Create `PruneSessionJob` ← depends on: [3.6.3.1]
  - **What:** Prune old tool results from session context (OpenClaw)
  - **Why:** Old tool results bloat context. After TTL expires (5 minutes default), tool results are trimmed or replaced with placeholders.
  - **Context:** Uses soft-trim (keep head+tail) or hard-clear depending on size. Protects last 3 assistant messages.
  - Prune old tool results if TTL elapsed
  - Return pruning stats

- [ ] **3.5.2.10** Create `CheckMemoryFlushJob`
  - **What:** Check if memory flush is needed based on soft threshold (OpenClaw)
  - **Why:** Memory flush should run before compaction, not after. This job checks if we're approaching the threshold and haven't flushed this cycle.
  - **Context:** Uses `memoryFlushCompactionCount` to prevent duplicate flushes per compaction cycle.
  - Check if memory flush is needed based on soft threshold
  - Return boolean

### 3.5.3 Create Agent Orchestration Services

> **Why:** Services orchestrate jobs into complete agent operations. They handle the full lifecycle: load config → check context → execute AI → save results → handle approvals.

- [x] **3.5.3.1** Create `app/Services/Agent/` directory — ✅ superseded: agent services live in app/Services/ (AgentChatService, AgentPermissionService, AgentDocumentService, etc.)
  - **What:** Directory for agent orchestration services
  - **Why:** Organizes agent services separately. Each service class defines a complete agent operation.

- [x] **3.5.3.2** Create `AgentTaskService` ← depends on: [3.5.2.2-3.5.2.10] — ✅ superseded: implemented as AgentChatService + AgentRespondJob orchestration
  - **What:** Main service for executing an agent task (responding to user input)
  - **Why:** This is the core agent loop. It handles OpenClaw patterns (memory flush, pruning), executes the AI, saves messages, and manages approvals.
  - **Context:** Uses Laravel's queue system for async execution. Jobs can be retried on failure.
  ```php
  class AgentTaskService {
      public function execute(AgentTask $task): AgentResult {
          // 1. Fetch agent config
          $config = FetchAgentConfigJob::dispatchSync($task->agentId);

          // 2. Check if memory flush needed before execution (OpenClaw)
          $flushNeeded = CheckMemoryFlushJob::dispatchSync($task->sessionId);
          if ($flushNeeded) {
              MemoryFlushJob::dispatchSync($task->sessionId);
          }

          // 3. Prune session if TTL elapsed (OpenClaw)
          PruneSessionJob::dispatchSync($task->sessionId);

          // 4. Execute agent with Laravel AI SDK
          $result = ExecuteAgentJob::dispatchSync($config, $task->prompt);

          // 5. Handle silent responses (NO_REPLY convention)
          if (str_starts_with($result->text, 'NO_REPLY')) {
              SaveSessionMessageJob::dispatchSync($task->sessionId, $result, true);
              return $result->withSuppressedOutput();
          }

          // 6. Save messages to session
          SaveSessionMessageJob::dispatchSync($task->sessionId, $result);

          // 7. Handle approval if needed
          if ($result->requiresApproval) {
              $approval = CreateApprovalRequestJob::dispatchSync($result);
              $approved = $this->waitForApproval($approval->id);

              if ($approved) {
                  ExecuteApprovedActionJob::dispatchSync($result);
              }
          }

          return $result;
      }
  }
  ```

- [ ] **3.5.3.3** Create `AgentSessionResetService`
  - **What:** Service for resetting agent sessions (daily reset, idle reset, manual reset)
  - **Why:** Sessions need to be reset according to reset_policy. This service archives the old session, creates a new one, and optionally runs a "goodbye" summary.
  - **Context:** Triggered by scheduler for daily resets, or by idle detection for idle resets.
  - Handle scheduled session resets
  - Archive old session
  - Create new session

- [ ] **3.5.3.4** Create `SubagentSpawnService` ← depends on: [3.5.3.2]
  - **What:** Service for spawning and managing a child agent
  - **Why:** Subagent spawning needs to track the parent-child relationship, enforce timeouts, and handle cancellation. This service wraps AgentTaskService with spawn-specific logic.
  - **Context:** Creates SubagentRun record, starts child task, waits for completion or timeout, stores result.
  - Spawn child agent task
  - Track parent-child relationship
  - Handle timeout and cancellation

### 3.5.4 Queue Infrastructure

> **Why:** Agent jobs need queue workers to process them and APIs to monitor/control them. This infrastructure makes agent execution operational.

- [x] **3.5.4.1** Configure queue workers for agent jobs — ✅ queue config exists; agent jobs dispatched to queue
  - **What:** Set up queue configuration for agent job processing
  - **Why:** Agent jobs need dedicated queue configuration. May need separate queues for high-priority vs background tasks.
  - **Context:** Configure in `config/queue.php`. Consider separate connection for agent jobs.
  ```bash
  php artisan queue:work --queue=agents,default
  ```

- [x] **3.5.4.2** Add agent task status endpoints — ✅ TaskController with full lifecycle endpoints (start/pause/resume/complete/fail/cancel)
  - **What:** API endpoints to check agent task status and manage execution
  - **Why:** Frontend needs to display task progress (e.g., "waiting for approval", "executing"). Endpoints enable monitoring and control.
  - **Context:** Status updates broadcast via WebSocket for real-time UI updates.
  - `GET /api/agent-tasks/{id}` - get task status
  - `POST /api/agent-tasks/{id}/cancel` - cancel running task

- [ ] **3.5.4.3** Configure Horizon for queue monitoring (optional)
  - **What:** Install Laravel Horizon for queue monitoring dashboard
  - **Why:** Debugging agent jobs is easier with a visual UI. Shows job history, failures, and queue metrics.
  - **Context:** Optional - can use database queries or Laravel Telescope if preferred.
  ```bash
  composer require laravel/horizon
  ```

---

## Phase 3.6: Context Management Services (OpenClaw)

> **Why:** These services implement OpenClaw's sophisticated context management patterns. Without them, agents would lose important context during compaction, accumulate bloated tool results, and lack nuanced approval controls.

### 3.6.1 Context Window Guard

- [ ] **3.6.1.1** Create `ContextWindowGuard` service ← depends on: [2.1.4]
  - **What:** Service that monitors and enforces context window limits
  - **Why:** LLMs have fixed context windows (e.g., 200K tokens for Claude). This guard tracks usage, enforces reserves, and triggers compaction when needed.
  - **Context:** OpenClaw reserves tokens for compaction operations. The floor (20K) ensures there's always room to write summaries.
  - `resolveContextWindowInfo($agent)` - get window size and reserves
  - `canAcceptTokens($session, $newTokens)` - check if within limits
  - `shouldTriggerCompaction($session)` - check threshold
  - Enforce `reserveTokensFloor` minimum (20,000)

### 3.6.2 Pre-Compaction Memory Flush

- [ ] **3.6.2.1** Create `MemoryFlushService` ← depends on: [3.6.1.1]
  - **What:** Service that runs a silent agent turn to persist important context before compaction
  - **Why:** Compaction loses detail. Before it happens, the agent should save important facts, decisions, and learnings to durable memory. This prevents "amnesia."
  - **Context:** OpenClaw triggers this at `contextWindow - reserveTokensFloor - softThresholdTokens`. The `memoryFlushCompactionCount` prevents running multiple flushes per compaction cycle.
  - `shouldRunMemoryFlush($session)` - check soft threshold
  - `runMemoryFlush($session)` - execute silent agent turn
  - Track `memoryFlushCompactionCount` to prevent duplicate flushes
  - Use NO_REPLY convention for silent output

- [ ] **3.6.2.2** Create memory flush system prompt
  - **What:** Special system prompt that instructs the agent to save important context
  - **Why:** The agent needs clear instructions about what to save. The prompt should list categories: facts learned, user preferences, important decisions, etc.
  - **Context:** Response starts with NO_REPLY so it's hidden from users.
  - System prompt: "Store durable memories before context compaction"
  - Instruct agent to persist important context to MEMORY.md

### 3.6.3 Session Pruning

- [ ] **3.6.3.1** Create `SessionPruningService` ← depends on: [2.2.2]
  - **What:** Service that trims old tool results from session context
  - **Why:** Tool results (file contents, command output) bloat context quickly. After a TTL, old results are less relevant. Pruning keeps context focused on recent activity.
  - **Context:** OpenClaw uses 5-minute TTL. Soft-trim keeps head+tail so there's still context about what the tool returned. Hard-clear is for very large results.
  - `shouldPrune($session)` - check TTL elapsed since last API call
  - `pruneSession($session)` - trim old tool results
  - Soft-trim: head (1500 chars) + tail (1500 chars)
  - Hard-clear: replace with `[Old tool result content cleared]`
  - Protect last 3 assistant messages

### 3.6.4 Tool Kind Classification

- [ ] **3.6.4.1** Create `ToolKindClassifier` service
  - **What:** Service that classifies tools by their operation type (read/edit/delete/etc.)
  - **Why:** Different operations have different risk levels. Reads are safe, deletes are dangerous. Classification enables nuanced approval policies (e.g., "auto-approve reads, require approval for deletes").
  - **Context:** OpenClaw infers kind from tool name patterns. We store it in the database for faster lookup.
  - `inferToolKind($toolName)` - classify tool by operation type
  - Patterns: contains 'read' → read, 'write/edit/update' → edit, etc.
  - Used by approval logic for nuanced decisions

### 3.6.5 Execution Approval System

- [ ] **3.6.5.1** Create `ExecutionApprovalService` ← depends on: [3.6.4.1]
  - **What:** Service that decides whether a tool execution needs approval
  - **Why:** Centralized approval logic based on security mode, ask mode, and allowlist patterns. This is the gatekeeper for all tool executions.
  - **Context:** Security modes (deny/allowlist/full) control what CAN execute. Ask modes (off/on-miss/always) control what PROMPTS for approval.
  - `evaluateExecution($agent, $tool, $args)` - check against settings
  - Security mode logic: deny blocks all, allowlist checks patterns, full allows all
  - Ask mode logic: always prompts, on-miss prompts for non-allowlisted
  - Track allowlist usage: update `last_used_at`, `last_used_command`

- [ ] **3.6.5.2** Define default safe skills
  - **What:** List of tools that are pre-approved by default
  - **Why:** Common read-only tools (jq, grep, wc) are safe and frequent. Auto-approving them reduces friction without increasing risk.
  - **Context:** Configurable via `auto_allow_skills` setting. Users can disable if they want stricter control.
  - Auto-allow: jq, grep, cut, sort, uniq, head, tail, tr, wc
  - Configurable via `auto_allow_skills` setting

- [ ] **3.6.5.3** Create `AgentToolAllowlist` model ← depends on: [1.2.5]
  - **What:** Eloquent model for agent-specific command allowlist patterns
  - **Why:** Users can pre-approve command patterns (e.g., "npm test", "git status"). This model handles pattern matching and usage tracking.
  - **Context:** Patterns can be exact matches or globs. Usage tracking helps identify stale patterns.
  - Relationships: `belongsTo(AgentConfiguration)`
  - Methods: `matchPattern($command)`, `trackUsage($command)`

---

## Phase 3.7: Hybrid Memory Search (OpenClaw)

> **Why:** Agents need to search their memories intelligently. Hybrid search combines vector embeddings (semantic similarity) with full-text search (exact matches) for best results. This enables agents to recall relevant information even when phrased differently.

### 3.7.1 Embedding Service

- [ ] **3.7.1.1** Create `EmbeddingService` ← depends on: [0.1.3]
  - **What:** Service that generates vector embeddings for text
  - **Why:** Embeddings convert text to numerical vectors that capture meaning. Similar texts have similar vectors. This enables semantic search (finding relevant content even with different wording).
  - **Context:** OpenAI's text-embedding-3-small is cost-effective. GLM also provides embeddings. Batch mode reduces API calls for bulk indexing.
  - `embed($text)` - generate embedding via configured provider
  - Support OpenAI text-embedding-3-small, GLM embeddings
  - Batch mode for multiple texts

- [ ] **3.7.1.2** Create `EmbeddingCacheService` ← depends on: [1.5.1.3]
  - **What:** Caching layer that prevents re-embedding unchanged text
  - **Why:** Embedding API calls cost money. If text hasn't changed, reuse the cached embedding. Critical for cost control during re-indexing operations.
  - **Context:** Uses content hash as cache key. Same text = same hash = cache hit. Different provider or model = different cache entry.
  - `getOrCreate($text)` - check cache, generate if missing
  - Hash text content for cache key
  - Prevent re-embedding unchanged content

### 3.7.2 Chunking Service

- [ ] **3.7.2.1** Create `ChunkingService`
  - **What:** Service that splits long texts into optimal chunks for embedding
  - **Why:** Embeddings work best on moderate-sized text chunks (~400 tokens). Chunking with overlap ensures context isn't lost at chunk boundaries.
  - **Context:** OpenClaw uses 400-token chunks with 80-token overlap. Line number tracking enables linking search results back to source location.
  - `chunkText($text, $maxTokens, $overlap)` - split into chunks
  - Default: ~400 tokens per chunk, 80 token overlap
  - Track start/end line numbers

### 3.7.3 Memory Indexing

- [ ] **3.7.3.1** Create `MemoryIndexService` ← depends on: [3.7.1.1, 3.7.2.1]
  - **What:** Service that indexes memories and session messages for search
  - **Why:** Before searching, content must be chunked and embedded. This service manages the indexing pipeline for all agent content.
  - **Context:** Indexing should happen in background jobs to avoid blocking user requests. Full reindex is needed when chunking strategy changes.
  - `indexMemory($agentId, $memoryId)` - index single memory entry
  - `reindexAgent($agentId)` - full reindex for agent
  - `indexSession($sessionId)` - index session messages
  - Background job for indexing

### 3.7.4 Hybrid Search

- [ ] **3.7.4.1** Create `HybridMemorySearch` service ← depends on: [3.7.3.1]
  - **What:** Service that performs combined vector + FTS search
  - **Why:** Neither search method alone is optimal. Vector search finds semantic matches but misses exact keywords. FTS catches exact matches but misses paraphrases. Combined scoring gives best of both.
  - **Context:** OpenClaw weights 0.7 vector + 0.3 text. pgvector's `<=>` operator computes cosine distance. PostgreSQL's `ts_rank` provides FTS scoring.
  - `search($agentId, $query, $limit)` - combined search
  - Vector similarity via pgvector `<=>` operator
  - FTS via `ts_rank` + `to_tsvector`
  - Combined scoring: `0.7 * vectorScore + 0.3 * textScore`
  - Return chunks with source file + line ranges

- [ ] **3.7.4.2** Create `MemorySearchController` ← depends on: [3.7.4.1]
  - **What:** API endpoint for searching agent memory
  - **Why:** Frontend needs to search agent memory for the MemorySearchInput component. Also used by agents themselves to recall information.
  - **Context:** Limit parameter prevents returning too many results. Response includes source references for navigation.
  - `POST /api/agents/{id}/memory/search` - search agent memory
  - Request: `{ query: string, limit?: number }`
  - Response: `{ results: [{ text, score, source, lines }] }`

### 3.7.5 Collection Management (QMD)

- [ ] **3.7.5.1** Create `MemoryCollection` model ← depends on: [1.5.3.1]
  - **What:** Eloquent model for named search collections that scope memory queries
  - **Why:** Collections are the fundamental organizing unit for QMD search. They determine which documents are included in search results for each query.
  - **Context:** Maps to QMD's collection system where `memory-root`, `memory-dir`, etc. define what's searchable.
  - Relationships: `belongsTo(AgentConfiguration)`, `belongsToMany(Document)` through `memory_collection_documents`
  - Scopes: `forAgent($agentConfigId)`, `ofType($type)`
  - Methods: `addDocument($doc)`, `removeDocument($doc)`, `getSearchableChunkIds()`

- [ ] **3.7.5.2** Create default collections in `AgentDocumentService` ← depends on: [3.7.5.1]
  - **What:** Automatically create default collections when agent document structure is set up
  - **Why:** Every agent needs baseline collections matching QMD defaults. Automatic creation ensures agents are immediately searchable without manual setup.
  - **Context:** Hook into existing `createAgentDocumentStructure()` method.
  - Default collections: `identity` (8 identity files), `memory-root` (MEMORY.md), `memory-logs` (memory/*.md daily logs), `sessions` (indexed transcripts)
  - Auto-populate identity collection with identity documents on creation

- [ ] **3.7.5.3** Create `MemoryCollectionController` ← depends on: [3.7.5.1]
  - **What:** API endpoints for managing custom memory collections
  - **Why:** Users or agents may want to create custom collections for project-specific document groups beyond the defaults.
  - `GET /api/agents/{id}/memory/collections` — list agent's collections
  - `POST /api/agents/{id}/memory/collections` — create custom collection
  - `POST /api/agents/{id}/memory/collections/{cid}/documents` — add document to collection
  - `DELETE /api/agents/{id}/memory/collections/{cid}/documents/{did}` — remove document from collection

### 3.7.6 Session Transcript Indexing (QMD)

- [ ] **3.7.6.1** Create `ExportSessionTranscriptJob` ← depends on: [3.7.3.1]
  - **What:** Queue job that converts session messages to markdown and stores as a Document in the agent's memory/ folder
  - **Why:** QMD indexes session transcripts so agents can search past conversations. This enables agents to recall information from previous sessions by searching through exported transcripts semantically.
  - **Context:** Exported document is automatically added to the 'sessions' collection and triggers chunk indexing.
  - Convert session messages to markdown (preserve role attribution: user/assistant/tool)
  - Store as Document in agent's memory/ folder with title `session-{date}-{id}.md`
  - Attach to 'sessions' MemoryCollection
  - Dispatch `IndexAgentMemoryJob` for the new document

- [ ] **3.7.6.2** Wire session transcript export to session lifecycle ← depends on: [3.7.6.1]
  - **What:** Trigger transcript export on session archival events
  - **Why:** Sessions are archived on daily reset, idle timeout, or manual reset. Each archival should produce a searchable transcript document.
  - **Context:** Hook into AgentSession status change from 'active' to 'archived'.
  - Dispatch `ExportSessionTranscriptJob` on session archival
  - Include in pre-compaction memory flush pipeline
  - Optional: configurable retention (default: 30 days)

### 3.7.7 Periodic Re-Indexing (QMD)

- [ ] **3.7.7.1** Create `PeriodicReindexJob` ← depends on: [3.7.3.1]
  - **What:** Scheduled job that re-indexes changed documents across all active agents
  - **Why:** QMD re-indexes every 5 minutes to catch changes. OpenCompany needs the same periodic cycle to catch document changes not triggered by model observers (e.g., direct DB updates, bulk imports, admin edits).
  - **Context:** Uses delta tracking (SHA256 hash comparison on `content_hash`) to only re-process changed documents.
  - Schedule: `everyFiveMinutes()` in `app/Console/Kernel.php`
  - Delta-based: compare stored content hash vs current content hash, skip unchanged
  - Scope: all agents with at least one active session or recent activity

- [ ] **3.7.7.2** Create `EmbeddingRefreshJob` ← depends on: [3.7.1.1]
  - **What:** Scheduled job that regenerates stale or missing embeddings
  - **Why:** QMD refreshes embeddings every 60 minutes (less frequent than text indexing since embeddings are expensive). Embeddings may need regeneration when provider changes, models are updated, or new chunks lack embeddings.
  - Schedule: `hourly()` in `app/Console/Kernel.php`
  - Process chunks where embedding is null or provider/model has changed
  - Uses `EmbeddingCacheService` to avoid redundant API calls

- [ ] **3.7.7.3** Add Document model observer for indexing triggers ← depends on: [3.7.3.1]
  - **What:** Eloquent observer on Document model that dispatches indexing jobs when memory documents are created or updated
  - **Why:** QMD uses file watchers with 15-second debounce to detect changes. OpenCompany uses model observers with debounced queue dispatch for the same purpose.
  - **Context:** Only trigger for documents inside agent memory/ and identity/ folders, not all documents.
  - Observer triggers on `created` and `updated` events
  - Filter: only agent memory/identity documents (check parent folder hierarchy)
  - Dispatch `IndexAgentMemoryJob::dispatch($doc->id)->delay(15)` (15-second debounce)

### 3.7.8 Scope Rules & Security (QMD)

- [ ] **3.7.8.1** Create `MemorySearchScopeGuard` service ← depends on: [3.7.4.1]
  - **What:** Service that enforces search scope restrictions based on chat/conversation type
  - **Why:** QMD restricts memory search by chat type (DM-only by default). This prevents agents from leaking private conversation memories when operating in group channel contexts.
  - **Context:** Configurable per agent via `AgentSettings.memory_search_scope`. Default: `dm_only`.
  - Scope modes: `dm_only` (default), `all`, `none`
  - DM conversations: full memory access (all collections)
  - Group channels: only shared/public memories (no session transcripts)
  - Applied as a filter wrapper around `HybridDocumentSearch`

- [ ] **3.7.8.2** Add security checks to `RecallMemory` tool ← depends on: [3.7.8.1]
  - **What:** Enforce QMD-equivalent security guards in the memory search tool
  - **Why:** QMD blocks non-markdown reads, rejects symlinks, and prevents path traversal. RecallMemory tool needs identical guards to prevent agents from accessing unauthorized content.
  - Validate document paths are within agent scope (own documents only)
  - Reject attempts to read non-markdown content
  - Block path traversal patterns (`../`)
  - Enforce collection-based access (only search documents in agent's collections)

### 3.7.9 Enhanced HybridMemorySearch with QMD Features

- [ ] **3.7.9.1** Add result clamping to `HybridMemorySearch` ← depends on: [3.7.4.1, 1.5.4.2]
  - **What:** Enforce QMD-equivalent result limits to prevent context bloat
  - **Why:** Without clamping, search results can overwhelm the agent's context window. QMD's defaults are battle-tested to balance relevance with context budget.
  - **Context:** Read limits from `config/memory.php`.
  - `maxResults`: 6 (top-K results)
  - `maxSnippetChars`: 700 (truncate at word boundaries)
  - `maxInjectedChars`: 4000 (total across all results)
  - `timeoutMs`: 4000 (enforced via `DB::timeout()` or query cancellation)

- [ ] **3.7.9.2** Add citation generation to search results ← depends on: [3.7.4.1, 1.5.4.1]
  - **What:** Generate `path#Lstart-Lend` citations for each search result
  - **Why:** Citations enable agents to reference source material precisely and users to navigate to the exact source of retrieved information.
  - Format: `document_path#L{start_line}[-L{end_line}]`
  - Include citation in SearchResult response object
  - Auto-mode: show citations in DM, suppress in groups (matching QMD's `"auto"` citation mode)

- [ ] **3.7.9.3** Add collection filtering to `HybridMemorySearch` ← depends on: [3.7.5.1]
  - **What:** Allow searches to be scoped to specific named collections
  - **Why:** Agents may want to search only identity files, or only session transcripts, or only daily logs. Collection filtering enables this granularity without complex query building.
  - Optional `collectionNames` parameter on search method
  - Default: search all collections for the agent
  - Filter `memory_chunks` by document membership in specified collections

---

## Phase 4: Frontend API Integration

> **Why:** The frontend needs TypeScript methods to call all backend APIs. This phase creates the API client layer that Vue components will use. Centralizing API calls in useApi ensures consistent error handling and type safety.

### 4.1 Extend useApi Composable

- [ ] **4.1.1** Add agent configuration methods to `useApi.ts` ← depends on: [3.1.1]
  - **What:** TypeScript methods for fetching and updating agent configuration
  - **Why:** Agent configuration (personality, instructions, identity) is the most frequently edited data. These methods connect the configuration editor components to the backend.
  - **Context:** Separate update methods for each field enable autosave without sending entire config.
  ```typescript
  fetchAgentConfiguration(agentId: string)
  updateAgentConfiguration(agentId: string, data)
  updateAgentPersonality(agentId: string, content: string)
  updateAgentInstructions(agentId: string, content: string)
  updateAgentIdentity(agentId: string, identity)
  updateAgentToolNotes(agentId: string, notes: string)
  ```

- [ ] **4.1.2** Add agent capabilities methods ← depends on: [3.2.1]
  - **What:** Methods for managing agent tool/capability assignments
  - **Why:** Capabilities UI needs to fetch available capabilities and update agent's enabled tools. Bulk update enables "save all changes" pattern.
  - **Context:** `fetchAllCapabilities()` gets the system-wide list. Agent-specific capabilities have per-agent settings (enabled, requires_approval).
  ```typescript
  fetchAgentCapabilities(agentId: string)
  updateAgentCapabilities(agentId: string, capabilities)
  fetchAllCapabilities()
  ```

- [ ] **4.1.3** Add agent settings methods ← depends on: [3.3.1]
  - **What:** Methods for managing agent runtime settings
  - **Why:** Settings panel needs to fetch and update behavior mode, cost limits, reset policies, and OpenClaw settings (security mode, ask mode, etc.).
  - **Context:** Individual update methods allow saving specific settings without full form submission.
  ```typescript
  fetchAgentSettings(agentId: string)
  updateAgentSettings(agentId: string, settings)
  updateAgentBehaviorMode(agentId: string, mode)
  updateAgentCostLimit(agentId: string, limit: number)
  updateAgentResetPolicy(agentId: string, policy)
  ```

- [ ] **4.1.4** Add agent session methods ← depends on: [3.4.1]
  - **What:** Methods for managing conversation sessions and messages
  - **Why:** Session UI needs to list past sessions, view messages, and create new sessions (context reset). This is central to the chat/memory experience.
  - **Context:** Pagination is important for sessions with many messages. `createNewSession` archives current and starts fresh.
  ```typescript
  fetchAgentSessions(agentId: string, page?: number)
  fetchCurrentSession(agentId: string)
  createNewSession(agentId: string)
  fetchSessionMessages(sessionId: string, page?: number)
  archiveSession(sessionId: string)
  ```

- [ ] **4.1.5** Add agent memory methods ← depends on: [3.5.1]
  - **What:** Methods for managing persistent memories and daily logs
  - **Why:** Memory view needs to display, add, and delete memories. Reset is a destructive action that clears all agent knowledge.
  - **Context:** Daily logs are read-only from frontend perspective. They're written by the agent during operation.
  ```typescript
  fetchAgentMemories(agentId: string)
  addAgentMemory(agentId: string, entry)
  deleteAgentMemory(agentId: string, memoryId: string)
  resetAgentMemory(agentId: string)
  fetchAgentDailyLogs(agentId: string)
  ```

- [ ] **4.1.6** Add subagent methods ← depends on: [3.6.1]
  - **What:** Methods for managing subagent spawning
  - **Why:** Subagent UI needs to configure spawn permissions, trigger spawns, monitor runs, and cancel if needed.
  - **Context:** Spawn is async - it starts a background task and returns immediately. Frontend polls or uses WebSocket to track progress.
  ```typescript
  fetchSpawnPermissions(agentId: string)
  updateSpawnPermissions(agentId: string, permissions)
  spawnSubagent(agentId: string, task)
  fetchSubagentRuns(agentId: string)
  cancelSubagentRun(runId: string)
  ```

- [ ] **4.1.7** Add memory search methods (OpenClaw) ← depends on: [3.7.4.2]
  - **What:** Method for semantic memory search
  - **Why:** MemorySearchInput component needs to search agent memories. Returns ranked results with source references.
  - **Context:** Uses hybrid search (vector + FTS) on backend.
  ```typescript
  searchAgentMemory(agentId: string, query: string, limit?: number)
  ```

- [ ] **4.1.8** Add execution approval methods (OpenClaw) ← depends on: [3.6.5.1]
  - **What:** Methods for managing command allowlist
  - **Why:** AllowlistManager component needs to display, add, and remove allowlist patterns. Shows usage stats for each pattern.
  - **Context:** Patterns can be exact commands or globs. Adding a pattern auto-approves matching commands.
  ```typescript
  fetchAgentAllowlist(agentId: string)
  addAllowlistPattern(agentId: string, pattern: string)
  removeAllowlistPattern(agentId: string, patternId: string)
  ```

### 4.2 Update Frontend Components (Already Created)

> **Why:** Components exist with mock data. This phase connects them to real APIs, making the UI functional.

- [x] **4.2.1** Connect `AgentPersonalityEditor.vue` to API ← depends on: [4.1.1] — ✅ superseded by AgentIdentityFiles.vue two-panel editor for all 8 identity files
  - **What:** Wire personality editor to backend
  - **Why:** Users need to edit and save agent personality. Currently uses mock data.
  - **Context:** Should show loading state while saving, success toast on save, error handling for failures.
  - Replace mock save with `updateAgentPersonality()`
  - Add error handling and success feedback

- [x] **4.2.2** Connect `AgentInstructionsEditor.vue` to API ← depends on: [4.1.1] — ✅ superseded by AgentIdentityFiles.vue two-panel editor
  - **What:** Wire instructions editor to backend
  - **Why:** Users need to edit and save agent instructions. Currently uses mock data.
  - **Context:** Same UX patterns as personality editor - loading, success, error states.
  - Replace mock save with `updateAgentInstructions()`
  - Add error handling and success feedback

- [x] **4.2.3** Connect `AgentCapabilities.vue` to API ← depends on: [4.1.2] — ✅ AgentCapabilities.vue with real tool toggles via AgentPermissionController
  - **What:** Wire capabilities toggles to backend
  - **Why:** Users need to enable/disable tools and set approval requirements. Currently uses mock data.
  - **Context:** Should fetch system capabilities list and agent's current assignments. Save should bulk update.
  - Fetch real capabilities list
  - Save capability changes and notes

- [x] **4.2.4** Connect `AgentMemoryView.vue` to API ← depends on: [4.1.4, 4.1.5] — ✅ superseded: MEMORY.md managed via identity file editor
  - **What:** Wire memory and session display to backend
  - **Why:** Users need to view sessions, messages, and memories. Also need to add memories and start new sessions.
  - **Context:** Session list should be paginated. Memory add/delete should update list in real-time.
  - Fetch real session data
  - Fetch real memory entries
  - Implement new session creation
  - Implement memory add/delete

- [x] **4.2.5** Connect `AgentSettingsPanel.vue` to API ← depends on: [4.1.3] — ✅ AgentSettingsPanel.vue connected with real behavior mode, brain selector, delete
  - **What:** Wire settings form to backend
  - **Why:** Users need to configure agent behavior, cost limits, and reset policies.
  - **Context:** Some actions (reset, delete) need confirmation dialogs. Pause/resume should update status badge.
  - Fetch real settings
  - Save settings changes
  - Implement reset/pause/delete actions

- [x] **4.2.6** Connect `AgentIdentityCard.vue` to real data ← depends on: [4.1.1] — ✅ agent identity data fetched from real API
  - **What:** Wire identity display to backend
  - **Why:** Agent card should show real name, emoji, type, and stats (sessions, messages, cost).
  - **Context:** Stats may need separate endpoint or be included in config response.
  - Ensure identity is fetched from API
  - Display real stats

- [ ] **4.2.7** Create `AllowlistManager.vue` component (OpenClaw) ← depends on: [4.1.8]
  - **What:** New component for managing command allowlist patterns
  - **Why:** Users need to pre-approve commands to reduce approval prompts. Should show which patterns are used and when.
  - **Context:** Pattern input should support glob syntax hints. Usage stats help users clean up stale patterns.
  - List allowlist patterns with usage stats
  - Add/remove patterns
  - Show last used command for each pattern

- [ ] **4.2.8** Update `AgentSettingsPanel.vue` with OpenClaw settings ← depends on: [4.1.3]
  - **What:** Add new settings fields for OpenClaw features
  - **Why:** Users need to configure security mode, ask mode, context reserves, and pruning TTL.
  - **Context:** Use dropdowns for enums (security_mode, ask_mode). Number inputs for token counts. Toggle for auto_allow_skills.
  - Security mode selector (deny/allowlist/full)
  - Ask mode selector (off/on-miss/always)
  - Reserve tokens configuration
  - Pruning TTL configuration
  - Auto-allow skills toggle

- [ ] **4.2.9** Create `MemorySearchInput.vue` component (OpenClaw) ← depends on: [4.1.7]
  - **What:** New component for semantic memory search
  - **Why:** Users and agents need to search memories by meaning, not just keywords. Enables finding relevant context quickly.
  - **Context:** Search input with debounced API calls. Results show matched chunk with source reference (click to view full entry).
  - Search input with results display
  - Show matched chunks with source references
  - Link to full memory entries

### 4.3 Update Agent/Show.vue Page

> **Why:** The main agent page needs to coordinate all components with real data. Replace mock `fetchData()` with actual API calls.

- [x] **4.3.1** Replace mock `fetchData()` with real API calls ← depends on: [4.2.1-4.2.6] — ✅ Agent/Show.vue fetches real data from API (not mocks)
  - **What:** Load all agent data from API on page mount
  - **Why:** Page currently shows mock data. Need to fetch real configuration, capabilities, settings, session, and memories.
  - **Context:** Consider parallel fetching for better performance. Handle loading and error states for each section.
  - Fetch agent configuration
  - Fetch capabilities
  - Fetch settings
  - Fetch current session
  - Fetch memories

- [x] **4.3.2** Implement all event handlers with real API calls — ✅ Agent/Show.vue uses real API for all operations
  - **What:** Wire all component events to API methods
  - **Why:** User actions (save, delete, etc.) must persist to backend. Currently many handlers just log or show toasts.
  - **Context:** Destructive actions (reset, delete) need confirmation dialogs. Success/error feedback via toasts.
  - `savePersonality()` → API call
  - `saveInstructions()` → API call
  - `saveCapabilityNotes()` → API call
  - `startNewSession()` → API call
  - `addMemoryEntry()` → API call
  - `deleteMemoryEntry()` → API call
  - `updateSettings()` → API call
  - `resetAgentMemory()` → API call with confirmation
  - `togglePause()` → API call
  - `deleteAgent()` → API call with confirmation

---

## Phase 5: Agent Control Actions

> **Why:** Agents need operational controls beyond configuration. Users must be able to pause, resume, stop, and delete agents. These are critical safety controls.

### 5.1 Agent Status Management

- [x] **5.1.1** Add status control endpoints to `UserController` ← depends on: [2.4.1] — ✅ AgentController handles status (PATCH /api/agents/{id})
  - **What:** API endpoints for controlling agent operational status
  - **Why:** Users need to pause agents (stop processing), resume them, or hard-stop current work. Essential for managing runaway or misbehaving agents.
  - **Context:** Pause prevents new tasks from starting. Stop cancels the currently running task.
  - `POST /api/agents/{id}/pause` - pause agent
  - `POST /api/agents/{id}/resume` - resume agent
  - `POST /api/agents/{id}/stop` - stop agent (cancel current task)

- [x] **5.1.2** Implement pause/resume logic — ✅ agent status management (idle/working/sleeping) with AgentStatusUpdated broadcast event
  - **What:** Business logic for status transitions and task cancellation
  - **Why:** Status changes must update the database and notify connected clients. Stopping requires cancelling the active task.
  - **Context:** WebSocket broadcast ensures all open tabs see status change immediately.
  - Update agent status to 'paused'/'working'/'idle'
  - Cancel any running tasks if stopping
  - Broadcast status change via WebSocket

### 5.2 Agent Deletion

- [x] **5.2.1** Add agent deletion endpoint ← depends on: [2.4.1] — ✅ DELETE /api/agents/{id} exists in routes + AgentController
  - **What:** Soft-delete endpoint for removing an agent
  - **Why:** Users need to delete agents they no longer need. Soft delete allows recovery if deletion was accidental.
  - **Context:** Must clean up related data: archive sessions, clear/archive memories, remove from any channels.
  - `DELETE /api/agents/{id}` - soft delete agent
  - Archive all sessions
  - Clear memories (or archive)
  - Remove from channels

- [ ] **5.2.2** Add confirmation dialog in frontend
  - **What:** Dangerous action confirmation with name typing
  - **Why:** Deletion is destructive. Requiring users to type the agent name prevents accidental deletion.
  - **Context:** Similar to GitHub's repository deletion pattern. Should show what will be deleted (sessions, memories, etc.).
  - Show warning about data loss
  - Require typing agent name to confirm

---

## Phase 6: Database Seeding

> **Why:** Seeders provide initial data for development and testing. Capabilities must be seeded before agents can be configured. Agent seeders create demo agents for testing the system.

### 6.1 Create Seeders

- [ ] **6.1.1** Create `CapabilitySeeder` ← depends on: [1.4.1]
  - **What:** Seed the capabilities table with default tools
  - **Why:** Capabilities are system-defined, not user-created. This seeder creates the tools that agents can be assigned.
  - **Context:** Each capability has default enabled/approval settings. Tool kind (from OpenClaw) should also be set.
  - Seed 6 default capabilities:
    - Code execution (enabled, no approval, kind: execute)
    - File operations (enabled, no approval, kind: edit)
    - Git operations (enabled, no approval, kind: execute)
    - API requests (enabled, no approval, kind: fetch)
    - Database access (enabled, requires approval, kind: execute)
    - Production deployment (disabled, requires approval, kind: execute)

- [ ] **6.1.2** Create `AgentConfigurationSeeder` ← depends on: [6.1.1]
  - **What:** Create agent configurations for demo/test agents
  - **Why:** Developers need agents to test with. Creates pre-configured agents with meaningful personalities and instructions.
  - **Context:** Existing seeded agents (Atlas, Echo, Nova, etc.) need configurations. Each agent type should have appropriate capabilities.
  - Create configurations for existing seeded agents (Atlas, Echo, Nova, Pixel, Logic, Scout)
  - Set default personality and instructions for each type
  - Assign appropriate capabilities

- [ ] **6.1.3** Create `AgentSettingsSeeder` ← depends on: [6.1.2]
  - **What:** Create default settings for each agent
  - **Why:** Agents need settings to operate. This seeder creates sensible defaults for development.
  - **Context:** Supervised mode is safest for development. Include OpenClaw settings with reasonable defaults.
  - Create default settings for each agent
  - Behavior mode: supervised
  - Cost limit: 100
  - Reset policy: daily at 4am
  - Security mode: allowlist (OpenClaw default)
  - Ask mode: on-miss (OpenClaw default)

### 6.2 Run Seeders

- [ ] **6.2.1** Update `DatabaseSeeder.php` to include new seeders ← depends on: [6.1.1-6.1.3]
  - **What:** Register new seeders in the main seeder
  - **Why:** Running `php artisan db:seed` should execute all seeders in correct order.
  - **Context:** Order matters: Capabilities → AgentConfiguration → AgentSettings (due to foreign keys).

- [ ] **6.2.2** Run `php artisan db:seed` ← depends on: [6.2.1]
  - **What:** Execute all seeders to populate database
  - **Why:** Creates development data needed to test the system.
  - **Context:** Can use `--class` to run specific seeders. Fresh install should run all.

---

## Phase 7: Testing

> **Why:** Tests ensure the system works correctly and catches regressions. Backend tests verify API contracts and business logic. Frontend tests verify user interactions work as expected.

### 7.1 Backend Tests

- [x] **7.1.1** Create `AgentConfigurationTest` feature test ← depends on: [3.1.1] — ✅ AgentControllerTest exists in tests/Feature/
  - **What:** Test agent configuration API endpoints
  - **Why:** Configuration is core functionality. Tests ensure CRUD works, authorization prevents unauthorized access, and validation rejects bad data.
  - **Context:** Use Laravel's testing helpers. Test as authenticated user and verify cannot access other users' agents.
  - Test CRUD operations
  - Test authorization (only owners can edit)
  - Test validation

- [x] **7.1.2** Create `AgentCapabilityTest` feature test ← depends on: [3.2.1] — ✅ AgentPermissionControllerTest + AgentPermissionServiceTest + ToolRegistryTest exist
  - **What:** Test capability management API
  - **Why:** Capabilities control what tools agents can use. Tests ensure assignment works and bulk updates don't break relationships.
  - **Context:** Test both individual capability toggle and bulk update. Verify pivot table data (enabled, requires_approval) persists correctly.
  - Test capability assignment
  - Test bulk updates

- [ ] **7.1.3** Create `AgentSettingsTest` feature test ← depends on: [3.3.1]
  - **What:** Test settings API endpoints
  - **Why:** Settings control agent behavior. Tests ensure all settings save correctly and enum validation rejects invalid values.
  - **Context:** Include tests for OpenClaw settings (security_mode, ask_mode). Verify JSON fields (reset_policy) serialize/deserialize correctly.
  - Test settings updates
  - Test enum validation

- [ ] **7.1.4** Create `AgentSessionTest` feature test ← depends on: [3.4.1]
  - **What:** Test session management API
  - **Why:** Sessions are the conversation context. Tests ensure creation, archival, and message retrieval work correctly.
  - **Context:** Test pagination for message retrieval. Verify new session creation archives the old one.
  - Test session creation
  - Test session archival
  - Test message retrieval

- [ ] **7.1.5** Create `AgentMemoryTest` feature test ← depends on: [3.5.1]
  - **What:** Test memory management API
  - **Why:** Memories are persistent agent knowledge. Tests ensure CRUD and reset work correctly.
  - **Context:** Reset is destructive - test that it clears all memories. Test category enum validation.
  - Test memory CRUD
  - Test memory reset

### 7.2 Frontend Tests

> **Why:** Frontend tests ensure the UI works correctly. Component tests verify individual components, integration tests verify they work together.

- [ ] **7.2.1** Test Agent/Show.vue renders all tabs
  - **What:** Verify the main agent page renders all 7 tabs correctly
  - **Why:** Page structure is foundational. If tabs don't render, nothing else works.
  - **Context:** Should test tab switching works and correct content appears for each tab.

- [ ] **7.2.2** Test personality editor save/preview
  - **What:** Test markdown editing and preview functionality
  - **Why:** Personality editor is a primary user interaction. Save must work, preview must render markdown.
  - **Context:** Test markdown rendering, save button calls API, success/error feedback appears.

- [ ] **7.2.3** Test instructions editor save/preview
  - **What:** Test instructions editing functionality
  - **Why:** Same importance as personality editor. Instructions define agent behavior.
  - **Context:** Same test patterns as personality editor.

- [ ] **7.2.4** Test capabilities toggle
  - **What:** Test capability enable/disable and approval toggle
  - **Why:** Capability toggles control tool access. Must work reliably.
  - **Context:** Test toggle state changes, save persists changes, list refreshes correctly.

- [ ] **7.2.5** Test memory add/delete
  - **What:** Test adding and deleting memory entries
  - **Why:** Memory management is important for agent knowledge. Add/delete must work correctly.
  - **Context:** Test form submission, new entry appears in list, delete removes entry.

- [ ] **7.2.6** Test settings changes
  - **What:** Test all settings form fields and save
  - **Why:** Settings affect agent behavior. All fields must persist correctly.
  - **Context:** Test each setting type: enums, numbers, JSON (reset_policy), toggles.

- [ ] **7.2.7** Test dark mode on all components
  - **What:** Verify all components display correctly in dark mode
  - **Why:** Dark mode is expected feature. Broken dark mode is poor UX.
  - **Context:** Use Tailwind's dark: prefix. Verify text contrast, background colors, borders.

---

## Phase 8: Future Enhancements (Post-MVP)

> **Why:** These features are valuable but not required for MVP. They enhance the system with advanced capabilities like auto-compaction, subagent spawning, skills, and webhooks.

### 8.1 Vector Memory Search
- [x] **8.1.1** ~~Install pgvector extension~~ → Moved to Phase 1.5.1.1
- [x] **8.1.2** ~~Create `memory_embeddings` table~~ → Moved to Phase 1.5.1.2
- [x] **8.1.3** ~~Implement embedding generation service~~ → Moved to Phase 3.7.1.1
- [x] **8.1.4** ~~Create semantic search endpoint~~ → Moved to Phase 3.7.4.2
- [x] **8.1.5** ~~Add search UI to memory view~~ → Moved to Phase 4.2.9

### 8.2 Context Management

- [x] **8.2.1** ~~Implement context pruning service~~ → Moved to Phase 3.6.3.1

- [ ] **8.2.2** Implement auto-compaction
  - **What:** Automatic context compression when approaching token limits
  - **Why:** Without compaction, agents hit context limits and can't continue. Auto-compaction summarizes old context to make room for new.
  - **Context:** OpenClaw compacts by summarizing older messages. Keeps recent tokens intact. Triggered by ContextWindowGuard.

- [x] **8.2.3** ~~Add pre-compaction memory flush~~ → Moved to Phase 3.6.2.1

- [ ] **8.2.4** Add compaction history view
  - **What:** UI to view past compaction events and their summaries
  - **Why:** Users may want to see what was compacted and when. Helps understand what context was lost.
  - **Context:** Store compaction summaries in session_messages with type 'compaction'. Display in a timeline view.

### 8.3 Subagent Spawning UI

> **Why:** Backend supports subagents (Phase 3.5.3.4) but needs frontend UI. These components let users spawn agents and monitor their work.

- [ ] **8.3.1** Create spawn dialog component
  - **What:** Modal dialog for spawning a subagent
  - **Why:** Users need to select which agent to spawn and provide a task description.
  - **Context:** Should show available agents (based on spawn permissions) and task input field.

- [ ] **8.3.2** Add spawn button to agent page
  - **What:** Button in agent page to trigger spawn dialog
  - **Why:** Entry point for spawning subagents from the current agent.
  - **Context:** Button should be disabled if agent has no spawn permissions.

- [ ] **8.3.3** Show running subagents list
  - **What:** Component showing currently running subagent tasks
  - **Why:** Users need to monitor spawned agents - see progress, status, and cancel if needed.
  - **Context:** Real-time updates via WebSocket. Show status badge (running, success, error).

- [ ] **8.3.4** Add subagent result announcement
  - **What:** Notification when a subagent completes its task
  - **Why:** Users need to know when spawned work is done. Announce results in the parent agent's chat.
  - **Context:** WebSocket notification triggers toast and chat announcement.

### 8.4 Skills System

> **Why:** Skills are reusable, composable agent capabilities. OpenClaw has a sophisticated skill system with tiering. This enables "slash commands" and skill-based agent composition.

- [ ] **8.4.1** Create skills database tables
  - **What:** Tables for skill definitions, versions, and agent-skill assignments
  - **Why:** Skills need persistent storage. Versioning enables skill updates without breaking existing agents.
  - **Context:** OpenClaw has skill tiers: bundled (system), managed (installed), workspace (custom).

- [ ] **8.4.2** Create skills management UI
  - **What:** UI for browsing, installing, and managing skills
  - **Why:** Users need to discover available skills and assign them to agents.
  - **Context:** Similar to VS Code extension marketplace but for agent skills.

- [ ] **8.4.3** Implement skill tiering (workspace > managed > bundled)
  - **What:** Priority system for skill resolution
  - **Why:** Users may want to override bundled skills with custom versions. Tiering ensures custom skills take precedence.
  - **Context:** OpenClaw resolution: workspace (highest) → managed → bundled (lowest).

- [ ] **8.4.4** Add skill invocation tracking
  - **What:** Track which skills are used and how often
  - **Why:** Usage analytics help users understand agent behavior. Useful for optimization and debugging.
  - **Context:** Store invocation counts, last used timestamp, average execution time.

### 8.5 Webhooks & External Integrations

> **Why:** Agents need to be triggered by external events (GitHub commits, Slack messages, etc.). Webhooks enable event-driven agent activation.

- [x] **8.5.1** Already implemented basic UI (Integrations.vue) — ✅ Integrations.vue exists with Telegram and Plausible configured
  - **What:** UI skeleton for integrations exists
  - **Why:** Placeholder for webhook management interface.

- [ ] **8.5.2** Create webhooks database table
  - **What:** Table for webhook endpoint definitions
  - **Why:** Store webhook URLs, secrets, target agents, and event filters.
  - **Context:** Each webhook has a unique URL, secret for verification, and maps to an agent + action.

- [ ] **8.5.3** Implement webhook processing logic
  - **What:** Controller and service for receiving and processing webhooks
  - **Why:** Incoming webhooks need to be verified (signature), parsed, and routed to the appropriate agent.
  - **Context:** Support common webhook formats (GitHub, Slack, generic). Queue for async processing.

- [ ] **8.5.4** Add webhook testing UI
  - **What:** UI for testing webhook endpoints
  - **Why:** Users need to verify webhooks work before deploying. Test sends a sample payload and shows result.
  - **Context:** Similar to Stripe's webhook testing. Show recent webhook deliveries and their status.

---

## Phase 3.8: Plugin System

> **Why:** OpenClaw's plugin architecture enables extensibility without modifying core code. Plugins add tools, channels, providers, skills, and more. OpenCompany should support the same extensibility via Laravel packages.

### 3.8.1 Plugin Infrastructure

- [ ] **3.8.1.1** Create `plugins` migration
  - **What:** Table to track installed plugins and their configuration
  - **Why:** Need to know which plugins are installed, enabled, and how they're configured.
  - Fields: `id`, `name`, `version`, `description`, `author`, `enabled`, `capabilities` (JSON), `config` (JSON), `slot` (nullable enum: memory/sandbox/browser), `created_at`, `updated_at`

- [ ] **3.8.1.2** Create `Plugin` model
  - **What:** Eloquent model for plugin management
  - **Why:** Central model for plugin CRUD and capability resolution.
  - Relationships: `hasMany(PluginCapability)`
  - Scopes: `enabled()`, `withCapability($type)`, `forSlot($slot)`

- [ ] **3.8.1.3** Create `PluginRegistryService`
  - **What:** Service that discovers, validates, and registers plugins
  - **Why:** Centralized plugin lifecycle management. Handles discovery chain: config → workspace → global → bundled.
  - **Context:** Plugins are Laravel packages with service providers. The registry tracks which capabilities each plugin provides.
  - `discover()` - scan for available plugins
  - `register(Plugin $plugin)` - register plugin capabilities
  - `validateConfig(Plugin $plugin)` - validate plugin config against schema
  - `resolveSlot(string $slot)` - get the active plugin for an exclusive slot

### 3.8.2 Plugin Capabilities

- [ ] **3.8.2.1** Create capability interfaces
  - **What:** PHP interfaces for each plugin capability type
  - **Why:** Type-safe contracts that plugins must implement. Ensures consistency across all plugins.
  - **Context:** OpenClaw supports 10 capability types. Start with the most useful ones.
  ```php
  interface ProvidesTools { public function tools(): array; }
  interface ProvidesChannels { public function channels(): array; }
  interface ProvidesProviders { public function providers(): array; }
  interface ProvidesSkills { public function skills(): array; }
  interface ProvidesHooks { public function hooks(): array; }
  ```

- [ ] **3.8.2.2** Create exclusive slot system
  - **What:** Logic to enforce that only one plugin can claim each exclusive slot
  - **Why:** Some capabilities (memory backend, sandbox) can only have one active implementation.
  - **Context:** If multiple plugins claim the same slot, highest-precedence one wins.

### 3.8.3 Plugin Management API

- [ ] **3.8.3.1** Create `PluginController`
  - **What:** API endpoints for managing plugins
  - **Why:** Frontend needs to list, enable/disable, and configure plugins.
  - `GET /api/plugins` - list all plugins
  - `POST /api/plugins/{id}/enable` - enable plugin
  - `POST /api/plugins/{id}/disable` - disable plugin
  - `PUT /api/plugins/{id}/config` - update plugin config
  - `POST /api/plugins/discover` - trigger plugin discovery

- [ ] **3.8.3.2** Create plugin management UI
  - **What:** Vue component for plugin management
  - **Why:** Users need to see installed plugins, toggle them, and configure settings.
  - **Context:** Similar to VS Code extension panel. Show capabilities, slot claims, config fields.

---

## Phase 3.9: Multi-Device Support

> **Why:** OpenClaw's gateway enables agents to be accessed from any device (iOS, Android, macOS, web). A node registry tracks connected devices and routes tasks based on device capabilities. OpenCompany should support similar multi-device access.

### 3.9.1 Node Registry

- [ ] **3.9.1.1** Create `connected_devices` migration
  - **What:** Table to track connected devices/clients
  - **Why:** Need to know which devices are connected, their capabilities, and health status.
  - Fields: `id`, `user_id` (FK), `device_id` (unique string), `platform` (enum: ios/android/macos/web/desktop), `device_name`, `capabilities` (JSON), `last_heartbeat_at`, `is_online`, `metadata` (JSON), `created_at`, `updated_at`

- [ ] **3.9.1.2** Create `ConnectedDevice` model
  - **What:** Eloquent model for device management
  - **Why:** Track device state and enable capability-based routing.
  - Relationships: `belongsTo(User)`
  - Scopes: `online()`, `withCapability($cap)`, `forPlatform($platform)`
  - Methods: `heartbeat()`, `markOffline()`, `hasCapability($cap)`

- [ ] **3.9.1.3** Create WebSocket heartbeat system
  - **What:** Periodic heartbeat via Reverb to track device health
  - **Why:** Need to detect disconnected devices. Devices send heartbeat every 30 seconds.
  - **Context:** Uses existing Laravel Reverb WebSocket. Add presence channel for device tracking.
  ```php
  // routes/channels.php
  Broadcast::channel('devices.{userId}', function ($user, $userId) {
      return $user->id === $userId ? [
          'id' => $user->id,
          'name' => $user->name,
          'device' => request()->header('X-Device-Id'),
      ] : null;
  });
  ```

### 3.9.2 Device-Aware Routing

- [ ] **3.9.2.1** Create `DeviceRouter` service
  - **What:** Service that routes notifications and tasks to the right device
  - **Why:** Some tasks need specific device capabilities (e.g., browser tasks → desktop device).
  - `routeNotification($user, $notification)` - route to best device
  - `routeTask($user, $task)` - route to device with required capabilities
  - `broadcastToAll($user, $event)` - broadcast to all connected devices

- [ ] **3.9.2.2** Create device status dashboard component
  - **What:** Vue component showing connected devices and their status
  - **Why:** Users need to see which devices are connected, online, and their capabilities.
  - Real-time status via WebSocket
  - Show platform icon, device name, last activity, capabilities

### 3.9.3 Cross-Platform Sync

- [ ] **3.9.3.1** Create sync event system
  - **What:** Broadcast state changes to all connected devices
  - **Why:** Agent state (messages, tasks, approvals) must be consistent across devices.
  - **Context:** Use existing Reverb channels. Add sync events for: new messages, task updates, approval requests, agent status changes.

---

## Phase 3.10: Cron & Scheduled Tasks

> **Why:** OpenClaw supports cron-based autonomous agent execution. Agents can perform tasks on a schedule without human triggers — daily summaries, periodic monitoring, scheduled reports. OpenCompany should support the same autonomous agent capabilities.

### 3.10.1 Cron Job Infrastructure

- [ ] **3.10.1.1** Create `agent_cron_jobs` migration
  - **What:** Table for scheduled agent tasks
  - **Why:** Store cron job definitions with schedule, task prompt, and delivery configuration.
  - Fields: `id`, `agent_id` (FK to users), `name`, `schedule` (cron expression), `task` (TEXT - prompt), `delivery_mode` (enum: announce/none/post), `target_channel_id` (nullable FK), `enabled`, `one_shot`, `last_run_at`, `last_result` (JSON), `created_at`, `updated_at`

- [ ] **3.10.1.2** Create `AgentCronJob` model
  - **What:** Eloquent model for cron job management
  - **Why:** Central model for cron CRUD and execution tracking.
  - Relationships: `belongsTo(User, 'agent_id')`, `belongsTo(Channel, 'target_channel_id')`
  - Scopes: `enabled()`, `forAgent($agentId)`, `dueNow()`
  - Methods: `isDue()`, `markRan()`, `shouldAutoDelete()`

- [ ] **3.10.1.3** Create `ExecuteAgentCronJob` queue job
  - **What:** Job that executes a scheduled agent task
  - **Why:** Cron jobs should run asynchronously on queue workers, with isolated sessions.
  - **Context:** Creates an isolated session (separate from conversation context) so cron execution doesn't pollute chat history.
  ```php
  class ExecuteAgentCronJob implements ShouldQueue
  {
      public function handle(): void
      {
          // Create isolated session for cron execution
          $session = AgentSession::create([
              'session_key' => "cron:{$this->cronJob->id}:" . now()->timestamp,
              'status' => 'active',
          ]);

          $agent = OpenCompanyAgent::for($this->cronJob->agent);
          $response = $agent->prompt($this->cronJob->task);

          // Deliver based on mode
          match ($this->cronJob->delivery_mode) {
              'announce' => $this->announceResult($response),
              'post' => $this->postToChannel($response),
              'none' => null,
          };

          // Auto-delete one-shot jobs
          if ($this->cronJob->one_shot) {
              $this->cronJob->delete();
          }

          $this->cronJob->update([
              'last_run_at' => now(),
              'last_result' => ['response' => (string) $response],
          ]);
      }
  }
  ```

### 3.10.2 Scheduler Integration

- [ ] **3.10.2.1** Register cron jobs with Laravel scheduler
  - **What:** Load agent cron jobs from DB and register with `Schedule`
  - **Why:** Laravel's scheduler handles cron expression evaluation, overlap prevention, and single-server execution.
  ```php
  // app/Console/Kernel.php
  protected function schedule(Schedule $schedule): void
  {
      AgentCronJob::where('enabled', true)->each(function ($job) use ($schedule) {
          $schedule->job(new ExecuteAgentCronJob($job))
              ->cron($job->schedule)
              ->withoutOverlapping()
              ->onOneServer();
      });
  }
  ```

- [ ] **3.10.2.2** Create cron job execution history migration
  - **What:** Table to track cron job execution history
  - **Why:** Need audit trail for scheduled executions. Track success/failure, runtime, token usage.
  - Fields: `id`, `cron_job_id` (FK), `status` (enum: success/error/timeout), `started_at`, `completed_at`, `token_count`, `result` (JSON), `error` (TEXT nullable)

### 3.10.3 Cron Management API & UI

- [ ] **3.10.3.1** Create `AgentCronJobController`
  - **What:** API endpoints for managing agent cron jobs
  - **Why:** Frontend needs CRUD for cron jobs plus manual trigger and history view.
  - `GET /api/agents/{id}/cron-jobs` - list cron jobs
  - `POST /api/agents/{id}/cron-jobs` - create cron job
  - `PUT /api/cron-jobs/{id}` - update cron job
  - `DELETE /api/cron-jobs/{id}` - delete cron job
  - `POST /api/cron-jobs/{id}/trigger` - manual trigger
  - `GET /api/cron-jobs/{id}/history` - execution history

- [ ] **3.10.3.2** Create cron management Vue component
  - **What:** UI for managing scheduled agent tasks
  - **Why:** Users need to create, edit, enable/disable, and monitor cron jobs.
  - **Context:** Include cron expression helper (common presets: daily, hourly, weekly, etc.), delivery mode selector, and execution history log.

---

### 3.11 Heartbeat System

- [ ] **3.11.1** Add heartbeat fields to `agent_configs` migration ← depends on: [1.1.3]
  - **What:** Migration adding `heartbeat_prompt`, `heartbeat_enabled`, `heartbeat_interval`, `heartbeat_active_start`, `heartbeat_active_end`, `heartbeat_timezone` to `agent_configs` table
  - **Why:** Agents need configurable heartbeat settings. OpenClaw stores this in HEARTBEAT.md; we use DB fields for admin UI editability.

- [ ] **3.11.2** Create `HeartbeatJob` ← depends on: [3.11.1, 3.1.1]
  - **What:** Queue job that runs an agent's heartbeat check: loads prompt, calls AI SDK, posts results to channel (or skips if ack-only)
  - **Why:** This is the core heartbeat execution. Adapted from OpenClaw's heartbeat-runner.ts.
  - Active hours gating via `between()` check
  - Ack suppression for responses under 30 chars or containing `HEARTBEAT_OK`

- [ ] **3.11.3** Wire scheduler to dispatch heartbeats ← depends on: [3.11.2]
  - **What:** Add scheduler entry in `app/Console/Kernel.php` that queries active agents with heartbeat enabled, dispatches `HeartbeatJob` for each
  - **Why:** Replaces OpenClaw's Node.js setInterval with Laravel's built-in scheduler.
  - Default interval: every 30 minutes (configurable per agent via `heartbeat_interval`)

- [ ] **3.11.4** Add heartbeat configuration to agent admin UI ← depends on: [3.11.1, 4.1.x]
  - **What:** Add heartbeat settings section to Agent/Show.vue Settings tab: enable toggle, prompt textarea, interval select, active hours inputs
  - **Why:** Admins need to configure heartbeat behavior per agent without touching the database directly.

---

### 3.12 Agent Execution Loop (Core Agent Brain)

> **This is the most critical phase.** Without this, agents cannot process messages or execute tasks. All other agent features (memory, heartbeat, sub-agents) depend on this.

- [x] **3.12.1** Create `AgentPromptBuilder` service ← depends on: [2.1.x, 3.1.1] — ✅ superseded: system prompt assembly built into OpenCompanyAgent using Document-based identity files
  - **What:** Service that assembles the system prompt from agent config fields (personality, instructions), user context, tool documentation, and memory. Follows OpenClaw's injection order: identity → personality → user → instructions → tools → memory.
  - **Why:** Clean separation of prompt assembly from execution. Handles sub-agent restrictions (only instructions, no personality/user context).

- [x] **3.12.2** Create `AgentToolExecutor` service ← depends on: [3.5.1.x] — ✅ superseded: tool resolution handled by ToolRegistry.getToolsForAgent() + AgentPermissionService
  - **What:** Service that resolves available tools for an agent (based on capabilities/permissions), executes tool calls from LLM responses, and returns results.
  - **Why:** Adapted from OpenClaw's tool execution loop. Handles the tool call → result → feed back cycle.
  - Tool resolution follows permission stack: profile → allow/deny → agent-specific restrictions

- [x] **3.12.3** Create `ProcessAgentMessageJob` ← depends on: [3.12.1, 3.12.2] — ✅ implemented as AgentRespondJob (core agent brain)
  - **What:** The core agent runner job. Dispatched when an agent is mentioned or receives a DM. Loads context, builds prompt, calls AI SDK with streaming, processes tool calls, stores response, broadcasts via Reverb.
  - **Why:** This is the "agent brain" — the single most important piece of the system. Replaces OpenClaw's `runEmbeddedPiAgent()`.
  - Queue: `agent-{id}` (serialized per agent to prevent race conditions)
  - Includes: conversation history loading, streaming response broadcast, post-processing (memory indexing, compaction check)

- [x] **3.12.4** Wire message controller to dispatch agent runs ← depends on: [3.12.3] — ✅ MessageController dispatches AgentRespondJob on @mention and DM
  - **What:** Update `MessageController::store()` to detect @mentions of agents and dispatch `ProcessAgentMessageJob`. Also handle DM channels where the other participant is an agent.
  - **Why:** This is the trigger that makes agents respond to messages.
  - Detection: check message content for @mentions matching agent names, or check if channel is a DM with an agent member

- [x] **3.12.5** Add response streaming via Reverb ← depends on: [3.12.3] — ✅ streaming via Reverb WebSocket (MessageSent, AgentStatusUpdated, TypingIndicator events)
  - **What:** Create `AgentTyping` broadcast event for partial response streaming. Clients receive chunks as the agent generates them, showing real-time typing.
  - **Why:** UX requirement — users should see agents "typing" in real-time, not wait for complete responses.
  - Broadcast on channel: `channel.{id}`
  - Event data: `{ agentId, chunk, isComplete }`

- [ ] **3.12.6** Add model failover support ← depends on: [3.12.3]
  - **What:** Configure primary + fallback models per agent in `agent_configs`. `ProcessAgentMessageJob` tries primary first, falls back to alternatives on failure.
  - **Why:** Adapted from OpenClaw's failover chain. Ensures agents stay operational if a provider has an outage.
  - Config: `model_primary`, `model_fallbacks` (JSON array) on agent_configs

---

## Verification Checklist

### Functional Verification
- [x] Navigate to `/agent/{id}` - page loads without errors — ✅ Agent/Show.vue with Inertia route
- [x] All 7 tabs render correctly (Overview, Personality, Instructions, Capabilities, Memory, Activity, Settings) — ✅ tabs: Overview, Tasks, Identity, Capabilities, Activity, Settings
- [ ] Edit personality → saves to database → persists on refresh
- [ ] Edit instructions → saves to database → persists on refresh
- [ ] Toggle capability → saves to database → persists on refresh
- [ ] Add memory entry → appears in list → persists on refresh
- [ ] Delete memory entry → removed from list
- [ ] Change settings → saves to database → persists on refresh
- [ ] Start new session → creates new session → clears context
- [ ] Pause agent → status changes → agent stops working
- [ ] Resume agent → status changes → agent can work again

### UI Verification
- [ ] Dark mode works on all components
- [ ] Loading states show skeleton placeholders
- [ ] Error states show appropriate messages
- [ ] Mobile responsive layout works
- [ ] Markdown preview renders correctly
- [ ] Context usage progress bar updates

### Data Integrity
- [ ] Agent config belongs to correct user
- [ ] Session messages ordered by timestamp
- [ ] Memory entries have correct categories
- [ ] Settings have valid enum values

### OpenClaw Features Verification
- [ ] Pre-compaction flush runs before reaching reserve threshold
- [ ] Tool kinds correctly inferred and affect approval logic
- [ ] Allowlist patterns matched and tracked (last_used_at updates)
- [ ] Session pruning triggers after TTL expires
- [ ] NO_REPLY messages suppressed from UI
- [ ] Hybrid search returns relevant results (vector + FTS)
- [ ] Embedding cache prevents duplicate API calls
- [ ] Reserve tokens enforced during compaction
- [ ] Security modes work correctly (deny/allowlist/full)
- [ ] Ask modes work correctly (off/on-miss/always)

---

## File Summary

### Packages to Install
```bash
# Required
composer require laravel/ai

# Optional
composer require laravel/mcp                   # MCP server for external AI clients
composer require laravel/horizon               # Queue monitoring dashboard
```

### Migrations to Create (17 files)
```
database/migrations/
├── xxxx_create_agent_configurations_table.php
├── xxxx_create_capabilities_table.php
├── xxxx_create_agent_capabilities_table.php
├── xxxx_create_agent_settings_table.php       # Includes OpenClaw fields
├── xxxx_create_agent_sessions_table.php       # Includes OpenClaw fields
├── xxxx_create_agent_session_messages_table.php  # Includes OpenClaw fields
├── xxxx_create_agent_memories_table.php
├── xxxx_create_agent_memory_daily_logs_table.php
├── xxxx_create_subagent_spawn_permissions_table.php
├── xxxx_create_subagent_runs_table.php
├── xxxx_create_agent_tool_allowlist_table.php    # OpenClaw
├── xxxx_create_memory_chunks_table.php           # OpenClaw
├── xxxx_create_embedding_cache_table.php         # OpenClaw
├── xxxx_create_plugins_table.php                  # Plugin system
├── xxxx_create_connected_devices_table.php        # Multi-device
├── xxxx_create_agent_cron_jobs_table.php           # Cron system
└── xxxx_create_cron_job_history_table.php          # Cron history
```

### Models to Create (17 files)
```
app/Models/
├── AgentConfiguration.php
├── Capability.php
├── AgentCapability.php
├── AgentSettings.php
├── AgentSession.php
├── AgentSessionMessage.php
├── AgentMemory.php
├── AgentMemoryDailyLog.php
├── SubagentSpawnPermission.php
├── SubagentRun.php
├── AgentToolAllowlist.php       # OpenClaw
├── MemoryChunk.php              # OpenClaw
├── EmbeddingCache.php           # OpenClaw
├── MemoryCollection.php            # QMD collections
├── Plugin.php                  # Plugin system
├── ConnectedDevice.php         # Multi-device
├── AgentCronJob.php            # Cron system
└── CronJobHistory.php          # Cron history
```

### Controllers to Create (12 files)
```
app/Http/Controllers/Api/
├── AgentConfigurationController.php
├── AgentCapabilityController.php
├── CapabilityController.php
├── AgentSettingsController.php
├── AgentSessionController.php
├── AgentMemoryController.php
├── SubagentController.php
├── MemorySearchController.php      # OpenClaw
├── MemoryCollectionController.php  # QMD collection management
├── AllowlistController.php         # OpenClaw
├── PluginController.php           # Plugin system
├── ConnectedDeviceController.php  # Multi-device
└── AgentCronJobController.php     # Cron system
```

### Agent + Tools to Create
```
app/Agents/
├── OpenCompanyAgent.php            # Single dynamic agent class
├── DynamicProviderResolver.php     # Resolves provider from IntegrationSetting
├── ToolRegistry.php                # Maps DB capabilities to tool classes
└── Tools/
    ├── Internal/                   # Workspace tools
    │   ├── SearchDocuments.php
    │   ├── ReadDocument.php
    │   ├── UpdateDocument.php
    │   ├── CreateListItem.php
    │   ├── UpdateListItem.php
    │   ├── SendMessage.php
    │   ├── CreateTaskStep.php
    │   ├── CreateApproval.php
    │   └── QueryDataTable.php
    ├── External/                   # SDK built-in wrappers
    │   ├── WebSearch.php
    │   └── WebFetch.php
    └── Memory/                     # Memory tools
        ├── SaveMemory.php
        └── RecallMemory.php
```

### Agent Jobs to Create (9 files)
```
app/Jobs/Agent/
├── FetchAgentConfigJob.php
├── ExecuteAgentJob.php
├── CreateApprovalRequestJob.php
├── ExecuteApprovedActionJob.php
├── SaveSessionMessageJob.php
├── MemoryFlushJob.php            # OpenClaw
├── PruneSessionJob.php           # OpenClaw
└── CheckMemoryFlushJob.php       # OpenClaw
```

### Agent Services to Create (3 files)
```
app/Services/Agent/
├── AgentTaskService.php
├── AgentSessionResetService.php
└── SubagentSpawnService.php
```

### Services to Create (16 files)
```
app/Services/
├── AgentToolRegistry.php
├── AgentPromptBuilder.php          # System prompt assembly (OpenClaw workspace files mapping)
├── AgentToolExecutor.php           # Tool resolution + execution loop
├── ContextWindowGuard.php          # OpenClaw
├── MemoryFlushService.php          # OpenClaw
├── SessionPruningService.php       # OpenClaw
├── ToolKindClassifier.php          # OpenClaw
├── ExecutionApprovalService.php    # OpenClaw
├── EmbeddingService.php            # OpenClaw
├── EmbeddingCacheService.php       # OpenClaw
├── ChunkingService.php             # OpenClaw
├── MemoryIndexService.php          # OpenClaw
├── HybridMemorySearch.php          # OpenClaw
├── HybridDocumentSearch.php        # QMD-enhanced hybrid search
├── MemorySearchScopeGuard.php      # QMD scope rules
├── PluginRegistryService.php   # Plugin system
├── DeviceRouter.php            # Multi-device
└── CronExecutionService.php    # Cron system
```

### Frontend Files to Update (2 files)
```
resources/js/
├── composables/useApi.ts  (add ~30 new methods)
└── Pages/Agent/Show.vue   (replace mocks with API calls)
```

### Frontend Components to Create (3 files - OpenClaw)
```
resources/js/Components/agents/
├── AllowlistManager.vue
├── MemorySearchInput.vue
└── SecurityModeSelector.vue
```

### Seeders to Create (3 files)
```
database/seeders/
├── CapabilitySeeder.php
├── AgentConfigurationSeeder.php
└── AgentSettingsSeeder.php
```

### Jobs to Create
```
app/Jobs/
├── IndexAgentMemoryJob.php         # Index single document into memory_chunks
├── ExportSessionTranscriptJob.php  # Export session messages to markdown document
├── PeriodicReindexJob.php          # Scheduled re-index (every 5 minutes)
├── EmbeddingRefreshJob.php         # Scheduled embedding refresh (hourly)
├── ReindexAgentJob.php             # Full agent re-index on demand
├── HeartbeatJob.php                # Periodic agent heartbeat check
└── ProcessAgentMessageJob.php      # Core agent brain — message processing + tool execution
```

### Config Files to Create
```
config/
└── memory.php                      # QMD search parameters & indexing config
```

---

## Implementation Priority Order

**Day 1: Package Setup**
0. Install & configure packages (0.1.1 - 0.2.2)

**Week 1: Foundation**
1. Database migrations (1.1.1 - 1.4.3)
2. Memory search infrastructure (1.5.1 - 1.5.4) ← includes QMD collections & clamping
3. Core models (2.1.1 - 2.4.2)

**Week 2: API Layer**
4. Controllers (3.1.1 - 3.7.1)
5. Seeders (6.1.1 - 6.2.2)

**Week 3: Agent Execution Jobs**
6. AI Tools (3.5.1.1 - 3.5.1.3)
7. Agent Jobs (3.5.2.1 - 3.5.2.10)
8. Agent Services (3.5.3.1 - 3.5.3.4)
9. Queue Infrastructure (3.5.4.1 - 3.5.4.3)

**Week 4: Context Management (OpenClaw)**
10. Context Window Guard (3.6.1)
11. Pre-Compaction Memory Flush (3.6.2)
12. Session Pruning (3.6.3)
13. Tool Kind Classification (3.6.4)
14. Execution Approval System (3.6.5)

**Week 5-6: Hybrid Memory Search + QMD Features (OpenClaw)**
15. Embedding Service (3.7.1)
16. Chunking Service (3.7.2)
17. Memory Indexing (3.7.3)
18. Hybrid Search (3.7.4)
19. Collection Management (3.7.5)
20. Session Transcript Indexing (3.7.6)
21. Periodic Re-Indexing (3.7.7)
22. Scope Rules & Security (3.7.8)
23. Enhanced Search with QMD Features (3.7.9)

**Week 7: Frontend Integration**
24. useApi methods (4.1.1 - 4.1.8)
25. Component connections (4.2.1 - 4.2.9)
26. Page updates (4.3.1 - 4.3.2)

**Week 8: Heartbeat System**
27. Heartbeat migration (3.11.1)
28. HeartbeatJob (3.11.2)
29. Scheduler wiring (3.11.3)
30. Heartbeat admin UI (3.11.4)

**Week 9: Agent Brain**
31. AgentPromptBuilder service (3.12.1)
32. AgentToolExecutor service (3.12.2)
33. ProcessAgentMessageJob (3.12.3)
34. Wire message controller (3.12.4)
35. Response streaming via Reverb (3.12.5)
36. Model failover support (3.12.6)

**Week 10: Polish & Testing**
37. Agent control actions (5.1.1 - 5.2.2)
38. Testing (7.1.1 - 7.2.7)

**Post-MVP: Enhancements**
39. Subagent spawning (8.3.x)
40. Skills system (8.4.x)
41. Webhooks (8.5.x)
42. Plugin system (3.8.x)
43. Multi-device support (3.9.x)
44. Cron & scheduled tasks (3.10.x)

---

## Status Update (February 2026)

> **This section reflects what has actually been built vs. what remains from the original plan above. Many phases were implemented organically and differ from the original spec — some items were superseded, others were built differently.**

### What's Been Built (Completed)

The following are **done and working** — these can be checked off from the phases above:

#### Agent Execution Engine (supersedes Phase 3.12)
- [x] `OpenCompanyAgent` — single dynamic agent class with identity file-based system prompts
- [x] `AgentRespondJob` — core agent response lifecycle (LLM call → response → task completion)
- [x] `ExecuteAgentTaskJob` — queue job for agent task execution
- [x] `AgentResumeFromSleepJob` — wake sleeping agents
- [x] `DynamicProviderResolver` — resolves LLM provider/model from `brain` field + IntegrationSettings
- [x] `ChannelConversationLoader` — loads conversation history for agent context
- [x] `AgentChatService` — orchestrates agent chat interactions
- [x] Message controller dispatches agent runs on @mention and DM
- [x] Response streaming via Reverb WebSocket

#### Agent Tools (supersedes Phase 3.5.1)
- [x] `ToolRegistry` — maps agent permissions to tool class instances (33 tools total)
- [x] `ApprovalWrappedTool` — wraps tools that require approval
- [x] Workspace tools: `SearchDocuments`, `ManageDocument`, `CommentOnDocument`
- [x] Messaging tools: `SendChannelMessage`, `ManageMessage`, `ReadChannel`, `ListChannels`
- [x] List tools: `ManageListItem`, `QueryListItems`, `ManageListStatus`
- [x] Task tools: `CreateTaskStep`, `UpdateCurrentTask`
- [x] Table tools: `ManageTable`, `ManageTableRows`, `QueryTable`
- [x] Calendar tools: `ManageCalendarEvent`, `QueryCalendar`
- [x] Approval tools: `WaitForApproval`, `Wait`
- [x] Integration tools: `SendTelegramNotification`, Plausible suite (8 tools)
- [x] Creative tools: `CreateJpGraphChart`, `RenderSvg`
- [x] Meta tools: `GetToolInfo`

#### Agent Identity System (supersedes Phase 1.1.1, 2.1.1, 3.1.1)
- [x] Document-based identity (8 `.md` files per agent: IDENTITY, SOUL, USER, AGENTS, TOOLS, MEMORY, HEARTBEAT, BOOTSTRAP)
- [x] `AgentDocumentService` — creates/manages identity file structure per agent
- [x] Identity files API (`GET/PUT /api/agents/{id}/identity-files/{type}`)
- [x] `AgentIdentityFiles.vue` — OpenClaw-style two-panel editor for all 8 files
- [x] BOOTSTRAP.md auto-clear after first agent interaction (`bootstrapped_at` tracking)

#### Agent Permissions (supersedes Phase 1.1.2-1.1.3, 3.2.1)
- [x] `AgentPermission` model — unified scope-based permissions (tool, channel, folder)
- [x] `AgentPermissionService` — resolves enabled tools, channels, folders, integrations
- [x] `AgentPermissionController` — API for managing all permission types
- [x] UI: tool toggles, channel access, folder access, integration toggles on Agent/Show.vue

#### Agent Configuration & Settings (partial supersede of Phase 1.1.4, 3.3.1)
- [x] `behavior_mode` on User model (autonomous/supervised/strict)
- [x] `must_wait_for_approval` flag
- [x] `brain` field (provider:model format) with validation
- [x] `sleeping_until` / `sleeping_reason` for sleep/wake cycle
- [x] Settings tab in Agent/Show.vue

#### Frontend — Agent Detail Page (supersedes Phase 4)
- [x] `Agent/Show.vue` — full agent detail page with tabs: Overview, Tasks, Identity, Capabilities, Activity, Settings
- [x] Real API data (not mocks) for all sections
- [x] `AgentCapabilities.vue` — tool toggles with app grouping
- [x] `AgentSettingsPanel.vue` — behavior mode, brain selector, delete agent
- [x] Task list with step tracking

#### Core Platform (all working)
- [x] Chat with channels, DMs, threads, reactions, attachments
- [x] Documents with versioning, comments, attachments, folder tree
- [x] Lists (kanban) with custom statuses, templates, automation rules
- [x] Tasks (agent work items) with steps, lifecycle, assignment
- [x] Calendar with recurrence, attendees, iCal feeds, import
- [x] Data Tables with 10 column types, 4 view modes, bulk operations
- [x] Approvals with Telegram forwarding
- [x] Activity feed, notifications, search
- [x] Integrations system (Telegram, Plausible configured)
- [x] Auth (login, register, password reset)

### What Was Dropped / Superseded

These items from the original plan are **no longer needed**:

- ~~`agent_configurations` table~~ → superseded by Document-based identity files
- ~~`agent_settings` table~~ → superseded by fields on `users` table
- ~~`capabilities` table~~ → superseded by `agent_permissions` + `ToolRegistry`
- ~~`stats` table~~ → `StatsController` computes everything dynamically
- ~~`AgentConfiguration` model~~ → deleted (cleanup commit 33a0147)
- ~~`AgentSettings` model~~ → deleted
- ~~`Capability` model~~ → deleted
- ~~`Stat` model~~ → deleted
- ~~`AgentPersonalityEditor.vue`~~ → replaced by `AgentIdentityFiles.vue`
- ~~`AgentInstructionsEditor.vue`~~ → replaced by `AgentIdentityFiles.vue`
- ~~`AgentMemoryView.vue`~~ → replaced by MEMORY.md in identity files
- ~~`CapabilitySeeder`~~ → deleted
- ~~Phase 4.2.1-4.2.4~~ → components replaced by identity file editor

---

## Next Up: Priority Implementation Queue

> **Ordered by impact vs effort. Each item is a self-contained project.**

### N1. Sub-Agent Spawning
**Impact:** HIGH | **Effort:** MEDIUM | **Priority:** 1

The core "Robo-Company" differentiator. A manager agent spawns worker agents into temporary channels, tracks their work, aggregates results. Foundation already exists (`manager_id` column, `directReports()` relationship).

- [ ] **N1.1** Create `subagent_spawn_permissions` migration
  - Fields: `id`, `parent_agent_id` (FK), `allowed_agents` (JSON), `max_concurrent`, `auto_archive_minutes`
- [ ] **N1.2** Create `subagent_runs` migration
  - Fields: `id`, `parent_agent_id`, `child_agent_id`, `task_description`, `label`, `status` (pending/running/success/error/timeout/cancelled), `runtime_config` (JSON), `result` (JSON), `created_at`, `completed_at`
- [ ] **N1.3** Create `SubagentSpawnPermission` and `SubagentRun` models
- [ ] **N1.4** Create `SubagentSpawnService`
  - Enforce spawn permissions (allowed_agents, max_concurrent)
  - Create ephemeral channel for parent↔child communication
  - Dispatch child agent task via queue
  - Track parent-child relationship in `subagent_runs`
  - Handle timeout and cancellation
- [ ] **N1.5** Create `SpawnSubagent` agent tool
  - Allows manager agents to spawn workers via tool call
  - Parameters: child_agent_id, task_description, timeout_minutes
  - Returns run ID for tracking
- [ ] **N1.6** Create `SubagentController` API
  - `GET /api/agents/{id}/spawn-permissions` — get spawn permissions
  - `PUT /api/agents/{id}/spawn-permissions` — update permissions
  - `POST /api/agents/{id}/spawn` — spawn subagent
  - `GET /api/agents/{id}/subagent-runs` — list runs
  - `POST /api/subagent-runs/{id}/cancel` — cancel running subagent
- [ ] **N1.7** Frontend: spawn dialog, running subagents list, result announcements
  - Spawn button on agent page (disabled if no spawn permissions)
  - Real-time status updates via WebSocket
  - Result announcement in parent agent's chat
- [ ] **N1.8** Add spawn permissions UI to Agent/Show.vue Settings tab

### N2. MCP Server
**Impact:** HIGH | **Effort:** LOW-MEDIUM | **Priority:** 2

Expose OpenCompany as an MCP server so external AI tools (Claude Desktop, Cursor, VS Code Copilot) can interact with the workspace. High developer appeal and unique positioning.

- [ ] **N2.1** Install `laravel/mcp` package
  ```bash
  composer require laravel/mcp
  ```
- [ ] **N2.2** Create MCP server configuration
  - Define available resources: documents, channels, tasks, list items, agents
  - Define available tools: search_documents, create_task, send_message, create_list_item, query_table
- [ ] **N2.3** Create MCP tool implementations
  - `SearchDocuments` — search workspace documents
  - `ReadDocument` — read a specific document
  - `CreateListItem` — create kanban items
  - `SendMessage` — send messages to channels
  - `CreateTask` — create agent tasks
  - `QueryTable` — query data tables
- [ ] **N2.4** Create MCP resource providers
  - Documents resource (list, read)
  - Channels resource (list, read messages)
  - Agents resource (list, status)
  - Tasks resource (list, read)
- [ ] **N2.5** Add authentication (API token-based)
- [ ] **N2.6** Add MCP server settings to Settings page
  - Enable/disable MCP server
  - Generate/revoke API tokens
  - Show connection URL for clients

### N3. Memory & Vector Search (Hybrid)
**Impact:** HIGH | **Effort:** HIGH | **Priority:** 3

Agents currently have no semantic memory beyond plain MEMORY.md text. Adding pgvector + hybrid search enables agents to recall past conversations and learnings by meaning, not just keywords.

- [ ] **N3.1** Install pgvector extension
  ```sql
  CREATE EXTENSION IF NOT EXISTS vector;
  ```
- [ ] **N3.2** Create `memory_chunks` migration
  - Fields: `id`, `agent_id`, `source_type` (identity/memory/session), `source_id`, `document_id` (FK), `start_line`, `end_line`, `content_hash`, `text`, `embedding` VECTOR(1536)
- [ ] **N3.3** Create `embedding_cache` migration
  - Fields: `provider`, `model`, `content_hash`, `embedding` VECTOR(1536), `dims`
  - Primary key on (provider, model, content_hash)
- [ ] **N3.4** Create `EmbeddingService`
  - Generate embeddings via OpenAI text-embedding-3-small (or configured provider)
  - Batch mode for multiple texts
  - Cache layer using `embedding_cache` table
- [ ] **N3.5** Create `ChunkingService`
  - Split long texts into ~400 token chunks with 80 token overlap
  - Track start/end line numbers
  - Content hashing for change detection
- [ ] **N3.6** Create `MemoryIndexService`
  - `indexDocument($agentId, $docId)` — chunk + embed single document
  - `reindexAgent($agentId)` — full reindex
  - Background job dispatch for async indexing
- [ ] **N3.7** Create `HybridMemorySearch` service
  - Vector similarity via pgvector `<=>` operator
  - Full-text search via `ts_rank` + `to_tsvector`
  - Combined scoring: 0.7 vector + 0.3 text
  - Result clamping: max 6 results, 700 chars per snippet, 4000 chars total
- [ ] **N3.8** Create `RecallMemory` agent tool
  - Allows agents to search their own memory semantically
  - Parameters: query, limit, collection (optional)
  - Returns ranked results with source citations
- [ ] **N3.9** Create `MemorySearchController` API
  - `POST /api/agents/{id}/memory/search` — search agent memory
- [ ] **N3.10** Create `IndexAgentMemoryJob` + `PeriodicReindexJob`
  - Index on document create/update (via model observer)
  - Periodic reindex every 5 minutes (delta-based)
  - Embedding refresh hourly
- [ ] **N3.11** Add Document model observer for auto-indexing
  - Trigger on identity/memory document changes
  - 15-second debounced dispatch
- [ ] **N3.12** Frontend: `MemorySearchInput.vue` component
  - Search input with debounced API calls
  - Show matched chunks with source references

### N4. Test Suite Foundation
**Impact:** MEDIUM | **Effort:** MEDIUM | **Priority:** 4

0% test coverage is a risk. Set up PHPUnit feature tests for the most critical API endpoints and establish patterns for future tests.

- [ ] **N4.1** Configure test environment
  - SQLite in-memory for speed
  - Test factories for User, Channel, Message, Document, Task, ListItem
  - Base test case with auth helpers
- [ ] **N4.2** Create model factories
  - `UserFactory` (human + agent variants)
  - `ChannelFactory` (public, private, dm)
  - `MessageFactory`
  - `DocumentFactory` (file + folder)
  - `TaskFactory` + `TaskStepFactory`
  - `ListItemFactory` + `ListStatusFactory`
  - `CalendarEventFactory`
  - `DataTableFactory` + `DataTableColumnFactory` + `DataTableRowFactory`
- [x] **N4.3** Agent API tests — ✅ 20+ test files exist in tests/Feature/ and tests/Feature/Tools/
  - `AgentControllerTest` — CRUD agents, identity files, show endpoint
  - `AgentPermissionControllerTest` — tool/channel/folder permission toggles
  - `AgentChatServiceTest` — message dispatch triggers agent response
- [ ] **N4.4** Core API tests
  - `ChannelControllerTest` — CRUD, members, read markers
  - `MessageControllerTest` — CRUD, reactions, threads, attachments
  - `DocumentControllerTest` — CRUD, versions, comments, folder tree
  - `ListItemControllerTest` — CRUD, reorder, status changes
  - `TaskControllerTest` — CRUD, lifecycle (start/complete/fail), steps
- [ ] **N4.5** Calendar & Table API tests
  - `CalendarEventControllerTest` — CRUD, recurrence, attendees, feeds
  - `DataTableControllerTest` — CRUD, columns, rows, bulk operations
- [ ] **N4.6** Integration tests
  - `ApprovalFlowTest` — create approval → approve/reject → agent resumes
  - `AgentToolExecutionTest` — agent uses tools correctly
- [ ] **N4.7** Set up CI pipeline (GitHub Actions)
  - Run tests on push/PR
  - Report coverage

### N5. Quick Wins & Polish
**Impact:** VISIBLE | **Effort:** LOW | **Priority:** 5

Small changes that immediately improve the demo experience and align code with documentation.

- [ ] **N5.1** Seed a `coordinator` agent in `UserSeeder`
  - All 7 TypeScript agent types now demonstrated
- [ ] **N5.2** Seed a `private` channel in `ChannelSeeder`
  - All channel types visible in demos
- [ ] **N5.3** Add `TaskStep` records to `AgentTaskSeeder`
  - Task detail view shows step tracking (action, decision, approval steps)
- [ ] **N5.4** Align `ExternalChannelProvider` type with reality
  - Only list implemented providers (telegram, slack) — remove or comment out others
- [ ] **N5.5** Add Data Tables section to `features.md`
  - Major built feature gets marketing visibility
- [ ] **N5.6** Add Calendar section to `features.md`
  - Built feature gets marketing visibility
- [ ] **N5.7** Update `emergent.md` risk assessment
  - Agent execution engine is now built (was listed as CRITICAL gap)
  - Update "No Agent Execution Engine" section to reflect current state
- [ ] **N5.8** Rename automation triggers for clarity
  - `task_created` → `list_item_created`
  - `assign_task` → `assign_list_item`
  - Aligns with the Tasks vs ListItems naming convention

### N6. External Channel: Discord
**Impact:** MEDIUM | **Effort:** MEDIUM | **Priority:** 6

Prove the external channel architecture scales beyond Telegram. Discord is where the AI/developer community lives.

- [ ] **N6.1** Create `DiscordService` (similar to `TelegramService`)
  - Bot token management
  - Send/receive messages via Discord API
  - Channel mapping (Discord channel ↔ OpenCompany channel)
- [ ] **N6.2** Create `DiscordWebhookController`
  - Receive Discord gateway events
  - Route messages to appropriate channels
  - Handle Discord-specific formatting (embeds, mentions)
- [ ] **N6.3** Create Discord integration settings
  - Add to `IntegrationSeeder` — bot token, guild ID, channel mappings
  - Add Discord configuration UI to Integrations page
- [ ] **N6.4** Create `SendDiscordNotification` agent tool
  - Similar to `SendTelegramNotification`
  - Support Discord embeds for rich formatting
- [ ] **N6.5** Update `ExternalChannelProvider` type
  - Add `discord` to TypeScript union type
  - Update channel creation flow to support Discord channels
- [ ] **N6.6** Test bidirectional message flow
  - Message in Discord → appears in OpenCompany channel
  - Agent response in OpenCompany → appears in Discord

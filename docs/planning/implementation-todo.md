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
| **Orchestration** | **Laravel Workflow** | No external infra, familiar patterns, good enough for MVP |
| **Future Option** | Temporal | Upgrade path when scale demands it |

**Core Packages:**
- `laravel/ai` - Official Laravel AI SDK (agents, tools, embeddings, multimodal)
- `laravel-workflow/laravel-workflow` - Durable workflow orchestration

**Optional Packages:**
- `laravel/mcp` - Expose OpenCompany as MCP server for external AI clients

---

## Phase 0: Package Installation & Setup

> **Why:** Before building the agent system, we need the core AI and workflow packages installed. Laravel AI SDK provides official first-party LLM integration, and Laravel Workflow handles durable task orchestration.

### 0.1 Install Core Packages
- [ ] **0.1.1** Install Laravel AI SDK
  - **What:** Official first-party Laravel package for AI/LLM integration with multiple providers
  - **Why:** Laravel AI SDK is the official package from the Laravel team. It supports agents, tools, streaming, embeddings, image generation, audio, and comprehensive testing utilities.
  - **Context:** We chose Laravel AI SDK over Prism (community package) for its first-party support, multimodal capabilities, and built-in testing.
  ```bash
  composer require laravel/ai
  ```

- [ ] **0.1.2** Publish AI SDK config
  - **What:** Creates `config/ai.php` with provider settings
  - **Why:** Need to configure API keys and provider-specific settings. Also enables adding custom providers like GLM via OpenAI-compatible endpoint.
  ```bash
  php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
  ```

- [ ] **0.1.3** Configure providers in `config/ai.php`
  - **What:** Set up API credentials for all LLM providers
  - **Why:** Anthropic/Claude is our primary LLM for agent tasks. OpenAI, Gemini, Groq, xAI are available as alternatives/fallbacks.
  - **Context:** GLM/Zhipu AI uses OpenAI-compatible endpoint with custom base URL. Provider failover is built-in.
  - Set `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, etc. in `.env`

- [ ] **0.1.4** Install Laravel Workflow
  - **What:** Durable workflow orchestration package for Laravel
  - **Why:** Agent tasks need to survive failures, handle long-running operations (waiting for approval), and maintain state. Laravel Workflow provides this without external infrastructure like Temporal.
  - **Context:** Alternative was Temporal, but Laravel Workflow is simpler and sufficient for MVP scale.
  ```bash
  composer require laravel-workflow/laravel-workflow
  ```

- [ ] **0.1.5** Publish Laravel Workflow config & migrations
  - **What:** Creates workflow tables and configuration
  - **Why:** Workflows need database persistence for state, timers, and signals.
  ```bash
  php artisan vendor:publish --provider="Workflow\WorkflowServiceProvider"
  ```

- [ ] **0.1.6** Run workflow migrations ← depends on: [0.1.5]
  - **What:** Creates `workflows`, `workflow_states`, and related tables
  - **Why:** Workflow state must persist to database for durability across restarts.

### 0.2 Verify Setup
- [ ] **0.2.1** Test Laravel AI SDK agent ← depends on: [0.1.3]
  - **What:** Simple test to verify provider APIs are working
  - **Why:** Catch configuration errors early before building dependent features.
  - **Context:** Should return a response and log token usage.
  ```php
  use function Laravel\Ai\agent;

  $response = agent(
      instructions: 'You are a helpful assistant.',
  )->prompt('Hello, world!');
  ```

- [ ] **0.2.2** Test Laravel Workflow setup ← depends on: [0.1.6]
  - **What:** Create a minimal workflow that persists and resumes
  - **Why:** Verify workflow infrastructure before building complex agent workflows.
  - Create simple test workflow
  - Verify state persistence

### 0.3 Optional: Install Extensions
- [ ] **0.3.1** Install Laravel MCP
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
- [ ] **1.1.1** Create `agent_configurations` migration
  - **What:** Stores the core identity and personality of each AI agent
  - **Why:** Agents need persistent personality (SOUL.md), instructions (AGENTS.md), and identity metadata. This is what makes each agent unique and consistent across sessions.
  - **Context:** In OpenClaw, these are markdown files in the workspace. We store them in DB for easier management via UI.
  - Fields: `id`, `user_id` (FK), `personality`, `instructions`, `identity`, `tool_notes`, `created_at`, `updated_at`
  - `personality` = TEXT (markdown, SOUL.md equivalent) - Agent's tone, boundaries, operating principles
  - `instructions` = TEXT (markdown, AGENTS.md equivalent) - Operating instructions, memory guidelines, skills
  - `identity` = JSON (`{name, emoji, type, avatar, description}`) - Visual identity for UI
  - `tool_notes` = TEXT (TOOLS.md equivalent) - Environment-specific tool notes (SSH hosts, device nicknames)

- [ ] **1.1.2** Create `agent_capabilities` migration ← depends on: [1.1.1]
  - **What:** Junction table linking agents to their enabled capabilities/tools
  - **Why:** Different agents need different tools. A code assistant needs git/file access, while a research agent needs web search. Per-agent capability control enables safe, scoped tool access.
  - **Context:** This enables the "capabilities" tab in the agent settings UI where users can toggle tools on/off.
  - Fields: `id`, `agent_config_id` (FK), `capability_id` (FK), `enabled`, `requires_approval`, `notes`, `created_at`

- [ ] **1.1.3** Create `capabilities` migration (master list)
  - **What:** Master list of all available tools/capabilities in the system
  - **Why:** Centralizes tool definitions so new tools can be added system-wide and assigned to agents. Defines default approval requirements per tool type.
  - **Context:** Seeded with common tools. Each has an icon for UI display and category for grouping.
  - Fields: `id`, `name`, `description`, `icon`, `category`, `default_enabled`, `default_requires_approval`, `created_at`
  - Seed with: code_execution, file_operations, git_operations, api_requests, database_access, production_deployment

- [ ] **1.1.4** Create `agent_settings` migration ← depends on: [1.1.1]
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

- [ ] **1.1.5** Update `capabilities` migration with tool kind
  - **What:** Classifies each tool by its operation type (read/edit/delete/execute/etc.)
  - **Why:** Enables intelligent approval rules - auto-approve reads but require approval for deletes. Different risk levels for different operation types.
  - **Context:** OpenClaw uses `inferToolKind()` to classify tools. We store it in DB for faster lookup.
  - `kind` enum: read/edit/delete/move/search/execute/fetch/other (default: other)

### 1.2 Memory & Session Tables

> **Why:** Agents need persistent memory across conversations. Sessions track the current conversation, while memories persist facts and learnings long-term.

- [ ] **1.2.1** Create `agent_sessions` migration ← depends on: [1.1.1]
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

- [ ] **1.4.1** Run `php artisan migrate` ← depends on: [1.1.1-1.3.2]
  - **What:** Execute all migration files to create tables
  - **Why:** Database must exist before models can query it.

- [ ] **1.4.2** Verify all tables created correctly
  - **What:** Check that all tables, indexes, and constraints exist
  - **Why:** Catch any migration errors early. Use `php artisan migrate:status` and check foreign keys.

- [ ] **1.4.3** Seed capabilities table with default capabilities
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

---

## Phase 2: Laravel Models

> **Why:** Eloquent models provide the ORM layer for all database operations. Models define relationships, casts, scopes, and business logic. Each model maps to a table from Phase 1.

### 2.1 Core Models

- [ ] **2.1.1** Create `AgentConfiguration` model ← depends on: [1.4.1]
  - **What:** Primary model for agent identity - personality, instructions, and visual identity
  - **Why:** Central model that all other agent-related models reference. Contains the agent's "soul" (personality) and "brain" (instructions).
  - **Context:** Uses soft deletes so deleted agents can be restored. Casts ensure JSON fields are handled as arrays.
  - Relationships: `belongsTo(User)`, `hasMany(AgentCapability)`, `hasOne(AgentSettings)`, `hasMany(AgentSession)`, `hasMany(AgentMemory)`
  - Casts: `identity` → array, `personality` → string, `instructions` → string

- [ ] **2.1.2** Create `Capability` model ← depends on: [1.4.1]
  - **What:** System-wide capability/tool definitions
  - **Why:** Master list of available tools that agents can be granted. Includes tool kind for approval logic.
  - **Context:** Read-only from application perspective - admin-seeded. Agents reference these via AgentCapability pivot.
  - Relationships: `belongsToMany(AgentConfiguration)` through `agent_capabilities`

- [ ] **2.1.3** Create `AgentCapability` model (pivot with extra fields) ← depends on: [2.1.1, 2.1.2]
  - **What:** Junction table linking agents to their enabled tools with per-agent settings
  - **Why:** Each agent can have different tool permissions. One agent might have code_execution with approval required, another without.
  - **Context:** The `notes` field stores agent-specific tool notes (e.g., "Use this for the staging server only").
  - Relationships: `belongsTo(AgentConfiguration)`, `belongsTo(Capability)`

- [ ] **2.1.4** Create `AgentSettings` model ← depends on: [2.1.1]
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

- [ ] **2.4.1** Add relationships to User model ← depends on: [2.1.1-2.3.2]
  - **What:** Connect User model to agent-related models
  - **Why:** Users own agents. A user can have one agent configuration (if they are an agent user). Also tracks spawn permissions and runs.
  - **Context:** The `hasOne(AgentConfiguration)` is for "agent users" - users that are actually AI agents in the system.
  - `hasOne(AgentConfiguration)` - only for agent users
  - `hasOne(SubagentSpawnPermission, 'parent_agent_id')`
  - `hasMany(SubagentRun, 'parent_agent_id')`
  - `hasMany(SubagentRun, 'child_agent_id')`

- [ ] **2.4.2** Add helper methods to User model
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

- [ ] **3.1.1** Create `AgentConfigurationController` ← depends on: [2.1.1]
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

- [ ] **3.2.1** Create `AgentCapabilityController` ← depends on: [2.1.3]
  - **What:** Manage which tools/capabilities are enabled for an agent
  - **Why:** Agents need different tools. This API enables the UI to toggle capabilities and set per-agent approval requirements.
  - **Context:** Bulk update is important for "save all changes" UX. Individual PATCH allows toggling single capability without affecting others.
  - `GET /api/agents/{id}/capabilities` - list agent capabilities
  - `PUT /api/agents/{id}/capabilities` - bulk update capabilities
  - `PATCH /api/agents/{id}/capabilities/{capabilityId}` - update single capability

- [ ] **3.2.2** Create `CapabilityController` ← depends on: [2.1.2]
  - **What:** Read-only access to system-wide capability definitions
  - **Why:** Frontend needs the master list of available capabilities to render the capability assignment UI.
  - **Context:** Capabilities are admin-seeded, not user-created. This is read-only.
  - `GET /api/capabilities` - list all available capabilities

### 3.3 Agent Settings Controller

- [ ] **3.3.1** Create `AgentSettingsController` ← depends on: [2.1.4]
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
  - **Context:** Spawn endpoint triggers the SubagentSpawnWorkflow. Cancel sends a signal to stop the running workflow.
  - `GET /api/agents/{id}/spawn-permissions` - get spawn permissions
  - `PUT /api/agents/{id}/spawn-permissions` - update permissions
  - `POST /api/agents/{id}/spawn` - spawn subagent
  - `GET /api/agents/{id}/subagent-runs` - list subagent runs
  - `GET /api/subagent-runs/{id}` - get run details
  - `POST /api/subagent-runs/{id}/cancel` - cancel running subagent

### 3.7 Register Routes

- [ ] **3.7.1** Add all routes to `routes/api.php` ← depends on: [3.1.1-3.6.1]
  - **What:** Wire up all controller methods to URL routes
  - **Why:** Routes connect HTTP requests to controller actions. Must be registered before frontend can call the API.
  - **Context:** Group under `/api/agents` prefix. Auth middleware ensures only authenticated users access their agents.
  - Group under `agents` prefix
  - Apply auth middleware
  - Add rate limiting where appropriate

---

## Phase 3.5: Workflow Integration (Prism + Laravel Workflow)

> **Why:** This phase connects the AI layer (Prism) with the orchestration layer (Laravel Workflow). Tools give agents abilities. Workflows coordinate multi-step agent tasks with durability and approval gates.

### 3.5.1 Create Prism Tools

- [ ] **3.5.1.1** Create `app/AI/Tools/` directory
  - **What:** Directory for Prism tool definitions
  - **Why:** Organizes AI tools separately from services. Each tool class defines what the AI can do and how.

- [ ] **3.5.1.2** Create base tool classes for agent capabilities
  - **What:** Prism tool implementations for each capability type
  - **Why:** Tools are how agents interact with the system. Each tool wraps a system capability (file access, git, etc.) with parameter validation and execution logic.
  - **Context:** Tools use Prism's tool definition format. Some tools require approval (database, deployment) - this is checked at execution time.
  - `CodeExecutionTool` - execute code snippets
  - `FileOperationTool` - read/write files
  - `GitOperationTool` - git commands
  - `ApiRequestTool` - external API calls
  - `DatabaseAccessTool` - database queries (requires approval)
  - `DeploymentTool` - production deployments (requires approval)

- [ ] **3.5.1.3** Create tool registry service
  - **What:** Service that provides tools to agents based on their enabled capabilities
  - **Why:** Agents should only see tools they're allowed to use. The registry filters the master tool list by agent's enabled capabilities.
  - **Context:** Called by ExecuteAgentActivity to get the tool list for each Prism call.
  ```php
  class AgentToolRegistry {
      public function getToolsForAgent(AgentConfiguration $config): array
      public function filterByCapabilities(array $tools, array $enabledCapabilities): array
  }
  ```

### 3.5.2 Create Workflow Activities

> **Why:** Activities are the building blocks of workflows. Each activity does one thing: fetch config, execute AI, save message, etc. Activities are retryable and their results are persisted.

- [ ] **3.5.2.1** Create `app/Workflows/Activities/` directory ← depends on: [0.1.4]
  - **What:** Directory for Laravel Workflow activity classes
  - **Why:** Organizes workflow activities. Each class handles one atomic operation.

- [ ] **3.5.2.2** Create `FetchAgentConfigActivity`
  - **What:** Load agent configuration and enabled tools from database
  - **Why:** Workflows need agent config to operate. This activity is the first step in any agent task - load who the agent is.
  - **Context:** Returns AgentConfiguration with relationships (capabilities, settings) loaded.
  - Fetch agent configuration from database
  - Return config with enabled tools

- [ ] **3.5.2.3** Create `ExecuteAgentActivity` ← depends on: [3.5.1.2]
  - **What:** Execute Prism AI call with tools
  - **Why:** This is the core AI execution - send prompt to Claude, get response, handle tool calls. The activity wraps Prism so workflows can orchestrate AI calls.
  - **Context:** Uses Prism's withMaxSteps for multi-turn tool use. Token tracking is critical for billing and context management.
  - Execute Prism text generation with tools
  - Handle streaming responses
  - Track token usage
  ```php
  class ExecuteAgentActivity extends Activity {
      public function execute(AgentConfiguration $config, string $prompt): AgentResult {
          return Prism::text()
              ->using(Provider::Anthropic, $config->model ?? 'claude-sonnet-4-20250514')
              ->withSystemPrompt($config->personality . "\n" . $config->instructions)
              ->withTools($this->toolRegistry->getToolsForAgent($config))
              ->withMaxSteps(5)
              ->withPrompt($prompt)
              ->asText();
      }
  }
  ```

- [ ] **3.5.2.4** Create `CreateApprovalRequestActivity`
  - **What:** Create an approval request record and notify users
  - **Why:** When agent wants to do something risky (database access, deployment), humans must approve. This activity creates the approval request.
  - **Context:** Approval requests appear in the Approvals page. Users are notified via WebSocket.
  - Create approval record in database
  - Notify relevant users
  - Return approval request ID

- [ ] **3.5.2.5** Create `WaitForApprovalActivity`
  - **What:** Long-running activity that suspends workflow until approval/rejection
  - **Why:** Workflows must pause and wait for human decision. This activity uses Laravel Workflow's signal feature to resume when approval arrives.
  - **Context:** Can wait indefinitely or have a timeout. Rejection cancels the workflow.
  - Long-running activity that waits for approval signal
  - Resume workflow when approved/rejected

- [ ] **3.5.2.6** Create `ExecuteApprovedActionActivity`
  - **What:** Execute the action that was approved
  - **Why:** After approval, the original tool call needs to be executed. This activity runs the approved action safely.
  - **Context:** Logs the execution for audit trail. Updates task status to completed.
  - Execute the approved action
  - Update task status

- [ ] **3.5.2.7** Create `SaveSessionMessageActivity`
  - **What:** Persist messages to the session_messages table
  - **Why:** All messages (user, assistant, tool) must be saved for context loading and history. This activity handles persistence and token count updates.
  - **Context:** Handles the `is_silent` flag for NO_REPLY messages.
  - Persist messages to agent_session_messages table
  - Update token counts

- [ ] **3.5.2.8** Create `MemoryFlushActivity` ← depends on: [3.6.2.1]
  - **What:** Execute pre-compaction memory flush (OpenClaw)
  - **Why:** Before context compaction, the agent should save important information to durable memory. This activity triggers a silent agent turn to do that.
  - **Context:** Uses NO_REPLY convention so output isn't shown to user. The agent is prompted to persist important context to MEMORY.md.
  - Execute pre-compaction memory flush
  - Return flush result for logging

- [ ] **3.5.2.9** Create `PruneSessionActivity` ← depends on: [3.6.3.1]
  - **What:** Prune old tool results from session context (OpenClaw)
  - **Why:** Old tool results bloat context. After TTL expires (5 minutes default), tool results are trimmed or replaced with placeholders.
  - **Context:** Uses soft-trim (keep head+tail) or hard-clear depending on size. Protects last 3 assistant messages.
  - Prune old tool results if TTL elapsed
  - Return pruning stats

- [ ] **3.5.2.10** Create `CheckMemoryFlushActivity`
  - **What:** Check if memory flush is needed based on soft threshold (OpenClaw)
  - **Why:** Memory flush should run before compaction, not after. This activity checks if we're approaching the threshold and haven't flushed this cycle.
  - **Context:** Uses `memoryFlushCompactionCount` to prevent duplicate flushes per compaction cycle.
  - Check if memory flush is needed based on soft threshold
  - Return boolean

### 3.5.3 Create Workflows

> **Why:** Workflows orchestrate activities into complete agent operations. They handle the full lifecycle: load config → check context → execute AI → save results → handle approvals.

- [ ] **3.5.3.1** Create `app/Workflows/` directory ← depends on: [0.1.4]
  - **What:** Directory for Laravel Workflow workflow classes
  - **Why:** Organizes workflows separately. Each workflow class defines a complete agent operation.

- [ ] **3.5.3.2** Create `AgentTaskWorkflow` ← depends on: [3.5.2.2-3.5.2.10]
  - **What:** Main workflow for executing an agent task (responding to user input)
  - **Why:** This is the core agent loop. It handles OpenClaw patterns (memory flush, pruning), executes the AI, saves messages, and manages approvals.
  - **Context:** Workflows are durable - if the server crashes, they resume from the last completed activity. The `yield` syntax enables Laravel Workflow's generator-based execution.
  ```php
  class AgentTaskWorkflow extends Workflow {
      public function execute(AgentTask $task) {
          // 1. Fetch agent config
          $config = yield Activity::make(FetchAgentConfigActivity::class, $task->agentId);

          // 2. Check if memory flush needed before execution (OpenClaw)
          $flushNeeded = yield Activity::make(CheckMemoryFlushActivity::class, $task->sessionId);
          if ($flushNeeded) {
              yield Activity::make(MemoryFlushActivity::class, $task->sessionId);
          }

          // 3. Prune session if TTL elapsed (OpenClaw)
          yield Activity::make(PruneSessionActivity::class, $task->sessionId);

          // 4. Execute agent with Prism
          $result = yield Activity::make(ExecuteAgentActivity::class, $config, $task->prompt);

          // 5. Handle silent responses (NO_REPLY convention)
          if (str_starts_with($result->text, 'NO_REPLY')) {
              yield Activity::make(SaveSessionMessageActivity::class, $task->sessionId, $result, true);
              return $result->withSuppressedOutput();
          }

          // 6. Save messages to session
          yield Activity::make(SaveSessionMessageActivity::class, $task->sessionId, $result);

          // 7. Handle approval if needed
          if ($result->requiresApproval) {
              $approval = yield Activity::make(CreateApprovalRequestActivity::class, $result);
              $approved = yield Activity::make(WaitForApprovalActivity::class, $approval->id);

              if ($approved) {
                  yield Activity::make(ExecuteApprovedActionActivity::class, $result);
              }
          }

          return $result;
      }
  }
  ```

- [ ] **3.5.3.3** Create `AgentSessionResetWorkflow`
  - **What:** Workflow for resetting agent sessions (daily reset, idle reset, manual reset)
  - **Why:** Sessions need to be reset according to reset_policy. This workflow archives the old session, creates a new one, and optionally runs a "goodbye" summary.
  - **Context:** Triggered by scheduler for daily resets, or by idle detection for idle resets.
  - Handle scheduled session resets
  - Archive old session
  - Create new session

- [ ] **3.5.3.4** Create `SubagentSpawnWorkflow` ← depends on: [3.5.3.2]
  - **What:** Workflow for spawning and managing a child agent
  - **Why:** Subagent spawning needs to track the parent-child relationship, enforce timeouts, and handle cancellation. This workflow wraps AgentTaskWorkflow with spawn-specific logic.
  - **Context:** Creates SubagentRun record, starts child workflow, waits for completion or timeout, stores result.
  - Spawn child agent workflow
  - Track parent-child relationship
  - Handle timeout and cancellation

### 3.5.4 Workflow Infrastructure

> **Why:** Workflows need worker processes to execute them and APIs to monitor/control them. This infrastructure makes workflows operational.

- [ ] **3.5.4.1** Create workflow worker command
  - **What:** Artisan command that processes workflow jobs
  - **Why:** Workflows execute on queue workers. This command starts the worker that processes workflow activities.
  - **Context:** Run with `php artisan workflow:work`. Can run multiple workers for parallelism.
  ```bash
  php artisan workflow:work
  ```

- [ ] **3.5.4.2** Configure queue workers for workflows
  - **What:** Set up queue configuration for workflow processing
  - **Why:** Workflows need dedicated queue configuration. May need separate queues for high-priority vs background workflows.
  - **Context:** Configure in `config/queue.php`. Consider separate connection for workflows.

- [ ] **3.5.4.3** Add workflow status endpoints
  - **What:** API endpoints to check workflow status and send signals
  - **Why:** Frontend needs to display workflow progress (e.g., "waiting for approval"). Signal endpoint enables sending approval decisions to waiting workflows.
  - **Context:** Signals are how external events (approval clicks) communicate with paused workflows.
  - `GET /api/workflows/{id}` - get workflow status
  - `POST /api/workflows/{id}/signal` - send signal (e.g., approval)

- [ ] **3.5.4.4** Install Waterline UI (optional) ← depends on: [0.1.4]
  - **What:** Web UI for monitoring Laravel Workflows
  - **Why:** Debugging workflows is easier with a visual UI. Shows workflow history, current state, and allows manual intervention.
  - **Context:** Optional - can use database queries directly if preferred.
  ```bash
  composer require laravel-workflow/waterline
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
  - **Context:** Spawn is async - it starts a workflow and returns immediately. Frontend polls or uses WebSocket to track progress.
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

- [ ] **4.2.1** Connect `AgentPersonalityEditor.vue` to API ← depends on: [4.1.1]
  - **What:** Wire personality editor to backend
  - **Why:** Users need to edit and save agent personality. Currently uses mock data.
  - **Context:** Should show loading state while saving, success toast on save, error handling for failures.
  - Replace mock save with `updateAgentPersonality()`
  - Add error handling and success feedback

- [ ] **4.2.2** Connect `AgentInstructionsEditor.vue` to API ← depends on: [4.1.1]
  - **What:** Wire instructions editor to backend
  - **Why:** Users need to edit and save agent instructions. Currently uses mock data.
  - **Context:** Same UX patterns as personality editor - loading, success, error states.
  - Replace mock save with `updateAgentInstructions()`
  - Add error handling and success feedback

- [ ] **4.2.3** Connect `AgentCapabilities.vue` to API ← depends on: [4.1.2]
  - **What:** Wire capabilities toggles to backend
  - **Why:** Users need to enable/disable tools and set approval requirements. Currently uses mock data.
  - **Context:** Should fetch system capabilities list and agent's current assignments. Save should bulk update.
  - Fetch real capabilities list
  - Save capability changes and notes

- [ ] **4.2.4** Connect `AgentMemoryView.vue` to API ← depends on: [4.1.4, 4.1.5]
  - **What:** Wire memory and session display to backend
  - **Why:** Users need to view sessions, messages, and memories. Also need to add memories and start new sessions.
  - **Context:** Session list should be paginated. Memory add/delete should update list in real-time.
  - Fetch real session data
  - Fetch real memory entries
  - Implement new session creation
  - Implement memory add/delete

- [ ] **4.2.5** Connect `AgentSettingsPanel.vue` to API ← depends on: [4.1.3]
  - **What:** Wire settings form to backend
  - **Why:** Users need to configure agent behavior, cost limits, and reset policies.
  - **Context:** Some actions (reset, delete) need confirmation dialogs. Pause/resume should update status badge.
  - Fetch real settings
  - Save settings changes
  - Implement reset/pause/delete actions

- [ ] **4.2.6** Connect `AgentIdentityCard.vue` to real data ← depends on: [4.1.1]
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

- [ ] **4.3.1** Replace mock `fetchData()` with real API calls ← depends on: [4.2.1-4.2.6]
  - **What:** Load all agent data from API on page mount
  - **Why:** Page currently shows mock data. Need to fetch real configuration, capabilities, settings, session, and memories.
  - **Context:** Consider parallel fetching for better performance. Handle loading and error states for each section.
  - Fetch agent configuration
  - Fetch capabilities
  - Fetch settings
  - Fetch current session
  - Fetch memories

- [ ] **4.3.2** Implement all event handlers with real API calls
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

- [ ] **5.1.1** Add status control endpoints to `UserController` ← depends on: [2.4.1]
  - **What:** API endpoints for controlling agent operational status
  - **Why:** Users need to pause agents (stop processing), resume them, or hard-stop current work. Essential for managing runaway or misbehaving agents.
  - **Context:** Pause prevents new tasks from starting. Stop cancels the currently running workflow.
  - `POST /api/agents/{id}/pause` - pause agent
  - `POST /api/agents/{id}/resume` - resume agent
  - `POST /api/agents/{id}/stop` - stop agent (cancel current task)

- [ ] **5.1.2** Implement pause/resume logic
  - **What:** Business logic for status transitions and workflow cancellation
  - **Why:** Status changes must update the database and notify connected clients. Stopping requires cancelling the active workflow.
  - **Context:** WebSocket broadcast ensures all open tabs see status change immediately.
  - Update agent status to 'paused'/'working'/'idle'
  - Cancel any running tasks if stopping
  - Broadcast status change via WebSocket

### 5.2 Agent Deletion

- [ ] **5.2.1** Add agent deletion endpoint ← depends on: [2.4.1]
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

- [ ] **7.1.1** Create `AgentConfigurationTest` feature test ← depends on: [3.1.1]
  - **What:** Test agent configuration API endpoints
  - **Why:** Configuration is core functionality. Tests ensure CRUD works, authorization prevents unauthorized access, and validation rejects bad data.
  - **Context:** Use Laravel's testing helpers. Test as authenticated user and verify cannot access other users' agents.
  - Test CRUD operations
  - Test authorization (only owners can edit)
  - Test validation

- [ ] **7.1.2** Create `AgentCapabilityTest` feature test ← depends on: [3.2.1]
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

- [ ] **8.5.1** Already implemented basic UI (Integrations.vue)
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

## Verification Checklist

### Functional Verification
- [ ] Navigate to `/agent/{id}` - page loads without errors
- [ ] All 7 tabs render correctly (Overview, Personality, Instructions, Capabilities, Memory, Activity, Settings)
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
composer require prism-php/prism
composer require laravel-workflow/laravel-workflow

# Optional
composer require grpaiva/prism-agents
composer require elliottlawson/converse-prism
composer require laravel-workflow/waterline  # UI for workflow monitoring
```

### Migrations to Create (13 files)
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
└── xxxx_create_embedding_cache_table.php         # OpenClaw
```

### Models to Create (13 files)
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
└── EmbeddingCache.php           # OpenClaw
```

### Controllers to Create (9 files)
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
└── AllowlistController.php         # OpenClaw
```

### AI Tools to Create (6 files)
```
app/AI/Tools/
├── CodeExecutionTool.php
├── FileOperationTool.php
├── GitOperationTool.php
├── ApiRequestTool.php
├── DatabaseAccessTool.php
└── DeploymentTool.php
```

### Workflow Activities to Create (9 files)
```
app/Workflows/Activities/
├── FetchAgentConfigActivity.php
├── ExecuteAgentActivity.php
├── CreateApprovalRequestActivity.php
├── WaitForApprovalActivity.php
├── ExecuteApprovedActionActivity.php
├── SaveSessionMessageActivity.php
├── MemoryFlushActivity.php            # OpenClaw
├── PruneSessionActivity.php           # OpenClaw
└── CheckMemoryFlushActivity.php       # OpenClaw
```

### Workflows to Create (3 files)
```
app/Workflows/
├── AgentTaskWorkflow.php
├── AgentSessionResetWorkflow.php
└── SubagentSpawnWorkflow.php
```

### Services to Create (11 files)
```
app/Services/
├── AgentToolRegistry.php
├── ContextWindowGuard.php          # OpenClaw
├── MemoryFlushService.php          # OpenClaw
├── SessionPruningService.php       # OpenClaw
├── ToolKindClassifier.php          # OpenClaw
├── ExecutionApprovalService.php    # OpenClaw
├── EmbeddingService.php            # OpenClaw
├── EmbeddingCacheService.php       # OpenClaw
├── ChunkingService.php             # OpenClaw
├── MemoryIndexService.php          # OpenClaw
└── HybridMemorySearch.php          # OpenClaw
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

---

## Implementation Priority Order

**Day 1: Package Setup**
0. Install & configure packages (0.1.1 - 0.2.2)

**Week 1: Foundation**
1. Database migrations (1.1.1 - 1.4.3)
2. Memory search infrastructure (1.5.1 - 1.5.2) ← OpenClaw
3. Core models (2.1.1 - 2.4.2)

**Week 2: API Layer**
4. Controllers (3.1.1 - 3.7.1)
5. Seeders (6.1.1 - 6.2.2)

**Week 3: Workflow Integration**
6. AI Tools (3.5.1.1 - 3.5.1.3)
7. Workflow Activities (3.5.2.1 - 3.5.2.10)
8. Workflows (3.5.3.1 - 3.5.3.4)
9. Workflow Infrastructure (3.5.4.1 - 3.5.4.4)

**Week 4: Context Management (OpenClaw)**
10. Context Window Guard (3.6.1)
11. Pre-Compaction Memory Flush (3.6.2)
12. Session Pruning (3.6.3)
13. Tool Kind Classification (3.6.4)
14. Execution Approval System (3.6.5)

**Week 5: Hybrid Memory Search (OpenClaw)**
15. Embedding Service (3.7.1)
16. Chunking Service (3.7.2)
17. Memory Indexing (3.7.3)
18. Hybrid Search (3.7.4)

**Week 6: Frontend Integration**
19. useApi methods (4.1.1 - 4.1.8)
20. Component connections (4.2.1 - 4.2.9)
21. Page updates (4.3.1 - 4.3.2)

**Week 7: Polish & Testing**
22. Agent control actions (5.1.1 - 5.2.2)
23. Testing (7.1.1 - 7.2.7)

**Post-MVP: Enhancements**
24. Subagent spawning (8.3.x)
25. Skills system (8.4.x)
26. Webhooks (8.5.x)

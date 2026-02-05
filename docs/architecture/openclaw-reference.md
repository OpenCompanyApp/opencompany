# OpenClaw: Agent, Memory & Skills Architecture

A comprehensive technical breakdown of how OpenClaw handles agents, subagents, memory systems (short-term and long-term), and skills.

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Agent Architecture](#agent-architecture)
3. [Subagent System](#subagent-system)
4. [Memory Architecture](#memory-architecture)
5. [Skills System](#skills-system)
6. [Memory Database Schema](#memory-database-schema)
7. [Embedding Providers](#embedding-providers)
8. [Chunking Parameters](#chunking-parameters)
9. [Hybrid Search Scoring](#hybrid-search-scoring)
10. [QMD Architecture](#qmd-architecture)
11. [Plugin API Reference](#plugin-api-reference)
12. [Tool Security Model](#tool-security-model)
13. [Key Takeaways for OpenCompany](#key-takeaways-for-opencompany)

---

## Executive Summary

OpenClaw is a sophisticated personal AI assistant with a multi-layered architecture for managing agents, memory, and skills. Key characteristics:

- **Multi-agent support**: Multiple isolated agents per gateway, each with separate workspaces, credentials, and sessions
- **Hierarchical memory**: Short-term (session transcripts) + long-term (workspace files + vector search)
- **Tiered skills**: Workspace → managed → plugin → bundled, with gating based on requirements
- **Subagent spawning**: Parent agents can spawn child agents for task delegation with automatic result announcement

---

## Agent Architecture

### Agent Configuration

Agents are defined in `~/.openclaw/openclaw.json`:

```typescript
type AgentConfig = {
  id: string;                    // Unique identifier (e.g., "main", "work", "home")
  default?: boolean;             // Mark as default agent
  name?: string;                 // Display name
  workspace?: string;            // Agent workspace directory
  agentDir?: string;             // State directory for auth, config
  model?: AgentModelConfig;      // Primary model + fallbacks
  identity?: IdentityConfig;     // Name, emoji, creature, vibe, theme, avatar
  subagents?: {
    allowAgents?: string[];      // Which agents this can spawn ("*" for any)
    model?: string;              // Model for spawned subagents
  };
  sandbox?: {
    mode?: "off" | "non-main" | "all";
    scope?: "session" | "agent" | "shared";
    workspaceAccess?: "none" | "ro" | "rw";
  };
  tools?: AgentToolsConfig;
};
```

**Example configuration:**
```json5
{
  agents: {
    list: [
      { id: "home", workspace: "~/.openclaw/workspace-home", default: true },
      { id: "work", workspace: "~/.openclaw/workspace-work" }
    ]
  },
  bindings: [
    { agentId: "home", match: { channel: "telegram", peer: { kind: "dm", id: "12345" } } },
    { agentId: "work", match: { channel: "slack", teamId: "T123456" } }
  ]
}
```

---

### Identity Files

Each agent workspace contains markdown files that define the agent's identity, behavior, and operating context. These files are injected into the system prompt at session start.

| File | Purpose | Loaded In |
|------|---------|-----------|
| **IDENTITY.md** | Agent metadata: name, emoji, creature type, vibe, avatar | All sessions |
| **SOUL.md** | Behavioral guidelines, personality rules, boundaries, core truths | All sessions (except sub-agents) |
| **AGENTS.md** | Operating instructions, memory management guidelines, group chat etiquette | All sessions |
| **TOOLS.md** | User-maintained notes about tools, platform conventions, local infrastructure | All sessions |
| **USER.md** | User profile, preferred forms of address, timezone | All sessions (except sub-agents) |
| **HEARTBEAT.md** | Periodic check-in checklist (inbox, calendar, mentions, weather) | Heartbeat sessions only |
| **BOOTSTRAP.md** | One-time first-run setup ritual (deleted after completion) | First session only |
| **BOOT.md** | Optional gateway restart checklist (executed on each gateway start) | Boot sessions only |
| **MEMORY.md** | Curated long-term memory (user-maintained facts and preferences) | Main/private sessions only |

**Injection order:** `IDENTITY → SOUL → USER → AGENTS → TOOLS → MEMORY → HEARTBEAT → BOOTSTRAP`

**Sub-agent restrictions:** Sub-agents only receive **AGENTS.md** and **TOOLS.md**. They do NOT get SOUL.md, IDENTITY.md, USER.md, HEARTBEAT.md, or BOOTSTRAP.md — this keeps sub-agents focused on the task without inheriting the parent's full persona.

**Workspace directories:**
- `memory/` — Daily append-only log files (`YYYY-MM-DD.md`)
- `skills/` — Workspace-specific skill files (override bundled/managed skills by name collision)
- `canvas/` — Optional canvas UI files for node displays

**IDENTITY.md example:**
```markdown
---
name: Logic
emoji: 🤖
creature: AI assistant
vibe: sharp, precise
theme: dark
avatar: ./avatar.png
---
```

**SOUL.md principles:**
- Be genuinely helpful (no filler phrases)
- Have opinions and preferences
- Be resourceful before asking
- Earn trust through competence
- Respect boundaries and privacy

---

### Agent Lifecycle

**Session Key Format:**
```
Main agent:     agent:<agentId>:main
Subagent:       agent:<agentId>:subagent:<uuid>
Channel DM:     agent:<agentId>:<channel>:dm:<peerId>
Channel group:  agent:<agentId>:<channel>:group:<groupId>
```

**Lifecycle phases:**

1. **Bootstrap** - Workspace initialized with template files, auth profiles loaded
2. **Session Init** - Session key resolved, freshness evaluated, reset policies applied
3. **Running** - Gateway processes messages via embedded pi-mono runtime
4. **Cleanup** - Sessions archived or deleted based on policy

---

### Agent Execution Loop

The execution loop is the complete path from message intake to persistent reply. OpenClaw uses an async, queue-based architecture:

**Entry points:**
- Gateway RPC: `agent` (fire-and-forget) or `agent.wait` (wait for completion)
- CLI: `openclaw agent` command
- Internal: heartbeat, cron, webhook triggers

**Execution flow:**

```
Message Arrives
    ↓
1. Validate & Route — resolve sessionKey, sessionId, agentId
    ↓
2. Return RunId — { runId, acceptedAt } returned immediately (async)
    ↓
3. Enqueue — serialized per session, respects maxConcurrent limits
    ↓
4. Load Context — session history, workspace files, skills, bootstrap
    ↓
5. Build System Prompt — inject bootstrap files in order, add tools/skills
    ↓
6. Run LLM — call provider API with model + fallbacks
    ↓
7. Stream Events — emit lifecycle, assistant, and tool event streams
    ↓
8. Execute Tool Calls — run tools, emit results, loop back to LLM
    ↓
9. Compact if Needed — auto-summarize if near token limit
    ↓
10. Emit Final Reply — send lifecycle:end with payload list
```

**Queue modes:**
- **Per-session lane** — Serialized execution prevents race conditions within a session
- **Global lane** — Optional limit on max concurrent runs across all sessions
- **Sub-agent lane** — Dedicated concurrency group for sub-agent runs

**Model failover:**
If the primary model fails, OpenClaw automatically tries fallback models in order. Auth profiles are rotated if the failure is authentication-related.

---

### Multi-Agent Routing

Bindings route messages to specific agents based on channel/peer matching:

```typescript
type AgentBinding = {
  agentId: string;
  match: {
    channel: string;           // "telegram", "slack", "discord", etc.
    accountId?: string;        // For multi-account channels
    peer?: { kind: "dm" | "group" | "channel"; id: string };
    guildId?: string;          // Discord servers
    teamId?: string;           // Slack workspaces
  };
};
```

**Routing priority (most-specific wins):**
1. Exact peer match (DM/group/channel id)
2. Guild ID (Discord)
3. Team ID (Slack)
4. Account ID match
5. Channel-level match
6. Fallback to default agent

---

## Subagent System

### Spawning Mechanism

Parent agents spawn subagents using the `sessions_spawn` tool:

```typescript
{
  task: string;                  // Required task description
  label?: string;                // Display label
  agentId?: string;              // Target agent (if allowed)
  model?: string;                // Override model
  runTimeoutSeconds?: number;    // Max runtime (0 = unlimited)
  cleanup?: "delete" | "keep";   // Session cleanup mode
}
```

**Spawn process:**
1. **Permission check** - Requester must not be a subagent; target must be in `allowAgents`
2. **Session creation** - `agent:${targetAgentId}:subagent:${uuid}`
3. **Gateway call** - Run in `AGENT_LANE_SUBAGENT` lane
4. **Registry tracking** - `SubagentRunRecord` persisted to disk
5. **Await completion** - Via `agent.wait` RPC

**Restrictions:**
- Subagents cannot spawn other subagents
- Max concurrent subagents: 8 (configurable)
- Auto-archive after 60 minutes (configurable)

**Sub-Agent Inheritance Model:**

| Aspect | Inherited from Parent | Isolated/New |
|--------|----------------------|--------------|
| Message delivery context | ✓ (channel, account, peer) | |
| Model config | ✓ (with override option) | |
| Thinking level | ✓ (with override option) | |
| Workspace files | Partial: AGENTS.md + TOOLS.md only | SOUL.md, IDENTITY.md, USER.md, HEARTBEAT.md, BOOTSTRAP.md excluded |
| Session state | | ✓ New empty session |
| Tool calls | | ✓ Isolated execution |
| Session tools | | ✗ NOT available (sessions_list, sessions_history, sessions_send, sessions_spawn) |
| Timeout | | ✓ Configurable via `runTimeoutSeconds` |

**Auto-archive:** Sub-agent sessions are automatically archived after `archiveAfterMinutes` (default: 60 minutes). Transcripts are renamed with a `.deleted.<timestamp>` suffix.

---

### Isolation & Sandboxing

**Session isolation:**
- Each agent has separate session store: `~/.openclaw/agents/<agentId>/sessions/`
- Subagents run in separate sessions with unique keys

**Workspace isolation:**
- Default per-agent workspace: `~/.openclaw/workspace-<agentId>`
- Can be overridden in agent config

**Authentication isolation:**
- Separate auth profiles per agent: `~/.openclaw/agents/<agentId>/agent/auth-profiles.json`
- Main agent auth available as fallback

**Sandbox modes:**
- `off` - No sandboxing
- `non-main` - Sandbox non-main sessions only
- `all` - Sandbox everything

**Workspace access:**
- `none` - No workspace access
- `ro` - Read-only
- `rw` - Read-write

**Tool policy:**
- Subagents excluded from session tools by default (`sessions_list`, `sessions_history`, `sessions_send`, `sessions_spawn`)

---

### Communication & Announcement

After a subagent completes, an "announce step" reports results back to the parent:

```
Parent Agent
     ↓ (sessions_spawn)
Subagent Session (isolated)
     ↓ (completion)
Announce Step
     ↓ (gateway call)
Parent Chat Channel
```

**Announce payload includes:**
- Status: success, error, timeout, unknown
- Result summary from announce output
- Stats: runtime, tokens (in/out/total), cost estimate
- Session key and transcript path

---

### Multi-Agent Routing (Bindings)

In multi-agent setups, OpenClaw routes incoming messages to specific agents based on **bindings** — pattern-matching rules that map channel + account + peer to an agent ID.

**Binding structure:**
```json5
{
  bindings: [
    { agentId: "home", match: { channel: "whatsapp", accountId: "personal" } },
    { agentId: "work", match: { channel: "whatsapp", accountId: "business" } },
    { agentId: "work", match: { channel: "slack", teamId: "T12345" } },
    { agentId: "home", match: { channel: "discord", peer: { kind: "dm" } } }
  ]
}
```

**Match fields:** `channel`, `accountId`, `peer.kind` (dm/group/channel), `peer.id`, `guildId`, `teamId`

**Resolution:** Most-specific match wins. If no binding matches, the default agent handles the message.

---

## Memory Architecture

### Short-Term Memory (Sessions)

**Session storage:**
- Metadata: `~/.openclaw/agents/<agentId>/sessions/sessions.json`
- Transcripts: `~/.openclaw/agents/<agentId>/sessions/<sessionId>.jsonl`

**Session metadata (SessionEntry):**
```typescript
{
  sessionId: string;
  updatedAt: number;
  sessionFile?: string;
  chatType?: "direct" | "group" | "room";
  inputTokens?: number;
  outputTokens?: number;
  totalTokens?: number;
  contextTokens?: number;
  compactionCount?: number;
  memoryFlushAt?: number;
  memoryFlushCompactionCount?: number;
}
```

**Transcript format (JSONL):**
- Header: `{ type: "session", id, timestamp, cwd, parentSession }`
- Messages: `{ type: "message", message: { role, content } }`
- Compaction: `{ type: "compaction", summary, firstKeptEntryId, tokensBefore }`

---

### Long-Term Memory (Workspace Files)

**Workspace layout:**
```
~/.openclaw/workspace/
├── AGENTS.md              # Operating instructions
├── SOUL.md                # Personality & boundaries
├── IDENTITY.md            # Name, emoji, avatar
├── USER.md                # User profile
├── TOOLS.md               # Tool notes & conventions
├── HEARTBEAT.md           # Periodic check-in checklist
├── BOOTSTRAP.md           # One-time setup (deleted after)
├── BOOT.md                # Gateway restart checklist (optional)
├── MEMORY.md              # Curated long-term memory
├── memory/
│   ├── 2025-01-31.md      # Daily log (append-only)
│   ├── 2025-01-30.md
│   └── ...
├── skills/                # Workspace-specific skill overrides
└── canvas/                # Canvas UI files (optional)
```

**Memory files:**
- `MEMORY.md` - Curated facts (private sessions only)
- `memory/YYYY-MM-DD.md` - Daily append-only logs

---

### Session Reset Policies

**Reset modes:**

1. **Daily reset** (default 4:00 AM)
   ```json
   { "mode": "daily", "atHour": 4 }
   ```

2. **Idle reset**
   ```json
   { "mode": "idle", "idleMinutes": 60 }
   ```

3. **Per-type overrides**
   ```json
   {
     "resetByType": {
       "dm": { "mode": "idle", "idleMinutes": 240 },
       "group": { "mode": "idle", "idleMinutes": 120 }
     }
   }
   ```

4. **Per-channel overrides**
   ```json
   {
     "resetByChannel": {
       "discord": { "mode": "idle", "idleMinutes": 10080 }
     }
   }
   ```

**Manual reset:** `/new` or `/reset` commands

---

### Heartbeat System

The heartbeat is a periodic "pulse" mechanism that wakes an agent to autonomously check on things and report back. It enables proactive behavior without requiring user messages.

**How it works:**
1. Agent is woken at configured intervals (default: every 30 minutes)
2. Reads `HEARTBEAT.md` for the check-in checklist
3. Executes checks (email, calendar, mentions, project status, etc.)
4. Reports findings to the configured delivery target
5. If nothing to report, responds with `HEARTBEAT_OK` token (suppressed from output)

**Configuration:**

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enabled` | boolean | true | Enable/disable heartbeat |
| `every` | string | "30m" | Interval between heartbeats (duration format) |
| `prompt` | string | (reads HEARTBEAT.md) | Custom heartbeat prompt |
| `target` | string | "last" | Delivery target: "last" (last sender), "none", or channel ID |
| `model` | string | (agent default) | Override model for heartbeat runs |
| `ackMaxChars` | number | 30 | Max chars for suppressed ack responses |
| `activeHours.start` | string | — | Start time (HH:MM, 24h format) |
| `activeHours.end` | string | — | End time (HH:MM, 24h format) |
| `activeHours.timezone` | string | "user" | Timezone: "user", "local", or IANA TZ |

**Active hours gating:** If `activeHours` is configured, heartbeat runs are skipped outside the specified time window. This prevents agents from checking in during off-hours.

**Ack behavior:** When the agent's response is effectively just an acknowledgment (under `ackMaxChars`), the heartbeat token is stripped and the response is suppressed. Only substantive reports are delivered.

**HEARTBEAT.md example:**
```markdown
# Heartbeat Checklist
- Check inbox for new messages
- Review calendar for upcoming events
- Scan mentions and notifications
- Check project status updates
- Report anything that needs attention
```

---

### Context Pruning & Compaction

**Pruning (in-memory, per-request):**
- Only old `toolResult` messages are pruned
- User + assistant messages never modified
- Last 3 assistant messages protected

**Pruning config:**
```json
{
  "contextPruning": {
    "mode": "cache-ttl",
    "ttl": "5m",
    "softTrim": { "maxChars": 4000, "headChars": 1500, "tailChars": 1500 },
    "hardClear": { "placeholder": "[Old tool result content cleared]" }
  }
}
```

**Compaction (auto-triggered):**
- On context overflow error → compact → retry
- When `contextTokens > contextWindow - reserveTokens`

**Compaction config:**
```json
{
  "compaction": {
    "enabled": true,
    "reserveTokens": 16384,
    "keepRecentTokens": 20000
  }
}
```

---

### Pre-Compaction Memory Flush

Writes durable memories before compaction erases context:

```json
{
  "compaction": {
    "memoryFlush": {
      "enabled": true,
      "softThresholdTokens": 4000,
      "prompt": "Write lasting notes to memory/YYYY-MM-DD.md; reply NO_REPLY if nothing."
    }
  }
}
```

**How it works:**
1. Monitor context usage
2. When crossing soft threshold, run silent agentic turn
3. Agent writes durable state to `memory/YYYY-MM-DD.md`
4. Uses `NO_REPLY` convention (user sees nothing)
5. Runs once per compaction cycle

---

### Vector Memory Search

**Database:** SQLite at `~/.openclaw/memory/<agentId>.sqlite`

**Schema:**
- `files` - Indexed file metadata (path, hash, mtime)
- `chunks` - Text chunks with embeddings
- `embedding_cache` - Provider/model/hash → embedding
- `chunks_fts` - Full-text search (FTS5)
- `chunks_vec` - Vector similarity (sqlite-vec)

**Hybrid search:**
```
finalScore = 0.7 * vectorScore + 0.3 * textScore
```

**Memory tools:**
- `memory_search` - Semantic query returns snippets
- `memory_get` - Read specific memory files

> **See also:** [QMD Architecture](#qmd-architecture) for the full sidecar memory search system including collections, update cycles, session indexing, citations, scope rules, and result clamping.

---

## Skills System

### Skill Definition Format

Skills are directories containing `SKILL.md` with YAML frontmatter:

```markdown
---
name: github
description: GitHub CLI integration for issues, PRs, and repos.
homepage: https://cli.github.com
metadata: {"openclaw":{"emoji":"🐙","requires":{"bins":["gh"]}}}
---

# GitHub CLI

[Instructions for the agent...]
```

**Frontmatter fields:**
- `name` (required) - Skill identifier
- `description` (required) - Brief description
- `homepage` - Documentation URL
- `user-invocable` (default: true) - Expose as slash command
- `disable-model-invocation` (default: false) - Exclude from prompt
- `metadata` - JSON with OpenClaw extensions

---

### Skill Tiers & Loading

**Four tiers (highest to lowest precedence):**

1. **Workspace skills** - `<workspace>/skills/`
   - Per-agent, user-owned
   - Override all other tiers

2. **Managed skills** - `~/.openclaw/skills/`
   - Shared across agents
   - Local overrides for bundled

3. **Plugin skills** - Declared in `openclaw.plugin.json`
   - Loaded when plugin enabled

4. **Bundled skills** - Shipped with installation
   - 50+ skills included

---

### Gating & Requirements

**Metadata structure:**
```typescript
{
  always?: boolean;              // Skip all gating
  os?: string[];                 // ["darwin", "linux", "win32"]
  requires?: {
    bins?: string[];             // All must exist on PATH
    anyBins?: string[];          // At least one must exist
    env?: string[];              // Environment variables
    config?: string[];           // Config paths (e.g., "browser.enabled")
  };
  install?: SkillInstallSpec[];  // Installation options
}
```

**Install spec example:**
```json
{
  "install": [
    { "kind": "brew", "formula": "gh", "bins": ["gh"] },
    { "kind": "apt", "package": "gh", "bins": ["gh"] }
  ]
}
```

---

### Skill Execution

**Two invocation modes:**

1. **Model invocation** - LLM invokes via prompt
   - Skills formatted as XML in system prompt
   - Agent routes to appropriate tools

2. **User invocation** - Slash commands (`/github`, `/notion`)
   - Command names sanitized and deduplicated

**Command dispatch:**
- Default: Agent processes and routes
- With `command-dispatch: tool`: Direct tool invocation

---

### ClawHub Registry

Public skills registry at `https://clawhub.com`:

```bash
# Search
clawhub search "postgres"

# Install
clawhub install my-skill --version 1.2.3

# Update
clawhub update --all

# Publish
clawhub publish ./my-skill --slug my-skill --name "My Skill"
```

---

## Memory Database Schema

> Detailed schema of OpenClaw's SQLite-based memory system.

### Database Location

Each agent has its own memory database: `~/.openclaw/memory/<agentId>.sqlite`

### Tables

**`files` — Indexed file metadata**

| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER | Auto-increment primary key |
| `path` | TEXT | Relative file path within workspace |
| `hash` | TEXT | SHA256 hash of file content |
| `mtime` | INTEGER | File modification timestamp |
| `chunk_count` | INTEGER | Number of chunks generated from this file |

**`chunks` — Text chunks with source tracking**

| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER | Auto-increment primary key |
| `file_id` | INTEGER | Foreign key to `files` table |
| `content` | TEXT | Chunk text content |
| `start_line` | INTEGER | Starting line number in source file |
| `end_line` | INTEGER | Ending line number in source file |
| `token_count` | INTEGER | Approximate token count for this chunk |

**`embedding_cache` — Provider/model-keyed embedding cache**

| Column | Type | Description |
|--------|------|-------------|
| `provider` | TEXT | Embedding provider (openai, gemini, local) |
| `model` | TEXT | Model identifier (text-embedding-3-small, etc.) |
| `content_hash` | TEXT | SHA256 hash of the text content |
| `embedding` | BLOB | Binary embedding vector |

Primary key: `(provider, model, content_hash)`

**`chunks_fts` — Full-text search (FTS5 virtual table)**

```sql
CREATE VIRTUAL TABLE chunks_fts USING fts5(content, content=chunks, content_rowid=id);
```

Enables BM25-ranked text search across all chunks.

**`chunks_vec` — Vector similarity (sqlite-vec virtual table)**

```sql
CREATE VIRTUAL TABLE chunks_vec USING vec0(
    chunk_id INTEGER PRIMARY KEY,
    embedding float[1536]  -- or float[768] for Gemini
);
```

Enables cosine similarity search across embeddings.

---

## Embedding Providers

> OpenClaw supports three embedding providers with automatic fallback.

### Provider Comparison

| Provider | Model | Dimensions | Batch Support | Cost |
|----------|-------|------------|---------------|------|
| **OpenAI** | `text-embedding-3-small` | 1536 | Yes (Batch API) | ~$0.02/1M tokens |
| **Gemini** | `text-embedding-004` | 768 | Yes (batch endpoint) | Free tier available |
| **Local** | `node-llama-cpp` | Variable | No | Free (CPU/GPU) |

### Fallback Chain

```
OpenAI → Gemini → Local
```

If the primary provider fails (rate limit, network error), OpenClaw automatically falls back to the next provider. Each provider generates different embeddings, so the cache is keyed by `(provider, model)`.

### Batch Embedding

For bulk indexing operations, OpenClaw uses batch APIs to reduce costs:

- **OpenAI Batch API**: Submit embedding jobs asynchronously, results delivered via webhook
- **Gemini batch**: Synchronous batch requests with multiple texts per call
- **Local**: Sequential processing (no batching, but zero cost)

---

## Chunking Parameters

> How OpenClaw splits documents into searchable chunks.

### Sliding Window Algorithm

| Parameter | Default | Description |
|-----------|---------|-------------|
| **Chunk size** | 1024 tokens | Maximum tokens per chunk |
| **Overlap** | 128 tokens | Tokens shared between adjacent chunks |
| **Boundary** | Line-aware | Never splits mid-line |

### Algorithm

1. Tokenize the document
2. Create windows of 1024 tokens with 128-token overlap
3. Adjust window boundaries to nearest line break (never split mid-line)
4. Track `start_line` and `end_line` for each chunk
5. Hash each chunk's content for deduplication

### Delta Tracking

OpenClaw uses incremental indexing to avoid re-processing unchanged files:

1. On indexing, compute SHA256 hash of each file
2. Compare with stored hash in `files` table
3. Only re-chunk files where hash has changed
4. Copy unchanged chunk embeddings from cache (zero API cost)

This means re-indexing a workspace where 5% of files changed only costs 5% of a full index.

---

## Hybrid Search Scoring

> Updated scoring formula for combined vector and text search.

### Scoring Formula

```
finalScore = 0.6 × vectorScore + 0.4 × textScore
```

| Component | Method | Weight | Strengths |
|-----------|--------|--------|-----------|
| **Vector** | Cosine similarity via sqlite-vec | 0.6 | Semantic similarity, paraphrasing, conceptual matches |
| **Text** | BM25 ranking via FTS5 | 0.4 | Exact keywords, names, technical terms, code identifiers |

### Why Hybrid?

Neither search method alone is optimal:

- **Vector-only** misses exact keyword matches (e.g., searching for "PostgreSQL" might match "database system" but miss the exact term)
- **Text-only** misses semantic similarity (e.g., "how to fix the login bug" won't match "authentication error resolution")
- **Hybrid** captures both: semantic meaning AND exact keywords

### Query Pipeline

```
User Query
    ↓
┌───────────────┬──────────────────┐
│ Vector Search │ Full-Text Search │
│ (embed query) │ (tokenize query) │
│     ↓         │      ↓           │
│ cosine sim    │ BM25 ranking     │
│ via chunks_vec│ via chunks_fts   │
└───────┬───────┴────────┬─────────┘
        ↓                ↓
    Normalize scores (0.0 - 1.0)
        ↓
    Weighted combine: 0.6 × vector + 0.4 × text
        ↓
    Sort by final score, return top-K
```

---

## QMD Architecture

> QMD is OpenClaw's sidecar memory search daemon — an external subprocess that provides advanced indexing, embedding, and hybrid search over agent workspace files and session transcripts.

### Dual-Backend Architecture

OpenClaw's memory system supports two backends, but only one is active at a time via an exclusive plugin slot:

- **Built-in backend**: SQLite at `~/.openclaw/memory/<agentId>.sqlite` (the same database described in the [Vector Memory Search](#vector-memory-search) section above)
- **QMD subprocess**: External `qmd` CLI launched as a sidecar process alongside the agent
- **Plugin slot system**: The memory slot is exclusive — only one backend can be active at a time (built-in SQLite **OR** QMD). When QMD claims the memory slot, the built-in backend is deactivated.
- **`FallbackMemoryManager` wrapper**: If the QMD subprocess fails or crashes, the system automatically falls back to the built-in SQLite backend, ensuring memory search is never completely unavailable.
- **Status reporting**: The system reports which backend served results: `status().backend = "qmd"` or `"builtin"`

---

### Collections

Collections are named groups of indexed file paths, scoped per agent.

**Three default collections** (when `includeDefaultMemory=true`):

| Collection | Path Pattern | Description |
|------------|-------------|-------------|
| `memory-root` | `MEMORY.md` | Indexes `MEMORY.md` at workspace root |
| `memory-alt` | `memory.md` | Indexes `memory.md` (lowercase alternative) |
| `memory-dir` | `memory/**/*.md` | Indexes all markdown files in the memory directory recursively |

**Custom collections:**
- Registered via `qmd collection add <path> --name <name> --mask <pattern>`
- Each agent has its own set of collections (agent-scoped)
- Collection names are sanitized: lowercase, alphanumeric + hyphens only
- Paths in search results appear as `qmd/<collection>/<relative-path>`

---

### Search Algorithm

QMD uses hybrid search combining BM25 and vector embeddings:

- **BM25**: Exact keyword matching — good for IDs, code symbols, environment variables, and technical terms
- **Vector**: Semantic similarity via embeddings — good for paraphrased queries, conceptual matches, and natural language questions
- **Optional reranking**: GGUF models can be used for query expansion and result reranking

**Score merging formula:**
```
finalScore = vectorWeight * vectorScore + textWeight * textScore
```

**Default weights:**
- `vectorWeight` = 0.7
- `textWeight` = 0.3
- Weights normalize to 1.0

> **Note:** QMD uses 0.7/0.3 weights while the main [Hybrid Search Scoring](#hybrid-search-scoring) section above documents 0.6/0.4 — QMD has its own independently tuned weights.

---

### Agent Memory Tools

Two tools are provided via `createMemorySearchTool` + `createMemoryGetTool`:

**1. `memory_search(query, maxResults?, minScore?)`**

Semantic + keyword search across indexed collections.

- **Parameters:**
  - `query` (string) — Search query text
  - `maxResults` (number, optional) — Maximum results to return
  - `minScore` (number, optional) — Minimum score threshold
- **Returns:** `{ results, provider, model, fallback, citations }`
- **Each result:** `{ path, snippet, startLine, endLine, score, source }`
- **Source types:** `memory` or `sessions`
- Scoped to DM chats by default (see [Scope Rules](#scope-rules) below)

**2. `memory_get(path, from?, lines?)`**

Read a specific memory file by path.

- **Parameters:**
  - `path` (string) — Workspace-relative path (e.g., `MEMORY.md`, `memory/2026-02-05.md`, `qmd/<collection>/<file>`)
  - `from` (number, optional) — Starting line (1-indexed)
  - `lines` (number, optional) — Number of lines to read
- **Returns:** `{ path, text }`
- **Security:** Rejects non-markdown files, symlinks, and path traversal attempts

---

### Update Cycles

QMD maintains its index freshness through several mechanisms running at different intervals:

| Cycle | Default Interval | Description |
|-------|-----------------|-------------|
| **Boot indexing** | On startup | `qmd update && qmd embed` runs when the agent starts |
| **Periodic re-indexing** | 5 minutes | Background text/BM25 index updates |
| **Debounced file watching** | 15 seconds | File changes trigger debounced re-index (1.5s internal debounce for the file watcher, 15s for coalesced updates) |
| **Embedding refresh** | 60 minutes | Expensive embedding operation runs less frequently than text indexing |

**Efficiency mechanisms:**
- **Delta tracking**: Only re-processes files whose SHA256 hash changed since the last index
- **Dirty marking**: The built-in manager watches `MEMORY.md` and `memory/` for changes; marks the index dirty on modification
- **Session transcript sync**: Delta thresholds of 100KB or 50 messages trigger session export to markdown

---

### Session Transcript Indexing

QMD indexes past conversation transcripts so agents can semantically search their own history:

- **Raw sessions** stored as JSONL: `~/.openclaw/agents/<agentId>/sessions/<sessionId>.jsonl`
- **Exported to markdown**: `~/.openclaw/agents/<agentId>/qmd/sessions/<sessionId>.md`
- Markdown conversion preserves role attribution (user/assistant)
- Session transcripts are indexed by QMD like any other markdown collection
- **Configurable retention**: `memory.qmd.sessions.retentionDays` (default: 30)
- Enables agents to search past conversations semantically

---

### Daily Log Integration

Daily logs provide running temporal context without bloating session size:

- **Pre-compaction flush** writes to `memory/YYYY-MM-DD.md` (append-only)
- Agents read today + yesterday's logs at session start (loaded into context)
- Daily logs are indexed as part of the `memory-dir` collection (`memory/**/*.md`)
- Provides continuity across session resets and compaction cycles

---

### Result Format & Clamping

| Parameter | Default | Description |
|-----------|---------|-------------|
| `maxResults` | 6 | Maximum results returned per query |
| `maxSnippetChars` | 700 | Maximum characters per snippet |
| `maxInjectedChars` | 4000 | Total characters injected into context |
| `timeoutMs` | 4000 | Query timeout in milliseconds |

- Results include: `docid`, `score`, `snippet`, `file`, `body`
- Snippet metadata includes line numbers in `@@ -<start>,<count>` regex format

---

### Citations

Citations link search results back to exact source locations:

- **Format:** `Source: <path>#L<startLine>[-L<endLine>]`
- **Citation mode** configurable: `"auto"` (default), `"on"`, `"off"`
  - `"auto"`: Citations shown in DM conversations, suppressed in group channels
  - `"on"`: Always show citations
  - `"off"`: Never show citations

---

### Scope Rules

Memory search access is restricted by chat type to prevent private memory leakage:

- **Default policy:** `deny` with rule `{ action: "allow", match: { chatType: "direct" } }`
- **DM conversations:** Full memory search access
- **Group channels:** Memory search blocked (prevents private memory leakage into shared contexts)
- Configurable per agent via `memory.qmd.scope`

---

### Security

QMD enforces several security boundaries:

- **File type restriction**: Blocks non-markdown file reads (only `.md` files accessible)
- **Symlink rejection**: Rejects symlink traversal to prevent escaping workspace boundaries
- **Path validation**: Validates path escaping for collection roots
- **Agent isolation**: Each agent searches only its own collections — no cross-agent access
- **Session transcript sanitization**: Transcripts are sanitized before export to markdown

---

### Subprocess Communication

QMD runs as a child process managed by Node.js:

**Launch configuration:**
- Spawned via Node.js `spawn()` with per-agent environment variables:
  - `XDG_CONFIG_HOME` → agent-specific QMD config directory
  - `XDG_CACHE_HOME` → agent-specific QMD cache directory
  - `NO_COLOR=1`

**Commands:**
- `qmd collection list` — List registered collections
- `qmd collection add` — Register a new collection
- `qmd update` — Re-index text/BM25
- `qmd embed` — Refresh embeddings
- `qmd query` — Execute a search query

**Communication protocol:**
- JSON communication: stdout parsed with strict JSON parsing
- Timeout protection: 4s for queries, 120s for update/embed operations
- Health monitoring with automatic fallback on crash

---

### QMD Configuration Reference

Full configuration example:

```json5
{
  "memory": {
    "backend": "qmd",
    "citations": "auto",
    "qmd": {
      "command": "qmd",
      "includeDefaultMemory": true,
      "sessions": {
        "enabled": true,
        "retentionDays": 30
      },
      "update": {
        "interval": "5m",
        "debounceMs": 15000,
        "onBoot": true,
        "embedInterval": "60m"
      },
      "limits": {
        "maxResults": 6,
        "maxSnippetChars": 700,
        "maxInjectedChars": 4000,
        "timeoutMs": 4000
      },
      "scope": {
        "default": "deny",
        "rules": [
          { "action": "allow", "match": { "chatType": "direct" } }
        ]
      }
    }
  }
}
```

---

### QMD Storage Layout

```
~/.openclaw/
├── agents/
│   └── <agentId>/
│       ├── qmd/
│       │   ├── xdg-config/             # QMD configuration
│       │   ├── xdg-cache/qmd/
│       │   │   └── index.sqlite        # QMD search index
│       │   └── sessions/               # Exported session transcripts
│       │       ├── <sessionId>.md
│       │       └── <sessionId>-topic-<topicId>.md
│       └── sessions/
│           ├── sessions.json           # Session metadata
│           └── <sessionId>.jsonl       # Raw session transcripts
└── memory/
    └── <agentId>.sqlite                # Builtin fallback index
```

---

## Plugin API Reference

> Technical reference for OpenClaw's plugin system.

### Plugin Manifest (`openclaw.plugin.json`)

```typescript
type PluginManifest = {
  name: string;                    // Plugin identifier
  version: string;                 // SemVer version
  description?: string;            // Human-readable description
  author?: string;                 // Plugin author
  homepage?: string;               // Documentation URL

  capabilities?: {
    tools?: string[];              // Paths to tool definition files
    gateway?: string[];            // Gateway RPC method handlers
    http?: string[];               // HTTP request handlers
    commands?: string[];           // CLI command handlers
    channels?: string[];           // Channel implementations
    providers?: string[];          // AI provider implementations
    services?: string[];           // Background service definitions
    skills?: string[];             // Skill directories
    hooks?: string[];              // Lifecycle hook handlers
    cli?: string[];                // CLI extensions
  };

  slots?: {
    memory?: boolean;              // Claims exclusive memory slot
    sandbox?: boolean;             // Claims exclusive sandbox slot
    browser?: boolean;             // Claims exclusive browser slot
  };

  config?: {
    schema?: string;               // Path to JSON Schema for config validation
    defaults?: Record<string, unknown>;  // Default config values
  };

  requires?: {
    openclaw?: string;             // Minimum OpenClaw version (SemVer range)
    node?: string;                 // Minimum Node.js version
    bins?: string[];               // Required system binaries
    env?: string[];                // Required environment variables
  };
};
```

### Plugin Lifecycle

```
Discovery → Validation → Registration → Initialization → Running → Shutdown
```

| Phase | Description |
|-------|-------------|
| **Discovery** | Scan config paths, extensions dirs, bundled plugins |
| **Validation** | Parse manifest, validate config against JSON Schema |
| **Registration** | Register capabilities with the gateway |
| **Initialization** | Call plugin `init()`, start services |
| **Running** | Plugin capabilities available to agents |
| **Shutdown** | Call plugin `shutdown()`, clean up resources |

### Exclusive Slots

When a plugin claims a slot, it completely replaces the built-in implementation:

```json
{
  "slots": { "memory": true }
}
```

Only one plugin can claim each slot. If multiple plugins claim the same slot, the highest-precedence one wins (config > workspace > global > bundled).

---

## Tool Security Model

> OpenClaw's three-tier tool execution security system.

### Security Tiers

```
Tier 1: DENY          → Block all tool execution
Tier 2: ALLOWLIST      → Only pre-approved tools execute
Tier 3: FULL           → All tools execute without approval
```

### Tool Groups

Tools are organized into groups for batch permission management:

| Group | Tools | Risk Level |
|-------|-------|------------|
| **group:memory** | `memory_search`, `memory_get`, `memory_write` | Low |
| **group:web** | `web_search`, `web_fetch`, `web_browse` | Medium |
| **group:fs** | `file_read`, `file_write`, `file_delete` | Medium-High |
| **group:runtime** | `exec`, `shell`, `code_run` | High |
| **group:sessions** | `sessions_list`, `sessions_send`, `sessions_spawn` | Medium |
| **group:ui** | `ui_notify`, `ui_prompt`, `ui_confirm` | Low |
| **group:automation** | `cron_create`, `webhook_create` | Medium |
| **group:messaging** | `channel_send`, `dm_send` | Medium |
| **group:nodes** | `node_list`, `node_command` | High |

### Permission Resolution Stack

Tool access is resolved through a 7-layer permission stack, evaluated in order:

```
1. Profile Base        → minimal / coding / messaging / full
       ↓
2. Per-Agent Allow     → tools.allow[] or tools.alsoAllow[] (merged)
       ↓
3. Per-Agent Deny      → tools.deny[] (highest priority, wins over allow)
       ↓
4. Owner-Only          → Certain tools restricted to agent owner (e.g., whatsapp_login)
       ↓
5. Provider-Specific   → tools.byProvider[provider] with own allow/deny/profile
       ↓
6. Elevated            → Requires explicit sender approval for high-risk tools
       ↓
7. Sandbox/Subagent    → Additional restrictions for sandboxed or spawned sessions
```

**Key rules:**
- `deny` always wins over `allow` at any layer
- `allow` and `alsoAllow` cannot both be set (they merge)
- Groups (e.g., `group:fs`) expand to individual tools before evaluation
- Safe bins (read-only tools like grep, cat, jq) bypass the allowlist check

### Tool Profiles

Pre-configured sets of tool groups for common use cases:

| Profile | Groups Included | Use Case |
|---------|----------------|----------|
| **minimal** | memory | Read-only assistant, no external actions |
| **coding** | memory, fs, runtime | Software development tasks |
| **messaging** | memory, messaging, sessions | Communication-focused agent |
| **full** | All groups | Fully autonomous agent (trusted) |

### Per-Agent Allowlists

Each agent maintains its own allowlist of pre-approved command patterns:

```json
{
  "allowlist": [
    "npm test",
    "git status",
    "git diff *",
    "ls -la *",
    "cat *.md"
  ]
}
```

Patterns support glob matching. When a tool execution matches an allowlist pattern:
- The command executes immediately (no approval prompt)
- `last_used_at` and `last_used_command` are updated for audit

### ExecAsk Decision Flow

```
Tool Execution Request
    ↓
Is security_mode = "deny"?  → YES → Block execution
    ↓ NO
Is security_mode = "full"?  → YES → Execute immediately
    ↓ NO (allowlist mode)
Is tool in safe_bins?  → YES → Execute immediately
    ↓ NO
Does command match allowlist pattern?  → YES → Execute immediately
    ↓ NO
Is ask_mode = "off"?  → YES → Block execution
    ↓ NO
Prompt user for approval
    ↓
User approves?  → YES → Execute + optionally add to allowlist
    ↓ NO
Block execution
```

---

### Credential & Auth Profiles

Credentials are stored separately from the workspace for security:

**Storage locations:**
- `~/.openclaw/credentials/` — Per-provider credentials (OAuth tokens, API keys)
- `~/.openclaw/agents/<agentId>/agent/auth-profiles.json` — Per-agent auth configuration
- **NOT in workspace** — Credentials are never committed to workspace repos

**Auth profile features:**
- **Multi-account support** — Multiple accounts per channel (e.g., WhatsApp personal + business)
- **Fallback chain** — Main agent's auth profiles serve as fallback for sub-agents
- **Profile rotation** — On auth failure, OpenClaw rotates to the next available profile
- **OAuth flow** — Web providers authenticate via `openclaw login` command

**Per-agent auth example:**
```json
{
  "whatsapp": {
    "personal": { "accountId": "...", "token": "..." },
    "business": { "accountId": "...", "token": "..." }
  },
  "discord": {
    "default": { "token": "..." }
  }
}
```

---

## Key Takeaways for OpenCompany

### What to Adopt

1. **Identity files pattern** - Separate files for personality (SOUL), instructions (AGENTS), tool notes (TOOLS)
2. **Session key hierarchy** - Structured keys for routing and isolation
3. **Subagent spawning** - Task delegation with automatic result announcement
4. **Memory flush before compaction** - Preserve learnings before context reset
5. **Tiered skills** - Workspace overrides managed overrides bundled
6. **Skill gating** - Requirements-based filtering (bins, env, config)

### What to Adapt

1. **File-based config → Database** - Store in PostgreSQL with UI editor
2. **Single-user → Multi-tenant** - Team-scoped workspaces and permissions
3. **Local trust → Explicit auth** - Standard session auth with SSO
4. **JSONL transcripts → Structured tables** - Better querying and analytics
5. **CLI skills → API-based actions** - Structured, auditable operations

### Architecture Comparison

| Aspect | OpenClaw | OpenCompany (Target) |
|--------|----------|------------------|
| Config storage | JSON files | Database + UI |
| Session storage | JSONL files | PostgreSQL |
| Memory search | SQLite + vectors | PostgreSQL + pgvector |
| Skills | Markdown + frontmatter | Database + API |
| Multi-agent | Config bindings | Team/workspace scoped |
| Approvals | CLI prompt | UI workflow + audit |

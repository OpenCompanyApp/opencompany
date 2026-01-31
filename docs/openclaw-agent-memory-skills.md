# OpenClaw: Agent, Memory & Skills Architecture

A comprehensive technical breakdown of how OpenClaw handles agents, subagents, memory systems (short-term and long-term), and skills.

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Agent Architecture](#agent-architecture)
3. [Subagent System](#subagent-system)
4. [Memory Architecture](#memory-architecture)
5. [Skills System](#skills-system)
6. [Key Takeaways for Olympus](#key-takeaways-for-olympus)

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

Each agent workspace contains identity files that define the agent's personality and operating instructions:

| File | Purpose |
|------|---------|
| **IDENTITY.md** | Agent metadata: name, emoji, creature type, vibe, avatar |
| **SOUL.md** | Behavioral guidelines, personality rules, boundaries |
| **AGENTS.md** | Operating instructions and persistent memory |
| **TOOLS.md** | User-maintained notes about tools and infrastructure |
| **USER.md** | User profile and preferred forms of address |
| **BOOTSTRAP.md** | One-time setup ritual (deleted after completion) |

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
├── MEMORY.md              # Curated long-term memory
├── memory/
│   ├── 2025-01-31.md      # Daily log (append-only)
│   ├── 2025-01-30.md
│   └── ...
├── AGENTS.md              # Operating instructions
├── SOUL.md                # Personality
├── TOOLS.md               # Tool notes
└── USER.md                # User profile
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

## Key Takeaways for Olympus

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

| Aspect | OpenClaw | Olympus (Target) |
|--------|----------|------------------|
| Config storage | JSON files | Database + UI |
| Session storage | JSONL files | PostgreSQL |
| Memory search | SQLite + vectors | PostgreSQL + pgvector |
| Skills | Markdown + frontmatter | Database + API |
| Multi-agent | Config bindings | Team/workspace scoped |
| Approvals | CLI prompt | UI workflow + audit |

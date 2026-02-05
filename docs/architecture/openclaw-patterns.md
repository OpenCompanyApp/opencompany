# Strategic Analysis: OpenClaw Patterns for OpenCompany

## Executive Summary

OpenClaw is a personal AI assistant system with sophisticated patterns for agent management, approval workflows, and multi-channel communication. While built for individual power users with a "local-first, security-optional" philosophy, many of its architectural patterns can be adapted for OpenCompany's enterprise-focused agent work OS.

This document identifies which OpenClaw systems to adopt, which to skip, and how to implement them properly for business users who need auditability, compliance, and team collaboration.

---

## System Comparison

| Aspect | OpenClaw | OpenCompany |
|--------|----------|---------|
| **Target User** | Individual power user | Business teams |
| **Security Model** | Opt-in, local trust | Mandatory approval flows |
| **Agent Isolation** | Per-workspace files | Database-backed with roles |
| **Channels** | WhatsApp, Telegram, Discord, etc. | Internal chat + integrations |
| **Approvals** | CLI/socket-based | UI-driven with audit trails |
| **Cost Tracking** | Token counting | Token usage tracking per agent/task |
| **Persistence** | JSONL files | PostgreSQL/MySQL |

---

## Patterns to Adopt

### 1. Granular Tool/Action Approval System

**OpenClaw Pattern:**
- Three security levels: `deny`, `allowlist`, `full`
- Pattern-based allowlists with glob matching
- Approval socket protocol for external approval UIs
- Per-agent configuration

**OpenCompany Adaptation:**
```
ApprovalPolicy:
  - level: "allowlist" | "approval_required" | "blocked"
  - patterns: ["read:*", "write:documents/*", "execute:safe-commands/*"]
  - requires_approval_above: { cost: 10, risk: "medium" }
  - approvers: [role: "manager", user_ids: [...]]
```

**Business Value:**
- Compliance: Every agent action is auditable
- Risk Management: High-cost or sensitive operations require human approval
- Flexibility: Teams can define their own allowlists per project/agent

**Implementation Priority:** HIGH

---

### 2. Multi-Agent Workspace Isolation

**OpenClaw Pattern:**
- Each agent has isolated workspace directory
- Separate session stores per agent
- Per-agent auth profiles and credentials
- Config-based routing (not hardcoded)

**OpenCompany Adaptation:**
```php
// Agent workspace model
Agent {
  id, name, type, status
  workspace_id -> Workspace
  system_prompt (AGENTS.md equivalent)
  persona (SOUL.md equivalent)
  tool_permissions[]
  credential_vault_id
}

Workspace {
  id, name, team_id
  agents[]
  documents[]
  channels[]
  budget_allocation
}
```

**Business Value:**
- Team Isolation: Marketing agents can't access Engineering documents
- Credential Security: Each workspace has its own API key vault
- Resource Allocation: Budgets are per-workspace, not global

**Implementation Priority:** HIGH

---

### 3. Session Management Strategies

**OpenClaw Pattern:**
- DM scoping: `main` (shared), `per-peer`, `per-channel-peer`
- Session reset policies (daily, idle-based, manual)
- Session pruning and compaction for context management

**OpenCompany Adaptation:**
```
SessionPolicy:
  - dm_scope: "shared" | "per_user" | "per_channel"
  - retention: { days: 30, max_tokens: 100000 }
  - auto_reset: { daily_at: "04:00", idle_hours: 24 }
  - compaction: { enabled: true, summarize_after: 50000_tokens }
```

**Business Value:**
- Context Continuity: Agent remembers ongoing projects
- Privacy: Sensitive conversations can be isolated
- Cost Control: Old context is pruned to reduce token usage

**Implementation Priority:** MEDIUM

---

### 4. Webhook Integration System

**OpenClaw Pattern:**
- Custom webhook endpoints with payload transforms
- Token-based auth
- Presets for common services (Gmail, GitHub)
- Template-based routing to agents

**OpenCompany Adaptation:**
```php
Webhook {
  id, name, secret_token
  transform_template // JSON path mapping
  target_agent_id | target_channel_id
  enabled, created_by
}

// Example: GitHub PR webhook
{
  "match": { "headers.X-GitHub-Event": "pull_request" },
  "transform": {
    "title": "$.pull_request.title",
    "author": "$.pull_request.user.login",
    "url": "$.pull_request.html_url"
  },
  "action": "create_task",
  "assign_to": "code-review-agent"
}
```

**Business Value:**
- External Integration: CRM, email, CI/CD can trigger agent work
- No Custom Code: Business users configure via UI
- Audit Trail: All webhook triggers are logged

**Implementation Priority:** MEDIUM

---

### 5. Skills/Plugin Architecture

**OpenClaw Pattern:**
- Three-tier loading: workspace > managed > bundled
- Skill metadata with requirements (bins, env, config)
- Platform/OS filtering
- ClawHub registry for sharing

**OpenCompany Adaptation:**
```
Skill {
  id, name, description
  scope: "workspace" | "organization" | "global"
  requirements: { services: [...], permissions: [...] }
  parameters: { schema: JSONSchema }
  implementation: { type: "http" | "code", endpoint: "..." }
  enabled_for: [workspace_ids]
}

// Example: "Generate Report" skill
{
  "name": "generate-quarterly-report",
  "scope": "organization",
  "requirements": { "services": ["analytics-api"] },
  "parameters": {
    "quarter": "Q1|Q2|Q3|Q4",
    "department": "string"
  }
}
```

**Business Value:**
- Reusability: Common workflows shared across teams
- Governance: IT controls which skills are available
- Extensibility: Teams can create custom skills

**Implementation Priority:** LOW (future phase)

---

### 6. Cost Attribution & Budgets

**OpenClaw Pattern:**
- Per-message token counting
- Per-agent cost visibility
- Model failover for cost optimization

**OpenCompany Enhancement (token-based cost tracking):**
```
// Track token usage and costs on tasks and agents
Task {
  + token_usage: {
      input_tokens, output_tokens,
      model, cost_usd
    }
}

AgentTokenUsage {
  agent_id          // Which agent spent
  workspace_id      // Which workspace
  task_id           // Which task (if applicable)
  model             // e.g., "claude-sonnet-4-20250514"
  input_tokens      // Prompt tokens
  output_tokens     // Completion tokens
  cost_usd          // Computed cost in USD
  recorded_at       // Timestamp
}

Budget {
  workspace_id
  monthly_limit
  alert_threshold: 80%
  hard_cap: boolean
  rollover: boolean
}
```

**Business Value:**
- Chargeback: Departments pay for their agent usage
- Forecasting: Predict costs based on usage patterns
- Control: Hard caps prevent budget overruns

**Implementation Priority:** MEDIUM

---

### 7. QMD Memory Search Pattern

**OpenClaw Pattern:**

QMD (Quick Markdown) is OpenClaw's sidecar memory search system that provides hybrid BM25 + vector search across markdown files. Key characteristics:

- **Dual-backend**: QMD subprocess as primary, built-in SQLite as fallback
- **Collections**: Named groups of indexed paths (`memory-root`, `memory-alt`, `memory-dir`)
- **Hybrid search**: BM25 keyword matching + vector embeddings merged with configurable weights (default: 0.7 vector + 0.3 text)
- **Update cycles**: Boot (full index), periodic (5m for text), debounced (15s on file change), embedding refresh (60m)
- **Session transcript indexing**: Conversations exported to markdown and indexed for semantic search
- **Daily log integration**: Pre-compaction flush writes to `memory/YYYY-MM-DD.md`; today + yesterday loaded at session start
- **Result clamping**: maxResults=6, maxSnippetChars=700, maxInjectedChars=4000, timeoutMs=4000
- **Citations**: Source attribution with `path#Lstart[-Lend]` format
- **Scope rules**: Memory search restricted by chat type (DM-only by default)
- **Security**: Blocks non-markdown reads, rejects symlinks and path traversal, agent isolation

**OpenCompany Adaptation:**

| QMD Concept | OpenCompany Equivalent |
|---|---|
| SQLite per agent | Shared PostgreSQL with `agent_config_id` scoping |
| sqlite-vec extension | pgvector extension |
| FTS5 virtual table | PostgreSQL GIN index + `to_tsvector` |
| QMD subprocess (sidecar) | Laravel services (direct PHP calls, no subprocess) |
| File watcher (inotify) | Eloquent model observers on `Document` model |
| Periodic cron (5m) | Laravel Scheduler `everyFiveMinutes()` |
| Embedding refresh (60m) | Laravel Scheduler `hourly()` |
| Filesystem collections | `MemoryCollection` model (DB-backed) |
| Path glob patterns | Document relationship queries |
| IPC communication | Direct service injection |
| Fallback chain (QMD → SQLite) | Single PostgreSQL backend (no fallback needed) |
| `memory_search` tool | `RecallMemory` tool class |
| `memory_get` tool | `SaveMemory` / `RecallMemory` tool classes |

**Key Architectural Differences:**

1. **No subprocess overhead**: OpenClaw runs QMD as a child process with IPC, crash recovery, and health monitoring. OpenCompany runs search as a regular Laravel service within the same process, eliminating subprocess management entirely. Trade-off: search shares resources with the application.

2. **PostgreSQL instead of SQLite**: OpenClaw uses per-agent SQLite databases for complete isolation. OpenCompany uses a shared PostgreSQL instance with `agent_config_id` scoping — better for multi-tenant environments (single backup, single connection pool, existing infrastructure) but requires careful indexing.

3. **Queue-based indexing**: OpenClaw's QMD manages its own indexing cycles (boot, periodic, debounced). OpenCompany uses Laravel's queue system and scheduler for the same purpose, integrating with existing infrastructure (Horizon dashboard, failed jobs tracking, retry logic).

4. **Document model as source of truth**: OpenClaw indexes filesystem markdown files. OpenCompany indexes `Document` model records from PostgreSQL. The existing `AgentDocumentService` manages the hierarchy (identity/, memory/ folders), so the indexer reads from the same source — no filesystem synchronization needed.

5. **Collection model instead of path patterns**: OpenClaw defines collections as filesystem path globs (`memory/**/*.md`). OpenCompany defines collections as database relationships (`MemoryCollection hasMany Documents`), enabling UI-driven collection management and dynamic membership.

**What This Enables:**

- Agents search their memories intelligently (semantic similarity + exact keyword matching)
- Session transcripts become searchable knowledge across conversations
- Daily logs provide temporal context without loading full conversation history
- Citations enable source verification and navigation to exact document locations
- Scope rules prevent private conversation memories from leaking into group channels
- No external dependencies beyond existing PostgreSQL + pgvector
- Collection-based organization allows fine-grained search scoping

**Implementation Priority:** HIGH — Core to agent intelligence. Without QMD-equivalent search, agents lose the ability to recall and build on past knowledge.

---

### 8. Heartbeat Pattern

**OpenClaw Pattern:**
Periodic "pulse" mechanism that wakes agents at configurable intervals (default 30m) to autonomously check inbox, calendar, mentions, and project status. Uses HEARTBEAT.md as a checklist. Responses under `ackMaxChars` are suppressed. Supports active hours gating with timezone awareness.

**OpenCompany Adaptation:**

| OpenClaw | OpenCompany |
|----------|-------------|
| Heartbeat runner (Node.js `setInterval`) | Laravel Scheduler `everyThirtyMinutes()` per agent |
| HEARTBEAT.md file in workspace | `agent_configs.heartbeat_prompt` DB field |
| `HEARTBEAT_OK` ack token | Job returns "no action needed" (skip posting) |
| Active hours gating (`activeHours.start/end`) | Scheduler `between('09:00', '18:00')` constraint |
| Target delivery ("last" sender, channel ID) | Post to agent's primary channel or last DM |
| Per-agent heartbeat config override | Per-agent config in `agent_configs` table |

**Key Architectural Differences:**
- No file watcher needed — scheduler handles timing
- Heartbeat prompt stored in DB, editable via admin UI
- Results posted as regular messages in channels (leveraging existing messaging)
- Active hours per agent via `agent_configs.heartbeat_active_start/end` fields

**What This Enables:**
- Proactive agents that check on things without being asked
- Scheduled monitoring (agent checks project status every 30m)
- Time-aware behavior (no heartbeats at 3am)
- Customizable per agent (analyst checks data, writer checks content pipeline)

**Implementation Priority:** MEDIUM

---

### 9. Workspace Files → Document System Pattern

**OpenClaw Pattern:**
Each agent has a workspace directory with markdown files defining identity, behavior, and context. Files are injected into the system prompt in a specific order. Sub-agents only receive a subset.

**OpenCompany Adaptation:**

| OpenClaw File | OpenCompany Equivalent | Storage |
|--------------|----------------------|---------|
| AGENTS.md | `agent_configs.instructions` | DB field |
| SOUL.md | `agent_configs.personality` | DB field |
| IDENTITY.md | `agent_configs.name` + `users.avatar` + `agent_configs.emoji` | DB fields |
| USER.md | Workspace/user context (injected at runtime from authenticated user) | Runtime |
| TOOLS.md | Agent capabilities + tool documentation (auto-generated from registered tools) | Generated |
| HEARTBEAT.md | `agent_configs.heartbeat_prompt` | DB field |
| BOOTSTRAP.md | Agent onboarding flow (first-run `BootstrapAgentJob`) | Job |
| BOOT.md | Not needed — Laravel handles application lifecycle | N/A |
| MEMORY.md | Documents via `AgentDocumentService` (memory folder) | DB + files |
| `memory/*.md` | Daily logs via `AgentDocumentService.createMemoryLog()` | DB + files |
| `skills/` | Skill/tool registry per agent | DB |
| `canvas/` | Not applicable (web UI handles all rendering) | N/A |

**Injection Order (System Prompt Assembly):**

```php
// AgentPromptBuilder::build()
$sections = [
    'identity'     => $agentConfig->name . ' — ' . $agentConfig->emoji,
    'personality'  => $agentConfig->personality,     // SOUL.md equivalent
    'user_context' => $this->getUserContext($user),  // USER.md equivalent
    'instructions' => $agentConfig->instructions,    // AGENTS.md equivalent
    'tools'        => $this->getToolDocumentation(), // TOOLS.md equivalent
    'memory'       => $this->getRelevantMemory(),    // MEMORY.md + daily logs
];
```

**Key Difference:** OpenClaw uses flat files; OpenCompany uses structured DB fields. This enables:
- Admin UI editing (no file system access needed)
- Version history via database
- Role-based access control on who can edit agent identity
- Faster loading (no file I/O)

**Implementation Priority:** HIGH (foundation for agent execution)

---

### 10. Credential Vault Pattern

**OpenClaw Pattern:**
Credentials stored separately from workspace at `~/.openclaw/credentials/`. Per-agent auth profiles at `~/.openclaw/agents/<id>/agent/auth-profiles.json`. Supports multi-account per channel (e.g., WhatsApp personal + business). Main agent profiles serve as fallback for sub-agents.

**OpenCompany Adaptation:**

| OpenClaw | OpenCompany |
|----------|-------------|
| `~/.openclaw/credentials/` (filesystem) | `integration_settings` table (encrypted at rest) |
| Per-agent `auth-profiles.json` | `agent_integration_configs` table (agent_id + provider + config) |
| OAuth web flow (`openclaw login`) | Laravel Socialite + OAuth controller flows |
| Multi-account per channel | Multiple `IntegrationSetting` rows per agent per provider |
| Main agent fallback for sub-agents | Parent agent's integrations inherited by sub-tasks |
| Profile rotation on auth failure | Retry with next available config in `integration_settings` |

**Security Model:**
- All credentials encrypted at rest (Laravel's `encrypted` cast)
- Never exposed in API responses (hidden attributes)
- Scoped per agent — agents cannot access other agents' credentials
- Admin-only configuration via IntegrationController

**Implementation Priority:** MEDIUM

---

### 11. Agent Execution Loop Pattern

**OpenClaw Pattern:**
Async execution via gateway RPC. Message validated, runId returned immediately, agent runs in background. 10-step flow: validate → enqueue → load context → build prompt → run LLM → stream → execute tools → buffer → compact → emit. Queue lanes for concurrency control (per-session, global, sub-agent).

**OpenCompany Adaptation:**

| OpenClaw | OpenCompany |
|----------|-------------|
| Gateway RPC (`agent` / `agent.wait`) | HTTP API + Laravel queue job dispatch |
| Session key routing | `channel_id` + `agent_config_id` lookup |
| pi-agent-core embedded runtime | Laravel AI SDK `chat()->send()` / `chat()->stream()` |
| Model failover (fallback array) | AI SDK provider failover configuration |
| Stream events (lifecycle, assistant, tool) | WebSocket broadcast via Laravel Reverb |
| Queue lanes (per-session serialized) | Laravel queue with `onQueue('agent-' . $agentId)` |
| Global max concurrent | Queue worker `--max-jobs` + rate limiting |
| Sub-agent lane | Separate queue `agent-subagent` |
| Tool execution loop | `AgentToolExecutor` service with registered tool handlers |
| Context compaction | `ContextCompactionService` triggered at token threshold |
| Block streaming (paragraph chunking) | Reverb broadcast of partial responses |

**OpenCompany Execution Flow:**

```
1. Message arrives (WebSocket or API)
      ↓
2. MessageController stores message, dispatches ProcessAgentMessageJob
      ↓
3. Job loads: agent config, channel context, identity documents, memory
      ↓
4. AgentPromptBuilder assembles system prompt
      ↓
5. Laravel AI SDK sends to provider (with streaming if enabled)
      ↓
6. Tool calls? → AgentToolExecutor handles, results fed back to LLM
      ↓
7. Response streamed via Reverb to connected clients
      ↓
8. Final response stored as Message in database
      ↓
9. MemoryIndexService triggered for new content
      ↓
10. ContextCompactionService checks token threshold
```

**Key Differences:**
- No fire-and-forget RPC — Laravel queue handles async execution
- No embedded runtime — AI SDK abstracts provider communication
- No session key routing — Database relationships handle agent↔channel mapping
- Streaming via WebSocket (Reverb) instead of SSE/event streams

**Implementation Priority:** CRITICAL (this is the agent brain)

---

## Patterns to Skip or Modify

### 1. Local File-Based Configuration

**OpenClaw:** Uses `~/.openclaw/openclaw.json` and markdown files

**Why Skip:**
- Not suitable for multi-user teams
- No version control or audit trail
- Can't be managed via UI

**OpenCompany Approach:** Database-backed configuration with UI editor and change history

---

### 2. Device Pairing / Local Trust

**OpenClaw:** Trusts local connections automatically, pairing codes for remote

**Why Skip:**
- Businesses need consistent auth regardless of network location
- Compliance requires explicit authentication

**OpenCompany Approach:** Standard session-based auth with SSO/SAML support

---

### 3. Raw Shell Execution

**OpenClaw:** Agents can execute arbitrary shell commands with allowlist

**Why Modify:**
- Too risky for business environments
- Compliance issues with arbitrary code execution

**OpenCompany Approach:**
- Predefined "actions" with parameters, not raw commands
- Sandboxed execution environments
- All actions go through approval workflow

---

### 4. Multi-Channel Consumer Messaging

**OpenClaw:** WhatsApp, Telegram, Signal, iMessage integration

**Why Skip (for now):**
- Business communication should stay in controlled channels
- Compliance and data retention concerns
- Can be added later for customer support use cases

**OpenCompany Approach:** Internal chat + Slack/Teams integration for business tools

---

## Implementation Roadmap

### Phase 1: Foundation (Current Sprint)
1. **Tool Approval Policies** - Extend approval system for granular action control
2. **Agent Workspaces** - Isolate agents by team/project
3. **Cost Attribution** - Track token usage and costs per agent and workspace

### Phase 2: Integration (Next Quarter)
4. **Webhook System** - External triggers for agent work
5. **Session Management** - Configurable retention and reset policies
6. **Budget Controls** - Per-workspace limits and alerts

### Phase 3: Extensibility (Future)
7. **Skills Registry** - Shareable agent capabilities
8. **External Channels** - Slack/Teams integration
9. **Custom Actions** - User-defined sandboxed operations

---

## Database Schema Additions

```sql
-- Agent workspaces
CREATE TABLE workspaces (
  id UUID PRIMARY KEY,
  name VARCHAR(255),
  team_id UUID REFERENCES teams(id),
  budget_monthly DECIMAL(10,2),
  budget_used DECIMAL(10,2),
  settings JSONB,
  created_at TIMESTAMP
);

-- Tool/action policies
CREATE TABLE action_policies (
  id UUID PRIMARY KEY,
  workspace_id UUID REFERENCES workspaces(id),
  name VARCHAR(255),
  pattern VARCHAR(500), -- glob pattern
  policy ENUM('allow', 'require_approval', 'deny'),
  approval_config JSONB, -- who can approve, thresholds
  created_at TIMESTAMP
);

-- Session configurations
CREATE TABLE session_policies (
  id UUID PRIMARY KEY,
  workspace_id UUID REFERENCES workspaces(id),
  dm_scope ENUM('shared', 'per_user', 'per_channel'),
  retention_days INT,
  max_tokens INT,
  auto_reset_config JSONB,
  created_at TIMESTAMP
);

-- Webhooks
CREATE TABLE webhooks (
  id UUID PRIMARY KEY,
  workspace_id UUID REFERENCES workspaces(id),
  name VARCHAR(255),
  secret_token VARCHAR(255),
  match_rules JSONB,
  transform_template JSONB,
  target_type ENUM('agent', 'channel', 'task'),
  target_id UUID,
  enabled BOOLEAN DEFAULT true,
  created_by UUID REFERENCES users(id),
  created_at TIMESTAMP
);

-- Token usage tracking per agent/task
CREATE TABLE agent_token_usage (
  id UUID PRIMARY KEY,
  agent_id UUID REFERENCES users(id),
  workspace_id UUID REFERENCES workspaces(id),
  task_id UUID REFERENCES tasks(id),
  model VARCHAR(100),
  input_tokens INT,
  output_tokens INT,
  cost_usd DECIMAL(10,6),
  recorded_at TIMESTAMP DEFAULT NOW()
);
```

---

## Key Architectural Decisions

### 1. Config via Database, Not Files
- All configuration stored in PostgreSQL
- UI for editing policies
- Full audit trail of changes
- Role-based access to configuration

### 2. Approval-First, Not Allowlist-First
- Default: all agent actions require approval
- Allowlists are exceptions, not the norm
- Every approval has an audit record

### 3. Team-Scoped, Not User-Scoped
- Workspaces belong to teams, not individuals
- Shared visibility into agent behavior
- Collaborative approval workflows

### 4. Structured Actions, Not Shell Commands
- Predefined action types with schemas
- No arbitrary code execution by default
- Sandboxed environments for advanced use cases

---

## Plugin System

> Deep dive into OpenClaw's extensible plugin architecture.

### Plugin Discovery Chain

OpenClaw loads plugins from multiple sources, with higher-precedence sources overriding lower ones:

1. **Config paths** — Explicitly listed in `openclaw.json` configuration
2. **Workspace extensions** — `<workspace>/extensions/` directory
3. **Global extensions** — `~/.openclaw/extensions/` directory
4. **Bundled plugins** — Shipped with the OpenClaw installation

### Plugin Capabilities

Each plugin declares which capabilities it provides. OpenClaw supports 10 capability types:

| Capability | Description |
|------------|-------------|
| **tools** | Custom tool definitions agents can use |
| **gateway** | RPC method handlers for the gateway server |
| **http** | HTTP request handlers (webhooks, custom endpoints) |
| **commands** | CLI commands added to the `openclaw` binary |
| **channels** | Messaging channel implementations (Telegram, Slack, etc.) |
| **providers** | AI model provider implementations |
| **services** | Background services with lifecycle management |
| **skills** | Skill definitions (SKILL.md + metadata) |
| **hooks** | Event hooks for the agent lifecycle |
| **cli** | CLI command extensions |

### Plugin Manifest

Plugins are defined by an `openclaw.plugin.json` manifest:

```json
{
  "name": "my-plugin",
  "version": "1.0.0",
  "description": "Custom plugin for OpenClaw",
  "capabilities": {
    "tools": ["./tools/my-tool.ts"],
    "channels": ["./channels/my-channel.ts"],
    "skills": ["./skills/"]
  },
  "slots": {
    "memory": true
  },
  "config": {
    "schema": "./config-schema.json"
  }
}
```

### Exclusive Plugin Slots

Some capabilities are **exclusive** — only one plugin can fill a slot at a time:

- **memory** slot — One memory backend (built-in SQLite or QMD plugin)
- **sandbox** slot — One sandbox implementation
- **browser** slot — One browser automation provider

When a plugin claims an exclusive slot, it replaces the built-in implementation entirely.

### Config Validation

Plugin configuration is validated using JSON Schema + Zod:
- `config-schema.json` defines the expected configuration shape
- Runtime validation via Zod ensures type safety
- Invalid config prevents plugin from loading

### OpenCompany Adaptation

OpenCompany should implement plugins as **Laravel packages** with service providers:

```php
// Plugin service provider pattern
class MyPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register tools
        $this->app->tag([MyCustomTool::class], 'agent.tools');

        // Register channel
        $this->app->singleton(MyChannel::class);
        $this->app->tag([MyChannel::class], 'agent.channels');
    }

    public function boot(): void
    {
        // Register routes, migrations, config
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->publishes([__DIR__.'/../config/my-plugin.php' => config_path('my-plugin.php')]);
    }
}
```

**Implementation Priority:** LOW (Phase 3.8, post-MVP)

---

## Gateway Architecture

> Analysis of OpenClaw's WebSocket RPC gateway for multi-device agent access.

### Gateway Overview

The OpenClaw gateway is a WebSocket RPC server that enables remote access to agent capabilities from any connected device. It serves as the central nervous system for agent communication.

### RPC Method Namespaces

The gateway exposes methods organized by namespace:

| Namespace | Purpose | Key Methods |
|-----------|---------|-------------|
| **agent.*** | Agent lifecycle | `agent.run`, `agent.wait`, `agent.cancel`, `agent.status` |
| **sessions.*** | Session management | `sessions.list`, `sessions.history`, `sessions.send`, `sessions.spawn` |
| **chat.*** | Chat operations | `chat.send`, `chat.receive`, `chat.typing` |
| **nodes.*** | Device registry | `nodes.register`, `nodes.heartbeat`, `nodes.list` |
| **cron.*** | Scheduled tasks | `cron.list`, `cron.create`, `cron.delete`, `cron.trigger` |
| **browser.*** | Browser automation | `browser.navigate`, `browser.screenshot`, `browser.execute` |
| **exec.approvals.*** | Execution approval | `exec.approvals.list`, `exec.approvals.approve`, `exec.approvals.reject` |

### Node Registry

Connected devices register as "nodes" with the gateway:

```typescript
type NodeRegistration = {
  nodeId: string;           // Unique device identifier
  platform: string;         // "ios", "android", "macos", "web"
  deviceName: string;       // User-friendly name
  capabilities: string[];   // What the device can do
  lastHeartbeat: number;    // Unix timestamp
};
```

The node registry enables:
- **Device-aware routing** — Send notifications to the right device
- **Capability matching** — Route browser tasks to devices with browsers
- **Health monitoring** — Detect disconnected devices via heartbeat
- **Cross-platform sync** — Keep agent state consistent across devices

### Channel Manager

The gateway includes a unified channel manager that abstracts messaging across platforms:

```
Gateway
  ├── Channel Manager
  │   ├── Telegram Channel
  │   ├── Discord Channel
  │   ├── Slack Channel
  │   ├── Signal Channel
  │   ├── Web Channel
  │   └── Plugin Channels (Matrix, MS Teams, Zalo, etc.)
  └── Agent Router
      └── Binding-based routing (most-specific match wins)
```

### OpenCompany Adaptation

OpenCompany already has Laravel Reverb for WebSocket communication. The gateway pattern maps to:

```php
// Extend Reverb with RPC-style method routing
// routes/channels.php already handles auth
// Add method dispatching via Reverb events

// Agent RPC events
class AgentRpcEvent implements ShouldBroadcast
{
    public function __construct(
        public string $method,      // e.g., "agent.run"
        public array $params,
        public string $requestId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('agent.'.$this->params['agentId']);
    }
}
```

**Implementation Priority:** MEDIUM (Phase 3.9)

---

## Cron & Autonomous Agents

> OpenClaw's system for scheduled, autonomous agent execution.

### Overview

OpenClaw supports cron-based scheduling where agents execute tasks autonomously on a schedule, without human triggers. This enables:

- Daily standup summaries
- Periodic data monitoring
- Scheduled report generation
- Automated maintenance tasks

### Cron Job Configuration

```json
{
  "cron": {
    "jobs": [
      {
        "id": "daily-summary",
        "agentId": "main",
        "schedule": "0 9 * * 1-5",
        "task": "Generate a daily summary of yesterday's activity",
        "delivery": "announce",
        "enabled": true
      },
      {
        "id": "weekly-report",
        "agentId": "analyst",
        "schedule": "0 8 * * 1",
        "task": "Compile weekly metrics report",
        "delivery": "post",
        "channel": "reports"
      }
    ]
  }
}
```

### Delivery Modes

When a cron job completes, the result can be delivered in different ways:

| Mode | Behavior |
|------|----------|
| **announce** | Agent announces the result in its primary chat channel |
| **none** | Silent execution — result stored but not delivered |
| **post** | Post result directly to a specified channel |

### Scheduling Features

- **ISO 8601 support** — Use standard cron expressions or ISO 8601 intervals
- **One-shot tasks** — Execute once and auto-delete
- **Agent-scoped** — Each agent has its own schedule, isolated from others
- **Isolated execution** — Cron jobs run in separate sessions to avoid polluting conversation context

### OpenCompany Adaptation

Laravel's built-in scheduler integrates naturally:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Load cron jobs from database
    AgentCronJob::where('enabled', true)->each(function ($job) use ($schedule) {
        $schedule->job(new ExecuteAgentCronJob($job))
            ->cron($job->schedule)
            ->withoutOverlapping()
            ->onOneServer();
    });
}

// AgentCronJob model
class AgentCronJob extends Model
{
    protected $casts = [
        'enabled' => 'boolean',
        'one_shot' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}

// ExecuteAgentCronJob
class ExecuteAgentCronJob implements ShouldQueue
{
    public function handle(): void
    {
        // Create isolated session for cron execution
        $session = AgentSession::create([
            'agent_config_id' => $this->cronJob->agent->agentConfiguration->id,
            'session_key' => "cron:{$this->cronJob->id}:" . now()->timestamp,
            'status' => 'active',
        ]);

        $agent = OpenCompanyAgent::for($this->cronJob->agent);
        $response = $agent->prompt($this->cronJob->task);

        // Deliver based on mode
        match ($this->cronJob->delivery_mode) {
            'announce' => $this->announce($response),
            'post' => $this->postToChannel($response),
            'none' => null, // Store result only
        };

        // Auto-delete one-shot jobs
        if ($this->cronJob->one_shot) {
            $this->cronJob->delete();
        }

        $this->cronJob->update(['last_run_at' => now()]);
    }
}
```

### Database Schema

```sql
CREATE TABLE agent_cron_jobs (
    id UUID PRIMARY KEY,
    agent_id UUID REFERENCES users(id),
    name VARCHAR(255),
    schedule VARCHAR(100),        -- Cron expression
    task TEXT,                     -- Task prompt
    delivery_mode ENUM('announce', 'none', 'post'),
    target_channel_id UUID NULL REFERENCES channels(id),
    enabled BOOLEAN DEFAULT true,
    one_shot BOOLEAN DEFAULT false,
    last_run_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Implementation Priority:** MEDIUM (Phase 3.10)

---

## Conclusion

OpenClaw provides excellent architectural patterns for agent management, but its "power user, security-optional" philosophy needs adaptation for business use. The key insight is to take the **structural patterns** (workspace isolation, approval flows, session management, webhooks) while replacing the **implementation details** (file-based config, local trust, raw shell access) with enterprise-appropriate alternatives.

OpenCompany already has a solid foundation with its approval system and cost tracking via token usage. The next step is to add workspace isolation and granular action policies, which will unlock the multi-agent, multi-team capabilities that businesses need.

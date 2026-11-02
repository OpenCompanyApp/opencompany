# Connections, Events and Routines

Status: **Accepted design direction** (owner, 2026-11-02). Nothing here is implemented yet. Current
implementation truth is in [Current State](../CURRENT_STATE.md).
Direction: [Product Direction](../PRODUCT_DIRECTION.md), pillar 9 and the nuclear decisions.
Research input: [Integration strategy (2026-11)](integration-strategy-2026-11.md) and the
[agent landscape report](../research/2026-11-agent-landscape.md).
Related: [Agent Primitives Catalog](agent-primitives-catalog.md),
[Governance model](../architecture/governance-model.md),
[Computer provider contract](../architecture/computer-provider-contract.md),
[API-first client split](../architecture/api-first-client-split.md).

This document defines one model for integrations, file sync, automations, triggers, channels and
proactive behaviour. It replaces the per-package PHP integration system, Automations,
`ListAutomationRule`, integration `HasTriggers`/`IntegrationWebhook`, and hardcoded scheduled jobs.

## Why one model

Today these are separate mechanisms:

- integration packages;
- an MCP client;
- prompt and script automations;
- list automation rules;
- integration webhooks;
- Telegram digests in cron;
- separate event tables (`VfsOperationEvent`, `WebUsageEvent`).

Each has its own credentials, permission checks and audit. They are all the same thing seen from
different sides: something external or internal happens, the company decides whether and how an
agent responds, and governance applies. One model gives one place to enforce policy and one log to
audit.

## The six concepts

```
Connections ──produce──▶ Events ──match──▶ Triggers ──start──▶ Routines ──execute as──▶ Runs
                               Policy applies at every step
```

### 1. Connections

A connection is an authenticated link from a workspace to an external system. It declares three
capabilities:

| Capability | Meaning | Examples |
|---|---|---|
| **Actions** | Tools the connection offers to agents | MCP tools, API-manifest operations, Drive "share file" |
| **Data** | Content projected into OpenCompany | Drive folder in Files/VFS, mailbox, calendar, MCP resources |
| **Events** | Push (webhook, watch), poll (cursor) or none | Drive change feed, Gmail push, signed webhook, MCP subscription |

**Ownership and secrets:**

- A connection is **personal** (a human's own account) or **shared** (a service account or
  workspace credential). Personal connections are visible to other users' agents only when their owner
  grants it.
- Secrets live in the vault and are used only through the credential broker. They never enter the
  model, prompts, the VFS, mruby globals, logs or the agent's computer disk. The browser-session
  exception is tracked in the Product Direction open questions.
- One generic OAuth broker serves every connection type. Client registration order: CIMD (each
  instance publishes its own client metadata document), then DCR, then admin-entered client
  credentials. Slack and Google require the last option.
- Per-agent grants decide which agents may use which connection, and in which mode (read or act).

**Tier 1, deep connectors.** These are native, few, high-quality and app-owned under
`app/Domain/Connections/`. They implement domain contracts rather than tool lists:

| Contract | First connectors | Projection |
|---|---|---|
| `StorageMount` | Google Drive, OneDrive/SharePoint; then Dropbox, Box, S3/R2, WebDAV, SFTP | Folder in Files and the VFS, e.g. `/files/drive/...` |
| `Mailbox` | Gmail, Outlook, IMAP | Agent and shared inboxes; one-time codes |
| `Calendar` | Google, Microsoft, CalDAV | Built-in calendar |
| Later | Notion/Confluence import, Git repositories | Docs, Files |

*Breadcrumb for future agents:* deep connectors are intentionally app-owned. The old rule that
integration code lives in `../integrations` no longer applies, because that repository is archived.

**Storage mount semantics** (Drive as the reference implementation):

- **Index and lazy fetch, not a live passthrough.**
  - Metadata and extracted text sync through the provider's change feed and push notifications.
  - Content is fetched and cached on first read.
  - Indexed text joins OpenCompany's hybrid search and memory, labelled with its source and marked
    untrusted.
- **Native formats:** Google Docs, Sheets and Slides appear as Markdown, CSV and text exports.
  Phase 1 writes are uploads and new files. In-place editing of native documents comes later.
- **Writes** go through the VFS write path: policy, approvals and audit. They are then pushed with a
  revision check, and conflicts surface to the agent and the human.
- **Effective access** is the intersection of the external ACL (for the connection's identity) and
  OpenCompany resource permissions and per-agent grants.
- **Computer agents** see mounts only through the VFS projection, never through their own provider
  login.
- **Long-tail storage:** Laravel Flysystem adapters in-process for S3, R2, WebDAV and SFTP. An
  optional rclone sidecar (MIT) serves other backends. Drive and OneDrive stay native because they
  need change feeds, format exports and permission mapping.

**Tier 2, generic connectors:**

- **MCP connections:**
  - Built on the `laravel/mcp` client: MCP 2026-07-28, with fallback to 2025-11-25 and 2025-03-26.
  - Full OAuth 2.1: Protected Resource Metadata, RFC 8707 `resource`, PKCE, `iss` check, step-up
    scopes.
  - Registered per workspace at request or job time, never globally at boot.
  - URLs go through `WebRequestGuard`, with redirect and size limits.
  - Tool definitions are hash-pinned, and a changed definition needs re-approval.
  - `readOnlyHint` and `destructiveHint` are hints only.
  - URL-mode elicitation and `input_required` map to approval cards and secure forms.
  - stdio servers run only on an agent's computer or a sidecar, never in the web process.
  - The admin directory is sourced from the official MCP Registry (remote servers) plus curated
    first-party vendor servers.
- **API manifests:**
  - A declarative OpenAPI subset with an auth type from a fixed enum. This replaces about 55
    free-text strategies.
  - Read or write is inferred from the HTTP method, with overrides.
  - Agents get search-and-execute meta-tools, not one tool per endpoint.
  - Non-GET calls are approval-gated by default, and base URLs are SSRF-guarded.
- **Skills and plugins:**
  - SKILL.md skills sit under a `/skills` VFS mount with progressive disclosure. Their scripts run
    only in mruby or on the agent's computer.
  - Plugin import reads Agent Plugins 1.0 `plugin.json` and `.claude-plugin/plugin.json` /
    `marketplace.json`, taking skills and MCP config only. Hooks, commands, `bin/` and monitors
    are dropped.
  - Approval is bound to the skill digest, and imports are scanned. There is no automatic install
    from public registries. `userConfig` secrets go to the vault.
- **Generic inbound-webhook connection:**
  - A per-connection URL with HMAC or signature verification, configured per connection.
  - Secrets are taken from headers only, never the query string.
  - Any service that can POST becomes an event source.
- **MCP-resources mount:** resources from any MCP server appear read-only under
  `/connections/<name>/`.
- **Managed connectors:**
  - Activepieces (MIT) is an optional self-hosted sidecar. Composio, Pipedream Connect and Zapier
    MCP connect with the user's own key.
  - All of them attach as MCP servers and are never a hard dependency.
  - n8n nodes are not reused (Sustainable Use License).

Chat channels (Telegram, later Slack and X) remain the channel layer. They are connections for event
and identity purposes, but conversations are owned by the messenger model.

### 2. Events

One durable, append-only event log per workspace.

**Envelope:**

| Field | Meaning |
|---|---|
| `id`, `workspace_id` | Identity and scope |
| `type` | Namespaced, e.g. `drive.file.changed`, `task.assigned`, `message.posted`, `approval.resolved`, `run.failed`, `budget.exceeded`, `vfs.file.written`, `time.tick` |
| `source` | Connection, channel, internal domain or scheduler |
| `subject` | VFS path or record reference |
| `actor` | Human, agent, connection or system |
| `occurred_at`, `recorded_at` | Time |
| `idempotency_key` | Dedupe for webhooks and polling |
| `payload` | Stored and always treated as untrusted data in prompts |
| `causality_id`, `depth` | Chain of events and runs that led here; used for loop protection |

**Sources:**

- **Internal domain events:** issues, records, docs, messages and mentions, approvals, runs, budgets,
  VFS writes.
- **Connections:** change feeds, webhooks, MCP subscriptions, managed-connector triggers, polling
  cursors.
- **Channels:** Telegram and later Slack and X messages and mentions.
- **Time:** cron, intervals, one-off reminders.

**Uses beyond triggering:**

- the messenger activity feed;
- the audit trail and run replay;
- budget accounting and analytics.

The scattered per-feature event tables migrate into this log.

**Reliability:**

- Events are written before acknowledgement, using a queue-before-ack pattern.
- Failed processing goes to a dead-letter queue and can be replayed.
- Retention is configurable per type.

### 3. Triggers

A trigger is one of:

- a **schedule** (cron, interval or one-off);
- an **event filter** (type, source, subject pattern, field conditions);
- a **heartbeat**;
- an **inbound message** (DM, mention, channel message);
- a **manual** start.

**Invariant: a trigger is never consent.** It can start a run but never approves an external
action. Trigger payloads enter the run wrapped as untrusted data.

### 4. Routines

A routine is a versioned unit of recurring or reactive work, owned by an agent or a team.

| Field | Values |
|---|---|
| Kind | **Agent** (instructions or skill, LLM), **script** (mruby, no LLM), **hybrid** (script that calls the agent for judgment) |
| Triggers | One or more |
| Mode | Read-only or act |
| Output | Thread, DM, issue, doc, table or channel |
| Notify | Always, only if new (checkpointed), or on failure |
| Load control | Debounce, coalescing (e.g. 50 Drive changes become one run), concurrency limit, quiet hours |
| Limits | Budget (tokens, compute, spend) and approval policy |
| History | Version history and run history |

Script routines are the cheap, deterministic path for plumbing. Hybrid routines call the LLM only
when judgment is needed. This is how OpenCompany is cheaper than LLM-only agents and smarter than
flow builders.

**Authoring:**

- in chat ("every Monday at 9…", "when an invoice lands in Drive…");
- "save this task as a routine" from a successful run;
- templates in agent packs.

Routines render as readable cards with their run history, on the agent profile and on a workspace
Routines page. **There is no visual flow builder.**

### 5. Runs

Every routine execution is a run on the existing agent-run control plane: ledger, heartbeat, pause
and cancel, approval parking, replay and stale-run reconciliation. Runs carry the causality chain
from their triggering event.

### 6. Policy

Policy applies at every step:

- connection grants;
- event acceptance (signature checks, rate limits);
- trigger activation (quiet hours, kill switch);
- routine start (budget, concurrency, depth);
- tool calls (approval policy, hard-deny floor, read-only mode, untrusted-content taint).

The full model is in the [Governance model](../architecture/governance-model.md).

## Proactivity as configuration

| Behaviour | Composition |
|---|---|
| Heartbeat | Scheduled, read-only agent routine with a checkpoint; notifies only if new |
| Monitor | Event-triggered, read-only agent routine |
| Daily brief | Scheduled agent routine posting to a thread |
| Follow-up | Message-triggered routine, limited to threads a human started, with a cap on unanswered messages |
| File intake | `drive.file.changed` on a folder, then a hybrid routine (extract with a script, classify with the agent), then a table record |
| Telegram digest | Built-in scheduled routine (replaces the hardcoded `telegram:send-digests` job) |

## Loop protection

- Every event and run carries `causality_id` and `depth`. A configurable maximum depth stops routines
  that re-trigger themselves and agents that ping-pong.
- This replaces the dead in-process delegation depth guard
  (`AgentCommunicationService::incrementDepth`).
- Per-routine and per-connection rate limits, a dead-letter queue, and an admin kill switch for each
  routine, agent and workspace.

## What gets deleted

| Today | Replacement |
|---|---|
| ~600 integration packages, `integration-core`, bundle metapackage, 60 MB catalog, `create-integration` prompt | Deep connectors plus generic connectors; a directory sourced from the MCP Registry, curated vendor servers, plugin marketplaces and manifests |
| `IntegrationSetting` per-package forms | Connections with vault-backed secrets |
| Package-owned OAuth controllers | Generic OAuth broker |
| Automations (prompt and script), `RunDueAutomations` | Routines; the scheduler emits time events |
| `ListAutomationRule` | Record-change triggers |
| `HasTriggers` / `IntegrationWebhook` | Connection event capabilities and the inbound-webhook connection |
| Hardcoded cron jobs (e.g. Telegram digests) | Built-in routines |
| `VfsOperationEvent`, `WebUsageEvent` and similar | The event log |

The Google package's OAuth and client code is mined for the native Drive, Gmail and Calendar
connectors before deletion.

## Phasing

| Phase | Work |
|---|---|
| 0 | Event envelope and log table in the squashed baseline; causality chain and depth limit; Connections model with vault, credential broker and generic OAuth broker; delete the package system; policy model |
| 1 | Activity feed from the event log; routines created from chat; Automations migrated to Routines; rebuilt MCP client and directory; Google Drive storage mount |
| 2 | Generic inbound webhooks; Gmail push and agent inbox; record-change events; script and hybrid routines; API manifests; skills and plugin import; Activepieces sidecar documented |
| 3 | Routine governance (budgets, quiet hours, read-only heartbeats, "notify only if new", follow-up caps); save a task as a routine; OneDrive/SharePoint; calendars; MCP-resources mount; Flysystem and rclone mounts |
| 5 | OpenCompany as an MCP server; MCP Apps rendered as messenger cards; A2A Agent Cards |

## Open questions

- The default ownership (personal or shared) for each connection category.
- Whether the Activepieces sidecar ships in the default docker-compose.
- Event retention defaults and storage growth on self-hosted installs.
- Whether heavy pipeline-style routines later run on Bowerbird as an optional executor behind the
  routine contract.

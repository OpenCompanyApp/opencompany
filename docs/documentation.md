# Documentation Structure

Status: Product documentation-site blueprint, reconciled 2026-08-09; aligned to the
locked [Product Direction](PRODUCT_DIRECTION.md) on 2026-11-02. This is an information
architecture and content backlog, not the current engineering documentation index or a
shipped-feature list. Use `docs/INDEX.md` for repo navigation and `docs/CURRENT_STATE.md`
for product truth.

## Direction alignment (2026-11-02)

The [Product Direction](PRODUCT_DIRECTION.md) makes OpenCompany a messenger-first product
("Grok Bot for companies"). The public docs site must follow that order, and it must keep
"what exists today" separate from "what is planned".

**Today (current app, see [Current State](CURRENT_STATE.md)).** The authenticated home is the
Dashboard at `/w/{workspace}`. The sidebar lists Dashboard, Tasks, Approvals, Organization,
Automation, Chat, Docs, Files, Tables, Calendar, Lists and a collapsible Agents section. Chat is
one page among many. Pages are Laravel + Inertia pages with Wayfinder-generated routes.

**Target (accepted 2026-11-02, not implemented).** See
[UI Structure and Agent Editing](planning/ui-structure-and-agent-editing.md), the
[sidebar mockup](ui/mockups/sidebar-2026-11.html), the
[primitives plus apps mockup](ui/mockups/apps-and-primitives-2026-11.html) and
[Messenger UI](planning/messenger-ui.md).

- **Home** is the attention page and the default screen: needs you (approvals, questions,
  takeovers, sign-in requests), then results, then FYI, with the daily brief. It replaces
  Dashboard, Activity and Workload. It is not called "Inbox" (the Mail app and agent inboxes keep
  that word).
- **One 260px rail:** Home, Pinned (per person), Agents (opening one starts a DM; status and 🖥 for
  agents with a computer), People, Groups and channels, **Apps**, **Spaces**, and a footer with
  Team and Admin. Mobile uses a bottom bar: Home, Chats, Spaces, Team.
- **Three panes:** rail | main | resizable context pane. Chat-first puts the conversation in the
  main pane and the object, computer, run or thread details in the context pane; object-first puts
  the object in the main pane and an agent chat scoped to it in the context pane.
- **Computer access is per agent** and admin-controlled; VFS-only is first-class. The computer view
  (watch, take over, hand back) appears only for agents that have one (Phase 2,
  [Computer Provider Contract](architecture/computer-provider-contract.md)). The VFS stays the
  governed path to company data either way.
- **Core primitives** (pillar 7): Threads, Issues, Tables and Records, Docs, Files, Artifacts,
  Routines, Agents, People. **Spaces** are flat (never nested), and each has the same six tools:
  Issues, Tables, Docs, Files, Artifacts and Routines. Everything is typed, owned and has a status;
  creating Spaces, tables, fields or relations, schema changes and deletes are human-approved
  proposals.
- **Artifacts** ([Artifacts and Forms](planning/artifacts-and-forms.md)) are the only code-bearing
  primitive: Pages (declarative blocks over live data) and Interactive artifacts (sandboxed HTML
  plus mruby endpoints), with a governed publishing ladder (draft → Space → workspace → signed
  link → public, public off by default). **Forms** are a view on any Table with one governed
  submission pipeline. Built-in chart and diagram blocks and Typst PDF export into Files replace
  the renderer packages.
- **One concept per job:** Lists → Issues; Tasks (agent runs) → runs in threads and the agent
  profile; Automation → Routines; Dashboard/Activity/Workload → Home; Org → Team; three file
  stores → one Files store.
- **Change sets:** agent edits to tables, issues and docs go plan → dry-run preview → live staged
  edits in the agent's color → review per cell, row or all → apply, with "Revert this run".
  Human edits always win. Docs use collaborative suggestion mode.
- **Apps** are optional add-ons built only from primitives: Mail (Phase 2, doubles as the agent
  inbox), Calendar (Phase 3), CRM (Phase 4), then Helpdesk, Recruiting and Invoices.
- **Connections** replace the Integrations page and package catalog, and **Routines, Triggers and
  Events** replace Automations (pillar 9,
  [Connections, Events and Routines](planning/connections-events-routines.md)). No flow builder.
- **Governance** (pillar 5, [Governance Model](architecture/governance-model.md)): one policy engine with three presets (Personal, Team,
  Company) that set defaults only. Actions are **gated by reversibility, not by "write"**:
  reversible edits, trash and drafts mostly auto-apply; external sends ask the first time per
  target; spend, exposure and purges need approval per preset; the hard-deny floor never runs.
  Permissions are set at Space level, and agents join Spaces like teammates. A per-agent autonomy
  slider (Ask first · Balanced · Autopilot) writes scoped grants. Behaviour modes are deleted. Roles,
  approver policies, budgets and audit are Phase 3.
- **API-first now** (pillar 4): a new Vue 3 + Reka UI client on the versioned public API (OpenAPI),
  the realtime stream and push. Inertia is frozen, and old pages are deleted as the new client
  reaches parity ([API-First Client Split](architecture/api-first-client-split.md)).

**Writing rules for this blueprint.**

- Write the "Getting Started" and "User Guide" tabs chat-first: the first tutorial is talking to
  an agent in chat, not touring the dashboard.
- Mark every Phase 1–5 surface `[SOON]` until `CURRENT_STATE.md` lists it as implemented.
- Lua scripting is retired by the direction; mruby is the only Code Mode runtime. Script
  documentation should describe mruby Code Mode once that work lands, not Lua.
- Do not document computers, connections, routines, the event log, the policy model, budgets,
  approver roles, SSO, compliance or hosted Cloud as shipped.
- Pages for deleted or merged surfaces (Dashboard, Activity, Workload, Lists, Tasks as a
  top-level area, Automations, Org, the integration package catalog, per-package integrations,
  behaviour modes) are not written.
- Use "Home" for the attention page; reserve "inbox" for the Mail app and agent inboxes.

> Master blueprint for the OpenCompany documentation site.
> Each entry maps to a page (MDX file) on the website.

**Legend**

- Existing pages are unmarked
- `[NEW]` — page to be created
- `[SOON]` — documents a planned feature (show "Coming Soon" badge)
- `[EXPAND]` — existing page that needs significant content additions
- Indented items under a page are **sub-pages** (nested in sidebar)

---

## Tab 1 — Getting Started

> First-time visitors land here. Optimized for the "zero to productive" journey.
> Audience: everyone new to OpenCompany.

```
Getting Started
├── What is OpenCompany?             [NEW]    — Product overview, value proposition, and who it's for
├── Core Concepts                    [NEW]    — Workspaces, agents, channels, tasks, and how they connect
├── Installation                              — Prerequisites, local setup, first run
├── Quick Start                      [NEW]    — Five-minute path from install to first agent interaction
│
└── Tutorials
    ├── Your First Agent                      — Create an agent, assign a channel, watch it work
    ├── Building a Marketing Team             — Multi-agent setup for content and campaign workflows
    ├── Customer Support Bot                  — Ticket triage, knowledge base search, escalation
    ├── Sales Pipeline Automation             — Lead tracking, follow-ups, CRM integration
    └── Multi-Channel Deployment              — Connect Telegram, route external messages to agents
```

**9 pages** (4 new, 5 existing)

---

## Tab 2 — User Guide

> Day-to-day feature reference for workspace members and admins.
> Audience: end users, workspace administrators.

```
User Guide
├── Overview                                  — Hub page with quick links to every feature
├── Home                             [SOON]   — Attention page: needs you, results, FYI, daily brief (replaces Dashboard, Activity, Workload)
├── Navigating OpenCompany           [SOON]   — The rail, pins, three panes (chat-first / object-first), mobile bottom bar, ⌘K
│
├── Workspaces                       [NEW]    — Multi-workspace overview and switching
│   ├── Creating & Managing          [NEW]    — Create, rename, configure workspace settings
│   ├── Members & Roles              [NEW]    — Invite members, assign admin/member roles
│   └── Invitations                  [NEW]    — Token-based invites, acceptance flow
│
├── Chats
│   ├── Overview                     [NEW]    — DMs, group chats with agents and people, channels
│   ├── Direct Messages              [NEW]    — One-on-one conversations with humans and agents
│   ├── Run Activity & Approvals     [SOON]   — Typed run activity, inline approval cards, stop and redirect
│   ├── External Channels            [NEW]    — Telegram first; other chat platforms later
│   └── Message Features             [NEW]    — Reactions, threads, pinning, attachments, search
│
├── Team
│   ├── Overview                     [SOON]   — People and agents directory with manager relations (replaces Org)
│   ├── Agents                                — Agent types, statuses, hierarchy, and lifecycle
│   ├── Creating Agents                       — Spawn agents, choose model, set personality
│   ├── Agent Profile                         — Identity, memory, brain selection; target adds grants, routines, budget, runs
│   ├── Agent Computers              [SOON]   — Per-agent computer or VFS-only; watch, take over, hand back (Phase 2)
│   ├── Autonomy & Always-Allow      [SOON]   — Autonomy slider (Ask first · Balanced · Autopilot), per-agent always-allow list, promotions
│   ├── Tools & Skills                        — Built-in tools, MCP tools, permission scoping
│   └── Agent Communication          [NEW]    — Agent-to-agent messaging, delegation, notifications
│
├── Spaces                           [SOON]   — Flat Spaces, each with Issues, Tables, Docs, Files, Artifacts, Routines
│   ├── Issues                       [SOON]   — Human and agent work items (Lists folds in; agent runs move to threads)
│   ├── Tables                                — Typed columns, views, bulk operations; target: Person/Agent, Relation, Attachment, Long-text
│   ├── Docs                                  — Documents, versions, comments; target: typed docs, owner/status/review, suggestion mode
│   ├── Files                                 — Workspace files; target: one Files store with Drive/OneDrive mounts and PDF exports
│   ├── Artifacts                    [SOON]   — Pages (blocks over live data, charts, diagrams) and interactive artifacts; versions and stable URLs
│   │   └── Publishing               [SOON]   — Draft → Space → workspace → signed link → public; registry and kill switch
│   ├── Forms                        [SOON]   — Form view on any Table; page, one-question, conversational, embed and internal modes
│   ├── Routines                     [SOON]   — Agent, script (mruby) and hybrid routines; triggers; no flow builder
│   └── Ownership & Lifecycle        [SOON]   — Owners, draft/current/archived, review dates, governed creation
│
├── Agent Edits & Change Sets        [SOON]   — Plan, dry-run, live staged edits, review per cell/row/all, apply, revert this run
│
├── Apps                             [SOON]   — Add-on apps built from primitives; install, scoped views in Spaces, uninstall
│   ├── Mail                         [SOON]   — On Gmail/Outlook connections; doubles as the agent inbox (Phase 2)
│   ├── Calendar                     [SOON]   — On calendar connections, agenda on Home (Phase 3); replaces today's Calendar page
│   └── CRM                          [SOON]   — First schema-only app on the manifest runtime (Phase 4)
│
├── Approvals                                 — Approval lifecycle and routing; target: inline, first-send, batched and learned-exception cards; non-blocking
├── Governance & Presets             [SOON]   — Personal / Team / Company presets, gating by reversibility, approvers, budgets, audit, hard-deny floor
├── Permissions & Spaces             [SOON]   — Space-level access, private Spaces, guest sharing, agents as members, "Why can't Ada see this?"
├── Trash                            [SOON]   — 30-day trash and restore; purge rules per preset
├── Search                           [NEW]    — Global search across objects, messages, channels
└── Admin & Profile                           — Connections, members, billing; Governance area (Team/Company); user preferences, appearance
```

**43 pages** (10 new, 12 existing/reorganized, 21 coming soon; recounted 2026-11-02)

---

## Tab 3 — Technical

> Architecture deep-dives and system internals.
> Audience: developers, contributors, curious power users.

```
Technical
├── Overview                                  — Technical documentation hub and reading guide
├── Architecture                              — Application layers, request lifecycle, AI SDK integration
├── Memory System                             — Dual-memory model, compaction, hybrid search, token tracking
├── Agent Orchestration                       — Agent creation, peer subagents, delegation callbacks, lifecycle
├── Tools & Code Mode                         — Tool registry, mruby Code Mode, VFS
├── External Channel Sync                     — Bidirectional Chatogrator-backed channel synchronization
├── Events & Routines                [SOON]   — Workspace event log, causality chain, triggers, routines, dead-letter queue
├── API & Clients                    [NEW]    — API-first backend, OpenAPI contract, realtime stream, push, client auth
├── LLM Providers                             — Provider resolution, brain field format, token accounting
├── Data Model                       [NEW]    — Entity relationships, workspace scoping, key tables
├── Real-time & WebSockets           [NEW]    — Reverb channels, presence, broadcasting events
└── Conversation Compaction          [NEW]    — Summarization strategy, flush cycles, token budgets
```

**12 pages** (4 new, 7 existing, 1 coming soon)

---

## Tab 4 — Connections

> Connecting OpenCompany to the company's existing tools (pillar 9,
> [Connections, Events and Routines](planning/connections-events-routines.md)).
> Audience: workspace admins, developers setting up connections.

```
Connections
├── Overview                         [SOON]   — Connections → Events → Triggers → Routines → Runs; personal vs shared; vault and OAuth broker
│
├── AI Providers
│   ├── Overview                     [NEW]    — How AI model selection works, adding API keys
│   ├── Anthropic Claude             [NEW]    — Setup, supported models, recommended config
│   ├── OpenAI                       [NEW]    — GPT models, API key, organization ID
│   ├── Google Gemini                [NEW]    — Gemini models, service account setup
│   ├── DeepSeek                     [NEW]    — DeepSeek models, API configuration
│   ├── Groq                         [NEW]    — Fast inference, supported models
│   ├── Mistral                      [NEW]    — Mistral models, EU-hosted option
│   ├── xAI Grok                     [NEW]    — Grok models, API access
│   ├── Ollama                       [NEW]    — Local models, self-hosted LLM setup
│   ├── OpenRouter                   [NEW]    — Multi-provider proxy, model routing
│   └── More Providers               [NEW]    — Kimi, MiniMax, GLM, and adding custom providers
│
├── Deep Connectors (Tier 1)
│   ├── Google Drive                 [SOON]   — Storage mount into Files and the VFS (Phase 1)
│   ├── OneDrive & SharePoint        [SOON]   — Storage mount (Phase 3)
│   ├── Other Storage                [SOON]   — Dropbox, Box, S3/R2, WebDAV, SFTP
│   ├── Mailboxes                    [SOON]   — Gmail (Phase 2), Outlook, IMAP; agent and shared inboxes
│   └── Calendars                    [SOON]   — Google, Microsoft, CalDAV (Phase 3)
│
├── Generic Connections (Tier 2)
│   ├── MCP Servers                           — Connect remote MCP servers today; rebuilt client on laravel/mcp planned (Phase 1)
│   ├── API Manifests                [SOON]   — OpenAPI subset as search-and-execute tools
│   ├── Skills & Plugin Import       [SOON]   — SKILL.md skills, plugin bundles, digest-bound approval
│   ├── Inbound Webhooks             [SOON]   — Signed generic webhook connection
│   └── Managed Connectors           [SOON]   — Optional: Activepieces sidecar, or Composio/Pipedream/Zapier with your own key
│
├── Communication
│   ├── Telegram                              — Bot creation, webhook config, message sync
│   ├── Discord                      [SOON]   — Bot setup, Chatogrator adapter path, gateway/interaction hardening
│   ├── Slack                        [SOON]   — App installation, event subscriptions, Chatogrator channel sync
│   └── Microsoft Teams              [SOON]   — Teams app, bot framework, Chatogrator message routing
│
└── ChatGPT / Codex                           — OAuth integration, ChatGPT Pro subscription bridging
```

Removed 2026-11-02: the per-package Visualization (Mermaid, PlantUML, Typst, Vega-Lite, Celestial),
Analytics (Plausible) and generic Webhooks pages. The integration package system is deleted in the
Phase 0 nuclear cleanup; the renderers return as built-in chart and diagram blocks and Typst PDF
export into Files ([Artifacts and Forms](planning/artifacts-and-forms.md)); `render_svg` is app-owned
(see [Visualization Tools](tools/charts.md)).

**31 pages** (11 new, 7 existing, 13 coming soon)

---

## Tab 5 — API Reference

> Complete REST API and configuration reference.
> Audience: developers building on or integrating with OpenCompany.

```
API Reference
├── Overview                         [NEW]    — API-first conventions, OpenAPI contract, base URL, versioning, rate limits
├── Authentication                   [NEW]    — Tokens/OAuth for native clients, session auth, workspace context headers
├── Realtime Stream                  [SOON]   — WebSocket/SSE events for chat, runs, approvals, computer panel
│
├── Endpoints
│   ├── Users & Agents               [EXPAND] — CRUD users, list agents, update profiles
│   ├── Workspaces                   [NEW]    — Create, update, manage members, invitations
│   ├── Channels & Messages          [EXPAND] — Channel CRUD, message send/search, reactions, threads
│   ├── Tasks / Issues               [EXPAND] — Task CRUD, lifecycle actions, steps, filtering (Lists folds into Issues)
│   ├── Documents                    [NEW]    — Document CRUD, versions, comments, attachments, search
│   ├── Calendar                     [NEW]    — Events, attendees, feeds, iCal import/export
│   ├── Tables                       [NEW]    — Table CRUD, columns, rows, bulk operations, views
│   ├── Connections                  [SOON]   — Connection CRUD, OAuth broker, mounts
│   ├── Routines & Triggers          [SOON]   — Routines, triggers, runs (replaces Automations)
│   ├── Events                       [SOON]   — Workspace event log, dead-letter queue
│   ├── Notifications                [NEW]    — List, mark read, mark all read
│   └── Search                       [NEW]    — Global search, filtering, result types
│
├── Agent Configuration                       — Core fields, brain format, status values (behaviour modes deleted)
├── Policy Schema                             — Policy engine: presets, reversibility classes, scoped exceptions, tool-level rules
├── Environment Variables                     — All env vars with descriptions and defaults
├── CLI Commands                              — Artisan commands for admin and maintenance
├── Keyboard Shortcuts               [NEW]    — All keyboard shortcuts and customization
└── Glossary                         [NEW]    — Definitions of domain-specific terms
```

**22 pages** (10 new, 5 existing, 3 expanded, 4 coming soon)

---

## Tab 6 — Self-Hosting

> Everything needed to deploy and operate OpenCompany on your own infrastructure.
> Audience: DevOps, sysadmins, technical founders.

```
Self-Hosting
├── Overview                         [NEW]    — Self-hosting benefits, supported environments
├── Requirements                     [EXPAND] — System specs, PHP/Node versions, database options
├── Installation                     [EXPAND] — Step-by-step deployment on bare metal or Docker
├── Configuration                    [NEW]    — Environment variables, mail, queue, cache, storage
├── Database Setup                   [NEW]    — PostgreSQL with pgvector, SQLite option, migrations
├── Web Server                       [NEW]    — Nginx/Apache config, reverse proxy, Laravel Valet
├── SSL & Domains                    [NEW]    — TLS certificates, custom domains, subdomain routing
├── Background Services              [NEW]    — Queue workers, Reverb WebSocket, scheduler, process management
├── Upgrades                         [NEW]    — Version upgrades, migration strategy, rollback
├── Backup & Recovery                [NEW]    — Database backups, file storage, disaster recovery
└── Troubleshooting                  [NEW]    — Common deployment issues, logs, debugging
```

**11 pages** (9 new — most extracted/expanded from single existing self-hosting page)

---

## Tab 7 — Enterprise

> Security, compliance, and governance features for organizations.
> Audience: IT admins, security teams, compliance officers.

```
Enterprise
├── Overview                                  — Enterprise feature summary and pricing tiers
├── SSO & Identity                            — SAML, OpenID Connect, user provisioning, directory sync
├── Audit Logs                                — Activity logging, export, retention policies
├── Security & Compliance            [NEW]    — Encryption, data handling, GDPR, SOC 2, HIPAA
├── Role-Based Access Control        [NEW]    — Owner/Admin/Member roles, approver policies, Space-level permissions (custom roles later)
└── Data Residency                   [SOON]   — Region selection, data sovereignty, multi-region
```

**6 pages** (2 new, 3 existing, 1 coming soon)

---

## Tab 8 — Resources

> Support materials, community, and project information.
> Audience: everyone.

```
Resources
├── FAQ                              [NEW]    — Common questions about features, pricing, limits
├── Troubleshooting                  [NEW]    — Error messages, common issues, diagnostic steps
├── Changelog                        [NEW]    — Release history, version notes, breaking changes
├── Roadmap                          [NEW]    — Planned features, timeline, community voting
├── Contributing                     [NEW]    — How to contribute, development setup, code style
└── Community                        [NEW]    — Discord server, GitHub discussions, support channels
```

**6 pages** (all new)

---

## Summary

| Tab | Pages | Existing | New | Coming Soon |
|-----|-------|----------|-----|-------------|
| Getting Started | 9 | 5 | 4 | — |
| User Guide | 43 | 12 | 10 | 21 |
| Technical | 12 | 7 | 4 | 1 |
| Connections | 31 | 7 | 11 | 13 |
| API Reference | 22 | 8 | 10 | 4 |
| Self-Hosting | 11 | 1 | 10 | — |
| Enterprise | 6 | 3 | 2 | 1 |
| Resources | 6 | — | 6 | — |
| **Total** | **140** | **43** | **57** | **40** |

---

## Navigation Model

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  Logo    Getting Started  User Guide  Technical  Connections   ...    Search │  ← Header
├────────┬─────────────────────────────────────────────────────────────────────┤
│        │                                                                     │
│  Side  │                     Page Content                                    │
│  bar   │                                                                     │
│        │  ┌─────────────┐                                                    │
│  • Pg  │  │  Callout /  │                                                    │
│  • Pg  │  │  CardGrid   │                                                    │
│  ○ Pg  │  │             │                                                    │
│    ├ S │  └─────────────┘                                                    │
│    ├ S │                                                                     │
│    └ S │                                                                     │
│  • Pg  │                                    ┌──────────┬──────────┐          │
│  • Pg  │                                    │ Previous │   Next   │          │
│        │                                    └──────────┴──────────┘          │
├────────┴─────────────────────────────────────────────────────────────────────┤
│  Footer                                                                      │
└──────────────────────────────────────────────────────────────────────────────┘

• = page    ○ = page with children    S = sub-page (indented)
Active tab highlighted in header. Sidebar shows pages for current tab only.
Sub-pages collapse/expand under parent. Previous/Next links at page bottom.
```

---

## Writing Priorities

Pages should be created in this order to maximize value:

1. **Getting Started** — What is OpenCompany, Core Concepts, Quick Start (chat-first, per the Product Direction)
2. **User Guide: Home, Chats and Spaces** — The core navigation of the accepted UI, as it lands
3. **User Guide: Messenger, Connections and Routines** — As each Phase 1 surface lands
4. **API Reference: Overview + Authentication** — Unblocks developer adoption
5. **Self-Hosting** — Expand single page into full deployment guide
6. **Resources: FAQ + Troubleshooting** — Reduces support load
7. **Connections: AI Providers** — Each provider gets a setup page
8. **API Reference: Endpoint pages** — Break monolithic API page into per-resource docs
9. **User Guide: sub-pages** — Channel types, document versioning, etc.
10. **Enterprise: Security, RBAC** — Unlocks enterprise sales

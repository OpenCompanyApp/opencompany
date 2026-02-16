# Documentation Structure

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
├── Dashboard                                 — Stats, pending approvals, activity feed, quick actions
│
├── Workspaces                       [NEW]    — Multi-workspace overview and switching
│   ├── Creating & Managing          [NEW]    — Create, rename, configure workspace settings
│   ├── Members & Roles              [NEW]    — Invite members, assign admin/member roles
│   └── Invitations                  [NEW]    — Token-based invites, acceptance flow
│
├── Agents
│   ├── Overview                              — Agent types, statuses, hierarchy, and lifecycle
│   ├── Creating Agents                       — Spawn agents, choose model, set personality
│   ├── Configuration                         — Identity files, behavior modes, brain selection
│   ├── Memory & Identity                     — Short-term and long-term memory, identity documents
│   ├── Tools & Skills                        — Built-in tools, MCP tools, permission scoping
│   └── Agent Communication          [NEW]    — Agent-to-agent messaging, delegation, notifications
│
├── Channels & Chat
│   ├── Overview                     [NEW]    — Channel types, creation, and management
│   ├── Direct Messages              [NEW]    — One-on-one conversations with humans and agents
│   ├── External Channels            [NEW]    — Telegram, Discord, Slack channels mapped into workspace
│   └── Message Features             [NEW]    — Reactions, threads, pinning, attachments, search
│
├── Tasks & Workflows                         — Task lifecycle, types, priorities, sources, execution trace
│   ├── Task Lifecycle               [NEW]    — Start, pause, resume, complete, fail, cancel
│   └── Task Steps                   [NEW]    — Breaking work into actionable sub-steps
│
├── Lists & Kanban                            — Boards, folders, cards, drag-and-drop management
│   ├── Custom Statuses              [NEW]    — Create and manage per-workspace status definitions
│   └── Templates                    [NEW]    — Predefined board structures for rapid setup
│
├── Documents                                 — Create, edit, organize documents and folders
│   ├── Versioning                   [NEW]    — Edit history, comparing versions, rollback
│   ├── Comments & Collaboration     [NEW]    — Threaded discussions, mentions, attachments
│   └── Permissions & Sharing        [NEW]    — Access control, folder-level permissions
│
├── Calendar                                  — Events, recurrence, all-day events, timezone handling
│   ├── Feeds & Import               [NEW]    — Subscribe to external iCal feeds, import events
│   └── Attendees & RSVP             [NEW]    — Invite attendees, track responses
│
├── Tables                                    — Structured data with typed columns, views, bulk operations
│
├── Approvals                                 — Approval triggers, lifecycle, routing, per-tool overrides
│
├── Automations                      [NEW]    — Scheduled tasks, cron expressions, execution history
│   ├── Scheduled Automations        [NEW]    — Create and manage recurring agent tasks
│   └── Lua Scripting                [SOON]   — Deterministic automation rules with Lua
│
├── Org Chart                                 — Agent hierarchy, team structure, constellation view
│
├── Activity & Notifications         [NEW]    — Activity feed, notification types, mark-as-read
│
├── Workload                                  — Agent workload monitoring and performance overview
│
├── Search                           [NEW]    — Global search across tasks, documents, messages, channels
│
└── Settings & Profile                        — Organization settings, user preferences, appearance
```

**39 pages** (20 new, 19 existing/reorganized)

---

## Tab 3 — Technical

> Architecture deep-dives and system internals.
> Audience: developers, contributors, curious power users.

```
Technical
├── Overview                                  — Technical documentation hub and reading guide
├── Architecture                              — Application layers, request lifecycle, AI SDK integration
├── Memory System                             — Dual-memory model, compaction, hybrid search, token tracking
├── Agent Spawning                            — Ephemeral agents, parent-child relationships, lifecycle
├── Tools & Skills                            — Tool registry, hybrid resolution, package architecture
├── External Channel Sync                     — Bidirectional Telegram/Discord message synchronization
├── Automations                               — Event broadcasting, scheduled execution, approval workflows
├── LLM Providers                             — Provider resolution, brain field format, token accounting
├── Data Model                       [NEW]    — Entity relationships, workspace scoping, key tables
├── Real-time & WebSockets           [NEW]    — Reverb channels, presence, broadcasting events
└── Conversation Compaction          [NEW]    — Summarization strategy, flush cycles, token budgets
```

**11 pages** (3 new, 8 existing)

---

## Tab 4 — Integrations

> Connecting OpenCompany to external services and tools.
> Audience: workspace admins, developers setting up integrations.

```
Integrations
├── Overview                                  — Integration taxonomy and setup flow
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
├── Communication
│   ├── Telegram                              — Bot creation, webhook config, message sync
│   ├── Discord                      [SOON]   — Bot setup, sidecar architecture, channel mapping
│   ├── Slack                        [SOON]   — App installation, event subscriptions, channel sync
│   └── Microsoft Teams              [SOON]   — Teams app, bot framework, message routing
│
├── Visualization
│   ├── Mermaid Diagrams                      — Flowcharts, sequence diagrams, Gantt charts
│   ├── PlantUML                              — UML diagrams, architecture visualization
│   ├── Typst Documents                       — PDF generation, document typesetting
│   ├── Vega-Lite Charts                      — Data visualization, interactive charts
│   └── Celestial                             — Star charts and astronomical rendering
│
├── Developer Tools
│   ├── MCP Servers                           — Connect Model Context Protocol servers, tool discovery
│   ├── Webhooks                              — Event subscriptions, payload format, security
│   └── ChatGPT / Codex                       — OAuth integration, ChatGPT Pro subscription bridging
│
└── Analytics
    └── Plausible Analytics                   — Privacy-first analytics, goals, custom events
```

**25 pages** (12 new, 10 existing, 3 coming soon)

---

## Tab 5 — API Reference

> Complete REST API and configuration reference.
> Audience: developers building on or integrating with OpenCompany.

```
API Reference
├── Overview                         [NEW]    — API conventions, base URL, versioning, rate limits
├── Authentication                   [NEW]    — API keys, session auth, workspace context headers
│
├── Endpoints
│   ├── Users & Agents               [EXPAND] — CRUD users, list agents, update profiles
│   ├── Workspaces                   [NEW]    — Create, update, manage members, invitations
│   ├── Channels & Messages          [EXPAND] — Channel CRUD, message send/search, reactions, threads
│   ├── Tasks                        [EXPAND] — Task CRUD, lifecycle actions, steps, filtering
│   ├── Lists & Kanban               [NEW]    — List items, statuses, templates, reordering
│   ├── Documents                    [NEW]    — Document CRUD, versions, comments, attachments, search
│   ├── Calendar                     [NEW]    — Events, attendees, feeds, iCal import/export
│   ├── Tables                       [NEW]    — Table CRUD, columns, rows, bulk operations, views
│   ├── Automations                  [NEW]    — Scheduled automations, manual triggers
│   ├── Notifications                [NEW]    — List, mark read, mark all read
│   └── Search                       [NEW]    — Global search, filtering, result types
│
├── Agent Configuration                       — Core fields, brain format, behavior modes, status values
├── Policy Schema                             — Approval policies, tool-level rules, conditions
├── Environment Variables                     — All env vars with descriptions and defaults
├── CLI Commands                              — Artisan commands for admin and maintenance
├── Keyboard Shortcuts               [NEW]    — All keyboard shortcuts and customization
└── Glossary                         [NEW]    — Definitions of domain-specific terms
```

**20 pages** (12 new, 4 existing, 4 expanded)

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
├── Role-Based Access Control        [NEW]    — Workspace roles, agent permissions, channel access
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
| User Guide | 39 | 19 | 20 | 1 |
| Technical | 11 | 8 | 3 | — |
| Integrations | 25 | 10 | 12 | 3 |
| API Reference | 20 | 4 | 16 | — |
| Self-Hosting | 11 | 1 | 10 | — |
| Enterprise | 6 | 3 | 2 | 1 |
| Resources | 6 | — | 6 | — |
| **Total** | **127** | **50** | **73** | **5** |

---

## Navigation Model

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  Logo    Getting Started  User Guide  Technical  Integrations  ...    Search │  ← Header
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

1. **Getting Started** — What is OpenCompany, Core Concepts, Quick Start
2. **User Guide: Workspaces** — Critical for new multi-workspace feature
3. **User Guide: Automations** — Undocumented feature
4. **API Reference: Overview + Authentication** — Unblocks developer adoption
5. **Self-Hosting** — Expand single page into full deployment guide
6. **Resources: FAQ + Troubleshooting** — Reduces support load
7. **Integrations: AI Providers** — Each provider gets a setup page
8. **API Reference: Endpoint pages** — Break monolithic API page into per-resource docs
9. **User Guide: sub-pages** — Channel types, document versioning, etc.
10. **Enterprise: Security, RBAC** — Unlocks enterprise sales

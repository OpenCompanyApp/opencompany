# Emergent Insights

> Non-obvious findings, gaps, risks, and opportunities discovered during a comprehensive codebase audit (February 2026). This document captures things a product owner should know but might not notice from day-to-day development.

---

## 1. Implementation vs Marketing Gap

The `features.md` marketing page makes claims that significantly exceed current implementation. This is fine for a roadmap — but risky if the page goes live before the code catches up.

### Core differentiators that don't exist yet

| Feature | Marketing Status | Code Status | Risk |
|---------|-----------------|-------------|------|
| **Sub-agent spawning** | Extensive section (6 features, queue modes, heartbeat) | Zero code | HIGH — this is the "Robo-Company" vision |
| **MCP server** | Listed as capability | Not started | HIGH — key for developer adoption |
| **Skills/custom tools** | Full section (5 features, marketplace) | Not started | HIGH — extensibility story |
| **Plugin system** | 10 capability types described | Not started | MEDIUM — can wait for post-MVP |
| **Multi-step workflows** | Section with 5 features | Not started | HIGH — tied to agent execution |
| **External channels** | 8 providers listed | 2 working (Telegram, Slack) | HIGH — 75% gap |
| **Memory/search** | Hybrid vector + BM25, embeddings, citations | Basic ILIKE pattern matching | HIGH — core differentiator |
| **Agent execution engine** | Implied throughout | Not started | CRITICAL — nothing makes agents "think" yet |

### Recommendation

Before any public launch, either:
1. **Scale back features.md** to only list what's built (honest approach), or
2. **Prioritize the top 3 gaps** (agent execution, sub-agents, memory search) to deliver the core promise

---

## 2. Hidden Gems — Fully Built but Under-Marketed

These features are complete in the codebase but barely mentioned in marketing. They're "free" wins.

### Data Tables (the Airtable competitor)

- **What exists:** Full CRUD with 10+ column types (text, number, date, select, multiselect, checkbox, URL, email, user, attachment), 4 view modes (grid, kanban, gallery, calendar), bulk operations, column reordering
- **Marketing mention:** One line in landing-page.md, zero lines in features.md
- **Opportunity:** No competitor combines Airtable-like structured data with AI agents. This is genuinely unique. Feature it prominently.

### Calendar Events

- **What exists:** Full CRUD with attendee management, status tracking (pending/accepted/declined/tentative), recurrence rules
- **Marketing mention:** Not in features.md at all
- **Opportunity:** Calendar + agents = "agents that schedule meetings, track deadlines, create events." Compelling for enterprise demos.

### Search API

- **What exists:** Cross-entity search across users, channels, messages, tasks, and documents
- **Marketing mention:** Brief mention, undersells capability
- **Opportunity:** "Search everything in your workspace" is a table-stakes feature that competitors often lack.

### List Templates

- **What exists:** Template system for creating kanban items from predefined configurations
- **Marketing mention:** Minimal
- **Opportunity:** Templates + automation rules = "create standardized workflows" — useful for enterprise customers.

---

## 3. The Naming Confusion Problem

The Tasks → ListItems rename created terminology debt that can confuse new developers.

### The problem

| What the code says | What it means | What a developer might think |
|-------------------|---------------|------------------------------|
| `task_created` (automation trigger) | A **ListItem** was created | An agent **Task** was created |
| `assign_task` (automation action) | Assign a **ListItem** | Assign an agent **Task** |
| `AutomationRuleController` | Manages **ListAutomation** rules | Could be for any automation |
| `TaskController` | Agent work cases | The old kanban tasks |
| `ListItemController` | Kanban board items | Something new/unfamiliar |

### Additional mismatches

- `coordinator` agent type exists in TypeScript enum but is never seeded — new developers won't see it in demos
- `ExternalChannelProvider` enum has 4 entries but marketing promises 8
- `private` and `agent` channel types are defined but never seeded

### Recommendations

1. Rename automation triggers: `task_created` → `list_item_created`, `assign_task` → `assign_list_item`
2. Or: add prominent comments in seeders explaining the distinction
3. Seed all defined types (add coordinator agent, private channel) so demos match type definitions

---

## 4. Architecture Strengths

Things that are done well and should be preserved:

- **UUID primary keys throughout** — no integer ID leakage, proper for multi-tenant future
- **Two-system task design** — ListItems for human project management, Tasks for agent work. This separation is genuinely novel and prevents the "everything is a task" confusion other platforms have.
- **Inertia.js + Vue 3** — SPA feel without maintaining a separate API layer. Pages get server-side data via props.
- **Laravel Reverb** for WebSockets — avoids Pusher/third-party dependency for real-time features
- **AgentDocumentService** — clean abstraction for agent document management (identity files, memory logs, daily logs). Good foundation for the QMD memory system.
- **Realistic seeder data** — 7 users (1 human + 6 agents), 10 channels, 13 list items, 13 tasks with varied statuses. New developers get a realistic workspace immediately.
- **Composable-based API layer** — `useApi()` centralizes all API calls with consistent error handling and TypeScript types

---

## 5. Strategic Opportunities

### Data Tables + AI Agents = Unique Positioning

No competitor offers structured data tables alongside AI agents. Competitors either have agents (CrewAI, AutoGen) or structured data (Airtable, Notion) — never both. OpenCompany could own the "AI agents that work with your data" narrative.

**Demo scenario:** "Agent analyzes customer feedback from a Data Table, creates tasks for each issue, and updates the table with resolution status."

### Self-Hosting as Competitive Advantage

The "Sustainable Use License" blocks hosted services but explicitly allows self-hosting. In a market where enterprises are increasingly wary of sending data to cloud AI services, "run it on your own servers" is powerful. Lean into this harder in marketing.

### Task Steps as Transparency Feature

Step-by-step tracking of agent work (`TaskStep` model with types: action, decision, approval, sub_task, message) is rare among competitors. Most agent platforms show a black box. OpenCompany shows every step. This directly addresses the enterprise concern: "What is the AI doing?"

**Marketing angle:** "Full visibility into every decision your agents make."

### Calendar + Agent Integration

Calendar is built. Agents exist. Connecting them creates compelling scenarios:
- Agents create calendar events for deadlines they discover
- Agents check calendar availability before scheduling meetings
- Agents send pre-meeting prep summaries based on calendar events

### External Channel Bridge

Even with just Slack + Telegram working, this is more than most agent platforms offer. Frame it as: "Bring AI agents to where your team already works — no new app to learn."

---

## 6. Risks to Address

### Overpromise Risk (HIGH)

If `features.md` goes live as-is, users will expect sub-agents, MCP server, 8 external channels, hybrid memory search, skills marketplace, and a plugin system. None of these exist. The gap between promise and reality is the single biggest risk.

### No Agent Execution Engine (CRITICAL)

All the infrastructure is ready — channels for communication, tasks for work tracking, documents for knowledge, memory for context. But there is no code that makes an agent actually "think." No LLM calls, no tool execution, no workflow orchestration. The agent "brain" is the #1 missing piece.

### Test Coverage (HIGH)

QA strategy documents ~5% overall coverage, 0% API tests, 0% model tests, 0% component tests. Any significant refactor is risky. The Tasks→ListItems rename was done without test safety nets.

### Search Scalability (MEDIUM)

Every major feature needs search: agent memory, documents, messages, channels, tasks. The current implementation is ILIKE pattern matching in PostgreSQL. This works for demos but won't scale. The QMD/pgvector architecture is designed but not implemented.

### Credit System Ghost (LOW, being fixed)

Credits were removed but references lingered across 6+ documentation files. The replacement cost-tracking strategy isn't designed yet. Agents need some way to track costs — the current approach appears to be token counting via the AI SDK, but there's no explicit cost tracking model.

---

## 7. Quick Wins

Low-effort changes that would immediately improve the project:

| # | Change | Effort | Impact |
|---|--------|--------|--------|
| 1 | Add `coordinator` agent to UserSeeder | 10 min | All 7 TypeScript agent types demonstrated |
| 2 | Seed a `private` channel in ChannelSeeder | 5 min | All channel types visible in demos |
| 3 | Add Data Tables section to `features.md` | 15 min | Major feature gets marketing visibility |
| 4 | Add Calendar section to `features.md` | 10 min | Built feature gets marketing visibility |
| 5 | Create empty `config/memory.php` with structure | 10 min | Signals architecture direction, unblocks config references |
| 6 | Add TaskSteps to AgentTaskSeeder | 15 min | Task detail view shows step tracking in demos |
| 7 | Update `ExternalChannelProvider` type to only list implemented providers | 5 min | Types match reality |

---

## 8. Priority Matrix

What to build next, based on impact vs effort:

### Must-have before launch
1. **Agent execution engine** — Without this, agents can't do anything
2. **Memory search** (at least basic hybrid) — Agents need memory to be useful
3. **Honest features.md** — Don't launch with unimplemented features listed

### High impact, medium effort
4. **Sub-agent spawning** — Core differentiator, enables the "Robo-Company" vision
5. **At least 1 more external channel** (Discord) — Shows the integration story works
6. **Data Tables marketing** — Already built, just needs visibility

### Nice-to-have
7. MCP server — Developer adoption tool
8. Skills system — Extensibility story
9. Plugin system — Ecosystem play
10. Additional external channels — Scale the integration story

---

*Last updated: February 2026*
*Source: Automated codebase audit cross-referencing 19 documentation files against models, routes, migrations, types, and seeders.*

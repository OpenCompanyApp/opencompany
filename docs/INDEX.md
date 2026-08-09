# Documentation Index

> Quick reference to OpenCompany's tracked documentation. Each doc serves a specific purpose - use the "Read when" column to find what you need. Some imported research and planning docs intentionally describe future, historical, or external-system architecture; those rows call that out so they are not mistaken for current app behavior.

---

## Architecture & Technical

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [openclaw-reference.md](architecture/openclaw-reference.md) | OpenClaw's agent, memory, skills, QMD, and plugin architecture (reference material) | Designing agent features, understanding source patterns |
| [openclaw-patterns.md](architecture/openclaw-patterns.md) | Which OpenClaw patterns to adopt, adapt, or skip for OpenCompany | Planning new agent capabilities |
| [laravel-ai-sdk.md](architecture/laravel-ai-sdk.md) | Historical Laravel AI SDK strategy - providers, tools, streaming, memory, workflows, QMD adaptation. Current runtime details live in [ai-provider-runtime-architecture.md](architecture/ai-provider-runtime-architecture.md). | Understanding earlier AI architecture decisions |
| [technology-decisions.md](architecture/technology-decisions.md) | Tech stack choices: Laravel AI SDK, app-owned provider runtime, Laravel queues for orchestration | Understanding why we chose what |
| [observability.md](architecture/observability.md) | Proposal for monitoring, metrics, logging, error tracking, health checks, and alerting; planned file paths are not current inventory unless they exist | Planning admin/ops features |
| [ai-tool-packages.md](architecture/ai-tool-packages.md) | AI tool package ecosystem strategy - ToolProvider contract, credential abstraction, hybrid ToolRegistry, building new tool packages | Creating or modifying AI tool packages, understanding package architecture |
| [embedded-chromium-browser-investigation-2026-05-24.md](architecture/embedded-chromium-browser-investigation-2026-05-24.md) | Feasibility research for remote Chromium browser sessions with user live control, agent automation, provider options, and OpenCompany runtime/security boundaries | Planning browser automation, web research, or human-in-the-loop browsing features |
| [web-search-fetch-adapter-investigation-2026-05-24.md](architecture/web-search-fetch-adapter-investigation-2026-05-24.md) | Historical adapter investigation plus the current non-browser `web_search`/`web_fetch` provider, caching, usage, diagnostics, safety, and test baseline | Auditing or extending regular web research/fetch tools before browser automation |
| [telegram-chatogrator-capabilities-investigation-2026-05-24.md](architecture/telegram-chatogrator-capabilities-investigation-2026-05-24.md) | Research-only comparison of OpenCompany Telegram/Chatogrator, KosmoKrator Telegram gateway, current Hermes Telegram UX, provider abstraction differences, and current Telegram Bot API capabilities | Investigating Telegram/chat-provider capabilities before deciding any product direction |
| [telegram-world-class-implementation-overview-2026-05-24.md](architecture/telegram-world-class-implementation-overview-2026-05-24.md) | Agent-first Telegram target overview: switch agents/topics, run status, approvals, and attachments as agent context instead of a broad product-module command center | Rethinking or auditing Telegram UX direction |
| [telegram-world-class-implementation-plan-2026-05-24.md](architecture/telegram-world-class-implementation-plan-2026-05-24.md) | Phased reset plan for the agent-first Telegram implementation, including visible command shrinkage, agent/topic switching, media-as-context, approvals/run controls, Mini App as advanced drawer, and default-off legacy resource commands | Implementing or continuing the Telegram UX reset |
| [link-agent-wallets-investigation.md](architecture/link-agent-wallets-investigation.md) | Stripe Link Agents investigation — agent wallet/payment credential flow, MCP fit, and OpenCompany integration risks | Evaluating payment-agent capabilities or planning Link integration |
| [interagent-comms.md](architecture/interagent-comms.md) | Inter-agent communication protocol — ContactAgent tool with ask/delegate/notify patterns, DM channels, delegation tracking | Building or debugging agent-to-agent communication |
| [kosmokrator-reuse-audit.md](architecture/kosmokrator-reuse-audit.md) | Full audit of what OpenCompany should reuse, adapt, or skip from KosmoKrator | Planning cross-repo reuse, agent runtime work, metadata consolidation |
| [kosmokrator-opencompany-runtime-comparison-2026-05-11.md](architecture/kosmokrator-opencompany-runtime-comparison-2026-05-11.md) | Fresh runtime comparison of KosmoKrator and OpenCompany — agent runtime, integrations, providers, MCP, subagents, and migration priorities | Planning the next OpenCompany runtime extraction work |
| [domain-driven-design-architecture.md](architecture/domain-driven-design-architecture.md) | Proposed DDD modular-monolith architecture - bounded contexts, ownership rules, migration phases, and before/after examples | Planning maintainable long-term backend structure |
| [maintainability-refactor-audit-2026-05-19.md](architecture/maintainability-refactor-audit-2026-05-19.md) | Fresh maintainability audit with prioritized refactor opportunities and the calendar domain extraction completed during the audit | Planning the next backend cleanup pass |
| [ai-provider-runtime-architecture.md](architecture/ai-provider-runtime-architecture.md) | Current provider/model catalog, Laravel AI runtime registration, prompt-cache, and LLM usage/cost ledger architecture | Working on AI providers, model metadata, cost analytics, or OpenRouter billing |
| [runtime-alignment-implementation-audit.md](architecture/runtime-alignment-implementation-audit.md) | Post-implementation audit — findings now tracked as Plane issues (OC-1 through OC-6) | Reviewing audit results and fix status |
| [wayfinder-routing.md](architecture/wayfinder-routing.md) | Current Wayfinder route/action generation conventions, frontend usage rules, and Docker build contract | Working on Vue navigation, API calls, Laravel routes, or asset builds |
| [docs-code-alignment-audit-2026-05-24.md](architecture/docs-code-alignment-audit-2026-05-24.md) | In-progress docs/code alignment audit evidence and remaining work | Continuing the all-docs cross-check |
| [integrations-catalog-branch-audit-2026-05-10.md](architecture/integrations-catalog-branch-audit-2026-05-10.md) | Historical May 10 branch audit of integration-catalog gaps, with May 24 notes for resolved OAuth, catalog, paging, and dedupe work | Understanding why the integration-catalog hardening work happened |
| [pest-browser-testing-implementation-plan-2026-05-10.md](architecture/pest-browser-testing-implementation-plan-2026-05-10.md) | Pest Browser/Playwright migration plan. The current tree has both Dusk browser tests and new Pest browser specs. | Reviewing or continuing browser-test migration |
| [documentation.md](documentation.md) | Product documentation outline and information architecture | Updating user-facing docs or navigation |
| [ai-providers.md](ai-providers.md) | AI provider inventory and implementation priority using the current OpenCompany AI Runtime | Evaluating provider support or adding model integrations |

## Ecosystem & Integrations

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [ecosystem/integrations/](ecosystem/integrations/) | Integration package authoring reference, language-neutral script bridge, credentials, triggers, publishing flow, and generated JavaScript Code Mode docs | Building or updating OpenCompany integration packages |
| [ecosystem/integrations/core/README.md](ecosystem/integrations/core/README.md) | Shared integration-core contracts and primitives used by package implementations | Changing common package behavior or reviewing package ownership boundaries |
| [ecosystem/integrations/celestial/README.md](ecosystem/integrations/celestial/README.md) | Celestial integration package reference | Working on the Celestial package |
| [ecosystem/integrations/clickup/README.md](ecosystem/integrations/clickup/README.md) | ClickUp integration package reference | Working on the ClickUp package |
| [ecosystem/integrations/coingecko/README.md](ecosystem/integrations/coingecko/README.md) | CoinGecko integration package reference | Working on the CoinGecko package |
| [ecosystem/integrations/exchangerate/README.md](ecosystem/integrations/exchangerate/README.md) | ExchangeRate integration package reference | Working on exchange-rate tools |
| [ecosystem/integrations/google/README.md](ecosystem/integrations/google/README.md) | Google integration package reference | Working on Google OAuth/API tools |
| [ecosystem/integrations/mermaid/README.md](ecosystem/integrations/mermaid/README.md) | Mermaid integration package reference | Working on diagram rendering tools |
| [ecosystem/integrations/plausible/README.md](ecosystem/integrations/plausible/README.md) | Plausible integration package reference | Working on Plausible analytics tools |
| [ecosystem/integrations/ticktick/README.md](ecosystem/integrations/ticktick/README.md) | TickTick integration package reference | Working on TickTick task tools |
| [ecosystem/integrations/trustmrr/README.md](ecosystem/integrations/trustmrr/README.md) | TrustMRR integration package reference | Working on TrustMRR verification tools |
| [ecosystem/integrations/worldbank/README.md](ecosystem/integrations/worldbank/README.md) | World Bank integration package reference | Working on World Bank data tools |
| [ecosystem/iris/ecosystem-overview.md](ecosystem/iris/ecosystem-overview.md) | Imported Iris ecosystem overview and reusable architecture notes | Comparing OpenCompany with Iris ideas |
| [ecosystem/iris/missing-in-iris.md](ecosystem/iris/missing-in-iris.md) | Iris capability gaps and missing features | Evaluating whether an Iris concept should move into OpenCompany |
| [ecosystem/kosmokrator/README.md](ecosystem/kosmokrator/README.md) | Imported KosmoKrator documentation index | Navigating the imported KosmoKrator research corpus |
| [ecosystem/kosmokrator/architecture/](ecosystem/kosmokrator/architecture/) | Imported KosmoKrator architecture docs, including overview, permission modes, and subagent architecture | Studying reusable runtime and agent patterns |
| [ecosystem/kosmokrator/audits/](ecosystem/kosmokrator/audits/) | Imported KosmoKrator audit reports, including RAM and memory-leak audits | Reviewing external audit findings for reusable lessons |
| [ecosystem/kosmokrator/audits/ram-audit/](ecosystem/kosmokrator/audits/ram-audit/) | Imported KosmoKrator RAM-efficiency audit and synthesis reports | Reviewing memory/performance findings in detail |
| [ecosystem/kosmokrator/deep-audit-2026-04-04.md](ecosystem/kosmokrator/deep-audit-2026-04-04.md) | Imported root-level KosmoKrator deep audit | Reviewing external audit findings that are outside the nested audits directory |
| [ecosystem/kosmokrator/proposals/](ecosystem/kosmokrator/proposals/) | Imported KosmoKrator proposals for context, desktop UX, integrations, streaming, and TUI work | Looking for product/runtime inspiration |
| [ecosystem/kosmokrator/reports/](ecosystem/kosmokrator/reports/) | Imported KosmoKrator reports, including sub-agent swarm gap analysis | Reviewing external runtime/product audit reports |
| [ecosystem/kosmokrator/research/](ecosystem/kosmokrator/research/) | Imported research on Claude Code, OpenCode, and related coding-agent systems | Comparing agent product patterns |
| [ecosystem/kosmokrator/tools/](ecosystem/kosmokrator/tools/) | Imported web-tool specifications and prompts | Designing or auditing browser/web-fetch tools |

## Planning & Implementation

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [memory-implementation.md](planning/memory-implementation.md) | Memory system architecture reference — STM/LTM model, phase summary **(Status: Complete)** | Understanding the memory architecture |
| [kosmokrator-runtime-alignment-checklist.md](planning/kosmokrator-runtime-alignment-checklist.md) | Checklist for aligning runtime behavior with KosmoKrator patterns — completed work + pointers to open Plane issues | Reviewing runtime-alignment status |
| [external-channel-sync.md](external-channel-sync.md) | Bidirectional sync design for Chatogrator-backed external channels — message tracking, edit/pin/react sync, channel discovery, and provider hardening status | Working on external platform integration |
| [discord.md](discord.md) | Historical Discord sidecar proposal plus current Chatogrator adapter/webhook pointer | Evaluating Discord integration design history before implementing current work |
| [automation-endpoints-artifacts.md](planning/automation-endpoints-artifacts.md) | Automation endpoint and agent-built artifact implementation plan | Working on automation API, generated artifacts, or dashboard-like internal tools |
| [chatogrator.md](planning/chatogrator.md) | Chat integration and ChatOgrator planning | Planning chat-side package/runtime behavior |
| [codex-subscription-auth.md](planning/codex-subscription-auth.md) | Codex subscription authentication planning | Working on Codex integration |
| [dream-vfs.md](planning/dream-vfs.md) | Dream VFS and memory consolidation planning | Evaluating future memory/file abstractions |
| [unified-vfs-overview.md](planning/unified-vfs-overview.md) | Unified virtual filesystem direction — one agent-facing filesystem over docs, files, memory, tasks, channels, tables, tools, and workspace objects | Planning the full VFS architecture before implementation |
| [integration-refactor.md](planning/integration-refactor.md) | Integration refactor strategy and implementation notes | Working on package/runtime integration cleanup |
| [integrations.md](planning/integrations.md) | Integrations product and architecture planning | Planning integration features or package scope |
| [laravel-mcp-client.md](planning/laravel-mcp-client.md) | Laravel MCP client planning | Building or evaluating MCP client support |
| [quickjs-code-mode-migration.md](planning/quickjs-code-mode-migration.md) | Approved hard-cut implementation plan from Lua/Luau to synchronous JavaScript on QuickJS, including shared integration contracts, security budgets, persisted scripts, UI, deployment, and removal gates | Implementing or reviewing the QuickJS Code Mode migration |
| [code-mode-agent-experience.md](architecture/code-mode-agent-experience.md) | Code Mode discovery, validation, execution, effects, retry, trace, and evaluation contracts | Reviewing or improving agent experience for programmatic tool use |
| [memory-systems.md](planning/memory-systems.md) | Memory-system research and roadmap notes | Comparing memory implementation options |
| [skill-system.md](planning/skill-system.md) | Skill-system planning for reusable agent capabilities | Designing skill packaging or execution |
| [vector-optional.md](planning/vector-optional.md) | Optional vector-search architecture | Deciding whether vector storage belongs in a feature |
| [visual-programming-research.md](planning/visual-programming-research.md) | Visual workflow/programming research | Planning visual builder experiences |

## Testing & QA

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [qa-strategy.md](testing/qa-strategy.md) | Testing pyramid, CI/CD pipeline, coverage targets, test data management | Setting up automated test infrastructure |

## Audits

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [vendor-open-source-audit-2026-04-10.md](vendor-open-source-audit-2026-04-10.md) | Open-source vendor/package audit snapshot | Reviewing dependency and licensing risk |
| [vendor-open-source-critical-audit-2026-04-10-round-2.md](vendor-open-source-critical-audit-2026-04-10-round-2.md) | Critical follow-up vendor audit snapshot | Reviewing high-priority dependency risk |

## Tools & Features

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [charts.md](tools/charts.md) | Visualization tools reference — render_svg, render_vegalite, render_mermaid, render_plantuml, render_typst | Using or extending visualization tools |

## UI/UX

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [design-system.md](ui/design-system.md) | Design tokens, colors, typography, spacing, animations, accessibility | Building new UI components, choosing colors or spacing |
| [components.md](ui/components.md) | All 28 shared components with props, slots, events, and usage | Using a shared component, checking available props |
| [layouts.md](ui/layouts.md) | Common layout patterns with ASCII diagrams, responsive breakdowns | Designing a new page, understanding how layouts work |
| [pages/activity.md](ui/pages/activity.md) | Activity page specification | Working on activity feeds |
| [pages/agent-detail.md](ui/pages/agent-detail.md) | Agent detail page specification | Working on agent profile/detail behavior |
| [pages/approvals.md](ui/pages/approvals.md) | Approvals page specification | Working on approval queues |
| [pages/auth.md](ui/pages/auth.md) | Auth page specification | Working on login/register flows |
| [pages/automation.md](ui/pages/automation.md) | Automation page specification | Working on automation UI |
| [pages/calendar.md](ui/pages/calendar.md) | Calendar page specification | Working on calendar UI |
| [pages/chat.md](ui/pages/chat.md) | Chat page specification | Working on chat UI |
| [pages/dashboard.md](ui/pages/dashboard.md) | Dashboard page specification | Working on dashboard UI |
| [pages/developer.md](ui/pages/developer.md) | Developer tool catalog and QuickJS Code Console specification | Working on developer tool reference or script execution UI |
| [pages/docs.md](ui/pages/docs.md) | Docs page specification | Working on document viewer UI |
| [pages/files.md](ui/pages/files.md) | Files page specification | Working on workspace file manager UI |
| [pages/integrations.md](ui/pages/integrations.md) | Integrations page specification | Working on integration UI |
| [pages/lists.md](ui/pages/lists.md) | Lists page specification | Working on list/board UI |
| [pages/messages.md](ui/pages/messages.md) | Direct-message redirect and legacy component notes | Working on DM links or Chat redirect compatibility |
| [pages/org.md](ui/pages/org.md) | Organization page specification | Working on organization settings |
| [pages/profile.md](ui/pages/profile.md) | Profile page specification | Working on user profile UI |
| [pages/settings.md](ui/pages/settings.md) | Settings page specification | Working on workspace settings |
| [pages/tables.md](ui/pages/tables.md) | Tables index page specification | Working on database/table index UI |
| [pages/tables-detail.md](ui/pages/tables-detail.md) | Table detail page specification | Working on table detail UI |
| [pages/tasks.md](ui/pages/tasks.md) | Agent tasks page specification | Working on agent case/task UI |
| [pages/welcome.md](ui/pages/welcome.md) | Welcome page specification | Working on onboarding/welcome UI |
| [pages/workspace.md](ui/pages/workspace.md) | Workspace setup, creation, and invitation page specification | Working on onboarding or workspace creation flows |
| [pages/workload.md](ui/pages/workload.md) | Workload page specification | Working on workload planning UI |

---

## Key Concepts

| Term | Meaning |
|------|---------|
| **ListItems** | Kanban board items at `/lists` — user-managed todo items (formerly "Tasks") |
| **Tasks** | Agent cases at `/tasks` — discrete work items that agents execute |
| **OpenClaw** | Open-source AI agent platform we study for architectural patterns |
| **QMD** | Quick Markdown — OpenClaw's memory search system; we adapt it with PostgreSQL + pgvector |
| **Laravel AI SDK** | AI provider toolkit used underneath OpenCompany's app-owned AI runtime; current provider/model/catalog ownership lives in `app/Domain/Ai`, `app/Ai`, and `config/ai.php` |

# Documentation Index

Status: Current navigation index.
Verified: 2026-08-31.

> The application repository is canonical for implementation truth. Start with [Documentation Policy](README.md), [Current State](CURRENT_STATE.md), and [Roadmap and Open Audits](ROADMAP.md). Some imported research and planning documents intentionally describe future, historical, or external-system architecture; their status guard takes precedence over old examples in the body.

## Start Here

| Document | Authority |
|---|---|
| [README.md](README.md) | Documentation ownership, status vocabulary, and maintenance rules |
| [CURRENT_STATE.md](CURRENT_STATE.md) | Canonical implemented/partial/planned product inventory |
| [ROADMAP.md](ROADMAP.md) | Canonical priorities and unresolved audit register |
| [Linear backlog coverage](planning/linear-project-backlog-coverage-2026-10-09.md) | Documentation-to-issue mapping, workstream organization, historical TODO dispositions and verified operational tracking; not implementation or deployment evidence |
| [Licensing FAQ](../LICENSING.md) | Prospective AGPL-3.0-only complete self-hosted application, Apache integration boundary, separate Cloud, DCO governance, and historical-version terms |
| [Commercial Use](../COMMERCIAL_USE.md) | Practical AGPL guidance for internal, hosted, white-label, OEM, support, and official Cloud use |
| [Trademark and Compatibility Policy](../TRADEMARKS.md) | Rules for truthful references, forks, compatible products, official branding, and permission requests |
| [Contributing](../CONTRIBUTING.md) | Inbound-equals-outbound license mapping, Developer Certificate of Origin, provenance, and review requirements |
| [agent-run-queue-control-plane.md](architecture/agent-run-queue-control-plane.md) | Current durable agent execution architecture |
| [ai-provider-runtime-architecture.md](architecture/ai-provider-runtime-architecture.md) | Current AI provider/model/gateway/cost architecture |
| [vfs.md](tools/vfs.md) | Current VFS runtime contract |

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
| [web-search-fetch-adapter-investigation-2026-05-24.md](architecture/web-search-fetch-adapter-investigation-2026-05-24.md) | KosmoKrator-backed investigation and OpenCompany implementation plan for adapter-based non-browser `web_search`/`web_fetch`; current app baseline covers config, providers, managers, direct tools, Lua docs/runtime normalization, provider setup and web access UI, caching, usage events, diagnostics/configuration commands, extraction, URL safety, focused tests, adapter failure/malformed-payload coverage, and opt-in direct/Tavily live smoke tests, while broader provider live smoke coverage remains planned | Auditing or extending regular web research/fetch tools before browser automation |
| [telegram-chatogrator-capabilities-investigation-2026-05-24.md](architecture/telegram-chatogrator-capabilities-investigation-2026-05-24.md) | Research-only comparison of OpenCompany Telegram/Chatogrator, KosmoKrator Telegram gateway, current Hermes Telegram UX, provider abstraction differences, and current Telegram Bot API capabilities | Investigating Telegram/chat-provider capabilities before deciding any product direction |
| [telegram-world-class-implementation-overview-2026-05-24.md](architecture/telegram-world-class-implementation-overview-2026-05-24.md) | Agent-first Telegram target overview: switch agents/topics, run status, approvals, and attachments as agent context instead of a broad product-module command center | Rethinking or auditing Telegram UX direction |
| [telegram-world-class-implementation-plan-2026-05-24.md](architecture/telegram-world-class-implementation-plan-2026-05-24.md) | Phased reset plan for the agent-first Telegram implementation, including visible command shrinkage, agent/topic switching, media-as-context, approvals/run controls, Mini App as advanced drawer, and default-off legacy resource commands | Implementing or continuing the Telegram UX reset |
| [link-agent-wallets-investigation.md](architecture/link-agent-wallets-investigation.md) | Stripe Link Agents investigation — agent wallet/payment credential flow, MCP fit, and OpenCompany integration risks | Evaluating payment-agent capabilities or planning Link integration |
| [standalone-artifact-service-investigation-2026-05-25.md](architecture/standalone-artifact-service-investigation-2026-05-25.md) | Investigation of a standalone no-build, VFS-backed artifact runtime/service using object storage, Lua endpoints, and cheap model routing | Deciding whether Artifacts should become an extractable service or standalone product direction |
| [bowerbird-durable-automation-integration.md](architecture/bowerbird-durable-automation-integration.md) | Accepted optional-product architecture: standalone OpenCompany Code Mode, optional Bowerbird durable execution, shared Ruby and integration contracts, Program Lock publication, Run projection, review round trips, NocoDB pilot, failure boundaries, migration and qualification | Building or reviewing the OpenCompany/Bowerbird automation relationship |
| [cloudflare-os-opencompany-investigation-2026-08-09.md](architecture/cloudflare-os-opencompany-investigation-2026-08-09.md) | Read-only source investigation of Cloudflare OS, its Gadget/Gatekeeper/Blueprint architecture, concrete OpenCompany overlaps and gaps, and a staged adoption path centered on MCP safety, resource capabilities, action ledgers, proposed changes, and Artifacts | Evaluating Cloudflare OS ideas or planning OpenCompany capability, MCP, approval, automation, or Artifact work |
| [grok-bot-opencompany-investigation-2026-08-17.md](architecture/grok-bot-opencompany-investigation-2026-08-17.md) | Research and checkout-grounded comparison of Grok Bot's shared-computer agent model with OpenCompany, including Routines, task teaching, team supervision, memory synthesis, browser takeover, economics, risks, and a staged adoption path | Evaluating Grok Bot ideas or planning OpenCompany teammate UX, automations, multi-agent coordination, memory, browser, mobile, or budget work |
| [vfs-unix-command-gap-analysis-2026-05-26.md](architecture/vfs-unix-command-gap-analysis-2026-05-26.md) | Gap analysis comparing OpenCompany VFS unix command coverage with Mirage and other VFS inspiration repos, including flags, parser features, permission-aware boundaries, and a parity roadmap | Expanding or auditing the VFS shell command surface |
| [interagent-comms.md](architecture/interagent-comms.md) | Inter-agent communication protocol — ContactAgent tool with ask/delegate/notify patterns, DM channels, delegation tracking | Building or debugging agent-to-agent communication |
| [kosmokrator-reuse-audit.md](architecture/kosmokrator-reuse-audit.md) | Full audit of what OpenCompany should reuse, adapt, or skip from KosmoKrator | Planning cross-repo reuse, agent runtime work, metadata consolidation |
| [kosmokrator-opencompany-runtime-comparison-2026-05-11.md](architecture/kosmokrator-opencompany-runtime-comparison-2026-05-11.md) | Fresh runtime comparison of KosmoKrator and OpenCompany — agent runtime, integrations, providers, MCP, subagents, and migration priorities | Planning the next OpenCompany runtime extraction work |
| [domain-driven-design-architecture.md](architecture/domain-driven-design-architecture.md) | Proposed DDD modular-monolith architecture - bounded contexts, ownership rules, migration phases, and before/after examples | Planning maintainable long-term backend structure |
| [maintainability-refactor-audit-2026-05-19.md](architecture/maintainability-refactor-audit-2026-05-19.md) | Fresh maintainability audit with prioritized refactor opportunities and the calendar domain extraction completed during the audit | Planning the next backend cleanup pass |
| [ai-provider-runtime-architecture.md](architecture/ai-provider-runtime-architecture.md) | Current provider/model catalog, Laravel AI runtime registration, prompt-cache, and LLM usage/cost ledger architecture | Working on AI providers, model metadata, cost analytics, or OpenRouter billing |
| [runtime-alignment-implementation-audit.md](architecture/runtime-alignment-implementation-audit.md) | Post-implementation audit — findings now tracked as Plane issues (OC-1 through OC-6) | Reviewing audit results and fix status |
| [agent-run-queue-control-plane.md](architecture/agent-run-queue-control-plane.md) | Current one-queue agent runtime control plane: logical runs, locking, retries, tool idempotency, cancellation, automation/Telegram recovery, workspace scope, and operator behavior | Operating or changing queued agent execution |
| [wayfinder-routing.md](architecture/wayfinder-routing.md) | Current Wayfinder route/action generation conventions, frontend usage rules, and Docker build contract | Working on Vue navigation, API calls, Laravel routes, or asset builds |
| [docs-code-alignment-audit-2026-05-24.md](architecture/docs-code-alignment-audit-2026-05-24.md) | Historical May 24 docs/code alignment evidence; current truth and priorities moved to CURRENT_STATE and ROADMAP | Reviewing the previous reconciliation pass |
| [integrations-catalog-branch-audit-2026-05-10.md](architecture/integrations-catalog-branch-audit-2026-05-10.md) | Historical May 10 branch audit of integration-catalog gaps, with May 24 notes for resolved OAuth, catalog, paging, and dedupe work | Understanding why the integration-catalog hardening work happened |
| [pest-browser-testing-implementation-plan-2026-05-10.md](architecture/pest-browser-testing-implementation-plan-2026-05-10.md) | Pest Browser/Playwright migration plan. The current tree has both Dusk browser tests and new Pest browser specs. | Reviewing or continuing browser-test migration |
| [documentation.md](documentation.md) | Product documentation outline and information architecture | Updating user-facing docs or navigation |
| [ai-providers.md](ai-providers.md) | AI provider inventory and implementation priority using the current OpenCompany AI Runtime | Evaluating provider support or adding model integrations |

## Ecosystem & Integrations

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [ecosystem/integrations/](ecosystem/integrations/) | Integration package authoring reference, package layout, Lua tools, credentials, triggers, app publishing flow, and generated per-package Lua docs | Building or updating OpenCompany integration packages |
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
| [mruby-only-code-mode-implementation.md](planning/mruby-only-code-mode-implementation.md) | Planned sole-language mruby migration: shared isolated engine, PHP host, agent experience, automations, legacy-source handling, ecosystem, qualification and release | Implementing the Ruby hard cut that supersedes Lua/Luau and the proposed QuickJS runtime |
| [ai-app-builder-market-research-2026-05-25.md](market/ai-app-builder-market-research-2026-05-25.md) | Current market map for Base44/Lovable-style AI app builders, no-code AI platforms, design-to-code tools, mobile builders, internal-tool builders, and coding agents | Comparing AI app-builder platforms or deciding OpenCompany differentiation against prompt-to-app products |
| [memory-implementation.md](planning/memory-implementation.md) | Memory system architecture reference — STM/LTM model, phase summary **(Status: Complete)** | Understanding the memory architecture |
| [kosmokrator-runtime-alignment-checklist.md](planning/kosmokrator-runtime-alignment-checklist.md) | Checklist for aligning runtime behavior with KosmoKrator patterns — completed work + pointers to open Plane issues | Reviewing runtime-alignment status |
| [external-channel-sync.md](external-channel-sync.md) | Bidirectional sync design for Chatogrator-backed external channels — message tracking, edit/pin/react sync, channel discovery, and provider hardening status | Working on external platform integration |
| [discord.md](discord.md) | Historical Discord sidecar proposal plus current Chatogrator adapter/webhook pointer | Evaluating Discord integration design history before implementing current work |
| [versioned-workspace-master-overview.md](planning/versioned-workspace-master-overview.md) | Planning master overview of shared resource revisions, agent proposals, connected knowledge, VFS, workspace-wide Git history, object storage, snapshots, and practical workflows | Designing the versioned workspace or understanding how its collaboration and storage layers fit together |
| [automation-endpoints-artifacts.md](planning/automation-endpoints-artifacts.md) | Automation endpoint and agent-built artifact implementation plan | Working on automation API, generated artifacts, or dashboard-like internal tools |
| [chatogrator.md](planning/chatogrator.md) | Chat integration and ChatOgrator planning | Planning chat-side package/runtime behavior |
| [ai-economics-and-proactive-teammates.md](planning/ai-economics-and-proactive-teammates.md) | Product plan for rational AI economics, LLM cost controls, provider independence, and proactive Slack-style agent lanes | Planning cost-aware agent teammates, Slack proactivity, or channel ownership |
| [modular-platform-and-allbase-lab.md](planning/modular-platform-and-allbase-lab.md) | Target architecture for a modular OpenCompany, the Allbase playground relationship, shared module contracts, capability graduation, and the Reader-first adoption path | Planning optional business modules, workflow packs, or reuse of Allbase concepts |
| [codex-subscription-auth.md](planning/codex-subscription-auth.md) | Codex subscription authentication planning | Working on Codex integration |
| [dream-vfs.md](planning/dream-vfs.md) | Dream memory-consolidation plan plus the historical VFS route; current VFS behavior lives in tools/vfs.md | Planning Dream and later memory lifecycle work |
| [unified-vfs-overview.md](planning/unified-vfs-overview.md) | Implemented VFS baseline plus target architecture for later mounts, indexing, permissions UX, and production hardening | Understanding why the VFS is shaped this way or planning extensions |
| [vfs.md](tools/vfs.md) | Current VFS runtime reference — unix-like command subset, redirection/glob/jq behavior, budgets, and Lua helper envelope | Using or testing the implemented VFS surface |
| [integration-refactor.md](planning/integration-refactor.md) | Integration refactor strategy and implementation notes | Working on package/runtime integration cleanup |
| [integrations.md](planning/integrations.md) | Integrations product and architecture planning | Planning integration features or package scope |
| [laravel-mcp-client.md](planning/laravel-mcp-client.md) | Laravel MCP client planning | Building or evaluating MCP client support |
| [lua-scripting.md](planning/lua-scripting.md) | Lua scripting strategy for integrations and tools | Working on sandboxed script execution |
| [memory-systems.md](planning/memory-systems.md) | Memory-system research and roadmap notes | Comparing memory implementation options |
| [skill-system.md](planning/skill-system.md) | Skill-system planning for reusable agent capabilities | Designing skill packaging or execution |
| [vector-optional.md](planning/vector-optional.md) | Optional vector-search architecture | Deciding whether vector storage belongs in a feature |
| [visual-programming-research.md](planning/visual-programming-research.md) | Visual workflow/programming research | Planning visual builder experiences |

## Testing & QA

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [qa-strategy.md](testing/qa-strategy.md) | Testing pyramid, CI/CD pipeline, coverage targets, test data management | Setting up automated test infrastructure |
| [browser-full-qa-2026-05-24.md](qa/browser-full-qa-2026-05-24.md) | Historical deep browser checkup with 2026-08-09 reconciliation notes | Reviewing the original findings and evidence before fresh browser QA |
| [browser-qa-2026-05-24.md](qa/browser-qa-2026-05-24.md) | Historical browser route/UI report with resolved migration note and remaining revalidation boundary | Tracing a browser regression back to the May 24 evidence set |

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
| [pages/developer.md](ui/pages/developer.md) | Developer tool catalog and Lua console specification | Working on developer tool reference or script execution UI |
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

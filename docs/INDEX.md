# Documentation Index

> Quick reference to all OpenCompany documentation. Each doc serves a specific purpose — use the "Read when" column to find what you need.

---

## Architecture & Technical

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [openclaw-reference.md](architecture/openclaw-reference.md) | OpenClaw's agent, memory, skills, QMD, and plugin architecture (reference material) | Designing agent features, understanding source patterns |
| [openclaw-patterns.md](architecture/openclaw-patterns.md) | Which OpenClaw patterns to adopt, adapt, or skip for OpenCompany | Planning new agent capabilities |
| [laravel-ai-sdk.md](architecture/laravel-ai-sdk.md) | Laravel AI SDK integration strategy — providers, tools, streaming, memory, workflows, QMD adaptation | Implementing AI features |
| [technology-decisions.md](architecture/technology-decisions.md) | Tech stack choices: Laravel AI SDK + Prism, Laravel queues for orchestration | Understanding why we chose what |
| [observability.md](architecture/observability.md) | Monitoring, metrics, logging, error tracking, health checks, alerting | Building admin/ops features |
| [ai-tool-packages.md](architecture/ai-tool-packages.md) | AI tool package ecosystem — ToolProvider contract, credential abstraction, hybrid ToolRegistry, building new tool packages | Creating or modifying AI tool packages, understanding the plugin architecture |
| [link-agent-wallets-investigation.md](architecture/link-agent-wallets-investigation.md) | Stripe Link Agents investigation — agent wallet/payment credential flow, MCP fit, and OpenCompany integration risks | Evaluating payment-agent capabilities or planning Link integration |
| [interagent-comms.md](architecture/interagent-comms.md) | Inter-agent communication protocol — ContactAgent tool with ask/delegate/notify patterns, DM channels, delegation tracking | Building or debugging agent-to-agent communication |
| [kosmokrator-reuse-audit.md](architecture/kosmokrator-reuse-audit.md) | Full audit of what OpenCompany should reuse, adapt, or skip from KosmoKrator | Planning cross-repo reuse, agent runtime work, metadata consolidation |
| [kosmokrator-opencompany-runtime-comparison-2026-05-11.md](architecture/kosmokrator-opencompany-runtime-comparison-2026-05-11.md) | Fresh runtime comparison of KosmoKrator and OpenCompany — agent runtime, integrations, providers, MCP, subagents, and migration priorities | Planning the next OpenCompany runtime extraction work |
| [domain-driven-design-architecture.md](architecture/domain-driven-design-architecture.md) | Proposed DDD modular-monolith architecture - bounded contexts, ownership rules, migration phases, and before/after examples | Planning maintainable long-term backend structure |
| [maintainability-refactor-audit-2026-05-19.md](architecture/maintainability-refactor-audit-2026-05-19.md) | Fresh maintainability audit with prioritized refactor opportunities and the calendar domain extraction completed during the audit | Planning the next backend cleanup pass |
| [runtime-alignment-implementation-audit.md](architecture/runtime-alignment-implementation-audit.md) | Post-implementation audit — findings now tracked as Plane issues (OC-1 through OC-6) | Reviewing audit results and fix status |
| [integrations-catalog-branch-audit-2026-05-10.md](architecture/integrations-catalog-branch-audit-2026-05-10.md) | Branch audit of integrations catalog, multi-account OAuth, UI wiring, and validation gaps | Reviewing current integration work before fixing or shipping |
| [pest-browser-testing-implementation-plan-2026-05-10.md](architecture/pest-browser-testing-implementation-plan-2026-05-10.md) | Pest browser testing rollout plan with Playwright, local/CI scope, and first integration UI coverage | Planning browser-test implementation |
| [ai-tool-strategy.md](strategy/ai-tool-strategy.md) | AI tool ecosystem strategy — package publishing, MCP export, missing tool analysis, Fair Code growth | Planning tool ecosystem, evaluating new tool integrations |

## Planning & Implementation

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [memory-implementation.md](planning/memory-implementation.md) | Memory system architecture reference — STM/LTM model, phase summary **(Status: Complete)** | Understanding the memory architecture |
| [kosmokrator-runtime-alignment-checklist.md](planning/kosmokrator-runtime-alignment-checklist.md) | Checklist for aligning with KosmoKrator/prism-relay — completed work + pointers to open Plane issues | Reviewing runtime-alignment status |
| [external-channel-sync.md](external-channel-sync.md) | Bidirectional sync design for Telegram/Discord — message tracking, edit/pin/react sync, channel discovery **(Telegram: Done, Discord: Not started)** | Working on external platform integration |
| [discord.md](discord.md) | Discord integration documentation — architecture, sidecar, configuration | Setting up or debugging Discord integration |
| [codex-subscription-auth.md](planning/codex-subscription-auth.md) | Codex subscription authentication planning | Working on Codex integration |

## Strategy & Business

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [masterplan.md](strategy/masterplan.md) | Original vision: "Slack for Autonomous Organizations" — raw brainstorming notes | Understanding the big picture and product thesis |
| [business-strategy.md](strategy/business-strategy.md) | Open-core model, pricing tiers, go-to-market roadmap, competitive positioning | Making business decisions |
| [enterprise-security.md](strategy/enterprise-security.md) | Enterprise security and governance strategy — SSO, RBAC, audit, compliance | Planning enterprise features |
| [emergent.md](strategy/emergent.md) | Non-obvious insights, gaps, risks, and opportunities from codebase audit | Product planning, prioritization, risk assessment |

## Website & Marketing

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [features.md](website/features.md) | Complete feature list for the marketing website | Updating marketing claims, checking feature coverage |
| [enterprise.md](website/enterprise.md) | Enterprise marketing page content — security, compliance, SLAs | Targeting enterprise customers |
| [landing-page.md](website/landing-page.md) | Landing page copy, hero section, and messaging | Updating the website |

## Testing & QA

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [qa-strategy.md](testing/qa-strategy.md) | Testing pyramid, CI/CD pipeline, coverage targets, test data management | Setting up automated test infrastructure |

## Tools & Features

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [charts.md](tools/charts.md) | Visualization tools reference — render_svg, render_vegalite, render_mermaid, render_plantuml, render_typst | Using or extending visualization tools |

## Research

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [openai-frontier-analysis.md](research/openai-frontier-analysis.md) | Deep analysis of OpenAI's Frontier platform (Feb 2026) — threat assessment | Competitive intelligence, positioning decisions |

---

## UI/UX

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [design-system.md](ui/design-system.md) | Design tokens, colors, typography, spacing, animations, accessibility | Building new UI components, choosing colors or spacing |
| [components.md](ui/components.md) | All 26 shared components with props, slots, events, and usage | Using a shared component, checking available props |
| [layouts.md](ui/layouts.md) | Common layout patterns with ASCII diagrams, responsive breakdowns | Designing a new page, understanding how layouts work |
| [pages/](ui/pages/) | Individual page specifications with ASCII layouts and feature docs | Understanding or modifying a specific page |

---

## Key Concepts

| Term | Meaning |
|------|---------|
| **ListItems** | Kanban board items at `/lists` — user-managed todo items (formerly "Tasks") |
| **Tasks** | Agent cases at `/tasks` — discrete work items that agents execute |
| **OpenClaw** | Open-source AI agent platform we study for architectural patterns |
| **QMD** | Quick Markdown — OpenClaw's memory search system; we adapt it with PostgreSQL + pgvector |
| **Laravel AI SDK** | Official Laravel package for AI provider integration (our chosen framework) |

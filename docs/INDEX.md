# Documentation Index

> Quick reference to all OpenCompany documentation. Each doc serves a specific purpose — use the "Read when" column to find what you need.

---

## Architecture & Technical

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [openclaw-reference.md](architecture/openclaw-reference.md) | OpenClaw's agent, memory, skills, QMD, and plugin architecture (reference material) | Designing agent features, understanding source patterns |
| [openclaw-patterns.md](architecture/openclaw-patterns.md) | Which OpenClaw patterns to adopt, adapt, or skip for OpenCompany | Planning new agent capabilities |
| [laravel-ai-sdk.md](architecture/laravel-ai-sdk.md) | Laravel AI SDK integration strategy — providers, tools, streaming, memory, workflows, QMD adaptation | Implementing AI features |
| [technology-decisions.md](architecture/technology-decisions.md) | Tech stack choices: Laravel AI SDK over Prism, Laravel Workflow over Temporal | Understanding why we chose what |
| [observability.md](architecture/observability.md) | Monitoring, metrics, logging, error tracking, health checks, alerting | Building admin/ops features |

## Planning & Implementation

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [implementation-todo.md](planning/implementation-todo.md) | Complete task breakdown across 8+ phases with dependencies, priority order, and file manifests | Starting implementation work, tracking progress |

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
| [feature-test-map.md](testing/feature-test-map.md) | Checklist of every feature, button, and interaction to test (~500 items) | Manual QA testing |
| [qa-strategy.md](testing/qa-strategy.md) | Testing pyramid, CI/CD pipeline, coverage targets, test data management | Setting up automated test infrastructure |

## Research

| Document | What it covers | Read when... |
|----------|---------------|--------------|
| [openai-frontier-analysis.md](research/openai-frontier-analysis.md) | Deep analysis of OpenAI's Frontier platform (Feb 2026) — threat assessment | Competitive intelligence, positioning decisions |

---

## Key Concepts

| Term | Meaning |
|------|---------|
| **ListItems** | Kanban board items at `/lists` — user-managed todo items (formerly "Tasks") |
| **Tasks** | Agent cases at `/tasks` — discrete work items that agents execute |
| **OpenClaw** | Open-source AI agent platform we study for architectural patterns |
| **QMD** | Quick Markdown — OpenClaw's memory search system; we adapt it with PostgreSQL + pgvector |
| **Laravel AI SDK** | Official Laravel package for AI provider integration (our chosen framework) |
| **Laravel Workflow** | Durable execution engine for agent workflows |

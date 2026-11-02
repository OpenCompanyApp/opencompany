# OpenCompany

**A self-hosted workspace for AI-powered teams.** Humans and durable AI agents collaborate through shared chat, tasks, documents, tools, and approvals.

[Website](https://opencompany.app) · [Docs](https://docs.opencompany.app) · [Discord](https://discord.gg/opencompany)

---

## What is OpenCompany?

OpenCompany is a self-hosted collaboration platform where humans and AI agents work in the same workspace. Agents respond to chat, assigned tasks, schedules, automations, and delegated work; durable memory and run records preserve useful context and execution state across those interactions.

Built with Laravel 12, Vue 3, and Inertia.js.

## Features

- **Durable AI Agents** — Workspace agents with identity, memory, queued run state, retries, cancellation, and task history
- **Multi-Provider LLM** — OpenAI, Anthropic, Gemini, Groq, xAI, OpenRouter, Codex, and more through the app-owned provider catalog
- **Real-Time Chat** — Channels, DMs, threads, @mentions — humans and agents side by side
- **Agent Memory** — Short-term + long-term memory with hybrid vector/keyword search
- **Multi-Agent Delegation** — Existing workspace agents can receive isolated delegated work with child task/run tracking and sequential result aggregation
- **Task Management** — Full lifecycle tracking with Kanban boards and workload monitoring
- **Organization View** — Visual hierarchy for humans and agents
- **Documents & Knowledge** — Shared knowledge base accessible to all agents
- **Approvals & Governance** — Human-in-the-loop approval workflows
- **External Integrations** — Package integrations, MCP-connected tools, and Telegram runtime support
- **Multi-Workspace** — Workspace isolation, admin/member roles, and agent-specific permissions; full human RBAC remains planned
- **MCP Client Runtime** — Connect workspace-scoped MCP servers as agent tools

For a precise shipped/partial/planned breakdown, see [Current State](docs/CURRENT_STATE.md). Active security, reliability, and product work is tracked in [Roadmap and Open Audits](docs/ROADMAP.md).

## Quick Start

```bash
# Clone the repository
git clone https://github.com/OpenCompanyApp/opencompany.git
cd opencompany

# Install and setup
composer setup

# Start development
composer dev
```

> Requires PHP 8.4+, Node.js 20+, and PostgreSQL with [pgvector](https://github.com/pgvector/pgvector).

### Optional local Ruby engine candidate

Code-mode development can use the separately published mruby engine RC. Install
it with `scripts/install-ruby-engine.sh`; it verifies the selected platform
archive before extraction and prints, rather than writes, the needed local
configuration. See [the installation guide](docs/guides/mruby-installation.md)
for supported platforms and verification details.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.4+ |
| Frontend | Vue 3, Inertia.js, Tailwind CSS v4 |
| AI | OpenCompany AI Runtime on Laravel AI SDK |
| Real-time | Laravel Reverb (WebSockets) |
| Memory | PostgreSQL + pgvector |
| UI | Reka UI (headless primitives) |

## License

The working tree is prepared for a future open-source release under
[AGPL-3.0-only](LICENSE.md). AGPL permits self-hosting, modification, redistribution, commercial use,
and compliant competing hosting. Modified network deployments must provide Corresponding Source to
their users as required by AGPL section 13.

Reusable protocols, schemas, SDKs, integration kits, examples, and templates are intended to use
Apache-2.0 where their path notices say so; today that is `resources/code-docs/` (Code Mode guides
and examples, see [the notice](resources/code-docs/LICENSE)). Separate OpenCompany Cloud operations may remain
proprietary, but the self-hosted application remains complete and open source. Historical BSL or
FSL releases retain the exact terms shipped with those versions.

See also:

- [Licensing FAQ](LICENSING.md)
- [Commercial Use](COMMERCIAL_USE.md)
- [Trademark and Compatibility Policy](TRADEMARKS.md)
- [Contributing](CONTRIBUTING.md)

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=OpenCompanyApp/opencompany&type=Date)](https://star-history.com/#OpenCompanyApp/opencompany&Date)

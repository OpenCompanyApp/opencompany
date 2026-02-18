# OpenCompany

**The operating system for AI-powered teams.** Self-hosted platform where humans and AI agents collaborate as persistent team members — not just API calls.

[Website](https://opencompany.app) · [Docs](https://docs.opencompany.app) · [Discord](https://discord.gg/opencompany)

---

## What is OpenCompany?

OpenCompany is a self-hosted collaboration platform — think Slack, but half the users are AI agents. Agents live in channels 24/7, remember past conversations, learn from interactions, and work alongside humans as true team members.

Built with Laravel 12, Vue 3, and Inertia.js.

## Features

- **Persistent AI Agents** — Agents that run 24/7 with memory, not stateless API calls
- **Multi-Provider LLM** — OpenAI, Anthropic, Gemini, Groq, xAI, and more with automatic failover
- **Real-Time Chat** — Channels, DMs, threads, @mentions — humans and agents side by side
- **Agent Memory** — Short-term + long-term memory with hybrid vector/keyword search
- **Multi-Agent Orchestration** — Dynamic spawning, task delegation, result aggregation
- **Task Management** — Full lifecycle tracking with Kanban boards and workload monitoring
- **Org Chart** — Visual hierarchy with drag-and-drop for humans and agents
- **Documents & Knowledge** — Shared knowledge base accessible to all agents
- **Approvals & Governance** — Human-in-the-loop approval workflows
- **External Integrations** — Slack, Discord, Telegram, WhatsApp, Email, and more
- **Multi-Workspace** — Isolated workspaces with RBAC
- **MCP Server** — Expose your workspace as an MCP server

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

> Requires PHP 8.2+, Node.js 20+, and a database (SQLite works out of the box).

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Vue 3, Inertia.js, Tailwind CSS v4 |
| AI | Laravel AI SDK, Prism (multi-provider) |
| Real-time | Laravel Reverb (WebSockets) |
| Memory | PostgreSQL + pgvector |
| UI | Reka UI (headless primitives) |

## License

OpenCompany is fair-code licensed under the [Sustainable Use License](LICENSE.md).

- **Self-hosting**: Free for internal and personal use
- **Enterprise**: [Contact us](mailto:enterprise@opencompany.app) for SSO, audit logging, and white-labeling ([Enterprise License](LICENSE_EE.md))

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=OpenCompanyApp/opencompany&type=Date)](https://star-history.com/#OpenCompanyApp/opencompany&Date)

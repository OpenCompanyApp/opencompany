# OpenCompany

**Grok Bot for companies.** A messenger where your team works with named AI teammates. Each
teammate can have its own computer, and the company stays in control through roles, approvals,
budgets and audit. Tables, Issues and Docs are built in, and deep connections reach the tools your
company already uses. Built for teams, not individuals.

**Your data is yours: export, back up, leave anytime.**

*OpenCompany, by Bowerbird.*

[Website](https://opencompany.app) · [Docs](https://docs.opencompany.app) · [Discord](https://discord.gg/opencompany)

---

## What is OpenCompany?

Persistent AI agents with their own computer, memory, schedules and approval hand-backs are now a
common product shape, but the well-known ones are built for a single user. OpenCompany is the team
version, and it is self-hostable:

- **Named AI teammates, not chatbots.** Each agent is a workspace member with an identity, memory,
  skills, routines, a budget and grants. You assign it work the way you would assign a person: in
  chat, on an issue or record, or through a routine.
- **Messenger first, API first (planned client).** Chat is where work happens: DMs and group chats
  with people and agents, with tasks, records and approvals inline. **Home** gathers what needs you
  (approvals, questions, takeovers), then results, then FYI, and replaces dashboards. One left rail
  holds Home, pinned items, agents, people, groups, apps and Spaces; a context pane beside the chat
  shows the object, the run or the agent's computer. The new client is a Vue 3 single-page app on a
  versioned public API with a realtime stream, so web, mobile and desktop apps share one backend.
- **Own computer: optional, strongly recommended (planned).** An agent will be able to get its own
  computer (shell, real browser, installed software, logins, long-running jobs) through a pluggable
  `ComputerProvider` contract, with one computer per agent by default. Computer access is a
  per-agent choice. OpenCompany works fully without one: a VFS-only agent still has chat, Issues,
  Docs, Tables, memory, the VFS, Code Mode, connections and routines. You can also bring your own
  machine, even a Raspberry Pi or a home server: the outbound-only `oc host` daemon connects it
  without opening ports, and each agent gets only the capabilities you grant (planned).
- **Governed access to company data.** The permission-aware VFS (virtual filesystem) over tables,
  tasks, docs and files is a complete workspace on its own. When an agent has a computer, the VFS
  stays the only path to company data, with the same scoping, permissions, approvals and audit; the
  computer never bypasses it.
- **Governance is the point.** Control across many humans and many agents. Workspace isolation and
  server-side approvals exist today. Planned: one policy engine with Personal, Team and Company
  presets, gated by reversibility rather than by "write": undoable edits auto-apply as change sets,
  while external sends, spend and exposure ask first, with scoped "always allow" exceptions. An
  agent never exceeds the human it acts for and never approves anything. Also planned: human roles
  and approver policies, a hard-deny floor, budgets that pause work, and a replayable audit trail.
  Secrets stay in a vault behind a credential broker and never reach the model. All presets ship in
  the self-hosted product.
- **Connections, events and routines (planned).** One model for integrations and automation:
  connections produce events, triggers match them, and routines run as governed agent runs. Deep,
  app-owned connectors (Google Drive first, then mailboxes, calendars and more storage) sit beside a
  generic tier: MCP servers, API manifests, skills and signed webhooks. Routines are authored in chat;
  a trigger is never consent, and there is no visual flow builder.
- **Structured by default, not a page sprawl.** A fixed set of core primitives (threads, Issues,
  Tables and records, Docs, Files, Artifacts, routines, agents and people) organized in flat
  **Spaces**, each with the same six tools: Issues, Tables, Docs, Files, Artifacts and Routines. Agents use them directly instead of through connections. Typed fields, owners,
  review dates and human-approved schema changes keep the workspace tidy (planned).
- **Agents propose, you review, revert any run (planned).** Agents edit structured data through
  change sets: plan, preview, staged edits in the agent's color, review per cell, row or all, then
  apply. "Revert this run" undoes a run at any time, and human edits always win.
- **Artifacts and forms (planned).** Agents build live reports, dashboards, forms and small apps on
  your governed data, with no private storage. Pages are declarative blocks over live data;
  interactive artifacts run sandboxed on a separate origin with optional mruby endpoints; publishing
  goes from draft through Space and workspace to signed links and, only if an admin allows it,
  public. Any table can get a form view whose submissions flow through one governed pipeline.
- **Add-on apps on the same primitives (planned).** Optional apps such as Mail, Calendar and CRM are
  manifests over the core primitives with no storage of their own, so search, approvals, change sets
  and revert work on their data automatically.
- **Agent-first `oc` CLI (planned).** One Rust binary for the whole product, built for agents first
  (self-describing schema, `--json`, dry-run change sets, structured approval results) and humans
  second (a terminal UI for chat and Home). `oc mcp serve` lets Claude Code, Codex, Cursor and other
  local agents work with your OpenCompany issues, tables, docs and approvals using your own access.
  It is planned as a separate Apache-2.0 `opencompany-cli` repository on the same public API.
- **OpenCompany, by Bowerbird.** OpenCompany and [Bowerbird](https://bowerbird.si) form one
  portfolio with a clear split: OpenCompany owns business automation and the human surface;
  Bowerbird owns software delivery and heavy durable pipelines. Planned: Bowerbird sandboxes as a
  computer provider, Bowerbird as a deep connection (CI, run and review events, deployment
  approvals as cards), and heavy script routines graduating to Bowerbird pipelines with the same
  Ruby. Self-hosted OpenCompany never requires Bowerbird.
- **One scripting runtime.** mruby Code Mode is the only in-process scripting runtime; Lua and
  QuickJS are retired.
- **Open source first.** The complete self-hosted application is prepared for AGPL-3.0-only (see
  [License](#license)). A hosted OpenCompany Cloud is planned on top; self-hosting stays complete.
- **Your data is yours (planned).** Download any object (Docs as Markdown, PDF or DOCX; Tables as
  CSV, XLSX or JSON; Issues and threads; original files; agent packs). Export the whole workspace as
  a round-trippable `.ocarchive`, whose format spec is planned to be published under Apache-2.0.
  Back up on a schedule to storage you own, encrypted with a key only you hold. Keep an optional
  one-way Git mirror of Docs, Tables, Issues and artifact source. On Cloud, export and backups are
  free on every plan, a lapsed workspace stays read-only and exportable for at least 90 days, and you
  can move to self-hosting at any time. Bring your own model keys or local models, and expect no
  phone-home: self-hosted installs send no telemetry by default.

Built with Laravel 12 and Vue 3. Today's pages use Inertia.js; Inertia is frozen, and new UI is
built against the API.

## What exists today

Implemented in the current checkout (see [Current State](docs/CURRENT_STATE.md) for the precise
boundaries):

- **Durable AI agents.** Identity, memory, queued run records, retries, pause/cancel, stale-run
  reconciliation, task history and a guarded tool ledger.
- **Chat.** Channels, DMs, threads, @mentions, reactions and attachments, with humans and agents
  side by side.
- **Multi-agent delegation.** Peer agents receive isolated delegated work with child task/run
  tracking and sequential result aggregation.
- **Memory.** Durable memory files with hybrid pgvector/full-text retrieval, reranking and compaction.
- **VFS.** Permission-aware projections over agents, approvals, automations, channels, docs, files,
  lists, tables, tasks and tools, with Unix-like commands, pagination, budgets and approvals.
- **Work objects.** Tasks, lists, tables, calendar, documents (versions, comments, search) and files.
- **Scheduled automations.** Prompt and script automations with run history; these become Routines.
- **Approvals.** Agent/tool approval wrapping with in-app and Telegram responses and post-approval
  permission recheck.
- **Workspaces.** Multi-workspace isolation, admin/member roles and agent-specific permissions.
- **Providers and tools.** App-owned multi-provider LLM catalog (OpenAI, Anthropic, Gemini, Groq,
  xAI, OpenRouter, Codex and more), workspace-scoped MCP client, web search/fetch. The older
  integration package system is being deleted in Phase 0 and is not a product feature.
- **Telegram.** The deepest external chat surface: durable delivery, commands, topics, approvals,
  Mini App and media.
- **Cost visibility.** Usage ledger, token metrics and analytics.
- **Code Mode.** The mruby-only cutover is in progress (engine release candidate published; full
  qualification pending), replacing the earlier Lua scripting.

## What is planned

The order comes from the locked [Product Direction](docs/PRODUCT_DIRECTION.md); priorities and open
audits are in the [Roadmap](docs/ROADMAP.md). None of the following is shipped yet:

| Phase | Planned |
|---|---|
| 0 · Foundation (in progress) | Authorization and security fixes (channel membership, document ACLs, approver capability, no self-approval, credential and MCP isolation), landing the run control plane and VFS, mruby-only Code Mode, AGPL licensing, tests in CI; deleting the integration package system and behaviour modes; Connections model with vault and credential broker, workspace event log, policy model; artifacts groundwork (sandboxed user-content origin design, chart/diagram renderers into Files); `oc` CLI groundwork; `.ocarchive` format design and a no-telemetry CI check |
| 1 · Messenger | New Vue 3 client on the API: one rail, Home, three panes, group chats with several agents, inline approvals, typed run activity, mobile bottom bar; Inertia pages retired at parity; routines from chat; rebuilt MCP client; Google Drive mount; Artifacts v1 (Pages and interactive artifacts on read bindings); `oc` CLI v0 with `oc mcp serve`; object downloads and `oc export` |
| 2 · Computer v1 | `ComputerProvider` with local Docker and boat; shell, files, browser and takeover viewer; secure forms; egress allowlist; Gmail and agent inbox; Mail app; webhooks; script and hybrid routines; API manifests; skills and plugin import; artifact endpoints and write bindings; Forms v1; Connected hosts (bring your own machine via `oc host`); workspace archive export/import and `oc admin backup/restore` |
| 3 · Governance | Human roles and approver policies, hard-deny floor, budgets that pause work, run replay and audit, password managers, scoped approvals, heartbeats and daily briefs, OneDrive/SharePoint and calendars, Calendar app; signed and public artifact publishing; Forms v2; host MCP bridge and event source, `oc mount`; encrypted backups to your storage, Git mirror, export governance, GDPR export |
| 4 · Work apps and apps platform | Tables, Issues and Docs made agent-native: mentions, assignment, record-change events and triggers; change sets for every object; Spaces and anti-mess rules; app manifest runtime with CRM first; artifacts as app custom views and templates |
| 5 · Cloud | Hosted OpenCompany Cloud with managed computers; OpenCompany as an MCP server; self-host parity; custom domains and MCP Apps export for artifacts; Windows hosts; Cloud exit guarantees, region choice, verified deletion |

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

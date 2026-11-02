# OpenCompany Product Direction

Status: **Locked product direction.** Accepted by the owner on 2026-11-02, with the final
recommendation (connections, events and routines; integration tiers; nuclear cleanup) accepted the
same day. The UI structure, sidebar, anti-mess rules, the core-primitives-plus-apps model, and the
governance presets with gating by reversibility were accepted the same day as well, and so were
Artifacts as a core primitive and Forms as a Table view type, connected hosts (bring your own machine,
including over Tailscale), the Rust `oc` CLI, and full data sovereignty (export, backup and portability,
hosted included).
Supersedes: earlier positioning ("A Company in a Box", "Slack/Discord where half the team is AI",
the Reader-first modular company OS, the AI app builder framing) and the earlier integration and
automation plans, wherever they conflict with this document.
Evidence: [Agent landscape report (2026-11)](research/2026-11-agent-landscape.md), the
[Grok Bot investigation (2026-08)](architecture/grok-bot-opencompany-investigation-2026-08-17.md) and the
[integration strategy research](planning/integration-strategy-2026-11.md).

This document owns *what OpenCompany is becoming and in what order*. It makes no implementation
claims. What exists today is in [Current State](CURRENT_STATE.md). Engineering priority and open
audits are in the [Roadmap](ROADMAP.md), which must follow this document.

## The decision in one sentence

**OpenCompany is "Grok Bot for companies":** a messenger where a team works with named AI teammates.
Each teammate can have its own computer. The company stays in control through approvals, roles,
budgets and audit. Tables, Issues and Docs are built in, and deep connections reach the company's
existing tools. The product is fully self-hostable under AGPL-3.0-only, and a hosted Cloud is
offered on top.

It is aimed at teams and companies, not individuals.

## Why this direction

- Between May and October 2026 every major lab shipped a persistent, named agent with its own cloud
  computer, memory, schedules and approval hand-backs: Google Gemini Spark, xAI Grok Bot, Meta Muse,
  OpenAI dots and Anthropic Claude Duet. Users now expect this product shape.
- All of them are single-user. Their safety designs are strong, but none of them gives a company
  shared work objects, role-based approvals, budgets and an audit trail across many humans and many
  agents.
- The open-source "Grok Bot alternatives" (OpenMausBot, Rakazo, Codync) copy the experience with
  weak governance. Team platforms (Paperclip, Multica) have governance ideas but no computers and no
  messenger-grade chat. Multica's license forbids hosting it for others.
- OpenCompany already has the hard parts of a governed runtime: durable runs, server-side approvals,
  workspace scoping, memory, Telegram, mruby and a VFS. What it lacks is the experience layer
  (messenger, own computer), finished governance, and a coherent integration and automation model.

## Product pillars

### 1. AI teammates, not chatbots

- Every agent is a named workspace member with an identity, a manager, memory, skills, routines,
  a budget, grants and, optionally, a computer.
- Agents can be assigned work like a person: in chat, on an issue, on a table record or by a routine.
- Agents can be grouped into multi-agent group chats with humans. Delegation between agents is
  durable and depth-limited through the causality chain (pillar 9).

### 2. Own computer: per agent, optional, strongly recommended

- **OpenCompany must work fully without a computer.** A computerless agent still has chat, Issues,
  Docs, Tables, memory, the VFS, mruby Code Mode, connections and routines. Self-hosters with no
  sandbox infrastructure get a complete product.
- **Attaching a computer is recommended.** Onboarding, agent creation and the agent profile
  recommend it clearly and explain what it adds (shell, real browser, installed software, logins,
  long-running work), without blocking agents that lack one.
- **Computer access is a per-agent choice.** Within one workspace, some agents have a computer and
  others are VFS-only.
  - VFS-only is a first-class configuration and the least-privilege default for agents that only
    handle company data.
  - Admins decide which agents may have a computer; roles and policy can restrict it.
  - Moving an agent between modes keeps its identity, memory and VFS access.
- **Computers come from providers.** OpenCompany defines a `ComputerProvider` contract: create,
  start, stop, snapshot, fork, exec, files, browser, screen stream and ports. It is shaped on
  Bowerbird's public Workspace API `v1alpha` (bwb-cloud decision 0011); browser and screen stream are
  OpenCompany extensions. See the [computer contract](architecture/computer-provider-contract.md).
- **Provider order:**
  1. Local Docker or Firecracker on the self-hosted machine (self-host default).
  2. [boat](https://boat.dev) as the first hosted partner: EU regions, about $0.018 per hour for
     2 vCPU / 4 GB, stopped time free (Bowerbird research, 2026-10-04).
  3. E2B and Daytona.
  4. Bowerbird agent sandboxes once its S1 milestone ships. OpenCompany is Bowerbird's first design
     partner and dogfood customer.
  5. **Connected hosts** (bring your own machine; locked 2026-11-02): a Raspberry Pi, home server,
     laptop, VPS or office machine. The full design is in [`oc` CLI and hosts](planning/oc-cli-and-hosts.md).
     - **Connection.** The `oc` binary runs as a daemon (`oc host up`, `oc host install-service`)
       that holds an **outbound-only** connection to OpenCompany, so no inbound ports are needed.
       It works behind NAT and CGNAT, with or without Tailscale, for self-hosted and Cloud installs.
     - **Tailscale** is a supported path, not a requirement. A self-hosted server can join the
       tailnet as a sidecar. SSH-only hosts can be reached at tailnet or direct addresses with vault
       keys or Tailscale SSH; these get exec, files and the computer capability only. Hosted Cloud
       never joins a customer's tailnet.
     - **Capabilities, each granted per agent:**
       - computer: exec, files, processes, ports, and a browser if one is installed;
       - local MCP bridge: stdio MCP servers on the host, exposed as connections. This is the
         supported home for stdio MCP;
       - VFS projection;
       - event source: device events become untrusted events that can trigger routines.
     - **Safety.** Hosts are labelled self-managed.
       - Sandbox mode is `container` by default or `bwrap`. `none` needs an explicit flag and is
         flagged in Admin.
       - Policy, approvals, the floor, on-behalf-of access and audit apply unchanged.
       - Secrets are injected per call and never synced to the host. Host tokens are scoped,
         rotated and revocable.
       - Admin → Hosts has a disconnect kill switch. Approving a host is `exposure_widening`.
       - By default, one agent per host gets the computer capability. MCP bridges can be granted to
         several agents.
- **One computer per agent by default.** Grok Bot shares one computer across every bot on an account
  and admits "bots are not a security boundary". OpenCompany isolates per agent and offers a shared
  team computer only as an explicit opt-in with a warning.

### 3. The VFS is the governed path to company data

- **Without a computer**, the VFS is a complete workspace: an agent can do real file-style work
  through it alone.
- **With a computer**, both work together. The VFS is projected into the computer (mount, sync or CLI
  bridge), and every read and write still goes through VFS scoping, resource permissions, write
  policy, approvals and audit. **The computer is never a bypass.**
- Deep-connector data (for example Google Drive) appears in Files and the VFS under the same policy
  (pillar 9).
- **mruby** is the only Code Mode runtime: fast, cheap, safe in-process work against OpenCompany's
  own APIs and connections. Lua and QuickJS are retired.
- The **computer** handles arbitrary software, browsers, builds and long-running jobs.

### 4. Messenger-first and API-first

- **Messenger first, with Home as the attention page.**
  - **Home** ranks what needs you first (approvals, questions, takeovers, sign-in requests), then
    results, then FYI. The daily brief lands there. It is not called "Inbox", to avoid confusion
    with the Mail app and agent inboxes. It replaces Dashboard, Activity and Workload.
  - **One left rail** (accepted 2026-11-02), with these sections:
    - Home;
    - Pinned (per person);
    - Agents: opening one starts a DM, the rail shows its status, and 🖥 marks agents with a computer;
    - People;
    - Groups and channels;
    - **Apps** (add-ons such as Mail and CRM);
    - Spaces, each with the same six tools;
    - a footer with Team and Admin.

    On mobile, a bottom bar holds Home, Chats, Spaces and Team. The mockups are
    [the sidebar](ui/mockups/sidebar-2026-11.html) and
    [primitives plus apps](ui/mockups/apps-and-primitives-2026-11.html).
  - **Three panes:** rail | main | a resizable context pane.
    - **Chat-first:** the context pane shows the object being discussed, the agent's computer
      (watch, take over, hand back; only for agents that have one), the run (plan, activity and
      change set), or thread details.
    - **Object-first:** the main pane is the object, and the context pane is an agent chat scoped to
      that object and the current selection.
  - Conversations carry inline task, record and approval cards and typed, live run activity.
- **Agents edit structured data through change sets.** The flow is plan, then dry-run preview, then
  live staged edits in the agent's color, then review per cell, row or all, then apply. "Revert this
  run" undoes a run at any time, and human edits always win. Docs use collaborative suggestion mode.
  Full design: [UI structure and agent editing](planning/ui-structure-and-agent-editing.md).
- **A new client.** The messenger is a new Vue 3 + Reka UI single-page app with a token-based design
  system. Old Inertia pages are deleted as the new client reaches parity.
- **Approval cards** state what will happen, why, and where the request came from. They can be
  answered with one tap on web, mobile and Telegram.
- **API-first now** (decided 2026-11-02; previously "planned, likely soon"):
  - The messenger is built as a client of a versioned, documented public API (OpenAPI), with
    token/OAuth authentication for native apps, a realtime event stream (WebSocket/SSE) and push
    notifications.
  - Web, mobile and desktop apps share one backend. Telegram and other channels stay server-side
    adapters.
  - Inertia is frozen: no new Inertia pages or page props.
  - Still open: whether the web client leaves this repository, Wayfinder versus a generated OpenAPI
    client, and the mobile and desktop stack.
- **Telegram** is the first external surface. Slack, X and others are later entry points into the
  same conversations.

### 5. Company governance is the moat: one engine, three presets, gated by reversibility

Locked 2026-11-02. Governance must feel like Grok Bot for a solo user and scale to large or
regulated teams. **One policy engine with presets on top.** Full model:
[Governance model](architecture/governance-model.md).

**Always on, in every preset, never switchable (including self-hosted):**

- Workspace isolation, the encrypted vault, and a credential broker so secrets never enter the
  model, prompts, the VFS, mruby, logs or computer disks.
- The hard-deny floor: no money transfers between accounts, no password, 2FA or recovery changes, no
  credential export, and no agent changing its own grants, budget or policy.
- Untrusted-content labelling. Runs that read untrusted content lose automatic approval for
  external actions.
- **A trigger is never consent** for external actions.
- Pinned MCP tool definitions, an egress allowlist on computers, signed webhooks and rate limits.
- The event and audit log is always on.
- **An agent never approves anything.**
- **On-behalf-of access.** An agent never exceeds the human it acts for. In a DM, effective access is
  the agent's grants intersected with the requester's access. In a routine, it is intersected with the
  routine owner's access. In a group chat, it is intersected with the least-privileged human who
  invoked it. This prevents confused-deputy escalation.

**Gating by reversibility, not by "write".** This replaces the earlier rule that all writes need
approval by default.

| Action | Personal | Team | Company |
|---|---|---|---|
| Read | Allowed | Allowed within Space access | Allowed within Space access |
| Reversible internal edits (change set with "revert this run") | Auto-apply, visible in activity | Auto-apply; bulk edits (default over ~50 rows) and schema changes are proposals | Auto-apply small edits; proposals per Space policy |
| Delete | Moves to trash (30 days) and auto-applies | Trash auto-applies; purge needs approval | Trash is a proposal; purge needs two approvers |
| External drafts (email draft, calendar hold) | Auto-apply | Auto-apply | Auto-apply |
| External send, post or submit | Ask the first time per target, then one-tap "always for this recipient or domain" | Same; the agent's manager approves | Same; routine-started runs may only reach admin-allowlisted targets |
| Spend | Ask, or a per-agent spend cap | Budget per agent | Budget plus two approvers above a threshold |
| Exposure (share link, open port, add an MCP server or egress domain, share a connection) | Ask | Admin | Admin plus a second approver |
| Hard-deny floor | Never | Never | Never |

**Design rule: make actions reversible so they need no approval.**

- Archive and trash replace hard delete.
- Drafts come before sends.
- Every structured edit is a change set.

**Presets set defaults only.** Switching preset keeps every explicit grant, and everything can be
tuned afterwards. All presets ship in the AGPL self-hosted product; security is never paywalled.

- **Personal** (solo, Grok Bot style): the owner is the only approver. Approvals arrive as cards in
  the agent's DM, on Home and on Telegram. There are no role or policy screens.
- **Team** (about 2–50 people):
  - Owner, Admin and Member roles; Spaces carry permissions.
  - Each agent has a manager who is its default approver.
  - Connections are personal or shared. Each agent has a budget.
  - Members may grant "always" exceptions for their own personal connections. Exceptions on shared
    connections need an admin.
- **Company** (larger or regulated teams):
  - Approver policies and two approvers on high-risk classes.
  - Custom roles later.
  - Admin-approved allowlists for connections and MCP servers, skill approval, and a policy on who
    may attach computers.
  - Audit export and retention.
  - SSO/SCIM later; not claimed before it exists.

**Few, good approvals:**

- **Learned exceptions.** After repeated approvals of the same pattern (default 3), the card offers
  a scoped, exact-match "always allow", for example "Ada may email @acme.com". Admins can restrict
  who may accept these.
- **Batched review.** "Ada wants to send 12 follow-ups" is one card with a toggle per item.
- **Non-blocking approvals.** A pending approval parks only its step; the agent continues with other
  work.
- **Per-agent autonomy slider:** *Ask first · Balanced · Autopilot*.
  - Each position is a named bundle of scoped exceptions, capped by the preset and the floor.
  - This is **not** the deleted behaviour modes, which bypassed policy. The slider only writes grants
    into the policy engine.
  - New agents start at Balanced. The activity view suggests promotions based on approval history.
- **Exception scopes:** once, this run, this task, time-limited, or always. Exceptions match exactly
  on later calls.

**Permissions are simple on purpose (no Notion-style ACL soup):**

- Access is set at Space level. Private Spaces and personal items exist. A single object can be
  shared with a guest only as an explicit exception. There are no per-document ACLs.
- **Agents join Spaces like teammates.** "Add Ada to Sales" is the whole data-access model.
  Connections and computers are granted per agent on top.
- Effective access is Space membership ∩ the agent's grants ∩ the on-behalf-of human ∩ the external
  ACL (for deep connectors).
- "Why can't Ada see this?" is answered in one click from the policy engine's decision trace.

**Budgets and visibility:**

- Budgets for tokens, compute and spend, per agent and per workspace, pause work at the limit.
- Read-only run modes for heartbeats, monitors and background research, enforced where tools are
  registered.
- One durable event log (pillar 9) powers the audit trail, run replay, the activity feed, budget
  accounting, and the answer to "who allowed what, at what cost".
- **The UI follows the preset.** Personal shows approval cards, the per-agent "always allow" list and
  the slider. Team and Company add a Governance area in Admin: policies, exceptions, approvers,
  budgets and audit.

### 6. Agent primitives

The full list is in the [Agent Primitives Catalog](planning/agent-primitives-catalog.md):

- vault with per-agent grants; secure forms;
- password manager connectors (1Password first, then Bitwarden/Vaultwarden);
- takeover and hand back for CAPTCHAs, SSO and passkeys; live view;
- scoped and non-blocking approvals;
- agent inbox and one-time codes; single-use cards and spend caps;
- separate pause, stop, cancel-schedule, delete and kill controls;
- daily brief, monitors, action items and memory controls.

Every primitive works for VFS-only agents and computer agents, and on web, mobile and Telegram. On
Telegram, anything secret goes through the Mini App.

### 7. Core primitives, add-on apps, and no mess

OpenCompany avoids Notion's "everything is a page" sprawl by being structured by default.

- **Fixed core primitives, always on:** Threads, Issues, Tables and Records, Docs, Files,
  **Artifacts**, Routines, Agents and People. There is no generic "page" and no user-defined object
  kind. Docs are not containers. Artifacts are the only code-bearing primitive.
- **Issues, Tables and Docs are agent-native.** Every record is mentionable, assignable to an agent,
  and can emit events that trigger routines. Issues is core, not an add-on.
- **One level of containers:** Workspace → **Spaces** (flat, never nested) → objects. Every Space has
  the same six tools: Issues, Tables, Docs, Files, Artifacts and Routines. Only Files keeps folders,
  because it mirrors real file systems and mounts.
- **Typed everything:**
  - Docs have template types with properties.
  - Tables gain Person/Agent, Relation, Attachment and Long-text columns.
  - Embedding links to the canonical object rather than copying it.
- **Ownership and lifecycle:**
  - Every Doc, Table and Issue needs a human owner and a status (draft, current or archived).
  - Docs have a review date. Archived items leave search and agent context and redirect to their
    replacement.
- **Governed creation:** agents create records, issues and doc drafts within policy. Creating
  Spaces, Tables, fields or relations, changing a schema, and deleting anything are always proposals
  that a human approves.
  *Reconciliation note (2026-11-02):* pillar 5, locked later, governs deletes. Deleting moves items
  to a 30-day trash, which auto-applies in Personal and Team and is a proposal in Company. Purge
  needs approval. Schema changes are proposals in Team and Company per the pillar 5 table. The
  Personal default for structural changes is not yet specified.
- **System files are not content.** Agent identity and memory live on the agent profile and under
  the VFS `/agents` mount, not in Docs.
- **One concept per job:**

  | Today | Becomes |
  |---|---|
  | Lists | Folds into Issues |
  | Tasks (agent runs) | Runs inside threads and the agent profile |
  | Automation | Routines |
  | Dashboard, Activity, Workload | Home |
  | Org | Team |
  | Three file stores | One Files store |

  Which of today's `Task` and list models becomes Issues is decided in the
  [work apps design](planning/agent-native-work-apps.md).
- **Add-on apps** (accepted 2026-11-02). Optional apps are installed per workspace: Mail, CRM,
  Calendar, Helpdesk, Recruiting, Invoices, and third-party apps later. **Apps are built only from
  the core primitives and never bring their own storage.**
  - **Manifest.** Each app is a manifest declaring:
    - typed record types, which are schema-locked tables with relations;
    - views from a fixed catalog: list, board/pipeline, table, calendar, mail thread, record detail,
      timeline, form;
    - artifacts, which act as the app's custom views and endpoints;
    - routine templates;
    - an agent pack;
    - the connections it requires;
    - roles;
    - the events it emits.
  - **Uniform behavior.** Agents, search, approvals, change sets, revert and the event log work on app
    data automatically. App data appears in the VFS under `/apps/<app>/…`.
  - **Scope and lifecycle.** Apps live at workspace level and can show scoped views inside Spaces,
    for example Deals in the Sales Space. Uninstalling archives the data. Upgrades migrate the
    schema.
  - **First-party order:**
    1. Mail, built on the Gmail and Outlook connections. It doubles as the agent inbox (Phase 2).
    2. Calendar (Phase 3).
    3. CRM (Phase 4).
    4. Helpdesk, Recruiting and Invoices later.
  - **Third-party apps** come only as signed, declarative manifests, reviewed before install. Their
    custom views are interactive artifacts.
- **Artifacts** (locked 2026-11-02; full design in [Artifacts and Forms](planning/artifacts-and-forms.md)).
  Artifacts are where OpenCompany's strengths become visible output: governed live data, mruby server
  logic, routines, agents with computers, approvals, and self-hosting.
  - **Two kinds, one model:**
    - **Pages** are declarative reports and dashboards built from blocks (text, live views, charts,
      KPIs, queries, diagrams, timelines). They are live, or snapshotted on a schedule, and contain
      no code.
    - **Interactive artifacts** are custom HTML UIs plus optional mruby endpoints.
  - **No private storage.** Artifacts bind to Tables and other primitives through named bindings. An
    artifact that needs state gets an artifact-owned Table, which is visible and governed.
  - **Runtime:**
    - Interactive UI runs in a sandboxed iframe on a separate user-content origin with no cookies,
      using a default-deny CSP and the **MCP Apps bridge**.
    - Server logic runs as mruby endpoints under a new short `endpoint` profile.
    - Agents with a computer may publish the static build output of real frameworks.
    - Generated JavaScript never runs on the server.
    - Self-hosters set `ARTIFACT_ORIGIN`. Without it, a reduced-isolation fallback applies and is
      shown in Admin.
  - **Access and governance:**
    - A workspace member's access is the artifact's bindings ∩ the viewer's own access.
    - Edits go through platform change sets. Published versions are immutable at a stable URL.
      Calls are runs and events.
    - Every artifact has an owner, last-viewed tracking, a stale sweep, and an `/artifacts` mount
      in the VFS.
    - Remixing is allowed only inside the workspace.
  - **Publishing ladder:** draft → Space → workspace → signed link → public.
    - Signed links and public publishing are `exposure_widening` and always need approval.
    - **Public publishing is off per workspace** until an admin enables it.
    - External visitors get only share-safe endpoints, approved read-only published queries and form
      submission. They get no AI or connections by default.
    - Admin keeps a published-surfaces registry with a kill switch, abuse controls and a report link.
  - **Apps and artifacts share one manifest family.** An artifact can graduate into an app.
  - **Built-in blocks replace the deleted renderer packages:** Vega-Lite charts and Mermaid diagrams
    are rendered client-side; Typst PDF export goes into Files.
  - **Later:** OpenCompany's MCP server exposes interactive artifacts as `ui://` resources, so they
    render in Claude and ChatGPT.
- **Forms are a view type on any Table, not a separate app or artifact type** (locked 2026-11-02).
  - **One pipeline:**
    1. A public origin with no cookies.
    2. A captcha (ALTCHA by default; Turnstile, hCaptcha and reCAPTCHA pluggable), a honeypot and
       rate limits.
    3. Schema, logic and consent validation.
    4. Upload quarantine.
    5. A record created as a change set by the form-visitor principal.
    6. A `form.submitted` event, marked untrusted, that can start routines.
  - **Render modes:** a Tally-style page, one question at a time, conversational agent intake (its
    only tool is submit), embeds, and internal or Telegram forms.
  - **Artifacts may render custom form UIs**, but must submit through the form's endpoint.
  - **Agents generate forms** through plan, preview and approve.
  - **Retention and data rights:** retention, auto-delete, and export and erasure for submitters.

### 8. Open source first, hosted second

- The complete self-hosted application is AGPL-3.0-only. SDKs, protocols, integration kits and
  examples are Apache-2.0 where path notices say so. Contributions use DCO, inbound equals outbound.
- The owner is the sole author of the existing code, so the relicense has no contributor-authority
  problem. Counsel review is a confirmation step, not a blocker.
- OpenCompany Cloud is a hosted, managed version with managed computers (boat first, Bowerbird
  later). Only Cloud operations and billing are proprietary, and they never fork or replace
  self-hosted functionality.
- Self-hosting always remains a first-class, complete option.

### 9. Connections, events and routines

The single model for integrations, file sync, automations, triggers, channels and proactive
behaviour. The full design is in
[Connections, Events and Routines](planning/connections-events-routines.md).

```
Connections ──produce──▶ Events ──match──▶ Triggers ──start──▶ Routines ──execute as──▶ Runs
                               Policy applies at every step
```

- **Connections:** what the workspace is linked to. Each connection declares:
  - actions: the tools it offers;
  - data: mounts and synced content;
  - events: push, poll with a cursor, or none.

  Connections are personal or shared, and their secrets live in the vault behind the credential
  broker. One generic OAuth broker serves them all, with client registration in this order: CIMD,
  then DCR, then admin-entered client credentials.
- **Connection tiers:**
  - **Tier 1, deep connectors.** Native, few, high quality, app-owned under
    `app/Domain/Connections/`.
    - **Storage mounts:** Google Drive and OneDrive/SharePoint first, then Dropbox, Box, S3/R2,
      WebDAV and SFTP. Files are indexed and fetched lazily into Files and the VFS. Writes go
      through the VFS write path with revision checks. Effective access is the intersection of the
      external ACL and OpenCompany grants.
    - **Mailboxes:** Gmail, Outlook and IMAP, feeding agent and shared inboxes.
    - **Calendars:** Google, Microsoft and CalDAV.
    - **Later:** Notion/Confluence import and Git repositories.
  - **Tier 2, generic:**
    - a rebuilt MCP client on `laravel/mcp` (MCP 2026-07-28, OAuth 2.1, pinned tool definitions
      that need re-approval when they change, server hints treated as hints only);
    - API manifests: an OpenAPI subset exposed as search-and-execute tools;
    - SKILL.md skills, and plugin import for Agent Plugins 1.0 and Claude plugin bundles (skills
      and MCP parts only; digest-bound approval; no automatic install from public registries);
    - a generic signed inbound-webhook connection;
    - an MCP-resources mount;
    - optional managed connectors attached as MCP servers: Activepieces (MIT, self-hosted
      sidecar), or Composio, Pipedream or Zapier with the user's own key. Never a hard dependency.
- **Events:** one durable, append-only workspace log with a standard envelope: type, source, subject,
  actor, time, idempotency key, untrusted payload, and causality chain and depth. Sources are internal
  domain changes, connections, channels and time. The log replaces the scattered per-feature event
  tables over time.
- **Triggers:** schedule, event filter, heartbeat, inbound message or manual. Never consent.
- **Routines:** owned by an agent or team, and versioned. Three kinds:
  - **agent:** instructions or a skill, run by the LLM;
  - **script:** mruby with no LLM, cheap and deterministic;
  - **hybrid:** a script that calls the agent only for judgment.

  Each routine sets a mode (read-only or act), an output target, a notify policy (always, only if new,
  on failure), debounce and coalescing, concurrency, quiet hours, a budget and an approval policy.
  Routines are authored in chat or from "save this task as a routine". **There is no visual flow
  builder.**
- **Runs:** every routine execution is a run on the existing control plane.
- **Proactivity is configuration:**
  - a heartbeat is a scheduled read-only routine with checkpoints that notifies only if something is
    new;
  - a monitor is an event-triggered read-only routine;
  - a daily brief is a scheduled routine;
  - follow-ups are limited to threads a human started, with a cap on unanswered messages.
- **Loop protection:** every event and run carries a causality chain and depth. Failed events go to a
  replayable dead-letter queue.
- **Outward-facing later:** OpenCompany itself as an MCP server, MCP Apps rendered as messenger
  cards, and A2A Agent Cards per durable agent.

### 10. The `oc` CLI (Rust, agent-first)

Locked 2026-11-02. Full design: [`oc` CLI and hosts](planning/oc-cli-and-hosts.md).

- **One Rust binary, `oc`.** It is both the command-line client and the connected-host daemon. It
  is a single static binary for Linux (x86_64, aarch64), macOS and Windows.
- **Agents are the first user, humans a close second.** This follows Bowerbird's agent-first CLI
  decisions:
  - a self-describing schema (`oc schema`, `oc guide agents`);
  - errors that name the next command;
  - compact output, `--json`/NDJSON and stable exit codes;
  - `--wait`, and `--dry-run` that returns change sets;
  - structured pending-approval results;
  - an **agent-experience eval suite** that gates releases.

  For humans: a `ratatui` TUI for chat and Home, completions, man pages, and multiple contexts
  (self-hosted and Cloud). Login uses the device flow, and tokens live in the OS keychain.
- **It covers the whole product:**
  - Home, approvals, chat and `oc ask … --wait`;
  - agents;
  - issues, tables, docs, files and forms;
  - change sets and runs (tail, replay, revert);
  - routines and events;
  - `oc fs` and `oc mount`;
  - `oc code run`;
  - `oc artifact dev/publish`;
  - apps, skills and plugins;
  - connections;
  - `oc host`;
  - `oc admin` for self-hosters;
  - `oc api`.
- **`oc mcp serve`** exposes the user's workspace as a local MCP server. Claude Code, Codex, Cursor
  and others can then work with OpenCompany issues, tables, docs and approvals using the user's own
  access.
- **No private endpoints.** `oc` is a client of the same versioned public API and realtime stream
  as the web and mobile apps. Its Rust client is generated from OpenAPI.
- **Distribution:** a separate `opencompany-cli` repository under **Apache-2.0** (the SDK and client
  boundary in pillar 8). It ships through the OpenCompanyApp Homebrew tap, an install script,
  `cargo install`, `.deb` and `.rpm` packages, and signed GitHub Releases, and it can update itself.
- **Shared with Bowerbird:** the auth, contexts, output, schema and eval-harness crates are shared
  with Bowerbird's `bwb` client track.

### 11. Full data sovereignty, hosted included

Locked 2026-11-02. Full design: [Data sovereignty](planning/data-sovereignty.md).

> **Your workspace is yours.** Download any object, export the whole workspace, back it up to
> storage you own, and move between OpenCompany Cloud and self-hosting in either direction. Any time,
> any plan, no extra cost, documented open formats.

- **Object downloads** are available everywhere: in the UI, through `oc export` and through the API.
  - Docs: Markdown, PDF or DOCX.
  - Tables: CSV, XLSX or JSON.
  - Issues and threads: Markdown or JSON.
  - Files: the originals.
  - Artifact bundles and form submissions.
  - Agent packs: identity, memory with sources, skills, routines and grants.
- **Workspace archive (`.ocarchive`).**
  - Contains every primitive and all app data, with schemas, history, change sets, the event log and
    audit trail, files, agents, Spaces, roles and policies.
  - Round-trips into self-hosted or Cloud as a restore, a clone or a migration, and CI tests that it
    round-trips.
  - Secrets are excluded by default. An owner may include them, encrypted with a passphrase; agents
    never can.
  - The format spec is published under Apache-2.0.
- **Backups to customer-owned storage:** S3, R2, B2 or MinIO, WebDAV, SFTP, a Drive mount, or a
  connected host.
  - They run on a schedule, are incremental, and are **encrypted with a key the customer holds**, so
    even Cloud cannot read them.
  - Verification runs test-restore them.
  - Self-hosters get `oc admin backup/restore`, with `oc admin doctor` warnings.
- **Live access and mirrors:** the API, `oc`, `oc mount` and `oc mcp serve`, plus an optional
  one-way continuous **Git mirror** (Docs as Markdown, Tables as CSV, Issues, artifact source) to
  the customer's Git host.
- **Cloud guarantees:**
  - Cloud runs the same AGPL code, so it has no proprietary data formats.
  - Export and backups are free and unlimited on every plan.
  - After a subscription lapses, the workspace stays read-only and exportable for at least 90 days.
  - `oc admin import --from cloud` migrates to self-hosting.
  - Customers choose the region (EU first) and get verified deletion with a receipt.
  - Customer-managed keys come later.
- **Beyond data:**
  - Bring your own model keys or local models. A workspace can be set to send nothing to third-party
    models, and the run log shows which provider handled each run.
  - **No phone-home.** Self-hosted installs send no telemetry by default, and there are no remotely
    flippable flags or egress switches. CI enforces this.
- **Governance:**
  - Downloading objects you can see is a read; bulk downloads are audited.
  - A full workspace export is limited to owners and admins: automatic in Personal, an admin in
    Team, an admin plus a second approver in Company. It is always audited, and links expire.
  - Changing backup or mirror destinations is in the `governance` class, so agents cannot do it.
  - Any member can export their own personal data (GDPR).

## Nuclear decisions (accepted 2026-11-02)

OpenCompany has no users yet, so it breaks compatibility freely:

- **Integration package system deleted.**
  - The ~600 PHP integration packages, `integration-core` contracts, the bundle metapackage, the
    60 MB regex-built catalog, package-owned OAuth controllers, per-package `IntegrationSetting`
    forms and the `create-integration` scaffolding prompt are deleted.
  - The `integrations` repository is archived read-only with its history. Its ~9.9k uncommitted
    bulk-regeneration paths are backed up outside git and discarded.
  - The Google package's OAuth and client code is mined for the native Drive, Gmail and Calendar
    connectors.
- **Automations replaced.** Automations (prompt and script), `RunDueAutomations`,
  `ListAutomationRule`, `HasTriggers`/`IntegrationWebhook` and hardcoded cron jobs such as Telegram
  digests become Routines, Triggers and Events.
- **Migrations squashed** into a fresh baseline. No compatibility code and no legacy-history gates,
  including those in the mruby plan.
- **Behaviour modes deleted** in favour of the policy model (pillar 5): presets, gating by
  reversibility, and an autonomy slider that only writes scoped grants.
- **API-first now.** Inertia is frozen. Workload and the old Dashboard are deleted, and Lists folds
  into Issues.
- **Branches cleaned up.** After the Phase 0 work lands, it becomes `main`. The old `main` is tagged
  for history, and the QuickJS, mruby and Lua branches are deleted. Remote deletions and archiving
  happen only with the owner's go-ahead.
- **Kept as the real assets:** the run control plane, approvals, memory, VFS, mruby and Telegram.
  Telegram is refactored onto the messenger conversation model later, not rewritten.

## Phases

| Phase | Scope | Exit criteria |
|---|---|---|
| 0 · Foundation (in progress) | Part 1 audit fixes: P0 authorization and security, including the cross-workspace credential singleton bug and MCP isolation and SSRF. Land the run control plane and VFS. mruby-only Code Mode. AGPL licensing. Honest website. Tests in CI. Nuclear cleanup: migration squash, package-system deletion, Connections model with vault and credential broker, event log with causality chain, policy model replacing behaviour modes. Artifacts groundwork: user-content origin and sandbox proxy design, Code Mode bridge re-homed into the app, renderer outputs moved into Files with permissions. `oc` groundwork: device-flow OAuth, personal and host token types, OpenAPI generation. Data sovereignty groundwork: `.ocarchive` format designed with the squashed baseline schema; a no-telemetry and no-remote-flags check in CI | No known cross-workspace flaws; CI runs tests; one application license; website claims match `CURRENT_STATE.md`; package system gone; event log and Connections model in the baseline schema |
| 1 · Messenger | API-first messenger client and realtime stream; activity feed from the event log; routines created from chat (Automations migrated to Routines); rebuilt MCP client; Google Drive storage mount as the flagship deep connector. Artifacts v1: Pages (blocks, live views, charts, diagrams, snapshots) and interactive artifacts at private, Space and workspace level with read bindings; artifact cards and live preview in the context pane; versions and stable URLs; `/artifacts` mount in the VFS. **`oc` v0**: login and contexts, Home, approvals, ask and chat TUI, work objects, `oc fs`, runs, `--json` and schema, `oc mcp serve`, Homebrew and install script. Object downloads in the new client and `oc export` | A team can do a full day's work with agents from chat alone, including Drive files |
| 2 · Computer v1 | `ComputerProvider` with local Docker and boat; shell, files, browser, takeover viewer; secure forms; egress allowlist; VFS projection; idle stop and snapshots; Gmail and agent inbox; inbound webhooks; script and hybrid routines; API manifests; skills and plugin import. Ships with the money-movement and password-change hard-deny entries and a basic compute cap. Artifact mruby `endpoint` profile, write bindings through change sets, AI bridge with quotas, static bundles from computers. **Forms v1**: form view, page mode, internal and public submission, captcha, uploads, `form.submitted` events. **Connected hosts v1**: outbound `oc host` daemon with container sandbox, systemd service, Admin → Hosts, Raspberry Pi (aarch64) builds, SSH hosts over Tailscale or direct addresses; `oc artifact dev`, `oc code run`. Workspace archive export and import (`oc admin export/import`), artifact and form exports, `oc admin backup/restore` | An agent completes a browser-and-shell task on its own computer with takeover and approvals; VFS-only agents unaffected |
| 3 · Governance | Human roles and approver policies; full hard-deny floor; budgets that pause work; run replay and audit; 1Password, then Bitwarden/Vaultwarden; scoped and non-blocking approvals; heartbeats, monitors and daily briefs; save a task as a routine; OneDrive/SharePoint and calendars. Signed and public artifact publishing with published queries; published-surfaces registry, abuse controls and kill switch; artifact lifecycle sweeps. **Forms v2**: logic, partial saves, one-question mode, conversational intake, embeds, consent and retention. Host local MCP bridge and event source, `oc mount` (FUSE), bwrap sandbox, the `oc` agent-experience eval gate, `oc admin` for self-hosters. Scheduled encrypted backups to customer-owned storage, Git mirror, export governance gates, GDPR member export, backup verification | An admin can answer "who allowed what, at what cost" for any agent action |
| 4 · Work apps and apps platform | Agent-native Tables, Issues and Docs: mentions, assignment, record-change events and triggers, change sets for every object. Spaces and the anti-mess rules. App manifest runtime with CRM as the first schema-only app; Mail (Phase 2) and Calendar (Phase 3) are delivered on the same manifest model. Artifacts as custom views in app manifests; artifact-to-app packaging; artifact templates. `oc app`, plugin tooling and `oc forms` | Agents run real workflows in Tables and Issues without connections; CRM installs from a manifest with no app-specific storage |
| 5 · Cloud | Hosted OpenCompany Cloud with managed computers on boat (needs a partner agreement), moving to Bowerbird sandboxes; OpenCompany as an MCP server; MCP Apps cards; A2A. Custom domains, MCP Apps export of artifacts to Claude and ChatGPT, Git export, payments in forms and artifacts. Cloud contexts in `oc`; Windows host support. Cloud exit guarantees (free export, 90-day read-only window, Cloud-to-self-host migration), region choice, verified deletion; customer-managed keys later | Paying pilot workspaces on hosted Cloud; self-host parity maintained |

Phases may overlap. Phase 0 security work always takes priority over new surfaces.

## What this supersedes

| Earlier direction | Disposition |
|---|---|
| "A Company in a Box", "Inspired by OpenClaw" website messaging | Replaced by the one-sentence decision above |
| Reader-first modular company OS and Allbase lab ([plan](planning/modular-platform-and-allbase-lab.md)) | Replaced by add-on apps built only from core primitives (pillar 7). Reader is not the first product; Mail, Calendar and CRM are the first apps |
| AI app builder positioning ([market research](market/ai-app-builder-market-research-2026-05-25.md)) | Not the product. Artifacts are a core primitive bound to governed workspace data, not a standalone app builder |
| Lua scripting and the QuickJS branch | Retired. mruby is the only Code Mode runtime |
| Embedded Chromium browser in the app | Replaced by the browser on the agent's computer |
| Positioning against Zapier, n8n, Make and Activepieces | Secondary. Primary competitors are Grok Bot and its clones, Paperclip, Multica and Plane agents. Activepieces is an optional connector |
| Per-package PHP integrations ([integrations](planning/integrations.md), [integration refactor](planning/integration-refactor.md)) | Deleted. Replaced by deep connectors plus generic MCP, API manifests and skills (pillar 9) |
| Automations and visual programming ([research](planning/visual-programming-research.md)) | Replaced by Routines authored in chat; no flow builder |
| Bowerbird owning triggers and durable execution ([integration doc](architecture/bowerbird-durable-automation-integration.md)) | Superseded. OpenCompany owns events and routines natively. Bowerbird is a computer provider, and optionally later an executor for heavy pipeline routines behind the routine contract |
| Agent behaviour modes (autonomous, supervised, strict) | Deleted. Replaced by the policy model with presets, gating by reversibility, and a per-agent autonomy slider that writes scoped grants (pillar 5) |
| Inertia pages as the UI architecture | Frozen. API-first clients |

## Non-goals

- AI companions and consumer single-user assistants.
- Competing with OpenClaw on the number of chat channels.
- Running a VM fleet inside OpenCompany. Compute comes through a provider; the local
  Docker/Firecracker provider runs on the self-hoster's own machine through the same contract.
- A visual workflow builder.
- Maintaining hand-written per-app integration packages.
- Claiming compliance, SSO, SLAs or hosted features before they exist.

## Relationship to Bowerbird

OpenCompany is Bowerbird's flagship customer, not a competing product. The shared contract is the
Workspace API. OpenCompany never waits on Bowerbird: boat and local providers ship first, and the
Bowerbird provider follows when its sandboxes are live.

## Open questions

- Pricing and packaging of OpenCompany Cloud (per seat, per agent, compute pass-through).
- Whether the shared team computer is offered at all on hosted Cloud.
- How browser logins are captured and stored per agent (takeover sign-in versus a secrets form).
  Until this is settled, browser sign-in sessions on an agent's computer are an explicit exception to
  "credentials never enter the computer".
- The default connection ownership (personal or shared) for each connection category.
- Whether the Activepieces sidecar ships in the default docker-compose or only in docs.
- The mobile and desktop client stack; whether mobile web gets push before native apps.
- Whether the web client leaves this repository, and Wayfinder versus a generated OpenAPI client.
- Which of today's `Task` and list models becomes Issues
  ([work apps](planning/agent-native-work-apps.md)).
- Interim approval authority in Phase 1, before Phase 3 approver policies exist. Proposed: only
  workspace admins, or the human who started the conversation, may answer an approval, never the
  requesting agent.
- A boat partner agreement for managed computers on Cloud. boat's public terms restrict reselling,
  and boat has no idle timer, so OpenCompany owns idle stop.

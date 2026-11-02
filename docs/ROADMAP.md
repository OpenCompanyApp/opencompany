# OpenCompany Roadmap and Open Audits

Status: Active prioritized engineering register, organized by the phases of the locked product direction.
Verified: phase structure 2026-11-02 against [PRODUCT_DIRECTION.md](PRODUCT_DIRECTION.md); Phase 0
audit items 2026-08-09 against the active local checkout; licensing publication gate 2026-09-01.

This file consolidates active work that was previously scattered across audits, TODOs, product copy,
and long planning documents. Detailed plans remain linked; this register owns priority and current
disposition.

[PRODUCT_DIRECTION.md](PRODUCT_DIRECTION.md) owns what OpenCompany is becoming and the phase order;
this roadmap follows it. [CURRENT_STATE.md](CURRENT_STATE.md) owns what exists today. Nothing here is
an implementation claim, and no dates or estimates are given.

**Priority rule:** Phases may overlap, but Phase 0 security and authorization work always takes
priority over new surfaces.

## Phase overview

| Phase | Focus | Design docs |
|---|---|---|
| 0 · Foundation (in progress) | Audit fixes and P0 security, land run control plane and VFS, mruby-only Code Mode, AGPL licensing, honest public copy, tests in CI, nuclear cleanup, Connections model with vault and credential broker, event log with causality chain, policy model, artifacts groundwork, `oc` groundwork, `.ocarchive` format design and no-telemetry CI check | This file; [artifacts-and-forms.md](planning/artifacts-and-forms.md); [connections-events-routines.md](planning/connections-events-routines.md); [governance-model.md](architecture/governance-model.md); [mruby plan](planning/mruby-only-code-mode-implementation.md) |
| 1 · Messenger | New Vue 3 + Reka SPA client on the API and realtime stream: one rail, Home, three panes, inline approvals, typed run activity, mobile bottom bar; Inertia pages deleted at parity; activity from the event log, routines from chat, rebuilt MCP client, Google Drive mount; Artifacts v1 (Pages and interactive artifacts with read bindings); `oc` v0 with `oc mcp serve`; object downloads and `oc export` | [ui-structure-and-agent-editing.md](planning/ui-structure-and-agent-editing.md), [messenger-ui.md](planning/messenger-ui.md), [api-first-client-split.md](architecture/api-first-client-split.md), [connections-events-routines.md](planning/connections-events-routines.md) |
| 2 · Computer v1 | `ComputerProvider` with local Docker and boat, takeover, secure forms, VFS projection, Gmail and agent inbox, Mail app, webhooks, script/hybrid routines, API manifests, skills and plugin import, first hard-deny entries, compute cap; artifact endpoints and write bindings; Forms v1; Connected hosts v1 (`oc host`), `oc artifact dev`, `oc code run`; workspace archive export/import, `oc admin backup/restore`; agent connections (ACP runner, Copilot coding agent) | [computer-provider-contract.md](architecture/computer-provider-contract.md), [agent-primitives-catalog.md](planning/agent-primitives-catalog.md) |
| 3 · Governance | Roles and approver policies, full hard-deny floor, budgets, replay and audit, password managers, scoped and non-blocking approvals, heartbeats/monitors/daily briefs, OneDrive/SharePoint and calendars, Calendar & Scheduling app; signed and public artifact publishing; Forms v2; host MCP bridge and event source, `oc mount`, bwrap sandbox, `oc` eval gate, `oc admin`; encrypted backups to customer storage, Git mirror, export governance, GDPR export; hosted agent connections (Cursor, Codex, Jules, Claude Code as APIs allow) | [governance-model.md](architecture/governance-model.md) |
| 4 · Work apps and apps platform | Agent-native Tables, Issues and Docs with record-change events and triggers, change sets for every object, Spaces and anti-mess rules, app manifest runtime with CRM first; artifacts as app custom views, artifact-to-app packaging, templates; `oc app`, plugin tooling, `oc forms` | [agent-native-work-apps.md](planning/agent-native-work-apps.md), [ui-structure-and-agent-editing.md](planning/ui-structure-and-agent-editing.md) |
| 5 · Cloud | Hosted Cloud on boat then Bowerbird, OpenCompany as MCP server, MCP Apps cards, A2A; custom domains, MCP Apps export of artifacts, Git export, payments in forms and artifacts; Cloud contexts in `oc`, Windows host support; Cloud exit guarantees, region choice, verified deletion | [opencompany-cloud.md](planning/opencompany-cloud.md) |
| Bowerbird track | Shared CLI crates, one mruby engine and dialect, CI on Bowerbird Runners (now); Bowerbird computer driver (Phase 2); Bowerbird deep connection and pipeline graduation (Phases 3–4); shared account and wallet (Phase 5); Bowerbird Agents (with S1 or later) | [bowerbird-synergy.md](planning/bowerbird-synergy.md) |

**API-first is the rule now, not a separate track.** Inertia is frozen: no new Inertia pages or page
props. New UI, starting with the Phase 1 messenger, is a client of the versioned public API and the
realtime stream ([api-first-client-split.md](architecture/api-first-client-split.md)).

## Phase 0 — Foundation (in progress)

Scope: the audit fixes below; P0 authorization and security, including the cross-workspace
credential singleton bug and MCP isolation and SSRF; landing the run control plane and VFS; the
mruby-only Code Mode cutover; AGPL licensing; an honest website; tests in CI; and the nuclear cleanup
with its replacement foundations (Connections model, event log, policy model).

Exit criteria: no known cross-workspace flaws; CI runs tests; one application license; website and
README claims match [CURRENT_STATE.md](CURRENT_STATE.md); package system gone; event log and
Connections model in the baseline schema.

### P0 — authorization and product truth

These items block credible enterprise positioning and should precede broad new platform claims.

1. Introduce a central human/agent authorization decision service and explicit fixed roles.
2. Enforce channel membership for channel/message reads and writes.
3. Enforce document permissions across CRUD and search. (Pillar 5 moves access to Space level with
   no per-document ACLs; until Spaces exist, today's stored document permissions must still be
   enforced or removed, never silently ignored.)
4. Scope list automation rules through workspace-owned records. (`ListAutomationRule` is deleted by
   the nuclear cleanup; this item closes when the model is gone or scoped, whichever lands first.)
   **Done 2026-11-02:** scoped by `workspace_id` in the API and agent tools, with tests.
5. Route all direct-message creation through the workspace-validating resolver and reject self-DMs.
   **Done 2026-11-02**, with tests.
6. Add explicit agent-management and approval capabilities, including no self-approval for high-risk actions.
   **Partly done 2026-11-02:** `approver` role, no self-approval, agents never resolve, full
   permission re-check on execution, workspace-owned approvals. Open: agent-management capability
   and a members-settings UI to assign roles (API only today).
7. Add scoped/expiring/rate-limited AI Gateway keys. **Partly done 2026-11-02:** per-key and
   failed-auth rate limits; scopes and required expiry remain open.
8. Split settings danger actions into separately authorized, audited commands.
9. Add an append-only authorization decision ledger.
10. Keep public README, website copy, demos, and feature catalogs limited to claims in [CURRENT_STATE.md](CURRENT_STATE.md).
    **Website aligned 2026-11-02** (uncommitted in `opencompany-website`, awaiting owner review).
11. Before publishing the AGPL transition, verify copyright and contributor authority, review the
    final documents with counsel, record the exact cutover commit/release date, preserve historical
    BSL/FSL terms, and activate the DCO contribution workflow described in
    [CONTRIBUTING.md](../CONTRIBUTING.md). The owner is the sole author of the existing code, so
    contributor authority is not in question and counsel review is a confirmation step, not a
    blocker. **2026-11-02:** AGPL files committed on `feat/vfs`; first Apache-2.0 path notice
    (`resources/code-docs/`); counsel review and the release cutover remain.
12. Fix the cross-workspace credential singleton bug so credentials can never resolve across
    workspaces. **Done 2026-11-02 (app-side):** package client singletons are rebound per
    resolution and the resolver fails closed without a workspace; the package-level fix goes away
    with the nuclear cleanup.
13. Isolate MCP servers per workspace and close SSRF paths: URL, redirect and response limits,
    private-network blocking, and provenance (formerly a P2 hardening item). **Partly done
    2026-11-02:** per-workspace provider isolation, URL/redirect/private-network guard with DNS
    pinning and an operator allowlist, untrusted-result labelling. Open: response-size limits and
    provenance records.
14. Stop broadcasting `ApprovalCreated` and `AgentStatusUpdated` on public channels; use private,
    workspace-authorized channels (found in the
    [UI structure audit](planning/ui-structure-and-agent-editing.md)).

Detailed plan: the sibling vault currently owns `Architecture/OpenCompany/Planning/permissions-rbac-policy-system.md`; its technical implementation copy should ultimately move into this repository before coding begins.

### P1 — stabilize the working-tree runtime

1. Finish and commit the agent-run queue control plane as one scoped change after focused review.
   **Done 2026-11-02** (`ccd1997`, then merged with mruby in `bf2826c`).
2. Run the agent-run, automation, subagent, direct-message, VFS pagination, VFS capability, and VFS approval suites together.
   **Done 2026-11-02:** the full backend suite passes in parallel (1,011 passed, 9 skipped).
3. Apply and verify the pending local
   `2026_08_09_000003_add_vfs_pagination_indexes` migration before treating the
   local schema as current.
4. ~~Restore/verify the local `Lua\Sandbox` runtime so Lua/VFS bridge tests can run.~~
   Superseded: Lua is retired and mruby is the only Code Mode runtime. The Lua/VFS bridge test
   failures are resolved by the mruby-only cutover, not by restoring Lua. Implementation and
   evidence are owned by the [mruby plan](planning/mruby-only-code-mode-implementation.md) and
   [progress log](planning/mruby-implementation-progress.md).
5. Exercise stale-run reconciliation, cancellation, ambiguous tool outcomes, and queue-insert failure in a production-sized database/queue rehearsal.
6. Revalidate the VFS command catalog, documentation, and generated Code Mode help from the same
   command-spec registry (originally "generated Lua help").
7. Reconcile the stale `VfsFeatureTest` assertion for the new
   `rows.ndjson` cursor-paged read-only reason. **Done** (VFS suites green).
8. Decide when the single physical queue needs separate latency classes for chat, automation, broadcasts, and maintenance.

Architecture reference: [Agent Run Queue Control Plane](architecture/agent-run-queue-control-plane.md).

### P1 — close unresolved audits

#### Runtime and memory

- Count structured tool/checkpoint payloads correctly in retry context budgets.
- Deduplicate durable facts extracted across later compactions.
- Preserve explicit protected context classes during compaction.
- Expand tests around protected context and durable memory extraction.
- Reconcile old Plane issue statuses; prefix context overrides and prompt-cache usage metrics now appear implemented.

#### Maintainability

- Split response completion, delegation callback, and final-error delivery out of `RespondToChatMessage`.
- Move message creation/side effects into chat-domain use cases.
- Split settings reads, diagnostics, and danger actions.
- Continue replacing hidden `workspace()` dependencies with explicit workspace/actor inputs.
- Add stable read models for large API payloads.

#### Browser QA

- Add shared `401`/`419` session-expiry recovery.
- Replace hardcoded sidebar approval/chat badges with scoped live counts.
- Keep agent selection and route query synchronized.
- Block self-DM routes and backend creation.
- Recheck mobile composer spacing and mobile task tabs.
- Migrate/remove the legacy theme storage key.
- Add user-facing renderers for common tool arguments.
- Browser-revalidate the existing modal, Monaco, starter-card, workspace-agent, task-chat, accessibility, and light-mode fixes.

#### Testing and CI

- Add focused PHP tests to CI, then frontend build/type checks and browser smoke coverage.
  **Done 2026-11-02** except browser smoke: one `ci.yml` runs the parallel backend suite with the
  pinned mruby engine, a PostgreSQL/pgvector job (non-blocking until first green), PHPStan over a
  checked-in baseline, typecheck/build and the production-image smoke.
- Remove the stale `prism-codex` clone from CI when dependency installation no longer needs it.
  **Done 2026-11-02.**
- Replace the vault's unchecked feature-test map with generated route/test evidence plus a smaller manual checklist.

### Nuclear cleanup

OpenCompany has no users yet and breaks compatibility freely. Implementation is owned by the
session executing the cleanup in code; this list is the register.

- **Delete the integration package system:** the ~600 PHP integration packages, `integration-core`
  contracts, the bundle metapackage, the catalog, package-owned OAuth controllers, per-package
  `IntegrationSetting` forms and the `create-integration` scaffolding. Mine the Google package's
  OAuth and client code for the native Drive, Gmail and Calendar connectors first.
- **Archive the `integrations` repository** read-only with its history; back up its uncommitted
  bulk-regeneration paths outside git before discarding them.
- **Squash migrations** into a fresh baseline, with no compatibility code and no legacy-history
  gates (including those in the mruby plan).
- **Delete agent behaviour modes** (autonomous, supervised, strict and the autonomous default) in
  favour of the policy model below.
- **Delete Workload and the old Dashboard; fold Lists into Issues.** Their job moves to Home in the
  new client. Which of today's `Task` and list models becomes Issues is decided in
  [agent-native-work-apps.md](planning/agent-native-work-apps.md).
- **Delete dead UI code:** legacy chat and unused shared components, fake badges, dead buttons and
  dead listeners (about 7k lines, per the UI structure audit).
- **Replace Automations** (prompt and script), `RunDueAutomations`, `ListAutomationRule`,
  `HasTriggers`/`IntegrationWebhook` and hardcoded cron jobs (for example Telegram digests) with
  Routines, Triggers and Events.
- **Clean up branches.** After Phase 0 lands it becomes `main`; tag the old `main` for history;
  delete the QuickJS, mruby and Lua branches. Remote deletions and archiving happen only with the
  owner's go-ahead.
- **Keep:** the run control plane, approvals, memory, VFS, mruby and Telegram.

### Replacement foundations

Design: [connections-events-routines.md](planning/connections-events-routines.md) and
[governance-model.md](architecture/governance-model.md).

- **Connections model** with the vault and credential broker: personal or shared connections that
  declare actions, data and events; secrets in the vault, never in the model, prompts, VFS, mruby or
  logs; one generic OAuth broker (CIMD, then DCR, then admin-entered client credentials).
- **Event log:** one durable, append-only workspace log with the standard envelope (type, source,
  subject, actor, time, idempotency key, untrusted payload, causality chain and depth) and a
  replayable dead-letter queue.
- **Policy model:** one policy engine (pillar 5) replacing behaviour modes, gated by
  reversibility rather than by "write": reversible internal edits go through change sets with
  "revert this run", drafts come before sends, deletes go to trash, and external sends, spend and
  exposure are gated. Exceptions are scoped (once, this run, this task, time-limited, always) and
  matched exactly on later calls. The always-on invariants ship with it and are never switchable:
  workspace isolation; vault and credential broker; the event and audit log; untrusted-content
  labelling (loses automatic approval for external actions); a trigger is never consent for
  external actions; an agent never approves anything; and **on-behalf-of access**, where effective
  access is the agent's grants intersected with the requester (DM), the routine owner (routine) or
  the least-privileged invoking human (group chat).
  - The Personal, Team and Company presets set defaults on top of the engine; switching keeps every
    explicit grant, and all presets ship in the AGPL self-hosted product. The direction's phase
    table does not phase the presets themselves; the engine they configure is Phase 0 and roles and
    approver policies are Phase 3.
- **API-first groundwork:** the first versioned API endpoints and the realtime stream the Phase 1
  messenger needs. Inertia is frozen from now on.

### Other Phase 0 workstreams

- **mruby-only Code Mode:** complete qualification and local cutover; remove remaining Lua paths.
  Owned by the [mruby plan](planning/mruby-only-code-mode-implementation.md). **2026-11-02:** merged
  into `feat/vfs`, Lua removed, VFS scripting ported, live GLM evals 6/6
  ([evidence](testing/mruby-glm-evals-2026-11-02.md)); stable engine release and security review
  remain.
- **AGPL licensing:** see P0 item 11; owned by the licensing documents.
- **Honest public copy:** README, website, demos and catalogs match `CURRENT_STATE.md`. No "Company
  in a Box", "Inspired by OpenClaw", integration-count or behaviour-mode claims (P0 item 10).
- **Tests in CI:** see Testing and CI above.
- **Artifacts groundwork** ([artifacts-and-forms.md](planning/artifacts-and-forms.md)): design the separate user-content origin and sandbox
  proxy; re-home the Code Mode bridge into the app; move renderer outputs into Files with
  permissions. The renderer packages become built-in chart and diagram blocks plus Typst PDF export
  into Files.
- **`oc` groundwork** ([oc-cli-and-hosts.md](planning/oc-cli-and-hosts.md)): device-flow OAuth, personal and host token types, and
  OpenAPI generation for the generated Rust client.
- **Data sovereignty groundwork** ([data-sovereignty.md](planning/data-sovereignty.md)): design the `.ocarchive` workspace archive
  format alongside the squashed baseline schema, and add a CI check that enforces no telemetry and
  no remotely flippable flags or egress switches.

## Bowerbird synergy track (Now: Phases 0–1)

Design: [bowerbird-synergy.md](planning/bowerbird-synergy.md). OpenCompany never waits on Bowerbird, and self-hosted OpenCompany never
requires a Bowerbird account. Bowerbird milestones (C1, S1, C4/C5) are Bowerbird's, not OpenCompany
dates.

- **Shared agent-first CLI crates** (contexts, device login, keychain, structured errors, schema and
  guide, eval harness) in Bowerbird's public Apache-2.0 client track, consumed by `oc`.
- **One mruby engine and dialect spec:** Bowerbird owns the mruby guest, OpenCompany owns host
  bindings and permissions, and OpenCompany's Code Mode evals are part of the engine release gate.
- **OpenCompany CI on Bowerbird Runners** when Bowerbird's C1 opens.
- **Open owner decision:** which GitHub org and legal entity hold OpenCompany (`OpenCompanyApp`,
  `BowerbirdHQ`/`bowerbird-si`, Ginger Media BV, or another structure), and the wallet currency.

## Phase 1 — Messenger

Design: [messenger-ui.md](planning/messenger-ui.md),
[api-first-client-split.md](architecture/api-first-client-split.md) and
[connections-events-routines.md](planning/connections-events-routines.md).

Scope:

- **API-first messenger client** on a versioned, documented public API (OpenAPI), with token/OAuth
  authentication suitable for native apps, a realtime event stream (WebSocket/SSE) for chat, run
  progress and approvals, and push notifications for approvals and mentions.
- **New client:** a Vue 3 + Reka UI single-page app with a token-based design system (semantic
  tokens, wrapper components, dark mode through tokens). Old Inertia pages are deleted as the new
  client reaches parity. Design: [ui-structure-and-agent-editing.md](planning/ui-structure-and-agent-editing.md);
  mockups: [sidebar](ui/mockups/sidebar-2026-11.html) and
  [primitives plus apps](ui/mockups/apps-and-primitives-2026-11.html).
- **One 260px left rail:** Home, Pinned (per person), Agents (opening one starts a DM; status, and 🖥
  for agents with a computer), People, Groups and channels, Apps, Spaces, and a footer with Team and
  Admin. On mobile, a bottom bar with Home, Chats, Spaces and Team.
- **Home** as the attention page: needs-you (approvals, questions, takeovers, sign-in requests),
  then results, then FYI. It replaces Dashboard, Activity and Workload, and is fed by the event log.
- **Three panes:** rail | main | resizable context pane, in chat-first and object-first layouts.
- DMs and group chats with several agents and humans.
- Inline task, record and approval cards. Approval cards state what will happen, why, and where the
  request came from, and can be answered with one tap on web, mobile and Telegram. Interim approval
  authority until Phase 3 is an open question in the direction (proposed: workspace admins or the
  human who started the conversation, never the requesting agent).
- **Typed, live run activity** in the conversation; built-in apps (Tables, Issues, Docs, Files)
  open in the context pane.
- **Activity** read from the event log, surfaced in Home and in run activity.
- **One concept per job** (structure cleanup; the direction does not phase these individually, so
  they land with the new client): Org becomes Team; agent runs (`Task`) show as runs inside threads
  and the agent profile; the three file stores (`WorkspaceFile`, `DocumentAttachment`,
  `MessageAttachment`) become one Files store; agent system files (identity, memory) move out of
  Docs to the agent profile and the VFS `/agents` mount. Lists → Issues and Dashboard/Workload
  deletion are in the Phase 0 nuclear cleanup.
- **Routines created from chat:** existing Automations migrated to Routines.
- **Rebuilt MCP client** on `laravel/mcp`, with pinned tool definitions that need re-approval when
  they change and server hints treated as hints only.
- **Google Drive storage mount** as the flagship deep connector, into Files and the VFS, writes via
  the VFS write path, effective access = external ACL ∩ OpenCompany grants.
- Telegram stays the first external surface. Slack, X and others are later entry points into the
  same conversations, added only when live-hardened (former P2 item 3).
- Open: whether the web client leaves this repository, Wayfinder versus a generated OpenAPI client,
  the mobile and desktop stack, and whether mobile web gets push before native apps.

- **Artifacts v1** ([artifacts-and-forms.md](planning/artifacts-and-forms.md)): Pages (blocks, live views, charts, diagrams, scheduled
  snapshots) and interactive artifacts (sandboxed HTML UI on the user-content origin with the MCP
  Apps bridge) at private, Space and workspace level with read bindings and no private storage;
  artifact cards and live preview in the context pane; immutable versions at stable URLs; the
  `/artifacts` VFS mount. Spaces gain Artifacts as their sixth tool.
- **`oc` v0** ([oc-cli-and-hosts.md](planning/oc-cli-and-hosts.md)): the agent-first Rust CLI on the public API only — login and
  contexts, Home, approvals, `oc ask … --wait` and a chat TUI, work objects, `oc fs`, runs,
  `--json` and `oc schema`, and **`oc mcp serve`** so Claude Code, Codex, Cursor and others work with
  the workspace using the user's own access. Distributed from the separate Apache-2.0
  `opencompany-cli` repository through a Homebrew tap and install script.
- **Object downloads** in the new client and through `oc export` and the API: Docs as Markdown, PDF
  or DOCX; Tables as CSV, XLSX or JSON; Issues and threads as Markdown or JSON; original Files; agent
  packs ([data-sovereignty.md](planning/data-sovereignty.md)).

Exit criteria: a team can do a full day's work with agents from chat alone, including Drive files.

## Phase 2 — Computer v1

Design: [computer-provider-contract.md](architecture/computer-provider-contract.md) and
[agent-primitives-catalog.md](planning/agent-primitives-catalog.md).

Scope:

- `ComputerProvider` contract (create, start, stop, snapshot, fork, exec, files, browser, screen
  stream, ports), shaped on Bowerbird's Workspace API `v1alpha`, with local Docker (or Firecracker)
  and boat providers. E2B, Daytona and Bowerbird sandboxes follow. Bring-your-own SSH is now part
  of Connected hosts (below).
- Computer access is a per-agent choice; VFS-only is first-class and the least-privilege default.
  One computer per agent by default; a shared team computer only as an explicit opt-in with a
  warning.
- Shell, files, a real browser and the takeover viewer (**take over** / **hand back**). This
  replaces the former P3 item "embedded browser sessions and human takeover" and the
  [embedded Chromium investigation](architecture/embedded-chromium-browser-investigation-2026-05-24.md).
- **VFS projection:** company data reaches the computer only through the VFS (mount, sync or CLI
  bridge) under the same scoping, permissions, write policy, approvals and audit, and changes flow
  back through the VFS write path. The computer is never a bypass. Without a computer, the VFS stays
  a complete workspace. See the
  [VFS agent-friendliness audit](architecture/vfs-agent-friendliness-audit-2026-11.md).
- Secure forms, egress allowlist, idle stop and snapshots.
- **Gmail and agent inbox** (mailbox deep connector feeding agent and shared inboxes).
- **Mail app**, the first add-on app, built on the Gmail and Outlook connections and doubling as the
  agent inbox. Delivered on the app manifest model that Phase 4 generalizes.
- **Signed inbound webhooks** as a generic connection.
- **Script and hybrid routines** (mruby with no LLM, or mruby calling the agent only for judgment).
- **API manifests:** an OpenAPI subset exposed as search-and-execute tools.
- **Skills and plugin import:** SKILL.md skills and Agent Plugins / Claude plugin bundles (skills and
  MCP parts only), digest-bound approval, no automatic install from public registries (former P3
  Skills; [skill-system.md](planning/skill-system.md) is partially superseded).
- Ships with the **money-movement and password-change hard-deny entries** and a **basic compute
  cap**.

- **Artifact server logic:** the mruby `endpoint` profile, write bindings through change sets, the
  AI bridge with quotas, and static bundles built on agents' computers. Endpoint calls are runs and
  events.
- **Forms v1:** `form` as a view on any Table, page mode, internal and public submission through
  one governed pipeline (captcha, rate limits, validation, upload quarantine, change set), and the
  untrusted `form.submitted` event, which like any trigger is never consent.
- **Connected hosts v1** ([oc-cli-and-hosts.md](planning/oc-cli-and-hosts.md)): bring your own machine (Raspberry Pi, home server,
  laptop, VPS) through the outbound-only `oc host` daemon with a container sandbox by default, a
  systemd service, Admin → Hosts with a disconnect kill switch, Raspberry Pi (aarch64) builds, and
  SSH-only hosts over Tailscale or direct addresses (exec, files and computer only). Hosts are
  labelled self-managed, capabilities are granted per agent, secrets are injected per call, host
  tokens are scoped and rotated, and approving a host is `exposure_widening`. Hosted Cloud never
  joins a customer tailnet.
- **`oc artifact dev`** and **`oc code run`**.
- **Workspace archive:** `.ocarchive` export and import (`oc admin export/import`) as restore, clone
  or migration, with a CI round-trip test; secrets excluded by default (an owner may include them
  encrypted with a passphrase, agents never); artifact and form exports; `oc admin backup/restore`
  ([data-sovereignty.md](planning/data-sovereignty.md)).
- **Bowerbird computers (with Bowerbird S1):** a Bowerbird `ComputerProvider` driver on Workspace
  API `v1alpha`, with OpenCompany running the conformance and agent-experience suites, and a
  proposal to add `browser`, `screen` and `desktop_stream` as an optional `v1alpha` capability
  ([bowerbird-synergy.md](planning/bowerbird-synergy.md)).
- **Agent connections v1** ([connections-events-routines.md](planning/connections-events-routines.md)): the provider-neutral `ExternalAgentProvider`
  contract (launch, follow up, stop, status and events into the event log, results as PR, diff,
  files, logs and cost); the ACP runner for coding CLIs on agents' computers and connected hosts,
  using the user's own subscriptions; and the GitHub Copilot coding agent through the GitHub
  connection. **Governance gate:** launching an outside agent is an external action (asks the first
  time per provider and repository, then one-tap "always"; admins can restrict providers and
  repositories; a trigger is never consent; outside-agent events are untrusted). Provider keys stay
  in the vault, injected per call, and reported usage feeds the agent's budget.

Exit criteria: an agent completes a browser-and-shell task on its own computer with takeover and
approvals; VFS-only agents are unaffected.

## Phase 3 — Governance

Design: [governance-model.md](architecture/governance-model.md). Builds on the Phase 0 P0
authorization items and policy model, which must land first.

Scope:

- Human roles and approver policies; no self-approval; an agent never approves anything. Team:
  Owner/Admin/Member roles and each agent's manager as its default approver. Company: approver
  policies with two approvers on high-risk classes, admin-approved allowlists for connections and
  MCP servers, skill approval, a computer-attach policy, and audit export and retention. Custom
  roles and SSO/SCIM come later and are not claimed.
- The full hard-deny floor no approval can unlock: no money transfers between accounts, no
  password/2FA/recovery changes, no credential export, no agent changing its own grants, budget or
  policy (the first two ship in Phase 2).
- Cumulative token, compute and spend budgets per agent and per workspace that pause work at the
  limit, built on the usage ledger and event log (former P2 item 2).
- Run replay and audit from the event log.
- Password manager connectors: 1Password first, then Bitwarden/Vaultwarden.
- Scoped and non-blocking approvals (a pending approval parks only its step).
- Governance area in Admin for Team and Company (policies, exceptions, approvers, budgets, audit);
  Personal keeps approval cards, the per-agent "always allow" list and the slider.
- **Not phased by the direction** (listed here because they belong to pillar 5, not scheduled):
  learned exceptions (offer "always allow" after repeated same-pattern approvals), batched review
  cards, the per-agent autonomy slider (*Ask first · Balanced · Autopilot*, writing only grants,
  capped by preset and floor), trash and purge rules per preset, and the "Why can't Ada see this?"
  decision trace. Space-level permissions (no per-document ACLs; agents join Spaces like
  teammates) arrive with Spaces in Phase 4.
- Heartbeats, monitors and daily briefs as read-only or scheduled routines; triggers are never
  consent; runs that read untrusted content lose automatic approval.
- Save a task as a routine.
- Per-agent least-privilege grants and resource-specific connection grants rather than ambient
  access to whole connected accounts (former P2 item 6).
- **OneDrive/SharePoint** storage mount and **calendars** (Google, Microsoft, CalDAV) deep
  connectors.
- **Calendar & Scheduling app** (add-on, on the calendar connections and the same manifest model):
  calendars, booking links and meeting prep.
- **Artifact publishing:** signed-link and public levels of the publishing ladder (public off per
  workspace by default) with published queries; the published-surfaces registry, abuse controls and
  kill switch; artifact lifecycle sweeps; `exposure_widening` governance.
- **Forms v2:** logic, partial saves, one-question mode, conversational intake, embeds, consent and
  retention.
- **Hosts and `oc`:** the host local MCP bridge (the supported home for stdio MCP servers) and host
  event source, `oc mount` (FUSE), the bwrap sandbox, the `oc` agent-experience eval gate on
  releases, and `oc admin` for self-hosters.
- **Data sovereignty** ([data-sovereignty.md](planning/data-sovereignty.md)): scheduled, incremental backups to customer-owned
  storage, encrypted with a customer-held key, with verification test-restores; the one-way
  continuous Git mirror (Docs as Markdown, Tables as CSV, Issues, artifact source); export
  governance gates (full export automatic in Personal, an admin in Team, an admin plus a second
  approver in Company; always audited; backup and mirror destinations in the `governance` class);
  GDPR export of a member's own data.
- **Bowerbird deep connection** (Phases 3–4, [bowerbird-synergy.md](planning/bowerbird-synergy.md)): CI, run and review events in the event
  log; agent actions to re-run, cancel and approve or reject a deployment; Bowerbird reviews as
  approval cards; usage records and spend-limited agent tokens backing compute budgets. Routine
  graduation to Bowerbird pipelines follows once Bowerbird's automation plane exists; OpenCompany
  keeps the trigger, approvals and context.
- **Agent connections, hosted providers:** Cursor Cloud Agents once its API settles; OpenAI Codex
  cloud, Google Jules and Claude Code cloud tasks where their APIs allow; same contract, governance
  gate and usage-into-budgets as Phase 2.
- **Bowerbird Agents (with Bowerbird S1 or later, not tied to an OpenCompany phase):** managed
  coding agents reached through the same `ExternalAgentProvider` contract and the Bowerbird
  connection, never exclusive ([bowerbird-synergy.md](planning/bowerbird-synergy.md)).

Exit criteria: an admin can answer "who allowed what, at what cost" for any agent action.

## Phase 4 — Work apps and apps platform

Design: [agent-native-work-apps.md](planning/agent-native-work-apps.md) and
[ui-structure-and-agent-editing.md](planning/ui-structure-and-agent-editing.md).

Scope:

- Agent-native Tables (Airtable-like), Issues (Linear-like) and Docs: every record is mentionable,
  assignable to an agent, and emits record-change events that triggers can match.
- **Change sets for every object:** plan, dry-run preview, live staged edits in the agent's color,
  review per cell, row or all, then apply; "revert this run" at any time; human edits always win;
  Docs use collaborative suggestion mode.
- **Spaces and the anti-mess rules:** one flat level of Spaces, each with the six tools (Issues,
  Tables, Docs, Files, Artifacts and Routines); typed docs and columns (Person/Agent, Relation, Attachment, Long-text); embeds link
  canonical objects; human owner, status (draft/current/archived) and review date with a stale
  sweep; governed creation (Spaces, Tables, fields, relations, schema changes and deletes are
  human-approved proposals).
- **App manifest runtime:** add-on apps built only from the core primitives, with no own storage.
  Manifests declare record types, views from a fixed catalog, routine templates, an agent pack,
  required connections, roles and emitted events; app data appears under VFS `/apps/<app>/…`;
  uninstall archives; upgrades migrate the schema. **CRM** is the first schema-only app.
- Mail (Phase 2) and Calendar (Phase 3) are delivered on the same manifest model.
- **Artifacts in the apps platform:** artifacts as custom views and endpoints in app manifests (one
  manifest family), artifact-to-app packaging, and artifact templates.
- **`oc app`**, plugin tooling and **`oc forms`**.
- **Dogfooding:** Bowerbird planning moves from Linear into OpenCompany Issues. The Bowerbird deep
  connection and routine-to-pipeline graduation complete here if not done in Phase 3.
- Finish document publishing and sharing controls on Space-level permissions, with guest sharing of
  a single object only as an explicit exception; access must be enforced (Phase 0 P0 item 3) before
  any public link (former P2 item 1).
- Space-level permissions: private Spaces and personal items, agents joining Spaces like teammates,
  effective access = Space membership ∩ agent grants ∩ on-behalf-of human ∩ external ACL.

Exit criteria: agents run real workflows in Tables and Issues without connections; CRM installs
from a manifest with no app-specific storage.

## After Phase 4 — more apps

Artifacts are no longer an "after Phase 4" item; they span Phases 0–5 as placed above (former P2
item 4). [automation-endpoints-artifacts.md](planning/automation-endpoints-artifacts.md) keeps its
bindings, templates, endpoint catalog and gates; its runtime is superseded by
[artifacts-and-forms.md](planning/artifacts-and-forms.md) and its automation parts by Routines.

- **App catalog and order** (pillar 7). Build an app only where AI teammates add a lot of value and
  the core primitives can express it; **each app ships an agent pack** (for example a "Sales
  teammate"). Mail (Phase 2), Calendar & Scheduling (Phase 3) and CRM (Phase 4) are placed above.
  - **Next after CRM:** Helpdesk (tickets from mail, forms and a chat widget; SLAs, macros; agent
    triage and drafted answers), Requests & Approvals (purchase, time-off, access and expense
    requests with policy-routed approvals), and Knowledge Base (internal wiki plus a public help
    center from Docs; stale-page sweeps).
  - **Later:** Quotes & Invoices (syncs with accounting, never replaces it), Recruiting, Meetings
    (needs recording connectors), Content & Social, Client Portal, Assets & Vendors, Goals/OKRs, and
    People basics.
  - **No app needed** for tasks and projects (Issues), databases (Tables), wiki (Docs), forms and
    surveys (Forms), dashboards, portals and internal tools (Artifacts), or automation (Routines).
  - **Connect, never build:** accounting and bookkeeping (Exact, Moneybird, Xero, QuickBooks);
    payroll, banking and payments (behind connections and the hard-deny floor); e-signature and
    video calls; code hosting and CI (GitHub, Bowerbird); e-commerce, full ERP and BI warehouses.
  - **Beachhead (recommended, owner to confirm):** small service businesses and agencies of 5–50
    people, with a bundle of Mail, Calendar, CRM, Helpdesk, Quotes & Invoices and Client Portal. The
    alternative, research and content operations, would pull Knowledge Base, Meetings and Content &
    Social forward.
- Third-party apps as signed, declarative manifests reviewed before install; their custom views are
  interactive artifacts.
- The [modular platform plan](planning/modular-platform-and-allbase-lab.md) is superseded: its
  module ideas are replaced by add-on apps (pillar 7).

## Phase 5 — Cloud

Design: [opencompany-cloud.md](planning/opencompany-cloud.md).

Scope:

- Hosted OpenCompany Cloud with managed computers on boat (needs a partner agreement), moving to
  Bowerbird sandboxes when they exist. Only Cloud operations and billing are proprietary; self-host
  parity is maintained.
- **OpenCompany as an MCP server**, MCP Apps rendered as messenger cards, and A2A Agent Cards per
  durable agent. This replaces the former P3 "OpenCompany Connect" hosted-MCP item; managed OAuth is
  the generic OAuth broker from Phase 0.
- **Artifacts outward:** custom domains, MCP Apps export of artifacts to Claude and ChatGPT, Git
  export, and payments in forms and artifacts.
- Cloud contexts in `oc`, and Windows host support.
- **Cloud exit guarantees** ([data-sovereignty.md](planning/data-sovereignty.md)): export and backups free on every plan, at least 90
  days read-only and exportable after a lapse, Cloud-to-self-host migration (`oc admin import --from
  cloud`), region choice (EU first), and verified deletion with a receipt.
- **Bowerbird account and bundles (optional, with Bowerbird C4/C5):** one Bowerbird account and
  prepaid wallet for agent and CI compute, partner-credit bundles both ways, and Cloud computers
  defaulting to Bowerbird sandboxes once S1 passes its gate (boat remains a provider). Cloud bills its
  own seats and plans ([bowerbird-synergy.md](planning/bowerbird-synergy.md)).
- Enterprise identity, compliance, residency and managed multi-region cloud (former P3). None of
  these is claimed before it exists.

Exit criteria: paying pilot workspaces on hosted Cloud; self-host parity maintained.

## Unphased runtime work

- **Not phased by the direction (pillar 11):** customer-managed keys (BYOK) on Cloud ("later"), and
  the workspace setting to send nothing to third-party models, local models, and showing the
  provider per run in the run log.

- Dream memory consolidation, temporal decay, and vector-optional writes (former P3). Scheduled when
  memory quality blocks a phase exit.
- Further deep connectors not yet assigned to a phase: Dropbox, Box, S3/R2, WebDAV, SFTP, Outlook
  and IMAP mailboxes, Notion/Confluence import, Git repositories, the MCP-resources mount, and
  optional managed connectors attached as MCP servers (Activepieces sidecar; Composio, Pipedream or
  Zapier with the user's own key; never a hard dependency). Sequencing follows
  [connections-events-routines.md](planning/connections-events-routines.md).

## Superseded or removed

These former items are not on the roadmap. See the supersede table in
[PRODUCT_DIRECTION.md](PRODUCT_DIRECTION.md).

- **Package integrations and the catalog:** deleted in Phase 0; replaced by deep connectors plus
  generic MCP, API manifests and skills
  ([connections-events-routines.md](planning/connections-events-routines.md)).
- **Automations as they exist:** replaced by Routines, Triggers and Events.
- **Visual workflow builder (former P3):** removed. Routines are authored in chat; there is no flow
  builder ([visual-programming-research.md](planning/visual-programming-research.md) is superseded).
- **OpenCompany Connect as a package system (former P3):** superseded by the Connections model, the
  generic OAuth broker and, in Phase 5, OpenCompany as an MCP server.
- **Plugin and device ecosystems (former P3):** replaced by plugin import (skills and MCP parts) in
  Phase 2; no general plugin runtime is planned.
- **Bowerbird owning triggers and durable execution:** superseded by the workload split
  ([bowerbird-synergy.md](planning/bowerbird-synergy.md)). OpenCompany owns business automation; Bowerbird owns software delivery and heavy
  durable pipelines, and heavy script routines can graduate to a Bowerbird pipeline with the same
  Ruby. The shared PHP `integrations` repository plan is obsolete.
- **Agent behaviour modes:** deleted; replaced by the policy model.
- **Reader-first modular platform and Allbase lab:** replaced by add-on apps on the core primitives
  (pillar 7; [modular-platform-and-allbase-lab.md](planning/modular-platform-and-allbase-lab.md)).
- **Dashboard, Activity, Workload, Lists, Org and the Automation page:** replaced by Home, Issues,
  Team and Routines.
- **New Inertia pages and page props:** frozen.

These remain plans until code, tests, operator behavior, and user-facing surfaces exist.

## Product sequence

The sequence is the phase order in [PRODUCT_DIRECTION.md](PRODUCT_DIRECTION.md): clear out the old
systems and lay the Connections, event-log and policy foundations; ship the API-first messenger
with routines and Drive; give agents their own computer and the first mailbox and generic
connections; finish governance; make the work apps agent-native; then offer hosted Cloud.
Self-hosting stays complete at every step, and the product must work fully for agents without a
computer. Public claims follow [CURRENT_STATE.md](CURRENT_STATE.md), not this roadmap.

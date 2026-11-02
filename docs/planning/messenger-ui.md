# Messenger UI (Phase 1)

Status: Design — planned (Phase 1). No implementation claims.
Verified: 2026-11-02 against docs/PRODUCT_DIRECTION.md; current-state statements checked against docs/CURRENT_STATE.md.
Owner doc: [Product Direction](../PRODUCT_DIRECTION.md) (pillar 4, Messenger-first and API-first).
Layout authority: [UI Structure and Agent Editing](ui-structure-and-agent-editing.md) (accepted;
Appendix A sidebar, Appendix B apps) and the mockups
[sidebar](../ui/mockups/sidebar-2026-11.html) and
[primitives plus apps](../ui/mockups/apps-and-primitives-2026-11.html).
Related: [API-first client split](../architecture/api-first-client-split.md) ·
[Connections, Events and Routines](connections-events-routines.md) ·
[Agent Primitives Catalog](agent-primitives-catalog.md) ·
[Agent-native work apps](agent-native-work-apps.md) ·
[Artifacts and Forms](artifacts-and-forms.md) ·
[Governance model](../architecture/governance-model.md) ·
[Computer provider contract](../architecture/computer-provider-contract.md) ·
[OpenCompany Cloud](opencompany-cloud.md) ·
[VFS agent-friendliness audit](../architecture/vfs-agent-friendliness-audit-2026-11.md) ·
[Agent landscape report](../research/2026-11-agent-landscape.md) ·
[Grok Bot investigation](../architecture/grok-bot-opencompany-investigation-2026-08-17.md)

This document is the Phase 1 delivery plan for the messenger: which parts of the accepted UI
structure ship in Phase 1, what they depend on, and how they are accepted. The **layout, rail,
panes, change-set flow and messenger details are owned by the accepted
[UI Structure and Agent Editing](ui-structure-and-agent-editing.md)**; this document links to it
instead of restating it, and adds Phase 1 scope, API/stream contracts, approval authority, routines,
Drive, primitives placement and acceptance. Where the two ever disagree, the accepted UI doc and the
direction win.

It does not own the policy model (pillar 5, [governance model](../architecture/governance-model.md)),
connections/events/routines (pillar 9), the computer contract (pillar 2, Phase 2), primitive
requirements (pillar 6, [catalog](agent-primitives-catalog.md)), or work-app models (pillar 7,
[work apps](agent-native-work-apps.md)).

---

## The rule: a new API-first client (pillar 4)

> **The messenger is a new Vue 3 + Reka UI single-page app with a token-based design system,
> built only on the versioned public API (OpenAPI), the realtime event stream and push.**
> **Inertia is frozen: no new Inertia pages or page props.** Old Inertia pages are deleted as the new
> client reaches parity; they are not ported or polished.

1. Data comes from the versioned public API in
   [API-first client split](../architecture/api-first-client-split.md).
2. Live updates (messages, typed run activity, approvals, presence, object changes) arrive on the
   realtime stream fed from the workspace event log (pillar 9). No polling as a primary path.
3. The workspace is explicit in every request; never inferred from a browser session.
4. Authorization is server-side and identical for every client; hiding a control is never the
   protection.
5. Design system: semantic tokens and wrapper components from day one; dark mode through tokens, not
   hand-paired classes (see section 6 of the UI doc).
6. Open: whether the client lives in this repository (`clients/web`) or its own, and Wayfinder versus
   a generated OpenAPI client.
7. The same API and card/event contracts also serve the **`oc` CLI** (pillar 10): its `ratatui` TUI
   offers chat and Home in a terminal, `oc approvals` answers the same approval cards, and
   `oc mcp serve` exposes the workspace to local coding agents with the user's own access. See
   [`oc` CLI and hosts](oc-cli-and-hosts.md).

---

## Today (grounding)

From [CURRENT_STATE.md](../CURRENT_STATE.md) and the findings in section 1 of the
[UI doc](ui-structure-and-agent-editing.md#1-current-ui-findings):

- Chat at `/w/{workspace}/chat` is a ChatGPT-style assistant shell with two left rails, no sections,
  unread counts, mentions or threads, polled tool steps, and about 4.6k lines of dead legacy chat
  code.
- Approvals render as a queue at the bottom of the conversation and do not show what, why or where
  from; some approval/status events are broadcast on public channels (sent to Phase 0).
- Dashboard is home; navigation has 13 equal top-level destinations.
- Automations are scheduled prompt and script automations; the direction replaces them with
  Routines in Phase 1.
- There is no event log, no Drive mount, no per-agent computer and no takeover viewer.
- Phase 0 authorization gaps (channel membership, DM creation, approver capability) are
  prerequisites, not part of this design.

---

## Phase 1 scope

Following the build order in section 6 of the UI doc, Phase 1 ships step 1 and the parts of steps
2–3 needed for the exit criterion:

| Area | Phase 1 | Later |
|---|---|---|
| Shell | 260px rail (Appendix A), three panes, ⌘K, mobile bottom bar | Spaces with the anti-mess rules (Phase 4) |
| Home | Needs you → results → FYI, from the event log | Daily brief (Phase 3) |
| Chats | DMs, groups, channels, sections, unread, mentions, reply threads | — |
| Run activity | Typed activity, plan checklist, stop/redirect | — |
| Approvals | Reversibility gating, inline cards, first-time-per-target asks, routing per preset | Roles/approver policies, learned exceptions, batched and non-blocking approvals, slider (Phase 3) |
| Context pane | Object (issue, table, doc, file incl. Drive, artifact live preview), run, thread details | Computer (Phase 2); change sets for every object (Phase 4) |
| Artifacts | Artifacts v1: Pages and interactive artifacts at private, Space and workspace level with read bindings; artifact cards; live preview; versions and stable URLs; Make view; Space Artifacts tool | Write bindings and endpoints, forms (2); signed/public publishing and registry (3); app custom views (4); MCP Apps export (5) |
| Routines | Authored in chat; Automations migrated | Save task as routine, heartbeats, monitors (Phase 3) |
| Files | Drive mount in Files and the context pane | Other storage mounts (Phase 3+) |
| Apps section | Rail section present; empty unless apps installed | Mail (2), Calendar (3), CRM (4) |

---

## Shell and navigation

Owned by Appendix A of the UI doc and the [sidebar mockup](../ui/mockups/sidebar-2026-11.html).
Phase 1 implements it as specified:

- **One 260px left rail**, top to bottom: workspace switcher, new chat, search (⌘K); **Home** with a
  "N need you" badge; **Pinned** (per person); **Agents** (opening one starts a DM; status dot,
  current task, unread, 🖥 for agents with a computer); **People**; **Groups and channels**;
  **Apps** (installed add-ons with counts); **Spaces** (flat; each expands to the **six tools**
  Issues, Tables, Docs, Files, Artifacts and Routines, plus scoped app views); footer **Team**,
  **Admin** and the user menu.
- Sections collapse and reorder per user; Spaces the user is not a member of are hidden; the rail
  lists only what the server says the viewer may see.
- Agents without a computer simply have no 🖥 badge. VFS-only is a first-class, least-privilege
  configuration and is never styled as a warning.
- **Mobile:** a bottom bar with Home, Chats, Spaces and Team; cards open full-screen sheets; the
  computer opens full screen.
- Dashboard, Activity and Workload are replaced by Home; Org by Team. Their Inertia pages are deleted
  at parity, not linked from the new client.

### Home (the attention page)

Home is the landing screen. It is **not** called Inbox; "agent inbox" and the Mail app's inbox are
different things (pillar 6, Mail app).

- **Needs you** first: approvals, questions, takeover requests and sign-in requests addressed to the
  viewer, each answerable in place.
- **Results** next: completed runs, routine outputs, handoffs returned.
- **FYI** last: workspace activity rendered from the **event log** (pillar 9), with causality ("started
  by routine X, triggered by Drive change Y"), filterable. This is the Phase 1 "activity feed from the
  event log"; it lives inside Home, not as a separate page.
- The **daily brief** lands on Home when Phase 3 delivers it; an agenda appears when the Calendar app
  exists.
- Only events whose subject the viewer may read are shown; payloads are untrusted data, rendered as
  text.

### Three panes

Owned by section 3 of the UI doc: rail | main | resizable, collapsible context pane.

- **Chat-first:** main = conversation. Context pane = the **object** under discussion (issue, table,
  doc, file including Drive files, or an **artifact with live preview**), the agent's **computer** (watch, take over, hand back; only for
  agents that have one; Phase 2), the **run** (plan checklist, activity, change set), or **thread
  details** (members, routines, files).
- **Object-first:** main = the object; context pane = the **agent panel**, a chat with an agent scoped
  to that object and the current selection.
- Same components in both; opening an object from a card or a chat from an object swaps panes.

---

## Chats

Owned by section 5 of the UI doc. Phase 1 delivers a team messenger, not an assistant:

- DMs with agents and people, group chats mixing agents and people, channels; sections, unread
  counts, mentions and reply threads. Agents carry an "Agent" label and avatar; "Assistant" is gone.
- Agents in groups respond when mentioned, assigned a card, or by explicit group policy; unaddressed
  agents stay quiet.
- Agent-to-agent delegation renders as a handoff card with depth from the causality chain.
- Private context (DMs, private memory, personal connections) is not placed in group chats unless
  deliberately summarized and allowed by server-side audience rules.
- Composer: mentions, record and file references (rendering cards), attachments into the one Files
  store, slash commands, idempotent sends. **Secrets are never typed into chat**; the composer warns
  and offers a secure-form card (pillar 6).

### Typed run activity

Following section 5 of the UI doc: activity types **thought** (collapsed), **action**, **needs input**,
**result**, **error**; a live plan checklist; states working, needs you, done, stale; **Stop** and
**Redirect** on every run, with stopping stating what was already done. Delivered over the stream from
run events. Runs (today's "Tasks") appear inside threads, on Home and on the agent profile, not as a
top-level area. A completed run is not "achieved" unless verified.

### Inline cards

Typed payloads from the API/stream, rendered identically by every client and mirrored by the Telegram
adapter; updated in place.

| Card | Phase |
|---|---|
| Issue / record / doc / file (incl. Drive) — open in context pane, assign, mention | 1 |
| Run summary — outcome, what changed, cost, revert link once change sets exist | 1 |
| Question (needs input) and progress update | 1 |
| Handoff | 1 |
| Outside-agent delegation run (see below) | 2 (ACP runner, Copilot); 3 (Cursor and others) |
| Routine — triggers, mode, next run, last outcome, run now, pause, versions | 1 |
| Approval (see below) | 1 |
| Plan card with dry-run preview, and change-set review | Tables first per build order; every object in Phase 4 |
| Secure form, takeover request, sign-in request, payment | per catalog |
| Artifact — kind (page or interactive), version, bindings, publish level, live thumbnail; opens live preview in the context pane; "Publish" as a governed action | 1 |
| Form — a Table's form view with submission count and publish level | 2 |
| MCP App from an external MCP server, rendered as a card through the same sandbox and bridge | 5 |

---

## Approvals

Pillar 5 (locked 2026-11-02) is the authority: **one policy engine, three presets (Personal, Team,
Company), gated by reversibility, not by "write"**. The card contract is owned by the
[governance model](../architecture/governance-model.md); placement by section 5 of the
[UI doc](ui-structure-and-agent-editing.md). The messenger never decides policy; it renders what the
engine returns.

### What reaches a human at all

Most agent work needs no approval:

- **Reads** within Space access, **external drafts** (email draft, calendar hold) and **reversible
  internal edits** (change sets with "revert this run") **auto-apply** in every preset and show up in
  typed run activity and Home's FYI, each with a revert link. In Team, bulk edits (default over ~50
  rows) and schema changes become **proposals**; in Company, proposals follow Space policy.
- **Deletes** move to a 30-day trash (auto in Personal and Team; a proposal in Company); **purge**
  needs approval (Team) or two approvers (Company).
- What asks a human: **external send, post or submit**, **spend**, **exposure** (share link, open
  port, add an MCP server or egress domain, share a connection), proposals, MCP tool-definition
  changes, skill/plugin digests. Runs that read untrusted content lose automatic approval for
  external actions. A trigger is never consent for external actions.
- Hard-deny actions render as "not allowed", never approvable. **An agent never approves anything.**

### Who answers, per preset

| Preset | Approver | Where cards appear |
|---|---|---|
| Personal | The owner only | The agent's DM, Home, Telegram. No role or policy screens |
| Team | The agent's **manager** by default; admins for exposure and shared connections; members may grant "always" on their own personal connections | Approver's DM with the agent, Home, Telegram |
| Company | **Approver policies**; **two approvers** on high-risk classes (purge, spend above threshold, exposure) | Each approver's Home and DM; the card shows "1 of 2 approved" |

Cards never appear as actionable in group chats; a group run shows "waiting for <approver>". The server
computes eligibility; the client only displays it. Until Phase 3 roles and approver policies exist, the
direction's interim proposal applies: workspace admins or the human who started the conversation, never
the requesting agent (in Personal that is simply the owner).

### Card anatomy

- Inline at the point in the run where it was raised, and mirrored in Needs you on Home: what will
  happen (rendered from arguments, secrets redacted), why, the source and causality chain, an
  untrusted-content flag, the on-behalf-of human, and a run link.
- **One tap** on web, mobile and Telegram; answers go straight to the policy engine via the API, never
  parsed from chat text; resolution is atomic across surfaces.
- **Exception scopes** offered on the card: once, this run, this task, time-limited, always; each
  matches exactly on later calls. Admin-locked "ask first" always wins; who may accept "always" is set
  by preset and admins.

### Few, good approvals

- **First time per target:** the first external send to a recipient or domain asks; the card offers a
  one-tap **"always for this recipient"** / **"always for @domain"** exception. Company: routine-started
  runs may only reach admin-allowlisted targets, so the card says so instead of offering "always".
- **Learned exceptions:** after repeated approvals of the same pattern (default 3), the card offers a
  scoped exact-match "always allow" ("Ada may email @acme.com"). Admins can restrict who may accept.
- **Batched review cards:** "Ada wants to send 12 follow-ups" is one card with a per-item toggle,
  per-item preview, and approve-selected / deny-all.
- **Non-blocking approvals:** a pending approval parks only its step; the run keeps working on other
  steps and the activity shows "parked: waiting for <approver>".
- **Variants:** external send (first-time-per-target), batched send, spend, exposure, proposal
  (bulk edit, schema change, new Space/table/field/relation, trash in Company, purge), **MCP
  tool-definition re-approval** with a diff of the pinned definition (Phase 1, rebuilt MCP client),
  **skill or plugin digest approval** (Phase 2), outbound mail from an agent or shared inbox (Phase 2),
  payment (later).

### Autonomy slider and "why can't Ada see this?"

- **Agent profile → Autonomy:** a per-agent slider **Ask first · Balanced · Autopilot**. Each position
  is a named bundle of scoped exceptions, capped by the preset and the hard-deny floor; moving it only
  writes grants into the policy engine (it is not the deleted behaviour modes). **New agents start at
  Balanced.** The profile lists the agent's "always allow" exceptions with revoke buttons, and the
  activity view suggests promotions from approval history ("Ada's last 10 sends to @acme.com were
  approved — promote?").
- **"Why can't Ada see this?"** (and "why did this need approval?") opens a one-click explainer from
  the policy engine's decision trace: Space membership, agent grants, on-behalf-of human, external ACL,
  preset rule or floor entry that decided, with a direct fix where the viewer may apply one (for
  example "Add Ada to Sales").

### UI follows the preset

- **Personal:** approval cards, the per-agent "always allow" list and the slider. No Governance area,
  role screens or approver settings.
- **Team and Company:** Admin gains a **Governance** area: policies, exceptions (including learned
  ones), approvers, budgets and audit ("who allowed what, at what cost").

### Phasing

The direction's phase table places scoped and non-blocking approvals and human roles/approver policies
in Phase 3. Phase 1 ships the card, routing, first-time-per-target asks and the reversibility gating
that keeps internal edits approval-free; see open questions for which exception options ship in
Phase 1.

---

## Delegation to outside agents

Agent connections (pillar 9, "Agent connections: outside agents as delegates") let a human or a
teammate hand a task to an outside agent that runs in its own environment, through the
provider-neutral `ExternalAgentProvider` contract. The messenger owns how that looks:

- **Starting a delegation.** A human writes "@Cursor fix the flaky test" (outside agents appear as
  mentionable delegates, visually distinct from teammates, with a provider label), or a teammate
  decides to delegate during its own run, which shows as a step in its typed run activity.
- **Delegation run card**, updated live from the event log: provider (for example Copilot coding
  agent, a CLI over ACP such as Claude Code or Codex CLI on a computer or connected host, later Cursor
  Cloud Agents), repository or environment, state (queued, working, needs input, done, failed,
  stopped), the latest provider events, elapsed time and **reported cost**, plus **Follow up** (send
  an instruction to the outside agent) and **Stop**. Stopping says what was already done.
- **Results** arrive on the card as **PR links**, **change sets** (for VFS/company-data edits, with
  "revert this run") or **artifacts**, plus logs. Where the provider's result cannot be reverted by
  OpenCompany (for example a merged PR), the card says so.
- **Approval.** Launching an outside agent sends company context outside, so it is an external
  action: the **first time per provider and repository** asks, through the normal approval routing
  for the preset, with a one-tap **"always for this provider and repo"** exception. A routine can
  never silently delegate (trigger is never consent); routine-started delegation asks per the preset.
- **Admin restrictions.** Providers and repositories an admin has not allowed are shown **greyed out**
  in the mention picker and delegation menu with the reason ("not allowed by your admin"), never hidden
  silently and never launchable.
- **Keys and usage.** Provider keys are the user's own, stored in the vault and injected per call;
  connecting one happens in Admin → Connections or via a secure form, never in chat. Reported usage is
  shown on the card and counted against the delegating agent's **budget** on its profile.
- **Phasing** (direction): Phase 2 adds the ACP runner on computers and connected hosts and the
  Copilot coding agent through the GitHub connection; Phase 3 adds Cursor Cloud Agents once its API
  settles, and Codex, Jules and Claude Code cloud tasks where their APIs allow; Bowerbird Agents
  follow with Bowerbird S1 or later.

## Artifacts in the messenger

Artifacts are a core primitive (pillar 7). Their model, runtime, security and publishing are owned by
[Artifacts and Forms](artifacts-and-forms.md) (see "Where artifacts show up in the UI"); this section
fixes the messenger surfaces.

- **Artifact cards in chat.** When an agent builds or updates a Page or an interactive artifact, the
  conversation shows an artifact card (kind, version, bindings, current publish level). Opening it
  shows a **live preview in the context pane** that refreshes as the agent edits the draft, side by
  side with the chat. Each saved version is immutable at a stable URL; the card links to its versions.
- **Live preview isolation.** Interactive artifacts render in a sandboxed iframe on the separate
  user-content origin (no cookies, default-deny CSP) and talk to the host only through the MCP Apps
  bridge; Pages render as built-in blocks (text, live views, Vega-Lite charts, Mermaid diagrams, KPIs,
  queries, timelines). Messages an artifact sends toward an agent are marked untrusted. If a
  self-hosted workspace has no `ARTIFACT_ORIGIN`, the reduced-isolation fallback is labelled in the
  preview and in Admin.
- **Make view.** Each person has a Make list of finished outputs from their agents (artifacts,
  exported PDFs, forms), separate from chat scrollback, reachable from Home's results and the user
  menu (exact placement within the accepted rail is an open question).
- **Space Artifacts tool.** Each Space's Artifacts tool lists its Pages, interactive artifacts and
  forms with owner, publish level, last viewed and stale status.
- **Publishing ladder in the UI:** draft → Space → workspace → signed link → public. The card and the
  artifact header show the current rung and offer the next one. Moving to signed link or public is an
  `exposure_widening` approval card (approver per preset); public is unavailable until an admin
  enables it for the workspace. Admin shows the **published-surfaces registry** with per-surface kill
  switch, abuse reports and usage. Rungs beyond workspace arrive in Phase 3.
- **Forms** (Phase 2): an agent proposes a form for a Table through plan, preview and approve; the
  form view opens in the context pane; submissions arrive as records via change sets and as untrusted
  `form.submitted` events on Home; internal and Telegram render modes let members fill forms in chat.
- **Typst PDF export** of Pages and Docs lands in Files and shows as a file card.

## Routines in chat

Routines (pillar 9) replace Automations, migrated in Phase 1. There is no visual flow builder.

- Authored in chat ("every Monday at 9…", "when a file lands in the Invoices Drive folder…"): the
  agent drafts a routine card (kind, triggers, mode read-only or act, output target, notify policy,
  quiet hours, budget, approval policy); a human confirms; creating or changing a routine is governed.
- Edited in chat, producing versions. Routine outputs post to their target with a header naming the
  routine and trigger; results also surface on Home.
- Routines show in the thread details pane, on the agent profile and under each Space's Routines tool.
- "Save this task as a routine", heartbeats, monitors and the daily brief arrive in Phase 3.

## Files and Google Drive (Phase 1 flagship)

- Drive appears in the one **Files** store and the **VFS**; effective access is the Drive ACL ∩
  OpenCompany grants. Files keeps folders because it mirrors mounts.
- In chat: file cards; open in the context pane; hand a file or folder to an agent.
- Agent edits go through the VFS write path with revision checks as reversible change sets (auto-apply
  with revert); sharing a Drive file or link outward is an exposure action and asks.
- Drive change events land in the event log and can trigger routines.
- Connecting Drive happens in Admin → Connections (or an inline prompt); secrets go to the vault.

## Computer in the context pane

Phase 2 fills it; Phase 1 reserves the slot.

- Shown **only for agents that have a computer**. VFS-only agents get no empty pane or disabled tabs.
- Terminal, browser, screen; view and control are separate permissions; take over / hand back with one
  controller at a time; the computer never bypasses the VFS.
- VFS-only agents show a non-blocking state on their Team profile only: a recommendation or "request a
  computer" when policy allows; a plain mode statement when an admin deliberately kept the agent
  VFS-only. Never a nag, never blocking.
- Data contract owned by the [computer provider contract](../architecture/computer-provider-contract.md).

## Team and agent profiles

Team replaces Org: a directory of people and agents with manager relations. Agent profiles hold
identity, memory, grants, Spaces it belongs to, autonomy slider and "always allow" list, computer
(mode), routines, budget and runs. Agent identity and memory files
live here and under VFS `/agents`, not in Docs.

---

## Agent primitives in the messenger

Requirements and phasing are owned by the [Agent Primitives Catalog](agent-primitives-catalog.md#phasing)
and the direction's phase table. Placement:

| Primitive | Surface | Phase |
|---|---|---|
| Secure form | Secure-form card in the responsible human's DM/Home; Telegram via Mini App | 2 |
| Vault, password managers, broker | Agent profile → Credentials (names only) | vault/broker 0; password managers 3 |
| Agent inbox, OTPs | Agent profile → Inbox, backed by the Mail app on Gmail/Outlook; OTP use as a redacted run step | 2 |
| Takeover and sign-in requests | Needs you on Home + card opening the computer pane in control mode | 2 |
| Live view | Typed run activity (1) and computer pane view (2) | 1 / 2 |
| Approvals, scoped/non-blocking, hard-deny | Approval card | 1 / 3 / 2–3 |
| Ask vs notify | Question card vs progress update | 1 |
| File handoff | Drop into chat → Files/VFS | 1 |
| Pause, stop, cancel-schedule, delete, kill switch | Run activity, routine cards, agent profile, Admin | stop/redirect 1; full set 3 |
| Daily brief, monitors | Home; routine cards | 3 |
| Memory controls, action items | Agent profile | 3 / per catalog |
| Payments, spend caps | Payment approval card; profile | later / 3 |

---

## Requirements

- R1. New Vue 3 + Reka SPA with a token design system on the versioned API and stream; no Inertia
  pages or props; old pages deleted at parity.
- R2. 260px rail exactly per Appendix A, including Apps and Spaces; mobile bottom bar Home, Chats,
  Spaces, Team.
- R3. Home as the attention page (needs you → results → FYI from the event log); replaces Dashboard,
  Activity and Workload.
- R4. Three panes, chat-first and object-first, with the context-pane contents above.
- R5. Team messenger chats: sections, unread, mentions, reply threads, Agent labels.
- R6. Typed run activity with plan checklist and stop/redirect over the stream.
- R7. Approvals per pillar 5: reversibility gating (internal edits auto-apply with revert), preset-based
  approvers and routing (never group chats), one tap on web/mobile/Telegram, exception scopes,
  first-time-per-target with "always for this recipient/domain", learned exceptions, batched review,
  non-blocking parking, MCP re-approval and digest variants.
- R7a. Autonomy slider (default Balanced) and "always allow" list on the agent profile; promotion
  suggestions; "Why can't Ada see this?" explainer; Governance area in Admin for Team and Company only.
- R8. Routines authored in chat; Automations migrated; no flow builder.
- R9. Drive files in Files and the context pane through Files/VFS.
- R9b. Delegation to outside agents: mentionable delegates, live delegation run card (provider,
  repo/environment, state, cost, follow up, stop), results as PR links, change sets or artifacts with
  revert where possible, first-time-per-provider-and-repo approval with one-tap "always", admin-
  restricted providers greyed out, usage against the agent budget; on the direction's phasing.
- R9a. Artifact cards, live preview in the context pane (sandboxed, separate origin), versions at
  stable URLs, the Make view, the Space Artifacts tool, and the publishing ladder with governed
  publish actions and the Admin published-surfaces registry, on the direction's phasing.
- R10. Computer pane only for computer agents; non-blocking, policy-aware VFS-only state.
- R11. Surfaces for agent primitives on the catalog's phasing.
- R12. Workspace scoping and server-side authorization for every endpoint and subscription; typed,
  documented card and event payloads; idempotent mutations; no hardcoded provider/model IDs.

## Acceptance criteria

- Phase 1 exit: a team does a full day's work with agents from chat alone, including Drive files —
  start from Home, DM an agent from the rail, form a group with a second agent and a colleague,
  assign an issue, watch typed run activity, answer an approval from Home or the DM, open an issue and
  a Drive file in the context pane, have an agent edit a Drive doc (auto-applied, revertible) and send
  a first email to a new recipient (one ask, then "always for this domain"), create a routine
  in chat.
- An approval never appears as an actionable card in a group chat; the requesting agent can never
  answer it; in Phase 1 only admins or the conversation's starting human can.
- A Telegram answer updates the web card through the stream, and vice versa.
- A changed MCP tool definition blocks the tool until re-approved.
- An agent's reversible edit to a table or doc auto-applies, appears in Home's FYI with a revert link,
  and raises no approval; a Team bulk edit over the threshold becomes a proposal.
- In a Personal workspace no Governance area or role screen is shown; in Team and Company it is.
- "Why can't Ada see this?" names the deciding factor (Space membership, grant, on-behalf-of human,
  external ACL or preset rule).
- Home's FYI shows routine runs, approvals and a Drive change with causality and never shows events
  the viewer cannot read (API-level tests, including cross-workspace).
- The rail matches Appendix A; the mobile bottom bar works; dark mode works through tokens.
- An agent builds a Page over a Table in a DM; the card opens a live preview that updates as the agent
  edits; publishing it to the Space is a governed action; the version URL stays stable after edits.
- (Phase 2) "@Copilot fix the flaky test" asks once for that provider and repo, then shows a live
  delegation card ending in a PR link, with cost counted against the agent's budget; a disallowed
  provider appears greyed out.
- An interactive artifact preview cannot read the app's cookies or call tools outside its
  app-visible set.
- No Inertia pages or props are added; Dashboard, Activity and Workload are not reachable from the new
  client.

---

## Lessons from the landscape report

| Source | Lesson | Applied as |
|---|---|---|
| Grok Bot | Legibility; computer panel with takeover; approvals to the responsible human, not groups | Context pane; approval routing |
| Grok Bot | Shared computer is not a boundary; Vault and Secure Forms | Per-agent computers; secure-form cards |
| OpenAI dots | Watching ≠ control; distinct controls; completed run ≠ achieved | Take over / hand back; stop says what was done |
| OpenAI dots | Unviewable memory criticized | Memory on the agent profile |
| Claude Duet | Non-blocking approvals; only you complete a task; trigger ≠ consent | Parked steps; humans own issues; causality on cards |
| Meta Muse | Approvals in client UI, posted straight to the policy service | Structured cards via API |
| Google Chrome agent | Work log with takeover | Typed run activity + computer pane |
| Gemini, Duet, dots | Daily brief; notify only when new | Brief on Home; notify only when needed |
| Linear | Typed agent activities | Run activity types |
| Mattermost Agents | Only the conversation starter approves | Phase 1 authority |
| Cursor (via UI doc) | Approval fatigue | Reversibility gating, learned exceptions, batched cards, slider |

## Open questions

- **Phase 1 exception options:** pillar 5 makes first-time-per-target asks with "always for this
  recipient/domain" the normal flow, while the phase table lists scoped approvals in Phase 3. Proposal:
  ship "once" and the per-target "always" exception in Phase 1 (the Personal-preset minimum), and the
  remaining scopes, learned exceptions, batching and the slider in Phase 3.
- Interim approval authority before Team/Company roles exist (open in the direction).
- Group-chat default responder policy.
- How much run activity non-owners see in channels.
- Takeover on mobile web, or always hand off via a signed link.
- Who may request a computer for a VFS-only agent, and where requests land.
- Push for mobile web before native apps (open in the direction).
- Client repository location and client generator (open in the direction and the UI doc).
- Placement of the Make view within the accepted rail (Home results filter, user menu, or Team
  profile).

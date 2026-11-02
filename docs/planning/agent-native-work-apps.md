# Work Apps and Apps Platform (Phase 4)

Status: Design — planned (Phase 4, "Work apps and apps platform"). No implementation claims. Records the Issues model decision that the direction delegates to this document.
Verified: 2026-11-02 against docs/PRODUCT_DIRECTION.md; current-state statements checked against docs/CURRENT_STATE.md.
Owner doc: [Product Direction](../PRODUCT_DIRECTION.md) (pillar 7, Core primitives, add-on apps, and no mess).
UI authority: [UI Structure and Agent Editing](ui-structure-and-agent-editing.md) (accepted; sections 2
and 4, Appendix B) and the [primitives plus apps mockup](../ui/mockups/apps-and-primitives-2026-11.html).
Related: [Messenger UI](messenger-ui.md) ·
[Artifacts and Forms](artifacts-and-forms.md) ·
[Connections, Events and Routines](connections-events-routines.md) ·
[API-first client split](../architecture/api-first-client-split.md) ·
[Agent Primitives Catalog](agent-primitives-catalog.md) ·
[Governance model](../architecture/governance-model.md) ·
[Computer provider contract](../architecture/computer-provider-contract.md) ·
[OpenCompany Cloud](opencompany-cloud.md) ·
[VFS runtime reference](../tools/vfs.md) ·
[VFS agent-friendliness audit](../architecture/vfs-agent-friendliness-audit-2026-11.md) ·
[mruby-only Code Mode plan](mruby-only-code-mode-implementation.md)

This document plans Phase 4: making **Tables**, **Issues** and **Docs** agent-native, introducing
**Spaces** and the anti-mess rules, **change sets for every object**, and the **app manifest runtime**
with CRM as the first schema-only app. It records which of today's models becomes Issues. The
information architecture, anti-mess rules and change-set UX are owned by the accepted
[UI doc](ui-structure-and-agent-editing.md); this document links to them and adds data-model
decisions, agent access (VFS, mruby, tools, computer), events, routines, the app manifest runtime,
requirements and acceptance. Where they disagree, the accepted UI doc and the direction win.

It does not own the event log, triggers and routines (pillar 9), policy and approvals (pillar 5), the
computer transport (pillar 2) or API conventions (pillar 4).

---

## Today (grounding)

From [CURRENT_STATE.md](../CURRENT_STATE.md) and section 1 of the
[UI doc](ui-structure-and-agent-editing.md#1-current-ui-findings):

- **Tables:** columns, rows, saved views; eight column types, no person/agent, relation, attachment
  or long-text; no virtualization, row detail, history or `updated_by`; agent VFS edits appear only
  after a manual reload.
- **Tasks:** `Task` + `AgentRunRecord` are agent execution records (steps, sources, lifecycle,
  analytics).
- **Lists:** `ListItem`s with `ListStatus`es, templates, comments, collaborators and
  `ListAutomationRule`s (the rule CRUD has a recorded workspace-scoping gap).
- **Docs:** TipTap without collaboration; autosave without version checks can silently overwrite an
  agent edit; unlimited nesting; agent identity and memory files appear in the Docs tree; document
  permissions not consistently enforced.
- **Files:** three stores (`WorkspaceFile`, `DocumentAttachment`, `MessageAttachment`).
- **Calendar**, **Dashboard**, **Activity**, **Workload**, **Org** exist as pages.
- **VFS:** permission-aware projections with keyset pagination, budgets, guarded mutations, approval
  replay and operation events.
- **Code Mode:** the direction makes mruby the only runtime; CURRENT_STATE (verified 2026-08-09) still
  describes Lua, and the migration is tracked in
  [mruby-implementation-progress.md](mruby-implementation-progress.md). Assumed as target, not shipped.
- No Spaces, change sets, app manifests, record assignment to agents, or workspace event log exist.

---

## Fixed core primitives

Per pillar 7: **Threads, Issues, Tables and Records, Docs, Files, Artifacts, Routines, Agents and
People**, always on. No generic page, no user-defined object kinds, Docs are not containers. Issues is
core, not an add-on. **Artifacts** are the only code-bearing primitive; their model is owned by
[Artifacts and Forms](artifacts-and-forms.md) and summarized under "Artifacts and forms in the work
apps" below.

### Decision: Issues are built from the list model

The direction leaves "which of today's `Task` and list models becomes Issues" to this document. This
is consistent with the accepted "one concept per job" table (Lists → Issues; Tasks → runs).

**Decision (proposed for owner confirmation): Issues are built from the list model; `Task` remains
the agent execution record.**

- `ListItem` → Issue; `ListStatus` → issue workflow states; `ListItemComment` → issue comments;
  `ListItemCollaborator` → assignee/delegate/watchers; `ListTemplate` → issue templates.
- `Task` / `AgentRunRecord` stay the **run**: shown inside threads, on Home and on the agent profile,
  no longer a top-level area. A run references the issue (or other object) it serves; one issue can
  have many runs.
- An agent works as the issue's **delegate** while a human stays the **owner** (UI doc section 4,
  Linear pattern). Agent run state (working, needs you, done, stale) shows beside the human-owned
  workflow state; only a human, or a policy explicitly allowing it, closes an issue.
- `ListAutomationRule` is deleted; record-change events plus triggers and routines replace it.
- Migrations are squashed and there are no users, so this is a schema reshape, not a compatibility
  layer. Internal class naming is an implementation choice; the product name is **Issues**.

Reason: list items already have the human work-item shape; `Task` is bound to the run control plane.
Keeping them separate keeps human-owned status apart from machine-owned run state.

### One concept per job

Owned by the direction and UI doc; recorded here for the work apps:

| Today | Becomes |
|---|---|
| Lists | Issues (decision above) |
| Tasks (agent runs) | Runs inside threads, Home and the agent profile |
| Automation (and list rules, agent schedules) | Routines |
| Dashboard, Activity, Workload | Home |
| Org | Team |
| Three file stores | One Files store; attachments are Files |
| Calendar page | The Calendar add-on app over calendar connections (Phase 3), plus an agenda on Home |

---

## Spaces and the anti-mess rules

Owned by section 2 of the UI doc; Phase 4 implements them:

- **One level of containers:** Workspace → **Spaces** (flat, never nested) → objects. Every object
  belongs to exactly one Space or to the workspace. Every Space has the same **six tools**: **Issues,
  Tables, Docs, Files, Artifacts, Routines**, plus scoped views of installed apps (for example Deals in Sales).
  Only Files keeps folders. Docs are flat within a Space, organized by typed properties and
  collections.
- **Typed everything:** doc template types (Spec, Policy, Meeting notes, Wiki, …) with properties;
  new table columns **Person/Agent**, **Relation** (to records, issues and docs), **Attachment**
  (Files) and **Long-text**; formula later. Embeds link to the canonical object, never copy it.
- **Owner, status, review:** every Doc, Table and Issue has a human owner and status (draft, current,
  archived); Docs have a review date. A **stale sweep** (agents may propose it, as a routine) flags
  expired items. Archived items leave search and agent context and redirect to their replacement.
- **Governed creation:** within policy, agents create records, issues and doc drafts. Creating
  Spaces, Tables, fields or relations and changing a schema are proposals a human approves (approval
  card variant in the [messenger](messenger-ui.md#approvals); in Personal the owner approves with one
  tap). **Deleting** follows pillar 5's reversibility rule: delete moves the object to a **30-day
  trash** (auto-applies in Personal and Team, a proposal in Company); **purge** (permanent deletion)
  needs approval in Team and two approvers in Company. Pillar 7's "deleting anything is always a
  proposal" is read here as permanent deletion; see open questions.
- **System files are not content:** agent identity, instructions and memory live on the agent profile
  and under VFS `/agents`, and are removed from the Docs tree.
- **Permissions are Space-level only** (pillar 5): Spaces are open or **private**; people can keep
  **personal items** outside shared Spaces; a single object may be shared with a **guest** only as an
  explicit, audited exception. There are **no per-document ACLs**. Spaces the user cannot see are hidden
  in the rail, search, VFS, Home and events. Every query stays workspace-scoped, and Space membership
  narrows it.
- **Agents join Spaces like teammates.** "Add Ada to Sales" is the whole data-access model for
  company data; connections and computers are granted per agent on top.
- **Effective access** for an agent = Space membership ∩ the agent's grants ∩ the **on-behalf-of**
  human (DM requester, routine owner, or least-privileged invoking human in a group chat) ∩ the
  external ACL for deep-connector data. "Why can't Ada see this?" is answered from the policy
  engine's decision trace (see the [messenger](messenger-ui.md#approvals)).
- Today's stored `DocumentPermission` rows (inconsistently enforced per CURRENT_STATE) are replaced by
  Space membership, not fixed into per-document ACLs; the Phase 0 security fix must still close the
  current leak until then.

---

## Change sets for every object

Owned by section 4 of the UI doc. Every agent edit to structured data, through the VFS, API or mruby,
produces a **change set**: items with old/new values per cell, field or text range, the producing run,
status and policy decision.

- **Flow:** plan (scope, cost estimate) → **dry-run preview** of sample changes → **live staged edits**
  in the agent's color with a presence chip → **review** per cell, row or all ("changed only" filter)
  → **apply** with person-or-agent attribution and a run link in record history.
- **"Revert this run"** undoes the whole run at any time, as long as affected cells have not changed
  since.
- **Human edits win:** an agent never overwrites a value a human changed after the agent read it;
  conflicts surface in review, never last-write-wins.
- **Gating by reversibility** (pillar 5): change sets are reversible, so **small edits auto-apply**
  as one revertible batch, visible in run activity and Home with "revert this run". **Review is for
  proposals**: bulk edits (Team default over ~50 rows), schema changes, and whatever a Company Space's
  policy marks as a proposal. Thresholds are per preset, then per Space and per agent (autonomy
  slider), capped by the floor. Plan and dry-run steps apply when the change set is a proposal or when
  the agent chooses to plan bulk work; auto-applied edits still show staged highlights while streaming.
- **Deletes** inside a change set move items to the 30-day trash and are revertible like other edits;
  purge is a separate approval (Team) or two-approver (Company) action.
- **On-behalf-of:** a change set never exceeds the on-behalf-of human's access; edits outside it are
  rejected with a decision-trace reason, not queued for approval.
- **Issues** use change sets for field changes. **Docs** use collaborative editing with the agent in
  **suggestion mode** (tracked changes, accept or reject), one canonical structured format with a
  markdown projection for the VFS, and version checks on every save.
- **Prerequisites:** realtime for every object from the event log, agent presence, record history with
  actor and run, a virtualized grid with server paging, a row detail pane, the new column types, and a
  versioned change-set API.
- Build order (UI doc section 6): change sets for Tables come first (with the context pane); Issues and
  Docs suggestion mode next; Phase 4 completes change sets for every object.

---

## Agent-native capabilities

- **Mentionable:** every record has a stable, workspace-scoped reference used for mentions, cards and
  event subjects; unreadable records render as "inaccessible record".
- **Assignable to an agent:** assignment records instructions, acceptance criteria and mode
  (read-only or act), emits an event, and starts or queues a run with the causality chain. The agent
  acts within its own grants; edits arrive as change sets.
- **Record changes emit events** (for example `issue.state_changed`, `table.row.updated`,
  `doc.version.created`) into the **one workspace event log**, with subject, actor, idempotency key and
  causality. **Triggers** match them and start **routines**; there are no per-app rule engines.
- **A trigger is never consent;** gated actions still raise approvals naming the triggering event.
  Content from connections, webhooks, inbound messages or non-members is untrusted and taints runs.
- **Loop protection** via causality depth, debounce and coalescing (pillar 9).
- Phasing note: the [pillar 9 phasing](connections-events-routines.md#phasing) lists record-change
  events in Phase 2, while the direction lists record-change events and triggers in Phase 4. This
  document treats emission as available once the log carries it, and the agent-native trigger and
  assignment experience as Phase 4.

### Routines, monitors and briefs over records

Routines are pillar 9; heartbeats, monitors, briefs and action items are also primitives (pillar 6).
Bound to the work apps:

- Routines target a Space, table, view, issue project or doc collection; authored in chat or saved from
  a task (Phase 3); never in a flow builder; listed under each Space's Routines tool.
- Script and hybrid routines (mruby, Phase 2) do deterministic record plumbing and call the agent only
  for judgment (for example Drive file intake → table row).
- Monitors are event-triggered **read-only** routines; heartbeats are scheduled read-only routines that
  notify only if new; both propose changes (a change set awaiting review, a draft issue) rather than
  apply them.
- The daily brief summarizes changes the recipient may read and lands on Home (Phase 3).
- The stale sweep is a built-in scheduled routine over owner/status/review data.

---

## Add-on apps platform

Owned by pillar 7 and Appendix B of the UI doc. Phase 4 delivers the **app manifest runtime**, with
**CRM** as the first schema-only app; Mail (Phase 2) and Calendar (Phase 3) are delivered on the same
manifest model.

- **Apps never bring their own storage.** They are built only from the core primitives.
- **A manifest declares:** record types (schema-locked tables with relations); views from a fixed
  catalog (list, board/pipeline, table, calendar, mail thread, record detail, timeline, **form**);
  **artifacts** acting as the app's custom views and endpoints; routine templates; an agent pack;
  required connections; roles; emitted events; and **`approval_rules`**, the field-level business
  rules, for example `{ when: "discount > 20", applies_to: [human, agent], approver:
  role:sales-lead }`. Tables can declare the same rules in table settings. They apply to humans
  and agents alike, run on the policy engine (cards, audit, decision trace) and only add gates.
  See [governance model](../architecture/governance-model.md#field-level-approval-rules-business-rules)
  and story 14 in [example flows](example-flows.md).
- **One manifest family:** apps and artifacts share it (see the manifest section of
  [Artifacts and Forms](artifacts-and-forms.md)). An artifact can graduate into an app
  (artifact-to-app packaging and templates, Phase 4).
- **Uniform behavior:** agents, search, approvals, change sets, revert and the event log work on app
  data automatically, because it is ordinary records.
- **VFS:** app data appears under **`/apps/<app>/…`**, governed like `/tables`.
- **Placement:** apps live at workspace level (rail section **Apps**) and show scoped views inside
  Spaces.
- **Lifecycle:** install is a governed action (approval); **uninstall archives** the data; **upgrades
  migrate the schema** through the same governed schema-change path.
- **First-party catalog and order:** see "App catalog and order" below (replaces the earlier short
  list Mail → Calendar → CRM → Helpdesk, Recruiting, Invoices).
- **Third-party apps** only as **signed, declarative manifests reviewed before install**; their custom
  views are interactive artifacts in the user-content sandbox. No third-party code runs in-process.
- Mail and Calendar may ship before the full runtime; they must use the manifest model from the start
  so Phase 4 does not need to migrate them.

---

### App catalog and order

Locked in pillar 7 of the [direction](../PRODUCT_DIRECTION.md) ("App catalog and order",
2026-11-02); recorded here so the platform design matches it.

- **Rule:** build an app only where AI teammates add a lot of value (repetitive, text-heavy or
  inbound-driven work) **and** where the core primitives can express it.
- **Each app ships an agent pack**, for example a "Sales teammate" or a "Support teammate". **The
  agent pack is the product; the app is where its work lives.** A manifest without a useful agent pack
  does not ship.
- **No app is needed for:** tasks and projects (Issues), databases (Tables), wiki (Docs), forms and
  surveys (Forms), dashboards, reports, portals and internal tools (Artifacts), or automation
  (Routines).

| # | App | Scope | When |
|---|---|---|---|
| 1 | **Mail** | Shared and personal inboxes, threads, drafts; doubles as the agent inbox | Phase 2 |
| 2 | **Calendar & Scheduling** | Calendars, booking links, meeting prep | Phase 3 |
| 3 | **CRM** | Companies, contacts, deals, pipeline, activity, enrichment, follow-ups | Phase 4 |
| 4 | **Helpdesk** | Tickets from mail, forms and a chat widget; SLAs, macros; agent triage and drafted answers | Next after CRM |
| 5 | **Requests & Approvals** | Purchase, time-off, access and expense requests, with policy-routed approvals and audit | Next |
| 6 | **Knowledge Base** | Internal wiki plus a public help center published from Docs; stale-page sweeps | Next |
| 7 | **Quotes & Invoices** | Quotes, invoices, payment links, reminders; **syncs with accounting, does not replace it** | Later |
| 8 | **Recruiting** | Job posts (public forms), candidates, pipeline, interviews | Later |
| 9 | **Meetings** | Notes, transcripts, decisions; action items become Issues, CRM updates and follow-ups | Later (needs recording connectors) |
| 10 | **Content & Social** | Content calendar, drafts, publishing behind approvals | Later |
| 11 | **Client Portal** | A per-client space with shared files, status and requests (artifacts plus sharing) | Later |
| 12 | **Assets & Vendors** | Equipment and software register, subscriptions, contracts, renewals | Later |
| 13 | **Goals / OKRs** | Goals linked to Issues and metrics, check-ins | Later |
| 14 | **People basics** | Directory, onboarding and offboarding checklists, time off | Later |

- **Connect, never build:**
  - accounting and bookkeeping (Exact, Moneybird, Xero, QuickBooks);
  - payroll, banking and payments (behind connections and the hard-deny floor);
  - e-signature and video calls;
  - code hosting and CI (GitHub, Bowerbird);
  - e-commerce, full ERP and BI warehouses.
- **Beachhead — recommended, owner to confirm:** small service businesses and agencies of 5–50
  people. Their bundle is **Mail, Calendar & Scheduling, CRM, Helpdesk, Quotes & Invoices and Client
  Portal**. The alternative is research and content operations (the earlier pilot idea), which would
  pull **Knowledge Base, Meetings and Content & Social** forward.
- Platform implications: every app above is a manifest over the primitives (no app storage); apps that
  send or publish (Mail, Helpdesk answers, Content & Social, Quotes & Invoices reminders) follow the
  pillar 5 external-send rules; Quotes & Invoices and People basics touch money and HR data, so they
  rely on connections and the hard-deny floor rather than holding payment or payroll capability.

## Artifacts and forms in the work apps

Owned by [Artifacts and Forms](artifacts-and-forms.md); phasing per the direction's table (Artifacts
v1 in Phase 1, endpoints and Forms v1 in Phase 2, public publishing and Forms v2 in Phase 3, app custom
views and packaging in Phase 4). What matters for Tables, Issues and Docs:

- **Bindings, not storage.** Artifacts read and write Tables and other primitives through named
  bindings; an artifact that needs state gets an **artifact-owned Table** that is visible, governed and
  listed in its Space. Artifact writes go through the same change sets (reversible edits auto-apply
  per preset; proposals per Space policy) and land in the event log.
- **Effective access** for a workspace member viewing an artifact = the artifact's bindings ∩ the
  viewer's own access (on-behalf-of); external visitors only get share-safe endpoints, approved
  read-only **published queries** and form submission.
- **Pages** are block reports over live Table and Issue views (charts, KPIs, queries, diagrams,
  timelines), live or snapshotted on a schedule. The deleted renderer packages are replaced by
  built-in **chart and diagram blocks** (Vega-Lite, Mermaid, client-side) and **Typst PDF export into
  Files**, with normal Files permissions.
- **Forms are a view on any Table** (`form` is in the view catalog), not an app or artifact type.
  - One governed **submission pipeline**: public origin without cookies, captcha (ALTCHA default),
    honeypot and rate limits, schema/logic/consent validation, upload quarantine, a record created as
    a change set by the **form-visitor principal**, and an untrusted `form.submitted` event that can
    start routines (which then lack automatic approval for external actions).
  - **Render modes** from one definition: page, one question at a time, conversational agent intake
    (its only tool is submit), embed, and internal or Telegram forms.
  - Custom form UIs in artifacts must submit through the Table's form endpoint.
  - Agents generate forms through plan, preview and approve; retention, auto-delete, export and
    erasure apply to submissions.
- **Lifecycle** matches the anti-mess rules: owner, last-viewed tracking and a stale sweep for
  artifacts; immutable published versions at stable URLs; remix only inside the workspace.

## How agents work with the apps

Computer access is a per-agent choice (pillar 2); VFS-only is first-class and least-privilege;
switching modes keeps identity, memory and VFS access. The VFS is the governed path in both modes
(pillar 3).

### VFS-only agents

- **Read:** `/tables/…` rows (NDJSON and per-row JSON), issue projections (replacing `/lists`),
  `/docs` (markdown projection of the canonical format), `/files` (including Drive mounts),
  `/artifacts` (sources, manifests, versions), `/apps/<app>/…`, with keyset pagination and budgets. Archived objects are excluded from default
  listings and agent context.
- **Write:** `vfs_patch` / `vfs_write` produce **change sets**, not direct mutations; small reversible
  edits auto-apply with revert, bulk and schema-level writes become proposals per preset and Space
  policy, `rm` moves to trash; every write lands in the event log.
- **Capability truth:** `vfs_stat`/`vfs_read` metadata says whether a path is patchable, denied,
  approval-gated or proposal-only. Remaining gaps are in the
  [VFS agent-friendliness audit](../architecture/vfs-agent-friendliness-audit-2026-11.md).
- **mruby Code Mode** for bulk work; its writes form one change set per run, revertible as a unit.
- **Tools** for precise single actions; approvals and grants key on them.
- Connections are available but not needed for the Phase 4 exit.

### Agents with a computer

- The VFS is projected into the computer (mount, sync or CLI bridge; contract owned by the computer
  doc). App data reaches the computer only that way.
- Writes inside the computer return through the VFS write path as change sets with base versions;
  human edits win; stale writes become reviewable conflicts.
- **The computer is never a bypass:** no database credentials, API secrets or broader-than-agent
  tokens; a CLI bridge uses a run-scoped brokered credential.

---

## Requirements

- W1. Fixed core primitives; no generic page or user-defined object kinds.
- W2. Issues built from the list model; `Task` stays the run; Lists and `ListAutomationRule` removed;
  agent as delegate, human as owner.
- W3. Flat Spaces with the six tools (Issues, Tables, Docs, Files, Artifacts, Routines) and scoped
  app views; membership-scoped everywhere.
- W4. Typed docs with template properties; Person/Agent, Relation, Attachment and Long-text columns;
  link-not-copy embeds.
- W5. Owner, status and review date; stale sweep routine; archived items leave search and agent
  context and redirect.
- W6. Governed creation: Spaces, tables, fields, relations and schema changes are proposals; delete
  goes to a 30-day trash per preset; purge needs approval (Team) or two approvers (Company).
- W6a. Space-level permissions only: private Spaces, personal items, guest single-object sharing as an
  exception, no per-doc ACLs; agents join Spaces as members; effective access = Space membership ∩
  agent grants ∩ on-behalf-of human ∩ external ACL; decision-trace explainer.
- W7. Agent system files moved out of Docs to the agent profile and `/agents`.
- W8. Change sets for every object: small reversible edits auto-apply with "revert this run"; review
  (plan, dry-run, staged edits, per cell/row/all) for proposals; human edits win; Docs suggestion mode
  with version checks.
- W9. Mentions, assignment and record-change events into the workspace event log; triggers start
  routines; trigger never consent; taint; loop protection.
- W10. App manifest runtime: schema-locked record types, fixed view catalog, routine templates, agent
  pack, required connections, roles, events; `/apps/<app>/` in the VFS; install governed, uninstall
  archives, upgrades migrate.
- W11. CRM as the first schema-only app; Mail and Calendar & Scheduling on the same manifest model;
  the 14-app catalog order from pillar 7; every app ships an agent pack; "connect, never build"
  categories stay connections.
- W12. One Files store; Typst PDF exports and chart/diagram outputs land in Files with permissions.
- W12a. Form view on any Table with the single governed submission pipeline and the render modes;
  `form` in the view catalog; artifacts as apps' custom views in one manifest family; artifact-owned
  Tables instead of private storage.
- W13. Workspace scoping on every query (`forWorkspace()`, relation-scoped `whereHas`); Phase 0
  document-permission fixes first; no hardcoded provider/model IDs.

## Acceptance criteria

- **Phase 4 exit (direction):** agents run real workflows in Tables and Issues without connections,
  and **CRM installs from a manifest with no app-specific storage**.
- Example workflow (Team preset): Ada is added to the Sales Space; a new Leads row emits an event; a
  read-only monitor proposes scores; Ada's small edit to that row auto-applies and shows on Home with a
  revert link; her later re-score of 200 rows becomes a proposal that a human reviews per row and
  applies; a concurrent human edit to one cell survives; an assigned agent (delegate) files a follow-up
  issue owned by a human; a later run is undone with "Revert this run". Home shows the causality chain.
- The same workflow works for a VFS-only agent and a computer agent; the computer agent's edits appear
  as VFS-originated change sets with the same approvals and events.
- An agent cannot create a Space, table, field or relation or change a schema without an approved
  proposal; its deletes land in trash and are restorable for 30 days; purge requires the preset's
  approvals.
- An agent in a group chat cannot read a record that the least-privileged invoking human cannot read,
  even if the agent itself is a Space member; "Why can't Ada see this?" names that reason.
- No per-document ACL exists; access changes only through Space membership, personal items or a guest
  share exception.
- A Leads Table gets a form view; a public submission (once public publishing is enabled) passes the
  pipeline, creates a record as a form-visitor change set, and emits an untrusted `form.submitted`
  event that starts a routine without automatic approval for external actions.
- CRM ships at least one custom view as an interactive artifact declared in its manifest.
- CRM's Deals appear under `/apps/crm/…` and as a scoped view in the Sales Space; uninstalling CRM
  archives its records.
- A read-only monitor cannot mutate any record (enforced at tool registration, tested).
- A user cannot learn a record's title through a mention, card, context pane, VFS path, Home or
  notification without read access, including across Spaces and workspaces.
- Agent identity and memory files are absent from Docs; Lists, Dashboard, Activity and Workload no
  longer exist as surfaces.

---

## Lessons from the landscape report

| Source | Lesson | Applied as |
|---|---|---|
| Multica, Plane, Linear | Agents take issue assignments; agent as delegate; typed activities | Assignment; delegate/owner split |
| Claude Duet | Only you complete a task; trigger ≠ consent | Humans close issues; triggers never consent |
| OpenAI dots | Completed run ≠ achieved; read-only background roles | Review before apply; read-only monitors |
| Meta Muse | Taint removes auto-allow | Untrusted record sources taint runs |
| Manus, Lindy | File system as working memory | VFS projection of every object and app |
| Grok Bot | Shared computer, shared logins | Computer never bypasses the VFS |
| Paperclip | Budgets pause work | Routine budgets (Phase 3) |
| Notion (via UI doc) | "Everything is a page" becomes a mess | Fixed primitives, flat Spaces, typed objects |
| Airtable, Excel, Gemini Sheets, Hex (via UI doc) | Plan mode, staged edits, per-cell review, human edits win | Change sets |

## Open questions

- Owner confirmation of the Issues decision above (direction says it is decided here).
- Do table rows, issues and app records share one record core (fields, comments, history), or only a
  shared reference/event/change-set layer? (App record types as "schema-locked tables" suggests the
  table core.)
- What the Tasks page becomes once runs live in threads, Home and profiles (deleted at parity, or
  folded into the agent profile).
- Change-set auto-apply thresholds per Space (open in the UI doc).
- Docs collaboration engine and canonical format (open in the UI doc).
- Mount vs sync vs CLI bridge as the default VFS projection into a computer, and the synced-write
  conflict model.
- Pillar 7 says "deleting anything" is always a proposal; pillar 5 lets trash auto-apply in Personal
  and Team. This doc treats trash as reversible and purge as the governed delete; owner confirmation
  needed. Also whether schema changes in Personal are proposals (pillar 7) or auto-apply (pillar 5's
  Personal column).
- Beachhead confirmation (service businesses and agencies vs research and content operations),
  which decides whether Knowledge Base, Meetings and Content & Social move ahead of Helpdesk.
- How app upgrades handle data that no longer fits a migrated schema (archive, quarantine, or block
  the upgrade).

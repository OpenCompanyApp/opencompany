# Governance Model

Status: Design — planned (Phases 0–4). No implementation claims beyond those marked "exists today".
Verified: 2026-11-02 against docs/PRODUCT_DIRECTION.md (pillar 5 as locked in commit 5127548); current-state statements checked against docs/CURRENT_STATE.md and docs/ROADMAP.md.
Owner doc: [Product Direction](../PRODUCT_DIRECTION.md).

Company governance is OpenCompany's moat
([pillar 5](../PRODUCT_DIRECTION.md#5-company-governance-is-the-moat-one-engine-three-presets-gated-by-reversibility)).
It must feel like Grok Bot for a solo user and scale to large or regulated teams. The design is **one
policy engine with three presets on top**, gating actions **by reversibility, not by "write"**. This
document is the full model behind pillar 5: who may do what, who may allow what, what nobody may
allow, what it may cost, and how every answer is recorded. Phase 3 exit: *an admin can answer "who
allowed what, at what cost" for any agent action.*

Related designs:

- [Connections, Events and Routines](../planning/connections-events-routines.md) (pillar 9): the
  workspace event log, triggers, routines, connections and the OAuth broker this model governs.
- [Agent Primitives Catalog](../planning/agent-primitives-catalog.md) (pillar 6): vault, secure forms,
  password manager connectors, approval cards and exceptions, the floor, controls and kill switch,
  spend caps, agent inbox and memory controls. This document defines the rules those primitives obey.
- [UI structure and agent editing](../planning/ui-structure-and-agent-editing.md) (pillars 4 and 7):
  change sets, governed creation, Spaces, owner/status/review, Home and add-on app manifests.
- [Artifacts and Forms](../planning/artifacts-and-forms.md) (pillar 7): the publishing ladder,
  visitor identities, published queries, mruby endpoints, the AI bridge and the form submission
  pipeline governed here.
- [Computer Provider Contract](computer-provider-contract.md) (pillar 2): computer actions, takeover,
  egress and per-agent computer attachment.
- [Messenger UI](../planning/messenger-ui.md) and [API-first client split](api-first-client-split.md)
  (pillar 4): approval cards, budget pauses, takeovers and Home are API resources and realtime events.
- [Agent-native work apps](../planning/agent-native-work-apps.md): record-change events, which are
  triggers and never consent.
- [Agent Run Queue Control Plane](agent-run-queue-control-plane.md): the run records every chat and
  routine run executes on.
- [VFS Runtime Reference](../tools/vfs.md) and the
  [VFS agent-friendliness audit (2026-11)](vfs-agent-friendliness-audit-2026-11.md) (pillar 3).

## Principles

1. **One authority outside the model.** A server-side policy engine decides allow, deny, ask or
   propose for every tool call and at every step of connection → event → trigger → routine → run.
   The model proposes; it never grants. Approval answers go straight to the engine from structured UI
   (card, Telegram button or Mini App), never parsed from chat text.
2. **Gate by reversibility.** Reversible actions apply automatically and stay revertible; irreversible
   and outward-facing actions ask. Design rule: make actions reversible so they need no approval
   (archive and trash instead of hard delete, drafts before sends, every structured edit a change set).
3. **One engine, three presets.** Presets (Personal, Team, Company) supply defaults only. Invariants
   and explicit grants always win over preset defaults.
4. **Invariants are never switchable,** in any preset, including self-hosted.
5. **An agent never exceeds the human it acts for** (on-behalf-of access).
6. **Few, good approvals.** Learned exceptions, batched review and non-blocking approvals keep approval
   count low without weakening the floor.
7. **Simple permissions.** Space-level access, no per-document ACLs.
8. **Everything leaves a receipt** in the one workspace event log.
9. **Security is never paywalled.** All presets and all governance features ship in the AGPL
   self-hosted product.

Industry evidence is in the
[agent landscape report](../research/2026-11-agent-landscape.md#converged-design-patterns-across-all-vendors).

## Always-on invariants

Evaluated before any preset default. No preset, grant, exception, slider position, role or approval
can relax them.

1. **Workspace isolation.**
2. **Vault and credential broker.** Secrets never enter the model, prompts, the VFS, mruby, logs or
   computer disks (browser sign-in sessions are the one documented exception until login capture is
   settled; see the Product Direction open questions).
3. **Hard-deny floor:** no money transfers between accounts; no password, 2FA or recovery changes; no
   credential export; no agent changing its own grants, budget or policy. See [floor](#hard-deny-floor).
4. **Untrusted-content labelling.** Runs that read untrusted content lose automatic approval for
   external actions.
5. **A trigger is never consent** for external actions.
6. **Supply-chain and network guards:** pinned MCP tool definitions, an egress allowlist on computers,
   signed webhooks and rate limits.
7. **The event and audit log is always on.**
8. **An agent never approves anything.**
9. **On-behalf-of access.** Effective access is the agent's grants intersected with the access of the
   human it acts for: the requester in a DM, the routine owner in a routine, and the
   least-privileged human who invoked it in a group chat. This prevents confused-deputy escalation.
10. **No phone-home** ([pillar 11](../PRODUCT_DIRECTION.md#11-full-data-sovereignty-hosted-included)).
    Self-hosted installs send no telemetry by default (any opt-in telemetry is documented and
    inspectable), and there are no remotely flippable feature flags or egress switches. CI enforces
    this from Phase 0.
11. **Model routing is visible.** The run log records which model provider handled each run. A
    workspace setting, "send nothing to third-party models", restricts model routing to local models
    or explicitly allowed endpoints; when set, the engine denies any model call that would leave for a
    third-party provider, rather than falling back.

## Presets

| | Personal | Team | Company |
|---|---|---|---|
| Who | Solo user, Grok Bot style | About 2–50 people | Larger or regulated teams |
| Roles | Owner only | Owner, Admin, Member | Owner, Admin, Member; custom roles later |
| Approvers | The owner is the only approver | Each agent's manager is its default approver; admins too | Approver policies; two approvers on high-risk classes |
| Permissions | Owner sees everything; private items | Spaces carry permissions | Spaces carry permissions; per-Space policies |
| Connections | Personal | Personal or shared; members may grant "always" on their own personal connections; shared connections need an admin | Admin-approved allowlists for connections and MCP servers |
| Budgets | Optional per-agent spend cap | Budget per agent | Budgets plus two approvers above a spend threshold |
| Extra controls | — | — | Skill approval, computer-attach policy, audit export and retention; SSO/SCIM later (not claimed before it exists) |
| UI | Approval cards in the agent's DM, Home and Telegram; per-agent "always allow" list; autonomy slider; no role or policy screens | Adds a **Governance** area in Admin: policies, exceptions, approvers, budgets, audit | Same Governance area, with approver policies and audit export |

- **Defaults only.** Switching preset keeps every explicit grant and exception; everything is tunable
  afterwards.
- **No hidden enforcement.** Personal hides policy screens but not policy: invariants, the floor and
  the event log apply exactly as in Company.

## Roles and principals

### Humans

Fixed roles (ROADMAP P0 #1) per preset as above; custom roles later. Capabilities are named so
policies and the audit can refer to them, for example `approval.resolve` (optionally per action
class), `exception.grant`, `agent.manage`, `agent.computer.attach`, `computer.takeover`,
`connection.share`, `budget.raise`, `policy.edit`, `structure.approve`, `trash.purge`, `app.install`,
`skill.approve`, `mcp.approve`, `audit.read`, `settings.danger`. Role defaults are fixed in code; in
Personal the owner holds all of them. Installed add-on apps may declare further roles
([add-on apps](#add-on-app-manifests)).

### Agents

- Every agent is a named workspace member with identity, memory, grants, Space memberships, connection
  grants, routines, a budget, an autonomy slider position and optionally a computer.
- Every agent has a **manager** (in Personal, the owner). In Team the manager is the default approver.
- Agents never hold approval capabilities and never approve anything, including for other agents.
- Subagent, delegated and routine-started runs carry the causality chain and depth and never hold more
  privilege than the run, routine or human that started them.

### External visitors (published surfaces)

Published artifacts and forms introduce principals outside the workspace
([Artifacts and Forms, identity](../planning/artifacts-and-forms.md#identity)):

| Principal | Who | May do | Never |
|---|---|---|---|
| **Share-link visitor** | Anyone holding a signed link (optional password and expiry) | Call endpoints and **published queries** marked share-safe for that artifact | Read bindings directly; use AI or connections unless an admin enabled them for that artifact |
| **Anonymous (public) visitor** | Anyone on a public surface | Public endpoints, published queries and form submission, all rate-limited and captcha-protected where they accept input | Use AI or connections by default; see anything outside the published scope |
| **Form visitor** | Whoever submits a form (internal, link or public) | Create a record through the form's submission pipeline, as a change set attributed to the form-visitor principal | Read existing records (except an explicitly configured "show my submission"); edit or delete |

Rules:

- **Never on behalf of a member.** Visitor principals are not workspace members and never act on behalf
  of one. Their access is exactly the published scope, not the publisher's or any member's access.
- **Untrusted payloads.** Every visitor input (endpoint arguments, form fields, uploads, `ui/message`
  from an artifact) is untrusted. Runs and routines it triggers (for example on `form.submitted`) are
  tainted and have no automatic approval for external actions.
- **Rate limits and captcha** apply per surface, per form and per IP; uploads are quarantined.
- **No approval power.** Visitors cannot answer approvals or receive cards.

### Triggers and connections

Schedules, event filters, heartbeats, inbound messages and manual starts are **triggers**; connections
are **sources and tools**. Neither is a principal and neither has approval power. Both are recorded as
the run's origin in its causality chain.

## Permissions and effective access

Simple on purpose, with no Notion-style ACL soup:

- **Space-level access.** Access is granted per Space. Private Spaces and personal items exist.
- **Guest sharing** of a single object is the one explicit exception to Space-level access.
- **No per-document ACLs.** Today's stored document permission rows (ROADMAP P0 #3) must still be
  enforced until they are migrated to Space-level permissions; no new per-object ACLs are designed.
- **Agents join Spaces like teammates.** "Add Ada to Sales" is the whole data-access model.
  Connections and computers are granted per agent on top.
- **Effective access** for an agent action:

  ```text
  Space membership (or personal-item ownership, or guest share)
    ∩ the agent's grants (tools, connections, computer)
    ∩ the on-behalf-of human's access
    ∩ the external ACL (deep connectors, for the connection's identity)
  ```

- **Decision trace.** Every decision records which term denied or narrowed access. "Why can't Ada see
  this?" is answered in one click from that trace, for example "Ada is not in the Finance Space" or
  "Ada is acting for Sam, who has no access to this Drive folder".

### On-behalf-of resolution

| Run origin | The on-behalf-of human |
|---|---|
| DM with an agent | The requesting human |
| Group chat or channel | The least-privileged human who invoked the agent in that turn (an @mention, reply or command) |
| Assignment (issue, record) | The human who assigned it |
| Routine (any trigger) | The routine owner (for team-owned routines, the human who last enabled it) |
| Delegation or subagent | The on-behalf-of human of the parent run |
| Artifact viewed by a member | The viewing member: effective access is the artifact's bindings ∩ the viewer's access; mutations are recorded as "artifact X on behalf of Y" |
| Artifact endpoint or published query called by a visitor | None: the visitor principal, limited to the published scope (see [published surfaces](#published-surfaces-artifacts-and-forms)) |
| Approved step | Unchanged; the approver's access does not widen the run, it only satisfies the approval |

## Decision order

> **Invariant.** The engine evaluates in this fixed order. Steps must not be reordered, and no later
> step may widen what an earlier step denied. Invariants (steps 1–5) are evaluated before preset
> defaults and can never be relaxed by a preset, slider or exception.

1. **Workspace boundary.** Principal, target resources, connection and run belong to the active
   workspace.
2. **Kill switch.** A workspace, agent or routine kill switch denies.
3. **Hard-deny floor.** Floor actions are denied.
4. **Run mode.** Read-only runs never reach write tools (they are not even registered).
5. **Supply-chain and network guards.** The MCP tool definition matches its pinned hash; the egress
   destination is allowlisted; the webhook signature and rate limits pass.
6. **Explicit deny** on the agent, tool, connection or resource.
7. **Enablement.** The connection, MCP server, skill, app or computer provider is enabled and approved
   for the workspace and the agent.
8. **Agent grant.** Space membership and the agent's tool, connection and computer grants.
9. **On-behalf-of intersection.** The requester, routine owner or least-privileged group-chat invoker
   must also have access to the target. If not, deny with a trace naming that human.
10. **External ACL.** For deep connectors, the external system's ACL for the connection's identity.
11. **Budget and depth.** The estimated cost fits remaining budgets and the causality depth is within
    the limit; otherwise pause (budget) or stop (depth).
12. **Taint and trigger.** If the run is tainted, or the action is external and the run is
    trigger-started, automatic approval of external actions is removed for this decision.
13. **Explicit exceptions.** An exact-match scoped exception (including those written by learned
    exceptions or the autonomy slider) may satisfy an approval requirement, within the preset's caps
    and never for floor actions or where step 12 removed it.
14. **Preset resolution.** For anything not settled above, the preset supplies the default gate for
    the action's reversibility class: allow, ask, propose (change-set review) or ask two approvers.
    Defaults only; they never override steps 1–13.
14a. **Field-level approval rules** (business rules; locked 2026-11-02). The engine evaluates them
    together with the approval requirement, after every boundary, floor, access and budget step.
    Rules come from app manifests (`approval_rules`) and from Table settings, for example
    "discount > 20% needs the sales lead". The final gate is the **strictest** of the step 14
    default and every matching rule, so a rule can turn "allow" into "propose to role X" but never
    the reverse. Rules apply to humans and agents alike. See
    [Field-level approval rules](#field-level-approval-rules-business-rules).
15. **Execution-time recheck.** On approved execution, change-set apply or replay, steps 1–11 and
    14a run again against current state (today only the tool permission is rechecked).

This replaces the order in today's `OpenCompanyPermissionEvaluator` (workspace boundary → integration
enablement → session grant → tool permission → approval requirement) and its behaviour-mode step.

## Field-level approval rules (business rules)

Locked 2026-11-02 ([Product Direction](../PRODUCT_DIRECTION.md) pillars 5 and 7; story 14 in
[example flows](../planning/example-flows.md)).

- **What they are.** Declarative rules on record fields that turn a matching edit into a proposal
  routed to a named approver role. Examples: "discount > 20% needs the sales lead", "amount >
  €5,000 needs finance". Apps declare them in their manifest's `approval_rules`; Tables declare
  them in table settings (a `structural` change).
- **Shape (manifest).** `approval_rules: [{ when: "discount > 20", applies_to: [human, agent],
  approver: role:sales-lead }]`. `when` is a bounded predicate over the record's fields, comparing
  old and new values; it is not code. `applies_to` names the principals the rule covers.
  `approver` names a role or a resolvable person, never an agent.
- **Humans and agents alike.** A business rule is not an agent restriction. A human edit that
  matches becomes a proposal exactly like an agent edit, so a rule cannot be bypassed by doing the
  work by hand or through a form, artifact endpoint, import, API call or `oc`.
- **Same engine.** Matching edits produce the normal change-set proposal and approval card (what,
  why, where from, plus the rule that fired), the same audit entries and the same decision trace.
  "Why does this need approval?" names the rule.
- **Only adds gates (invariant).** A rule can make a gate stricter. It can never loosen a
  reversibility gate, the floor, an invariant or a step 1–11 denial. Explicit exceptions, learned
  exceptions and the autonomy slider (step 13) do not satisfy a field rule. Only the named approver
  role, under that preset's approver policy, can approve it. An agent never approves.
- **Self-approval (proposed).** The person who made the edit cannot approve their own rule-gated
  proposal, except in a Personal workspace, where the owner is the only approver.
- **Recheck.** Applying a staged change set re-evaluates the rules against current values (step 15).
- **Lifecycle.** Adding, changing or removing a rule is a `structural` change, and app upgrades that
  change `approval_rules` are reviewed with the upgrade.

## Action classes and default gates

Gating is by **reversibility**. This replaces the earlier rule that all writes need approval by
default. The table matches pillar 5; "proposal" means a change-set or plan card that a human reviews.

| Class | Examples | Personal | Team | Company |
|---|---|---|---|---|
| `read` | Read records, docs, files, mounted Drive files, search | Allowed | Allowed within Space access | Allowed within Space access |
| `reversible_edit` | Change-set edits to cells, records, issue fields, doc text; create records, issues and doc drafts | Auto-apply, visible in activity | Auto-apply; **bulk edits (default over ~50 rows) and schema changes are proposals** | Auto-apply small edits; proposals per Space policy |
| `delete` | Delete a record, issue, doc or file (moves to trash for 30 days) | Trash auto-applies | Trash auto-applies; **purge needs approval** | **Trash is a proposal; purge needs two approvers** |
| `external_draft` | Email draft, calendar hold, unsent post | Auto-apply | Auto-apply | Auto-apply |
| `external_send` | Send email, post, submit a form, mutating MCP or API-manifest call | Ask the first time per target, then one-tap "always for this recipient or domain" | Same; the agent's manager approves | Same; routine-started runs may only reach admin-allowlisted targets |
| `agent_delegation` (an `external_send`) | Launch or follow up an outside agent (Cursor Cloud Agents, Copilot coding agent, Codex cloud, Jules, Claude Code cloud tasks, an ACP coding CLI, Bowerbird Agents); this sends company context outside | Ask the first time per **provider and repository**, then one-tap "always" | Same; the agent's manager approves; only admin-allowlisted providers and repositories | Same; the allowlist restricts by default; routine-started launches only to allowlisted provider/repository pairs |
| `spend` | Purchases, paid API calls, card use | Ask, or a per-agent spend cap | Budget per agent | Budget plus two approvers above a threshold |
| `exposure_widening` | Approve a connected host; share link; publish an artifact or form by signed link or publicly; widen a published surface (new write endpoint, new or wider binding, new egress domain, AI or connections for visitors, custom domain); open a computer port; add an MCP server or egress domain; share a connection | Ask | Admin | Admin plus a second approver |
| `structural` | Create a Space, Table, field or relation; schema change; install or upgrade an app | Proposal (owner approves) | Proposal | Proposal per Space policy |
| `governance` | Change grants, exceptions, budgets, policies, identity files, computer attachment, connection grants | Owner | Admin (or holder of the capability) | Admin per approver policy |
| `floor` | Money transfers between accounts; password, 2FA or recovery changes; credential export; agent changing its own grants, budget or policy | **Never** | **Never** | **Never** |

Notes:

- **Classification.** Every tool (internal, VFS, mruby bridge, connection, MCP, API manifest, computer)
  declares a class. MCP `readOnlyHint` / `destructiveHint` are hints only; API-manifest operations are
  classified by HTTP method with overrides; unclassified tools are `external_send`.
- **Structural changes** are human-approved proposals in every preset
  ([pillar 7, governed creation](../PRODUCT_DIRECTION.md#7-core-primitives-add-on-apps-and-no-mess));
  in Personal the proposal is a one-tap card to the owner.
- **External actions in tainted or trigger-started runs** always ask (invariants 4 and 5), even when an
  "always" exception exists for the target. In Company, routine-started external actions also require
  an admin-allowlisted target.
- **Exceptions** can never cover the floor. "Always" is not offered for purge, `exposure_widening` or
  `structural` proposals; each instance asks.

## Hard-deny floor

Defined in code, versioned, grown only through code review. Workspace policy can add entries but never
remove them.

- **Core floor (pillar 5):** money transfers between accounts; password, 2FA or recovery changes;
  credential export by an agent (the only credential-including export is an owner's, see
  [exports](#export-backups-and-portability)); an agent changing its own grants, budget or policy. The money-transfer and
  password-change entries ship with the Phase 2 browser; the rest of the floor is complete in Phase 3.
- **Proposed additions** (design, for confirmation): trading securities; accessing another person's
  account or impersonating a human; editing or deleting event-log records.

A floor hit returns a structured denial as a tool result ("this action can only be done by a human;
here is what to hand over"). The agent may create a handoff issue for a human; that is work, not an
approval.

## Change sets

Every agent edit to structured data (table cells and records, issue fields, doc text, app records)
produces a change set, whether made through the VFS, the API or mruby
([UI design, section 4](../planning/ui-structure-and-agent-editing.md#4-agentic-editing-of-tables-and-issues-and-docs)).

- **Reversible edits auto-apply.** Within the agent's effective access and the preset's
  `reversible_edit` gate, a change set applies immediately as one batch, highlighted in the agent's
  color, visible in activity and revertible.
- **Review applies to proposals.** Bulk edits over the threshold, schema changes, Company Space-policy
  proposals and trash proposals go through plan → dry-run preview → staged edits → review per cell,
  row or all → apply. Reviewing a proposal *is* its approval; there is no second card.
- **Docs** use collaborative suggestion mode for proposals; auto-applied doc edits are still tracked
  and revertible.
- **Human edits always win.** An agent never overwrites a value a human changed after the agent read
  it; the conflict surfaces in review. Enforced by version checks, not by prompt.
- **Revert this run** undoes a run's change set at any time while the affected values are unchanged
  since. Revert is attributed to the human and recorded.
- **Recheck on apply.** Applying a staged change set reruns the decision order against current state.
- **Event log.** Change set planned, applied, staged, reviewed (per item, with reviewer), rejected and
  reverted are events with the run's causality chain; record history shows person-or-agent attribution
  and the run link.

## Trash and purge

- **Delete means trash.** Deleted records, issues, docs and files move to the Space's trash for 30
  days (default; tunable upward). Trashed items leave search and agent context.
- **Restore** from trash is a reversible action available to anyone with access to the item, and
  "revert this run" restores items a run trashed.
- **Purge** (permanent deletion, including automatic purge after the retention window) follows the
  preset: automatic in Personal, approval in Team, two approvers in Company.
- **Events.** Trash, restore and purge are event-log entries with actor, run and causality. The event
  survives the purge; the purged content does not.
- External deletes (for example deleting a file in a Drive mount or an email) are `external_send` or
  trash-style operations depending on whether the external system has a recoverable trash; this is
  declared per deep connector.

## Governed creation and content rules

From [pillar 7](../PRODUCT_DIRECTION.md#7-core-primitives-add-on-apps-and-no-mess):

- **Agents create within policy:** records, issues and doc drafts, as reversible change sets.
- **Always proposals:** creating Spaces, Tables, fields or relations, and changing a schema
  (`structural`). Deleting follows the trash and purge rules above.
- **Owner, status and review.** Every Doc, Table and Issue has a human owner and a status (draft,
  current, archived); Docs have a review date. An agent can be an issue's delegate, never its owner. A
  stale sweep, which agents may propose, flags expired items; archiving is a human decision. Archived
  items leave search and agent context.
- **Agent system files are not content.** Identity, instructions and memory live on the agent profile
  and under the VFS `/agents` mount, not in Docs. Editing them is a `governance` action following the
  existing relationship rules (self, manager), recorded in the event log.

## Add-on app manifests

Add-on apps (Mail, Calendar, CRM and later others) are built only from core primitives and never bring
their own storage.

- **Same policy for app data:** the engine, effective access, change sets, trash, revert, the event
  log, search and the VFS (`/apps/<app>/…`) apply exactly as to core data.
- **Roles declared by apps** map to capabilities scoped to that app's record types and views, are
  assigned by admins, and can never exceed the core role and capability model.
- **Install, upgrade and uninstall** are `structural` proposals approved by a holder of `app.install`.
  The review lists record types, required connections, roles, routine templates and the agent pack.
  Uninstall archives the data. Routine templates and agent packs are inert until enabled.
- **Third-party apps** install only from signed, declarative manifests, reviewed before install and
  bound to the manifest digest; a changed manifest needs re-approval. No executable code is installed.
  Custom views come later as sandboxed MCP Apps-style UI.

## Published surfaces: artifacts and forms

Artifacts are the only code-bearing primitive; forms are a view type on any Table
([pillar 7](../PRODUCT_DIRECTION.md#7-core-primitives-add-on-apps-and-no-mess),
[Artifacts and Forms](../planning/artifacts-and-forms.md#publishing-and-governance)).

### Publishing ladder

| Level | Who sees it | Gate per preset |
|---|---|---|
| Draft | Owner and authoring agent | None (reversible) |
| Space | Space members | Personal and Team: auto (internal, reversible). Company: per Space policy |
| Workspace | All members | Same as Space |
| Signed link ⚑ | Anyone holding the link | `exposure_widening`: Personal asks; Team needs an admin; Company needs an admin plus a second approver |
| Public ⚑ | Anyone, listed or unlisted | Same as signed link, **and** public publishing must be enabled for the workspace; it is **off by default** and enabling it is a `governance` action by an admin |

- Each publish is of an **immutable version** at a stable URL; republishing a new version that widens
  scope (escalations above) needs the same gate again. Narrowing or unpublishing is reversible and
  needs no approval; unpublishing keeps the data.
- An agent may prepare a publish; it is always a proposal for ⚑ levels, never self-approved.
- Pre-publish checks (password-type fields, brand-impersonation patterns, pre-publish scan) can block
  or require approval.

### Published queries

- A **published query** is a pinned, parameter-bounded, read-only, field-allowlisted projection that
  the owner approves as part of publishing.
- It runs **as the publisher's grant, limited to the published scope**: the query text and allowed
  fields are pinned at approval; visitors may only supply declared parameters within declared bounds.
- A change to the query, its fields or its parameter bounds is a new version and needs approval again.
- If the publisher later loses access to the underlying data, the query stops serving (decision
  recheck at call time), and the registry flags it.

### Artifact endpoints

- **mruby `endpoint` profile:** short wall clock, binding handles, a viewer principal, and a
  synchronous runner that still produces a run and events. Long work is queued as a routine.
- **Under the policy engine.** Every endpoint call is a run evaluated in the normal decision order.
  For members, on-behalf-of is the viewing member; for visitors, the visitor principal within the
  published scope.
- **Writes** go through change sets: reversible internal writes auto-apply per preset; external actions
  follow normal approvals. Endpoints whose side effects need approval cannot run unattended for
  visitors.
- **Bridge.** Only app-visible tools (the artifact's bound operations, its endpoints and the AI bridge)
  are callable; anything an artifact sends toward an agent is untrusted.

### AI bridge quotas

- The host AI bridge (`ai.complete`, `ai.extract`) is billed to the workspace budget with a **quota per
  artifact**, recorded as usage events.
- It is off for share-link and public visitors by default; enabling it for visitors is
  `exposure_widening`.
- Quota exhaustion fails the call cleanly; it never borrows another artifact's or agent's budget.

### Forms

- One governed submission pipeline: public origin with no cookies → captcha (ALTCHA default),
  honeypot and rate limits → schema, logic and consent validation → upload quarantine → record created
  as a change set by the form-visitor principal → untrusted `form.submitted` event.
- Custom artifact form UIs must submit through the form's endpoint; there is no second write path.
- Agents create forms as plan → preview → approve proposals; publishing a form follows the ladder.
- Retention, auto-delete, export and erasure for submitters are form settings recorded in the event log.

### Published-surfaces registry and kill switch

- Admin keeps **one registry** of every artifact, form and endpoint reachable from outside the
  workspace: owner, level, version, last change, traffic, AI and connection enablement.
- **Kill switch** per surface, per workspace (all external surfaces) and per instance (instance admin
  takedown). It is checked on every visitor request before anything else and is an event.
- **Abuse controls:** rate limits, captcha, a "report" link on every public surface, and lifecycle
  sweeps (last-viewed tracking, stale sweep).
- These controls are part of the AGPL product and never paywalled.

## Approval authority

| Preset | Who answers | Where |
|---|---|---|
| Personal | The owner only | Cards in the agent's DM, on Home and on Telegram |
| Team | The agent's manager by default; admins; per-connection owners for "always" on their personal connections | Same, plus the Governance area |
| Company | Approver policies (roles, groups, per Space and class); **two approvers** on high-risk classes (purge, spend above threshold, `exposure_widening`) | Same |

- **An agent never approves anything** (invariant).
- **No self-approval.** A human cannot approve a request where they are the requesting principal, nor
  a governance change that benefits their own capabilities. In Personal the owner necessarily approves
  their own agents' requests; that is "ask before sending", not self-approval. In Company, the second
  approver must differ from the human who initiated the run.
- **Interim (Phase 1).** Before approver policies exist, only workspace admins or the human who started
  the conversation may answer, never the requesting agent (Product Direction open question, proposed).
- **Capability check at answer time,** atomically with the existing pending → resolved claim.
- **Expiry** is a denial. **Circuit breaker:** repeated denials in one run pause it and notify the
  manager.

## Few, good approvals

- **Exception scopes:** once, this run, this task, time-limited, always. Exceptions match exactly on
  later calls: workspace, agent, tool (and pinned definition hash), class, target (recipient, domain,
  resource, folder), argument constraints, expiry and an optional spend ceiling.
- **Learned exceptions.** After N approvals of the same exact pattern (default N = 3), the card offers a
  scoped "always allow", for example "Ada may email @acme.com". Admins can restrict who may accept
  learned exceptions (for example only admins, or only for personal connections). Accepting is a
  `governance` event.
- **Batched review.** Many similar requests from one run ("Ada wants to send 12 follow-ups") are one
  card with a toggle per item; each item is still decided and recorded individually.
- **Non-blocking approvals.** A pending approval parks only its step; the run continues independent
  work and resumes the step when answered.
- **Per-agent autonomy slider: Ask first · Balanced · Autopilot.**
  - Each position is a **named bundle of scoped exceptions** written into the policy engine, capped by
    the preset and the floor. Moving the slider changes grants; it never bypasses the engine.
  - This is **not** the deleted behaviour modes (`autonomous`/`supervised`/`strict`), which bypassed
    policy.
  - New agents start at **Balanced**. The activity view suggests promotions based on approval history.
  - Moving the slider is a `governance` action by the agent's manager or an admin (the owner in
    Personal), recorded as an event. An agent cannot move its own slider (floor).
  - Invariants 4 and 5 still apply at Autopilot: tainted and trigger-started runs ask for external
    actions.

## Approval card contract

One contract renders everywhere: web, mobile, Telegram (Mini App for anything secret) and native
clients through the API, consistent with the [Messenger UI](../planning/messenger-ui.md).

| Field | Content |
|---|---|
| **What** | Plain-language summary; tool and class; target; arguments with secrets redacted; the change set or diff |
| **Why** | The agent's stated reason; the issue, record or routine it serves; link to the run |
| **Where from** | Trigger type; the on-behalf-of human; the causality chain; provenance of motivating content; an **untrusted content** flag when tainted |
| **Risk and cost** | Class, reversibility, estimated token/compute/money cost, remaining budget |
| **Who can answer** | Approver set and why; "you cannot approve this" for ineligible viewers; "needs a second approver" when applicable |
| **Options** | Approve once; Deny (optional reason returned to the agent); for eligible approvers, a scoped exception (this run, this task, time-limited, always), and the learned-exception offer when N is reached. Batched cards add per-item toggles. Floor actions are never rendered as approvable |
| **Expiry** | When the request lapses |

Pending cards appear on **Home** under "needs you" and inline in the conversation. Answers post
directly to the engine and update every surface over the realtime stream. Telegram buttons carry an
opaque request reference, never parameters. MCP URL-mode elicitation and `input_required` render as
approval cards or secure forms.

## Connections, OAuth and the credential broker

Detailed in [Connections, Events and Routines](../planning/connections-events-routines.md#1-connections)
and the [catalog](../planning/agent-primitives-catalog.md#1-credentials-and-identity).

- **Vault** (Phase 0 schema): per-workspace, personal and shared entries, each granted to specific
  agents. Connection secrets, MCP OAuth tokens, API-manifest keys, provider keys and plugin
  `userConfig` secrets live there.
- **Credential broker:** the only component that decrypts vault entries for outbound use, injecting at
  the tool, browser-fill or network boundary with run-scoped surrogate tokens.
- **Generic OAuth broker:** client registration in the order CIMD → DCR → admin-entered client
  credentials.
- **Connection ownership:** personal or shared. Team members may grant "always" exceptions on their own
  personal connections; exceptions on shared connections need an admin. Company uses admin-approved
  allowlists for connections.
- **Secret lookup** (`SecretSource`): vault → connected password manager (1Password, then
  Bitwarden/Vaultwarden; fill approved by a human) → secure form.
- **Use is an event;** values are never logged.

## MCP, API manifests, skills and plugins

- **Pinned MCP tool definitions** (invariant). A changed definition is disabled until re-approved.
  Company requires MCP servers to be on an admin-approved allowlist; adding a server is `exposure_widening`.
- **MCP isolation:** per-workspace registration at request or job time; `WebRequestGuard` with redirect
  and size limits; stdio servers only on an agent's computer or a sidecar.
- **API manifests:** non-GET operations are `external_send` by default; base URLs are SSRF-guarded.
- **Skills and plugins:** scanned; approval bound to the content digest; no automatic install from
  public registries; skills cannot grant tools, budgets, computers or egress. Skill approval is a
  Company control; in Personal and Team the owner or an admin approves on import.
- **Managed connectors** attach as MCP servers and are governed like any MCP server.

## Computer access policy

Computer access is a per-agent choice
([pillar 2](../PRODUCT_DIRECTION.md#2-own-computer-per-agent-optional-strongly-recommended)).

- **By preset:** Personal — the owner attaches. Team — admins decide which agents may have a computer.
  Company — a written computer-attach policy (which agents, roles or templates; allowed providers;
  forbidden combinations such as finance grants plus a computer) is the default.
- **Least-privilege default:** VFS-only can be the default for new agents; the UI still recommends a
  computer where useful. Agents may request a computer; they never attach one.
- **Mode switch preserves the agent:** identity, memory, grants, Space memberships and VFS access are
  untouched; detaching never deletes the agent.
- **On-behalf-of applies to computer runs** like any other run.
- **Takeover** requires `computer.takeover`; actions during takeover are attributed to the human.
- Attach, detach, provider change, sharing, egress changes and profile enables are `governance` or
  `exposure_widening` events.

## Export, backups and portability

"Your workspace is yours" ([pillar 11](../PRODUCT_DIRECTION.md#11-full-data-sovereignty-hosted-included);
full design in [Data sovereignty](../planning/data-sovereignty.md)). Export is a sovereignty right, not
a privilege, but bulk and full exports are exfiltration paths, so they are gated and always audited.

| Action | Personal | Team | Company |
|---|---|---|---|
| Download an object you can see (UI, `oc export`, API) | `read` | `read` | `read` |
| Bulk downloads (many objects at once) | `read`, audited | `read`, audited | `read`, audited |
| Agent downloads or exports objects to an external destination | `external_send` under normal policy | Same | Same |
| Full workspace export (`.ocarchive`) | Owner; automatic | Owner or admin; an admin approves | Owner or admin; admin plus a second approver |
| Export including secrets | Owner only, passphrase-encrypted, re-authentication required | Same | Same |
| Add or change a backup destination or Git mirror target | `governance` (owner) | `governance` (admin) | `governance` (admin per approver policy) |
| Member's own personal data export (GDPR access and portability) | Any member, for their own data | Same | Same |
| Workspace deletion | Owner, with an export prompt first and a deletion receipt | Same | Same |

Rules:

- **Full exports are owners' and admins' only, always audited,** and delivered through **expiring
  links**. Agents cannot start a full workspace export.
- **Credential-including exports are owner-only and never by an agent** (floor). By default archives
  exclude secrets.
- **Backup and mirror destinations are `governance`.** Agents cannot add or change them, even with an
  exception or at Autopilot. Scheduled backups to an approved destination run without further
  approval and are events; backups are encrypted with a customer-held key.
- **GDPR self-export** covers a member's own messages, personal items, profile and the data held about
  them; it never includes other members' private data.
- **Imports and restores** (`oc admin import`, `oc admin restore`, `--from cloud`) are owner or
  instance-admin actions and recorded as events in the target workspace.
- Every export, download batch, backup run, restore and destination change is an event-log entry.

## Agent delegation (outside agents)

Outside agents are delegates reached through the provider-neutral `ExternalAgentProvider` contract
([pillar 9, agent connections](../PRODUCT_DIRECTION.md#9-connections-events-and-routines)): hosted cloud-agent APIs, coding-agent CLIs over ACP on a computer
or connected host, and later Bowerbird Agents. Governance:

- **An external action.** Launching an outside agent sends company context (task, files, repository
  access) outside, so it is `agent_delegation`, gated like `external_send`: ask the first time per
  provider **and** repository, then a one-tap "always" exact-match exception for that pair. The
  approver is the preset's external-send approver (owner; agent's manager; Company approver policy).
- **Admin allowlist.** Admins can restrict which providers and repositories are allowed, in the Admin
  Governance area. In Company the allowlist restricts by default (nothing is allowed until listed).
- **Keys are the user's own.** Provider keys and CLI subscriptions belong to the user, live in the
  vault and are injected per call by the credential broker; they never enter model context, prompts,
  logs or the outside agent's task text.
- **Budgets.** Usage and cost reported by the provider are usage events that feed the delegating
  agent's budget; a launch is refused when the budget is exhausted.
- **Trigger is never consent.** A routine cannot silently send code or context to an outside agent:
  trigger-started launches ask (or, in Company, reach only allowlisted pairs).
- **On-behalf-of applies.** The context, repositories and files handed to the outside agent are limited
  to the effective access of the human the delegating run acts for.
- **Results come back under normal gates.** PRs, diffs, files and artifacts return as change sets,
  PR links or artifacts with "revert this run" where possible; merging, publishing or sending them
  follows the usual classes and approvals.
- **Outputs are untrusted.** Status events, logs, diffs and summaries from outside agents are labelled
  untrusted content; a run that reads them loses automatic approval for external actions.
- Launch, follow-up, stop, status and results are event-log entries with the run's causality chain.

## Connected hosts

Connected hosts (bring your own machine through the outbound `oc host` daemon, or SSH-only hosts) are
governed like provider computers, with extra disclosure
([Computer Provider Contract, connected hosts](computer-provider-contract.md#connected-hosts)):

- **Host approval is `exposure_widening`:** Personal asks; Team needs an admin; Company needs an admin
  plus a second approver. A host stays pending, with no capability usable, until approved.
- **Per-capability grants per agent:** computer, local MCP bridge (per MCP server), VFS projection and
  event source are each granted separately to specific agents. By default one agent per host gets the
  computer capability; giving it to more agents follows the shared-computer opt-in.
- **Self-managed labelling.** OpenCompany does not guarantee a host's isolation, egress enforcement
  beyond best effort, snapshots, physical security or availability, and says so in the UI. A sandbox
  mode of `none` is flagged in red. Company policy may forbid self-managed hosts, `none` mode, or hosts
  for agents with certain grants.
- **Host tokens** are their own token type, scoped to one host and workspace, rotated automatically and
  revocable; they cannot act as a user.
- **Secrets are injected per call** by the broker and never stored or synced on the host.
- **On-behalf-of applies to host-run actions** exactly as to other runs.
- **Event-source payloads are untrusted;** routines they trigger lose automatic approval for external
  actions.
- **Kill switch:** Admin → Hosts → Disconnect revokes the token and stops all agent processes on the
  host. Enrollment, approval, grants, disconnects and every host action are event-log entries.

## Data access path

> **Invariant.** All agent access to company data goes through governed paths: the VFS and the app's
> own tools, under the same engine. Deep-connector data (for example Google Drive) appears in Files and
> the VFS under the same policy. A computer is never a bypass: company data reaches it only through
> the VFS projection (mount, sync or CLI bridge) and returns only through the VFS write path.

- Without a computer, the VFS is a complete workspace for file-style work
  ([pillar 3](../PRODUCT_DIRECTION.md#3-the-vfs-is-the-governed-path-to-company-data)).
- Storage-mount writes go through the VFS write path and are then pushed with a revision check.
- VFS tools, mruby bridge calls and computer bridges emit the same `vfs.*` events and share one
  decision, change-set and replay path.
- See [Computer Provider Contract, VFS invariant](computer-provider-contract.md#vfs-invariant-the-computer-is-never-a-bypass).

## Run modes

Routines declare a mode, read-only or act; chat runs act. Enforced where tools are registered
(`ToolRegistry` and the tool providers), not by prompt.

| Mode | Use | Tool surface |
|---|---|---|
| `act` | Chat, assignments, act routines | Effective access, gated by class and preset |
| `read_only` | Heartbeats, monitors, background research, daily-brief gathering | Only `read` tools registered; no computer write tools (no computer tools by default); output is a message, a proposed issue or a suggestion |

## Triggers, events and untrusted content

- **Trigger is not consent** for external actions: schedules, event filters, heartbeats, inbound
  messages and webhooks start runs, and trigger-started external actions ask (in Company, only to
  allowlisted targets).
- **Labelling.** Event, webhook, connection and host event-source payloads, indexed connector content, tool results, web
  and MCP output, inbound messages and computer-browser content enter the model as labelled untrusted
  data blocks.
- **Taint.** A run that reads untrusted content loses automatic approval for external actions for the
  rest of the run. Reversible internal edits still auto-apply under the preset (they remain revertible
  and visible), and the card shows the taint source.
- **Loop protection.** Every event and run carries `causality_id` and `depth`; a maximum depth stops
  self-retriggering routines and agent ping-pong. Per-routine and per-connection rate limits and a
  dead-letter queue apply.
- **Event acceptance:** signed webhooks with secrets from headers only, rate limits, idempotency keys.
- **Optional critic** (later): an alignment check before high-risk tools that sees only the goal and
  tool-call metadata.

## Agent identity: inbox and one-time codes

Gmail, the Mail app and the agent inbox are Phase 2; phone numbers later.

- An optional per-agent email address, fed through the `Mailbox` connector into the agent's inbox.
  Enabling it is a `governance` action. Inbound mail is untrusted. Drafts are `external_draft`; sending
  is `external_send`.
- One-time codes go to the fill or broker and are redacted from model context where possible. Agents
  disclose that they are agents when contacting third parties.

## Controls and kill switch

Distinct in UI and API because "stopping work does not undo completed actions"
([catalog, control](../planning/agent-primitives-catalog.md#4-control)):

| Control | Effect | Who |
|---|---|---|
| Pause | Pauses the run; resumable (exists today through `ActiveAgentRun` / `ManageTasks`) | On-behalf-of human, manager, admin |
| Stop (cancel run) | Ends the run; completed side effects stand (exists today as cancel) | On-behalf-of human, manager, admin |
| Cancel schedule | Disables a routine's triggers | Routine owner, manager, admin |
| Delete | Removes an agent, routine, connection or computer per its own rules | Admin (owner in Personal) |
| Host disconnect | Admin → Hosts → Disconnect revokes a host's token and stops agent processes on it | Admin (owner in Personal) |
| Published-surface kill switch | Takes one artifact or form, or every external surface of the workspace, offline immediately; instance admins can take down any surface | Admin (owner in Personal); instance admin |
| **Kill switch** | Per routine, agent or workspace: cancels running runs, holds queued runs, stops computers, disables triggers until re-enabled | Admin (owner in Personal); an event; reachable from Telegram |

The kill switch is checked at trigger activation, routine start and every cancellation checkpoint and
wins over every grant and approval.

## Budgets

- **Scopes:** per agent and per workspace; per routine (routine contract); per provider/model later.
- **Resources:** tokens and money from model usage; compute from computer metering; spend from payment
  tools. All are usage events in the event log (`llm_usage_events` exists today and migrates in).
- **Reserve before call, settle after.**
- **Phase 2 basic compute cap** ships with the first computers.
- **Pause at the limit (Phase 3):** the run moves to `paused` / `waiting` with reason `budget`,
  computers stop with a snapshot, and a budget card goes to holders of `budget.raise`. An agent can
  never raise its own budget (floor).
- **Spend by preset:** Personal asks or uses a per-agent spend cap; Team uses a budget per agent;
  Company adds two approvers above a threshold. Single-use virtual cards are later.
- **Self-host:** budgets are spend control and reporting; nothing is billed.

## Event log, audit and replay

The workspace event log from
[Connections, Events and Routines](../planning/connections-events-routines.md#2-events) is the single
durable, append-only record and is always on (invariant). Its envelope (type, source, subject, actor,
time, idempotency key, untrusted payload, causality chain and depth) powers:

- **Audit:** policy decisions with their decision trace (allow, deny, ask, propose, floor hit, with the
  failing term of the effective-access formula), approvals, exceptions granted and used, slider
  changes, secret uses, taint, budgets, change sets, trash and purge, connection, MCP and app
  approvals, computer lifecycle and takeover, kill switch. This realizes ROADMAP P0 #9's authorization
  decision ledger.
- **Replay:** re-rendering a run's timeline and decisions by following its causality chain; never
  re-executing side effects.
- **Home and activity:** permission-checked views of the log.
- **Budget accounting.**
- **"Who allowed what, at what cost":** follow an action's causality chain to the trigger and
  on-behalf-of human, the decision and preset or exception that allowed it, the approver, and the cost
  events attributed to the chain.
- **Tamper resistance:** append-only for the application. Company adds audit export and retention
  settings with a documented minimum.

## Memory controls

Catalog Phase 3. Memory is viewable, editable and forgettable from the agent profile, each memory with
its source. Human edits follow the relationship rules (self, manager); every edit and forget is an
event. Memory derived from untrusted content keeps that provenance.

## Phasing

Consistent with the [Product Direction phases](../PRODUCT_DIRECTION.md#phases), the
[catalog phasing](../planning/agent-primitives-catalog.md#phasing) and the
[connections phasing](../planning/connections-events-routines.md#phasing).

| Area | Exists today (CURRENT_STATE) | Phase 0 | Phase 1 | Phase 2 | Phase 3 | Phase 4 |
|---|---|---|---|---|---|---|
| Policy engine | Behaviour modes, default `autonomous` | Policy engine with reversibility classes and presets replaces behaviour modes; on-behalf-of intersection | — | — | Learned exceptions, slider, batched review | Per-Space policies |
| Scoping and permissions | Broad `workspace_id` scoping; human ACLs incomplete | Close cross-workspace flaws (P0 #2–5, credential singleton bug, MCP isolation/SSRF) | — | — | Decision-trace explainer | Spaces as the permission unit, guest sharing |
| Approval authority | Any member can approve | Approver capability and no self-approval (P0 #6) | Interim: admins or the conversation starter | — | Manager approvers, approver policies, two approvers | — |
| Floor | None | — | — | Money-transfer and password-change entries | Full floor | — |
| Credentials | Encrypted at rest | Vault, credential broker, OAuth broker | — | Secure forms | 1Password, then Bitwarden/Vaultwarden | — |
| Event log | Per-feature tables | Event log with causality chain | Activity on Home | — | Replay and audit views; Company audit export | — |
| Budgets | Usage recorded, no enforcement | — | — | Basic compute cap | Budgets that pause; spend thresholds | — |
| Change sets and trash | — | — | — | — | — | Change sets for every object; trash and purge (Tables earlier with the new client) |
| Apps | — | — | — | Mail on the manifest model | Calendar | App manifest runtime, CRM, third-party manifest review |
| Artifacts and forms | Renderer packages, no artifacts | User-content origin and sandbox proxy design; Code Mode bridge re-homed | Artifacts at private, Space and workspace level with read bindings | Endpoint profile, write bindings via change sets, AI bridge quotas, static bundles from computers; Forms v1 with public submission | Signed and public publishing, published queries, registry, abuse controls, kill switch; Forms v2 | Artifacts as app custom views and packaging |
| Export and backups | — | `.ocarchive` format with the baseline schema; no-telemetry/no-remote-flags CI check | Object downloads, `oc export` | Workspace archive export/import, `oc admin backup/restore` | Scheduled encrypted backups, Git mirror, export gates, GDPR export, backup verification | — |
| Agent delegation | — | — | — | ACP runner on computers and hosts; Copilot coding agent; provider/repository allowlist | Cursor, Codex, Jules, Claude Code cloud where APIs allow | Bowerbird Agents with S1 or later |
| Connected hosts | — | Host token type | — | Outbound `oc host`, container sandbox, Admin → Hosts, SSH hosts | Local MCP bridge, event source, `oc mount`, bwrap | — |

## Acceptance criteria

Phase 3 exit and governance tests:

1. One engine decides every tool call in the documented order, with execution-time recheck; preset
   switches change defaults only and keep every explicit grant (tested by switching presets).
2. Invariants cannot be relaxed by preset, exception or slider position (tested at Autopilot in
   Personal).
3. **On-behalf-of tests:** in a DM, an agent with access to a Space cannot read it for a requester who
   lacks access; in a routine, access is limited to the routine owner's; in a group chat with two
   invokers, access is limited to the least-privileged one. Each denial's trace names the limiting
   human.
4. Reversible edits auto-apply as revertible change sets per preset; Team bulk edits over the threshold
   and schema changes become proposals; human edits are never overwritten.
5. Delete moves to trash for 30 days; purge follows the preset (auto, approval, two approvers); trash,
   restore and purge are events and revert restores a run's trashed items.
6. External sends ask the first time per target and then honour a one-tap "always for this recipient or
   domain"; tainted or trigger-started runs still ask; Company routine runs reach only allowlisted
   targets.
7. Learned exceptions are offered after 3 identical approvals and can be restricted by admins; batched
   cards decide items individually; pending approvals park only their step.
8. The autonomy slider only writes grants, is capped by preset and floor, defaults to Balanced, and
   cannot be moved by the agent.
9. Agents cannot approve anything; humans cannot approve their own requests where the preset forbids
   it; Company high-risk classes need two approvers.
10. Floor actions are denied with a structured result in every preset.
11. Space-level permissions, private Spaces and guest single-object shares behave as specified; no
    per-document ACL is consulted for new objects; "Why can't Ada see this?" returns the failing term.
12. Secrets never appear in prompts, logs, VFS, mruby or computer disks.
13. Changed MCP tool definitions, skills, plugins and third-party app manifests are disabled until
    re-approved.
14. Budgets pause runs and stop computers at the limit; only a capability holder can raise them.
15. No company-data read or write from a computer bypasses VFS policy or audit.
16. Personal shows no role or policy screens; Team and Company show the Governance area in Admin.
17. Signed and public publishing require the preset's `exposure_widening` approval; public publishing
    is impossible while it is disabled for the workspace (the default).
18. Visitor principals never act on behalf of a member: a public visitor can reach only published
    queries, public endpoints and form submission; a published query cannot be called with parameters
    outside its bounds or return non-allowlisted fields.
19. A form submission creates exactly one change set by the form-visitor principal and one untrusted
    `form.submitted` event, and routines it triggers ask before external actions.
20. The published-surfaces registry lists every external surface, and its kill switch takes a surface
    offline before any further visitor request is served.
21. A full workspace export follows the preset gate, is audited and delivered by an expiring link; an
    agent can neither start one, include secrets, nor change a backup or mirror destination; any
    member can export their own personal data.
22. With "send nothing to third-party models" set, no model call reaches a third-party provider, and
    every run shows its provider; a self-hosted install makes no telemetry or remote-flag requests by
    default (CI check).
23. Launching an outside agent asks the first time per provider and repository, respects the admin
    allowlist, never asks from a trigger-started run without an approval, uses vault keys injected per
    call, and records reported usage against the agent's budget.
24. For any agent action, an admin can answer "who allowed what, at what cost" from the event log via
    the audit view and the API.

## Open questions

- The bulk-edit threshold default (~50 rows per pillar 5) and default Company per-Space proposal
  policies.
- Exactly which exceptions each autonomy slider position bundles, per preset.
- Which classes count as "high-risk" for Company two-approver rules beyond purge, spend above threshold
  and `exposure_widening`.
- On-behalf-of for team-owned routines (last enabler versus a designated owner).
- Whether the proposed floor additions (securities trading, impersonation, event-log tampering) are
  accepted.
- Event-log retention minimums on self-host, and the audit export format.
- Whether the critic model is in Phase 3 or later.
- Whether instance admins on self-host can disable public publishing for every workspace by default.
- The threshold at which a download counts as "bulk" for audit and alerting.

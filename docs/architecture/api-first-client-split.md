# API-First Client Split

Status: Design — accepted rule (pillar 4, API-first now; Inertia frozen). Implementation planned from Phase 1. No implementation claims.
Verified: 2026-11-02 against docs/PRODUCT_DIRECTION.md; current-state statements checked against docs/CURRENT_STATE.md and a read-only look at `routes/`, `bootstrap/app.php`, `config/` and `app/Http/Middleware` in the working tree.
Owner doc: [Product Direction](../PRODUCT_DIRECTION.md) (pillar 4, Messenger-first and API-first).
Related: [Messenger UI](../planning/messenger-ui.md) ·
[Connections, Events and Routines](../planning/connections-events-routines.md) ·
[Agent-native work apps](../planning/agent-native-work-apps.md) ·
[Agent Primitives Catalog](../planning/agent-primitives-catalog.md) ·
[Governance model](governance-model.md) ·
[Computer provider contract](computer-provider-contract.md) ·
[OpenCompany Cloud](../planning/opencompany-cloud.md) ·
[`oc` CLI and hosts](../planning/oc-cli-and-hosts.md) (accepted) ·
[UI Structure and Agent Editing](../planning/ui-structure-and-agent-editing.md) (accepted) ·
[Artifacts and Forms](../planning/artifacts-and-forms.md) (accepted) ·
[Wayfinder routing](wayfinder-routing.md)

This document owns the shape of OpenCompany's API-first architecture: one Laravel backend serving
web, mobile and desktop clients through a versioned public API, a realtime event stream and push.
It owns API, auth, realtime and notification requirements and the migration order. It does not own
product UX (messenger doc), policy content (governance model), the connection/event/routine model
(pillar 9 doc), or the computer contract.

---

## The rule (now)

The direction decided API-first **now** (2026-11-02; previously "planned, likely soon"):

> **Inertia is frozen: no new Inertia pages or page props.** The new web client is a **Vue 3 +
> Reka UI single-page app with a token-based design system**, built only on the versioned public API
> (OpenAPI), the realtime event stream and push notifications. Old Inertia pages keep working until
> the new client reaches parity for their job, and are then **deleted**, not ported or polished.

Corollaries:

- New endpoints are public API from the start: stable resource shapes, explicit workspace,
  documented errors, cursor pagination, idempotency keys on mutations.
- New realtime events are typed, documented, and sourced from the workspace event log.
- No security decision lives only in a client.
- Pages whose job moves elsewhere are deleted at parity rather than migrated: Dashboard, Activity
  and Workload (→ Home), Lists (→ Issues), Tasks as a top-level area (→ runs in threads, Home and
  profiles), Automation (→ Routines), Org (→ Team), Chat (→ the new messenger). See the "one concept
  per job" table in the [UI doc](../planning/ui-structure-and-agent-editing.md).

---

## Today

Observed in the working tree (2026-11-02); consistent with [CURRENT_STATE.md](../CURRENT_STATE.md):

- **Monolith:** Laravel 12 + Inertia + Vue 3. `routes/web.php` renders Inertia pages; most pages pass
  only identifiers as props and load data through `useApi()` against `/api/*`. Wayfinder generates
  TypeScript routes/actions from Laravel routes.
- **API:** `routes/api.php` has a large workspace-scoped group behind `resolve.workspace`, external
  webhooks, Telegram Mini App endpoints, and the OpenAI-compatible AI Gateway under `/ai-gateway/v1`
  (bearer keys). The app API has no version prefix and no OpenAPI document.
- **Auth:** session-cookie auth via Sanctum `statefulApi()`. No token or OAuth flow for native
  clients was found.
- **Workspace resolution:** `ResolveWorkspace` uses the route slug, then an `X-Workspace-Id` header,
  then a **session fallback** (`current_workspace_id`), which stateless clients cannot use.
- **Realtime:** Laravel Reverb with Echo private/presence channels (`chat.{channelId}`,
  `workspace.{id}.tasks|agents|approvals|online`, `notifications.{userId}`). Task runtime is partly
  polled. There is no workspace event log yet (Phase 0 adds it).
- **Telegram:** a deep server-side adapter with its own approval rendering and Mini App.
- **Push:** none.
- CURRENT_STATE's authorization gaps (channel membership, document permissions, DM creation,
  approver capability, gateway key scopes) apply to every client and are Phase 0 work.

## Target

```
                 +------------------------------------+
                 | Laravel backend (API-first)        |
                 |  /api/v1/... (OpenAPI)             |
                 |  auth: session | token | OAuth     |
                 |  workspace event log ─▶ realtime   |
                 |  stream + push dispatcher          |
                 |  connections, routines, policy     |
                 |  channel adapters (Telegram, later |
                 |  Slack/X) server-side              |
                 +----------------+-------------------+
                                  |
      +-------------------+-------+-----------+-------------------+
      |                   |                   |                   |
  Web SPA (Vue 3 +   Mobile client      Desktop client      `oc` (Rust CLI,
  Reka, token                                                 TUI, MCP server,
  design system)                                              host daemon)
```

- One backend; every first-party client is an API consumer without private back doors.
- **`oc` is a first-class client** (pillar 10, [`oc` CLI and hosts](../planning/oc-cli-and-hosts.md)):
  a single Rust binary that is the command-line client, a `ratatui` TUI for chat and Home, a local
  MCP server (`oc mcp serve`), and the outbound-only connected-host daemon (`oc host`). It uses only
  the public API and realtime stream, through a **Rust client generated from OpenAPI**. Agents are
  its first user, so the API's errors, schemas and results must be machine-actionable (see below).
- Telegram and later channels stay **server-side adapters** that call the same application services
  and emit into the same event log.
- Self-hosters get all clients against their own backend; Cloud runs the same backend.

---

## Required pieces

### 1. Versioned public API with OpenAPI

- A versioned prefix (for example `/api/v1`); breaking changes need a new version.
- Coverage: **everything the UI does**, including the Phase 1 messenger and:
  - **Conversations and messages**: sections, unread counts, mentions, reply threads, pins, cards,
    files (including Drive-mounted files through the one Files store/VFS).
  - **Home feed:** the attention page as a ranked, permission-filtered feed (needs you, results,
    FYI from the event log; daily brief), with counts for the rail badge.
  - **Rail state:** per-user pins, section order and collapse state.
  - **Presence and object realtime:** human and agent presence per object (who is viewing or
    editing, agent progress chips with stale timeouts), and change notifications for every object
    type, not just chat.
  - **Runs:** state, steps, controls (pause, stop, steer; later cancel-schedule, delete, kill switch).
  - **Approvals:** list, answer with an exception scope (once, this run, this task, time-limited,
    always; exact match), multi-approver state ("1 of 2"), and the variants (external send with
    first-time-per-target "always for this recipient/domain", spend, exposure, proposals, purge, MCP
    tool-definition re-approval, skill/plugin digest approval, outbound mail).
  - **Agent delegations** (pillar 9 agent connections): list allowed providers and repositories (with
    admin restrictions), launch a delegation (returns a run, or a structured pending-approval result
    on first use per provider and repo), follow up, stop, status/events from the event log, and
    results (PR link, change set, artifact, logs, reported cost against the agent budget).
  - **Batched approvals:** one approval with items, answered with per-item decisions in a single
    idempotent request.
  - **Exceptions:** list, create, revoke per agent; **learned-exception offers** (pattern, count,
    proposed exact-match scope) with accept/dismiss; who may accept is server-enforced.
  - **Connections** (pillar 9): list, create (personal or shared), OAuth broker start/callback,
    status, revoke, mounts; secrets never returned, only vault references.
  - **Events:** query the workspace event log (permission-filtered, cursor-paginated) for the
    activity feed and audit; dead-letter inspection and replay for admins.
  - **Triggers and routines:** CRUD with versions, enable/pause, run now, run history, routine drafts
    created from chat, "save this task as a routine".
  - **Policy:** the workspace **preset** (Personal, Team, Company) read and switch (switching keeps
    explicit grants); grants; approvers and approver policies; budgets; read-only modes. Personal
    exposes no role/policy endpoints beyond what its UI needs; Team and Company back the Governance
    area.
  - **Autonomy slider:** read and set per agent (Ask first, Balanced, Autopilot); the response lists
    the exceptions the position writes, capped by preset and floor; promotion suggestions from
    approval history.
  - **Decision trace:** "why can't Ada see this?" / "why did this need approval?" for a subject and
    agent, returning the deciding factors (Space membership, agent grant, on-behalf-of human,
    external ACL, preset rule, floor entry) and any fix the caller may apply.
  - **Spaces:** list, open/private, membership **for people and agents** ("add Ada to Sales"), the
    six tools per Space (Issues, Tables, Docs, Files, Artifacts, Routines), scoped app views, personal items, guest single-object shares (explicit,
    audited exception); creation and schema changes as governed proposals. No per-document ACL
    endpoints.
  - **Trash:** list, restore, and **purge** (approval per preset: Team one approver, Company two);
    items auto-expire after 30 days.
  - **Work apps:** Tables (including the new column types, server-side paging for a virtualized
    grid, row detail and history), Issues, Docs (canonical structured format, version checks,
    suggestion mode), Files; owner/status/review lifecycle; record references and mentions.
  - **Change sets:** create (plan and dry-run preview), stream staged items, review per cell, row or
    all, apply, reject, **revert this run**, and conflict reporting where human edits win; history
    with actor and run on every object.
  - **Artifacts:** create, list per Space, read sources and manifest, bindings, versions (immutable,
    stable URLs), live-preview session for the context pane, publish along the ladder (draft → Space
    → workspace → signed link → public; the last two are `exposure_widening` approvals), unpublish,
    remix inside the workspace, last-viewed and stale status, Typst PDF export into Files, and the
    **published-surfaces registry** with kill switch and abuse reports (admin).
  - **Artifact endpoints** (Phase 2): mruby `endpoint`-profile calls, each producing a run and event;
    published queries (field-allowlisted, read-only, owner-approved); AI bridge with per-artifact
    quotas.
  - **Forms:** form views on Tables (definition, render modes, publish level), and the **submission
    endpoint** on the public form origin (captcha, honeypot, rate limits, validation, upload
    quarantine, change set by the form-visitor principal, untrusted `form.submitted` event); export
    and erasure for submitter data.
  - **Apps platform:** app manifests (validate, signature status for third-party apps), install
    (governed), upgrade (schema migration), uninstall (archive), and app record access mirroring
    `/apps/<app>/…` in the VFS.
  - **Agents:** profile, mode (computer or VFS-only), memory controls, action items, inbox.
  - **Computer:** panel endpoints defined by the computer contract.
- An OpenAPI document generated from, or checked against, the code in CI so drift fails the build.
- Conventions: cursor pagination (aligned with VFS keyset paging), typed error envelope,
  idempotency keys on mutations, ETags/versions on editable records, redaction of secrets.
- Rate limits per token and per workspace (also a Phase 0 security item).
- No hardcoded provider/model IDs or capability lists; served from app-owned catalogs.

### 2. Authentication for native apps and `oc`

- Web client: first-party cookie sessions or tokens (open).
- Mobile and desktop: OAuth 2.1 authorization code with PKCE, short-lived access tokens, rotating
  refresh tokens, per-device sessions that users and admins can list and revoke.
- **`oc`** (and native apps where a browser redirect is awkward): **OAuth device authorization flow**
  (`oc login`). Tokens are stored in the **OS keychain**, never in plain config files.
- **Contexts:** a client may hold several contexts (instance + workspace + identity), for self-hosted
  instances and, from Phase 5, Cloud. The API must make the instance and workspace identity
  discoverable so `oc context use` can switch safely; tokens are per context.
- **Token types:**
  - **Personal user tokens:** act as the user, scoped, expiring, listable and revocable by the user and
    admins. Used by `oc`, scripts and `oc mcp serve`.
  - **Host tokens:** for the `oc host` daemon only; scoped to that host's approved capabilities
    (computer, local MCP bridge, VFS projection, event source), rotated automatically, revocable from
    Admin → Hosts with a kill switch. A host token cannot act as a user or approve anything.
  - **Agent and on-behalf-of identity stays server-side.** No client receives an agent token; agent
    calls are made by the server with the agent identity plus the on-behalf-of human, as required by
    the authorization invariants below.
- Token scopes map onto existing capabilities; effective permission = user capability ∩ token scope.
- AI Gateway keys stay separate machine credentials with their own scopes and expiry.
- This user-facing OAuth server is distinct from the **connection OAuth broker** (pillar 9), which
  makes OpenCompany an OAuth *client* of external services.
- SSO/SAML is not claimed.

### 2a. Agent-friendly contracts (required by `oc`)

`oc` follows agent-first CLI rules (self-describing schema, errors naming the next command,
`--json`/NDJSON, stable exit codes, `--wait`, `--dry-run`). The API must support them for every
client:

- **Dry-run:** every mutating endpoint that produces a change set accepts a dry-run flag and returns
  the **change set** (items with old/new values, scope, policy decision: auto-apply, proposal, needs
  approval, denied) without applying it.
- **Structured pending-approval results:** when a call is parked on an approval, the response is a
  typed result (not an error string) with the approval id, what/why/where-from summary, eligible
  approvers, how to answer (endpoint and `oc approvals approve <id>`), and how to wait.
- **Waitable operations:** runs, `ask`, change-set application and approvals expose a wait mechanism
  (long-poll or stream subscription by id) so `--wait` never needs client polling loops.
- **Typed, actionable errors:** a stable error code, a human message, and a machine-readable next step
  (for example "missing scope X", "agent not in Space Y: `oc spaces add-member`"), plus the decision
  trace reference for policy denials.
- **Schema:** the OpenAPI document is published by the server so `oc schema` and the generated Rust
  client stay in sync; OpenAPI drift fails CI.
- **`oc mcp serve`:** exposes the user's workspace to local MCP clients (Claude Code, Codex, Cursor)
  **with the user's own access**, by translating MCP calls into public API calls with the user's
  personal token. It adds no server-side privileges; approvals raised by those calls are ordinary
  approvals routed per preset.

### 3. Realtime event stream (fed from the event log)

- The stream is a **projection of the workspace event log** (pillar 9): events are written to the
  log first (queue-before-ack), then fanned out to subscribed clients. The same envelope (type,
  source, subject, actor, time, causality) reaches clients, filtered by what the subscriber may read.
- High-frequency ephemeral data (text deltas, presence, typing, computer frame signalling) may use
  side channels that are not persisted, but anything that matters for audit, replay or the activity
  feed goes through the log.
- Carries: messages and deltas, typed run activity (thought, action, needs input, result, error;
  plan checklist), approval lifecycle, Home feed updates, routine runs, connection events (for example
  Drive changes), **object changes and change-set items for every object type**, presence (humans and
  agents, per object), agent status, and computer pane signalling.
- Transport: WebSocket (Reverb today) with SSE as a fallback. Each subscription has a cursor (the
  log position) so clients resume after reconnect and backfill gaps through the events API.
- Large payloads are fetched through the API, not pushed.

### 4. Push notifications

- For approvals addressed to the user, mentions, takeover requests and (configurable) routine
  outputs such as the daily brief.
- Dispatched by the backend from event-log events; payloads carry no secrets and minimal content
  (configurable per workspace), deep-linking into the client.
- Approve/reject from a notification uses the same approval endpoint and eligibility checks.
- Self-hosters configure push credentials or opt out; whether mobile web gets push before native
  apps is open.

### 5. Web client

- A new **Vue 3 + Reka UI SPA** with a token-based design system (semantic tokens, wrapper
  components, dark mode through tokens). Build order and reuse are owned by section 6 of the
  [UI doc](../planning/ui-structure-and-agent-editing.md): shell, Chats, Home, typed run activity and
  inline approvals first; then the context pane with change sets for Tables; then the agent panel,
  Issues and Docs suggestion mode; then the computer pane, Spaces and the anti-mess rules.
- Old Inertia pages are deleted as the SPA reaches parity for their job; none are extended.
- Wayfinder may keep generating typed calls while the client lives in this repository; see open
  questions.

### 6. Mobile and desktop spikes

- Goals: OAuth+PKCE sign-in, list conversations, send and stream a message, answer an approval,
  receive a push, view the activity feed, open a computer panel read-only.
- Spikes validate contracts; they are not product commitments.

### 7. Origins: API, user content and public forms

Artifacts and forms introduce origins that are deliberately **separate from the API and app
origin** (design owned by [Artifacts and Forms](../planning/artifacts-and-forms.md), "Runtime and
security"):

- **User-content origin** for interactive artifacts: rendered in a sandboxed iframe through a sandbox
  proxy, ideally one subdomain per artifact, configured by `ARTIFACT_ORIGIN`. It **never carries
  session cookies or API tokens**, uses a default-deny CSP from the manifest, and cannot call the
  public API directly.
- **MCP Apps bridge:** the only channel between an artifact and the host is the MCP Apps `ui/*`
  bridge (`tools/call`, `ui/message`, `ui/update-model-context`). The host client (web SPA, later
  native clients) relays bridge calls to the API **on behalf of the viewer**, restricted to the
  artifact's app-visible tools (its bindings, endpoints and the AI bridge). Effective access =
  bindings ∩ viewer's access. Anything an artifact sends toward an agent is tagged untrusted.
- **Public form and share origin:** receives form submissions and serves signed-link and public
  artifacts to anonymous or form-visitor principals; no auth cookies; rate-limited; only share-safe
  endpoints, published queries and submission.
- Without `ARTIFACT_ORIGIN`, self-hosts fall back to a `CSP: sandbox` opaque-origin iframe; the API
  reports the reduced isolation so Admin can show it.
- The same bridge is what Phase 5 MCP Apps export uses when OpenCompany's MCP server exposes
  artifacts as `ui://` resources to Claude or ChatGPT; external MCP App cards in the messenger reuse
  the sandbox in the other direction.

---

## Workspace scoping and authorization at the API layer

Invariants for every endpoint and stream subscription, for every client:

1. **Every app endpoint is workspace-scoped** with the workspace explicit in path or header. No new
   endpoint relies on the session fallback in `ResolveWorkspace`.
2. **Membership first:** the principal must be a member of the workspace (`workspace_members` for
   humans) before any lookup.
3. **Scope the query, then authorize the object:** models with `workspace_id` load through
   `forWorkspace()`; related models through their relation (`whereHas(...)`). A foreign id returns
   not-found.
4. **Same policy decisions for every client:** API controllers call the same policies and services;
   no client-specific shortcuts. The pillar 5 policy engine (presets, gating by reversibility,
   scoped exact-match exceptions, hard-deny floor) is enforced server-side, never in clients.
5. **Token scope narrows, never widens.** Personal tokens act as their user; host tokens are limited
   to their host's approved capabilities and can never act as a user or approve.
6. **Approvals:** eligibility is checked at the approval endpoint for every surface, per preset
   (Personal: owner; Team: the agent's manager by default, admins for exposure and shared
   connections; Company: approver policies, two approvers on high-risk classes). **An agent never
   approves anything**, and agent tokens cannot call approval or exception endpoints. Before Phase 3
   roles exist, the direction's interim proposal applies: workspace admins or the human who started
   the conversation.
6a. **On-behalf-of identity on every agent call:** every API, VFS, mruby and tool call made by an
   agent carries the on-behalf-of human (DM requester, routine owner, or least-privileged invoking
   human in a group chat) alongside the agent and run. Effective access = Space membership ∩ agent
   grants ∩ on-behalf-of human ∩ external ACL. Calls without an on-behalf-of identity are rejected;
   the identity is recorded in the event log and decision trace. This prevents confused-deputy
   escalation.
7. **Events and stream:** events are filtered per subscriber by read permission on their subject;
   channel auth re-checks on reconnect. Event payloads are untrusted data for every consumer.
8. **Connections:** personal connections are usable only by their owner (and agents the owner
   grants); shared connections follow workspace grants; effective access to mounted data is the
   external ACL ∩ OpenCompany grants.
9. **Spaces and apps:** Space membership (people and agents) narrows workspace access for objects,
   events, presence and VFS paths; app records follow the same rules as table records; schema
   changes, Space creation and app install are governed proposals; deletes go to trash and purge is
   approved per preset.
9a. **Artifacts and forms:** artifact principals ("artifact X on behalf of Y"), share-link visitors,
   anonymous public visitors and form visitors are distinct principals with their own narrow
   permissions; none can reach endpoints outside their allowlist, AI or connections unless an admin
   enabled it for that artifact.
10. **Audit:** every mutation records principal, on-behalf-of human (for agents), workspace, client
   type, session/token id, and the policy decision (auto-applied, exception id, or approval id) into
   the event log.

API feature tests include cross-workspace and non-member cases for each resource.

---

## Migration steps (ordered, no dates)

1. **Phase 0 prerequisites:** authorization fixes; event log with causality chain in the squashed
   baseline; Connections model with vault and credential broker; policy model replacing behaviour
   modes; API rate limits. **`oc` groundwork:** device-flow OAuth, personal and host token types, and
   OpenAPI generation (direction phase table).
2. **Contract baseline:** versioned namespace; pagination, error and idempotency conventions;
   OpenAPI document with CI drift check.
3. **Explicit workspace** on all new endpoints; stop relying on the session fallback.
4. **Event-fed stream:** stream contract with cursors and resume, sourced from the event log; move
   run progress from polling to stream events.
5. **`oc` v0 on the contract** (Phase 1, per [`oc` CLI and hosts](../planning/oc-cli-and-hosts.md)):
   login and contexts, Home, approvals, ask and chat TUI, work objects, `oc fs`, runs, `--json` and
   schema, `oc mcp serve`. Host tokens are used from Phase 2 (connected hosts).
5b. **New SPA on the contract** (Phase 1): token design system, rail, Chats, Home feed, typed run
   activity, inline approvals, routines from chat, Drive files, MCP re-approval cards.
6. **Delete superseded Inertia pages at parity** (Chat, Dashboard, Activity, Workload, Automation,
   Org, then Lists once Issues exists) instead of porting them.
7. **Token/OAuth auth** with device sessions and scopes.
8. **Push** for approvals, mentions and takeover requests.
8a. **Artifacts:** Phase 0 designs the user-content origin and sandbox proxy; Phase 1 ships artifact
   and preview APIs with read bindings; Phase 2 endpoints, write bindings and the form submission
   endpoint; Phase 3 signed/public publishing and the registry.
9. **Object APIs for Phase 4:** change sets, Spaces, presence for every object, and the app manifest
   runtime; remaining Inertia pages deleted at parity.
10. **Mobile and desktop spikes**; decide client repository placement.

Each step keeps the product usable.

---

## Risks

- Freezing v1 shapes too early; mitigate with a beta label until native clients ship.
- Two auth modes (cookie and token) double the surface; both share workspace and policy middleware.
- Event log fan-out cost on single-machine self-hosts; the stream must degrade gracefully and
  retention must be configurable.
- Telegram drift if the adapter bypasses application services or the event log.

## Open questions (from the direction, plus derived)

- Whether the web client lives in this repository (`clients/web`) or its own (open in the direction
  and the UI doc).
- Wayfinder versus a generated OpenAPI client (possibly both during transition).
- The mobile and desktop stack; whether mobile web gets push before native apps.
- Cookie sessions vs tokens for the first-party web client.
- WebSocket only, or WebSocket plus SSE.
- Which ephemeral stream data (deltas, presence, frames) stays out of the event log.
- Whether the AI Gateway and the app API share a versioning scheme.
- Whether native mobile/desktop apps use device flow, authorization code with PKCE, or both.
- How `oc` discovers Cloud versus self-host instances for contexts (Phase 5 Cloud contexts).

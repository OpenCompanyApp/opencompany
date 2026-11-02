# Cloudflare OS Investigation for OpenCompany

> **Direction status (2026-11-02):** Partially superseded by [Product Direction](../PRODUCT_DIRECTION.md) — the "do not adopt as runtime" conclusion stands; the Artifact follow-up is a later feature behind messenger, computer and governance work.

- Date: 2026-08-09
- Status: Read-only architecture investigation with accepted Artifact design follow-up
- Cloudflare OS revision reviewed: `1cb5e3d9096589e38f3fcfaf3f2191aa95a4c592`
- Cloudflare OS license: Apache-2.0

## Executive conclusion

OpenCompany should not adopt Cloudflare OS as its application runtime or reshape itself around
Cloudflare Workers. The products share an ambition -- safe, company-aware AI agents that can build
useful software -- but their centers of gravity differ:

| Cloudflare OS | OpenCompany |
|---|---|
| Personal, AI-generated applications and office-like workspaces | Persistent shared company with durable agents and business records |
| Gadget source code is the primary work product | Tasks, chat, documents, tables, files, automations, and agent work are primary |
| Per-workspace Durable Object and per-Gadget Dynamic Worker | Laravel application, PostgreSQL, queues, Reverb, Lua, and VFS |
| External access is introduced as narrow runtime capabilities | Workspace, integration, agent, tool, folder, and VFS permissions |
| Proposed Gadget code is accepted or reverted after an agent turn | Most approved mutations execute individually against live domain state |

The strongest reusable ideas are governance primitives, not infrastructure:

1. Harden the MCP boundary with endpoint safety, fixed limits, provenance-based trust, and
   conservative action classification.
2. Introduce specific external resources to a run, chat, automation, or artifact instead of making
   an entire connected account ambiently available.
3. Unify approval execution with OpenCompany's durable tool-action ledger.
4. Add proposed change sets for related internal mutations before attempting provider-specific
   simulation of external writes.
5. Make future Artifact templates copy code and binding requirements but never credentials,
   workspace state, or conversation history.
6. Register agent-created schedules disabled and require explicit activation.
7. Add observation provenance later if OpenCompany needs to guarantee that shared agent output does
   not reveal external data to people who lack access to its source.
8. Generate a permission-filtered Artifact endpoint catalog from the validated manifest so agents
   can discover typed contracts without creating a second endpoint runtime or one permanent tool per
   endpoint.

The first engineering priority should be MCP hardening. The current OpenCompany MCP client accepts a
workspace-configured URL, contacts it from the server, and does not visibly provide Cloudflare OS's
host blocklist, redirect-hop revalidation, bounded response machinery, or trust tiers.

## Accepted Artifact follow-up

The Artifact comparison produced four accepted additions to the canonical plan:

| Addition | OpenCompany decision |
|---|---|
| Binding requirements | Add portable named requirements to `artifact.json` and resolve them to concrete workspace-local resources through credential-free, revocable Artifact binding records. |
| Blueprint-like reuse | Call the product concept an **Artifact template**. Version source, tests, assets, and binding requirements; install into a fresh draft without copying authority, private data, schedules, or runtime history. |
| Atomic proposed changes | Stage file, manifest, and binding operations against one Artifact draft revision; preview and validate the whole diff, then apply it as one logically atomic draft update. Keep external side effects and arbitrary domain-data mutations outside this change set. |
| Agent-callable endpoint catalog | Generate a typed, permission-filtered catalog from the versioned manifest. HTTP routes and Automations remain invocation surfaces; the catalog is the discovery surface, and every call still uses the same endpoint runner. |

The concrete manifest shapes, records, flows, publishing gates, agent workflow, and implementation
backlog now live in
[automation-endpoints-artifacts.md](../planning/automation-endpoints-artifacts.md#binding-requirements-and-resolutions),
including the separate sections for
[Artifact templates](../planning/automation-endpoints-artifacts.md#artifact-templates-and-installation),
[atomic proposed changes](../planning/automation-endpoints-artifacts.md#atomic-proposed-changes), and the
[endpoint catalog](../planning/automation-endpoints-artifacts.md#agent-callable-endpoint-catalog).

## Verification scope

This investigation used:

- the local Cloudflare OS clone at `inspiration/cloudflare-os`;
- upstream source fixed to commit
  [`1cb5e3d`](https://github.com/cloudflare/cloudflare-os/tree/1cb5e3d9096589e38f3fcfaf3f2191aa95a4c592);
- the current local OpenCompany checkout;
- the sibling `../integrations/core` contracts where OpenCompany delegates integration runtime
  behavior.

No application was started, no test suite was run, no deployment was inspected, and no external
service was contacted. The OpenCompany checkout already contained substantial unrelated uncommitted
work. This investigation did not modify that work.

The current checkout is the source of truth for the comparisons below. Some older Artifact and VFS
planning documents say that VFS does not exist; that statement is stale because the current checkout
contains an implemented `app/Domain/Vfs` runtime and its focused tests.

## What Cloudflare OS is

Cloudflare OS describes itself as an AI productivity operating system originally developed for use
inside Cloudflare. It is not a machine operating system. The term covers two roles:

- a safe AI productivity environment for a company; and
- a runtime that schedules, isolates, connects, and governs AI workloads and generated applications.

It exposes three product surfaces:

1. An agent chat preloaded with company context.
2. Sandboxed, AI-built personal applications called **Gadgets**.
3. A capability-based integration and approval framework called **Gatekeepers**.

The current public repository is version 2, a rewrite described as early access with known rough
edges as of its August 2026 release. The upstream overview is in the
[Cloudflare OS README](https://github.com/cloudflare/cloudflare-os/blob/1cb5e3d9096589e38f3fcfaf3f2191aa95a4c592/README.md).

### Product model

Cloudflare OS treats a workspace more like an AI-native office document than a shared SaaS tenant.
A workspace may contain multiple Gadgets, and every Gadget can be a custom application. Users can
create thousands of these small applications and modify their code through the built-in agent.

The typical sharing unit is either:

- a live Gadget with shared code, state, chat, and collaboration; or
- a Blueprint containing reusable source code and connection requirements from which someone creates
  an independent instance.

This differs from OpenCompany's durable-team model. OpenCompany agents operate inside a shared
workspace of existing records and conversations. OpenCompany should therefore borrow Cloudflare OS's
controls while keeping its own domain model.

## How Cloudflare OS works

```text
Human request
  -> workspace Overseer
  -> coding agent chooses a Gadget or a direct task
  -> agent writes JavaScript for a Code Mode execution
  -> isolated Dynamic Worker with no ambient network
  -> explicitly named env bindings
  -> resource-specific Gatekeeper session
       -> authorize and record external reads
       -> submit side-effecting actions
       -> approve, reject, and sometimes revert
  -> Gadget files and bindings remain proposed until accepted
```

### Runtime mapping

Cloudflare OS explicitly uses an operating-system analogy:

| Operating-system concept | Cloudflare OS component |
|---|---|
| Kernel | `packages/workshop-backend` |
| Drivers | `packages/gatekeeper-*` |
| Shell | `packages/workshop-frontend` |
| Processes | Gadgets |
| Executables/templates | Blueprints |
| ACLs | Shared permissions |
| Special managed actors | AI agents |

The runtime uses Cloudflare Workers, Durable Objects, Dynamic Workers, Facets, Worker bindings, Yjs,
and Cap'n Web RPC. Every workspace is a Durable Object. Gadget and Gatekeeper implementations are
loaded as facets or Dynamic Workers associated with that workspace.

The architecture is tightly coupled to recent Workers runtime features. Although `workerd` is open
source, these components are not a portable library that can be inserted into a Laravel application.

### Overseer

The Overseer Durable Object is the workspace kernel. It owns or coordinates:

- chat histories and active-agent turns;
- agent restart/resumption after Durable Object restarts;
- Gadget creation, deletion, code, storage, and bindings;
- provisional Gadgets and per-chat proposed changes;
- Yjs document synchronization and merge/revert behavior;
- Gatekeeper facets and approval queues;
- hooks and persistent callbacks;
- Blueprint creation and instantiation;
- collaborators, share links, observer checks, and session revocation;
- output indexing and UI subscriptions.

The implementation is capable but very large: `overseer.ts` is approximately 9,500 lines and the
shared RPC API is approximately 2,900 lines at the reviewed revision. This concentration is a useful
warning for OpenCompany: adopt the records and invariants, not an equivalent all-owning service.

### Agent and Code Mode

The agent is a general-purpose agent that can build Gadgets or perform one-off work directly. Rather
than exposing every integration method as a traditional model tool, it uses Code Mode:

1. The model receives a small set of orchestration and discovery tools.
2. Named capabilities appear in an `env` object.
3. The model uses `describeBinding` to obtain the TypeScript interface for one capability.
4. The model writes a JavaScript snippet using those typed bindings.
5. The snippet runs in a Dynamic Worker with `globalOutbound: null`.

The agent cannot enumerate or guess binding interfaces. Progressive discovery avoids filling the
prompt with every integration schema. A bounded catalog tells it what resource categories are
available, and it asks for detailed interfaces only when needed.

Relevant source:

- [agent.ts](https://github.com/cloudflare/cloudflare-os/blob/1cb5e3d9096589e38f3fcfaf3f2191aa95a4c592/packages/workshop-backend/src/agent.ts)
- [overseer.ts](https://github.com/cloudflare/cloudflare-os/blob/1cb5e3d9096589e38f3fcfaf3f2191aa95a4c592/packages/workshop-backend/src/overseer.ts)

### Gadgets

A Gadget has client and server portions:

- Server code executes in a Dynamic Worker with no global outbound internet capability.
- Client code executes inside a sandboxed iframe with a restrictive content security policy.
- Client/server calls use Cap'n Web RPC.
- A Gadget sees only the bindings explicitly installed into its environment.
- The agent can call the Gadget's RPC API directly, making the application agent-operable without a
  separate MCP server.

The security property comes from capability confinement, not from trusting generated code. Generated
code can only reach objects placed in its environment.

### Gatekeepers

Gatekeepers are service-specific capability wrappers. The main conceptual layers are:

```text
GatekeeperVendor
  -> authenticate or provision an external account

GatekeeperUser
  -> represent one connected account without exposing its secrets

Gatekeeper<Session>
  -> represent the resource-specific facet installed in a workspace

Session
  -> expose the narrow API made available to agent or Gadget code
```

They own four important boundaries:

1. **Authentication:** OAuth and other provider credentials stay inside the Gatekeeper account.
2. **Resource scope:** The user introduces a specific resource, not merely a whole provider.
3. **Observation authorization:** Every external read calls `authorizeObservation()` before data is
   returned to the Gadget or agent.
4. **Action authorization:** Every side-effecting action is submitted to the central approval queue
   and can only be performed after `applyAction()`.

The contract is defined in
[gatekeeper.ts](https://github.com/cloudflare/cloudflare-os/blob/1cb5e3d9096589e38f3fcfaf3f2191aa95a4c592/packages/workshop-shared/src/gatekeeper.ts).

### Deferred actions and simulation

A Gatekeeper may maintain a staged representation of a requested write. It can tell the agent that
the proposed operation succeeded and make later reads reflect the staged value. This allows the agent
to finish a coherent sequence of work while the actual external actions wait for human review.

Each action description can declare:

- a human title and full description;
- a stable `actionKind` tag and display label;
- whether automatic revert is implemented;
- whether the agent should await the decision because simulation is unavailable;
- whether this particular action is eligible for a user-configured autoapproval rule.

This is not universal transactionality. Simulation is implemented separately by each Gatekeeper and
is difficult for external systems with rich state or irreversible actions. OpenCompany should begin
with proposed internal mutations, where it owns the storage and can provide reliable previews and
version guards.

### MCP security model

Cloudflare OS centralizes the security-sensitive parts of its MCP connectors in `mcp-shared` so two
connectors cannot drift. Its notable policies are:

- `tools.ts` is the only component allowed to interpret MCP annotations.
- User-supplied (`byo`) and deployment-vetted endpoints have different trust tiers.
- An unannotated tool is a write/action, requires approval, and cannot autoapply.
- A user-supplied server's claims cannot make a write autoapply.
- Fixed limits bound tool catalogs, descriptions, schemas, response bodies, retained results,
  pending actions, and redirect hops.
- User-supplied endpoints are checked against an address/host blocklist.
- Redirects are followed manually and every destination is revalidated.
- The same safe fetch layer is used during OAuth.
- Approved writes are claimed before dispatch and settled before handling the provider-controlled
  result payload.

The at-most-once policy distinguishes outcomes that are known to have been rejected before dispatch
from outcomes that may have taken effect. Only `401` and `403` are treated as proof of refusal.
Generic HTTP failures, JSON-RPC errors, connection loss, malformed replies, and oversized results are
treated as outcome-unknown and are not retried automatically.

See the
[MCP shared README](https://github.com/cloudflare/cloudflare-os/blob/1cb5e3d9096589e38f3fcfaf3f2191aa95a4c592/packages/mcp-shared/README.md).

### Proposed changes

Agent-created Gadget code, newly created Gadgets, and new Gadget bindings are provisional to a chat.
The chat log records a changes message, and the human can merge or revert a coherent group. This is
different from an action approval: proposed source and internal configuration can be safely inspected
as a branch before it becomes mainline state.

Yjs provides synchronized editing, compact snapshots, and history replay. OpenCompany does not need
Yjs to obtain the central benefit; durable change-set records and domain-owned preview/apply behavior
are sufficient for an initial implementation.

### Blueprints

A Blueprint is a reusable snapshot of a Gadget. It contains:

- committed source code;
- named binding requirements and configuration guidance;
- metadata and version information.

It explicitly excludes:

- live credentials and connections;
- Gadget SQLite contents;
- chat and edit history.

The recipient chooses their own resources when creating a new Gadget, and the new instance receives
independent state and bindings. Blueprints can be versioned, exported, imported, curated by an admin,
and promoted as standard output formats.

See
[blueprints.md](https://github.com/cloudflare/cloudflare-os/blob/1cb5e3d9096589e38f3fcfaf3f2191aa95a4c592/docs/blueprints.md).

### Sharing and observation authorization

Cloudflare OS separates sharing intent from authority to see externally sourced data. Non-owner
collaborators become observers. Gatekeepers can verify that each observer's own external account is
authorized to see everything already observed and can block future observations that would reveal
data to an unauthorized collaborator.

This includes a blunt `prohibitAllSharing` fallback for data that must remain owner-only. The more
granular observer mechanism requires every provider to define its atomic authorization unit -- for
example a GitHub repository, Notion page, Google spreadsheet, or Supabase project.

The mechanism is powerful but costly. OpenCompany should consider it only after resource bindings and
observation records exist. It is a later enterprise-governance feature, not an MVP requirement.

Relevant source:

- [sharing.md](https://github.com/cloudflare/cloudflare-os/blob/1cb5e3d9096589e38f3fcfaf3f2191aa95a4c592/docs/sharing.md)
- [observers.md](https://github.com/cloudflare/cloudflare-os/blob/1cb5e3d9096589e38f3fcfaf3f2191aa95a4c592/docs/observers.md)

### Scheduler and hooks

The Scheduler Gatekeeper supports interval, wall-clock, and one-shot callbacks. Its useful invariants
are:

- registration creates a disabled hook;
- activation is a separate explicit user action;
- wall-clock schedules require an explicit IANA timezone;
- every logical occurrence has a stable `runId` reused across retries;
- callback authorization is rechecked before every attempt;
- retries are bounded;
- missed occurrences are skipped instead of replayed in a catch-up storm;
- callback state is persisted before crossing an RPC boundary;
- batches and concurrency are bounded.

See the
[Scheduler README](https://github.com/cloudflare/cloudflare-os/blob/1cb5e3d9096589e38f3fcfaf3f2191aa95a4c592/packages/gatekeeper-scheduler/README.md).

## Current OpenCompany baseline

OpenCompany already implements several controls that should remain canonical.

### Durable run control plane

The current checkout documents and implements one logical runtime above Laravel's physical queue:

- `Task` and `AgentRunRecord` form a durable one-to-one work unit.
- Per-agent locks serialize work across chat, task, automation, and subagent adapters.
- Semantic retries reuse the same logical run.
- Cancellation, pause, heartbeats, and stale reconciliation are durable.
- `agent_tool_executions` claims calls before execution, stores ordered signatures, encrypts replay
  results, and blocks automatic repeats of ambiguous calls.
- Automation occurrences use UUID dispatch keys and are recoverable between durable claim and queue
  insertion.

See [agent-run-queue-control-plane.md](agent-run-queue-control-plane.md) and
[`IdempotentToolExecutor.php`](../../app/Domain/AgentRuntime/Application/IdempotentToolExecutor.php).

This is already a strong equivalent to Cloudflare OS's crash-aware action machinery for tool calls
performed inside an active run. It should be extended, not replaced.

### Permissions

[`OpenCompanyPermissionEvaluator`](../../app/Agents/Runtime/Permissions/OpenCompanyPermissionEvaluator.php)
enforces checks in a deliberate order:

1. workspace boundary;
2. integration enablement;
3. session grant;
4. tool permission;
5. approval requirement.

VFS composes backing-domain permissions and reports mutation capabilities before an agent acts. Read
visibility does not automatically grant mutation rights. Approved VFS replay revalidates permissions
before executing. See [the VFS runtime reference](../tools/vfs.md).

The missing concept is not another permission evaluator. It is a persisted resource binding that
narrows a connected account to a resource and makes that narrow capability available only to a
particular subject and lifespan.

### Lua and code-first integrations

OpenCompany already avoids presenting every package integration as a top-level model tool.
`ToolRegistry` exposes a small direct tool set while integrations are accessed through `lua_exec` and
the shared Lua bridge. Lua executes with memory and CPU limits and calls tools through the same agent
permissions.

Inside an active agent run, Lua bridge calls also use `IdempotentToolExecutor`. This is conceptually
close to Cloudflare OS Code Mode while retaining a smaller, app-owned and more portable sandbox.

OpenCompany should preserve this direction:

- Lua for deterministic integration and artifact logic;
- VFS for discoverable source/data operations;
- the app-owned permission evaluator for authority;
- a future resource-binding map as the environment of narrow external capabilities.

It should not execute arbitrary agent-generated JavaScript in the Laravel queue worker process.

### MCP

The current MCP flow is:

```text
McpServerController
  -> ManageMcpServers
  -> McpClient initialize / tools-list / tools-call
  -> McpToolProvider catalog
  -> McpProxyTool
  -> McpRuntime
  -> OpenCompanyPermissionEvaluator
  -> remote server
```

Positive properties:

- servers are workspace-scoped;
- URLs and auth configuration are stored with encrypted model casts;
- permissions use stable local tool slugs;
- execution passes through workspace/integration/tool policy;
- all MCP tools currently default to type `write`, which is conservative;
- a newly created server remains disabled unless initial discovery succeeds.

Material gaps found in the current checkout:

1. Controller validation checks URL syntax but not destination safety.
2. Creating or testing a server immediately makes a backend request to the supplied URL.
3. No app-level denial of loopback, private, link-local, metadata, or otherwise reserved addresses was
   found in the MCP path.
4. The client does not explicitly disable redirects or manually revalidate each hop.
5. No fixed MCP response-body, catalog-page, tool-count, description, or schema limits comparable to
   Cloudflare OS were found.
6. HTTP failure messages include the raw remote response body.
7. The resource boundary is the server/tool, not a provider resource such as a repository or
   document.
8. The current code does not distinguish user-supplied and deployment-vetted server provenance.
9. No policy interpretation of MCP read-only, destructive, or idempotent annotations is present.

Relevant files:

- [`McpServerController.php`](../../app/Http/Controllers/Api/McpServerController.php)
- [`ManageMcpServers.php`](../../app/Domain/Integrations/Application/ManageMcpServers.php)
- [`McpClient.php`](../../app/Services/Mcp/McpClient.php)
- [`McpToolProvider.php`](../../app/Services/Mcp/McpToolProvider.php)
- [`McpRuntime.php`](../../app/Services/Mcp/McpRuntime.php)
- [`McpPermissionEvaluator.php`](../../app/Services/Mcp/McpPermissionEvaluator.php)

These conclusions cover the application layer only. A deployment may have separate egress controls,
but none were verified in this investigation and infrastructure controls would not replace URL and
redirect validation in the app.

### Approvals

`ApprovalWrappedTool` creates a pending approval containing the exact tool slug and parameters. The
agent may wait for it or continue other work. `ApprovalExecutionService` atomically changes the
approval from pending to approved/rejected, rebinds workspace context, rechecks tool permission, and
executes an approved tool later.

This already resembles Cloudflare OS's asynchronous action queue, but there is a reliability gap:

- the conditional pending-to-decision update prevents concurrent duplicate button handling;
- after that decision, the tool is called directly outside the active-run `IdempotentToolExecutor`;
- there is no separate durable `claimed`, `dispatching`, `applied`, `failed`, or `outcome_unknown`
  lifecycle for the approved action;
- a crash after storing `approved` but before the call can lose the action;
- a crash after the provider call but before recording/displaying the result leaves no explicit
  action-outcome record;
- the raw `tool_execution_context` is redacted for array/API presentation but is not app-encrypted by
  its model cast.

Relevant files:

- [`ApprovalWrappedTool.php`](../../app/Agents/Tools/System/ApprovalWrappedTool.php)
- [`ApprovalExecutionService.php`](../../app/Services/ApprovalExecutionService.php)
- [`ApprovalRequest.php`](../../app/Models/ApprovalRequest.php)

### Automations

OpenCompany already has several Scheduler-quality properties:

- explicit timezone stored with every automation;
- stable UUID dispatch keys for logical occurrences;
- durable Task/AgentRun creation before physical enqueue;
- row locking when claiming due schedules;
- retry reuse of the same logical run;
- stale-run reconciliation;
- advancing from the current time, which skips missed schedules rather than replaying every missed
  occurrence;
- automatic disablement after repeated failures.

The main lifecycle difference is that
[`ManageAutomations::create()`](../../app/Domain/Automations/Application/ManageAutomations.php)
sets `is_active` to true. An agent-created schedule therefore becomes live immediately after creation.
Cloudflare OS's separate register/enable lifecycle is safer and provides a natural review point for
cadence, target, resources, and side effects.

### Artifacts

OpenCompany's Artifact planning already aligns strongly with Gadgets:

- first-class workspace Artifact domain;
- folder-first bundles under `/artifacts/{slug}`;
- manifest-declared pages, assets, endpoints, and permissions;
- restricted Liquid templates;
- Lua backend endpoints;
- VFS-native inspection and patching;
- sandboxed iframe rendering;
- versioned publishing, run logs, fixtures, and rollback;
- an extractable runtime boundary without prematurely creating a separate service.

See:

- [automation-endpoints-artifacts.md](../planning/automation-endpoints-artifacts.md)
- [standalone-artifact-service-investigation-2026-05-25.md](standalone-artifact-service-investigation-2026-05-25.md)

No first-class `Artifact` model, route, viewer, catalog, or artifact runtime was found in the current
checkout. The VFS now exists, so the Artifact plan should be refreshed when implementation starts.

## Recommended adaptations

### P0: Secure the MCP transport and trust boundary

Add one app-owned MCP transport policy used by connection tests, discovery, OAuth where applicable,
and tool execution.

It should own:

- permitted URL schemes;
- normalization and credential rejection in URLs;
- DNS resolution and denial of loopback, private, link-local, multicast, reserved, and cloud metadata
  destinations unless an explicit self-hosted administrator policy allows them;
- re-resolution and destination validation on every redirect;
- a small fixed redirect limit;
- connection, header, body, SSE-event, and total-time limits;
- bounded tool pagination, tool counts, descriptions, and JSON schemas;
- safe error normalization that does not include arbitrary remote bodies;
- consistent logging that excludes auth headers, tokens, prompts, and raw provider payloads.

Add a provenance/trust field to each MCP server:

```text
trust_tier
  byo       # user supplied; default
  vetted    # deployment administrator reviewed this endpoint and its annotations
```

Suggested classification:

| Server/tool metadata | Decision |
|---|---|
| No annotations | Action; approval required; never autoapply |
| BYO + `readOnlyHint: true` | Possible read classification, with the risk explicitly accepted by product policy |
| BYO write hints | Never sufficient for autoapproval |
| Vetted + non-destructive + idempotent hints | May be considered by an app-owned policy engine |

Keep interpretation in one class so UI, Lua catalogs, permissions, approvals, and execution cannot
drift.

### P1: Resource-scoped introductions

Add a capability record separate from the credential-bearing connection:

```text
resource_bindings
  id
  workspace_id
  subject_type          # agent_run, chat, agent, automation, artifact
  subject_id
  connection_type       # integration_setting, mcp_server, future connection
  connection_id
  resource_type         # repository, document, calendar, project, server tool set
  resource_locator      # canonical provider ID or validated resource URL
  allowed_operations    # read, create, update, delete, or provider-specific scopes
  granted_by_id
  granted_at
  expires_at
  revoked_at
  metadata
```

Security invariants:

- possession of a binding never crosses the workspace boundary;
- the binding can only reduce the connected account's authority;
- every execution rechecks that the connection, integration, agent, and binding remain enabled;
- revocation takes effect for future calls without rewriting historical run records;
- sensitive locators and configuration are not exposed in model prompts unless needed;
- a run/chat binding expires by default, while durable automation/artifact bindings require explicit
  user confirmation.

Agent flow:

```text
agent needs GitHub repository
  -> list bounded connectable resource types
  -> request repository introduction with reason and binding name
  -> human chooses account and exact repository
  -> create resource_binding
  -> resume run with env.REPOSITORY
  -> describe binding on demand
```

This can initially wrap OpenCompany package tools and MCP servers without adopting Cap'n Web. A PHP
capability object or Lua namespace can carry the same conceptual boundary.

### P1: Unify approvals with a durable action ledger

Either extend `approval_requests` or introduce an `agent_actions` record associated with an approval:

```text
agent_actions
  id
  workspace_id
  agent_run_id nullable
  approval_request_id nullable
  tool_slug
  action_kind
  reversible
  await_decision
  parameters_signature
  encrypted_execution_payload
  redacted_preview
  status               # proposed, approved, rejected, claimed, applied, failed, outcome_unknown
  claimed_at
  applied_at
  resolved_by_id
  error_summary
```

Required behavior:

1. Persist the proposed action and encrypted execution payload.
2. Atomically claim an approved action before contacting the provider.
3. Never release a claim for blind automatic replay after dispatch may have occurred.
4. Settle the fact that a provider call returned before parsing or storing a potentially malformed or
   oversized result.
5. Distinguish known pre-dispatch refusal from an unknown outcome.
6. Recheck workspace, integration, resource binding, agent, and tool permissions at apply time.
7. Keep a separate redacted human preview; do not derive the UI directly from raw parameters.
8. Use stable `action_kind` values for policy and human-readable labels for display.

OpenCompany's `AgentToolExecution` behavior should remain the core reference. The new action path
should share its concepts without forcing a delayed approval into a no-longer-active provider turn.

### P1: Add internal proposed change sets

Start with OpenCompany-owned mutations rather than simulating third-party state.

```text
change_sets
  id
  workspace_id
  agent_run_id
  chat_id
  status              # open, ready, accepted, rejected, conflicted, applied
  summary
  created_by_agent_id
  accepted_by_id
  accepted_at

change_operations
  id
  change_set_id
  sequence
  domain_type         # document, table_row, task, list_item, file, agent_config, artifact
  domain_id
  operation
  base_version
  patch
  redacted_preview
  apply_status
```

Initial scope:

- text document patches;
- table-row updates;
- task/list-item changes;
- VFS-backed file edits;
- future Artifact bundle files.

Applying a set should validate all base versions first. Domain services remain the mutation owners;
the change-set service coordinates preview, conflict detection, approval, and ordered application. It
must not become a second permission system or generic storage engine.

### P2: Blueprint-capable Artifacts

Extend the planned Artifact manifest with binding requirements:

```json
{
  "name": "Customer Health",
  "type": "app",
  "bindings": {
    "CRM": {
      "kind": "integration_resource",
      "provider": "hubspot",
      "resourceType": "portal",
      "operations": ["read"]
    },
    "REPORT_MODEL": {
      "kind": "ai_model",
      "capabilities": ["text", "structured_output"]
    }
  }
}
```

A Blueprint or Artifact template should capture:

- manifest and source files;
- immutable version/snapshot identity;
- binding shapes, friendly descriptions, and optional suggested resources;
- fixtures and tests;
- presentation metadata.

It must not capture:

- credentials or tokens;
- live `IntegrationSetting` or MCP account IDs;
- tables, files, or runtime state unless exported as explicit sanitized seed data;
- chat history or agent memory;
- active schedules or persistent callbacks.

Instantiation should create an independent Artifact draft, ask the user to satisfy each binding, run
fixtures under the reduced capabilities, and require a separate publish/enable decision.

The smallest useful demonstration remains one table-backed form, dashboard, and scheduled report.

### P2: Separate automation registration from activation

For automations created by an agent:

1. Persist the schedule as disabled.
2. Show cadence, IANA timezone, target agent/channel, required resource bindings, intended side
   effects, and retry policy.
3. Require explicit user activation.
4. Create durable resource bindings during activation.
5. Recheck admission before every attempt.
6. Reuse the current dispatch key for all physical retries of one occurrence.
7. Continue skipping missed occurrences rather than backfilling a storm.

Human-authored automations could retain an explicit `activate now` choice, but the API should not make
activation an invisible side effect of create.

### Later: Observation provenance and sharing enforcement

If OpenCompany needs the guarantee that a shared channel, document, or Artifact cannot expose data to
someone who lacks access to its external source, add an observation ledger:

```text
external_observations
  id
  workspace_id
  agent_run_id
  resource_binding_id
  provider_resource_type
  provider_resource_id_hash
  sensitivity
  observed_at

observation_outputs
  observation_id
  output_type          # message, document, artifact_version, task_result
  output_id
```

Sharing or adding a viewer would then evaluate the viewer against every relevant resource, or fail
closed to owner-only sharing where the provider has no reliable access oracle.

This is expensive because every integration must define resource granularity and a viewer-access
check. Build it only for providers and enterprise workflows that justify the cost.

## Suggested staged plan

### Phase 1: MCP and action safety

- Add a shared safe MCP endpoint/fetch policy.
- Add response, catalog, schema, redirect, and pending-action limits.
- Add `byo` and `vetted` trust tiers.
- Centralize MCP annotation interpretation.
- Remove raw response bodies from user-facing/logged exceptions.
- App-encrypt delayed execution payloads and create separate redacted previews.
- Give approved actions explicit claimed/applied/outcome-unknown state.

Deliverable: one MCP server can be connected and used without granting unsafe transport behavior or
ambiguous annotation-driven authority.

### Phase 2: Resource introductions

- Add `resource_bindings`.
- Add a bounded resource catalog contract to integration providers.
- Implement request/grant/deny/revoke UI and agent flow.
- Scope run/chat bindings by default and add explicit durable automation bindings.
- Start with GitHub repository and one document-style integration.

Deliverable: an agent can request and receive one specific external resource without ambient access
to the account.

### Phase 3: Proposed internal changes

- Add `change_sets` and ordered operations.
- Support document, table-row, task/list, and file patches.
- Add preview, conflict detection, accept, reject, and audit UI.
- Apply through existing domain services and permission checks.

Deliverable: an agent can prepare a coherent multi-record change for one human decision.

### Phase 4: Artifact Blueprint proof

- Refresh the Artifact plan against the now-implemented VFS.
- Implement the smallest Artifact domain inside OpenCompany.
- Add manifest binding requirements and independent template instantiation.
- Use a sandboxed iframe and Lua endpoint proxy.
- Prove one table-backed form/dashboard/report workflow.

Deliverable: a reusable agent-built internal application that is inspectable, testable, governed,
and independently bound to each workspace's resources.

### Phase 5: Provenance where demanded

- Record external observations and their output relationships.
- Add provider-specific viewer authorization checks.
- Add owner-only fallback for sources that cannot verify observers.
- Enforce on Artifact/document/channel sharing where the business requirement exists.

Deliverable: defensible cross-user sharing of outputs derived from restricted external data.

## What not to copy

### Do not replace the Laravel runtime with Workers

OpenCompany's queues, PostgreSQL records, permission evaluator, Lua sandbox, VFS, Reverb, and domain
services already fit its durable shared-company model. Replacing them with Durable Objects and Facets
would create a multi-year platform rewrite without solving the most immediate product or security
gaps.

### Do not recreate the Overseer as a god object

Keep ownership divided across existing bounded contexts:

- Agent Runtime owns logical runs and tool-call safety.
- Authorization owns permission decisions.
- Integrations own provider and tool contracts.
- MCP infrastructure owns protocol and transport.
- Approvals/Actions own delayed human decisions and action outcomes.
- VFS projects domain capabilities; it does not own domain data.
- Artifacts own manifests, source bundles, versions, rendering, and endpoint execution.
- Automations own schedules and occurrence lifecycle.

### Do not execute generated JavaScript in Laravel workers

Lua already provides a bounded, deterministic code-first surface. If a richer JavaScript Gadget
runtime becomes necessary, run it as a separate sandbox service behind OpenCompany auth, resource
bindings, and the tool/action gateway.

### Do not implement arbitrary external-state simulation first

Provider simulation must understand every read/write interaction and pending-state edge case. Start
with domain-owned proposed changes. Add external simulation only for a high-value integration where
the provider semantics are narrow, testable, and reversible.

### Do not trust MCP annotations by default

Annotations are server claims, not authority. User-supplied servers must not be able to mark a write
safe for autoexecution merely by setting `readOnlyHint`, `destructiveHint`, or `idempotentHint`.

### Do not make per-user copies the central OpenCompany model

Independent copies are useful for Artifact templates. They conflict with OpenCompany's primary value
as a shared company workspace with durable agents and shared operational records. Use Blueprint-like
copy semantics at the Artifact/template boundary only.

## Risks and unresolved decisions

1. **Resource grammar:** Each provider needs a stable canonical resource ID and supported operation
   set. URLs alone are insufficient for many APIs.
2. **MCP read classification:** Treating BYO `readOnlyHint` as a read improves usability but trusts a
   user-chosen server claim. OpenCompany must make that tradeoff explicit.
3. **Approval payload retention:** Encryption, retention, redaction, and deletion rules are required
   before storing exact delayed-execution inputs broadly.
4. **Change-set atomicity:** Cross-domain operations cannot always commit in one database transaction,
   so apply semantics need explicit partial-failure handling.
5. **Artifact sandbox:** Iframe isolation and endpoint proxying must prevent generated browser code
   from calling arbitrary OpenCompany APIs.
6. **Sharing provenance cost:** Observer verification can become a provider-by-provider authorization
   product. Do not promise it generically before supported providers are defined.
7. **Current checkout status:** Several runtime and VFS conclusions depend on uncommitted local code.
   Revalidate against the target branch before turning this report into an implementation plan.

## Final recommendation

Treat Cloudflare OS as a reference implementation for a capability-secure AI application environment,
not as an upstream platform for OpenCompany.

The practical sequence is:

```text
secure MCP transport and action execution
  -> introduce narrow external resources
  -> stage internal changes in reviewable batches
  -> instantiate Artifact templates with fresh bindings
  -> add observation-aware sharing only where customers require it
```

This preserves OpenCompany's strongest existing work -- durable agents, business-domain records,
workspace scoping, Lua, VFS, approvals, and the queue control plane -- while importing the parts of
Cloudflare OS that materially improve safety and product leverage.

# OpenCompany and Bowerbird Durable Automation Integration

Status: Accepted cross-product direction with proposed contracts; no Bowerbird connector or Ruby
Code Mode described here is implemented in this checkout.
Verified: 2026-08-31 against the active OpenCompany checkout and the sibling Bowerbird docs-first
repository.

## Executive decision

OpenCompany and Bowerbird should be tightly integrated while remaining complete, independently
deployable products.

- OpenCompany owns organizational work, members, business permissions, WorkItems, discussions,
  source authoring UX, business approvals and outcome accounting.
- Bowerbird owns durable workflow execution, Program Locks, triggers, schedules, waits, replay,
  provider dispatch, mutation receipts, reconciliation, credential leases and execution evidence.
- OpenCompany must retain useful standalone Code Mode and local automations when Bowerbird is not
  installed or connected.
- Bowerbird must retain its own authoring, review, connection, Run and operator surfaces when
  OpenCompany is not installed.
- The products share versioned integration contracts, normalized events, schemas, Ruby conventions,
  test fixtures and optional identity/connection projections. They do not share a database or
  silently share authority.

The integrated product experience is:

> OpenCompany is where an organization describes, reviews and observes work. Bowerbird is where
> admitted durable work executes.

## Current checkout truth

The active OpenCompany checkout currently has scheduled prompt and Lua script automations. The
current path uses:

- [`Automation`](../../app/Models/Automation.php) for definition, schedule and recent-result state;
- [`RunScriptAutomationJob`](../../app/Jobs/RunScriptAutomationJob.php) for queued uniqueness,
  timeout and workspace binding;
- [`ExecuteScriptAutomation`](../../app/Domain/Automations/Application/ExecuteScriptAutomation.php)
  for task creation, local sandbox execution, output publication and success/failure recording;
- `LuaSandboxService`, `LuaBridge` and the shared integration catalog for local Code Mode; and
- OpenCompany's own encrypted workspace connection settings and permission checks.

The current local runner intentionally avoids whole-script retry after an interrupted external
write because the provider may have completed it even when OpenCompany did not receive a result.
OpenCompany has useful logical agent-run controls, but it does not currently have Bowerbird's
proposed Effect journal, Program Lock, provider receipts, reconciliation or multi-day durable wait
model.

The future Ruby and Bowerbird behavior in this document is architecture, not current
implementation. Until it is built and qualified, current-state documentation and code remain the
implementation authority.

## Product modes

| Mode | OpenCompany | Bowerbird | Intended use |
|---|---|---|---|
| OpenCompany standalone | Local Code Mode, short scripts, schedules, integrations, local approvals and run history | Not installed | Teams wanting OpenCompany without an execution platform |
| Bowerbird standalone | Not installed | Complete durable workflow, CI/CD, factory, connection, review and operator product | Developers and platform teams using Bowerbird alone |
| Integrated self-hosted | Business UX, source/review collaboration and outcome projection | Durable execution and infrastructure authority | Organizations running the complete stack themselves |
| OpenCompany plus Bowerbird Cloud | Same business-facing authority | Managed control plane with managed, customer or hybrid workers | Teams wanting managed durability and scale |

Standalone means no required Bowerbird service, account, database or control plane for
OpenCompany, and no required OpenCompany service for Bowerbird. It is acceptable to reuse public
protocols, integration contracts, SDKs or a pinned hardened Ruby guest artifact.

## Goals

1. Give OpenCompany a production-grade optional backend for long-running business automations.
2. Preserve a useful, simple local execution path.
3. Let one integration package serve OpenCompany, Bowerbird, MCP and other hosts.
4. Let OpenCompany agents propose safe durable workflows without self-activating them.
5. Present Bowerbird reviews through OpenCompany's organization-aware collaboration UX.
6. Make every cross-product command and projection idempotent and revision-bound.
7. Allow either product to be installed, upgraded, backed up, exported and removed independently.
8. Use OpenCompany as Bowerbird's first serious business-automation customer and dogfood surface.

## Non-goals

- OpenCompany does not reimplement Bowerbird's durable scheduler, journal or reconciliation engine.
- Bowerbird does not become the authority for OpenCompany members, WorkItems or business records.
- The products do not share database tables, queues or vault master keys.
- Local Code Mode is not falsely advertised as replay-safe distributed workflow execution.
- Connecting the products does not copy every credential or grant every agent every capability.
- A local script is not silently converted into a Bowerbird Program.
- A human approval is not treated as proof that an external mutation succeeded.
- Callback delivery is not treated as authoritative Run state.
- The initial implementation does not require every existing automation to migrate.

## Authority matrix

| Record or decision | Authority | Other product's role |
|---|---|---|
| Workspace, member and organizational role | OpenCompany | Receives bounded actor assertions through a configured trust relationship |
| WorkItem, discussion and business outcome | OpenCompany | May mutate through an explicit versioned provider operation |
| Automation purpose, owner and desired business policy | OpenCompany | Receives immutable invocation inputs and references |
| Draft source review discussion | OpenCompany or Git, according to source mode | Compiles only an immutable submitted bundle |
| Candidate diagnostics and potential graph | Bowerbird | OpenCompany presents the projection |
| Program Lock and admitted deployment | Bowerbird | OpenCompany stores references and desired-state projection |
| Trigger cursor, dedupe and Run creation | Selected execution backend; Bowerbird in connected durable mode | OpenCompany configures business intent and displays state |
| Run, Step, Effect, Attempt and replay | Bowerbird | OpenCompany stores a thin ordered projection |
| Provider intent, receipt, ambiguity and reconciliation | Bowerbird | OpenCompany shows business-relevant evidence |
| Runtime review checkpoint | Bowerbird | OpenCompany presents it and authenticates organizational actors |
| Review discussion and notification | OpenCompany | Bowerbird stores the accepted structured response and frozen subject identity |
| Credential material | Credential authority selected for the connection | The other product stores a reference, never an implicit copy |
| Feature Tree product progress | Git in the Bowerbird repository | OpenCompany projects it and proposes reviewed Git changes |

## Target architecture

```text
OpenCompany
├── AutomationDefinition
├── AutomationRevision
├── AutomationDeployment projection
├── AutomationRunProjection
├── ReviewProjection
├── ConnectionProjection
├── local execution backend
└── Bowerbird execution backend adapter
          │
          │ versioned HTTPS/events contract
          ▼
Bowerbird
├── source admission and compiler
├── Program Lock and deployment
├── trigger ingress and scheduler
├── durable Run engine
├── provider/credential broker
├── review effect and signal admission
├── evidence store
└── ordered event and result APIs
          │
          ▼
Shared integrations ecosystem
├── language-neutral contracts
├── PHP backends
├── Ruby/Wasm/OCI/Rust provider components
├── normalized triggers
├── schemas, docs and fixtures
└── generated OpenCompany/Bowerbird/MCP projections
```

The boundary is API-driven even when both products run on one host. A same-host installation may
use a private network or local socket transport, but it retains the same authentication, revision,
idempotency and schema rules as a remote connection.

## OpenCompany execution-backend port

OpenCompany's Automations domain should depend on an application-level backend contract rather than
on Lua, Ruby, Laravel queues or Bowerbird directly.

Illustrative PHP shape:

```php
interface AutomationExecutionBackend
{
    public function capabilities(): BackendCapabilities;

    public function validate(
        AutomationSource $source,
        AutomationContext $context,
    ): ValidationResult;

    public function publish(
        AutomationRevision $revision,
        PublicationContext $context,
    ): PublishedAutomation;

    public function start(
        PublishedAutomation $automation,
        AutomationInvocation $invocation,
    ): ExecutionReference;

    public function cancel(
        ExecutionReference $execution,
        int $expectedRevision,
    ): CancellationResult;

    public function inspect(
        ExecutionReference $execution,
    ): ExecutionSnapshot;
}
```

Initial implementations:

```text
OpenCompanyLocalExecutionBackend
BowerbirdExecutionBackend
```

The domain contract should expose capability discovery rather than forcing both implementations to
the weakest common denominator. Examples include:

```text
supports_code_mode
supports_schedule
supports_webhook
supports_durable_wait
supports_runtime_review
supports_child_runs
supports_reconciliation
supports_replay
supports_remote_workers
maximum_runtime
maximum_input_bytes
maximum_output_bytes
```

The OpenCompany UI and authoring agent use this data to explain why an automation can run locally,
why Bowerbird is recommended, or why a requested capability requires a different backend.

## Standalone local execution

OpenCompany's local backend remains a first-class product feature. It should support:

- interactive Code Mode;
- short deterministic transformations;
- integration reads;
- simple external writes with honest unknown-delivery reporting;
- manual and cron invocations;
- short webhook handlers;
- agent-generated scripts;
- structured inputs and results;
- bounded logs and call traces;
- local connection settings and permission checks; and
- approval before starting a sensitive local script.

It does not need to implement:

- multi-day suspension;
- a durable effect journal;
- restart replay across named effects;
- child Runs;
- distributed placement;
- elastic worker pools;
- persistent development environments;
- generalized mutation reconciliation;
- CI/CD pipelines; or
- autonomous software factories.

Local execution should remain useful rather than intentionally frustrating users into an upsell.
Bowerbird is recommended when its correctness or scale properties materially matter.

## Shared Ruby engine and separate hosts

The long-term language direction is a restricted Ruby Code Mode aligned with Bowerbird Ruby. That
does not mean OpenCompany embeds Bowerbird's complete control plane.

A reusable low-level distribution may contain:

```text
bowerbird-ruby-engine
├── exact hardened mruby build
├── parser/compiler and source diagnostics
├── fresh isolated guest process
├── resource metering and limits
├── serialization protocol
├── prohibited-capability validation
└── signed/versioned artifact identity
```

Host behavior stays separate:

```text
OpenCompany local host
├── exposes bounded app.* calls
├── resolves workspace permissions
├── invokes local integration backends
├── records a local call trace
└── does not claim durable replay

Bowerbird authority host
├── exposes durable ctx.* calls
├── persists intent before dispatch
├── supplies journaled results during replay
├── owns receipts and reconciliation
└── binds execution to a Program Lock
```

OpenCompany may bundle the engine or sidecar and remain standalone. It should not load an unrelated
mruby engine inside PHP or maintain a divergent language profile without conformance tests.

The current Lua runtime remains current implementation truth until a Ruby hard cut is built and
qualified. Do not add Ruby as a permanent parallel scripting language. Migration should preserve
old source as disabled history, require explicit rewrite/validation and remove the old runtime only
after the complete release gate passes.

## Ruby profiles

### OpenCompany Code profile

`opencompany-code-v1` is synchronous, bounded and optimized for concise business operations:

```ruby
rows = app.integrations.nocodb.records.list(
  connection: :operations,
  table_id: "releases",
  limit: 20
)

latest = rows.records.max_by { |row| row[:released_at] }

app.integrations.slack.messages.post(
  connection: :engineering,
  channel: "#releases",
  text: "Latest release: #{latest[:version]}"
)

{
  version: latest[:version],
  records_checked: rows.records.length
}
```

The host performs authorization and provider calls. The script receives neither raw credentials nor
ambient network/filesystem/process access.

### Portable automation profile

`opencompany-portable-automation-v1` is a deliberately restricted subset that can run locally or
inside one Bowerbird execution step:

- bounded synchronous control flow;
- no waits, reviews or child workflows;
- no concurrency assumption;
- explicit maximum items/pages/bytes;
- typed input and output;
- preferably observation-only;
- at most one external mutation initially; and
- no automatic retry assumption.

Conversion from portable local execution to a durable Bowerbird workflow still creates a reviewed
new revision. Portability never silently changes effect guarantees.

### Bowerbird workflow profile

`bowerbird-ruby-v1` adds named durable effects, Program Lock admission, replay, waits, reviews,
child Runs, reconciliation and Bowerbird execution policies. OpenCompany edits and presents this
source, but Bowerbird compiles and runs it.

## Source, revision and deployment model

OpenCompany's current `Automation` row combines several concerns that should separate as the
integration grows.

### AutomationDefinition

Business identity:

- workspace;
- stable automation ID;
- name, purpose and description;
- owner/team;
- business policy;
- desired trigger class;
- selected execution backend;
- related projects, WorkItems and outcomes;
- active desired revision; and
- archive/retention state.

### AutomationRevision

Immutable authoring candidate:

- source bundle or Git source reference;
- source digest;
- runtime/profile;
- input and result schemas;
- declared connections and capabilities;
- author and creation reason;
- review discussion;
- local validation results;
- Bowerbird compilation diagnostics;
- potential graph reference;
- candidate Program Lock digest; and
- supersession lineage.

### AutomationDeployment

Published execution binding:

- backend identity;
- immutable source revision;
- Bowerbird project/program/deployment references when connected;
- admitted Program Lock digest;
- trigger and schedule bindings;
- connection references;
- publication approval;
- active/suspended/retired desired state;
- backend-observed state and revision; and
- last successful reconciliation time.

### AutomationRunProjection

Thin business-facing cache:

- Bowerbird Run reference or local execution reference;
- invocation identity;
- trigger summary;
- current status and named step;
- outstanding review count;
- business result summary;
- cost/usage summary where permitted;
- terminal result/evidence references;
- last contiguous event sequence; and
- projection health/staleness.

OpenCompany must not copy Bowerbird's complete Step/Effect/Attempt ledger and then treat it as a
second authority. Advanced inspection uses capability-scoped embedded views or a deep link.

## Authoring and publication lifecycle

```text
Draft
  -> immutable OpenCompany revision
  -> local validation
  -> Bowerbird candidate compilation
  -> diagnostics and potential graph
  -> capability/provider/schema/cost diff
  -> organizational review
  -> revision-bound publish request
  -> Bowerbird admission
  -> active deployment projection
```

Detailed sequence:

1. A human or agent edits an automation in OpenCompany.
2. Saving creates an immutable revision; it does not alter an active Program Lock.
3. OpenCompany performs syntax and profile checks through the selected backend.
4. For Bowerbird, it submits the complete bounded source closure and external references.
5. Bowerbird returns source-located diagnostics or a candidate containing Program Lock digest,
   potential graph, providers, schemas, capabilities, execution classes and cost/resource bounds.
6. OpenCompany renders readable source, graph and authority differences.
7. An authorized human approves the exact candidate digest and desired bindings.
8. OpenCompany sends a compare-and-set publication command.
9. Bowerbird independently validates its project policy, connections, secrets, resources and
   compatibility.
10. OpenCompany projects acceptance or structured denial; Bowerbird remains deployment authority.

An agent can propose and repair a candidate but cannot approve its own new authority or activate
itself.

## Invocation lifecycle

Every invocation has a stable identity:

```json
{
  "deployment_id": "deployment_01K_SYNTHETIC",
  "invocation_key": "opencompany:automation:auto_01K:scheduled:2026-09-01T09:00:00Z",
  "input": {
    "workspace_ref": "workspace_01K_SYNTHETIC",
    "automation_ref": "auto_01K_SYNTHETIC",
    "scheduled_at": "2026-09-01T09:00:00Z"
  },
  "caller": {
    "system": "opencompany",
    "workspace_id": "workspace_01K_SYNTHETIC"
  }
}
```

If OpenCompany's queue worker crashes after Bowerbird accepts the request, retrying the same
invocation key returns the original Run. A transport retry cannot create another logical Run.

The local backend follows the same OpenCompany invocation identity where practical, even though it
cannot provide full Bowerbird replay semantics.

## Triggers and schedules

OpenCompany can present business-friendly trigger configuration for both backends:

- manual;
- cron schedule and timezone;
- OpenCompany WorkItem/task/document/table event;
- GitHub/GitLab release or pull-request event;
- Slack interaction;
- NocoDB webhook or polling event;
- inbound API request;
- Bowerbird child/parent Run; and
- monitored external condition.

In Bowerbird mode, Bowerbird owns webhook verification, durable capture, cursor state, dedupe,
admission and Run creation. OpenCompany owns the business association and configuration UX.

For OpenCompany business events, an outbox publishes an immutable normalized event after the
authoritative database transaction. Bowerbird records the event delivery identity before creating
a Run. Replays use the same event/invocation key.

One selected ingress owner should register a provider webhook and fan out normalized internal
events. OpenCompany and Bowerbird must not accidentally register duplicate Slack, GitHub or NocoDB
subscriptions that create duplicate writes or billable delivery.

## Human review round trip

Bowerbird runtime review and OpenCompany organizational approval form one protocol without merging
their authorities.

```text
Bowerbird ctx.review
  -> frozen subject and review effect
  -> review.requested event
  -> OpenCompany ReviewProjection
  -> human discussion and decision UI
  -> OpenCompany actor/role validation
  -> signed revision-bound response
  -> Bowerbird state/capability/schema validation
  -> durable Run resume
```

The projected review contains:

- Bowerbird Run and review references;
- exact subject digest;
- review kind and allowed outcomes;
- semantic units and safe views;
- assigned OpenCompany role/team/user;
- response schema;
- expiry and current revision;
- changed/superseded state; and
- event cursor.

OpenCompany adds collaboration, reminders, comments and organization context without changing the
frozen subject. A direct edit creates a new automation/plan revision and review checkpoint.

Illustrative response:

```json
{
  "review_id": "review_01K_SYNTHETIC",
  "expected_revision": 3,
  "subject_digest": "sha256:synthetic",
  "decision": "edit",
  "value": {
    "edited_text": "Bowerbird v2.4.0 is now available."
  },
  "actor": {
    "system": "opencompany",
    "workspace_id": "workspace_01K_SYNTHETIC",
    "user_id": "user_01K_SYNTHETIC"
  },
  "idempotency_key": "opencompany:review:review_01K_SYNTHETIC:decision:1"
}
```

OpenCompany verifies current workspace membership and business permission. Bowerbird independently
verifies trusted caller, Run/review revision, subject currency, capability, nonce, expiry and
response schema. Callback failure leaves the review waiting; it never implies approval or rejection.

## Shared integration ecosystem

The separate integrations repository should become the common contract and conformance source for
both products.

```text
integrations/
├── contracts/
│   └── nocodb/
│       ├── integration.toml
│       ├── operations/
│       ├── triggers/
│       ├── connections/
│       ├── schemas/
│       └── fixtures/
├── packages/
│   └── nocodb/              # current PHP implementation
├── backends/
│   ├── wasm/
│   ├── rust/
│   └── oci/
├── bindings/
│   ├── opencompany-ruby/
│   ├── bowerbird-ruby/
│   ├── typescript/
│   └── mcp/
├── docs/
└── conformance/
```

This can be introduced gradually. Existing PHP packages remain where they are while
`contracts/nocodb` becomes the stable source for new cross-host behavior.

Every operation contract includes:

- stable integration and operation ID;
- contract version;
- effect class;
- input, result and error schemas;
- connection/auth requirements;
- capability and resource scope;
- pagination and rate-limit behavior;
- redaction rules;
- idempotency material where applicable;
- provider receipt projection;
- reconciliation operation;
- ambiguity behavior;
- normalized documentation; and
- shared fake/live conformance fixtures.

The shared integrations repository should use Apache-2.0 for its contracts, schemas, provider kits,
generated bindings, examples, fixtures, and independent out-of-process backends. This lets
OpenCompany, Bowerbird, third-party applications, and community providers share one ecosystem
without making either product a runtime dependency of the other. OpenCompany application code and
Bowerbird core code remain separately released AGPL-3.0-only Programs; consuming the same
permissive contract does not merge them into one portfolio-wide Program. Any backend that embeds,
links, or copies covered application/core implementation needs explicit license-compatibility
review, and private Cloud code cannot be the sole implementation of a self-hosting requirement.

## NocoDB pilot

NocoDB is the recommended first vertical slice because the integrations repository already has a
useful PHP provider with record, batch, base, table, view and count operations, while the shared
contract still needs richer durable mutation and trigger semantics.

The pilot should cover:

1. list/get observations with paging;
2. create/update/delete mutations;
3. idempotent create using an external business identity;
4. a non-idempotent/ambiguous failure case;
5. partial batch success;
6. reconciliation by external ID or exact record observation;
7. webhook normalization;
8. polling cursor and dedupe;
9. connection test and credential redaction;
10. rate limiting and retry hints;
11. schema drift;
12. PHP and Bowerbird provider conformance against identical fixtures.

Illustrative contract:

```toml
id = "nocodb.records.create"
version = 1
effect = "mutation"

input_schema = "schemas/record-create-input.v1.json"
output_schema = "schemas/record-create-output.v1.json"
receipt_schema = "schemas/record-create-receipt.v1.json"

capability = "nocodb.records.write"
idempotency_fields = ["connection", "table_id", "external_id"]
reconcile_operation = "nocodb.records.find_by_external_id"
```

The same contract generates the local `app.integrations.nocodb.records.create` projection and the
Bowerbird `Providers::NocoDB::Records::CreateV1` handle.

## Connection and credential modes

### OpenCompany standalone

OpenCompany stores encrypted workspace credentials and invokes its PHP integration backend through
the current credential resolver and permission boundary.

### Bowerbird standalone

Bowerbird stores immutable secret versions in its vault and issues short-lived, purpose-scoped
credential leases to provider execution.

### Integrated

OpenCompany preferably stores:

- connection display name;
- provider/contract identity;
- Bowerbird project and `ConnectionRef`;
- allowed OpenCompany teams/automations;
- status and last verified time; and
- safe configuration metadata.

Raw credential material remains in Bowerbird. Connecting the products does not grant all
OpenCompany agents access. Every deployment binds an exact connection, operation set, resource
scope and capability.

Moving an existing local connection to Bowerbird is an explicit reviewed operation. Safe
configuration may copy, but secrets require reauthorization or an audited one-time transfer. Local
automations remain local until individually migrated.

## Full durable release example

This example receives a release, requests review, posts to Slack and records a NocoDB row. It is
illustrative Bowerbird Ruby; none of these providers or runtime methods is implemented here.

```ruby
module OpenCompanyAutomations
  class PublishRelease < Bowerbird::Workflow
    entrypoint "workflow/publish-release"
    profile :workflow

    input Schemas::ReleasePublishedInput
    outcomes :published, :rejected, :provider_ambiguous
    policy Project::Policies::ReleaseCommunications

    def perform(ctx)
      release = ctx.observe(
        :load_release,
        Providers::GitHub::Releases::GetV1,
        {
          connection: Project::Connections::GitHubMain,
          repository: ctx.input.repository,
          release_id: ctx.input.release_id
        },
        returns: Schemas::GitHubRelease
      )

      announcement = ctx.execute(
        :draft_announcement,
        Bowerbird::Executors::Command,
        tool: Project::Scripts::DraftReleaseAnnouncement,
        input: { release: release.value },
        returns: Schemas::ReleaseAnnouncement
      )

      review = ctx.review(
        :announcement_review,
        Bowerbird::Reviews::Human,
        display_name: "Review release announcement",
        kind: :publication_authorization,
        subject: announcement.ref,
        subject_integrity: announcement.digest,
        views: [:announcement, :release_notes],
        outcomes: [:approve, :edit, :reject],
        assignment: Project::Roles::ReleaseManager,
        returns: Schemas::AnnouncementReview
      )

      return ctx.complete(:rejected) if review.value.decision == "reject"

      text = review.value.decision == "edit" \
        ? review.value.edited_text \
        : announcement.value.text

      slack = ctx.mutate(
        :publish_slack_announcement,
        Providers::Slack::Messages::PostV1,
        {
          connection: Project::Connections::SlackEngineering,
          channel: "#releases",
          text: text
        },
        capability: Project::Capabilities::ReleaseAnnouncement,
        key: ctx.key("release-slack", release.value.id),
        reconcile: Providers::Slack::Messages::FindByMetadataV1,
        unresolved: :return,
        returns: Schemas::SlackMessageReceipt
      )

      return ctx.complete(:provider_ambiguous) if slack.ambiguous?

      row = ctx.mutate(
        :record_nocodb_release,
        Providers::NocoDB::Records::CreateV1,
        {
          connection: Project::Connections::NocoDBOperations,
          table_id: "releases",
          external_id: release.value.id,
          fields: {
            version: release.value.tag,
            notes: text,
            slack_message_id: slack.receipt.message_id
          }
        },
        capability: Project::Capabilities::NocoDBReleaseWrite,
        key: ctx.key("nocodb-release", release.value.id),
        reconcile: Providers::NocoDB::Records::FindByExternalIdV1,
        unresolved: :return,
        returns: Schemas::NocoDBRecordReceipt
      )

      return ctx.complete(:provider_ambiguous) if row.ambiguous?

      work_item = ctx.mutate(
        :record_opencompany_result,
        Providers::OpenCompany::WorkItems::RecordReleaseV1,
        {
          connection: Project::Connections::OpenCompany,
          work_item_id: ctx.input.work_item_id,
          release: release.value.tag,
          slack_receipt: slack.receipt,
          nocodb_receipt: row.receipt
        },
        capability: Project::Capabilities::OpenCompanyWorkItemUpdate,
        key: ctx.key("release-work-item", ctx.input.work_item_id),
        reconcile: Providers::OpenCompany::WorkItems::ObserveReleaseV1,
        unresolved: :return,
        returns: Schemas::OpenCompanyWorkItemReceipt
      )

      return ctx.complete(:provider_ambiguous) if work_item.ambiguous?

      ctx.complete(
        :published,
        {
          slack: slack.receipt,
          nocodb: row.receipt,
          work_item: work_item.receipt
        }
      )
    end
  end
end
```

If Bowerbird loses a worker after Slack succeeds, replay supplies the journaled Slack receipt and
does not post twice. If NocoDB accepted the create but its response was lost, Bowerbird reconciles by
the external release identity before deciding whether another call is safe. OpenCompany receives a
business result projection; it does not infer success from a Slack or webhook callback.

## OpenCompany agent authoring experience

An OpenCompany agent can become a high-quality Bowerbird automation author without receiving
deployment authority:

1. Inspect permission-visible shared integration contracts.
2. Ask the human for outcome, constraints, trigger and review policy.
3. Draft a local or durable Ruby candidate.
4. Request compilation from the selected execution backend.
5. Repair source-located diagnostics.
6. Render named steps and potential graph.
7. Present capability, connection, cost and external-mutation differences.
8. Add tests and synthetic fixtures.
9. Request an independent human publication decision.
10. Submit the exact approved digest.

Example proposal card:

```text
When a production release is published:
  1. generate release notes;
  2. request release-manager approval;
  3. post to Slack #releases;
  4. create one NocoDB release row;
  5. update the related OpenCompany WorkItem.

New authority:
  READ  GitHub releases in bowerbirdhq/bowerbird
  WRITE Slack messages in #releases
  WRITE NocoDB records in table releases
  WRITE OpenCompany release fields on one WorkItem

Execution backend: Bowerbird
Maximum wait: 2 days

[View Ruby] [View graph] [View tests] [Request changes] [Approve publication]
```

## OpenCompany UI

OpenCompany owns business-facing pages:

- automation library;
- create/edit assistant and Ruby editor;
- local versus Bowerbird execution choice;
- trigger and schedule builder;
- connection binding;
- source revisions and diffs;
- potential graph;
- capability/cost diff;
- publication review;
- deployment projection;
- business-focused run history;
- unified review inbox;
- related WorkItems/discussions; and
- terminal business outcomes.

Bowerbird owns operator pages:

- Program Lock and source closure;
- full Run graph;
- Steps, Effects and Attempts;
- worker placement;
- credential grants;
- provider requests and receipts;
- reconciliation and ambiguity;
- replay and cancellation;
- evidence and artifacts; and
- runtime diagnostics.

OpenCompany may embed capability-scoped Bowerbird read components or deep-link to them. It should
not create an independently mutable copy of the Run inspector.

### Backend selection UX

```text
Execution

● Local OpenCompany
  Self-contained and fast
  Suitable for short scripts and integrations
  Maximum runtime: configured local limit

○ Bowerbird
  Durable and resumable
  Supports waits, reviews, reconciliation, agents and long-running workflows
  Recommended for production automation
```

Static analysis can warn:

```text
This script performs three external writes.

Local execution cannot guarantee safe recovery if execution stops between them.
Bowerbird can journal each operation and reconcile an unknown provider result.

[Keep local] [Convert to durable workflow]
```

Keeping local is allowed unless the requested construct is unsupported. Conversion creates a new
reviewable source revision.

## Cross-product API surface

The initial connector should remain narrow and versioned:

- pair/unpair an OpenCompany workspace and Bowerbird project;
- inspect product, protocol and provider capabilities;
- compile/admit an immutable source candidate;
- publish, suspend or retire a revision-bound deployment;
- create or reconcile a trigger binding;
- start, signal, cancel and inspect a Run;
- read ordered events after a cursor;
- fetch result/evidence references;
- submit a revision-bound review response; and
- inspect safe connection metadata.

Potential resource families:

```text
/v1/calling-applications
/v1/program-candidates
/v1/deployments
/v1/trigger-bindings
/v1/runs
/v1/runs/{run}/events
/v1/runs/{run}/result
/v1/reviews/{review}/responses
/v1/connections/{connection}/capabilities
```

These paths are design placeholders, not frozen APIs.

## Ordered events and projection repair

Every event includes:

- stable event ID;
- Run/deployment identity;
- monotonic per-stream sequence;
- schema version;
- occurred and recorded timestamps;
- event kind;
- immutable subject references;
- safe redacted payload; and
- trace/correlation identity.

OpenCompany applies events idempotently and persists the last contiguous sequence. If callbacks are
duplicated, delayed, missing or reordered, it repairs from the ordered event API. It never marks a
Run complete merely because one callback arrived.

Useful events include:

```text
deployment.admitted
deployment.denied
deployment.suspended
run.started
run.step_changed
run.review_requested
run.review_resolved
run.waiting
run.cancel_requested
run.completed
run.failed
run.provider_ambiguous
run.quarantined
```

OpenCompany notifications are projections of these events, not the execution authority.

## Identity and authorization

Pairing establishes:

- OpenCompany workspace to Bowerbird project mapping;
- OpenCompany application/workload identity;
- Bowerbird callback identity;
- trusted signing keys or workload federation;
- allowed actor-assertion forms;
- role-to-capability mapping policy;
- review delegation policy;
- connection visibility;
- redaction/retention agreement; and
- event cursor and health state.

No permanent organization-owner token should be used for ordinary execution. Commands use narrow,
short-lived credentials or signed workload identity. A compromised OpenCompany application identity
cannot mint Bowerbird capabilities; a compromised Bowerbird worker cannot modify OpenCompany
membership.

OpenCompany authenticates its people and evaluates current organization membership. Bowerbird
validates that the configured trust relationship permits the asserted actor to answer the exact
review or issue the exact command. Both checks are required.

## Failure behavior

| Failure | Required behavior |
|---|---|
| Bowerbird unavailable during submission | OpenCompany records a pending/failed submission, retains the invocation key and retries without duplicating a Run |
| OpenCompany unavailable during a Run | Bowerbird continues admitted work; human reviews wait and callbacks queue/retry |
| Callback delivery fails | Bowerbird result remains authoritative and retrievable; OpenCompany repairs projection from cursor |
| Duplicate invocation | Bowerbird returns the existing logical Run |
| Duplicate review response | Same decision receipt returns; conflicting response is rejected |
| Review subject changes | Previous response cannot advance the new subject; OpenCompany shows changed/superseded state |
| External mutation response lost | Bowerbird records ambiguity and reconciles before redispatch |
| OpenCompany local write response lost | Local trace reports unknown delivery and suppresses unsafe whole-script retry |
| Connection revoked | New effects are denied; in-flight policy determines reconcile-only, wait, cancel or quarantine |
| Products disconnected | Future calls are revoked; retained Runs and business records keep stable references and audit state |
| Event gap detected | OpenCompany pauses projection advance and requests missing ordered events |
| Schema/protocol mismatch | Admission fails with explicit compatibility diagnostics; no silent field dropping |

## Backup, export and removal

OpenCompany backup/export contains its definitions, revisions, discussions, business records,
projections and cross-product references. It does not need Bowerbird's secret material or raw private
evidence.

Bowerbird backup/export contains Programs, Program Locks, deployments, Runs, receipts, evidence,
connections and calling-application references. It does not need OpenCompany's full member,
discussion or business database.

Unpairing requires a reviewed plan for:

- active deployments;
- in-flight Runs;
- waiting reviews;
- trigger ownership;
- callback revocation;
- connection projections;
- retained evidence links; and
- automation fallback to local, suspension or retirement.

Removing either product cannot silently delete the other's authoritative history.

## Deployment topologies

### OpenCompany only

```text
OpenCompany web/queue
  -> local restricted Code Mode guest
  -> PHP integration backends
  -> OpenCompany database and credential store
```

### Bowerbird only

```text
Bowerbird control plane
  -> Bowerbird Ruby guest
  -> provider components/workers
  -> Bowerbird durable state, vault and evidence
```

### Same-host integrated

```text
OpenCompany services
  -> private authenticated Bowerbird API
Bowerbird services
  -> signed callback/event endpoint
Separate databases and credential boundaries
```

### Remote or Cloud integrated

```text
OpenCompany
  -> authenticated Bowerbird Cloud control plane
  -> managed or customer workers
  -> signed ordered callbacks to OpenCompany
```

### Offline degradation

OpenCompany local Code Mode remains available when a configured Bowerbird connection is unhealthy,
but existing Bowerbird deployments are not silently re-executed locally. Users may explicitly clone
a compatible revision into a new local automation after reviewing lost capabilities and changed
semantics.

## Security requirements

- Separate tenant/workspace/project identities with explicit mapping.
- No shared database credentials or vault master keys.
- No raw credentials in Ruby source, Program Locks, callbacks, logs or projections.
- Exact destination and redirect policy for provider network calls.
- Signed, replay-protected callbacks and commands.
- Compare-and-set deployment, review, cancellation and steering commands.
- Bounded payload, source, result, log and event sizes.
- Redaction before evidence crosses the product boundary.
- Explicit retention and residency policy for each cross-product reference.
- Subject digest and response schema on every runtime review.
- Capability and connection difference review before publication.
- No agent self-approval or capability minting.
- No automatic fallback across trust boundaries when capacity is unavailable.
- Poison tests for duplicate events, false completion, secret leakage and cross-workspace references.

## Migration from current OpenCompany automations

Migration is incremental and opt-in.

### Phase 0: contracts and truth

1. Preserve the current Lua implementation as current-state truth.
2. Freeze the execution-backend interface and capability model.
3. Freeze the shared integration operation/event contract using NocoDB.
4. Decide the OpenCompany Ruby profile and exact guest packaging before changing source persistence.

### Phase 1: local backend seam

1. Wrap existing local validation, publication, invocation and inspection behind the backend port.
2. Preserve behavior and tests.
3. Add stable invocation identities and structured unknown-write diagnostics where possible.
4. Make UI backend-aware without exposing Bowerbird yet.

### Phase 2: Bowerbird read-only connection

1. Pair one synthetic workspace/project.
2. Compile one effect-free candidate.
3. Start one manual synthetic Run.
4. Project ordered state and terminal result.
5. Prove disconnect/reconnect and cursor repair.

### Phase 3: first durable automation

1. Add one schedule or OpenCompany business-event trigger.
2. Add one provider observation.
3. Add one idempotent mutation with receipt.
4. Inject crashes before/after dispatch and receipt persistence.
5. Prove no duplicate semantic mutation.

### Phase 4: human review

1. Project one `ctx.review` into OpenCompany.
2. Reauthorize actor and submit an idempotent response.
3. Test stale subject, expired review, revoked role, duplicate and conflicting responses.
4. Resume the Run and project the terminal result.

### Phase 5: authoring and deployment UX

1. Add Ruby editor, diagnostics and graph.
2. Add capability/connection/cost diff.
3. Add immutable revisions and publication review.
4. Add deployment projection and rollback/retirement.
5. Let agents propose but never self-publish.

### Phase 6: shared integrations and connections

1. Qualify NocoDB across PHP and Bowerbird backends.
2. Add normalized webhook and polling events.
3. Add explicit local-to-Bowerbird connection migration.
4. Add marketplace installation projections.

### Phase 7: OpenCompany Ruby hard cut

Only after the shared Ruby engine and local host are qualified:

1. generate/rewrite current local examples and docs;
2. migrate persisted scripts through explicit disabled-history handling;
3. test every package projection and local automation path;
4. update deployment artifacts and health diagnostics;
5. ship one scripting runtime, not Lua plus Ruby indefinitely; and
6. retain rollback through the prior application release, not a hidden dual runtime.

This phase can occur independently of requiring a Bowerbird service.

## Test and qualification matrix

### OpenCompany standalone

- Starts and passes local Code Mode tests with no reachable Bowerbird endpoint.
- Local schedules, manual runs, integrations, permission denial and approval-before-start work.
- Unknown local write delivery never triggers unsafe whole-script retry.
- Backup/restore retains local definitions and runs.

### Bowerbird standalone

- Starts and passes workflow/provider/review tests with no OpenCompany endpoint.
- Bowerbird native review UI can resolve supported review contracts.
- Trigger, receipt, reconciliation, replay and result retrieval remain complete.

### Connected

- Pair/unpair and key rotation.
- Candidate compilation and source diagnostics.
- Capability diff and revision-bound publication.
- Idempotent invocation.
- Ordered events, duplicates, gaps and reconnect repair.
- Review request, actor reauthorization, response and resume.
- Cancellation and steering with expected revisions.
- Provider ambiguity and reconciliation.
- Callback failure with independent result retrieval.
- Workspace/project/actor isolation.
- Secret and private-evidence redaction.
- Independent backup, restore, upgrade and downgrade.

### Shared integration conformance

- Identical operation IDs and schema versions.
- PHP and Bowerbird result projections agree on domain meaning.
- Effect classifications cannot be weakened by a host.
- Mutation identity and reconcile operation are testable.
- Trigger normalization and dedupe are host-independent.
- Disposable live smoke tests use synthetic accounts/data.

## Product and commercial opportunity

One provider pack can produce value in several surfaces:

- OpenCompany local agent tools and Code Mode;
- OpenCompany durable automations through Bowerbird;
- Bowerbird standalone workflows and factories;
- MCP/API consumers;
- managed OAuth and connection hosting;
- hosted triggers and provider components;
- private enterprise integrations; and
- marketplace distribution and revenue share.

OpenCompany supplies business demand, context and collaboration. Bowerbird supplies durable
execution, compute and evidence. The shared integration ecosystem makes each product more valuable
without forcing customers to adopt both.

## Success measures

- A new integration contract generates useful projections for both products.
- OpenCompany installs and runs with no Bowerbird configuration.
- Bowerbird installs and runs with no OpenCompany configuration.
- Pairing takes minutes and creates no shared-database dependency.
- A local automation can be deliberately converted with an understandable semantic/capability diff.
- An OpenCompany user can review a Bowerbird checkpoint without losing subject integrity.
- Duplicate submissions, callbacks and responses do not duplicate logical work.
- A crash after an external write produces a receipt or explicit ambiguity, never false success.
- NocoDB passes shared PHP/Bowerbird conformance.
- Disconnecting either product leaves the other operational and historically coherent.

## Open decisions before implementation

1. Exact package/repository ownership of the shared Ruby engine and guest artifact.
2. Whether the first OpenCompany Ruby release exposes only local Code Mode or also the portable
   automation profile.
3. Exact application/workload identity and actor assertion protocol.
4. Whether same-host deployments use HTTPS, Unix sockets or both behind one transport abstraction.
5. Initial ordered-event transport: polling first, signed callbacks plus repair, or both.
6. Which OpenCompany approval records remain general business approvals versus Bowerbird review
   projections.
7. Source authority for simple UI-created automations versus Git-backed advanced programs.
8. Initial Bowerbird UI embedding mechanism and redaction boundary.
9. Connection migration UX and whether any provider permits secure one-time secret transfer.
10. Contract ownership and compatibility policy for the integrations repository.

These decisions change implementation shape but not the central product boundary: OpenCompany and
Bowerbird remain independently useful, and connected mode uses OpenCompany for organizational UX
over Bowerbird's durable execution authority.

## Cross-repository authority

The corresponding Bowerbird-side decision lives under the `OpenCompany` section of
`docs/INTEGRATIONS.md` in the Bowerbird repository. The two documents intentionally describe the
same boundary from different product perspectives. Changes to identity, authority, Program Lock,
review, connection or failure semantics must update both in one reviewed change.

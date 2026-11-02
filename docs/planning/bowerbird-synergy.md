# OpenCompany × Bowerbird: Synergy and Cross-Sell

Status: **Accepted** (owner, 2026-11-02). Nothing here is implemented. Binding summary: [Product Direction](../PRODUCT_DIRECTION.md), "Relationship to Bowerbird".
**Aya is discontinued** (owner, 2026-11-02). The portfolio is Bowerbird plus OpenCompany. Bowerbird
docs that still mention Aya are outdated.

Evidence: a read-only review on 2026-11-02 of the Bowerbird engine (`~/Projects/openfactory`), Bowerbird
Cloud (`~/Projects/bwb-cloud`), Roost (`~/Projects/roost`) and `bowerbird-ruby-engine`. Key sources:

- `openfactory/docs/planning/strategy/portfolio-organization-and-integration.md` (portfolio decision,
  2026-08-28);
- `openfactory/docs/planning/designs/integrations/integrations.md`;
- `openfactory/docs/planning/designs/operator-surfaces/cli-and-mcp.md`;
- `bwb-cloud/docs/planning/cloud-plan.md`;
- `bwb-cloud` decisions 0009 (pricing), 0011 (agent sandboxes before the engine) and 0012 (`.si`
  domains);
- `bwb-cloud/docs/planning/designs/runtime/agent-sandboxes.md`.

Direction: [Product Direction](../PRODUCT_DIRECTION.md), "Relationship to Bowerbird" and pillars 2, 9,
10 and 11.

## 1. Where Bowerbird is heading

| Product | What it is | Status (2026-11-02) | Milestone |
|---|---|---|---|
| **bwb engine** | Durable execution and CI/CD "software factory": mruby workflows, vault and broker, evidence, receipts | v0.1 Self-CI merged but not accepted; roadmap runs v0.2 to v1.0 | Engine phases |
| **Cloud Runners** | Hosted GitHub Actions runners, about 5× cheaper than GitHub, prepaid wallet, per-second billing | Site, pricing, docs and console on fixtures; no servers yet | C1 pilot, then C2/C3 |
| **Bowerbird Cache** | Remote build cache for any CI | Planned | C2 |
| **Agent sandboxes** | Persistent Firecracker microVMs (stop, resume, fork, snapshot) behind the public **Workspace API `v1alpha`**, with an agent-first `bwb` client | Planned; moved ahead of the engine | **S1**, after the C1 gate |
| **Hosted engine and Cloud Compute** | Engine cells, then managed workers and GPU, macOS and Arm pools | Planned | C4, C5 |
| **Roost** | Neutral price guide for CI runners and agent sandboxes, "Run by Bowerbird" | Live | Its own milestones |
| **bowerbird-ruby-engine** | Apache-2.0 mruby guest; OpenCompany Code Mode uses it | `0.1.0-rc.1` | Undefined |

The portfolio decision (2026-08-28) makes Bowerbird the master brand under Ginger Media BV:

- **Bowerbird:** execution, CI/CD, environments, credentials, evidence and managed compute.
- **OpenCompany:** "the organizational system of record".
- **Aya:** the supervision UI. *(Discontinued 2026-11-02. Its review and supervision role is covered by
  OpenCompany's Home, approval cards and messenger.)*

Both products share the licensing model: AGPL-3.0-only core, Apache-2.0 for SDKs, protocols and
clients, and DCO for contributions.

## 2. Tensions to resolve

The Bowerbird docs predate OpenCompany's 2026-11-02 locked direction.

| # | Tension | Proposed resolution |
|---|---|---|
| 1 | **Automation ownership.** Bowerbird docs say Bowerbird owns triggers, durable workflows and credential leases for OpenCompany. OpenCompany now owns events and routines natively | **Split by workload.** OpenCompany owns *business* automation: routines, events, approvals, agent work. Bowerbird owns *software delivery and heavy durable pipelines*: CI/CD, builds, long reproducible runs with evidence. Connect them through a Bowerbird connection and an optional "pipeline routine" executor backend. Neither depends on the other |
| 2 | **"OpenCompany replaces Linear"** as portfolio planning, with a Feature Tree importer and initiative/cycle domain | **Defer.** Bowerbird keeps Linear until OpenCompany Issues ships (Phase 4). Then dogfood Issues for Bowerbird work. Don't build a Feature Tree importer now |
| 3 | **Shared contract-first `integrations` repo** with PHP backends (Bowerbird plan) | **Obsolete.** OpenCompany deleted the package system. Shared integration happens through MCP, API manifests and deep connectors |
| 4 | **Two hardened mruby builds:** `bwb-ruby-sys` in the engine and `bowerbird-ruby-engine`, which only OpenCompany consumes | **One engine.** Bowerbird owns the mruby guest (one crate family, Apache-2.0). OpenCompany owns its host bindings and permissions. Every engine release must also pass OpenCompany's Code Mode evals. Publish a shared Ruby dialect spec so scripts are portable (section 3.2) |
| 5 | **Identity and billing are unassigned** across Cloud products | **Separate until OpenCompany Phase 5.** Then offer an optional "Bowerbird account" (one sign-in) and let OpenCompany Cloud draw compute from the **Bowerbird wallet** (section 4). Seats and plans stay with OpenCompany |
| 6 | **Shared CLI crates have no home.** `oc` plans to share them with `bwb`, but Bowerbird docs don't mention `oc` | Shared crates live in Bowerbird's public repository under Apache-2.0 (the client track, BWB-770+), published to crates.io. `oc` depends on them. Bowerbird's docs name `oc` as their second consumer |
| 7 | **`v1alpha` lacks browser and screen**, which OpenCompany needs for computers | OpenCompany, as design partner, proposes `browser`, `screen` and `desktop_stream` as an optional capability namespace in `v1alpha`. Providers advertise support |
| 8 | **Both products depend on boat.** OpenCompany uses it for computers; Bowerbird runs its own CI on it | OpenCompany ships on boat first and never waits. It switches its default managed computers to Bowerbird S1 when S1 passes the agent-experience gate. OpenCompany's CI moves to Bowerbird runners when C1 opens |
| 9 | **GitHub org and legal entity disagree:** `BowerbirdHQ` vs `bowerbird-si` vs `OpenCompanyApp`; Ginger Media BV vs Delaware/Dubai plans | **Owner decision needed** (open question below) |

## 3. Technical synergy, in order of value and timing

### 3.1 Computers: OpenCompany as the Workspace API's reference consumer

- OpenCompany's `ComputerProvider` Bowerbird driver implements `v1alpha` directly, and OpenCompany
  runs the `v1alpha` conformance and agent-experience suites (BWB-771/777) as Bowerbird's first
  design partner.
- Real agent workloads from OpenCompany (idle-heavy, stop and resume, fork) give S1 its first demand,
  telemetry and price-card validation (BWB-219).
- **Hosted OpenCompany Cloud defaults to Bowerbird sandboxes** once S1 is live. boat remains a
  provider.

### 3.2 One Ruby: from routine to pipeline

- OpenCompany's script and hybrid routines and Bowerbird's workflows both run restricted mruby on one
  engine and one documented dialect.
- **Graduation path.** A script routine that becomes heavy (long-running, needing reproducibility
  and evidence) can be promoted to a Bowerbird workflow with the same Ruby. OpenCompany keeps the
  trigger, approvals and human context. Bowerbird runs it and returns events, receipts and evidence.

### 3.3 Bowerbird as a deep connection in OpenCompany

- **Events in:** CI failed, deploy waiting for review, run finished, budget near limit. These become
  OpenCompany events, so routines like "when CI fails on main, an agent triages and opens an Issue"
  work out of the box.
- **Actions out:** re-run, cancel, approve or reject a deployment review, open a sandbox.
- **Bowerbird reviews** (`run.review`) appear as approval cards on OpenCompany Home, on mobile and on
  Telegram. OpenCompany becomes Bowerbird's human approval surface, matching the portfolio rule that
  OpenCompany owns business approvals.
- **Usage records** (`bowerbird.usage-record.v1`) feed OpenCompany budgets and audit. Bowerbird agent
  tokens with spend limits (BWB-766) back OpenCompany's per-agent compute budgets.

### 3.4 Shared agent-first tooling

- The shared crates cover contexts, device-flow login, keychain storage, structured errors (`hint` and
  `next`), schema and guide generation, and the agent-experience eval harness. `bwb` and `oc` stay
  consistent: an agent that learns one learns both.
- **Pairing for coding agents:** `oc mcp serve` gives work context (issues, docs, approvals), and
  `bwb` gives execution (sandboxes, CI). Each tool's agent guide (`guide agents`) cross-references the
  other.

### 3.5 Roost inside OpenCompany

- Admin → Computers can show live, dated provider prices from Roost's public JSON (boat, E2B,
  Daytona, Bowerbird) when someone picks a computer provider. This keeps Roost's neutrality policy:
  Bowerbird is listed only where it sells, and losing comparisons are shown.

### 3.6 Data sovereignty alignment

- Bowerbird's own archive and backup story should mirror OpenCompany's pillar 11: an open archive,
  backups to customer-owned storage, and no phone-home. That gives the portfolio one sovereignty
  promise, "your data is yours, across Bowerbird".

## 4. Cross-sell and bundles

### OpenCompany → Bowerbird

1. **Managed computers.** Every "give this agent a computer" moment in OpenCompany offers "Managed
   computer, powered by Bowerbird". This is the default on OpenCompany Cloud after S1, billed per
   second from the wallet with stopped time free.
2. **Dev teams in OpenCompany.** When an OpenCompany workspace connects GitHub, suggest the Bowerbird
   connection. CI triage by agents then leads naturally to "run this on Bowerbird Runners, about 5×
   cheaper than GitHub" and Bowerbird Cache.
3. **Heavy routines.** "This routine needs more than Code Mode": promote it to a Bowerbird pipeline.

### Bowerbird → OpenCompany

1. **The review inbox and teammates.** Bowerbird customers get deployment reviews, CI-failure triage
   and run summaries in OpenCompany with an AI ops teammate. A free OpenCompany workspace is linked
   from Bowerbird's console ("Review and supervise in OpenCompany").
2. **Agent sandbox customers.** Teams running agents on Bowerbird sandboxes are the ideal OpenCompany
   buyers: they already run agents and need governance, approvals and a team surface.
3. **Funnel.** Bowerbird's go-to-market (Show HN, Product Hunt, AI-answer visibility, open-source
   programme) and its Founding Builder Pass include an OpenCompany Cloud trial. Roost stays neutral
   and does not promote OpenCompany.

### Bundles and money

- **Phase 5, one Bowerbird account and wallet (optional).**
  - Compute for OpenCompany agents (sandboxes) and CI (runners, cache) draws from the same prepaid
    wallet.
  - OpenCompany seats and plans are billed by OpenCompany Cloud.
  - Partner-bundle credits (BWB-191/192) can include OpenCompany Cloud months, and OpenCompany plans
    can include Bowerbird compute credit.
- **Self-hosting stays independent.** A self-hosted OpenCompany works with no Bowerbird account, and
  with the self-hosted bwb engine or local Docker as computer providers.

### Brand

- Use **"OpenCompany, by Bowerbird"** as an endorsement once Bowerbird has a public presence
  (bowerbird.si). Keep OpenCompany's own domain and identity, since it is the end-user product.

## 5. Dogfooding loop

- The Bowerbird team uses OpenCompany: the messenger, agent teammates for CI triage and release
  notes, and later Issues for planning.
- OpenCompany's CI runs on Bowerbird Runners from C1. OpenCompany's agents use Bowerbird sandboxes
  from S1.
- Each product's release gates include the other: `v1alpha` conformance by the OpenCompany driver,
  and the Code Mode evals in engine releases.

## 6. Sequencing with a single builder

Order work so that each product's next milestone strengthens the other's, and nothing blocks.

1. **Now (OpenCompany Phase 0–1, Bowerbird C1):** shared CLI crates (both need them now), one mruby
   engine with a dialect spec, and an OpenCompany CI target on Bowerbird runners when C1 opens.
2. **OpenCompany Phase 2 alongside Bowerbird S1:** the `ComputerProvider` Bowerbird driver, with
   OpenCompany as the S1 design partner; and the `v1alpha` browser and screen capability proposal.
3. **OpenCompany Phase 3–4:** the Bowerbird deep connection (events, reviews, usage); pipeline
   routine graduation once the bwb automation plane (v0.8) exists; Bowerbird planning moves into
   OpenCompany Issues.
4. **OpenCompany Phase 5 alongside Bowerbird C4/C5:** the optional Bowerbird account and shared
   wallet, bundles, and Cloud computers defaulting to Bowerbird.

## Follow-up outside this repository (not done; needs owner go-ahead)

Update the Bowerbird docs to match:

- the portfolio doc and `integrations.md` (the automation-ownership split; planning deferred;
  integrations repo obsolete);
- the client track (shared crates with `oc` as second consumer);
- `v1alpha` (browser and screen capability proposal);
- the Cloud plan (OpenCompany as S1 design partner and the wallet bundle);
- engine ownership (one mruby engine).

## Open questions

- Which GitHub org and legal entity hold OpenCompany: `OpenCompanyApp`, `BowerbirdHQ`/`bowerbird-si`,
  Ginger Media BV, or a new structure?
- Whether the shared wallet is in euros or dollars (Bowerbird BWB-168 is open), and VAT treatment
  for cross-product bundles.

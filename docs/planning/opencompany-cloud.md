# OpenCompany Cloud

Status: Design — planned (Phase 5). No implementation claims.
Verified: 2026-11-02 against docs/PRODUCT_DIRECTION.md; current-state statements checked against docs/CURRENT_STATE.md.
Owner doc: [Product Direction](../PRODUCT_DIRECTION.md).

OpenCompany Cloud is the hosted, managed version of OpenCompany with managed computers
([pillar 8](../PRODUCT_DIRECTION.md#8-open-source-first-hosted-second), Phase 5). This document sets
the product and licensing boundaries for it. It does not contain pricing, cost models or commercial
strategy; those are decided separately and are open questions here.

Today there is no hosted Cloud: [Current State](../CURRENT_STATE.md) lists managed multi-region
cloud, SLAs, SSO/SAML, SCIM and compliance reports as not current, and OpenCompany is deployed only as
a self-hosted Laravel application.

Related designs:

- [Computer Provider Contract](../architecture/computer-provider-contract.md): the provider drivers
  Cloud uses for managed computers, and the computerless mode Cloud must also support.
- [Governance Model](../architecture/governance-model.md): budgets, metering, audit and the
  credential broker that Cloud metering and billing read from.
- [Agent Primitives Catalog](agent-primitives-catalog.md): vault, secure forms, password manager
  connectors, agent inbox and payments, which Cloud offers exactly as self-host does.
- [API-first client split](../architecture/api-first-client-split.md) (pillar 4, API-first now): Cloud
  serves the same versioned API and realtime stream to web, mobile and desktop clients.
- [Connections, Events and Routines](connections-events-routines.md) (pillar 9): connections, the
  OAuth broker, the workspace event log that metering reads, and the Phase 5 outward-facing work
  (OpenCompany as an MCP server, MCP Apps, A2A).
- [Messenger UI](messenger-ui.md) and [Agent-native work apps](agent-native-work-apps.md): the product
  surfaces Cloud hosts unchanged.

## What Cloud is

- **Managed OpenCompany.** The same AGPL application, operated for customers: workspaces, agents,
  messenger, Tables, Issues, Docs, VFS, mruby Code Mode, connections, routines, Telegram, approvals,
  budgets and audit, with upgrades, backups and monitoring handled by the operator.
- **Managed computers.** Agents can get a computer without the customer running sandbox
  infrastructure. Cloud starts with [boat](https://boat.dev) and moves to Bowerbird agent sandboxes
  when they exist (after Bowerbird's S1 milestone), through the same `ComputerProvider` drivers that
  self-hosters use.
- **Computer optional on Cloud too.** VFS-only agents are first class on Cloud. A Cloud workspace with
  no computers is a complete product, and per-agent computer access follows the same governance
  policy as self-host.

## Licensing boundary

The complete self-hosted application is AGPL-3.0-only. Cloud is a way of operating it, not a
different product.

> **Rule.** Only Cloud operations and billing may be proprietary. They never fork, patch, copy or
> replace functionality that the complete self-hosted application needs. Any capability a customer
> uses inside the product is in the AGPL application, available to self-hosters.

| May be proprietary (Cloud operations) | Must be in the AGPL application |
|---|---|
| Fleet provisioning, deployment pipelines, infrastructure config | Every product feature, including computers, takeover, vault, secure forms, budgets and audit |
| Billing, invoicing, payment processing, plan enforcement glue | The `ComputerProvider` contract and every provider driver Cloud uses (local, boat, E2B, Daytona, Bowerbird, connected hosts) |
| Abuse detection, fraud and capacity controls at the operator edge | Metering: the workspace event log and budgets that billing reads |
| — | Connections, the generic OAuth broker, vault and credential broker |
| — | OpenCompany as an MCP server, MCP Apps cards and A2A Agent Cards (Phase 5 features, shipped to self-host too) |
| — | The add-on apps platform: the app manifest runtime, first-party apps (Mail, Calendar, CRM and later ones), third-party manifest review and install, change sets and Home |
| Support and operator tooling | Workspace scoping, roles, approvals, credential broker, VFS policy |
| Signup and marketing site | Export and import of workspace data |

Consequences:

- Cloud runs the same application build as a self-hosted installation, configured for hosting. No
  Cloud-only fork, private patch set or private replacement of an app feature.
- AGPL network-use obligations apply to Cloud: users of Cloud can obtain the corresponding source of
  the application they use.
- Contributions remain inbound equals outbound under DCO. Nothing in Cloud depends on proprietary
  relicensing of contributions.
- A feature first built for Cloud lands in the AGPL application first and is enabled on Cloud by
  configuration.

## Packaging boundary

Pricing and packaging are open (see [open questions](#open-questions)). One rule is fixed by
[pillar 5](../PRODUCT_DIRECTION.md#5-company-governance-is-the-moat-one-engine-three-presets-gated-by-reversibility):
**security and governance are never paywalled.**

- All three governance presets (Personal, Team, Company) are available on every Cloud plan and in
  self-host.
- Every always-on invariant and security feature is included everywhere: workspace isolation, vault
  and credential broker, the hard-deny floor, untrusted-content labelling, trigger-is-not-consent,
  pinned MCP definitions, egress allowlists, signed webhooks, rate limits, the event and audit log,
  on-behalf-of access, approvals, two-approver policies, budgets, kill switches, and the security of
  published artifacts and forms.
- Cloud plans may differ by operated resources (for example managed compute, managed models, storage
  or support), not by whether a workspace may govern its agents safely.

## Self-host parity

Self-hosting stays a first-class, complete option. Parity means:

- **Same features.** Everything a Cloud workspace can do, a self-hosted workspace can do with its own
  infrastructure and accounts (for example its own boat account or local Docker).
- **Same contracts.** Same API, realtime stream, provider contract, governance model and data formats.
- **Same apps.** The add-on apps platform ([pillar 7](../PRODUCT_DIRECTION.md#7-core-primitives-add-on-apps-and-no-mess))
  is part of the AGPL application. Every first-party app available on Cloud installs on self-host from
  the same manifest, and third-party manifests install the same way on both. Cloud has no private app
  catalog or Cloud-only app.
- **Portability both ways.** A workspace can be exported from Cloud and imported into a self-hosted
  installation, and the reverse, including agents, identity files, memory, tasks, tables, docs, files,
  routines and audit history, as a `.ocarchive` workspace archive. Secrets are excluded by default;
  only an owner may include them, passphrase-encrypted. See [exit guarantees](#data-sovereignty-and-exit-guarantees).
- **No Cloud-only lock-in primitives.** Agent inbox addresses, managed computers and managed models
  are conveniences on Cloud; self-host offers the same primitive with its own mail domain, provider
  and model keys.
- **Parity is checked.** A parity list is maintained alongside releases; any Cloud capability without
  a self-host path is a release blocker.

## Data sovereignty and exit guarantees

Cloud keeps the promise "your workspace is yours"
([pillar 11](../PRODUCT_DIRECTION.md#11-full-data-sovereignty-hosted-included);
[Data sovereignty](data-sovereignty.md#5-hosted-cloud-guarantees)). These are Phase 5 commitments for
Cloud, not current features.

- **No proprietary formats.** Cloud runs the same AGPL code, so customer data is never stored in a
  Cloud-only format and the `.ocarchive` export is complete on Cloud.
- **Free, unlimited export and backups on every plan.** Object downloads, full workspace exports and
  scheduled backups to customer-owned storage are never metered, capped or paywalled.
- **Backups Cloud cannot read.** Backups to customer storage are encrypted with a key the customer
  holds; Cloud does not hold that key.
- **Read-only window after lapse.** When a subscription lapses or is cancelled, the workspace stays
  read-only and exportable for **at least 90 days** before deletion, with warnings to owners.
- **One-command exit.** `oc admin import --from cloud` migrates a Cloud workspace to a self-hosted
  installation.
- **Region choice.** Customers choose the region for their workspace, EU first.
- **Verified deletion.** Deleting a workspace purges primary data and schedules removal from provider
  backups within a documented window, and the owner receives a deletion receipt.
- **Customer-managed keys** (BYOK/KMS for encryption at rest) come later.
- **Model routing.** Cloud honours the "send nothing to third-party models" workspace setting and
  shows the provider for each run, as on self-host.

## Tenancy

High level only; topology choices are open.

- **Workspace is the tenant boundary**, as in the application today (`workspace_id`, `ResolveWorkspace`,
  `forWorkspace()`). Cloud depends on Phase 0 closing every known cross-workspace flaw and on the
  Phase 3 authorization work; it does not add a second isolation model in proprietary code.
- **Computers are isolated per agent and per workspace** by the provider contract. No computer, disk,
  snapshot or browser profile is reused across workspaces or customers.
- **Shared versus dedicated deployments** are an operator choice. If dedicated deployments are offered,
  they run the same build with different configuration.
- **Noisy neighbours.** The current single physical queue gives no workload-class latency isolation
  ([Agent Run Queue Control Plane](../architecture/agent-run-queue-control-plane.md#intentional-limitation)).
  Hosting many workspaces requires deciding how queues, per-workspace concurrency and budgets protect
  tenants from each other before pilots.

## Models: BYOK and managed

- **Bring your own key.** A Cloud workspace can connect its own provider keys (and Codex OAuth where
  supported), exactly as on self-host. The provider/model catalog stays app-owned; Cloud does not hard
  code providers.
- **Managed models.** Cloud may also offer models through operator-held keys. Usage is metered per
  workspace and agent as usage events in the workspace event log, which budgets read.
- Both can coexist in one workspace; every model call is attributed to the key source in the audit.

## Managed computers

- **boat first, with a partner agreement.** boat is the first hosted partner. Its public terms
  restrict sharing, reselling, brokering or providing third-party access to its compute, so managed
  boat computers on Cloud need a partner agreement (Product Direction, Phase 5). boat also has no idle
  timer; OpenCompany owns idle stop. Until such an agreement exists, Cloud customers
  can still attach their own boat account through the same driver, as self-hosters do.
- **Bowerbird by default after S1.** Once Bowerbird's S1 sandboxes pass their gate, Cloud computers
  default to Bowerbird sandboxes, offered as "Managed computer, powered by Bowerbird". boat remains a
  provider. OpenCompany never waits on Bowerbird; boat and local providers ship
  first.
- **Trust boundary disclosure.** Every managed provider is disclosed to the customer, with region,
  as a party that processes the computer's files and outputs. Cloud never falls back silently to a
  different provider for capacity.
- **Customer-provided providers.** A Cloud workspace may attach its own provider instance (boat,
  E2B, Daytona or Bowerbird) instead of a managed one.
- **Connected hosts on Cloud are outbound only.** Customers connect their own machines through the
  outbound `oc host` daemon. Hosted Cloud **never joins a customer's tailnet** and does not dial into
  customer networks; SSH-only hosts over a tailnet are a self-host option (a self-hosted server can
  run a Tailscale sidecar). Hosts stay labelled self-managed on Cloud.
- **Cloud contexts in `oc`** (Phase 5): the `oc` CLI gains Cloud contexts alongside self-hosted ones,
  using the same public API, device-flow login and token types. Windows host support is also Phase 5.
- **Shared team computer** on Cloud is an open question (below).

## Bowerbird account and wallet (Phase 5, optional)

From [OpenCompany × Bowerbird](bowerbird-synergy.md#bundles-and-money):

- **One optional Bowerbird account and wallet.** A Cloud customer may link one Bowerbird account;
  compute for OpenCompany agents and for CI then draws from the prepaid Bowerbird wallet.
- **Cloud bills its own seats and plans.** The wallet covers compute only; OpenCompany Cloud's own
  seats and plans are billed by Cloud.
- **Partner credits** can be bundled both ways.
- **Self-host never requires Bowerbird.** Self-hosted OpenCompany never needs a Bowerbird account,
  wallet or provider; Bowerbird is one provider among several.
- **Brand.** "OpenCompany, by Bowerbird" is an endorsement; OpenCompany keeps its own identity and
  domain.
- **Cross-sell.** Bowerbird's Founding Builder Pass and launch channels include an OpenCompany Cloud
  trial; Bowerbird's console offers "Review and supervise in OpenCompany"; OpenCompany offers managed
  Bowerbird computers and, through the Bowerbird connection, Runners and Cache.

## Compute pass-through and metering

- **One source.** Compute (running seconds, snapshot storage, exposure time), model usage and spend
  are usage events in the workspace event log with causality chains, and budgets read them
  ([Governance Model, budgets](../architecture/governance-model.md#budgets)). Cloud billing reads the
  same events; it does not keep a second, divergent count.
- **Budgets first.** Budgets that pause work at the limit protect Cloud customers from runaway cost
  in the same way as self-hosters. Cloud plan limits are enforced through the same mechanism.
- **Pass-through visibility.** Customers see compute and model usage per agent, per run and per
  computer in the product, with the provider named, so charges are explainable from receipts.
- **Self-host** sees the same spend reports and is never billed.

## Connections on Cloud

- **Same model as self-host.** Deep connectors (Drive, OneDrive/SharePoint, mailboxes, calendars),
  MCP, API manifests, skills and plugins work identically. Connection secrets live in the workspace
  vault behind the credential broker.
- **OAuth registration.** The generic OAuth broker registers clients in the order CIMD, then DCR,
  then admin-entered client credentials. On Cloud the operator may provide registered clients for
  providers that require them (for example Google, Slack) as configuration; a self-hoster enters
  their own. Neither path is proprietary functionality.
- **Managed connectors are optional.** Activepieces (MIT) can run as a self-hosted sidecar; Composio,
  Pipedream Connect or Zapier MCP attach with the customer's own key. All attach as MCP servers.
  Cloud never makes any of them a hard dependency, and never resells a third party's connector
  service without the customer's own account. Whether Cloud runs an Activepieces sidecar is open.

## Outward-facing interoperability (Phase 5)

Phase 5 adds, in the AGPL application and therefore on self-host as well
([Connections, Events and Routines, phasing](connections-events-routines.md#phasing)):

- **OpenCompany as an MCP server,** so external agents and clients can file issues, request
  approvals and read team docs under the same policy and audit.
- **MCP Apps** rendered as messenger cards.
- **A2A Agent Cards** per durable agent.

On Cloud these are hosted endpoints of the customer's workspace, authenticated with scoped tokens.

## Published surfaces on Cloud

Artifacts and forms ([Artifacts and Forms](artifacts-and-forms.md)) are part of the AGPL application;
Cloud operates them.

- **User-content domain.** Cloud serves interactive artifacts and public form endpoints from a
  separate user-content origin (ideally one subdomain per artifact) that never carries session
  cookies, with a default-deny CSP. Self-hosters get the same isolation by setting `ARTIFACT_ORIGIN`;
  without it a reduced-isolation fallback is shown in Admin.
- **Custom domains** for published artifacts and forms are Phase 5. Adding one is `exposure_widening`.
- **Abuse handling.** Rate limits, captcha, report links, pre-publish scans, the per-workspace
  published-surfaces registry and kill switch are product features in every deployment. On Cloud the
  operator additionally runs instance-level takedown and abuse response for reports against public
  surfaces; that operator process is a Cloud operation, but the takedown mechanism is in the AGPL app.
  Public publishing stays off per workspace by default on Cloud as on self-host.
- **MCP Apps export** (Phase 5): OpenCompany's MCP server exposes interactive artifacts as `ui://`
  resources so they render in Claude and ChatGPT with the user's own OpenCompany access.
- **Never paywalled.** The security of published surfaces (origin isolation, CSP, visitor principals,
  published-query bounds, captcha, rate limits, registry and kill switch) is included on every plan.

## Secrets on Cloud

- The vault, secure forms, password manager connectors and credential broker behave exactly as on
  self-host ([Agent Primitives Catalog](agent-primitives-catalog.md)). Whether Cloud uses an external
  KMS for vault keys is a catalog open question.
- Operator staff have no routine access to decrypted secrets; break-glass access, if any, is audited.
- Browser logins captured through takeover stay in the agent's browser profile on its computer, which
  belongs to the customer's workspace.

## Prerequisites before paying pilots

- Phase 0 exit: no known cross-workspace flaws, tests in CI, one license, honest public claims.
- Phase 2 computers with the credential broker, egress allowlist and idle stop working on the chosen
  managed provider.
- Phase 3 budgets that pause work, and audit and replay over the event log, so Cloud can
  answer "who allowed what, at what cost" for its customers.
- The API-first split far enough that the hosted web client and Telegram run on the public API.
- A partner agreement for managed boat computers, or managed computers deferred to Bowerbird.
- AGPL source availability for the deployed version.

## Phase 5 acceptance criteria

Phase 5 exit: *Paying pilot workspaces on hosted Cloud; self-host parity maintained.*

1. Pilot workspaces run on Cloud using the same application build as self-host, with no Cloud-only
   fork or patch set.
2. Agents on Cloud can have managed computers from a disclosed provider, and VFS-only agents work
   without one.
3. Compute and model usage bill from the same event-log usage the product shows to the customer.
4. A Cloud workspace exports to a self-hosted installation and keeps working there.
5. The parity list shows no Cloud feature without a self-host path.
6. Users can obtain the corresponding AGPL source for the deployed version.
7. Export and backups are free and unlimited on every plan; a lapsed workspace stays read-only and
   exportable for at least 90 days; `oc admin import --from cloud` succeeds; deletion produces a
   receipt.

## Non-goals

- No claims of compliance certifications, SSO/SAML, SCIM, data residency guarantees or SLAs before
  they exist and are verified. Public copy follows [Current State](../CURRENT_STATE.md).
- No proprietary product features. Cloud differs from self-host by operations, not functionality.
- No VM fleet inside OpenCompany. Compute always comes through a provider.
- No consumer single-user assistant offering.

## Open questions

- **Pricing and packaging:** per seat, per agent, compute pass-through, or a mix (Product Direction
  open question), within the rule that presets and security features are never paywalled.
- **Shared team computer on Cloud:** whether it is offered at all, given its weaker isolation.
- **Browser login capture and storage per agent:** the defaults between vault fills, secure forms and
  takeover sign-in on hosted computers, and how long profiles persist after an agent is deleted.
- **boat agreement:** whether a partner agreement for managed computers is pursued, or managed
  computers wait for Bowerbird.
- **Tenancy topology:** shared versus dedicated deployments, and the queue isolation model required
  before many tenants share one installation.
- **Agent inbox domain on Cloud:** which mail domain hosts agent addresses and how custom domains work.
- **Regions:** which regions follow the EU first, and how computer-provider regions are matched to
  the workspace region.
- **Deletion window:** the documented window for removal from provider backups.
- **Entity, org and wallet currency:** which legal entity and GitHub organisation hold OpenCompany
  (`OpenCompanyApp`, `BowerbirdHQ`/`bowerbird-si`, Ginger Media BV or another structure), and the
  wallet currency (open in [OpenCompany × Bowerbird](bowerbird-synergy.md#open-questions)).
- **Activepieces sidecar:** whether Cloud operates an Activepieces sidecar for customers, and whether
  it ships in the default docker-compose for self-host (Product Direction open question).
- **Operator OAuth clients:** which providers Cloud pre-registers clients for, and how customers
  bring their own instead.

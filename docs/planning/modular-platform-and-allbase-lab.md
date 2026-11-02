# Modular OpenCompany and the Allbase Lab

Status: Planning decision informed by a read-only local source investigation.
Verified: 2026-08-25 against the active OpenCompany and Allbase checkouts.

This document defines a target product and architecture direction. It does not claim that a module
runtime, module marketplace, Allbase migration, hosted OpenCompany MCP server, or the application
packs described below are implemented. Current implementation truth remains in
[Current State](../CURRENT_STATE.md), and current priorities remain in
[Roadmap and Open Audits](../ROADMAP.md).

## Executive decision

OpenCompany should become a modular company operating system: a governed organizational kernel,
a small set of foundational work applications, and a growing catalog of optional business modules
that humans and agents can operate together.

Allbase should not become a fourth production platform and should not be merged wholesale into the
OpenCompany repository. It should be preserved as a playground and implementation laboratory for
business-application concepts. Proven Allbase capabilities can graduate into OpenCompany through
explicit, workspace-scoped OpenCompany module contracts.

The intended distinction is:

> OpenCompany is not merely a place to chat with agents. It is the modular operating environment in
> which humans and AI teammates perform governed work using real company applications.

Breadth is an architectural advantage only when it is organized behind coherent contracts. The
public product should still be presented through narrow workflow packs and concrete outcomes rather
than as an undifferentiated collection of applications.

## Why this direction matters

Many agent platforms converge on the same visible surface:

- chat with one or more agents;
- prompt and memory management;
- scheduled prompts or generic workflows;
- a tool and integration catalog;
- an activity or run-history screen; and
- a canvas for generated output.

Those primitives are useful, but they do not provide the durable operational environment in which a
company actually works. OpenCompany can differentiate by combining its agent runtime and governance
with first-class business resources:

- tasks and lists;
- tables and structured records;
- documents and knowledge;
- calendar and recurring work;
- CRM;
- forms and inbound capture;
- monitored sources, RSS, and newsletters;
- webhook inboxes;
- reporting and operational dashboards;
- files, comments, attachments, and linked records; and
- later domain- or industry-specific applications.

The shared organizational fabric is the important part. An RSS item should be able to become a
document, task, table row, CRM signal, or agent assignment without every pair of modules building a
custom integration. An agent should be able to operate those resources only within its workspace,
resource grants, approval policy, and execution budget.

## Target product model

```text
OpenCompany
├── Organizational kernel
│   ├── organizations, workspaces, membership, and roles
│   ├── humans, agents, identity, and organizational relationships
│   ├── permissions, approvals, audit, and policy
│   ├── chat, notifications, memory, and agent execution
│   ├── files, search, resource references, and activity events
│   └── integration credentials, grants, and execution boundaries
│
├── Foundational applications
│   ├── tasks and lists
│   ├── tables
│   ├── documents
│   ├── calendar
│   └── files
│
├── Optional first-party modules
│   ├── CRM
│   ├── forms
│   ├── Reader / RSS / newsletters
│   ├── webhook inboxes
│   ├── reports
│   ├── deeper project management
│   ├── publishing and public portals
│   └── future business applications
│
└── Curated workflow packs
    ├── Research and Intelligence
    ├── Sales Operations
    ├── Content Operations
    ├── Customer Operations
    └── later vertical packs
```

The organizational kernel owns authority. Modules contribute business resources and actions but do
not create parallel concepts of workspace, user, agent, permission, approval, credential, or audit
history.

## Kernel versus foundational applications

The kernel should remain smaller than the visible product. A feature belongs in the kernel only when
every enabled module needs it to participate safely in OpenCompany.

### Kernel responsibilities

- workspace and membership resolution;
- human and agent identities;
- authorization and resource-specific grants;
- approval policy and durable approval execution;
- audit and operation events;
- agent runtime, memory, cost, and execution records;
- integration credentials and capability grants;
- stable resource identity and cross-resource references;
- shared files, attachments, comments, and notifications;
- search registration and permission-filtered retrieval;
- module registration, enablement, configuration, and health; and
- safe lifecycle behavior when a module is disabled or removed.

### Foundational applications

Tasks/lists, tables, documents, calendar, and files should ship with OpenCompany and be enabled by
default. They are not all kernel internals. Keeping them behind the same module-facing contracts
prevents every future application from depending directly on their storage schema and makes the
boundaries testable.

They are foundational because nearly every company workflow needs some combination of:

- unstructured knowledge;
- structured data;
- accountable work;
- time and recurrence; and
- evidence or artifacts.

An installation may hide an irrelevant foundational application from navigation, but other modules
should rely on stable capabilities rather than assuming its database tables are directly available.

## What a module is

A module is an OpenCompany-owned application capability that contributes durable workspace data,
user interface, agent actions, and operational behavior.

Each module should eventually declare:

```text
Module
├── stable ID, version, and compatibility range
├── installation and per-workspace enablement state
├── navigation entries and workspace surfaces
├── permissions and action risk classifications
├── resource types and typed references
├── routes, controllers, pages, and API resources
├── agent capabilities and tool-discovery metadata
├── search and VFS projections
├── events, triggers, and automation actions
├── import and export handlers
├── retention, disable, uninstall, and deletion behavior
├── dependencies on stable platform capabilities
└── health, diagnostics, and migration state
```

An illustrative manifest could eventually resemble the following. This is a target vocabulary, not
an accepted implementation schema:

```yaml
id: reader
name: Reader
version: 1.0.0
requires:
  opencompany: ">=1.0"
resources:
  - reader.feed
  - reader.entry
capabilities:
  - reader.feed.read
  - reader.feed.manage
  - reader.entry.read
  - reader.entry.classify
agent_actions:
  - reader.search
  - reader.summarize
  - reader.create_task
events:
  - reader.entry.received
  - reader.entry.matched_rule
search_provider: reader
vfs_mount: /reader
```

Initially, modules should be trusted first-party code loaded with the application. OpenCompany should
not build dynamic installation of untrusted third-party PHP or JavaScript until the contracts,
authorization model, migrations, operational isolation, and recovery behavior have matured through
several first-party modules.

## Module, integration, skill, automation, and execution

These concepts should remain separate even when the product makes them feel cohesive.

| Concept | Owns | Does not own |
|---|---|---|
| Module | Durable company resources, UI, resource actions, and domain behavior | External provider credentials or generic agent execution |
| Integration | A governed connection to an external provider and its capabilities | OpenCompany business records or workflow policy |
| Skill | Reusable instructions and assets that teach an agent how to perform work | Durable resource data or execution scheduling |
| Automation | Trigger, schedule, conditions, and invocation policy | A separate resource store or worker infrastructure |
| OpenFactory workflow | Durable external execution, workers, sandboxes, retries, and run evidence | OpenCompany workspace authority or business records |

For example:

```text
Reader module
  receives articles through
RSS and newsletter adapters
  monitored by a
Research Analyst skill
  scheduled through an
OpenCompany automation
  which may delegate a large investigation to
OpenFactory
  and writes proposed results back through
OpenCompany permissions and approvals
```

## Cross-module resource fabric

Modules should interoperate through a small platform-owned resource protocol rather than direct
cross-module model access.

### Stable resource reference

A resource reference should identify at least:

- workspace;
- module;
- resource type;
- resource identifier; and
- optional version or revision where immutable evidence is required.

The reference can support shared platform services such as:

- comments and mentions;
- attachments;
- favorites and recents;
- linked records;
- activity and audit history;
- search results;
- notification subjects;
- approvals;
- VFS projections;
- agent tool results; and
- automation event subjects.

Allbase has useful prototypes of comments, attachments, favorites, recents, activity events, search,
and polymorphic linked records. Their concepts should inform this layer, but the OpenCompany contract
must be workspace-scoped and permission-aware from the beginning.

### Events instead of table coupling

A module may publish stable domain events such as `reader.entry.received` or
`crm.deal.stage_changed`. Other modules and automations can react through versioned event contracts.
They should not reach into the publishing module's private tables.

Cross-module writes should happen through authorized application actions. An agent, automation, or
module may request `tasks.create` or `crm.lead.create`; it should not insert rows into another
module's storage directly.

### Disable and uninstall semantics

Disabling a module should:

- remove its navigation and interactive routes from the workspace;
- remove its capabilities from agents and automations;
- stop its schedules, polling, and trigger delivery;
- remove its search and VFS surfaces;
- retain its durable data by default; and
- keep historical audit references intelligible.

Uninstalling or deleting module data should be a separate, explicit, authorized operation with an
export path, impact preview, retention behavior, and audit record.

## Agent-facing module design

Module breadth must not become agent prompt and tool overload. Agents should not receive every tool
from every installed module on every run.

An agent-facing capability registry should filter by:

1. the active workspace;
2. modules enabled for that workspace;
3. the agent's module and resource grants;
4. the current task or conversation context;
5. action risk and approval requirements; and
6. tool relevance and context budget.

The runtime should expose a small discovery surface and load detailed module actions only when
needed. Actions need consistent metadata for:

- read, propose, write, destructive, and external-side-effect classifications;
- required permissions;
- approval behavior;
- idempotency and retry safety;
- input and output resource references;
- provenance and audit records;
- cost or execution-budget implications; and
- availability and module health.

This is how OpenCompany can support many modules without presenting an agent with thousands of
ambiently authorized tool definitions.

## Human-facing module design

Modularity must also prevent navigation overload.

- Workspaces enable only the modules relevant to their operating model.
- Navigation is derived from enabled modules and the current human's permissions.
- Modules can contribute command-palette actions, search scopes, dashboards, and creation actions.
- Workspace home should emphasize current responsibilities, exceptions, agent activity, and pending
  approvals rather than listing every installed application.
- Packs provide sensible defaults without preventing later customization.
- Global administrators can inspect module version, configuration, migration, queue, and health state.

The product may be broad, but an individual user should experience a focused workplace.

## Workflow packs and positioning

OpenCompany should be broad internally and specific commercially:

> Breadth is the architecture; workflow packs are the go-to-market.

Possible initial packs include:

| Pack | Default modules | Example outcome |
|---|---|---|
| Research and Intelligence | Reader, Docs, Tables, Reports, Tasks, Automations | Monitor sources, synthesize findings, assign follow-up, and preserve evidence |
| Sales Operations | CRM, Forms, Calendar, Reports, Tasks | Capture, qualify, progress, and review customer opportunities |
| Content Operations | Reader, Docs, Calendar, Forms, Tasks | Research, plan, review, publish, and learn from content work |
| Customer Operations | CRM, Forms, Webhook Inboxes, Docs, Reports | Collect requests, connect customer context, coordinate response, and track outcomes |

These are packaging and activation configurations over one platform. They should not fork the core
application or create separate databases.

## Long-term application and module catalog

This catalog is the credible long-term possibility space for OpenCompany. It is a taxonomy, not a
commitment to build every entry. A module should enter the implementation roadmap only after it
passes the graduation gates later in this document and supports a validated workflow or customer
need.

The catalog deliberately distinguishes five kinds of product surface:

1. mandatory platform services that modules depend on;
2. critical applications enabled by default;
3. high-leverage optional first-party modules;
4. later domain and vertical modules; and
5. platform extensions and external adapters that are not ordinary business modules.

### Mandatory platform services

These are not optional applications. They form the authority and runtime shared by every module.

| Service | Long-term responsibility |
|---|---|
| Workspace and tenancy | Organization boundaries, current-workspace resolution, isolation, and workspace lifecycle |
| Membership and organization | Human membership, roles, teams, departments, and organizational relationships |
| Human and agent identity | Stable identities, profiles, responsibility, status, and attribution |
| Authorization | Human and agent capabilities, record grants, policy evaluation, and denial evidence |
| Approvals | Proposed actions, authorized approvers, durable decisions, expiry, and safe replay |
| Audit and provenance | Append-only records of human, agent, automation, integration, and administrative activity |
| Resource registry | Stable typed references for resources contributed by every module |
| Resource relationships | Cross-module links, comments, mentions, attachments, favorites, and recent resources |
| Event fabric | Versioned domain events and authorized cross-module actions |
| Files and object storage | Shared files, attachments, versions, previews, retention, and download policy |
| Search and retrieval | Permission-filtered keyword, structured, and semantic retrieval across enabled modules |
| Notifications | Subscriptions, mentions, assignments, digests, delivery preferences, and external delivery |
| Agent runtime | Runs, retries, cancellation, delegation, tool execution, context, and result delivery |
| Agent memory | Short- and long-term memory, evidence, indexing, compaction, retention, and forgetting |
| AI provider runtime | Provider and model catalogs, credentials, transport, caching, usage, and cost accounting |
| Integration runtime | Connected accounts, credentials, resource-specific grants, MCP clients, and health |
| Module registry | Module discovery, compatibility, enablement, configuration, lifecycle, and health |
| Usage and budgets | Human, agent, provider, module, storage, and workflow usage and limits |
| Scheduling and queues | Local application jobs, schedules, retries, and the OpenFactory execution boundary |
| API and webhook foundation | Authentication, rate limits, event subscriptions, receipts, and operation policy |

Individual modules must not invent competing workspace, identity, agent, permission, approval,
credential, search, audit, or execution systems.

### Critical applications enabled by default

These applications define the base OpenCompany experience. They may use module-style internal
contracts, but every normal workspace should receive them by default.

#### Home / Command Center

- responsibilities, exceptions, and items needing attention;
- pending approvals and agent questions;
- current human, agent, and automation work;
- recent and pinned resources;
- workspace metrics and operational signals; and
- role-aware starting views rather than a generic module directory.

#### Conversations

- channels and direct messages;
- threads, mentions, reactions, pins, and attachments;
- human-to-human, human-to-agent, and agent-to-agent communication;
- topic or lane ownership;
- external-channel synchronization; and
- conversion of conversations into durable work and knowledge.

#### People and Organization

- human and agent directories;
- teams, departments, reporting lines, and organization view;
- roles, responsibilities, ownership, and escalation paths;
- availability and presence; and
- links to work, agents, approvals, goals, and company resources.

#### Work

- tasks and lists;
- list, board, and personal-work views;
- assignees, watchers, priority, status, and due dates;
- subtasks, dependencies, recurrence, and checklists;
- workload and responsibility views; and
- agent assignment, execution, and result tracking.

#### Documents

- shared knowledge and document hierarchies;
- versions, comments, mentions, attachments, and review dates;
- templates and structured metadata;
- content and semantic retrieval;
- agent-assisted drafting and synthesis; and
- permission and publication controls.

#### Tables

- structured workspace data;
- typed and computed fields;
- relations and stable resource references;
- filters, grouping, sorting, and saved views;
- imports, exports, and automation events; and
- permission-aware human and agent operations.

#### Calendar

- events and deadlines;
- recurring work and schedules;
- attendee and agent availability;
- task, automation, and resource relationships;
- internal and external calendar synchronization; and
- time-aware agent context and reminders.

#### Files

- workspace folders and shared files;
- attachments to any registered resource;
- previews, versions, metadata, and search;
- permission-filtered agent access;
- retention, export, and deletion; and
- references to externally stored artifacts without claiming ownership of them.

#### Approvals

- pending human decisions;
- agent and tool action proposals;
- business and operational approval requests;
- required approvers and separation-of-duty policy;
- decision history, expiry, revocation, and replay; and
- views organized by urgency, resource, agent, and workflow.

#### Automations

- schedules and event triggers;
- prompt, action, and script automations;
- conditions and bounded workflow steps;
- manual tests and disabled drafts;
- run history, retries, pause, and failure handling; and
- module-provided triggers and authorized actions.

#### Search and Command

- universal permission-aware search;
- structured filters and semantic retrieval;
- command-palette navigation;
- contextual creation and actions;
- recent, favorite, and related resources; and
- agent-assisted querying without bypassing resource permissions.

#### Notifications / Personal Inbox

- mentions and replies;
- assignments and watched-resource changes;
- agent questions and completion notices;
- approval requests;
- automation and integration failures;
- digest, snooze, subscription, and delivery preferences; and
- a personal triage surface distinct from shared business inboxes.

#### Integrations

- connected accounts and credentials;
- integration and MCP catalogs;
- connection setup, diagnostics, and health;
- resource-specific and agent-specific grants;
- inbound receipts and outbound operation history; and
- clear distinction among installed, configured, authorized, healthy, and live-verified behavior.

#### Activity and Audit

- human changes;
- agent runs and tool actions;
- automation executions;
- external side effects;
- permission and approval decisions;
- administrative and security events; and
- provenance linking results to inputs, actors, evidence, and policies.

#### Administration

- workspace identity and settings;
- members, roles, teams, and agents;
- module enablement and health;
- provider and integration configuration;
- security, authentication, sessions, and API access;
- usage, costs, budgets, queues, and system health; and
- retention, export, backup, migration, and danger actions.

### High-leverage optional first-party modules

These are the most credible first expansion wave because they build directly on current OpenCompany
foundations or substantial Allbase experiments.

#### Reader / Sources

- RSS and Atom feeds;
- newsletter inboxes;
- website and source monitoring;
- folders, tags, saved searches, and watchlists;
- classification and routing rules;
- canonical articles and duplicate handling;
- research digests and briefings;
- source provenance; and
- agent monitoring, summarization, comparison, and escalation.

#### CRM

- contacts, companies, and organizational relationships;
- leads and qualification;
- deals, pipelines, stages, and forecasts;
- activities, meetings, tasks, and follow-up;
- custom properties and segmentation;
- email templates, messages, and sequences;
- account plans and relationship history; and
- agent-assisted enrichment, preparation, and next actions.

#### Forms

- public and internal form builders;
- typed and conditional fields;
- response review and export;
- approval and request forms;
- creation or updating of module resources;
- automation triggers; and
- spam, retention, consent, and publication controls.

#### Webhook Inboxes

- managed inbound endpoints;
- request and payload inspection;
- authentication and signature policy;
- schema discovery;
- retention, redaction, and export;
- replay with safeguards;
- conversion into typed events; and
- agent interpretation and routing.

#### Reports and Dashboards

- registered metrics from enabled modules;
- charts, tables, summaries, and filters;
- role- and workspace-specific dashboards;
- scheduled reports and digests;
- permission-safe sharing;
- snapshots and comparison periods; and
- agent-written narratives grounded in metric provenance.

#### Advanced Projects

The foundational Work application handles general task coordination. This optional module adds:

- project portfolios;
- milestones, cycles, and releases;
- roadmaps and dependencies;
- estimates, time entries, and capacity planning;
- development and repository links;
- project templates;
- project-level reporting; and
- client or stakeholder project portals.

#### Imports and Data Onboarding

- CSV and structured-file imports;
- source-to-resource mapping;
- validation, preview, and correction;
- row-level error retention;
- resumable and recurring imports;
- migration adapters for common products;
- provenance and import history; and
- rollback or compensating-operation support where safe.

#### Public Portals

- shared project and status views;
- client and partner portals;
- published documents and knowledge sites;
- public forms and reports;
- selected file delivery;
- custom domains and branding; and
- explicit publication permissions, expiry, revocation, and access evidence.

#### Shared Email Inbox

- shared business addresses;
- threads, assignment, status, and ownership;
- customer and CRM linking;
- agent classification and drafting;
- SLA and follow-up state;
- templates and approval policy; and
- email-to-task, document, case, or opportunity conversion.

#### Meetings

- agendas and preparation;
- participant and account context;
- notes, recordings, and transcripts;
- decisions and action items;
- follow-up creation and assignment;
- calendar and document relationships; and
- agent preparation, facilitation, and synthesis.

#### Feedback

- customer, employee, and product feedback;
- classification, similarity, and deduplication;
- relationships to people, companies, products, and work;
- feature requests and evidence;
- triage, ownership, and status;
- trend and sentiment reporting; and
- auditable agent summaries and routing recommendations.

### Agent-native optional modules

These applications make OpenCompany more than a traditional suite with AI added afterward.

#### Agent Studio

- agent creation, identity, and responsibility templates;
- provider and model policy;
- module, integration, file, and resource grants;
- memory and context policy;
- schedules and escalation paths;
- configuration versions and rollback; and
- evaluation, diagnostics, and behavior history.

Core agent identity remains platform-owned. Agent Studio is the advanced configuration and
supervision application.

#### Skills Library

- reusable instructions and assets;
- workspace and agent-specific skills;
- versions, authorship, provenance, and review;
- required module and integration capabilities;
- tests and evaluations;
- enablement and permission policy; and
- a possible curated marketplace after the first-party contract matures.

#### Artifact Studio / Lab

- agent-generated dashboards and interactive tools;
- data explorers, reports, and small internal applications;
- versioned source, assets, and resource-binding requirements;
- proposed revisions and atomic draft changes;
- tests, preview, publication, and rollback;
- permission-bound access to workspace resources; and
- external sharing and custom domains where explicitly authorized.

#### Research Workspace

- research questions and investigation plans;
- source collections and citations;
- claims, evidence, contradictions, and confidence;
- long-lived dossiers and comparative reports;
- agent runs and review state; and
- handoff into documents, tables, tasks, CRM, or decisions.

This may begin as a workflow pack over Reader, Documents, Tables, Reports, Tasks, and Agents rather
than as an independent storage module.

#### Routine Studio

- teach-by-demonstration and natural-language routines;
- schedules and event triggers;
- required skills, modules, integrations, and permissions;
- test runs and disabled drafts;
- approval and budget policy;
- run history and exception handling; and
- agent-proposed routine improvements.

#### Agent Operations

- run queues and current execution;
- delegation and child-run relationships;
- budgets and token/provider usage;
- failures, retries, pause, cancel, and recovery;
- agent workload and latency;
- tool and approval history; and
- OpenFactory execution references and evidence.

#### Memory Studio

- proposed durable memory changes;
- source evidence and confidence;
- conflict, duplication, and staleness review;
- personal, agent, workspace, and protected scopes;
- retention, redaction, forgetting, and reindexing;
- consolidation and quality evaluation; and
- visibility into why a memory affected an agent response.

#### Browser and Computer Sessions

- remote browser or computer sessions;
- live observation and human takeover;
- session-specific capability and credential grants;
- authentication boundaries;
- recorded actions, screenshots, and evidence;
- pause, revoke, and retention controls; and
- linkage to agents, tasks, approvals, and external execution runs.

OpenFactory may own the generic browser/computer execution infrastructure. OpenCompany owns the
business request, workspace authority, resource grants, approval, supervision, and returned result.

### Sales and marketing modules

These can be separate modules when they own substantial durable behavior, or workflow packs when
existing modules already provide the required records.

- Campaigns and campaign planning
- Email marketing
- Content pipeline and editorial workflow
- Social publishing
- Digital asset library and brand resources
- Brand guidelines and review
- SEO and search-presence monitoring
- Competitor and market intelligence
- Partner and affiliate management
- Events and webinars
- Lead enrichment and routing
- Attribution and campaign reporting

The CRM should remain the shared customer and company identity instead of every sales or marketing
module creating its own contacts.

### Customer operations modules

- Help desk, tickets, and cases
- Customer-success workspaces
- Customer health and risk
- Customer onboarding
- SLA and escalation tracking
- Surveys, CSAT, and NPS
- Community operations
- Customer-facing knowledge base
- Customer portal
- Incident communication
- Renewals and expansion
- Account plans and success outcomes

These modules should share CRM accounts, contacts, communication history, tasks, documents, and
reports through stable resource references and authorized actions.

### Product and engineering modules

- Product roadmap
- Product discovery
- Feature requests and opportunity evidence
- Experiments and hypotheses
- Releases and changelog
- Bugs and issue tracking
- QA and test management
- Incident management
- Status pages
- Service catalog
- Architecture decisions
- Engineering metrics
- Repository, pull-request, CI, deployment, and release references

Git remains authoritative for source history, connected CI remains authoritative for exact-commit
verification, Aya owns source review and operator context, and OpenFactory owns generic durable
execution. OpenCompany owns organizational planning, business authorization, communication, and
outcomes around that engineering work.

### Operations and governance modules

- Requests and internal intake
- SOPs and runbooks
- Vendor management
- Procurement and purchase requests
- Contract management
- Asset and inventory management
- Risk register
- Compliance controls and evidence
- Policy management and attestations
- Access reviews
- Incident and crisis operations
- Business continuity
- Data-retention management
- Decision register
- Facilities and equipment
- Operational scorecards

### People modules

- Recruiting and applicant tracking
- Candidate pipeline and interview coordination
- Employee onboarding
- Offboarding and access closure
- Goals and OKRs
- Performance reviews
- One-on-ones
- Time off and availability
- Learning and training
- Skills directory
- Team health
- Compensation planning
- Workforce planning

People modules contain unusually sensitive data. They require stronger record- and field-level
authorization, audit, retention, export, regional, and agent-access controls than ordinary workspace
modules.

### Finance modules

- Budgets and budget requests
- Spend requests and approvals
- Expenses and receipts
- Purchase orders
- Invoices and billing workflows
- Accounts-receivable and accounts-payable coordination
- Subscriptions and renewal tracking
- Revenue and cash forecasts
- Financial dashboards
- Project and customer profitability
- Agent, provider, module, and workflow cost accounting

OpenCompany should initially orchestrate, approve, and analyze financial workflows while Stripe,
accounting software, banks, and other governed ledgers remain the financial systems of record. It
should not casually become a general ledger or payment custodian.

### Commerce and vertical modules

Possible later modules include:

- product and service catalogs;
- orders and fulfillment;
- memberships and subscriptions;
- bookings and reservations;
- case management;
- property and location management;
- field operations;
- grants and nonprofit programs;
- agency and client delivery; and
- professional-services engagements.

These should be built only after a real vertical workflow and customer prove that a reusable module
is more valuable than configuration of Tables, Forms, Work, Calendar, Documents, CRM, and Reports.

### Platform extensions and external adapters

The following may appear as first-class product surfaces but are not ordinary business modules:

- hosted OpenCompany MCP server;
- OpenCompany Connect for managed external access;
- CLI;
- public API and event subscriptions;
- JavaScript and Python SDKs;
- module and integration developer kits;
- curated module and skill marketplace;
- workspace templates and workflow packs;
- desktop and mobile clients;
- Telegram, Slack, Microsoft Teams, Discord, email, WhatsApp, SMS, and voice adapters;
- SSO, SAML, SCIM, and enterprise identity;
- compliance center and SIEM export;
- observability and AI FinOps;
- device registry;
- backup, export, restore, and migration center;
- visual workflow builder; and
- browser/computer execution providers.

An external chat provider is an adapter, not a second source of truth for conversations, tasks,
approvals, or agents. A CLI, SDK, API, VFS mount, or MCP server is an interface to authorized
OpenCompany capabilities, not a parallel application model.

### Expanded workflow-pack catalog

Packs are opinionated combinations of modules, permissions, dashboards, agents, skills,
automations, and integrations. They are not forks and do not own separate data models.

| Pack | Likely composition |
|---|---|
| Research and Intelligence | Reader, Research Workspace, Documents, Tables, Reports, Tasks, research agents, source-monitoring routines |
| Sales Operations | CRM, Forms, Calendar, Shared Email, Reports, Tasks, sales agents, pipeline routines |
| Content Operations | Reader, Documents, Calendar, Forms, Digital Assets, Reports, editorial agents, publication routines |
| Customer Operations | CRM, Help Desk, Forms, Webhooks, Documents, Reports, customer agents, escalation routines |
| Product Operations | Feedback, Roadmap, Projects, Documents, Tables, Reports, product agents, review routines |
| Engineering Operations | Projects, Incidents, Releases, Architecture Decisions, external Git/CI references, engineering agents |
| People Operations | People, Recruiting, Onboarding, Goals, Calendar, Documents, approvals, carefully scoped people agents |
| Back Office | Vendors, Procurement, Contracts, Budgets, Expenses, Reports, approval-heavy finance and operations routines |
| Governance and Compliance | Policies, Risk, Controls, Access Reviews, Incidents, Documents, evidence collection, compliance agents |
| Agency / Client Delivery | CRM, Projects, Portals, Files, Calendar, Reports, billing references, client-service agents |

### Catalog priority

The long-term catalog should not alter the near-term sequence:

1. stabilize authorization and the current agent runtime;
2. establish the internal module registry;
3. register existing critical applications through the contract;
4. graduate Reader from Allbase as the first optional vertical slice;
5. add Forms and Webhook Inboxes;
6. add Reports;
7. prove one or two workflow packs;
8. graduate CRM;
9. add public portals, shared inboxes, and other modules only when demanded; and
10. consider third-party modules and a marketplace after first-party lifecycle contracts are proven.

The desired end state is one governed company platform with a small mandatory foundation and only
the applications each workspace actually needs. The catalog can be large without making every
workspace, user, or agent experience the whole catalog.

## Allbase investigation

### Repository state

The inspected Allbase checkout is at `/Users/rutger/Sites/allbase`.

| Surface | 2026-08-25 observation |
|---|---|
| Branch | `main` at `8b07135` (`Add CRM lead segmentation`) |
| Last commit | 2026-05-03 |
| History | 90 commits: 4 on 2026-04-12, 27 on 2026-05-02, and 59 on 2026-05-03 |
| Remote | No Git remote configured |
| GitHub search | No matching repository or unique source phrase in repositories accessible to the authenticated account |
| Working tree | 30 existing entries: 28 deleted agent/configuration paths and 2 modified instruction files; no application paths dirty |
| Deployment | No Docker, CI, hosting, or other deployment surface found |
| Local data | A local 3.2 MB SQLite database exists; its private contents were not inspected |

Allbase has no configured upstream that can currently act as a recoverable or collaborative source of
truth. Before any destructive cleanup, its committed history and local-only state should be backed up
to an explicitly chosen repository.

### Implementation size

| Surface | Count |
|---|---:|
| Tracked PHP, Blade, JavaScript, and CSS lines | 53,320 |
| Eloquent model files | 70 |
| Database migrations | 95 |
| Product and settings page files | 53 |
| Application routes | 44 |
| Test files | 43 |
| Declared Pest test cases | 267 |

No Allbase tests were executed during this investigation. Test declarations demonstrate intended
coverage, not a currently passing build.

### Implemented product surface

The code contains substantial implementations for:

- Docs with spaces, books, pages, versions, templates, comments, attachments, exports, public pages,
  scheduled publishing, ownership, review dates, and analytics;
- Projects with issues, statuses, labels, cycles, milestones, relations, development links,
  checklists, recurring issues, estimates, time entries, workload, exports, and shared views;
- CRM with contacts, companies, first-class leads, deals, pipelines, custom stages, properties,
  activities, forecasts, email templates, sequences, messages, tracking, and segmentation;
- Forms with builders, public submission, responses, exports, and relationship points;
- Tables with bases, tables, typed fields, rows, saved views, and exports;
- Reader with RSS feeds, discovery, OPML, folders, saved searches, rules, topic feeds, canonical
  articles, newsletter ingestion, scheduled refresh, digests, and webpage extraction;
- webhook inboxes with public catch endpoints, message inspection, retention, and export;
- reports and shareable dashboards;
- CSV imports and module exports;
- activity events, notifications, comments, attachments, favorites, recents, and record links;
- API tokens and a small read-only API;
- a PWA shell, localization, security, session, data-retention, and instance settings;
- event-driven automation rules; and
- an AI assistant and authenticated MCP server.

The densest declared test coverage is in Reader, Docs, Projects, and CRM. The largest Livewire page
components are also concentrated there: the Docs workspace exceeds 2,100 lines, Reader index exceeds
1,800 lines, and multiple Project pages exceed 1,300 lines. The breadth is real, but the large
anonymous page components are a maintainability warning and should not define OpenCompany's target
module architecture.

### Architectural reality

Allbase is better described as one self-hosted company installation with user-owned records than as
a true multi-workspace platform.

- There is no `Workspace` model or structural `workspace_id` tenancy boundary.
- Most business records are selected through `owner_id`.
- Workspace role and guest state live directly on `User`.
- Groups and invitations are global to the installation.
- Module permission defaults are stored in global instance settings.
- Search and MCP tools resolve data for the authenticated owner rather than an active workspace.

OpenCompany must not inherit those assumptions. Any graduated capability needs native
`workspace_id` ownership, current workspace resolution, resource permissions, agent grants,
approval policy, and audit behavior.

### Capability depth boundaries

Some Allbase labels overstate the current implementation:

- The AI assistant is a menu of predefined actions. In local mode it performs counts, string
  transformations, keyword classification, and project creation. It can optionally call one
  configured model endpoint. It is not a durable agent runtime.
- MCP is implemented but narrow: two authenticated read-only tools for workspace search and summary,
  plus one overview resource.
- Global search uses SQL `LIKE` queries over selected modules. It is not Meilisearch, vector search,
  or semantic retrieval.
- The API exposes a small set of read-only collection endpoints plus newsletter ingestion. It is not
  a full REST API for every module.
- Automations react synchronously to activity events and support notification, issue assignment,
  issue status movement, and outbound webhook actions. They are not a general workflow engine.

These implementations remain useful prototypes. They should be evaluated for behavior and tests,
not adopted based on their product labels.

### Documentation and distribution gaps

Allbase's documentation is not a reliable current-state inventory:

- the roadmap lists already implemented modules as future work;
- the opportunity and competitor documents contain contradictory completion claims;
- the overview names Flux UI, Meilisearch, Tiptap, ApexCharts, and SortableJS, but the corresponding
  runtime dependencies are not present in the application manifests;
- the docs promise a fair-source license while `composer.json` still identifies the Laravel
  Livewire starter kit, declares MIT, and no license file exists;
- the proposed EUR 4.99 hosted tier has no verified billing or deployment implementation; and
- the claimed `allbase.co` association was not verified.

Licensing and provenance must be resolved before redistributing or copying substantial code. Concepts,
schemas, behavior, and test scenarios can be studied immediately; code reuse needs a clear ownership
and license decision.

## Allbase versus OpenCompany

| Surface | Allbase | OpenCompany |
|---|---|---|
| Product center | Broad integrated business suite | Governed organization where humans and agents work together |
| Tenancy | Global installation with owner-scoped resources | First-class multi-workspace model |
| Collaboration | Comments, mentions, sharing, teams, and record permissions | Channels, DMs, threads, external chat, agents, tasks, and approvals |
| AI | Small assistant action menu and optional generic endpoint | Durable agents, memory, provider runtime, tools, runs, and delegation |
| Business applications | Deep CRM, Reader, Forms, webhooks, and reporting | Strong organizational and agent foundation; thinner business verticals |
| Data surface | Generic tables plus application-specific records | Tables, lists, documents, VFS, files, and workspace resources |
| MCP | Small authenticated server | MCP client runtime; hosted OpenCompany server remains planned |
| Frontend | Laravel Livewire and Blade | Vue and Inertia |
| Operations | Local prototype without deployment configuration | Docker/self-hosting architecture with queues and realtime services |

The repositories overlap heavily in Docs, Projects/Tasks, Tables, Automations, search, attachments,
comments, activity, permissions, and AI/MCP vocabulary. A wholesale merge would import duplicate
features, a second frontend architecture, incompatible tenancy assumptions, and conflicting runtime
boundaries.

The complement is more valuable than the overlap: Allbase explores application width, while
OpenCompany supplies the organizational, agentic, permission, memory, integration, and execution
foundation.

## Allbase's role

Allbase should be classified as:

> Parked implementation asset and business-module laboratory for OpenCompany.

Its immediate responsibilities are limited:

- preserve the implementation and history;
- document module behavior and reusable tests;
- support bounded product experiments when useful;
- compare its capabilities against OpenCompany before new implementation; and
- provide evidence for module contracts and workflow packs.

It should not:

- become a fourth Ginger Media platform commitment;
- maintain an independent hosted-suite roadmap without external validation;
- define a second organization, permission, agent, integration, or automation runtime;
- be embedded inside OpenCompany as a Livewire application; or
- be merged wholesale merely to preserve code.

`allbase-lab` may become a clearer repository name later, but renaming is not required to adopt the
role now.

## Capability disposition

| Allbase capability | Recommended disposition |
|---|---|
| CRM | Preserve as the richest candidate optional module; port domain behavior deliberately after module and permission contracts exist |
| Forms | Strong early module candidate, especially with webhook and CRM/task actions |
| Reader / RSS / newsletters | Best first module experiment because it is bounded, differentiated, and naturally agentic |
| Webhook inboxes | Pair with Forms or Operations; reuse capture, inspection, retention, and event concepts |
| Reports | Adapt as a permission-aware reporting module over registered metrics and resources |
| Imports | Reuse parser, job, error-reporting, and correction concepts; implement on OpenCompany queues and workspace scope |
| Public sharing and portals | Port later behind explicit resource permission and publication contracts |
| Linked records and activity | Use as design input for the platform-owned resource/event fabric |
| Docs | Compare capability gaps; selectively port templates, publishing, review, export, or public-site behavior |
| Projects | Compare against OpenCompany Tasks/Lists and port only missing, validated workflows |
| Tables | Compare field/view behavior; do not create a parallel table system |
| Automations | Translate useful triggers/actions into OpenCompany's durable automation runtime |
| MCP server | Use the read-only resource/search concept as input to the planned hosted OpenCompany MCP surface |
| AI assistant | Replace with OpenCompany agents and module capabilities |
| Team and tenancy | Do not port; use OpenCompany workspaces, membership, permissions, and approvals |
| UI shell | Do not port; implement graduated modules in OpenCompany's Vue/Inertia design system |
| Pricing and licensing | Do not carry forward without a new commercial and legal decision |

Reader ownership has one product-boundary nuance: private feeds and personal attention management may
fit Aya, while shared monitored company sources, research routines, and operational signals fit
OpenCompany. The same source adapter may eventually serve both through a stable contract, but the
canonical business records and permissions must remain with the product that owns the workflow.

## Recommended first module: Reader

Reader is the best vertical slice for proving the module architecture.

### Why Reader

- It is bounded and primarily read-oriented.
- Allbase contains meaningful Reader behavior and the largest declared test suite.
- It visibly differentiates OpenCompany from agent-chat products.
- Agents can monitor, classify, summarize, compare, and escalate new information.
- Outputs naturally become tasks, documents, table rows, reports, or CRM signals.
- It exercises schedules, external fetches, provenance, retention, search, permissions, agent tools,
  automation events, and module health without requiring the full CRM authorization model.

### Reader vertical slice

The first implementation should prove:

1. a first-party module descriptor and service registration;
2. per-workspace enable and disable state;
3. workspace-scoped feeds and entries;
4. RSS/Atom and newsletter ingestion with provenance;
5. module navigation and permissions;
6. permission-filtered search and VFS projection;
7. a small set of dynamically discoverable agent capabilities;
8. `reader.entry.received` and `reader.entry.matched_rule` events;
9. an automation that proposes or creates a task/document under policy;
10. module health, refresh history, failure visibility, and retention controls;
11. export and safe disable behavior; and
12. focused feature, permission, queue, and agent-tool tests.

It should not initially include every Allbase Reader feature. OPML, canonical grouping, newsletter
inboxes, rules, saved searches, digests, webpage extraction, and topic feeds should be sequenced only
after the module boundary itself works.

## Implementation sequence

### Phase 0: protect the foundation

Do not let module expansion bypass the current authorization roadmap. Complete or explicitly account
for workspace scoping, human resource ACLs, agent-management capabilities, approval authority, and
the authorization ledger before exposing optional-module mutations to agents.

### Phase 1: introduce an internal module registry

- Define a stable module ID and descriptor interface.
- Add per-workspace enablement and configuration state.
- Derive navigation and capability availability from the registry.
- Define lifecycle states such as installed, enabled, disabled, unhealthy, and upgrade-required.
- Keep all modules trusted and first-party.

### Phase 2: retrofit existing applications

Register Tasks/Lists, Tables, Documents, Calendar, and Files through the internal contract without
extracting them into Composer packages. This proves that the contract describes existing behavior
rather than only a new greenfield module.

### Phase 3: implement Reader as the first optional module

Build the smallest end-to-end Reader slice described above. Use Allbase as behavioral and test input,
but implement the feature in OpenCompany's workspace, authorization, queue, Vue, search, VFS, event,
and agent-runtime architecture.

### Phase 4: establish the resource and event fabric

- Stabilize typed resource references.
- Register permission-filtered resource descriptors.
- Standardize comments, attachments, links, activity, search, VFS, and automation subjects.
- Version module events and authorized cross-module actions.

### Phase 5: graduate additional applications

Recommended order:

1. Forms plus webhook inboxes;
2. Reports;
3. Imports and public portals; and
4. CRM after resource permissions and cross-module actions are proven.

### Phase 6: package the product

Create curated workspace templates or packs for Research, Sales, Content, and Customer Operations.
Measure activation and recurring workflow value before expanding the module catalog further.

### Phase 7: consider third-party modules

Only after several first-party modules have exercised install, enable, disable, upgrade, health,
permissions, agent capabilities, migrations, export, and recovery should OpenCompany define a public
module SDK or marketplace.

## Risks and controls

| Risk | Control |
|---|---|
| Becoming another generic everything-suite | Lead with narrow workflow packs and measurable outcomes |
| Navigation and configuration overload | Per-workspace enablement, role-aware navigation, curated defaults, and unified command/search surfaces |
| Agent tool explosion | Permission- and relevance-filtered capability discovery with lazy detail loading |
| Inconsistent authorization | Kernel-owned permission, approval, and audit contracts; no module-local shortcuts |
| Cross-module schema coupling | Typed resource references, events, and authorized application actions |
| Migration and disable failures | Explicit lifecycle, health, export, retention, disable, and uninstall contracts |
| Many shallow applications | Graduation gates and a small first-party sequence rather than catalog-count goals |
| Plugin supply-chain risk | Trusted first-party modules first; defer untrusted runtime loading |
| Duplicating Allbase architecture | Reimplement graduated behavior on OpenCompany foundations instead of merging frameworks |
| Licensing uncertainty | Resolve Allbase ownership and license before copying substantial implementation |
| Distracting from runtime hardening | Keep current authorization and durable-run priorities ahead of broad module expansion |

## Graduation gates for an Allbase capability

An Allbase concept should graduate only when all of the following are true:

1. It supports a concrete OpenCompany workflow or validated customer need.
2. Existing OpenCompany behavior has been compared to avoid duplication.
3. The resource ownership and workspace boundary are explicit.
4. Human and agent permissions are defined.
5. Read, write, destructive, and external actions have approval classifications.
6. Events, search, VFS, API, and agent capability behavior are deliberately scoped.
7. Enable, disable, retention, export, and failure semantics are known.
8. The UI fits OpenCompany's design and does not import the Allbase shell.
9. Focused tests cover workspace isolation, permission failures, and critical behavior.
10. Code provenance and licensing permit the chosen degree of reuse.

This makes Allbase a useful source of evidence without turning prototype breadth into automatic
OpenCompany scope.

## Current decisions

As of 2026-08-25:

- OpenCompany's target is a modular company operating system for humans and AI teammates.
- The organizational kernel remains authoritative for workspace, identity, permissions, approvals,
  audit, agents, memory, integrations, and shared resource contracts.
- Tasks/Lists, Tables, Documents, Calendar, and Files are foundational applications.
- CRM, Forms, Reader, Webhook Inboxes, Reports, and other business applications are optional
  first-party module candidates.
- Allbase is a playground and business-module laboratory, not a fourth production platform.
- Allbase will not be merged wholesale into OpenCompany.
- Graduated behavior will be implemented in OpenCompany's Laravel/Vue, multi-workspace, permission,
  queue, search, VFS, and agent-runtime architecture.
- Reader is the recommended first optional module experiment.
- Breadth will be packaged through narrow workflow packs rather than marketed as an unbounded suite.
- A third-party plugin/module runtime is deferred until first-party module contracts are proven.

## Verification boundary and sources

This document used static local source inspection plus read-only Git and authenticated GitHub
repository/code searches. It did not start either application, inspect private Allbase database
contents, execute Allbase tests, query an OpenCompany deployment, or validate external providers.

OpenCompany sources:

- [Current State](../CURRENT_STATE.md)
- [Roadmap and Open Audits](../ROADMAP.md)
- [OpenCompany README](../../README.md)
- `app/Models`
- `app/Domain`
- `app/Agents`
- `resources/js/Pages`

Allbase sources from the sibling checkout at `/Users/rutger/Sites/allbase`:

- `docs/overview.md`
- `docs/modules.md`
- `docs/cross-cutting-features.md`
- `docs/roadmap.md`
- `docs/pricing.md`
- `docs/app-opportunity-map.md`
- `docs/competitor-gap-checklist.md`
- `composer.json`
- `package.json`
- `routes/web.php`
- `routes/api.php`
- `routes/ai.php`
- `app/Models`
- `app/Support`
- `app/Mcp`
- `resources/views/pages`
- `database/migrations`
- `tests`

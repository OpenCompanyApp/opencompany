# Grok Bot Investigation for OpenCompany

> **Direction status (2026-11-02):** Aligned with [Product Direction](../PRODUCT_DIRECTION.md) — evidence for the "Grok Bot for companies" decision; its "Automations → Routines" recommendation is adopted as pillar 9.

Status: Research and checkout-grounded product/architecture comparison.
Verified: 2026-08-17 against the public Grok Bot launch material, the signed macOS 0.18.0 distribution, and the active OpenCompany working tree.

## Executive conclusion

Grok Bot's main accomplishment is not a new individual runtime primitive. It combines persistent
agents, memory, schedules, approvals, computer use, delegation, and mobile supervision into one
convincing coworker experience:

> Hire a named bot, give it work, teach it routines, and supervise it while it operates a computer.

OpenCompany already has the stronger foundation for governed company operations: canonical
workspace data, persistent agent identities, durable tasks and runs, scoped tools, approvals,
multi-provider model selection, cost records, and auditable agent handoffs. Its main disadvantage is
that these capabilities do not yet appear as one coherent, immediately understandable teammate
experience.

OpenCompany should therefore copy Grok Bot's **product legibility and supervisory experience**, not
its computer-first runtime architecture:

1. Turn existing Automations into visible agent-owned **Routines**.
2. Let users convert a successful task and its recorded tool steps into a reviewed routine draft.
3. Add a team-floor view showing each agent's responsibility, current task, handoffs, approvals,
   next wake-up, results, and spend.
4. Make multi-agent handoffs visible in shared rooms before advertising autonomous swarms.
5. Add evidence-backed memory proposals with explicit scope, provenance, review, and deletion.
6. Move browser takeover higher in the roadmap, but retain per-workspace or per-session isolation and
   prefer structured tools whenever they exist.
7. Preserve provider independence, governed canonical company data, and transparent economics as
   OpenCompany's differentiation.

OpenCompany should **not** pivot into a platform where multiple agents share one mutable computer,
browser profile, or credential surface. APIs, MCP, VFS, and application-owned records should remain
the primary execution path. Browser or desktop interaction should be a controlled fallback for work
that genuinely cannot be completed through a deterministic interface.

## Evidence and confidence boundary

This investigation separates three evidence classes:

1. **Official facts:** statements published by SpaceXAI/xAI and Cursor.
2. **Client evidence:** static inspection of the publicly downloadable, signed Grok Bot macOS
   application. This reveals shipped components and product strings, but it cannot prove server-side
   implementation details or production behavior.
3. **OpenCompany facts:** code and current documentation in the active local checkout. The working
   tree contains uncommitted implementation work, so a checkout-level capability is not necessarily
   committed to `main`, deployed, or live-verified.

Community feedback is included only as anecdotal early-beta evidence. It is not treated as a
representative benchmark.

## What launched

SpaceXAI announced Grok Bot on 2026-08-11 as an early beta developed with Cursor. The official
product framing is an always-on team of named AI coworkers that can:

- use a cloud computer, browser, applications, files, and terminal;
- work asynchronously and report back;
- operate applications that do not expose APIs or MCP servers;
- remember preferences and working patterns;
- coordinate through bot-to-bot messages, shared threads, and group rooms;
- use a chief-of-staff bot to coordinate other bots;
- learn a demonstrated task and retain it as a routine;
- request approval or human takeover when necessary; and
- be supervised from desktop or phone.

Official sources:

- [Introducing Grok Bot](https://x.ai/news/introducing-grok-bot)
- [Grok Bot product page](https://x.ai/bot)

As of 2026-08-17, the public product page lists access through:

| Plan | Listed price |
|---|---:|
| Cursor Ultra | USD 200 per month |
| SuperGrok Heavy | USD 300 per month |
| Cursor Teams Premium | USD 120 per seat per month |

These are current public prices, not durable product contracts. They should be rechecked before use
in positioning or commercial material.

## Product model

Grok Bot treats the agent as the primary product object. A user creates or selects a named bot,
messages it like a coworker, gives it access to a computer and applications, and returns to the
conversation to inspect progress or take control.

The product vocabulary is deliberately human:

- bots have names and roles;
- bots work on assignments;
- a chief of staff coordinates other bots;
- users teach tasks rather than design workflow graphs;
- recurring work becomes a routine;
- the user can take over the computer and hand it back; and
- shared rooms make the team appear to work together.

That vocabulary hides infrastructure without hiding the work itself. The user sees the assignment,
computer, conversation, approval, result, and future routine rather than a collection of unrelated
agent-platform settings.

## How Grok Bot appears to work

The observable product architecture is approximately:

```text
Desktop or mobile conversation
  -> named persistent bot
  -> agent coordinator
  -> shared persistent cloud computer
       -> browser and web applications
       -> files and terminal
       -> connectors and MCP tools
       -> local-computer execution channel
       -> live view and human takeover
  -> transcript and synchronized agent state
  -> verified memory changes
  -> saved routine or next scheduled run
```

### Desktop client evidence

The reviewed distribution was the Apple Silicon macOS release:

- Version: `0.18.0`
- Bundle identifier: `com.anysphere.sand`
- Application type: Electron
- Distribution size: 155,793,020 bytes
- SHA-256: `a253ccd8aab01e083f9812a0264354c5034d8ba7f0610bbb557e82ae77d203eb`
- Signature: Developer ID Application for Anysphere Incorporated
- Gatekeeper result at inspection time: accepted and notarized
- Product copyright string: SpaceXAI, 2026

The application identifies its internal product name as `sand` and includes Cursor/Anysphere agent
runtime packages. Shipped components and dependencies indicate support for:

- agent coordination and agent workflows;
- isolated agent store workers;
- transcript mirroring and store synchronization;
- agent execution, MCP-backed execution, shell execution, and local execution;
- chat inference and context management;
- memory summarization and synthesis;
- routines and automation state;
- shared rooms and agent invitations;
- VNC/noVNC computer viewing and control;
- clipboard synchronization and presence/liveness state;
- WebAuthn proxying for interactive authentication;
- tool permissions and approval prompts;
- error/telemetry collection; and
- plugin and connector surfaces inherited from the Cursor ecosystem.

These components establish that the computer, takeover, transcript, approval, coordination, and
memory experiences are represented in the shipping client. They do not establish the exact cloud
topology, model-routing policy, tenant-isolation design, or server-side retention rules.

### One shared computer, not one isolated machine per bot

The launch copy can be read as if every bot receives an independent computer. The shipping client is
more explicit: it describes updating “the shared computer all your agents run on” while preserving
their files and logins.

The most likely user-level topology is therefore:

```text
One user or team
  -> one persistent virtual computer
       -> multiple named agents
       -> shared filesystem and machine resources
       -> separate browser profiles or logical sessions where configured
```

This is an important architectural distinction. Shared state makes collaboration and application
access easy, but it also creates contention, credential-boundary, browser-profile, cleanup, and
cross-agent leakage risks. OpenCompany should not treat a shared machine as its security boundary.

### Computer use and takeover

The client contains VNC-specific preload and user-interface behavior for:

- viewing the bot's computer;
- taking control;
- synchronizing the clipboard;
- showing computer/action state;
- proxying WebAuthn interactions; and
- handing control between bot and human.

This enables a useful login pattern: the bot navigates to the authentication boundary, the human
takes over for password, passkey, or one-time-code entry, and the bot resumes afterward. It also
lets a user repair an interaction without discarding the bot's session.

### Tools and approvals

The client exposes approval language equivalent to:

- allow this time;
- always allow;
- ask every time;
- approval needed; and
- the action was not allowed.

It also includes local-computer command permission states. This is an important part of the product
experience: autonomy is paired with an immediate supervisory control instead of being configured
only in an administrative settings page.

The static client does not prove how granular an “always allow” decision is, how long it lasts,
whether it is resource-specific, or whether a separate approver role can be required.

### Memory

Official launch material says bots remember how the user works and can use that context later. The
client adds evidence of a more deliberate synthesis path:

- proposed memory changes can be rejected;
- a proposal is invalid when it cannot be parsed;
- evidence references are checked against known source material;
- the current memory state can reject a proposed change; and
- stale evidence causes work to be requeued rather than silently accepted.

This is more useful inspiration than the vague claim that an agent “learns everything.” It suggests
memory is treated as proposed durable state with verification rather than direct transcript dumping.

### Routines and task teaching

The launch presents a “show it once” flow: the user demonstrates a task and the bot saves it as a
routine. The client contains `teach-recording` and scheduled-routine state, supporting the existence
of that product path.

SpaceXAI's separate [Grok Automations announcement](https://x.ai/news/grok-automations) provides
useful context for the broader automation model:

- natural-language instructions;
- schedules or email triggers;
- connector and skill selection;
- a fresh conversation with current data for each run;
- Run now for testing;
- full run history and resumable conversations;
- configurable notifications; and
- pause, resume, edit, and delete controls.

Grok Automations and Grok Bot should not be assumed to share an identical backend merely because
their concepts overlap. The interaction model is nevertheless a strong reference for how recurring
agent work can be made legible without exposing cron expressions or workflow-engine internals.

### Multi-agent coordination

Official materials describe multiple bots working in parallel, bots messaging one another, shared
threads, group chat, and a chief-of-staff role. The client includes:

- shared-room state;
- agent roster and workflow events;
- subagent and asynchronous-task state;
- room invitations and approvals; and
- privacy instructions warning agents not to reveal private one-to-one chats, memory files, user
  files, credentials, tokens, or connector data in shared rooms.

The presence of these controls is meaningful, but it does not prove unlimited concurrency. A shared
computer naturally creates resource contention even if reasoning runs are parallel.

## Early operational feedback

One early Reddit report from a business user running six bots described:

- Chrome profiles resetting daily;
- repeated reauthentication;
- frequent Chrome crashes;
- rapid use of the weekly allowance;
- only one or two agents appearing to work concurrently; and
- browser-based email and Teams feeling slower than native integrations.

The same report praised:

- personalized rather than sequence-based sales follow-up;
- understanding and continuing customer conversations;
- finding and sending requested material; and
- reaching applications that have no usable API.

Source: [early Grok Bot business-user report](https://www.reddit.com/r/grok/comments/1vob5q2/grok_bot/).

This is anecdotal and cannot establish normal reliability. It does illustrate the central product
tradeoff: browser access expands reach quickly, but the browser becomes part of the reliability,
identity, isolation, and cost model.

## Security and privacy questions

Cursor's general [security page](https://cursor.com/security) documents SOC 2 Type II availability,
annual penetration testing, least-privilege infrastructure access, model blocklists, agent security
guidance, and Privacy Mode. It states that data is not used for training when Privacy Mode is
enabled.

That general Cursor material does not fully answer Grok Bot-specific questions across both Cursor
and SuperGrok purchase paths. The launch material does not make the following boundaries sufficiently
clear for enterprise evaluation:

- whether every bot or team receives a separate VM or container boundary;
- exactly which agents share a filesystem, browser state, and machine credentials;
- retention and deletion behavior for transcripts, memory, screenshots, recordings, and downloads;
- whether Grok Bot data is eligible for training under each subscription path;
- cookie and browser-profile lifecycle;
- encryption and customer-managed-key availability;
- network egress and domain policy;
- exact action-audit granularity;
- separation of agent permission from human approver capability; and
- administrative export, SIEM, residency, and incident-response behavior.

These omissions are themselves product inspiration: OpenCompany can make isolation, provenance,
authorization, retention, cost, and action history explicit instead of requiring customers to infer
them from product behavior.

## Current OpenCompany baseline

The comparison below uses the active checkout and the status boundaries in
[Current State](../CURRENT_STATE.md). That document explicitly covers uncommitted local work and does
not imply that every feature is on `main`, deployed, configured, or externally live-verified.

### Persistent agents and company context

OpenCompany agents are workspace members with durable identities, configuration, model/provider
selection, tools, tasks, conversations, and private memory. The main runtime assembles identity,
workspace and tool context in
[`OpenCompanyAgent`](../../app/Agents/OpenCompanyAgent.php).

This differs from Grok Bot's computer-centered context. OpenCompany's primary context is the
company's governed application state: channels, messages, tasks, documents, files, lists, tables,
automations, approvals, integrations, and agent memory.

### Durable execution

The working tree contains a durable control plane built around `Task`, `AgentRunRecord`, heartbeats,
semantic attempts, pause/cancel, stale-run reconciliation, per-agent serialization, checkpointed tool
calls, and an agent-tool execution ledger. See the
[Agent Run Queue Control Plane](agent-run-queue-control-plane.md).

That foundation is stronger than a transcript-only automation model because it gives OpenCompany a
place to represent execution state, uncertain outcomes, retries, cancellation, and operator repair.
The current physical queue still lacks workload-class latency isolation.

### Multi-agent work

The [`ContactAgent`](../../app/Agents/Tools/Agents/ContactAgent.php) tool supports:

- `ask`, with an asynchronous answer delivered back to the requester;
- `delegate`, with a durable child task and callback; and
- `notify`, with an independent task and no callback.

The receiving agent does not automatically inherit the requester's full conversation. Required
context must be passed deliberately. This is a good privacy and reasoning boundary.

Direct dependency plans are currently executed sequentially by
[`SubagentOrchestrator`](../../app/Agents/Runtime/Subagents/SubagentOrchestrator.php). OpenCompany does
not yet have a concurrent branch scheduler, a complete child-run supervisory interface, or an
equivalent of Grok Bot's shared-room coordination experience.

### Memory

OpenCompany currently has durable memory files, indexing, embedding cache, pgvector and PostgreSQL
full-text retrieval, weighted search, reranking, compaction, and pre-compaction memory flush. The
agent prompt distinguishes private memory from participant-specific peer context.

Dream consolidation, temporal decay, and cross-log deduplication remain planned. Grok Bot's
evidence-checked synthesis model is directly relevant to that future work.

### Automations

OpenCompany already supports scheduled prompt and Lua-script automations, manual runs, durable run
control, run history, dedicated automation conversations, optional history clearing, and automatic
deactivation after repeated failure. See [`Automation`](../../app/Models/Automation.php).

General event and polling triggers, endpoint automations, multi-step graphs, and one-shot lifecycle
are not first-class. More importantly, existing functionality is presented as an automation
subsystem rather than as each agent's understandable set of recurring responsibilities.

### Integrations and tools

The generated integrations catalog contains hundreds of integrations and tens of thousands of tool
definitions. OpenCompany supports workspace credentials, multiple accounts, MCP clients, web search
and fetch, internal tools, and VFS-projected company resources.

This is a strategic advantage over browser-first operation: structured tools can be faster, cheaper,
more reliable, more narrowly permissioned, and easier to audit. A catalog entry does not prove that
credentials are configured or that every provider path is live-hardened.

### Approvals

OpenCompany's [`ApprovalExecutionService`](../../app/Services/ApprovalExecutionService.php) records a
human decision atomically, ensures that only one callback applies the side effect, rebinds workspace
context, and rechecks the agent's permission immediately before executing an approved tool.

The current product still lacks a complete approver-role model, no-self-approval enforcement,
approval profiles, and a complete human authorization ledger. Those are P0 items in the
[Roadmap](../ROADMAP.md) and must precede broader autonomous-computer claims.

### Provider choice and economics

OpenCompany owns its provider/model catalog and records usage and cost information. It is not bound
to a single model provider and can support BYOK, cheaper models, or local models where appropriate.

Cumulative budgets, per-lane budgets, ROI reporting, and a complete LLM FinOps experience remain
unfinished. The direction is documented in
[AI Economics and Proactive Teammates](../planning/ai-economics-and-proactive-teammates.md).

### Browser and computer use

Embedded browser sessions and human takeover are explicitly not current features. The existing
[browser investigation](embedded-chromium-browser-investigation-2026-05-24.md) already proposes the
right core boundary:

- an app-owned `BrowserRuntime`;
- provider adapters behind `BrowserSessionProvider`;
- workspace-scoped sessions;
- one active control owner;
- high-level tools plus a separately permissioned advanced Playwright path;
- human takeover and hand-back;
- encrypted provider metadata;
- redaction and action logging; and
- no browser-profile reuse across workspaces.

Grok Bot validates the product value of this direction but also strengthens the argument for strict
isolation and structured-tool preference.

## Detailed comparison

| Area | Grok Bot | OpenCompany | Assessment |
|---|---|---|---|
| Primary product object | Named bot with a computer | Persistent agent inside a company workspace | Grok Bot is easier to understand immediately; OpenCompany has richer governed context |
| Execution center | Shared computer, browser, files, terminal, connectors | Structured tools, integrations, MCP, VFS, scripts, canonical records | Keep OpenCompany tool-first and add computer use as fallback |
| Durable work | Conversations, routines, computer state | Tasks, run records, attempts, checkpoints, cancellation, reconciliation | OpenCompany has the stronger control-plane foundation |
| Multi-agent | Shared rooms, bot messaging, chief-of-staff presentation | Ask/delegate/notify, child tasks, callbacks, sequential dependency plans | OpenCompany needs coordination UX and safe parallel branches |
| Memory | Automatic learning with evidence of verified synthesis | Explicit private memory tools, indexed files, retrieval, compaction | Adopt evidence-backed proposals without silent cross-channel learning |
| Automation | Teach a task, save a routine, schedule and supervise it | Prompt/script automations with schedules, manual runs and history | Productize existing functionality as agent-owned Routines |
| Browser | Core execution surface with VNC takeover | Planned browser runtime only | Valuable gap, but security work must precede it |
| Approvals | Immediate computer/tool permission prompts | Atomic approval, permission recheck, durable execution context | OpenCompany backend is strong; RBAC and UX remain incomplete |
| Company data | Files, browser sessions and connectors centered around the computer | Canonical workspace records and explicit workspace ownership | OpenCompany's model is better for long-lived company operations |
| Integrations | Browser can reach almost any application; MCP/connectors also present | Very broad structured integration catalog, credentials and MCP client | API-first is more reliable; browser closes the long tail |
| Mobile | Native mobile supervision is part of the pitch | Telegram/Mini App is the strongest remote operator surface | Finish mobile supervision through Telegram before requiring a native app |
| Providers | Grok/Cursor-controlled experience | App-owned multi-provider catalog | Provider independence is an OpenCompany differentiator |
| Economics | High subscription tiers and opaque allowance consumption | Usage/cost ledger but incomplete budgets and ROI interface | Make cost, limits and business outcomes explicit |
| Extensibility | Cursor ecosystem, connectors, MCP and plugin surfaces | Integration packages, MCP client, planned skills/plugins/Artifacts | Preserve governed, portable capability packaging |
| Deployment | Managed cloud experience | Self-hosted application with a prospective AGPL-3.0-only release; broader managed cloud is planned | Differentiate on control while avoiding unverified enterprise claims |

## Ideas OpenCompany should adopt

### Priority matrix

| Idea | User value | Relative effort | Recommendation |
|---|---:|---:|---|
| Productize Automations as agent Routines | Very high | Low-medium | Build first after P0 gates |
| Save a successful task as a routine draft | Very high | Medium | Build on TaskStep and tool-ledger evidence |
| Agent team floor / mission control | Very high | Medium | Build alongside Routines |
| Governed agent packs | High | Medium | Package identity, tools, routines, budgets and outputs |
| Structured shared-room handoffs | High | Medium | Extend existing channels and ContactAgent |
| Evidence-backed memory synthesis | High | Medium-high | Align with Dream memory work |
| Proactive responsibility lanes | High | Medium-high | Add wake reasons, quiet hours and budgets |
| Browser takeover and login handoff | High | High | Build after authorization and budget gates |
| Teach by browser demonstration | High | Very high | Build only after semantic task-to-routine conversion works |
| One shared computer for every agent | Negative | High risk | Explicitly avoid |

### 1. Agent-owned Routines

Reframe Automations as responsibilities belonging to a specific agent. An agent page should show:

- routine name and intended business outcome;
- instructions or script;
- trigger and next run;
- required integrations, resources, and permissions;
- model policy and maximum budget;
- notification and escalation behavior;
- Run now, pause, edit, duplicate, and disable controls;
- current status and consecutive failures; and
- complete run history with the final deliverable and cost.

Internally, the existing Automation model can remain the execution primitive. “Routine” is the
agent-facing product contract.

### 2. Save completed task as routine

OpenCompany should first teach from its own durable work history rather than recording arbitrary
screen coordinates.

Proposed flow:

```text
Successful task or corrected agent run
  -> user selects Save as routine
  -> extract TaskSteps, tool calls, dependencies, approvals, and outputs
  -> identify fixed values versus input variables
  -> prefer deterministic script/tool calls where possible
  -> generate a versioned routine draft
  -> show required permissions, trigger, budget, and expected result
  -> replay in test mode
  -> human reviews and publishes
```

Important invariants:

- Never publish directly from one opaque model inference.
- Remove secrets and transient identifiers from the draft.
- Store typed tool/resource references instead of screen coordinates where possible.
- Separate read and mutation permissions.
- Record the source task and evidence used to build the draft.
- Detect unavailable integrations and changed tool schemas before each run.
- Fail loudly when a precondition or expected postcondition is not met.
- Version every published routine and retain rollback history.

Later, browser demonstrations can compile into the same representation. The compiler should replace
observed browser actions with structured tools when an equivalent integration exists.

### 3. Team floor and mission control

Create one supervisory view containing an agent roster with:

- role and owned responsibility;
- awake, sleeping, waiting, paused, failed, or working state;
- current task and elapsed time;
- active browser/tool/session when applicable;
- last meaningful action and latest deliverable;
- requested and pending approvals;
- incoming and outgoing handoffs;
- next scheduled routine or wake event;
- current spend, budget, and escalation; and
- direct controls for message, pause, cancel, take over, and inspect history.

This would make existing runtime state feel like a working team instead of a set of disconnected
administrative records.

### 4. Governed agent packs

Grok Bot uses recognizable roles such as chief of staff and sales support. OpenCompany should adapt
that idea as versioned agent packs rather than prompt presets.

An agent pack should define:

- identity and responsibility;
- recommended model policy;
- required and optional tools;
- resource-scoped integration requirements;
- initial routines and wake rules;
- output templates;
- default budget and escalation thresholds;
- approval policy requirements;
- success metrics; and
- installation/update behavior.

Credentials, connected accounts, live memory, schedules, private data, approval decisions, and run
history must never be embedded in a portable pack. Installation resolves those requirements against
workspace-local resources.

### 5. Structured handoff rooms

Extend channels and `ContactAgent` with visible handoff cards containing:

- requester and receiving agent;
- ask, delegate, or notify semantics;
- purpose and acceptance criteria;
- explicitly shared context;
- parent and child task relationship;
- priority and budget;
- current status;
- expected callback behavior; and
- final output or failure.

Private one-to-one context and memory should remain excluded unless intentionally summarized into the
handoff. Shared-room prompts are helpful defense in depth, but server-side ACL and context assembly
must enforce the boundary.

Before marketing parallel swarms, implement:

- safe dependency-graph branches;
- per-agent and per-workspace concurrency limits;
- child-run visibility;
- cancellation propagation;
- budget propagation;
- deterministic aggregation; and
- explicit partial-failure behavior.

### 6. Evidence-backed memory proposals

Treat long-term memory as reviewed durable state:

```text
Conversation, task, or routine outcome
  -> candidate fact or preference
  -> source citations and scope classification
  -> conflict, freshness, privacy, and duplication checks
  -> approve automatically under policy or request review
  -> versioned memory mutation
  -> later correction, expiry, or deletion
```

Each proposal should include:

- proposed content;
- private, team, workspace, or public scope;
- source message/task/run identifiers;
- confidence and expiry/freshness expectation;
- conflicts with existing memory;
- reason it is useful; and
- the agent or policy that approved it.

Agents must not silently move facts from private conversations or connected systems into shared
memory.

### 7. Proactive responsibility lanes

Grok Bot validates the idea that a coworker should own work rather than wait for every mention.
OpenCompany's planned lane model should give each agent:

- a responsibility boundary;
- deterministic and model-based wake rules;
- quiet hours;
- a maximum wake frequency;
- per-run and cumulative budgets;
- an explicit reason for waking;
- escalation rules; and
- a receipt when it acts or intentionally stays quiet.

Cheap deterministic filters, search, embeddings, or small models should run before an expensive
agent turn. Proactivity should never mean an unbounded polling loop.

### 8. Browser takeover and login handoff

The browser should be a first-class, governed resource rather than a hidden sidecar:

- one `BrowserSession` belongs to a workspace and optionally an agent/task;
- one actor controls it at a time;
- credentials and CDP/provider URLs never enter model context;
- high-risk actions require approval;
- typed values, cookies, headers, storage, tokens, and payment data are redacted;
- every action is tied to the agent, task, approval, and human operator;
- retention and cleanup policies are explicit;
- sessions have domain, network, duration, and concurrency policies; and
- persistent profiles are opt-in and isolated by workspace or narrower risk lane.

Preferred execution order:

1. Structured integration or internal OpenCompany tool.
2. Search or fetch adapter.
3. Browser automation for JavaScript-rendered or interactive work.
4. Visual computer use only when semantic browser control is insufficient.
5. Human takeover for authentication or ambiguous/high-impact interaction.

### 9. Mobile supervision through Telegram first

Grok Bot's phone experience is valuable because it turns mobile into a supervisor console, not a
second full workstation. OpenCompany can reach much of that value through its existing Telegram and
Mini App direction:

- message an agent;
- see current work and recent outcomes;
- approve or reject actions;
- pause or cancel a run;
- run or pause a routine;
- receive a concise proactive alert; and
- open a signed takeover link when browser interaction is required.

This should be completed before committing to a separate native application.

### 10. Transparent agent economics

Grok Bot's relatively high subscription tiers and early reports of rapid allowance use create an
opening for OpenCompany. Every teammate and routine should expose:

- cost per task and outcome;
- cost per model and provider;
- cached versus uncached tokens;
- browser/computer resource cost;
- retries and recovery overhead;
- budget remaining;
- reason for expensive-model escalation; and
- optional human-time-saved or business-outcome notes.

Provider independence and transparent economics should remain product-level differentiators, not
hidden infrastructure characteristics.

## Ideas OpenCompany should avoid or constrain

### Do not make one shared computer the security boundary

Agents should not automatically share cookies, local storage, downloads, filesystem state, SSH
configuration, or application credentials. Shared workspace data should flow through governed
OpenCompany resources. Deliberately shared files should use VFS/storage ACLs rather than ambient disk
access.

### Do not default to GUI automation

Browser operation is slower, more expensive, harder to test, and more fragile than structured APIs.
It is valuable for the long tail of applications, not as the default path for every integration.

### Do not silently learn a user's style

Automatic memory needs evidence, scope, retention, correction, and deletion. “It learns how you
work” is not an adequate privacy or correctness contract.

### Do not overclaim 24/7 parallel autonomy

OpenCompany should not market continuous work or swarms until it has production evidence for queue
isolation, parallel scheduling, recovery, budget enforcement, cancellation, monitoring, and operator
intervention.

### Do not use broad permanent permission toggles

An “always allow” decision should identify the exact capability, resource, action class, agent,
workspace, duration, spend ceiling, and approver. High-impact actions should remain ineligible for
ambient approval.

### Do not make cookies the integration layer

Managed OAuth, resource-specific grants, typed tools, and provider APIs should remain preferable.
Browser login state is an escape hatch and must have explicit lifecycle and revocation controls.

### Do not sacrifice provider independence

Agent packs, routines, memory, tasks, and company records should remain portable across model
providers. Provider-specific computer-use models belong behind runtime adapters and capability
metadata.

### Do not hide usage behind an opaque allowance

Show actual cost and work receipts. Allow budgets and policies at workspace, agent, lane, routine,
task, provider, and model level.

## Recommended implementation sequence

### Gate 0: complete existing trust foundations

Before expanding autonomy:

1. Finish the P0 human/agent authorization model.
2. Enforce channel and document ACLs consistently.
3. Add explicit approval capability and no-self-approval policy.
4. Complete the append-only authorization decision ledger.
5. Stabilize and verify the dirty agent-run control-plane work.
6. Add cumulative workspace and agent budget enforcement.

### Phase 1: make existing agents feel like teammates

1. Add role/responsibility and outcome language to agent onboarding.
2. Productize existing Automations as agent-owned Routines.
3. Add Run now, pause, next run, history, result, cost, and failure visibility.
4. Create the team-floor mission-control view.
5. Add initial governed agent packs for a few narrow jobs.

### Phase 2: teach from durable OpenCompany work

1. Add Save completed task as routine.
2. Compile TaskSteps and tool executions into a typed draft.
3. Add variable extraction, permission manifests, budgets, test mode, and versioning.
4. Add event triggers and one-shot routines after scheduled routines are coherent.

### Phase 3: make multi-agent work observable

1. Add structured handoff cards and shared coordination rooms.
2. Add a complete parent/child run interface.
3. Introduce safe parallel dependency branches.
4. Add concurrency, cancellation, partial-failure, and budget propagation policies.
5. Add chief-of-staff templates only after the runtime behavior is real.

### Phase 4: proactive and evidence-backed agents

1. Add memory proposals with source evidence, scope, conflict checks, and review.
2. Add owned responsibility lanes and wake rules.
3. Add quiet hours, digests, reason-for-wake receipts, and lane budgets.
4. Finish the Telegram/Mini App supervisor experience.

### Phase 5: isolated browser and computer operation

1. Implement the app-owned browser session/runtime contracts.
2. Add live view, action timeline, takeover, and hand-back.
3. Add login handoff and WebAuthn-compatible flows without exposing secrets to the model.
4. Add managed/self-hosted provider adapters and explicit resource accounting.
5. Compile browser demonstrations into reviewed routine drafts.
6. Add visual computer-use models only for interactions semantic browser tools cannot perform.

## Product-positioning consequence

Grok Bot's strongest message is “AI coworkers with computers.” OpenCompany should not answer with
“the same thing, self-hosted.” Its more durable positioning is:

> OpenCompany is the governed operating layer where AI teammates own recurring company work, use
> the right model and tools, coordinate with each other, ask for approval, leave complete receipts,
> and operate within explicit data and cost boundaries.

The computer becomes one tool available to a teammate, not the place where the company lives.

## Decisions recommended from this investigation

1. **Adopt:** Routines as the agent-facing wrapper over existing Automations.
2. **Adopt:** Save completed task as routine before browser-based teaching.
3. **Adopt:** Team-floor and structured-handoff supervisory UX.
4. **Adopt:** Governed agent packs rather than prompt-only role templates.
5. **Adopt:** Evidence-backed memory proposals.
6. **Adopt:** Proactive responsibility lanes with budgets and wake receipts.
7. **Adapt:** Browser takeover through the already planned app-owned runtime and strict isolation.
8. **Adapt:** Mobile supervision through Telegram/Mini App before a native client.
9. **Reject:** A shared mutable computer as the primary data, credential, or security boundary.
10. **Reject:** Browser-first execution when a deterministic tool exists.
11. **Reject:** Silent cross-channel learning and broad permanent permissions.
12. **Preserve:** Provider independence, canonical workspace records, explicit approvals, auditability,
    and transparent economics.

## Open questions before implementation

1. Should “Routine” replace “Automation” in the product UI or be a higher-level agent-owned object
   backed by one or more Automations?
2. Which completed TaskStep patterns are deterministic enough for the first task-to-routine compiler?
3. Should agent packs be implemented through the future Skills model, Artifacts/templates, or a
   separate installable agent-definition record?
4. What is the minimum child-run and handoff UI required before adding parallel branches?
5. Which memory proposal classes may auto-approve, and which always require a human?
6. Is the Telegram Mini App sufficient for first browser takeover, or is a responsive web supervisor
   surface required first?
7. Should persistent browser profiles be scoped per workspace, per agent, per connected account, or
   per task/risk lane?
8. What budget must cover browser-compute time in addition to LLM tokens?
9. Which role pack provides the strongest narrow managed-pilot wedge?
10. Which public product claims must wait until the P0 authorization and runtime rehearsal work is
    complete?

## Sources

### External

- SpaceXAI, [Introducing Grok Bot](https://x.ai/news/introducing-grok-bot), 2026-08-11.
- SpaceXAI, [Grok Bot](https://x.ai/bot), accessed 2026-08-17.
- SpaceXAI, [Automations in Grok](https://x.ai/news/grok-automations), 2026-07-16.
- Cursor, [Security](https://cursor.com/security), updated 2026-04-24 and accessed 2026-08-17.
- Reddit, [early Grok Bot business-user report](https://www.reddit.com/r/grok/comments/1vob5q2/grok_bot/), accessed 2026-08-17; anecdotal only.

### OpenCompany

- [Current State](../CURRENT_STATE.md)
- [Roadmap and Open Audits](../ROADMAP.md)
- [Agent Run Queue Control Plane](agent-run-queue-control-plane.md)
- [Embedded Chromium Browser Investigation](embedded-chromium-browser-investigation-2026-05-24.md)
- [AI Economics and Proactive Teammates](../planning/ai-economics-and-proactive-teammates.md)
- [`OpenCompanyAgent`](../../app/Agents/OpenCompanyAgent.php)
- [`ContactAgent`](../../app/Agents/Tools/Agents/ContactAgent.php)
- [`SubagentOrchestrator`](../../app/Agents/Runtime/Subagents/SubagentOrchestrator.php)
- [`Automation`](../../app/Models/Automation.php)
- [`ApprovalExecutionService`](../../app/Services/ApprovalExecutionService.php)
- [`CheckpointToolCall`](../../app/Listeners/CheckpointToolCall.php)

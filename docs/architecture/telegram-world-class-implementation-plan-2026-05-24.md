# Agent-First Telegram Reset Implementation Plan

Phased implementation plan for the reset target described in
[telegram-world-class-implementation-overview-2026-05-24.md](telegram-world-class-implementation-overview-2026-05-24.md).

The earlier broad "operator cockpit" plan is superseded. Its durable event,
identity, delivery, topic, approval, and media foundations remain valid, but the
visible Telegram UX must be agent-first and small.

## Phase 1: Shrink The Visible Surface

Goal: make Telegram stop presenting itself as the OpenCompany sidebar.

- Register only core default bot commands: `/start`, `/agents`, `/topic`,
  `/status`, `/approvals`, and `/help`.
- Keep `/link` private-scoped for identity linking, and keep `/settings` plus
  `/health` admin-scoped for operator diagnostics.
- Keep stop/resume controls available as contextual buttons when lane work
  exists instead of exposing them in the normal bot command menu.
- Remove broad resource commands from synced command menus: `/files`, `/docs`,
  `/lists`, `/tables`, `/calendar`, `/dashboard`, `/workload`, `/automation`,
  `/digest`, `/notify`, and `/snooze`.
- Keep existing broad handlers as legacy/advanced fallbacks behind
  `telegram.direct_resource_commands_enabled`, defaulting off.
- Update `/help` so it teaches the agent shell, not resource navigation.
- Update `/start` so it shows current agent, topic/lane, run state, and pending
  approvals.

Acceptance:

- `setMyCommands` payloads contain no broad resource commands in default,
  private, or group user scopes.
- `/start` buttons are limited to agent/lane/status/approvals/run controls, plus
  optional Mini App.
- `/help` contains no broad command lists.
- Typed broad resource commands and stale broad command callback tokens default
  to a removed-command notice, not a helper card or product-module card.

## Phase 2: Agent And Topic Switching

Goal: make switching agent/topic the primary control loop.

- Keep `/agents` as the main switcher for current chat/topic default agent.
- Keep `/topic` as the lane state card and mode controller.
- Make topic cards show only the human lane label, default agent, reply
  behavior, observed-context state, and safe actions.
- Ensure group/forum/private-topic routing prefers persisted conversation
  mapping, then explicit agent/topic configuration.
- Keep mention/free/observe/ignore modes, but present them as lane behavior, not
  as provider internals.

Acceptance:

- A user can switch the active lane agent from Telegram.
- A user can inspect and change topic mode from Telegram.
- The same Telegram chat can host multiple topic lanes without state bleeding.

## Phase 3: Media As Agent Context

Goal: make Telegram attachments inputs to agents, not file-manager workflows.

- Preserve download, storage, enrichment, attachment, media-group, voice, image,
  paid-media, and live-photo ingestion.
- Change the default media card to:
  - "Ask agent";
  - "Switch agent";
  - "Topic";
  - "Open files";
  - "Done".
- Keep folder move, create-doc, and attach-to-doc callbacks as advanced hidden
  actions for existing tests, Mini App, or future overflow UI.
- Ensure media enrichment text says "agent-ready" or equivalent rather than
  implying the user must manage files manually.

Acceptance:

- A captured file queues an agent action through the primary button.
- Folder/document management is not shown on the primary capture card.
- Existing advanced callbacks remain authorized and workspace-scoped.

## Phase 4: Run Status And Approvals

Goal: keep high-value operational controls first-class.

- `/status` should summarize current lane, active/queued/paused run state,
  current/default agent, and pending approvals.
- Agent run cards should keep pause/cancel/open controls.
- Approval cards should remain rich, server-authorized, and auditable in stored
  delivery/approval records while keeping default Telegram copy free of trace
  IDs.
- Callback tokens must stay opaque and re-check actor/workspace/policy state.

Acceptance:

- Pending approvals can be resolved from Telegram.
- Active runs can be canceled/resumed from Telegram.
- Delivery records preserve renderer versions and action provenance.

## Phase 5: Mini App As Advanced Drawer

Goal: keep rich OpenCompany browsing available without bloating chat.

- Keep the Mini App entry point when enabled.
- Treat Mini App panels as advanced inspection/operations surfaces for files,
  docs, tasks, automations, diagnostics, and logs.
- Do not make the Mini App the required path for normal chat use.
- Keep `/start` Mini App button optional and visually secondary.

Acceptance:

- Telegram chat remains usable without opening the Mini App.
- Advanced browsing and diagnostics still have a path.

## Phase 6: Remove Legacy Fallbacks When Agent Tools Cover Them

Goal: eventually delete direct resource commands once agent-mediated workflows
are reliable.

- Audit usage of hidden `/files`, `/docs`, `/lists`, `/tables`, `/calendar`,
  `/dashboard`, `/workload`, `/automation`, `/digest`, `/notify`, and `/snooze`.
- Keep them default-off through `telegram.direct_resource_commands_enabled`
  unless an operator explicitly needs the old cards during migration.
- Add agent tools/prompts/tests for the common natural-language replacements.
- Remove or admin-gate each hidden command after its replacement is proven.

Acceptance:

- Users can ask agents to operate OpenCompany resources naturally.
- No broad resource command remains necessary for normal Telegram UX.

## Current Reset Slice

Implemented in the first reset slice:

- synced command menus now use the small agent-first command set;
- `/start` renders "OpenCompany" with agent/lane/work/approval state;
- `/start` buttons no longer expose tasks, dashboard, files, docs, lists,
  tables, automations, or settings;
- `/help` explains the agent shell and omits broad command lists;
- media capture cards use attachment-ready language and tell users to ask the
  agent what to do with the attachment;
- legacy broad resource commands and stale broad command callbacks are
  default-off and route to a removed-command notice unless
  `telegram.direct_resource_commands_enabled` is enabled;
- default cards hide chat IDs, topic IDs, task IDs, raw modes, queue internals,
  and approval audit IDs;
- admin integration UI labels Telegram as an agent shell rather than command
  center.

Remaining work should continue from Phase 2 onward, especially deepening agent
and topic switching plus natural-language resource operation through the agent
runtime.

# Agent-First Telegram Implementation Overview

Investigation and product-architecture overview for OpenCompany Telegram.

This document was reset on 2026-05-25 after live Telegram testing showed that
the broad "command center" direction made the bot feel complex and noisy. The
target is now an agent-first Telegram shell. Telegram should not reproduce the
OpenCompany sidebar; it should make it fast to talk to the right agent in the
right topic, see what is running, approve risky work, and attach context.

## Product Principle

Telegram answers five questions:

- Which agent am I talking to?
- Which topic or lane owns this context?
- What is the current run doing?
- What needs my approval?
- What did I just attach for the agent?

Docs, files, lists, tables, calendar, and automations remain OpenCompany
capabilities, but they are not primary Telegram navigation. Users ask the agent
to operate those resources, and the agent uses OpenCompany tools and approval
policy to do the work.

## Visible Telegram Surface

The visible bot command/menu surface is intentionally small:

```text
/start       open the current agent lane
/agents      switch the agent for this chat or topic
/topic       change lane behavior
/status      see current work
/approvals   review pending agent approvals
/help        concise agent-shell help
```

Identity linking is available in private scope. Diagnostics such as `/settings`
and `/health` are admin scoped. Run actions such as stop/resume are surfaced as
buttons when work exists, not as primary menu items.

Advanced commands such as `/files`, `/docs`, `/lists`, `/tables`, `/calendar`,
`/dashboard`, `/workload`, and `/automation` are legacy handlers behind
`telegram.direct_resource_commands_enabled`, which defaults off. When the flag is
off, typed legacy commands and stale legacy command buttons return a short
"command removed" notice and do not open helper cards or product-module cards.

## Core UX

```text
OpenCompany

Iris is active
Lane: Main chat
Work: Nothing running
Approvals: none

Just send a message to work with Iris.

[Switch agent] [Lane]
[Status] [Approvals]
```

```text
Choose agent

Iris is active in this lane.

Iris
Atlas - working
Forge
Ledger - offline

[Iris] [Atlas]
[Forge] [Ledger]
```

```text
Lane

Main chat
Agent: Atlas
Replies: When you ask
Memory: on

[When asked] [Whenever helpful]
[Remember only] [Mute]
```

```text
Added to agent

1 attachment ready

Processing
Voice transcript queued

Ask the agent what to do with it.

[Switch agent] [Lane]
[Done]
```

```text
Approval required

Update production setting
Requester: Forge
Risk: high
Button expiry: 30 minutes

[Approve once] [Reject]
[Inspect]
```

## Hermes Lessons To Keep

Hermes is rich because the agent loop is rich, not because Telegram exposes every
resource as a command. The important patterns to preserve are:

- topic-aware session routing;
- root DM as a lightweight lobby/system lane when topic mode is enabled;
- group/forum gating by mention, reply, topic, and allowlist;
- media/voice/document normalization into agent-usable context;
- inline buttons for approvals, clarification, and selection;
- status/progress edits and reactions instead of chat spam;
- server-side authorization for every callback.

## OpenCompany Interpretation

OpenCompany owns the Telegram runtime under `app/Domain/Chat/Telegram`, with
Chatogrator used for other external chat providers where a generic adapter is
honest. Telegram-specific richness should remain provider-native, but the
visible UX is not provider-native CRUD. It is an agent shell over OpenCompany.

The broad resource cards are still useful as backend capabilities, Mini App
panels, diagnostics, or explicitly enabled fallback paths. They are not the
default Telegram conversation model.

## Modern Telegram Features To Use

Use Telegram features only where they strengthen the agent shell:

- private-chat and forum topics for lanes;
- inline keyboards/callbacks for switching, approvals, and clarification;
- message edits and reactions for run progress;
- media groups, photos, documents, voice, locations, and paid/live media as
  context attachments;
- deep links for identity linking;
- Mini App as an advanced drawer, not the first screen.

Business, guest, inline, and paid-media updates should remain supported by the
durable event pipeline, but they are not the primary product surface.

## Success Criteria

- Bot command menu contains only the agent-first core commands.
- `/start` presents agent/topic/run/approval state, not a product module menu.
- `/help` explains the agent shell and omits broad resource command lists.
- Captured media defaults to "ask the agent" and "switch agent/topic"; folder
  and document management are advanced paths.
- Typed legacy resource commands and stale broad command callbacks produce a
  removed-command notice unless `telegram.direct_resource_commands_enabled` is
  explicitly enabled.
- Default cards hide internal chat IDs, topic IDs, task IDs, raw modes, queue
  internals, and audit IDs; explicit inspect/admin surfaces may still expose
  trace identifiers when they are operationally useful.
- Direct messages, private topics, and group topics route to the configured
  agent/lane without guessing from text alone.
- Approvals remain first-class, server-authorized, and auditable.

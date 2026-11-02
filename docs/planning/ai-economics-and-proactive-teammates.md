# AI Economics & Proactive Teammates

Status: Planning, code-audited 2026-05-26. This document captures a product direction, not a shipped feature set. It combines two related bets: OpenCompany should win when AI usage becomes economically rational, and external-chat agents should feel like accountable teammates instead of bots that only wake up when tagged.

Related docs:

- `README.md`
- `docs/planning/integrations.md`
- `docs/external-channel-sync.md`
- `docs/planning/chatogrator.md`
- `docs/planning/lua-scripting.md`
- `docs/planning/automation-endpoints-artifacts.md`
- `docs/architecture/ai-provider-runtime-architecture.md`
- `docs/architecture/openclaw-patterns.md`
- `docs/architecture/telegram-world-class-implementation-plan-2026-05-24.md`

## Thesis

If subsidized AI subscriptions disappear, the market stops rewarding "unlimited AI chat" and starts rewarding operational economics:

- which model calls are worth paying for;
- which work can use cheap or local models;
- when a frontier model is justified;
- how to stop agents from wasting tokens;
- how to prove workflow ROI;
- how to keep data, credentials, memory, and audit logs under company control.

OpenCompany should position itself as the operating layer for companies that need AI work to be economical, governable, provider-independent, and measurable.

The same logic applies to Slack and other external chat surfaces. A bot that only answers when mentioned is a support widget. A real AI teammate has owned responsibilities, watches its work lane, wakes for meaningful events, stays quiet when nothing matters, and leaves receipts for what it did.

## Current State

OpenCompany already has several useful primitives:

- The product is framed as a self-hosted workspace where humans and persistent AI agents collaborate as team members.
- The app has an app-owned AI provider/model catalog and supports multiple model providers instead of hard-binding workflows to one vendor.
- Workspace-scoped integration settings store credentials and provider configuration.
- Agents can use internal chat, tasks, documents, memory, workspace files, tables, Lua, web tools, and MCP-connected tools.
- The external channel sync layer treats OpenCompany as the workspace source of truth, with external platforms mirrored through adapters where capabilities are proven.
- Slack is listed in `config/chat_integrations.php` with bot token, signing secret, team/bot identity, and default agent fields.
- `ChatProviderCapabilities` declares Slack support for sending, editing, deleting, pinning, reactions, files, slash commands, buttons, modals, ephemeral messages, history listing, channel discovery, and webhook proof.
- Telegram has the richest app-owned external-channel experience today, including lane/topic behavior, notifications, digests, approvals, and more mature command handling.
- Lua/script automations already exist and are important because deterministic scripts can handle repeated work without paying LLM cost every time.

Important current limitations:

- Slack is not yet a mature app-owned experience layer like Telegram.
- Slack setup is currently closer to "connect a bot" than "assign an agent a durable job in a channel."
- The current chat integration config does not model channel-level behavior such as mention-only, observe, teammate, or owner modes.
- Wake rules, heartbeat checks, quiet hours, per-lane budgets, and proactive summaries are not first-class cross-provider concepts.
- Cost and token visibility exists around task results and context, but OpenCompany does not yet present a full LLM FinOps product surface across agents, workflows, models, and providers.
- Provider capability declarations say what the app may rely on, but they do not by themselves prove that every Slack interaction path has been live-hardened.

## Product Positioning In A Rational AI Market

Avoid positioning OpenCompany as cheap unlimited AI. That market is fragile because its unit economics depend on third-party subsidies.

Position OpenCompany as:

> The place where businesses run AI agents with budgets, approvals, memory, integrations, local models, provider routing, and ROI tracking.

The strongest customer-facing claim:

> AI is no longer free magic. OpenCompany makes it operationally profitable.

This creates a durable reason to buy even if individual model providers change price, reduce free tiers, or remove bundled capacity.

## Economic Product Pillars

### 1. LLM FinOps

OpenCompany should make AI cost visible at the same level where business value is visible.

Needed surfaces:

- cost per agent;
- cost per task;
- cost per workflow;
- cost per channel/lane;
- cost per provider and model;
- budget limits per workspace, agent, channel, workflow, and automation;
- alerts when spend crosses thresholds;
- approval gates for expensive runs;
- model escalation logs explaining why a more expensive model was used;
- estimated human time saved and business outcome notes.

Example output:

> The support agent answered 412 questions this month. 361 used local or cheap models, 51 escalated to frontier models. Total AI cost: $18.40. Estimated human time saved: 46 hours.

### 2. Provider Independence

The product should make model-provider switching a normal operations decision, not a rewrite.

Examples:

- Support triage uses a cheap hosted model or local Ollama by default.
- Contract review escalates to a frontier model only for high-risk language.
- Web research uses the configured provider chain with explicit fallback behavior.
- Coding or analysis tasks route to the best available model for that task class.

The business pitch:

> Do not build your company around one AI vendor's pricing. Put OpenCompany between your workflows and the model market.

### 3. BYOK And Self-Hosted Control

Self-hosting and bring-your-own-key become more valuable when AI has real COGS.

OpenCompany should support:

- customer-owned provider keys;
- customer-owned model contracts;
- local model use for routine work;
- open-source self-hosted deployment;
- managed single-customer deployments;
- a commercial gateway option for teams that want OpenCompany to operate the provider layer.

Avoid bundled unlimited AI. Prefer a platform subscription plus BYOK, optional managed hosting, optional metered gateway usage, and premium governance/cost-control features.

### 4. Deterministic Automation First

In a rational market, repeated work should not require repeated expensive reasoning.

The target pattern:

1. Scripts, integrations, and webhooks collect structured data.
2. Rules and cheap models classify routine cases.
3. Expensive models handle ambiguity, synthesis, and high-risk judgment.
4. Approvals protect expensive or externally mutating actions.
5. Reports show what happened and what it cost.

This is where Lua/script automations, endpoint automations, artifacts, tables, and VFS can become a major product advantage.

## Proactive Slack Teammates

The Slack problem is simple: if a human must tag an agent every time, the agent does not feel like a teammate. But if the agent replies to everything, it becomes noisy and expensive.

The product answer is channel ownership with controlled proactivity.

### Agent Lanes

Add a durable "lane" concept for external and internal channels. A lane assigns responsibility, behavior, budget, and wake rules to an agent in a specific context.

Each lane should include:

- channel or external conversation;
- responsible agent;
- behavior mode;
- wake rules;
- allowed tools/actions;
- per-lane budget;
- quiet hours and timezone;
- digest schedule;
- escalation users or channels;
- memory scope;
- approval profile;
- audit log.

The same model should apply to Slack, Telegram topics, Discord channels, Teams channels, and internal OpenCompany channels.

### Lane Modes

Suggested modes:

| Mode | Meaning | Example |
| --- | --- | --- |
| Mention only | Agent responds only when directly mentioned. | Public company channel where noise would be risky. |
| Observe | Agent reads and stores context but stays silent unless a wake rule fires. | Sales channel where it watches for customer or deal signals. |
| Teammate | Agent can reply in threads when it has useful help. | Support or operations channel. |
| Owner | Agent is accountable for the lane and proactively manages work. | `#support`, `#incidents`, or a client-reporting channel. |

### Wake Rules

Wake rules should be explicit and inspectable, not hidden prompt magic.

Examples:

- unanswered question after 10 minutes;
- message contains `bug`, `refund`, `blocked`, `urgent`, `down`, or `security`;
- customer, deal, invoice, incident, or project name is mentioned;
- someone asks "who owns this?", "can someone", "should we", or "what is the status?";
- a promised follow-up did not happen;
- thread has no clear owner;
- integration webhook or incident alert arrives;
- scheduled heartbeat is due;
- daily or weekly digest window closes.

Wake rules should be cheap whenever possible. Use deterministic filters, embeddings/search, small models, or script checks before starting a costly agent run.

### Slack Behavior Rules

To feel like a good coworker, the agent should:

- reply in threads instead of spamming the main channel;
- react with an emoji when it picks something up;
- use ephemeral messages for low-confidence suggestions;
- summarize instead of narrating every step;
- ask before high-risk actions;
- respect quiet hours;
- batch low-priority updates into digests;
- explain when it escalated to a more expensive model;
- avoid replying when a human is already clearly handling the thread.

Examples:

- Support: "I noticed three users asked about CSV export this week. I drafted a help-doc update and created a task for adding the missing screenshot."
- Sales: "The ACME thread went quiet after they asked about enterprise pricing. I drafted a follow-up and linked the last proposal."
- Ops: "The deploy failed and Sentry errors increased. I created an incident task, summarized the logs, and notified `#incidents`."
- Finance: "Stripe shows 5 failed renewals today. I grouped them by account owner and created follow-up tasks."
- Project management: "The launch checklist has two overdue items and no owner for QA. Should I assign QA to Lisa?"

## What Needs To Change

### Data Model

Add first-class lane and wake-rule storage.

Candidate tables/models:

- `agent_lanes`
- `agent_lane_rules`
- `agent_lane_runs`
- `agent_lane_budgets`
- `agent_lane_digest_subscriptions`

Important fields:

- `workspace_id`
- `agent_id`
- `channel_id`
- `external_provider`
- `external_channel_id`
- `mode`
- `status`
- `active_hours`
- `timezone`
- `budget_limit`
- `budget_window`
- `approval_profile_id`
- `last_checked_at`
- `last_spoke_at`
- `last_digest_at`

### Runtime

Add a lane-aware runtime path that can wake an agent without a direct user mention.

Needed services/jobs:

- lane evaluator for inbound messages;
- heartbeat scheduler for due lanes;
- wake-rule matcher;
- cheap preflight classifier;
- agent-run builder that includes lane context;
- noise suppressor for acknowledgment-only runs;
- cost and budget guard before model execution;
- escalation path to humans or approvals;
- digest renderer.

The invariant: proactive wakeups must go through the same workspace, channel, permission, approval, provider, and cost boundaries as normal user-triggered runs.

### Slack Adapter And Setup

Slack needs a richer setup flow than token fields.

Needed setup capabilities:

- OAuth install flow;
- bot identity verification;
- event subscription verification;
- channel discovery and join/leave;
- per-channel lane assignment;
- mode selection;
- wake-rule presets;
- notification/digest preferences;
- interactive buttons and modals for approvals, snooze, assign, and "stop watching."

Slack-specific hardening should prove:

- inbound app mentions;
- channel messages in selected channels;
- thread replies;
- message edits/deletes;
- reactions;
- file events;
- signature verification;
- workspace/team mapping;
- outbound thread replies;
- ephemeral suggestions;
- modals/buttons.

### UI

Add a lane-management surface rather than burying this inside generic integration settings.

Suggested screens:

- Agent profile: "Owned lanes"
- Channel settings: "Agent behavior"
- Integrations > Slack: "Connected channels"
- Automation/Lab: "Wake rules"
- Activity/Audit: "Why did this agent wake?"
- Cost dashboard: "Spend by lane"

Lane cards should show:

- agent;
- mode;
- watched channel;
- wake rules;
- current budget usage;
- last wake reason;
- last useful output;
- pending approvals;
- quiet-hours status.

### Cost And Governance

Add controls that make proactivity economically safe:

- maximum wakes per lane per hour/day;
- maximum model spend per lane per day/month;
- cheap preflight required before expensive models;
- default model tier per lane;
- escalation model tier per lane;
- approval required above cost threshold;
- no external write action without permission;
- audit entry for every proactive wake and suppression decision.

### Agent Prompt And Memory

Agent identity should include responsibilities, boundaries, and lane expectations.

Examples:

- "You own first-pass support triage in `#support`."
- "Do not answer if a human has already taken ownership."
- "Use documentation and prior messages before giving a confident answer."
- "Create a task for product bugs; do not promise engineering timelines."
- "Stay silent when the heartbeat finds nothing actionable."

Memory should capture stable lane facts, such as customer names, common support topics, recurring incidents, owner mappings, and channel etiquette. It should not dump entire Slack histories into the prompt.

## Phased Plan

### Phase 1: Documented Lane Presets

- Add lane config model for internal and external channels.
- Add modes: mention-only, observe, teammate, owner.
- Add basic wake rules for unanswered questions and keywords.
- Add per-lane quiet hours and digest settings.
- Add UI to assign an agent to a Slack channel.

### Phase 2: Proactive Runtime

- Add heartbeat checks.
- Add inbound-message wake-rule evaluation.
- Add cheap preflight classification.
- Add thread-first Slack replies.
- Add cost guard and max-wake limits.
- Add "why did this wake?" audit records.

### Phase 3: Slack-Native Teammate UX

- Add Slack buttons for acknowledge, assign, snooze, stop watching, approve, reject.
- Add ephemeral suggestions.
- Add modals for quick task creation or escalation.
- Add channel discovery and lane onboarding.
- Add digest cards.

### Phase 4: Cross-Provider Lanes

- Generalize lanes to Telegram topics, Discord channels, Teams channels, and internal OpenCompany channels.
- Share wake-rule presets across providers.
- Preserve provider-specific behavior where capabilities differ.
- Show one operations surface for all agent-owned lanes.

### Phase 5: Economic Optimization

- Add spend-by-lane dashboards.
- Add model-routing policies by lane and workflow.
- Add ROI reports.
- Add provider price comparison and routing recommendations.
- Add local-model fallback recommendations for routine lanes.

## Business Wedge

The first sellable version should be narrow:

> We install AI teammates into your Slack and OpenCompany workspace. They own support, ops, reporting, or client-update lanes; they wake themselves when useful; they stay quiet otherwise; and every run has a cost, reason, and audit trail.

Best early pilots:

- small SaaS company support and founder-ops;
- marketing agency client reporting;
- dev shop incident and release coordination;
- e-commerce operations and finance follow-up.

The measurable promise:

> This lane saved X hours, resolved Y items, escalated Z risks, and cost $N in model usage.

## Open Questions

- Should lane ownership be a generic `AgentLane` domain or live under chat/external-channel integration first?
- Should heartbeats be per agent, per lane, or both?
- How much Slack history can be read without creating privacy, cost, or trust problems?
- Should `observe` mode store memory automatically, or only create summarized lane notes?
- How should per-lane budget enforcement interact with workspace-level provider limits?
- Should Slack proactivity ship before or after Telegram lanes are generalized into shared primitives?

# OpenCompany Current State

Status: Current implementation inventory.
Verified: implementation inventory 2026-11-02 against the `feat/vfs` checkout (run control plane,
VFS, mruby Code Mode and the 2026-11 audit fixes committed locally; not yet on `main`).

This document is the compact product/engineering source of truth. It describes the current checkout, including uncommitted work. It does not claim that a feature is committed to `main`, deployed, configured for every workspace, or live-verified with every external provider.

## Snapshot

| Surface | Current count |
|---|---:|
| Eloquent model files | 57 |
| Database migrations | 113 |
| API controllers | 54 |
| Laravel routes | about 377 total / 269 API (local route list) |
| Vue pages | 40 |
| Vue components | 153 |
| Top-level queue jobs | 11 |
| Test files | 151: 121 Feature, 15 Unit, 14 Browser, 1 opt-in live eval |
| AI providers in the generated catalog | 22 |
| Integration catalog snapshot | 591 integrations / about 41,000 tool definitions |

All migrations run cleanly on the in-memory SQLite test database. The local
development database was empty at verification time, so no claim is made about
an applied local schema; `2026_11_02_000001` adds `workspace_id` to automation
rules and approval requests with a backfill.

## Capability matrix

| Area | Current implementation | Important boundary |
|---|---|---|
| Workspace collaboration | Multi-workspace humans and agents, channels, DMs, messages, threads, reactions, attachments, tasks, lists, tables, calendar, documents, and files; realtime (Reverb) events for chat, tasks, agent status and approvals use private channels authorized by workspace membership (agents by their own workspace) | Workspace scoping is broad; human resource ACL enforcement remains incomplete |
| Agent execution | Durable `Task` plus `AgentRunRecord`, semantic attempts, heartbeat, pause/cancel, stale-run reconciliation, per-agent serialization, guarded tool ledger, and correlated response delivery; an agent cannot terminate the task its own run is executing | One physical queue gives no workload-class latency isolation; run events still live in task JSON, not an append-only event table |
| Multi-agent work | Peer agents as isolated tools; direct subagent calls create peer-owned child task/run records under the per-agent lock; the dependency orchestrator passes upstream outputs to dependents, skips dependents of failed steps and rejects cycles; durable delegation depth/cycle guard on `contact_agent` and subagents using the workspace causality chain; contacting a peer outside the manager chain needs approval by default | Dependency plans remain sequential; no concurrent branch scheduler, full child-run UI, handoff schema, or parent verification of child results |
| AI providers | App-owned provider/model catalog (also owns legacy alias upgrades and context windows), Laravel AI transport, Codex device OAuth, embeddings, prompt-cache policy, one configurable request timeout, and an OpenAI-compatible AI Gateway with per-key rate limits | No automatic cross-provider failover; AI Gateway chat streaming is not implemented; gateway keys lack scopes and required expiry |
| Memory | Durable memory files, indexing, embedding cache, pgvector, PostgreSQL full-text search, weighted retrieval, reranking (degrades to hybrid order when Ollama is absent), compaction, and pre-compaction memory flush | Dream consolidation, temporal decay, deduplication across extracted memory logs, and vector-optional writes remain planned; the pgvector CI job is not yet a required check |
| VFS | Permission-aware projections over agents, approvals, routines, channels, docs, files, lists, tables, tasks, tools, and workspace state; Unix-like command specs, pagination, budgets, approvals, and operation events; reachable from Code Mode as `app.vfs.*` | No `/skills`, `/artifacts` or `/memory` mount; not mounted into agent computers (no computers yet) |
| Code Mode | mruby is the only scripting runtime (`code_exec`, `code_*` docs tools, pinned `bowerbird-ruby-engine` v0.1.0-rc.1 run as an OS-contained subprocess); source admission, per-callback permission re-checks, write receipts, targeted repair hints; live GLM 5.1 evals pass 6/6 (`docs/testing/mruby-glm-evals-2026-11-02.md`) | Engine is a release candidate; Lua and QuickJS are removed; legacy Lua automations are disabled until rewritten in Ruby |
| Routines | Automations are replaced by Routines (agent and script kinds, plus app-owned built-ins such as the Telegram digest): `routines:tick` records one `routine.triggered` event per schedule slot and dispatches once per slot with the event's causality chain on the task and run; manual runs start their own chain; notify policies (always, on failure, only when the output changed); instruction/script version counter; durable run control (script runs get exactly one semantic attempt and a source receipt); agent tools and the `/routines` API. A trigger grants no extra permissions | Schedule-only: event, webhook and poll triggers, heartbeats, endpoint routines and multi-step graphs are not built; `mode` is stored but not enforced; `if_new` compares an output hash, not a checkpoint; built-in routines have no run record |
| Integrations and connections | The per-vendor integration package system is deleted. External tools come from remote MCP servers (SSRF guard, no redirects, pinned IPs, response cap, operator allowlist; isolated per workspace). App-owned settings remain for AI providers, Telegram, chat adapters and web search/fetch providers. Baseline Connections model: personal or shared connections, vault-encrypted secrets, per-agent read/act grants, and a per-call credential broker | No deep connectors (Drive, Gmail, Calendar) or generic connectors (rebuilt MCP client, API manifests, inbound webhooks, skills) yet; no Connections UI or OAuth broker; MCP is still registered at boot and filtered per workspace. OpenCompany is not a hosted MCP server |
| Event log | Append-only workspace event log with the standard envelope (type, source, subject, actor, idempotency key, untrusted payload) and causality chain/depth; tasks and runs carry causality; delegation records `agent.delegated` events and uses the shared depth limit (`EVENTS_MAX_DEPTH`) | Few producers yet; no activity feed, trigger matching, dead-letter queue, retention or migration of the older per-feature event tables |
| External chat | Telegram has the deepest app-owned runtime: durable receipts/delivery, operations, commands, topics/lanes, approvals, Mini App, media, and digests; messages from non-members are labelled as untrusted content for agents | Slack, Discord, Teams, and other adapters are not Telegram-equivalent product surfaces; Telegram approval cards still show buttons to users who cannot approve (the server refuses) |
| Documents | Hierarchy, versions, comments, attachments, content search, semantic search, and publish fields; delete moves a document (and a folder's subtree) to trash and out of search, restore brings back that cascade, purge is separate (table rows and list items also have a trash; workspace files do not, so file deletion is classed as a purge); optimistic concurrency on content (the document API and the VFS share one version token; a stale editor save gets 409 with the current document and the editor stops autosaving) | Document permission rows are not consistently enforced; public sharing, publish controls, starring, templates, and bulk operations are incomplete. **Storage format is mixed:** the web editor saves HTML (`content_format = html`) while agents and the VFS read and write Markdown, so one document can hold either; the format decision belongs to the work-apps UI design |
| Approvals and governance | Agent/tool approval wrapping, Telegram/Mini App/chat/in-app response paths, workspace-owned approval records, `approver` role (admins and approvers resolve; no self-approval; agents never resolve, and an agent-supplied amount can no longer waive approval), atomic resolution, full permission-chain re-check before execution, VFS replay context. Governance by reversibility: every built-in tool has an action class (`ActionClassifier`; MCP and unclassified tools are `external_send`); the workspace preset (`personal`/`team`/`company`, derived from membership when unset, admin-settable via `PATCH /api/workspace` and audited) picks the default gate per class; reversible edits auto-apply, external, structural and governance actions ask, purges ask in Team; scoped exact-match exceptions (once, run, task, until, always; `always` never covers purge, exposure or structural), trigger-started runs always ask for external actions, an install-level floor list is always denied. On-behalf-of: runs persist their human principal (requester, routine owner, inherited through delegation); a removed principal denies everything and only an admin principal can drive governance or exposure actions; approvals carry the principal and replay re-checks it. Agent edits to documents, table rows and list items are recorded per run and `POST /api/agent-runs/{id}/revert` undoes them unless a human changed the value since | No approver policies, two-approver or admin-approver routing (those gates are plain approval), slider, learned or batched approvals, Governance UI or grants UI; proposals are approvals, not change-set review; bulk-edit thresholds and per-recipient exceptions are not modelled; the core floor (money transfers, credential changes) has no tools to cover yet and self-grant changes are refused inside the tool; on-behalf-of is workspace-level until Spaces exist; taint is not tracked per run |
| Cost visibility | `llm_usage_events`, task token metrics, cache tokens, catalog estimates, OpenRouter reconciliation, and token analytics | No cumulative budget enforcement, lane/workflow budgets, ROI model, or complete LLM FinOps UI |
| Observability | Laravel logs/Telescope, health/debug surfaces, usage ledgers, Telegram operations, run records, queue heartbeat, and VFS operation events | No unified metrics/tracing/alerting platform, Sentry integration, operational SLOs, or full runbooks |
| Deployment | Self-hosted Laravel application in Docker (pinned PHP and mruby engine); migrations squashed on 2026-11-02 into a schema baseline (`database/schema/`, loaded by `artisan migrate` on a fresh database; fresh PostgreSQL installs work again); no phone-home: no telemetry SDKs, no update checks or remote flags, every outbound call site is an allowlisted user- or admin-configured destination, and boot plus every scheduled task make no HTTP request (`tests/Feature/DataSovereignty/NoPhoneHomeTest.php`); archive-ready schema (string IDs on every archived table, content-hashed files, append-only events, versions and change records; `.ocarchive` exporter not built); named rate limits, env-configured trusted proxies, allowlisted Telescope; prepared for a prospective AGPL-3.0-only release with Apache-2.0 only where a path notice says so (today `resources/code-docs/`); historical artifacts retain their shipped BSL or FSL terms | Agent computers, managed cloud, regional residency, Helm, air-gapped packaging, corresponding-source automation, and uptime SLA are strategy, not current code; AGPL publication awaits counsel review |

## Confirmed incomplete authorization work

The current product has workspace isolation, admin/approver/member roles, agent permissions, and approvals. It does not yet have complete human RBAC. The following code-backed gaps remain authoritative until fixed and tested:

1. Channel and message APIs do not consistently require human channel membership.
2. Document permissions are stored but not consistently enforced for list/read/write/delete/search.
3. Workspace members can update agent fields without a dedicated agent-management capability.
4. AI Gateway keys lack model/action scopes and required expiry (per-key rate limits now exist).
5. Settings danger actions remain behind broad admin access rather than separate capabilities and an authorization audit ledger.

Closed on 2026-11-02 with tests: workspace scoping of list automation rules (API and agent tools),
direct-message participant validation, the approver role and no-self-approval rule, and
workspace-owned approvals reachable without a channel.

The current VFS and agent-run work improves agent-side safety and durable execution; it does not close these human authorization gaps.

## Explicitly not current features

The following are present only as plans, research, or strategy:

- OpenCompany-hosted MCP server and OpenCompany Connect.
- First-class Artifacts/Lab, artifact endpoints, custom domains, and publishing workflow.
- Reusable OpenCompany skills and a skill marketplace.
- General plugin runtime with replaceable memory/sandbox/browser slots.
- Embedded Playwright browser sessions with human takeover.
- Visual workflow programming.
- SSO/SAML, SCIM, custom enterprise roles, compliance reports, SIEM export, and data-residency controls.
- Managed multi-region cloud and a 99.9% SLA.
- Official JavaScript/Python SDKs and device registry.
- Automatic organization-wide learning and autonomous 24/7 work without a trigger, schedule, or queued run.

## Verification boundary

This snapshot used the local checkout, route inventory, application boot, migrations, and focused code/test evidence. External provider credentials, production deployment state, provider uptime, managed hosting, and every adapter's live behavior require separate verification.

Verification on 2026-11-02: the full backend suite passes in parallel against the
real pinned mruby engine (1,011 passed, 9 skipped, 0 failed), PHPStan
level 6 passes against a checked-in baseline, the Vue typecheck and production build
pass, and the live GLM Code Mode evals pass 6/6. The PostgreSQL/pgvector CI job and the
production-image job are defined but have not yet run on GitHub.

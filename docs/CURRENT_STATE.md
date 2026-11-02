# OpenCompany Current State

Status: Current implementation inventory.
Verified: implementation inventory 2026-08-09 against the active local checkout and local
database; deployment-license row refreshed 2026-09-01.

This document is the compact product/engineering source of truth. It describes the current checkout, including uncommitted work. It does not claim that a feature is committed to `main`, deployed, configured for every workspace, or live-verified with every external provider.

## Snapshot

| Surface | Current count |
|---|---:|
| Eloquent model files | 58 |
| Database migrations | 111 |
| API controllers | 54 |
| Laravel routes | 375 total / 267 API |
| Vue pages | 40 |
| Vue components | 152 |
| Top-level queue jobs | 11 |
| Test files | 121: 92 Feature, 15 Unit, 14 Browser |
| AI providers in the generated catalog | 22 |
| Integration catalog snapshot | 591 integrations / about 41,000 tool definitions |

The inspected database has 110 of 111 migrations applied. The VFS,
`agent_runs`, direct-message scoping, and `agent_tool_executions` migrations are
applied; `2026_08_09_000003_add_vfs_pagination_indexes` is pending.

## Capability matrix

| Area | Current implementation | Important boundary |
|---|---|---|
| Workspace collaboration | Multi-workspace humans and agents, channels, DMs, messages, threads, reactions, attachments, tasks, lists, tables, calendar, documents, and files | Workspace scoping is broad; human resource ACL enforcement remains incomplete |
| Agent execution | Durable `Task` plus `AgentRunRecord`, semantic attempts, heartbeat, pause/cancel, stale-run reconciliation, per-agent serialization, guarded tool ledger, and correlated response delivery | Implemented in the current working tree; one physical queue gives no workload-class latency isolation |
| Multi-agent work | Existing peer agents are exposed as isolated tools; direct subagent calls create child task/run records and a dependency orchestrator aggregates results | Dependency plans remain sequential; there is no concurrent branch scheduler, full child-run UI, queue delivery modes, or auto-dissolution |
| AI providers | App-owned provider/model catalog, Laravel AI transport, Codex device OAuth, embeddings, prompt-cache policy, and OpenAI-compatible AI Gateway | No app-owned automatic cross-provider failover; AI Gateway chat streaming is not implemented |
| Memory | Durable memory files, indexing, embedding cache, pgvector, PostgreSQL full-text search, weighted retrieval, reranking, compaction, and pre-compaction memory flush | Dream consolidation, temporal decay, deduplication across extracted memory logs, and vector-optional writes remain planned |
| VFS | Permission-aware projections over agents, approvals, automations, channels, docs, files, lists, tables, tasks, tools, and workspace state; Unix-like command specs, pagination, budgets, approvals, and operation events | No `/skills` or `/artifacts` mount; current broad command work is still in the dirty checkout and its pagination-index migration is pending locally |
| Automations | Scheduled prompt and Lua script automations, manual runs, durable run control, run history, and agent tools | General event/poll triggers, endpoint automations, multi-step graphs, and one-shot lifecycle are not first-class |
| Integrations | Generated package catalog, workspace credentials, multi-account settings, remote MCP client/runtime, web search/fetch adapters, and webhook receipts | A catalog entry does not prove credentials or live behavior; OpenCompany is not a hosted MCP server |
| External chat | Telegram has the deepest app-owned runtime: durable receipts/delivery, operations, commands, topics/lanes, approvals, Mini App, media, and digests | Slack, Discord, Teams, and other adapters are not Telegram-equivalent product surfaces |
| Documents | Hierarchy, versions, comments, attachments, content search, semantic search, and publish fields | Document permission rows are not consistently enforced; public sharing, publish controls, starring, templates, and bulk operations are incomplete |
| Approvals | Agent/tool approval wrapping, Telegram and in-app response paths, atomic resolution, post-approval permission recheck, VFS replay context | No full approver-role model, no-self-approval policy, approval profiles, or complete human authorization ledger |
| Cost visibility | `llm_usage_events`, task token metrics, cache tokens, catalog estimates, OpenRouter reconciliation, and token analytics | No cumulative budget enforcement, lane/workflow budgets, ROI model, or complete LLM FinOps UI |
| Observability | Laravel logs/Telescope, health/debug surfaces, usage ledgers, Telegram operations, run records, queue heartbeat, and VFS operation events | No unified metrics/tracing/alerting platform, Sentry integration, operational SLOs, or full runbooks |
| Deployment | Self-hosted Laravel application; the current working tree is prepared for a prospective AGPL-3.0-only release, while historical artifacts retain their shipped BSL or FSL terms | Managed global cloud, regional residency, Helm, air-gapped packaging, corresponding-source automation, and uptime SLA are strategy, not current code |

## Confirmed incomplete authorization work

The current product has workspace isolation, admin/member roles, agent permissions, and approvals. It does not yet have complete human RBAC. The following code-backed gaps remain authoritative until fixed and tested:

1. Channel and message APIs do not consistently require human channel membership.
2. Document permissions are stored but not consistently enforced for list/read/write/delete/search.
3. List automation-rule CRUD is not scoped through the active workspace.
4. The legacy direct-message controller does not use `DirectMessageResolver` and does not validate authenticated participation, distinct participant IDs, or workspace membership before creation.
5. Workspace members can update agent fields without a dedicated agent-management capability.
6. Approval resolution lacks a dedicated approver capability and a no-self-approval rule.
7. AI Gateway keys lack model/action scopes, required expiry, and per-key rate limits.
8. Settings danger actions remain behind broad admin access rather than separate capabilities and an authorization audit ledger.

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

Focused verification on 2026-08-09 exercised agent runs, automations, task
management, VFS capabilities/pagination/core behavior, agent construction, and
runtime services: 99 tests passed and 5 failed. Four failures are blocked by the
missing local `Lua\Sandbox` class; one is a stale expected error string after
`rows.ndjson` became cursor-paged. These are recorded in `docs/ROADMAP.md` and
are not represented as passing coverage.

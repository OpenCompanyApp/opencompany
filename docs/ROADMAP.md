# OpenCompany Roadmap and Open Audits

Status: Active prioritized engineering register.
Verified: engineering register 2026-08-09 against the active local checkout; licensing publication
gate refreshed 2026-09-01.

This file consolidates active work that was previously scattered across audits, TODOs, product copy, and long planning documents. Detailed plans remain linked; this register owns priority and current disposition.

## P0 — authorization and product truth

These items block credible enterprise positioning and should precede broad new platform claims.

1. Introduce a central human/agent authorization decision service and explicit fixed roles.
2. Enforce channel membership for channel/message reads and writes.
3. Enforce document permissions across CRUD and search.
4. Scope list automation rules through workspace-owned records.
5. Route all direct-message creation through the workspace-validating resolver and reject self-DMs.
6. Add explicit agent-management and approval capabilities, including no self-approval for high-risk actions.
7. Add scoped/expiring/rate-limited AI Gateway keys.
8. Split settings danger actions into separately authorized, audited commands.
9. Add an append-only authorization decision ledger.
10. Keep public README, website copy, demos, and feature catalogs limited to claims in [CURRENT_STATE.md](CURRENT_STATE.md).
11. Before publishing the AGPL transition, verify copyright and contributor authority, review the
    final documents with counsel, record the exact cutover commit/release date, preserve historical
    BSL/FSL terms, and activate the DCO contribution workflow described in
    [CONTRIBUTING.md](../CONTRIBUTING.md).

Detailed plan: the sibling vault currently owns `Architecture/OpenCompany/Planning/permissions-rbac-policy-system.md`; its technical implementation copy should ultimately move into this repository before coding begins.

## P1 — stabilize the working-tree runtime

1. Finish and commit the agent-run queue control plane as one scoped change after focused review.
2. Run the agent-run, automation, subagent, direct-message, VFS pagination, VFS capability, and VFS approval suites together.
3. Apply and verify the pending local
   `2026_08_09_000003_add_vfs_pagination_indexes` migration before treating the
   local schema as current.
4. Restore/verify the local `Lua\Sandbox` runtime so Lua/VFS bridge tests can run instead of failing at class load.
5. Exercise stale-run reconciliation, cancellation, ambiguous tool outcomes, and queue-insert failure in a production-sized database/queue rehearsal.
6. Revalidate the VFS command catalog, documentation, and generated Lua help from the same command-spec registry.
7. Reconcile the stale `VfsFeatureTest` assertion for the new
   `rows.ndjson` cursor-paged read-only reason.
8. Decide when the single physical queue needs separate latency classes for chat, automation, broadcasts, and maintenance.

Architecture reference: [Agent Run Queue Control Plane](architecture/agent-run-queue-control-plane.md).

## P1 — close unresolved audits

### Runtime and memory

- Count structured tool/checkpoint payloads correctly in retry context budgets.
- Deduplicate durable facts extracted across later compactions.
- Preserve explicit protected context classes during compaction.
- Expand tests around protected context and durable memory extraction.
- Reconcile old Plane issue statuses; prefix context overrides and prompt-cache usage metrics now appear implemented.

### Maintainability

- Split response completion, delegation callback, and final-error delivery out of `RespondToChatMessage`.
- Move message creation/side effects into chat-domain use cases.
- Split settings reads, diagnostics, and danger actions.
- Continue replacing hidden `workspace()` dependencies with explicit workspace/actor inputs.
- Add stable read models for large API payloads.

### Browser QA

- Add shared `401`/`419` session-expiry recovery.
- Replace hardcoded sidebar approval/chat badges with scoped live counts.
- Keep agent selection and route query synchronized.
- Block self-DM routes and backend creation.
- Recheck mobile composer spacing and mobile task tabs.
- Migrate/remove the legacy theme storage key.
- Add user-facing renderers for common tool arguments.
- Browser-revalidate the existing modal, Monaco, starter-card, workspace-agent, task-chat, accessibility, and light-mode fixes.

### Testing and CI

- Add focused PHP tests to CI, then frontend build/type checks and browser smoke coverage.
- Remove the stale `prism-codex` clone from CI when dependency installation no longer needs it.
- Replace the vault's unchecked feature-test map with generated route/test evidence plus a smaller manual checklist.

## P2 — first useful product expansions

1. Finish document publishing/sharing controls and enforce document ACLs before public links.
2. Build cumulative workspace/agent budgets on the existing usage ledger before broader autonomous scheduling.
3. Mature one external provider lane beyond Telegram only when live-hardened behavior is proven.
4. Build endpoint automations and first-class Artifacts/Lab on the VFS, Lua, tables, documents, and file foundations.
5. Harden MCP URL/redirect/response limits and provenance before building hosted MCP or Connect.
6. Add resource-specific integration grants rather than ambient access to whole connected accounts.

## P3 — longer-term platform work

- OpenCompany Connect: managed OAuth, connected accounts, hosted MCP, CLI access, triggers, policies, and audit.
- Skills: reusable versioned instructions/assets with permissions and workspace lifecycle.
- Dream memory consolidation, temporal decay, and vector-optional writes.
- Embedded browser sessions and human takeover.
- Visual workflow builder.
- Plugin/device ecosystems.
- Enterprise identity, compliance, residency, and managed multi-region cloud.

These remain plans until code, tests, operator behavior, and user-facing surfaces exist.

## Product sequence

The private vault's narrow managed-pilot strategy remains the most code-aligned near-term commercial path: sell a configured research/content operations workflow with shared context and human approval. It does not require claiming unbuilt enterprise, cloud, marketplace, mobile, or autonomous-swarm capabilities.

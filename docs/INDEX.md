# Documentation Index

Status: Current navigation index and document classification.
Verified: 2026-11-02.

> Three documents carry authority, in this order of concern:
> [Product Direction](PRODUCT_DIRECTION.md) owns *what OpenCompany is becoming and in what order*;
> [Current State](CURRENT_STATE.md) owns *what is implemented today*;
> [Roadmap](ROADMAP.md) owns *engineering priority and open audits* and must follow the direction.
> Any other document that conflicts with one of these is wrong on that point. Imported research and
> older planning documents may describe future, historical, or external-system architecture; their
> status guard and the class below take precedence over old examples in the body.

## Classes

| Class | Meaning |
|---|---|
| **Active** | Maintained reference or plan that agrees with the product direction |
| **Active, partially superseded** | Still useful, but some sections assume retired choices (Lua scripting, agent behaviour modes, the integration package catalog, Automations, Reader-first modular OS, AI-app-builder framing, embedded app browser, new Inertia pages). The direction wins where they conflict |
| **Historical** | Dated or self-declared historical evidence: audits, QA runs, imported corpora. Not current behavior |
| **Superseded** | Replaced by the product direction or a later decision. Kept for context only; do not plan from it |

## Start Here

| Document | Class | Authority |
|---|---|---|
| [PRODUCT_DIRECTION.md](PRODUCT_DIRECTION.md) | Active | Locked product direction ("Grok Bot for companies"), eleven pillars, nuclear decisions, Phases 0–5, supersede table, non-goals, open questions |
| [CURRENT_STATE.md](CURRENT_STATE.md) | Active | Canonical implemented/partial/planned product inventory |
| [ROADMAP.md](ROADMAP.md) | Active | Canonical priorities by phase and the unresolved audit register |
| [README.md](README.md) | Active | Documentation ownership, status vocabulary, and maintenance rules |
| [Linear backlog coverage (2026-11-02)](planning/linear-project-backlog-coverage-2026-11-02.md) | Active | Documentation-to-issue mapping aligned to the phases; not implementation or deployment evidence |
| [Licensing FAQ](../LICENSING.md) | Active | Prospective AGPL-3.0-only self-hosted application, Apache-2.0 boundary, separate Cloud, DCO, historical-version terms |
| [Commercial Use](../COMMERCIAL_USE.md) | Active | Practical AGPL guidance for internal, hosted, white-label, OEM, support, and official Cloud use |
| [Trademark and Compatibility Policy](../TRADEMARKS.md) | Active | Truthful references, forks, compatible products, official branding |
| [Contributing](../CONTRIBUTING.md) | Active | Contribution license mapping, DCO, provenance, review, direction pointers |

## Repository root

| Document | Class | What it covers |
|---|---|---|
| [README.md](../README.md) | Active | Public project overview, quick start, what exists today versus planned |
| [AGENTS.md](../AGENTS.md) / [CLAUDE.md](../CLAUDE.md) | Active | Repo rules for coding agents; byte-identical mirrors |
| [LICENSE.md](../LICENSE.md) | Active | License text; controls over every summary |
| [LICENSING.md](../LICENSING.md), [COMMERCIAL_USE.md](../COMMERCIAL_USE.md), [TRADEMARKS.md](../TRADEMARKS.md), [CONTRIBUTING.md](../CONTRIBUTING.md) | Active | See Start Here |

## Product direction design docs

Each is a plan, not an implementation claim.

| Document | Class | What it covers |
|---|---|---|
| [connections-events-routines.md](planning/connections-events-routines.md) | Active (accepted design) | Pillar 9: Connections → Events → Triggers → Routines → Runs; deep connectors and generic tier, OAuth broker, vault, event log with causality chain, routines, proactivity as configuration, what gets deleted |
| [artifacts-and-forms.md](planning/artifacts-and-forms.md) | Active (accepted design) | Pillar 7: Artifacts as the ninth core primitive (Pages and interactive artifacts, bindings with no private storage, sandboxed user-content origin, mruby `endpoint` profile, publishing ladder, governance) and Forms as a view on any Table with one governed submission pipeline; phased across Phases 0–5 |
| [bowerbird-synergy.md](planning/bowerbird-synergy.md) | Active (accepted design) | OpenCompany × Bowerbird: workload split, computers on Workspace API `v1alpha`, one mruby engine and dialect, Bowerbird as a deep connection, shared CLI crates, cross-sell, optional shared account and wallet, sequencing; Aya discontinued |
| [data-sovereignty.md](planning/data-sovereignty.md) | Active (accepted design) | Pillar 11: object downloads, the round-trippable `.ocarchive` workspace archive, encrypted backups to customer-owned storage, the one-way Git mirror, Cloud exit guarantees, BYO models and no phone-home, export governance; phased across Phases 0–5 |
| [oc-cli-and-hosts.md](planning/oc-cli-and-hosts.md) | Active (accepted design) | Pillars 2 and 10: the agent-first Rust `oc` CLI (public API only, `oc mcp serve`, separate Apache-2.0 `opencompany-cli` repo) and Connected hosts via the outbound `oc host` daemon; phased across Phases 0–5 |
| [ui-structure-and-agent-editing.md](planning/ui-structure-and-agent-editing.md) | Active (accepted design) | Pillars 4 and 7: current UI findings, Home, the one rail, three panes, change sets and "revert this run", Spaces and anti-mess rules, core primitives and add-on apps, new Vue 3 + Reka client |
| [ui/mockups/sidebar-2026-11.html](ui/mockups/sidebar-2026-11.html) | Active (accepted mockup) | The accepted left rail, Home and mobile bottom bar |
| [ui/mockups/apps-and-primitives-2026-11.html](ui/mockups/apps-and-primitives-2026-11.html) | Active (accepted mockup) | Core primitives plus add-on apps (Mail, Calendar, CRM) |
| [messenger-ui.md](planning/messenger-ui.md) | Active | Phase 1 messenger-first UI: teammate rail, DMs and group chats, inline cards, live run stream, computer panel slot (only for agents with a computer) |
| [api-first-client-split.md](architecture/api-first-client-split.md) | Active | Pillar 4 API-first (the rule now; Inertia frozen): versioned OpenAPI public API, native-app auth, realtime stream, push, web client |
| [computer-provider-contract.md](architecture/computer-provider-contract.md) | Active | Phase 2 `ComputerProvider` contract, provider order, per-agent computer attachment and VFS-only mode, VFS as the governed data path, credential broker, takeover |
| [governance-model.md](architecture/governance-model.md) | Active | Pillar 5: policy model replacing behaviour modes (Phase 0), roles, approver policies, hard-deny floor, budgets, run audit, trigger and read-only rules (Phase 3) |
| [agent-native-work-apps.md](planning/agent-native-work-apps.md) | Active | Pillar 7 / Phase 4: Tables, Issues and Docs made agent-native; Workload and Dashboard deleted, Lists folds into Issues |
| [opencompany-cloud.md](planning/opencompany-cloud.md) | Active | Phase 5 hosted Cloud with managed computers and self-host parity |
| [example-flows.md](planning/example-flows.md) | Active | User stories and end-to-end examples (Code Mode, CLI, client onboarding, a 2 a.m. incident, CRM and Issues); story 14 shows a field-level approval rule in a CRM manifest |
| [agent-primitives-catalog.md](planning/agent-primitives-catalog.md) | Active (accepted requirements) | Pillar 6 agent primitives: vault, secure forms, credential broker, takeover for CAPTCHAs and sign-in, password-manager connectors, agent inbox and one-time codes, pause/stop/kill switch, spend caps, routines and daily brief, memory controls; phased Phase 2 / Phase 3 / Later |

## Research and evidence

| Document | Class | What it covers |
|---|---|---|
| [2026-11-agent-landscape.md](research/2026-11-agent-landscape.md) | Historical (dated evidence) | November 2026 audit of OpenCompany plus open-source and commercial always-on agent landscape; evidence for the direction |
| [grok-bot-opencompany-investigation-2026-08-17.md](architecture/grok-bot-opencompany-investigation-2026-08-17.md) | Historical (dated evidence) | Grok Bot shared-computer agent model compared with OpenCompany; evidence for the direction |
| [cloudflare-os-opencompany-investigation-2026-08-09.md](architecture/cloudflare-os-opencompany-investigation-2026-08-09.md) | Historical | Cloudflare OS Gadget/Gatekeeper/Blueprint comparison; useful input to governance and MCP safety |
| [integration-strategy-2026-11.md](planning/integration-strategy-2026-11.md) | Superseded (research input) | "MCP as the bus" integration research; accepted in a broader form as connections-events-routines.md |
| [2026-11-website-claims-alignment.md](research/2026-11-website-claims-alignment.md) | Historical (dated change record) | Website copy aligned to CURRENT_STATE and the direction, 2026-11-02 |
| [ai-app-builder-market-research-2026-05-25.md](market/ai-app-builder-market-research-2026-05-25.md) | Superseded | AI app-builder market map; OpenCompany is not an app builder. Agent-built output is now governed by [artifacts-and-forms.md](planning/artifacts-and-forms.md) |
| [openclaw-reference.md](architecture/openclaw-reference.md) | Active | External reference on OpenClaw's agent, memory, skills, and plugin architecture |
| [openclaw-patterns.md](architecture/openclaw-patterns.md) | Active | Which OpenClaw patterns to adopt, adapt, or skip |
| [link-agent-wallets-investigation.md](architecture/link-agent-wallets-investigation.md) | Active | Stripe Link Agents payment-credential investigation (dated 2026-04-30 in body) |

## Architecture and technical

| Document | Class | What it covers |
|---|---|---|
| [agent-run-queue-control-plane.md](architecture/agent-run-queue-control-plane.md) | Active | Current durable agent execution: runs, locking, retries, tool idempotency, cancellation, recovery |
| [ai-provider-runtime-architecture.md](architecture/ai-provider-runtime-architecture.md) | Active | Provider/model catalog, Laravel AI transport, prompt cache, usage and cost ledger |
| [wayfinder-routing.md](architecture/wayfinder-routing.md) | Active | Wayfinder route/action generation, frontend usage rules, Docker build contract |
| [vfs-agent-friendliness-audit-2026-11.md](architecture/vfs-agent-friendliness-audit-2026-11.md) | Active (dated audit) | VFS agent-friendliness and computer-coexistence audit with proposals (pillar 3) |
| [interagent-comms.md](architecture/interagent-comms.md) | Active, partially superseded | Agent-to-agent ask/delegate/notify and delegation tracking; behaviour-mode gating superseded by the policy model |
| [technology-decisions.md](architecture/technology-decisions.md) | Active | Stack choices: Laravel AI SDK, app-owned provider runtime, Laravel queues |
| [domain-driven-design-architecture.md](architecture/domain-driven-design-architecture.md) | Active, partially superseded | Proposed DDD modular-monolith target architecture; behaviour-mode and integration-package contexts superseded |
| [bowerbird-durable-automation-integration.md](architecture/bowerbird-durable-automation-integration.md) | Superseded | Superseded by the workload split ([bowerbird-synergy.md](planning/bowerbird-synergy.md)): OpenCompany owns business automation; Bowerbird owns software delivery and heavy durable pipelines |
| [ai-tool-packages.md](architecture/ai-tool-packages.md) | Superseded | Earlier `ai-tool-*` package strategy; the package system is deleted (pillar 9) |
| [laravel-ai-sdk.md](architecture/laravel-ai-sdk.md) | Historical | Earlier Laravel AI SDK strategy; current runtime lives in ai-provider-runtime-architecture.md |
| [observability.md](architecture/observability.md) | Superseded | Observability proposal superseded by the current AI runtime ledger (per its own status) |
| [runtime-alignment-implementation-audit.md](architecture/runtime-alignment-implementation-audit.md) | Historical | Post-implementation runtime audit; findings tracked as issues |
| [kosmokrator-reuse-audit.md](architecture/kosmokrator-reuse-audit.md) | Historical | What to reuse, adapt, or skip from KosmoKrator |
| [kosmokrator-opencompany-runtime-comparison-2026-05-11.md](architecture/kosmokrator-opencompany-runtime-comparison-2026-05-11.md) | Historical | KosmoKrator/OpenCompany runtime comparison |
| [maintainability-refactor-audit-2026-05-19.md](architecture/maintainability-refactor-audit-2026-05-19.md) | Historical | Maintainability audit; open items are tracked in the Roadmap Phase 0 |
| [docs-code-alignment-audit-2026-05-24.md](architecture/docs-code-alignment-audit-2026-05-24.md) | Historical | May 24 docs/code reconciliation evidence |
| [integrations-catalog-branch-audit-2026-05-10.md](architecture/integrations-catalog-branch-audit-2026-05-10.md) | Historical | Integration-catalog branch audit; the catalog is deleted in Phase 0 |
| [pest-browser-testing-implementation-plan-2026-05-10.md](architecture/pest-browser-testing-implementation-plan-2026-05-10.md) | Historical | Pest Browser/Playwright migration plan; partly implemented |
| [web-search-fetch-adapter-investigation-2026-05-24.md](architecture/web-search-fetch-adapter-investigation-2026-05-24.md) | Historical | Adapter-based `web_search`/`web_fetch` investigation and plan; Lua runtime notes are superseded |
| [telegram-chatogrator-capabilities-investigation-2026-05-24.md](architecture/telegram-chatogrator-capabilities-investigation-2026-05-24.md) | Historical | Telegram/Chatogrator capability research |
| [telegram-world-class-implementation-overview-2026-05-24.md](architecture/telegram-world-class-implementation-overview-2026-05-24.md) | Historical | Agent-first Telegram target overview; Telegram stays the first external surface |
| [telegram-world-class-implementation-plan-2026-05-24.md](architecture/telegram-world-class-implementation-plan-2026-05-24.md) | Historical | Phased agent-first Telegram reset plan |
| [vfs-unix-command-gap-analysis-2026-05-26.md](architecture/vfs-unix-command-gap-analysis-2026-05-26.md) | Historical | VFS unix command gap audit with reconciliation |
| [standalone-artifact-service-investigation-2026-05-25.md](architecture/standalone-artifact-service-investigation-2026-05-25.md) | Historical, partially superseded | Standalone artifact service research; Lua endpoints, the standalone-service idea and app-builder framing are superseded by [artifacts-and-forms.md](planning/artifacts-and-forms.md) |
| [embedded-chromium-browser-investigation-2026-05-24.md](architecture/embedded-chromium-browser-investigation-2026-05-24.md) | Superseded | In-app Chromium sessions; replaced by the browser on the agent's computer (Phase 2) |
| [documentation.md](documentation.md) | Active, partially superseded | Product documentation-site blueprint; feature framing (behaviour modes, integration catalog, Automations) predates the direction |
| [ai-providers.md](ai-providers.md) | Active | AI provider inventory and priority |
| [external-channel-sync.md](external-channel-sync.md) | Active | Bidirectional sync for Chatogrator-backed external channels |
| [discord.md](discord.md) | Historical | Discord sidecar proposal plus current adapter pointer |

## Planning and implementation

The [mruby-only implementation plan](planning/mruby-only-code-mode-implementation.md) and its
[implementation evidence](planning/mruby-implementation-progress.md) own the Code Mode runtime
replacement. mruby is the only Code Mode runtime; Lua and QuickJS are retired.

| Document | Class | What it covers |
|---|---|---|
| [mruby-only-code-mode-implementation.md](planning/mruby-only-code-mode-implementation.md) | Active, partially superseded | Sole-language mruby migration: engine, PHP host, agent experience, qualification. Legacy-history gates are dropped by the migration squash; automation parts move to script routines |
| [mruby-implementation-progress.md](planning/mruby-implementation-progress.md) | Active | mruby evidence log and remaining gates |
| [unified-vfs-overview.md](planning/unified-vfs-overview.md) | Active, partially superseded | Implemented VFS baseline and later mounts; Lua helper and behaviour-mode sections superseded |
| [versioned-workspace-master-overview.md](planning/versioned-workspace-master-overview.md) | Active, partially superseded | Shared resource revisions, agent proposals, workspace history, snapshots; its Git sections are superseded by the one-way Git mirror in [data-sovereignty.md](planning/data-sovereignty.md) |
| [ai-economics-and-proactive-teammates.md](planning/ai-economics-and-proactive-teammates.md) | Active, partially superseded | AI cost controls and proactive agent lanes; feeds Phase 3 budgets. Proactivity is now routine configuration (pillar 9); Lua and behaviour-mode mentions superseded |
| [automation-endpoints-artifacts.md](planning/automation-endpoints-artifacts.md) | Partially superseded | Runtime superseded by [artifacts-and-forms.md](planning/artifacts-and-forms.md) and automation parts by Routines (pillar 9); bindings, templates, endpoint catalog and gates kept |
| [skill-system.md](planning/skill-system.md) | Active, partially superseded | Reusable agent skills; SKILL.md skills and plugin import with digest-bound approval come in Phase 2. Lua, behaviour-mode and package-catalog sections superseded |
| [memory-implementation.md](planning/memory-implementation.md) | Active | Memory, compaction, and embeddings architecture reference |
| [memory-systems.md](planning/memory-systems.md) | Active | Memory-system research and roadmap notes |
| [dream-vfs.md](planning/dream-vfs.md) | Active | Dream memory-consolidation plan |
| [vector-optional.md](planning/vector-optional.md) | Active | Optional vector-search architecture |
| [chatogrator.md](planning/chatogrator.md) | Active, partially superseded | Chat-provider package strategy; external channels become connections producing events (pillar 9) |
| [codex-subscription-auth.md](planning/codex-subscription-auth.md) | Active | Codex subscription authentication research |
| [integrations.md](planning/integrations.md) | Superseded | Per-package PHP integrations; deleted and replaced by deep connectors plus generic MCP, API manifests and skills (pillar 9) |
| [laravel-mcp-client.md](planning/laravel-mcp-client.md) | Active, partially superseded | MCP client planning; the Phase 1 rebuild on `laravel/mcp` is specified in connections-events-routines.md |
| [kosmokrator-runtime-alignment-checklist.md](planning/kosmokrator-runtime-alignment-checklist.md) | Historical | KosmoKrator runtime alignment checklist (self-declared historical) |
| [integration-refactor.md](planning/integration-refactor.md) | Superseded | Integration package refactor; the package system is deleted (pillar 9) |
| [visual-programming-research.md](planning/visual-programming-research.md) | Superseded | Visual workflow research; replaced by Routines authored in chat. No flow builder |
| [linear-project-backlog-coverage-2026-10-09.md](planning/linear-project-backlog-coverage-2026-10-09.md) | Historical | Earlier Linear coverage; replaced by the 2026-11-02 coverage doc |
| [modular-platform-and-allbase-lab.md](planning/modular-platform-and-allbase-lab.md) | Superseded | Superseded — replaced by add-on apps (pillar 7). Reader-first modular OS and Allbase lab |
| [lua-scripting.md](planning/lua-scripting.md) | Superseded | Lua scripting strategy; Lua is retired in favor of mruby Code Mode |

## Tools and runtime references

| Document | Class | What it covers |
|---|---|---|
| [tools/vfs.md](tools/vfs.md) | Active, partially superseded | Current VFS runtime contract; its Lua helper envelope is superseded by mruby Code Mode |
| [tools/charts.md](tools/charts.md) | Active | Visualization tools reference |
| [guides/mruby-installation.md](guides/mruby-installation.md) | Active | Installing the mruby engine release candidate |
| [resources/code-docs/](../resources/code-docs/) (`_overview.md`, `context.md`, `errors.md`, `examples.md`, `vfs.md`, `web.md`) | Active | Agent-facing mruby Code Mode documentation |

## Ecosystem and integrations

All of `ecosystem/integrations/` describes the deleted package system; the `integrations` repository is archived. Deep connectors are app-owned under `app/Domain/Connections/` (pillar 9).

| Document | Class | What it covers |
|---|---|---|
| [ecosystem/integrations/README.md](ecosystem/integrations/README.md) | Superseded | Integration package authoring reference |
| [ecosystem/integrations/core/README.md](ecosystem/integrations/core/README.md) | Superseded | integration-core contracts |
| Package READMEs: [celestial](ecosystem/integrations/celestial/README.md), [clickup](ecosystem/integrations/clickup/README.md), [coingecko](ecosystem/integrations/coingecko/README.md), [exchangerate](ecosystem/integrations/exchangerate/README.md), [google](ecosystem/integrations/google/README.md), [mermaid](ecosystem/integrations/mermaid/README.md), [plausible](ecosystem/integrations/plausible/README.md), [ticktick](ecosystem/integrations/ticktick/README.md), [trustmrr](ecosystem/integrations/trustmrr/README.md), [worldbank](ecosystem/integrations/worldbank/README.md) | Superseded | Per-package references; the Google package code is mined for the native Drive, Gmail and Calendar connectors |
| Generated Lua API references: [clickup.md](ecosystem/integrations/clickup/clickup.md), [coingecko.md](ecosystem/integrations/coingecko/coingecko.md), [exchangerate.md](ecosystem/integrations/exchangerate/exchangerate.md), [google.md](ecosystem/integrations/google/google.md), [plausible.md](ecosystem/integrations/plausible/plausible.md), [ticktick.md](ecosystem/integrations/ticktick/ticktick.md) | Superseded | Per-package Lua API docs |
| [ecosystem/iris/ecosystem-overview.md](ecosystem/iris/ecosystem-overview.md), [ecosystem/iris/missing-in-iris.md](ecosystem/iris/missing-in-iris.md) | Historical | Imported Iris audits (self-declared historical) |

### Imported KosmoKrator corpus (Historical)

All files under `ecosystem/kosmokrator/` are imported external-project material, not OpenCompany
behavior.

| Area | Files |
|---|---|
| Index | [README.md](ecosystem/kosmokrator/README.md), [deep-audit-2026-04-04.md](ecosystem/kosmokrator/deep-audit-2026-04-04.md) |
| Architecture | [overview.md](ecosystem/kosmokrator/architecture/overview.md), [permission-modes.md](ecosystem/kosmokrator/architecture/permission-modes.md), [subagent-architecture.md](ecosystem/kosmokrator/architecture/subagent-architecture.md) |
| Audits | [deep-audit-2026-04-02.md](ecosystem/kosmokrator/audits/deep-audit-2026-04-02.md), [memory-leak-audit.md](ecosystem/kosmokrator/audits/memory-leak-audit.md), [self-audit-2026-03-30.md](ecosystem/kosmokrator/audits/self-audit-2026-03-30.md), [RAM-EFFICIENCY-AUDIT.md](ecosystem/kosmokrator/audits/ram-audit/RAM-EFFICIENCY-AUDIT.md), [synthesis-architecture.md](ecosystem/kosmokrator/audits/ram-audit/synthesis-architecture.md), [synthesis-core-agent.md](ecosystem/kosmokrator/audits/ram-audit/synthesis-core-agent.md), [synthesis-io-performance.md](ecosystem/kosmokrator/audits/ram-audit/synthesis-io-performance.md), [synthesis-security.md](ecosystem/kosmokrator/audits/ram-audit/synthesis-security.md) |
| Proposals | [command-inspiration.md](ecosystem/kosmokrator/proposals/command-inspiration.md), [context-compaction.md](ecosystem/kosmokrator/proposals/context-compaction.md), [context-management-redesign.md](ecosystem/kosmokrator/proposals/context-management-redesign.md), [context-management-strategies.md](ecosystem/kosmokrator/proposals/context-management-strategies.md), [desktop-app.md](ecosystem/kosmokrator/proposals/desktop-app.md), [ecosystem-architecture.md](ecosystem/kosmokrator/proposals/ecosystem-architecture.md), [integration-refactor-plan.md](ecosystem/kosmokrator/proposals/integration-refactor-plan.md), [laravel-ai-patterns.md](ecosystem/kosmokrator/proposals/laravel-ai-patterns.md), [streaming.md](ecosystem/kosmokrator/proposals/streaming.md), [tui-ux-improvements.md](ecosystem/kosmokrator/proposals/tui-ux-improvements.md) |
| Reports and research | [sub-agent-swarm-gaps-and-frankenstein-analysis.md](ecosystem/kosmokrator/reports/sub-agent-swarm-gaps-and-frankenstein-analysis.md), [claude-code-analysis.md](ecosystem/kosmokrator/research/claude-code-analysis.md), [claude-code-architecture.md](ecosystem/kosmokrator/research/claude-code-architecture.md), [opencode-feature-analysis.md](ecosystem/kosmokrator/research/opencode-feature-analysis.md) |
| Tools | [webfetch-system-prompt.md](ecosystem/kosmokrator/tools/webfetch-system-prompt.md), [web-tools-spec.md](ecosystem/kosmokrator/tools/web-tools-spec.md) |

## Testing and QA

| Document | Class | What it covers |
|---|---|---|
| [qa-strategy.md](testing/qa-strategy.md) | Historical | Testing pyramid and CI guidance from the March/April audit window (self-declared historical) |
| [mruby-ax-corpus.md](testing/mruby-ax-corpus.md) | Active | Deterministic mruby agent-experience regression corpus |
| [mruby-docker.md](testing/mruby-docker.md) | Active | Docker qualification and engine artifact verification |
| [mruby-history-browser.md](testing/mruby-history-browser.md) | Active | Mocked-API browser checks of the source-history UI |
| [mruby-console-browser.md](testing/mruby-console-browser.md) | Active | Mocked-API console rendering regression |
| [mruby-local-site.md](testing/mruby-local-site.md) | Active | Isolated PHP-FPM/browser and worker qualification |
| [mruby-dependency-audit.md](testing/mruby-dependency-audit.md) | Active | Dated dependency advisories for the engine |
| [mruby-glm-evals-2026-11-02.md](testing/mruby-glm-evals-2026-11-02.md) | Historical (dated evidence) | mruby Code Mode model evaluation run, 2026-11-02 |
| [browser-full-qa-2026-05-24.md](qa/browser-full-qa-2026-05-24.md) | Historical | Deep browser checkup with 2026-08-09 reconciliation notes |
| [browser-qa-2026-05-24.md](qa/browser-qa-2026-05-24.md) | Historical | Browser route/UI report with revalidation boundary |

## Audits

| Document | Class | What it covers |
|---|---|---|
| [vendor-open-source-audit-2026-04-10.md](vendor-open-source-audit-2026-04-10.md) | Historical | Open-source vendor/package audit snapshot |
| [vendor-open-source-critical-audit-2026-04-10-round-2.md](vendor-open-source-critical-audit-2026-04-10-round-2.md) | Historical | Critical follow-up vendor audit |

## UI/UX

The page specifications describe today's Inertia pages. Inertia is frozen: no new Inertia pages or
page props. New UI is the Vue 3 + Reka client following
[ui-structure-and-agent-editing.md](planning/ui-structure-and-agent-editing.md),
[messenger-ui.md](planning/messenger-ui.md) and
[api-first-client-split.md](architecture/api-first-client-split.md); Inertia pages are deleted as it
reaches parity. One concept per job: Dashboard, Activity and Workload → Home; Lists → Issues;
Org → Team; Automation → Routines; three file stores → one Files store.

| Document | Class | What it covers |
|---|---|---|
| [design-system.md](ui/design-system.md) | Active | Design tokens, colors, typography, spacing, accessibility |
| [components.md](ui/components.md) | Active | Shared components with props, slots, events |
| [layouts.md](ui/layouts.md) | Active, partially superseded | Layout patterns and responsive breakdowns; behaviour-mode settings superseded |
| [pages/chat.md](ui/pages/chat.md), [pages/messages.md](ui/pages/messages.md) | Active | Chat and DM pages; the starting point for the Phase 1 messenger |
| [pages/approvals.md](ui/pages/approvals.md), [pages/tasks.md](ui/pages/tasks.md), [pages/tables.md](ui/pages/tables.md), [pages/tables-detail.md](ui/pages/tables-detail.md), [pages/docs.md](ui/pages/docs.md), [pages/files.md](ui/pages/files.md) | Active | Approvals, tasks, tables, docs, and files pages (today's Inertia implementation) |
| [pages/auth.md](ui/pages/auth.md), [pages/profile.md](ui/pages/profile.md), [pages/welcome.md](ui/pages/welcome.md), [pages/workspace.md](ui/pages/workspace.md) | Active | Remaining page specifications |
| [pages/agent-detail.md](ui/pages/agent-detail.md), [pages/settings.md](ui/pages/settings.md) | Active, partially superseded | Agent profile and workspace settings; behaviour-mode selectors superseded by the policy model |
| [pages/calendar.md](ui/pages/calendar.md) | Active, partially superseded | Built-in calendar page; becomes the Calendar add-on app (Phase 3) on calendar connections |
| [pages/automation.md](ui/pages/automation.md) | Superseded | Automations UI → Routines |
| [pages/integrations.md](ui/pages/integrations.md) | Superseded | Package-catalog integrations UI; replaced by Connections |
| [pages/dashboard.md](ui/pages/dashboard.md), [pages/activity.md](ui/pages/activity.md), [pages/workload.md](ui/pages/workload.md) | Superseded | Dashboard, Activity and Workload → Home |
| [pages/lists.md](ui/pages/lists.md) | Superseded | Lists → Issues |
| [pages/org.md](ui/pages/org.md) | Superseded | Org → Team |
| [pages/developer.md](ui/pages/developer.md) | Active, partially superseded | Developer tool catalog; its Lua console sections are superseded by the mruby Code Mode console |

## Private material

| Location | Class | Notes |
|---|---|---|
| `docs/confidential/` | Historical | Gitignored local mirror of older private strategy notes; never committed. The sibling `oc-docs` vault is canonical for private material |

---

## Key Concepts

| Term | Meaning |
|------|---------|
| **AI teammate** | A named agent that is a workspace member with identity, manager, memory, skills, routines, budget, grants and, optionally, a computer |
| **Computer** | An optional per-agent machine supplied by a `ComputerProvider` (planned, Phase 2). OpenCompany works fully without one |
| **Code Mode** | The in-process mruby scripting runtime for work against OpenCompany's own APIs; the only scripting runtime |
| **VFS** | The permission-aware virtual filesystem over company data (tables, issues, docs, files) |
| **Connection / Event / Trigger / Routine** | The pillar 9 model: connections produce events, triggers match them and start routines, which execute as runs (planned) |
| **Policy model** | One policy engine with Personal/Team/Company presets, gated by reversibility (change sets, trash, drafts) rather than by "write", with scoped exceptions and on-behalf-of access; replaces behaviour modes (planned, Phase 0) |
| **ListItems** | Kanban board items at `/lists` — user-managed todo items (folding into Issues) |
| **Tasks** | Agent cases at `/tasks` — discrete work items that agents execute (becoming runs inside threads and the agent profile) |
| **Home** | The planned attention page: needs-you, then results, then FYI; replaces Dashboard, Activity and Workload |
| **`oc`** | The planned agent-first Rust CLI and connected-host daemon; a client of the public API, with `oc mcp serve` exposing the workspace to local coding agents |
| **Agent connection** | A planned connection kind for delegating a task to an outside agent (Cursor Cloud Agents, Copilot coding agent, Codex, Jules, Claude Code, ACP coding CLIs, later Bowerbird Agents) through the provider-neutral `ExternalAgentProvider` contract; launching one is an external action and its events are untrusted (Phases 2–3) |
| **Connected host** | A user's own machine (Raspberry Pi, home server, laptop, VPS) linked through the outbound-only `oc host` daemon, with per-agent capability grants (computer, local MCP bridge, VFS projection, event source); labelled self-managed (planned, Phase 2) |
| **`.ocarchive`** | The planned workspace archive: every primitive, app data, history, event log, files, agents, Spaces and policies, round-trippable into self-hosted or Cloud; secrets excluded by default; spec published under Apache-2.0 |
| **Git mirror** | An optional, planned one-way continuous mirror of Docs (Markdown), Tables (CSV), Issues and artifact source to the customer's Git host; OpenCompany remains the source of truth (Phase 3) |
| **Host token** | A scoped, rotated credential that a connected host uses to reach the workspace; distinct from personal tokens (planned, Phase 0 groundwork) |
| **Space** | A flat container (never nested) with the same six tools: Issues, Tables, Docs, Files, Artifacts, Routines (planned) |
| **Artifact** | The only code-bearing core primitive: a Page or an interactive artifact bound to governed data, with no private storage, immutable versions and stable URLs (planned, from Phase 1) |
| **Page** | A declarative artifact built from blocks (text, live views, charts, KPIs, queries, diagrams, timelines), live or snapshotted, with no code (planned) |
| **Form view** | A view type on any Table whose submissions go through one governed pipeline and emit an untrusted `form.submitted` event (planned, Phase 2) |
| **Publishing ladder** | Draft → Space → workspace → signed link → public; public is off per workspace by default, with a published-surfaces registry and kill switch (planned) |
| **Add-on app** | An optional manifest-defined app (Mail, Calendar, CRM, …) built only from core primitives (planned) |
| **Change set** | Staged agent edits reviewed and applied by a human; any run can be reverted (planned) |
| **OpenClaw** | Open-source AI agent platform studied for architectural patterns |
| **QMD** | Quick Markdown — OpenClaw's memory search system; adapted with PostgreSQL + pgvector |
| **Laravel AI SDK** | Provider toolkit underneath OpenCompany's app-owned AI runtime in `app/Domain/Ai`, `app/Ai`, and `config/ai.php` |

# Docs/Code Alignment Audit

Date: 2026-05-24
Status: In progress. This audit records code-backed documentation alignment work; it is not a claim that every Markdown file has been fully checked yet.

## Current Evidence Pass

The current pass checked the tracked and confidential Markdown inventory against high-risk code surfaces:

- Markdown inventory after this pass: 155 files across tracked docs, untracked docs in `docs/`, plus `docs/confidential`.
- Route inventory: 357 Laravel routes from `php artisan route:list --json`.
- UI page inventory: 23 `docs/ui/pages/*.md` files covering routed page groups and legacy redirect surfaces.
- `AGENTS.md` and `CLAUDE.md` remain byte-identical.

## Updates Made In This Pass

- Added [files.md](../ui/pages/files.md) for `resources/js/Pages/Files.vue`, `useFileManager`, and file/disk API endpoints.
- Added [developer.md](../ui/pages/developer.md) for `Developer/Tools.vue`, `Developer/LuaConsole.vue`, `/api/tools/catalog`, and `/api/lua/execute`.
- Added [messages.md](../ui/pages/messages.md) to document that `/messages` routes now redirect into Chat, while legacy `Messages/*.vue` components remain present but unrouted.
- Added [workspace.md](../ui/pages/workspace.md) for setup, workspace creation, and invitation acceptance flows.
- Updated [chat.md](../ui/pages/chat.md) and [profile.md](../ui/pages/profile.md) so DM links and redirects match current routes.
- Updated [../discord.md](../discord.md) so the sidecar design is clearly marked historical and the current Chatogrator adapter/webhook path is not contradicted.
- Updated [../planning/chatogrator.md](../planning/chatogrator.md) so it distinguishes the package's standalone webhook route from OpenCompany's workspace-aware `/api/webhooks/chat/{adapter}` route and current adapter wiring.
- Updated [../planning/integrations.md](../planning/integrations.md) so the current-state table reflects the installed integration package/catalog counts and Chatogrator-backed chat adapter wiring.
- Updated [../planning/integration-refactor.md](../planning/integration-refactor.md) so old refactor pain points are marked as historical and the May 2026 code check records the current `integration-core` contracts and provider registry shape.
- Updated [../ai-providers.md](../ai-providers.md) so provider setup no longer claims every provider requires a static `config/integrations.php` row and the implementation pattern matches `AiCatalog`-driven provider cards.
- Updated [ai-provider-runtime-architecture.md](ai-provider-runtime-architecture.md) so prompt-cache gateway files and direct AI Gateway usage recording are represented in the runtime map.
- Updated [../README.md](../../README.md) and [technology-decisions.md](technology-decisions.md) so provider failover is described as a Laravel AI SDK capability, while current app support is framed around the app-owned provider catalog.
- Updated [../documentation.md](../documentation.md) and [../INDEX.md](../INDEX.md) so external-channel docs reference the current Chatogrator-backed architecture instead of the older Discord sidecar/not-started framing.
- Updated confidential monetization notes so BSL 1.1 + Additional Use Grant remains the authoritative current license posture and workspace/plan gating is not presented as shipped without enforcement code.
- Updated [../tools/charts.md](../tools/charts.md) so visualization renderers are described as built-in SVG plus integration-package tools reached through Lua, not generic external MCP tools.
- Updated [../planning/lua-scripting.md](../planning/lua-scripting.md) and [../../resources/lua-docs/_overview.md](../../resources/lua-docs/_overview.md) so current script automations use `execution_type = "script"`, `RunScriptAutomationJob`, `LuaSandboxService`, and the current 30-second sandbox default instead of the older "No Lua yet" / `script_language` plan.
- Updated [../planning/memory-implementation.md](../planning/memory-implementation.md) and [../planning/memory-systems.md](../planning/memory-systems.md) so memory docs describe private-channel-only `MEMORY.md` prompt injection, current `memory/logs/YYYY-MM-DD.md` storage, weighted RRF retrieval, and current reranking provider options.
- Updated [../planning/dream-vfs.md](../planning/dream-vfs.md) so it treats the identity/memory refactor and current `WorkspaceFile` tools as implemented baseline while keeping Dream consolidation and unix-style VFS tools as roadmap.
- Updated [../planning/vector-optional.md](../planning/vector-optional.md) so the optional-embeddings plan explicitly states the current write path still requires embeddings and reorders follow-up work from the now-implemented memory refactor baseline.
- Updated [../planning/codex-subscription-auth.md](../planning/codex-subscription-auth.md) so OpenCompany's current Codex implementation is described as app-local device auth plus `CodexTextGateway`, with only the generated provider catalog treated as the current model list.
- Updated integration package reference docs under [../ecosystem/integrations](../ecosystem/integrations/) against the current `ToolProviderRegistry` counts, including CoinGecko, ExchangeRate, Google, and World Bank.
- Clarified the [../ecosystem/integrations/README.md](../ecosystem/integrations/README.md) package table as the curated local docs set, not the full installed `integration-bundle`/generated catalog surface.
- Updated confidential strategy/website drafts so OpenCompany's community posture is source-available under BSL 1.1 + Additional Use Grant, not MIT or generic open source.
- Updated [interagent-comms.md](interagent-comms.md) so the implemented `ContactAgent` ask path is documented as an async `agent_ask` task/callback rather than the older synchronous inline ask/timeout/depth-counter design.
- Added explicit research/status guards to [openclaw-patterns.md](openclaw-patterns.md) and [openclaw-reference.md](openclaw-reference.md) so OpenClaw material is not confused with current OpenCompany behavior.
- Updated [../ui/pages/tasks.md](../ui/pages/tasks.md) to cover `Tasks/Analytics.vue`, `/w/{workspace}/tasks/analytics`, and `GET /api/tasks/analytics/tokens`, and to describe `agent_ask` as async.
- Updated [../ui/pages/agent-detail.md](../ui/pages/agent-detail.md) so the inter-agent communication section matches the implemented async `contact_agent`/`agent_ask` callback flow.
- Updated [../documentation.md](../documentation.md) so the docs-site blueprint no longer marks Lua scripting as a future feature and describes current agent orchestration rather than ephemeral spawning.
- Updated [pest-browser-testing-implementation-plan-2026-05-10.md](pest-browser-testing-implementation-plan-2026-05-10.md) so the Pest migration note reflects the current PHP `^8.4` requirement and Pest v4 dependency state.
- Updated [../ui/pages/integrations.md](../ui/pages/integrations.md) for prefixed integration card IDs, the split AI-provider config endpoints, and the new generic webhook backend while noting the Vue webhook list still uses local state.
- Updated [../planning/integrations.md](../planning/integrations.md) so generic webhooks are described as persisted receipt/diagnostic endpoints, not as fully routed agent/channel/task delivery.
- Updated [../external-channel-sync.md](../external-channel-sync.md), [../planning/chatogrator.md](../planning/chatogrator.md), and [../discord.md](../discord.md) so chat webhook workspace resolution is described as proof-backed rather than payload-only.
- Updated confidential OpenAI Frontier competitive notes so OpenCompany is described as source-available/self-hostable under BSL 1.1 + Additional Use Grant, not fully open source.
- Updated [../INDEX.md](../INDEX.md) so the new UI page docs are discoverable.
- Updated [integrations-catalog-branch-audit-2026-05-10.md](integrations-catalog-branch-audit-2026-05-10.md) so the old branch audit is explicitly historical and no longer presents resolved multi-account OAuth, OAuth middleware, catalog availability, paging, or dedupe findings as current blockers.
- Updated [../confidential/website/features.md](../confidential/website/features.md) so external-channel marketing inventory distinguishes current Chatogrator adapter/webhook surfaces from remaining setup/hardening work, instead of calling Discord only a planned sidecar.
- Updated [../planning/codex-subscription-auth.md](../planning/codex-subscription-auth.md) so `gpt-5.3-codex` is described as the current generated app default, not a timeless "latest" model claim.
- Updated [../../.claude/commands/create-integration.md](../../.claude/commands/create-integration.md) for the current `../integrations/packages/*` monorepo layout.
- Updated [../ui/design-system.md](../ui/design-system.md) to point design-token readers at `resources/css/app.css`.
- Updated [../ui/pages/approvals.md](../ui/pages/approvals.md) so approval API docs match `useApi().respondToApproval()` and server-side authenticated responder handling.
- Updated [../ui/pages/tables-detail.md](../ui/pages/tables-detail.md) so alternate table views are documented as rendered `TableKanban`, `TableGallery`, and `TableCalendar` components with persisted view APIs.
- Updated confidential ecosystem overview notes so April 2026 package/runtime counts are marked as a snapshot and current provider/package counts are delegated to `composer.lock`, integration catalog config, and generated AI catalog files.
- Updated confidential landing-page, licensing, and ecosystem strategy wording so OpenCompany is consistently source-available under BSL 1.1 + Additional Use Grant, not generic "open source" or SUL.
- Updated confidential pricing/token strategy so free self-hosted limits are framed as proposed product entitlements layered on top of BSL self-hosting, not license-imposed limits.
- Updated [../ecosystem/integrations/README.md](../ecosystem/integrations/README.md) installation instructions for the current `../integrations/core` plus `../integrations/packages/*` path repository layout.
- Updated [../INDEX.md](../INDEX.md) so the May 10 integration-catalog audit is indexed as historical hardening context rather than current blocker guidance.
- Updated [../INDEX.md](../INDEX.md) so the observability plan is indexed as a proposal with planned paths, not as shipped admin/ops inventory.
- Updated [../INDEX.md](../INDEX.md) with directory-level coverage for generated integration Lua docs and imported KosmoKrator architecture/audit leaf docs.
- Folded the new [embedded-chromium-browser-investigation-2026-05-24.md](embedded-chromium-browser-investigation-2026-05-24.md) into the audit inventory and added an explicit investigation/proposal status guard.
- Updated confidential competitive landscape notes so OpenCompany's license, MCP support, external-channel sync, token-streaming status, and agent-to-agent flow match the current BSL/app-owned-runtime/MCP-client/Chatogrator/async-task codebase.
- Added explicit strategy/proposal guards to confidential SSO, GCP cloud infrastructure, tenancy/BYOK, and USA/Dubai corporate structure docs so planned organization billing/SSO/license/cloud infrastructure is not confused with current workspace-scoped app code.
- Updated confidential corporate-structure language from generic fair-source wording to the current BSL 1.1 + Additional Use Grant posture.

## Mechanical Checks

These checks passed after the updates:

```bash
git diff --check
```

```bash
# Local Markdown links across tracked Markdown plus docs/confidential:
# 155 files checked; fenced and inline code examples ignored.
php <local-link-checker>
```

```bash
# docs/INDEX.md coverage for tracked docs:
# Direct doc links plus intentional directory-level entries for imported KosmoKrator
# and generated integration sub-docs were checked.
php <docs-index-coverage-checker>
```

```bash
# resources/js/Components/shared inventory against docs/ui/components.md:
# 28 shared Vue components checked.
php <shared-component-inventory-checker>
```

```bash
# documented API route references against php artisan route:list --json:
# 251 API routes checked. Historical/planning docs, external API examples, and
# intentionally documented missing table import/export/duplicate routes excluded.
php <api-route-reference-checker>
```

```bash
# resources/js/Pages coverage against docs/ui/pages/*.md:
# 38 Vue page files checked against 23 UI page docs.
php <ui-page-coverage-checker>
```

```bash
# Inline path references in current, non-imported, non-planning docs:
# Existing app/docs/package paths checked. Imported KosmoKrator repo-relative
# paths and standalone package `config/ai-tools.php` examples excluded.
php <inline-path-reference-checker>
```

```bash
# Current inventory/count claims:
# composer.lock integration packages: 585
# IntegrationCatalog totals: 591 integrations, 41,493 tools
# ToolProviderRegistry totals: 595 providers, 41,420 registered runtime tools
# AI provider catalog: 22 provider IDs
# Laravel route inventory: 357 total routes
php <inventory-count-checkers>
```

## Known Historical/Imported Exceptions

The stale-term scan still reports expected historical/imported references:

- `docs/architecture/laravel-ai-sdk.md` keeps old GLM and Prism removal details, but the file is marked historical and points current runtime readers to `ai-provider-runtime-architecture.md`.
- `docs/vendor-open-source-audit-2026-04-10.md` and `docs/vendor-open-source-critical-audit-2026-04-10-round-2.md` intentionally retain Prism findings as removal rationale.
- `docs/ecosystem/kosmokrator/**` and `docs/ecosystem/iris/**` are imported ecosystem context; Prism references there describe those repos or historical comparisons, not current OpenCompany runtime dependencies.
- `docs/confidential/strategy/fair-code-valuation-strategy.md` uses `Sustainable Use License` as an n8n comparator, not as an OpenCompany license claim.
- `docs/confidential/research/competitive-landscape.md` uses `SUL` only for n8n/competitor comparison, not as an OpenCompany license claim.
- `docs/testing/qa-strategy.md` retains the old `tmp/integrations/` to `../integrations/` move in a historical commit-range note.
- `AGENTS.md` and `CLAUDE.md` mention Prism only as a guardrail against reintroducing Prism runtime dependencies.

## Remaining Work

Completion remains unproven until the broader Markdown set has been semantically reviewed for non-mechanical claims that cannot be covered by route, path, inventory, link, UI, and stale-term validators, especially confidential strategy/research docs and imported ecosystem material.

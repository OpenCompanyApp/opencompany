# Docs/Code Alignment Audit

Date: 2026-05-24
Status: In progress. This audit records code-backed documentation alignment work; it is not a claim that every Markdown file has been fully checked yet.

## Current Evidence Pass

The current pass checked the tracked and confidential Markdown inventory against high-risk code surfaces:

- Markdown inventory after this pass: 154 files across tracked docs plus `docs/confidential`.
- Route inventory: 348 Laravel routes from `php artisan route:list --json`.
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
- Updated confidential strategy/website drafts so OpenCompany's community posture is source-available under BSL 1.1 + Additional Use Grant, not MIT or generic open source.
- Updated [interagent-comms.md](interagent-comms.md) so the implemented `ContactAgent` ask path is documented as an async `agent_ask` task/callback rather than the older synchronous inline ask/timeout/depth-counter design.
- Added explicit research/status guards to [openclaw-patterns.md](openclaw-patterns.md) and [openclaw-reference.md](openclaw-reference.md) so OpenClaw material is not confused with current OpenCompany behavior.
- Updated [../ui/pages/tasks.md](../ui/pages/tasks.md) to cover `Tasks/Analytics.vue`, `/w/{workspace}/tasks/analytics`, and `GET /api/tasks/analytics/tokens`, and to describe `agent_ask` as async.
- Updated [../INDEX.md](../INDEX.md) so the new UI page docs are discoverable.

## Mechanical Checks

These checks passed after the updates:

```bash
git diff --check
```

```bash
# Local Markdown links across tracked Markdown plus docs/confidential
php <local-link-checker>
```

```bash
# docs/INDEX.md coverage for tracked docs
php <docs-index-coverage-checker>
```

```bash
# resources/js/Components/shared inventory against docs/ui/components.md
php <shared-component-inventory-checker>
```

```bash
# documented API route references against php artisan route:list --json
php <api-route-reference-checker>
```

```bash
# resources/js/Pages coverage against docs/ui/pages/*.md
php <ui-page-coverage-checker>
```

## Known Historical/Imported Exceptions

The stale-term scan still reports expected historical/imported references:

- `docs/architecture/laravel-ai-sdk.md` keeps old GLM and Prism removal details, but the file is marked historical and points current runtime readers to `ai-provider-runtime-architecture.md`.
- `docs/vendor-open-source-audit-2026-04-10.md` and `docs/vendor-open-source-critical-audit-2026-04-10-round-2.md` intentionally retain Prism findings as removal rationale.
- `docs/ecosystem/kosmokrator/**` and `docs/ecosystem/iris/**` are imported ecosystem context; Prism references there describe those repos or historical comparisons, not current OpenCompany runtime dependencies.
- `docs/confidential/strategy/fair-code-valuation-strategy.md` uses `Sustainable Use License` as an n8n comparator, not as an OpenCompany license claim.

## Remaining Work

Completion remains unproven until the broader Markdown set has been sampled or mechanically checked for stale claims beyond route/page/provider surfaces, especially older planning docs and confidential strategy docs.

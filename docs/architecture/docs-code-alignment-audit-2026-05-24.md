# Docs/Code Alignment Audit

Date: 2026-05-24
Status: In progress. This audit records code-backed documentation alignment work; it is not a claim that every Markdown file has been fully checked yet.

## Current Evidence Pass

The current pass checked the tracked and confidential Markdown inventory against high-risk code surfaces:

- Markdown inventory after this pass: 157 files across tracked Markdown,
  `docs/confidential`, and `resources/lua-docs`.
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
- Refreshed confidential OpenAI Frontier research against OpenAI/Axios/Fortune/TechCrunch sources, removing unsupported named-vendor agent compatibility and replacing an invented/paraphrased customer quote with an analyst note.
- Updated confidential emergent-strategy notes so the implemented MCP surface is correctly described as a remote MCP client/runtime; OpenCompany-as-MCP-server export remains roadmap.
- Added explicit status guards to confidential corporate-structure and domain research snapshots.
- Updated [../planning/integrations.md](../planning/integrations.md) so the Lua scripting bridge integration pattern is no longer labeled wholly planned; current `app.*` Lua bridge and script automations are distinguished from older proposed `oc.*` syntax.
- Added a current-runtime note to [../planning/lua-scripting.md](../planning/lua-scripting.md) so the older `oc.*` namespace examples are explicitly future ergonomics while current execution uses `app.*` through `LuaBridge`.
- Folded the new [web-search-fetch-adapter-investigation-2026-05-24.md](web-search-fetch-adapter-investigation-2026-05-24.md) into the Markdown inventory and added an explicit investigation/proposal status guard after checking the linked Tavily, Firecrawl, Exa, and Jina provider docs.
- Cross-checked the added web-search/fetch parity plan against the local KosmoKrator `src/Web` snapshot, including native provider managers, the older provider registry, Z.AI MCP/search-reader classes, and provider names; added a guard that KosmoKrator endpoints/default models are snapshot inputs rather than timeless vendor guarantees.
- Rechecked [../ui/pages/chat.md](../ui/pages/chat.md) against the current dirty `resources/js/Pages/Chat.vue` worktree and updated it from the old separate `ChannelList` / `Area` / `ChannelInfo` layout to the active unified `AssistantChatShell` / `ConversationSidebar` / `AssistantConversation` surface.
- Updated [../ui/pages/chat.md](../ui/pages/chat.md) again for the same dirty worktree's new Echo stream-event bridge: `.text_start`, `.text_delta`, `.text_end`, `.stream_end`, and `.stream_failed` now create/update temporary streaming assistant messages in the page.
- Updated confidential streaming comparisons so OpenCompany is no longer described as having no streaming at all; current chat response stream events are distinguished from still-separate AI Gateway/general transport streaming.
- Rechecked [web-search-fetch-adapter-investigation-2026-05-24.md](web-search-fetch-adapter-investigation-2026-05-24.md) against the new dirty `config/web.php` plus `app/Domain/Web` scaffold. The first update moved it from pure proposal to partial scaffold when config, contracts, enum, exceptions, and value objects appeared.
- Rechecked the same web-search/fetch investigation after additional web runtime classes appeared in the worktree. At that point the doc distinguished present code (`WebCredentialResolver`, `StreamableMcpToolInvoker`, `WebRequestGuard`, extractors, `WebResultCache`, provider managers, provider registry, formatter, direct `web_search`/`web_fetch` tools, `WebToolProvider`, `config/integrations.php` web-provider setup entries, Integrations UI category support, web access settings UI/defaults, config-only connection validation, `resources/lua-docs/web.md`, structured-output normalization in `IntegrationRuntime`, focused web tool/safety/direct-fetch tests, and concrete Tavily/Z.AI/Firecrawl/Exa/Brave/Parallel/Jina/SearxNG/Perplexity/OpenAI-native/Anthropic-native adapters) from then-missing usage recorder, diagnostics/live smoke tests, and broader provider/manager/Lua coverage.
- Rechecked the web runtime again after usage and diagnostic command files landed. The web investigation now records `WebUsageRecorder`, `WebUsageEvent`, `web_usage_events`, `WebAccessPolicy`, the then-current `web:providers` / `web:doctor` / `web:search` / `web:fetch` commands, and the later `web:configure` command is reconciled below.
- Rechecked [../ui/pages/chat.md](../ui/pages/chat.md) after the task-thinking UI changed. It now documents `triggerMessageId` task anchoring, inline `ThinkingPanel` placement after the spawning message, and the fallback first-task panel for empty assistant conversations.
- Rechecked [../ui/pages/settings.md](../ui/pages/settings.md) after `WebAccessSettings` and the `web` settings category appeared. It now lists Memory, Web Access, Storage, Debug, and Danger Zone as separate sections and documents web provider defaults, external fetch, limits, cache, domain, locale, and recency controls.
- Rechecked [../ui/pages/integrations.md](../ui/pages/integrations.md) after static `web.*` provider setup entries and the `web-providers` category appeared. It now documents that web providers open through `DynamicConfigModal` and lists the current web provider setup slugs.
- Rechecked [web-search-fetch-adapter-investigation-2026-05-24.md](web-search-fetch-adapter-investigation-2026-05-24.md) against the current `WebResultCache` implementation and clarified that the implemented baseline is workspace-scoped transient cache-store caching, while a persistent cache table remains optional future audit/diagnostic work.
- Rechecked [../ui/pages/chat.md](../ui/pages/chat.md) after `Chat.vue` added `startNewAssistantChat()`. The doc now explains that the New assistant chat action clears the selected channel into a draft state and sends the first prompt through the existing durable agent-DM path.
- Rechecked [web-search-fetch-adapter-investigation-2026-05-24.md](web-search-fetch-adapter-investigation-2026-05-24.md), [../ui/pages/settings.md](../ui/pages/settings.md), and [../INDEX.md](../INDEX.md) after the web usage recorder and web Artisan commands were committed. The docs now distinguish shipped `web_usage_events` / `WebUsageRecorder` / `web:providers` / `web:doctor` / `web:search` / `web:fetch` behavior from credential-gated live provider smoke tests and broader provider/manager/Lua coverage; `web:configure` was added to the current command list once that command appeared.
- Rechecked Web Access settings against `WebResultCache` and `DirectFetchProvider`: cache TTL was workspace setting-backed at that point, while `web_fetch_max_bytes` was still UI-only until the follow-up runtime hookup below.
- Rechecked [../ui/pages/chat.md](../ui/pages/chat.md) after `ThinkingPanel` added catalog-backed `metadata.tool_name` display and syntax-highlighted Lua/JSON inspection sections for tool arguments, results, and Lua runtime metadata.
- Rechecked [web-search-fetch-adapter-investigation-2026-05-24.md](web-search-fetch-adapter-investigation-2026-05-24.md) again after focused tests expanded to web provider settings, credential resolver, provider managers, provider registry, IntegrationRuntime web-tool parsing, Lua API docs, extractors, result cache, provider adapter success normalization, native search citations, and Z.AI provider coverage. Remaining gaps are now framed as live provider smoke tests and provider adapter error/malformed-payload coverage.
- Rechecked Web Access settings after `DirectFetchProvider` started reading `web_fetch_max_bytes` from workspace settings; [../ui/pages/settings.md](../ui/pages/settings.md) now describes the config fallback instead of saying the setting is UI-only.
- Rechecked [ai-provider-runtime-architecture.md](ai-provider-runtime-architecture.md) and [../ui/pages/chat.md](../ui/pages/chat.md) after `AgentRun::stream()` added catalog-gated streaming fallback and `RespondToChatMessage` limited websocket broadcasts to chat-consumed stream events. The docs now distinguish streamed text events from task-step tool evidence.
- Rechecked the same web investigation and [../INDEX.md](../INDEX.md) after `WebConfigureCommand` appeared. The current web Artisan namespace is now documented as `web:providers`, `web:configure`, `web:doctor`, `web:search`, and `web:fetch`.
- Rechecked [../ui/design-system.md](../ui/design-system.md) after runtime/tool-inspector syntax colors were added to `resources/css/app.css`; the design-system guide now documents the `oc-syntax` highlight.js wrapper class.
- Rechecked [../ui/pages/chat.md](../ui/pages/chat.md) after `AgentSelector.vue` appeared and the sidebar, empty state, and composer switched from native selects/plus-only behavior to the shared Reka popover.
- Rechecked [web-search-fetch-adapter-investigation-2026-05-24.md](web-search-fetch-adapter-investigation-2026-05-24.md) after `WebLiveSmokeTest`, `WebProviderAdapterFailureTest`, and direct gzip/deflate decode coverage appeared. The remaining web hardening note now points at broader provider live smoke coverage rather than missing adapter failure/malformed-payload tests.

## Mechanical Checks

These checks passed after the updates:

```bash
git diff --check
```

```bash
# Local Markdown links across tracked Markdown, docs/confidential, and resources/lua-docs:
# 157 files checked; fenced and inline code examples ignored; 0 failures.
php <local-link-checker>
```

```bash
# docs/INDEX.md coverage for tracked docs:
# Direct doc links plus intentional directory-level entries for imported KosmoKrator
# and generated integration sub-docs were checked.
# 116 non-confidential docs checked; 0 coverage gaps.
php <docs-index-coverage-checker>
```

```bash
# resources/js/Components/shared inventory against docs/ui/components.md:
# 28 shared Vue components checked; all documented prop names match component Props definitions.
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

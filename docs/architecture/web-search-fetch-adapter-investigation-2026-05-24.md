# Web Search And Fetch Adapter Investigation

Date: 2026-05-24

Status: Investigation / implementation proposal with active app-owned
implementation baseline.
External provider notes were spot-checked on 2026-05-24 against the linked
Tavily, Firecrawl, Exa, and Jina docs. The current OpenCompany worktree has the
first app-owned scaffold in `config/web.php` plus `app/Domain/Web` contracts,
capability enum, exceptions, request/response value objects, credential
resolver, URL safety guard, extraction helpers, result cache service, direct
fetch provider, streamable-HTTP MCP helper, and concrete provider adapter
classes for Tavily, Z.AI search/fetch, Firecrawl, Exa, Brave, Parallel, Jina,
SearxNG, Perplexity, OpenAI-native search, and Anthropic-native search.
Provider managers, provider registry, web tool formatter, direct
`web_search`/`web_fetch` tool registration, web-provider setup entries in
`config/integrations.php`, the Integrations UI category, and config-only
connection validation also exist in the current worktree. Workspace web defaults
and controls are present in `AppSetting`, `SettingController`, `Settings.vue`,
and `WebAccessSettings.vue`. Lua `app.web.*` docs and structured-output
normalization are present through `resources/lua-docs/web.md` and
`IntegrationRuntime`. Workspace-scoped usage events are recorded through
`WebUsageRecorder` / `web_usage_events`, and `web:providers`, `web:configure`,
`web:doctor`, `web:search`, and `web:fetch` provide the current Artisan
diagnostics and operator probes. Focused tests cover direct tool registration,
formatted/structured search output, usage-event recording,
outline/section/chunk fetch behavior, workspace domain policy enforcement,
external-fetch opt-in behavior, URL safety, and direct HTML extraction. Broader
provider error/malformed-payload coverage, direct gzip/deflate decoding, and
opt-in direct/Tavily live smoke paths are present in the focused web test suite.
KosmoKrator provider class names, endpoints, and default models in the parity
plan reflect the local
`/Users/rutger/Projects/kosmokrator` snapshot checked on 2026-05-24; refresh
that source before treating any provider detail as a current vendor contract.

## Summary

OpenCompany should add non-browser web access before embedded browser sessions.
The shape should be adapter-based, app-owned, workspace-aware, and available in
both direct agent tools and Lua.

Recommended first capability set:

- `web_search`: search provider abstraction for source discovery.
- `web_fetch`: URL fetch/extract abstraction for static HTML, text, markdown,
  and provider-based readers.
- Optional later: `web_crawl`, `web_extract`, `web_research`, and browser-backed
  fetch fallback.

Current implementation state in this worktree:

- Present: `config/web.php`.
- Present: `app/Domain/Web/Contracts/WebProvider.php`,
  `WebSearchProvider.php`, and `WebFetchProvider.php`.
- Present: `app/Domain/Web/Enums/WebCapability.php`.
- Present: `app/Domain/Web/Exceptions/WebProviderException.php` and
  `WebFetchPermanentException.php`.
- Present: request/response value objects for search/fetch plus
  `ExtractedPage`.
- Present: `WebCredentialResolver`, `StreamableMcpToolInvoker`,
  `WebRequestGuard`, `WebAccessPolicy`, `HtmlPageExtractor`,
  `MarkdownPageExtractor`, `WebResultCache`, `WebUsageRecorder`, and
  `DirectFetchProvider`.
- Present: concrete provider classes for `tavily`, `firecrawl`, `exa`,
  `brave`, `parallel`, `jina`, `searxng`, `perplexity`, `openai_native`,
  `anthropic_native`, and Z.AI search/fetch.
- Present: `WebSearchProviderManager`, `WebFetchProviderManager`,
  `WebProviderRegistry`, `WebToolFormatter`, `WebToolProvider`,
  `WebSearchTool`, and `WebFetchTool`; `web` is in
  `ToolRegistry::DIRECT_TOOL_GROUPS` and `WebToolProvider` is registered in
  `AppServiceProvider`.
- Present: `web.*` provider setup entries in `config/integrations.php`, a
  `web-providers` category in `resources/js/Pages/Integrations.vue`, and
  `IntegrationConnectionTester` validation that required web-provider config is
  present without making live vendor calls.
- Present: web access settings defaults and UI controls for provider defaults,
  fallbacks, external provider fetch, cache TTL, search/fetch character limits,
  max fetch bytes, domain allow/block lists, country, language, and recency.
  `DirectFetchProvider` consumes `web_fetch_max_bytes` when a workspace is
  bound and falls back to `config('web.fetch.max_bytes')`.
- Present: `resources/lua-docs/web.md` for `app.web.search` /
  `app.web.fetch`, plus `IntegrationRuntime` parsing for formatter-emitted
  structured data.
- Present: focused tests in `tests/Feature/Tools/WebToolsTest.php`,
  `tests/Feature/Admin/WebProviderSettingsTest.php`,
  `tests/Feature/Domain/Web/WebCredentialResolverTest.php`,
  `tests/Feature/Domain/Web/WebLiveSmokeTest.php`,
  `tests/Feature/Domain/Web/WebProviderManagerTest.php`,
  `tests/Feature/Domain/Web/WebProviderRegistryTest.php`,
  `tests/Feature/Integrations/IntegrationRuntimeWebToolTest.php`,
  `tests/Feature/LuaApiDocGeneratorTest.php`,
  `tests/Unit/Domain/Web/WebRequestGuardTest.php`,
  `tests/Unit/Domain/Web/DirectFetchProviderTest.php`, and
  `tests/Unit/Domain/Web/HtmlPageExtractorTest.php`,
  `tests/Unit/Domain/Web/MarkdownPageExtractorTest.php`,
  `tests/Unit/Domain/Web/WebProviderAdapterFailureTest.php`,
  `tests/Unit/Domain/Web/WebProviderAdapterTest.php`,
  `tests/Unit/Domain/Web/WebResultCacheTest.php`, and
  `tests/Unit/Domain/Web/ZaiWebProviderTest.php`.
- Present: Artisan commands `web:providers`, `web:configure`, `web:doctor`,
  `web:search`, and `web:fetch`.
- Present: provider adapter error/malformed-payload coverage and
  credential-gated live provider smoke tests that skip unless explicitly
  enabled with local credentials.

This should be separate from the Playwright browser runtime. Most web research
does not need a full browser session; search/fetch should be the cheap default,
with browser used only for JS rendering, interaction, login, screenshots, file
upload/download, or human takeover.

## KosmoKrator Reference

KosmoKrator already has the right broad architecture under
`/Users/rutger/Projects/kosmokrator/src/Web`:

- `Contracts/WebSearchProvider.php` and `Contracts/WebFetchProvider.php` define
  tiny provider interfaces: `id()`, `isAvailable()`, and `search()`/`fetch()`.
- `Value/WebSearchRequest.php` supports query, provider override, result limit,
  allowed/blocked domains, search depth, snippets, and answer inclusion.
- `Value/WebFetchRequest.php` supports URL, provider override, mode, format,
  max chars, prompt/focus fields, section/match/chunk targeting, timeout,
  strategy, metadata, and outline toggles.
- `Provider/WebSearchProviderManager.php` chooses explicit/default/fallback
  providers, caches identical requests, hard-filters allowed/blocked domains,
  trims result count, and aggregates provider errors.
- `Provider/WebFetchProviderManager.php` chooses explicit/default/fallback
  providers, supports direct/provider-only strategies, caches responses, and
  stops fallback on permanent failures.
- `Provider/Fetch/DirectFetchProvider.php` does browser-like HTTP fetch,
  validates public URLs, limits bytes/timeouts, decodes gzip/deflate, extracts
  HTML to markdown, and supports plain text.
- `Extract/HtmlPageExtractor.php` removes noisy nodes, selects a content root,
  converts HTML to markdown, extracts metadata, builds an outline, and splits
  sections.
- `Safety/WebRequestGuard.php` blocks unsafe URLs: non-http(s), embedded
  credentials, localhost, and private/reserved resolved IPs.
- `Cache/WebTransientCache.php` keeps short-lived in-memory results by agent
  turn/generation.
- `Tool/Web/WebSearchTool.php` and `Tool/Web/WebFetchTool.php` are thin,
  model-facing tools that return human-readable text plus structured metadata.
- `config/kosmo.yaml` keeps provider defaults/fallbacks, output limits,
  timeouts, API key env names, and provider enablement under `kosmo.web`.

Useful provider coverage in KosmoKrator:

- Search: Tavily, Z.AI MCP/chat search, Brave, Exa, Firecrawl, Jina, SearxNG,
  Perplexity, OpenAI-native, Anthropic-native.
- Fetch: direct static fetch, Z.AI reader, Firecrawl/Jina-style provider fetch.
- Crawl/research: present in older registry concepts, but not needed for the
  OpenCompany MVP.

## Current Provider Reality

Recent official docs confirm that the provider ecosystem still maps well to
this abstraction:

- Tavily search supports answers, cleaned/raw content, images/favicons, domain
  includes/excludes, country hints, and auto-parameters.
- Firecrawl search can optionally scrape search results and has geo targeting,
  timeout, invalid URL filtering, and enterprise ZDR options.
- Exa search supports web retrieval, highlights, deeper search modes, structured
  output schema, and categories such as companies/people.
- Jina exposes `r.jina.ai` for URL-to-LLM-friendly text and `s.jina.ai` for
  search plus LLM-friendly output, with tiered rate limits.

Sources:

- Tavily Search docs: https://docs.tavily.com/documentation/api-reference/endpoint/search
- Firecrawl Search docs: https://docs.firecrawl.dev/api-reference/endpoint/search
- Exa Search docs: https://exa.ai/docs/reference/search-api-guide
- Jina Reader API docs: https://jina.ai/en-US/reader/

## OpenCompany Fit

OpenCompany has the right primitives already:

- `ToolRegistry` merges built-in providers, integration packages, and MCP.
- `ToolRegistry::DIRECT_TOOL_GROUPS` controls direct model-visible tools.
- `LuaSandboxService` exposes `app.*` only through a supplied `LuaBridge`.
- `LuaBridge` delegates `app.*` calls into integration-core's Lua bridge.
- `OpenCompanyLuaToolInvoker` routes Lua calls through `IntegrationRuntime`.
- `IntegrationRuntime` instantiates tools from `ToolRegistry` and normalizes
  return values.
- `IntegrationSetting` and `IntegrationSettingCredentialResolver` already give
  workspace-scoped encrypted credentials and account aliases.
- `AgentPermissionService`, `ApprovalWrappedTool`, and MCP permission checks
  already model the access/approval boundary.

The web layer should be app-owned, not a generic external integration package,
because it becomes a core runtime primitive that agents will use frequently.
Provider adapters can be small and swappable, but OpenCompany should own:

- request/response DTOs;
- provider selection and fallback;
- URL safety;
- domain policy;
- caching;
- usage/cost accounting;
- result normalization;
- permission and approval semantics;
- Lua docs and direct tool descriptions.

## Recommended Architecture

Create an app-owned domain under `app/Domain/Web` or `app/Services/Web`.
`app/Domain/Web` is cleaner if this will grow into crawl/research/accounting.

Suggested classes:

- `Contracts/WebSearchProvider`
- `Contracts/WebFetchProvider`
- `ValueObjects/WebSearchRequest`
- `ValueObjects/WebSearchResponse`
- `ValueObjects/WebSearchResult`
- `ValueObjects/WebFetchRequest`
- `ValueObjects/WebFetchResponse`
- `ValueObjects/ExtractedPage`
- `Providers/WebSearchProviderManager`
- `Providers/WebFetchProviderManager`
- `Providers/Fetch/DirectFetchProvider`
- `Providers/Fetch/JinaReaderFetchProvider`
- `Providers/Search/TavilySearchProvider`
- `Providers/Search/BraveSearchProvider`
- `Providers/Search/ExaSearchProvider`
- `Providers/Search/FirecrawlSearchProvider`
- `Extraction/HtmlPageExtractor`
- `Extraction/MarkdownPageExtractor`
- `Safety/WebRequestGuard`
- `Safety/WebAccessPolicy`
- `Cache/WebResultCache`
- `Usage/WebUsageRecorder`

Register through an `OpenCompanyWebServiceProvider` or `AppServiceProvider`
bindings:

```text
WebRequestGuard
HtmlPageExtractor
MarkdownPageExtractor
WebResultCache
WebSearchProviderManager
WebFetchProviderManager
```

Provider config should be adapter-based:

```php
return [
    'search' => [
        'default_provider' => env('WEB_SEARCH_PROVIDER', 'tavily'),
        'fallback_providers' => ['exa', 'brave'],
        'max_results' => 8,
    ],
    'fetch' => [
        'default_provider' => 'direct',
        'fallback_providers' => ['jina'],
        'max_bytes' => 10_485_760,
        'max_chars' => 12_000,
    ],
    'providers' => [
        'tavily' => ['api_key_env' => 'TAVILY_API_KEY'],
        'exa' => ['api_key_env' => 'EXA_API_KEY'],
        'brave' => ['api_key_env' => 'BRAVE_SEARCH_API_KEY'],
        'firecrawl' => ['api_key_env' => 'FIRECRAWL_API_KEY'],
        'jina' => ['api_key_env' => 'JINA_API_KEY'],
    ],
];
```

For production/workspace configuration, use encrypted `IntegrationSetting` rows
or a small dedicated `web_provider_settings` table. Reusing `IntegrationSetting`
is faster and consistent with current UI/account-alias behavior, but a
dedicated table may become cleaner if web usage needs per-workspace policy,
quotas, billing limits, and provider routing rules.

Recommended compromise:

- Keep static provider metadata in `config/web.php`.
- Store workspace credentials in `IntegrationSetting` using ids like
  `web.tavily`, `web.exa`, `web.brave`, `web.firecrawl`, `web.jina`.
- Store workspace policy/defaults in `AppSetting` or a future
  `web_provider_settings` table.

## Tool Surface

OpenCompany should expose web tools in two ways.

### Direct Agent Tools

Add a built-in `WebToolProvider` and add `web` to
`ToolRegistry::DIRECT_TOOL_GROUPS`.

Direct tools:

- `web_search`
- `web_fetch`

Rationale:

- LLMs have strong training priors around `web_search` and `web_fetch`.
- Search/fetch are core research primitives, not rare integration actions.
- Two read-only tools do not bloat the direct catalog much.
- Direct use avoids requiring Lua for simple source discovery.

### Lua API

The same tools should also appear through Lua:

```lua
local results = app.web.search({
    query = "Laravel 12 queue batching official docs",
    allowed_domains = { "laravel.com" },
    max_results = 5,
})

local page = app.web.fetch({
    url = results.results[1].url,
    mode = "outline",
})

local section = app.web.fetch({
    url = results.results[1].url,
    mode = "section",
    section_id = page.outline[1].id,
})
```

This keeps deterministic automations cheap while preserving the simple direct
tool path for chat-like research.

## Web Search Request Shape

Start close to KosmoKrator:

```php
new WebSearchRequest(
    query: string,
    provider: ?string,
    maxResults: int,
    allowedDomains: array,
    blockedDomains: array,
    searchDepth: 'basic'|'advanced',
    includeSnippets: bool,
    includeAnswer: bool,
)
```

OpenCompany additions:

- `workspaceId`
- `agentId`
- `userId`
- `country` / `locale` later
- `recencyDays` or `publishedAfter` later
- `policy` or `policyProfile`

## Web Fetch Request Shape

Start close to KosmoKrator:

```php
new WebFetchRequest(
    url: string,
    provider: ?string,
    mode: 'metadata'|'outline'|'main'|'full'|'section'|'match'|'chunk',
    format: 'markdown'|'text'|'html',
    maxChars: int,
    heading: ?string,
    sectionId: ?string,
    match: ?string,
    chunkToken: ?string,
    timeout: ?int,
    strategy: 'auto'|'direct_only'|'provider_only',
    includeMetadata: bool,
    includeOutline: bool,
)
```

Keep KosmoKrator's modes. They are important for context control:

- `metadata`: cheap title/description/canonical preview.
- `outline`: inspect headings before pulling content.
- `main`: extracted primary content.
- `full`: full extracted content.
- `section`: load one section by id/heading.
- `match`: return matching sections.
- `chunk`: continue a truncated result.

OpenCompany additions:

- `workspaceId`
- `agentId`
- `userId`
- `domainPolicy`
- `cacheTtl`
- `storeSnapshot` later

## Safety Policy

Use KosmoKrator's `WebRequestGuard` as the baseline:

- allow only `http` and `https`;
- reject embedded username/password credentials;
- reject `localhost` and `*.localhost`;
- resolve DNS and reject private/reserved IPv4/IPv6 results.

OpenCompany should add:

- workspace allowlist/blocklist;
- global denylist for metadata services and common internal hostnames;
- redirect re-checking after final URL resolution;
- max redirects;
- max response bytes;
- per-agent and per-workspace rate limits;
- user-agent policy;
- optional robots/legal policy notes for crawl mode;
- audit log containing URL, final URL, provider, status, bytes, and actor.

For direct fetch, redirect final URLs must be revalidated. KosmoKrator validates
the requested URL up front; OpenCompany should also validate every redirect hop
or at least the final URL before buffering content.

## Caching

KosmoKrator uses in-memory turn-based cache. The current OpenCompany worktree
has `WebResultCache`, a workspace-scoped transient cache-store service keyed by
capability, provider, normalized request payload, and workspace id. That is the
implemented baseline; a database-backed persistent cache remains an optional
next step if result reuse, diagnostics, or audit requirements outgrow the cache
store.

- key: provider + normalized request payload + workspace policy version;
- storage: database or cache store;
- TTL: short by default, e.g. 15 minutes to 24 hours depending on provider and
  workflow;
- include `fetched_at`, `provider`, `final_url`, `content_hash`, `bytes`,
  `status_code`, and `cache_hit`;
- allow `no_cache` per request for freshness-sensitive tasks.

Do not store raw HTML indefinitely by default. Store extracted markdown/content
only when useful, and expire aggressively unless a user/agent explicitly saves
it to documents/files/memory.

## Provider Rollout

Current-state note: the worktree already has initial code for direct fetch,
Tavily, Z.AI, Firecrawl, Exa, Brave, Parallel, Jina, SearXNG, Perplexity,
OpenAI-native, Anthropic-native, the direct `web_search`/`web_fetch` tools,
provider setup entries, config-only connection validation, safety, extraction,
cache, and Lua docs/runtime normalization. The phase list below is retained as
rollout structure for hardening, tests, diagnostics, and product polish.

Phase 1:

- `direct` fetch provider;
- `tavily` search provider;
- `web_search` and `web_fetch` built-in tools;
- Lua `app.web.search` and `app.web.fetch`;
- URL safety, workspace policy, cache, and focused tests.

Phase 2:

- `jina` reader/fetch provider;
- `brave` or `exa` search provider;
- provider setup UI for provider choice and credentials;
- usage accounting and per-workspace limits.

Phase 3:

- Firecrawl search/scrape adapter;
- provider-only/direct-only strategies in UI;
- `web_extract` for structured extraction;
- `web_crawl` with strict page/depth/domain caps.

Phase 4:

- browser-backed fallback to the Playwright browser runtime for JS-heavy pages;
- deep research provider mode;
- saved source snapshots and citations.

## Integration With Existing OpenCompany Runtime

Implementation path:

1. Add `config/web.php`.
2. Add `app/Domain/Web` contracts, values, providers, managers, extraction,
   guard, cache, and policy classes.
3. Add built-in tools under `app/Agents/Tools/Web`.
4. Add `app/Agents/Tools/Providers/WebToolProvider`.
5. Register `WebToolProvider` in `AppServiceProvider`.
6. Add `web` to `ToolRegistry::DIRECT_TOOL_GROUPS`.
7. Ensure `LuaApiDocGenerator` sees the new built-in group so Lua docs expose
   `app.web.search` and `app.web.fetch`.
8. Add Integration/Settings UI entries for provider credentials.
9. Add tests for provider manager fallback/cache, guard behavior, direct fetch,
   HTML extraction, direct tools, and Lua invocation.

Suggested test slice:

- `tests/Unit/Web/WebProviderManagerTest.php`
- `tests/Unit/Web/DirectFetchProviderTest.php`
- `tests/Unit/Web/HtmlPageExtractorTest.php`
- `tests/Feature/WebToolsTest.php`
- `tests/Feature/LuaWebToolsTest.php`

## Recommendation

Port the KosmoKrator web architecture conceptually, not file-for-file.

Keep:

- tiny provider contracts;
- request/response DTOs;
- provider managers with explicit/default/fallback selection;
- hard domain filtering after provider results;
- direct fetch as the default fetch adapter;
- HTML-to-markdown extraction with metadata/outline/sections;
- `metadata`, `outline`, `section`, `match`, and `chunk` fetch modes;
- short cache;
- direct model tools with structured metadata.

Change for OpenCompany:

- make all runtime calls workspace-scoped;
- read credentials from encrypted workspace settings;
- expose as both direct tools and Lua functions;
- add persistent cache/usage/accounting instead of turn-only cache;
- strengthen redirect safety;
- integrate with agent permissions and approvals;
- keep browser/Playwright fallback separate and later.

## Full Parity Implementation Plan

Target scope: OpenCompany should reach functional parity with the current
KosmoKrator non-browser web search/fetch system, while using OpenCompany's
workspace, credential, permission, and audit boundaries. This means one
OpenCompany-native implementation that covers both KosmoKrator web layers:

- the newer native `web_search` / `web_fetch` provider-manager path under
  `Kosmokrator\Web\Value`, `Kosmokrator\Web\Provider\*ProviderManager`, and
  `Kosmokrator\Tool\Web\WebSearchTool` / `WebFetchTool`;
- the older external-provider registry path under `Kosmokrator\Web\WebProviderRegistry`,
  `WebProviderInterface`, `WebFetchExternalTool`, and the `web:*` CLI commands,
  where those surfaces expose search/fetch behavior that the native path does
  not yet cover.

Do not copy the dual architecture into OpenCompany. Build one canonical
`app/Domain/Web` layer and map every Kosmo capability into it.

### Parity Matrix

Required search parity:

- direct model-visible `web_search` tool;
- Lua `app.web.search(...)` wrapper over the same runtime path;
- provider override;
- default provider and fallback provider chain;
- provider availability checks;
- request/result cache;
- `query`;
- `max_results`;
- `allowed_domains`;
- `blocked_domains`;
- `search_depth` as `basic` / `advanced`;
- `include_snippets`;
- `include_answer`;
- `mode` / provider depth hint as `auto` / `fast` / `deep` where external
  providers support it;
- `country`;
- `language`;
- `recency`;
- timeout seconds;
- output character limit;
- provider metadata passthrough;
- post-provider domain filtering so provider bugs cannot bypass workspace
  policy;
- answer/result formatting compatible with direct tool output;
- structured metadata compatible with Lua and future UI rendering.

Required fetch parity:

- direct model-visible `web_fetch` tool;
- Lua `app.web.fetch(...)` wrapper over the same runtime path;
- native direct static fetch provider;
- external/provider fetch through the same `web_fetch` tool using
  `strategy="provider_only"` or a provider override, rather than a second
  OpenCompany-only tool;
- provider override;
- default provider and fallback provider chain;
- `strategy` as `auto` / `direct_only` / `provider_only`;
- `url`;
- `mode` as `metadata` / `outline` / `main` / `full` / `section` / `match` /
  `chunk`;
- `format` as `markdown` / `text` / `html`;
- `max_chars`;
- `summarize` reserved flag;
- `prompt` reserved/focus field;
- `heading`;
- `section_id`;
- `match`;
- `start_after` and `end_before` reserved boundary fields;
- `chunk_token` continuation;
- timeout seconds;
- `include_metadata`;
- `include_outline`;
- final URL;
- HTTP status code;
- content type;
- title;
- metadata;
- outline;
- section map;
- raw HTML when requested and available;
- extraction method;
- truncation flag;
- next chunk token;
- provider metadata passthrough.

Required direct-fetch parity:

- browser-like request headers matching Kosmo's intent:
  - `User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)
    AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36`;
  - `Accept: text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.8,*/*;q=0.7`;
  - `Accept-Language: en-US,en;q=0.9`;
  - `Accept-Encoding: gzip, deflate`;
  - `Cache-Control: no-cache`;
  - `Pragma: no-cache`;
  - `Upgrade-Insecure-Requests: 1`;
- request timeout and inactivity timeout;
- max response bytes;
- gzip and deflate decoding;
- 4xx permanent failure behavior except retryable statuses like `408` and
  `429`;
- HTML, XHTML, and `text/*` handling;
- unsupported content-type failure;
- final redirect URL capture;
- redirect target revalidation before content is trusted.

Required extraction parity:

- DOM-based HTML cleanup;
- removal of noisy nodes such as scripts, styles, nav, footer, aside, forms,
  buttons, hidden/template content, and other non-content blocks;
- primary content-root selection using `main`, `article`, role/content hints,
  and fallback body extraction;
- title extraction;
- meta description/canonical/open graph style metadata extraction;
- HTML-to-markdown conversion;
- markdown-to-plain-text conversion for `format="text"`;
- outline generation from headings;
- stable section IDs;
- section lookup by exact ID, slug-normalized ID, or heading;
- match mode over extracted sections;
- chunk tokens encoded as opaque base64url JSON containing source and offset.

Required provider parity:

- `direct` fetch;
- `tavily` search/fetch/crawl-capable adapter, with search and extract used for
  this scope;
- `zai` search adapter using remote MCP first and chat-search fallback;
- `zai` reader/fetch adapter using the Z.AI coding PaaS reader endpoint;
- `firecrawl` search/fetch adapter;
- `exa` search/fetch adapter;
- `brave` search adapter;
- `parallel` search/fetch adapter;
- `jina` search/fetch adapter;
- `searxng` search adapter;
- `perplexity` search adapter;
- `openai_native` search adapter;
- `anthropic_native` search adapter.

`web_crawl` can be implemented after search/fetch parity, but the provider
registry must not be designed in a way that blocks later crawl support. Tavily
and Firecrawl crawl methods should remain first-class future adapters with page
limits, instructions, and strict domain caps.

### Z.AI Coding Plan Support

Z.AI needs two separate adapters because Kosmo uses two separate Z.AI surfaces.

`ZaiMcpSearchProvider` parity:

- provider id `zai`;
- configured by workspace credential `web.zai` or provider alias `zai`;
- default remote MCP URL
  `https://api.z.ai/api/mcp/web_search_prime/mcp`;
- call remote MCP tool `web_search_prime`;
- send `Authorization: Bearer <key>`;
- map `search_depth=advanced` to `content_size=high`, otherwise `medium`;
- pass allowed domains as `search_domain_filter`;
- normalize MCP payload fields `title`, `link`, `content`, `publish_date`, and
  `media`;
- filter blocked domains after the provider response;
- retry rate limits using short configured delays;
- detect common rate limit messages including `429`, `1302`, and MCP `-429`.

Z.AI coding-plan/chat-search fallback parity:

- default coding PaaS base URL `https://api.z.ai/api/coding/paas/v4`;
- call `/chat/completions`;
- model `glm-5.1` unless provider config overrides it;
- attach a chat tool of type `web_search`;
- tool payload:
  - `enable: true`;
  - `search_engine: search-prime`;
  - `search_result: true`;
  - `count` clamped to `1..10`;
  - `content_size` from search depth;
  - `search_domain_filter` when allowed domains are present;
- prompt the model to return strict JSON with `answer` and `results`;
- parse fenced or raw JSON;
- fallback parse pipe-separated lines for degraded responses;
- prefer chat search for short queries and when `include_answer=true`;
- preserve answer text when provided.

Z.AI reader/fetch parity:

- call `POST /reader` on the coding PaaS base URL;
- payload fields:
  - `url`;
  - `timeout`;
  - `return_format` as `markdown` or `text`;
  - `no_cache: false`;
  - `retain_images: true`;
  - `with_links_summary: true`;
- require URL safety guard before sending;
- parse `reader_result.content`, `title`, `url`, `description`, and `metadata`;
- normalize to the same `WebFetchResponse` shape as direct fetch;
- extract markdown through the markdown extractor so outline/sections/chunks
  still work.

This is the "Z.AI coding plan support" requirement: OpenCompany must not only
support a generic Z.AI key. It must support Z.AI's coding PaaS web-search tool
shape and reader endpoint because those are the surfaces Kosmo uses for coding
agent planning and source gathering.

### OpenCompany Class Plan

The current worktree already has the config, contracts, value objects,
exceptions, extraction helpers, URL safety guard, cache service, credential
resolver, streamable-HTTP MCP helper, direct fetch provider, and provider
adapter classes listed below. The current worktree also has the manager,
registry, formatter, direct-tool registration, integration-settings pieces,
usage recorder, and Artisan diagnostics. The current settings UI also exposes
default provider, fallback provider, external fetch, cache, domain, locale, and
recency controls through `WebAccessSettings`. Remaining hardening should track
future provider-specific API drift and new adapter capabilities rather than a
known missing parity item.

Create:

- `config/web.php`;
- `app/Domain/Web/Contracts/WebSearchProvider.php`;
- `app/Domain/Web/Contracts/WebFetchProvider.php`;
- `app/Domain/Web/Contracts/WebProvider.php` for capability/status metadata;
- `app/Domain/Web/Enums/WebCapability.php`;
- `app/Domain/Web/Exceptions/WebProviderException.php`;
- `app/Domain/Web/Exceptions/WebFetchPermanentException.php`;
- `app/Domain/Web/ValueObjects/WebSearchRequest.php`;
- `app/Domain/Web/ValueObjects/WebSearchResponse.php`;
- `app/Domain/Web/ValueObjects/WebSearchResult.php`;
- `app/Domain/Web/ValueObjects/WebFetchRequest.php`;
- `app/Domain/Web/ValueObjects/WebFetchResponse.php`;
- `app/Domain/Web/ValueObjects/ExtractedPage.php`;
- `app/Domain/Web/Managers/WebSearchProviderManager.php`;
- `app/Domain/Web/Managers/WebFetchProviderManager.php`;
- `app/Domain/Web/Registry/WebProviderRegistry.php`;
- `app/Domain/Web/Extraction/HtmlPageExtractor.php`;
- `app/Domain/Web/Extraction/MarkdownPageExtractor.php`;
- `app/Domain/Web/Safety/WebRequestGuard.php`;
- `app/Domain/Web/Safety/WebAccessPolicy.php`;
- `app/Domain/Web/Cache/WebResultCache.php`;
- `app/Domain/Web/Usage/WebUsageRecorder.php`;
- `app/Domain/Web/Support/WebToolFormatter.php`.

Providers:

- `app/Domain/Web/Providers/Fetch/DirectFetchProvider.php`;
- `app/Domain/Web/Providers/Fetch/ZaiReaderFetchProvider.php`;
- `app/Domain/Web/Providers/Search/ZaiMcpSearchProvider.php`;
- `app/Domain/Web/Providers/TavilyProvider.php`;
- `app/Domain/Web/Providers/FirecrawlProvider.php`;
- `app/Domain/Web/Providers/ExaProvider.php`;
- `app/Domain/Web/Providers/BraveProvider.php`;
- `app/Domain/Web/Providers/ParallelProvider.php`;
- `app/Domain/Web/Providers/JinaProvider.php`;
- `app/Domain/Web/Providers/SearxngProvider.php`;
- `app/Domain/Web/Providers/PerplexityProvider.php`;
- `app/Domain/Web/Providers/OpenAiNativeSearchProvider.php`;
- `app/Domain/Web/Providers/AnthropicNativeSearchProvider.php`.

Tool/runtime classes:

- `app/Agents/Tools/Web/WebSearchTool.php`;
- `app/Agents/Tools/Web/WebFetchTool.php`;
- `app/Agents/Tools/Providers/WebToolProvider.php`;
- register `web` in `ToolRegistry::DIRECT_TOOL_GROUPS`;
- expose `app.web.search` and `app.web.fetch` through the existing Lua bridge
  by using the same tool invocation path;
- update `resources/lua-docs` with request/response examples.

Do not add a separate `web_fetch_external` direct tool in OpenCompany. Fold
Kosmo's external fetch behavior into `web_fetch` through `provider` and
`strategy`. That keeps the public agent API smaller while preserving the
behavior.

### Workspace Configuration Plan

Use static provider catalog data in `config/web.php`:

- provider id;
- label;
- capabilities;
- default base URL;
- API key env fallback;
- credential integration id;
- default timeout;
- max output defaults;
- enabled-by-default flag;
- feature flags for search/fetch/native/crawl.

Use workspace-scoped encrypted `IntegrationSetting` credentials:

- `web.tavily`;
- `web.zai`;
- `web.firecrawl`;
- `web.exa`;
- `web.brave`;
- `web.parallel`;
- `web.jina`;
- `web.searxng`;
- `web.perplexity`;
- `web.openai_native`;
- `web.anthropic_native`.

Use a workspace policy row or app setting for:

- default search provider;
- search fallback providers;
- default fetch provider;
- fetch fallback providers;
- whether external provider fetch is allowed;
- domain allowlist;
- domain blocklist;
- max results;
- max fetch chars;
- max bytes;
- cache TTL;
- per-agent/per-workspace rate limits.

### Database And Audit Plan

Add a persistent cache table unless the existing cache store is clearly enough:

- `id`;
- `workspace_id`;
- `provider`;
- `capability`;
- `request_hash`;
- `policy_hash`;
- `url`;
- `final_url`;
- `query`;
- `status_code`;
- `content_type`;
- `title`;
- `metadata_json`;
- `outline_json`;
- `sections_json`;
- `content`;
- `content_hash`;
- `bytes`;
- `fetched_at`;
- `expires_at`.

Add an audit/usage table or emit into the existing agent event log:

- `workspace_id`;
- `agent_id`;
- `user_id`;
- `conversation_id` / `message_id` where available;
- tool name;
- provider;
- capability;
- URL/query;
- final URL;
- status;
- bytes in/out;
- cache hit;
- error class;
- duration milliseconds;
- created timestamp.

Secrets must never be written to request metadata, tool output, audit logs, or
agent-visible errors.

### Safety Acceptance Criteria

The guard is not done until tests prove all of these fail:

- `file://`, `ftp://`, and other non-http(s) schemes;
- URLs with username/password;
- missing host;
- overlong URLs;
- `localhost`;
- `*.localhost`;
- loopback IPv4 and IPv6;
- private IPv4 and IPv6;
- reserved IPv4 and IPv6;
- link-local metadata IPs such as `169.254.169.254`;
- common cloud metadata hostnames;
- DNS names resolving to blocked IP ranges;
- redirect from a public URL to a blocked URL.

The guard is done when normal public `http` and `https` URLs still pass and
provider-backed fetch adapters use the same guard before sending URLs to the
external provider.

### Implementation Sequence

Current worktree note: steps 1 through 12 now exist as app-owned code. The
sequence below is retained as the implementation audit trail and as guidance
for future adapter expansion.

1. Add `config/web.php`, domain contracts, value objects, exceptions, formatter,
   and provider registry.
2. Add safety guard and tests before any network provider code.
3. Add cache and audit abstractions with in-memory fakes for tests.
4. Add direct fetch with exact browser-like headers, response decoding, max
   bytes, permanent failure classification, final URL capture, and redirect
   revalidation.
5. Add HTML and markdown extractors with outline, sections, metadata, and
   markdown/text conversion.
6. Add fetch manager and full `web_fetch` mode handling: metadata, outline,
   main, full, section, match, chunk.
7. Add search manager and `web_search` handling: request normalization, fallback
   chain, cache, domain filtering, and structured output.
8. Add `tavily`, `exa`, `brave`, `firecrawl`, `parallel`, `jina`, `searxng`,
   `perplexity`, `openai_native`, and `anthropic_native` adapters.
9. Add Z.AI MCP search, Z.AI chat-search fallback, and Z.AI reader/fetch.
10. Add `WebToolProvider`, direct tools, Lua routing, Lua docs, and tool
    catalog visibility.
11. Add settings/admin UI for provider credentials, default provider choices,
    fallback providers, domain policy, and external-fetch enablement.
12. Add diagnostics UI or Artisan commands equivalent to Kosmo's
    `web:providers`, `web:configure`, `web:doctor`, `web:search`, and
    `web:fetch`. OpenCompany currently has all five Artisan commands plus the
    normal Integrations and Web Access settings UI.
13. Add documentation and examples for direct tool use and Lua use.
14. Run provider contract tests, feature tests, and a small local smoke test
    with fake HTTP clients. Live provider tests should be opt-in and skipped
    unless credentials are present.

### Test Plan

Minimum test files:

- `tests/Unit/Domain/Web/WebRequestGuardTest.php`;
- `tests/Unit/Domain/Web/DirectFetchProviderTest.php`;
- `tests/Unit/Domain/Web/HtmlPageExtractorTest.php`;
- `tests/Unit/Domain/Web/MarkdownPageExtractorTest.php`;
- `tests/Unit/Domain/Web/WebResultCacheTest.php`;
- `tests/Unit/Domain/Web/WebSearchProviderManagerTest.php`;
- `tests/Unit/Domain/Web/WebFetchProviderManagerTest.php`;
- `tests/Unit/Domain/Web/WebProviderRegistryTest.php`;
- `tests/Unit/Domain/Web/ZaiProvidersTest.php`;
- `tests/Unit/Agents/Tools/WebSearchToolTest.php`;
- `tests/Unit/Agents/Tools/WebFetchToolTest.php`;
- `tests/Feature/Agents/WebToolsRuntimeTest.php`;
- `tests/Feature/Agents/LuaWebToolsTest.php`;
- `tests/Feature/Admin/WebProviderSettingsTest.php`.

Coverage targets:

- every request field is normalized and tested;
- every fetch mode has success and failure tests;
- every provider adapter has mocked success, provider error, malformed payload,
  and unavailable credential tests;
- Z.AI has separate tests for MCP success, MCP empty response, MCP rate limit,
  chat-search fallback, JSON-fenced response parsing, line fallback parsing,
  and reader response normalization;
- direct fetch tests assert the exact browser-like headers;
- direct fetch tests assert gzip and deflate decoding;
- safety tests cover DNS and redirect edge cases;
- Lua tests assert `app.web.search` and `app.web.fetch` return structured data,
  not only rendered text;
- workspace tests assert one workspace cannot use another workspace's provider
  credentials, cache entries, or policy.

### Definition Of Done

The full implementation is done when:

- OpenCompany exposes `web_search` and `web_fetch` directly to agents;
- Lua exposes `app.web.search` and `app.web.fetch`;
- every Kosmo search/fetch request option listed above is supported or
  intentionally reserved with the same no-op semantics as Kosmo;
- all Kosmo search/fetch providers listed above exist as OpenCompany adapters;
- Z.AI MCP search, Z.AI coding PaaS chat-search fallback, and Z.AI reader fetch
  are implemented;
- direct fetch sends the browser-like headers and handles compression, limits,
  extraction, sections, and chunks;
- provider credentials are workspace-scoped and encrypted;
- URL safety is enforced before direct and provider-backed fetch;
- cache behavior is workspace-scoped;
- audit/usage behavior is added and workspace-scoped;
- settings/admin diagnostics make provider status understandable;
- tests cover the parity matrix with fake HTTP clients and no required live
  provider calls.

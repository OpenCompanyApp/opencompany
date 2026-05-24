# Web Search And Fetch Adapter Investigation

Date: 2026-05-24

Status: Investigation / implementation proposal. The external provider notes
were spot-checked on 2026-05-24 against the linked Tavily, Firecrawl, Exa, and
Jina docs. The OpenCompany-specific classes, config, tools, Lua functions,
settings UI, cache, and phases below are proposed until matching app code
exists.

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
- `Policy/WebAccessPolicy`
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

KosmoKrator uses in-memory turn-based cache. OpenCompany should use a
workspace-scoped persistent cache:

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

Phase 1:

- `direct` fetch provider;
- `tavily` search provider;
- `web_search` and `web_fetch` built-in tools;
- Lua `app.web.search` and `app.web.fetch`;
- URL safety, workspace policy, cache, and focused tests.

Phase 2:

- `jina` reader/fetch provider;
- `brave` or `exa` search provider;
- settings UI for provider choice and credentials;
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

The first useful build should be small: `direct` fetch, one search adapter
(`tavily`), `web_search`, `web_fetch`, Lua docs, safety guard, and tests. That
will unlock normal web research without dragging browser infrastructure into the
first implementation.

# OpenCompany Integrations

Monorepo for all [OpenCompany](https://github.com/OpenCompanyApp) integration packages. Each package exposes tools that AI agents can call — from rendering diagrams to querying APIs to managing tasks.

Integrations are independent Composer packages built on a shared core. They work in any PHP 8.2+ application: [OpenCompany](https://github.com/OpenCompanyApp) (web), [KosmoKrator](https://github.com/OpenCompanyApp/kosmokrator) (CLI), or your own consumer.

## Repository Structure

```
core/                       Shared contracts, credential abstraction, script bridge, registry
packages/
  celestial/                Astronomy: moon phases, sunrise/sunset, planet positions, eclipses
  clickup/                  ClickUp project management: tasks, lists, folders, time tracking
  coingecko/                CoinGecko cryptocurrency: prices, market data, trending, charts
  constant-contact/         Constant Contact email marketing: contacts, campaigns, lists
  etsy/                     Etsy e-commerce: listings, orders, inventory, seller account
  exchangerate/             Currency exchange rates: 340+ fiat, crypto, and metal conversions
  google/                   Google Calendar, Gmail, Drive, Sheets, Docs, Forms, Contacts, Tasks, Analytics, Search Console
  mermaid/                  Mermaid diagram rendering to PNG
  microsoft-powerbi/        Microsoft Power BI: reports, datasets, workspaces, user info
  plantuml/                 PlantUML diagram rendering to PNG
  plausible/                Plausible Analytics: stats, realtime visitors, goals
  recruitee/                Recruitee ATS: job offers, candidates, departments
  splunk/                   Splunk log analytics: search, indexes, saved searches
  statuspage/               Atlassian Statuspage: incidents, components, status management
  tapfiliate/               Tapfiliate affiliate marketing: affiliates, conversions, tracking
  ticktick/                 TickTick task management with time tracking
  trustmrr/                 TrustMRR verified startup revenue data
  typst/                    Typst document rendering to PDF
  vegalite/                 Vega-Lite chart rendering to PNG
  worldbank/                World Bank economic indicators for 200+ countries
```

## Architecture

```
┌─────────────────────────────────────────────────┐
│  Host Application (OpenCompany, KosmoKrator)     │
│                                                  │
│  ┌──────────┐   ┌───────────────────────────┐   │
│  │ QuickJS  │──▸│ ScriptBridge                  │   │
│  │ sandbox  │   │   functionMap → tool slugs    │   │
│  │ app.integrations.mermaid.render(...)         │   │
│  └──────────┘   └────────────┬──────────────┘   │
│                              │                   │
│  ┌───────────────────────────▼──────────────┐   │
│  │ ToolProviderRegistry                      │   │
│  │   ├─ mermaid    → MermaidToolProvider     │   │
│  │   ├─ plausible  → PlausibleToolProvider   │   │
│  │   ├─ clickup    → ClickUpToolProvider     │   │
│  │   └─ ...                                  │   │
│  └───────────────────────────┬──────────────┘   │
│                              │                   │
│  ┌───────────────────────────▼──────────────┐   │
│  │ ToolProvider.createTool(class, context)    │   │
│  │   → CredentialResolver for API keys       │   │
│  │   → AgentFileStorage for file output      │   │
│  │   → Tool.execute(args) → ToolResult       │   │
│  └──────────────────────────────────────────┘   │
└─────────────────────────────────────────────────┘
```

**Key concepts:**

- **Tool** — A single callable action (e.g. "render a Mermaid diagram", "list ClickUp tasks"). Implements `name()`, `description()`, `parameters()`, `execute()`.
- **ToolProvider** — Groups related tools under an app name. Declares metadata, handles tool instantiation with credentials, and optionally provides JavaScript documentation.
- **ToolProviderRegistry** — Singleton that collects all providers. The host queries it to discover available tools.
- **CredentialResolver** — Abstraction for API keys. The default reads from `config/ai-tools.php`; OpenCompany swaps this for encrypted database storage.
- **ScriptBridge** — Routes synchronous `app.integrations.{name}.{function}(...)` calls from a host sandbox to PHP tool classes.

### How It Works in OpenCompany

OpenCompany uses a **code-first agent architecture** — agents write and execute JavaScript programs to access all workspace functionality, including integrations. The full pipeline:

1. **System prompt** includes a namespace summary of all available JavaScript APIs (`app.chat.*`, `app.integrations.mermaid.*`, etc.)
2. **Agent calls `code_exec`** with JavaScript code like `app.integrations.plausible.query_stats({...})`
3. **QuickJS sandbox** applies host-defined memory, CPU, stack, output, and callback budgets and routes synchronous `app.*` calls to `ScriptBridge`
4. **ScriptBridge** maps the function path to a tool slug via `ScriptCatalogBuilder`-generated function maps
5. **`OpenCompanyScriptToolInvoker`** instantiates the tool via the `ToolProvider` and calls `execute()`
6. **Result** flows back through QuickJS to the agent, with effect-aware call logging for observability and safe retry decisions

Agents can also introspect available tools at runtime:
- `code_read_doc("integrations.plausible")` — Full API reference with parameter tables
- `code_search_docs("query stats")` — Search across all namespaces and supplementary docs
- `code_list_docs()` — List all available namespaces and static pages

**Credential management in OpenCompany** uses encrypted database storage instead of config files. The `IntegrationSettingCredentialResolver` reads from the `integration_settings` table (workspace-scoped, `encrypted:array` cast). Users configure credentials through the Integrations UI — tool packages are unaware of the storage backend.

## Available Integrations

| Package | Tools | Triggers | Credentials | Category | Description |
|---------|------:|---------:|-------------|----------|-------------|
| [celestial](packages/celestial/) | 9 | — | None | Data | Moon phases, sunrise/sunset, planet positions, eclipses, zodiac |
| [clickup](packages/clickup/) | 34 | 4 | API token | Productivity | Tasks, lists, folders, time tracking, docs, chat |
| [coingecko](packages/coingecko/) | 8 | — | None | Data | Crypto prices, market data, trending coins, historical charts |
| [constant-contact](packages/constant-contact/) | 6 | — | Access token | Email | Contacts, campaigns, lists |
| [etsy](packages/etsy/) | 6 | — | API token | E-commerce | Shop listings, orders, inventory, seller profile |
| [exchangerate](packages/exchangerate/) | 5 | — | None | Data | 340+ currency conversions (fiat, crypto, metals) |
| [google](packages/google/) | 117 | — | OAuth | Productivity | Calendar, Gmail, Drive, Sheets, Docs, Forms, Contacts, Tasks, Analytics, Search Console |
| [mermaid](packages/mermaid/) | 1 | — | None | Rendering | Flowcharts, sequences, Gantt, class diagrams → PNG |
| [plantuml](packages/plantuml/) | 1 | — | None | Rendering | UML class, sequence, activity, component, state → PNG |
| [microsoft-powerbi](packages/microsoft-powerbi/) | 6 | — | Access token | Analytics | Reports, datasets, workspaces, user info |
| [plausible](packages/plausible/) | 8 | — | None | Analytics | Stats, realtime visitors, site and goal management |
| [recruitee](packages/recruitee/) | 6 | — | Access token | HR | Job offers, candidates, departments, user info |
| [splunk](packages/splunk/) | 6 | — | Bearer token | Monitoring | Log search, indexes, saved searches, user context |
| [statuspage](packages/statuspage/) | 5 | — | API key + Page ID | Monitoring | Incidents, components, status management |
| [tapfiliate](packages/tapfiliate/) | 5 | — | API key | Marketing | Affiliates, conversions, referral tracking |
| [ticktick](packages/ticktick/) | 9 | — | OAuth | Productivity | Projects, tasks, time tracking (TickTick and Dida365) |
| [trustmrr](packages/trustmrr/) | 2 | — | API key | Data | Verified startup revenue, MRR, growth, acquisitions |
| [typst](packages/typst/) | 1 | — | None | Rendering | Reports, invoices, proposals → PDF |
| [vegalite](packages/vegalite/) | 1 | — | None | Rendering | Bar, line, scatter, heatmap, boxplot charts → PNG |
| [worldbank](packages/worldbank/) | 6 | — | None | Data | GDP, inflation, population for 200+ countries |

## Installation

Each package directory is an independent Composer package. In your consuming application:

```json
{
    "repositories": [
        {"type": "path", "url": "../integrations/core"},
        {"type": "path", "url": "../integrations/packages/*"}
    ],
    "require": {
        "opencompanyapp/integration-core": "@dev",
        "opencompanyapp/integration-mermaid": "@dev",
        "opencompanyapp/integration-plausible": "@dev"
    }
}
```

Laravel auto-discovers service providers. For non-Laravel apps, use the contracts and registry directly.

## Catalog and SEO Metadata

`php build-catalog.php` writes `integrations-catalog.json`, the machine-readable catalog used by KosmoKrator docs, headless CLI discovery, JavaScript API docs, and SEO pages. Every integration stays in the catalog, including integrations that are not fully supported by a local CLI runtime yet, so hosts can document future proxy support without hiding available packages.

The catalog includes:

- `auth`, `auth_strategy`, and `auth_summary`
- `host_availability` for CLI, web, proxy, and MCP gateway surfaces
- `runtime_requirements` for binaries or services such as `mmdc`, Java, Typst, or Node.js
- `compatibility`, `compatibility_summary`, `cli_setup_supported`, and `cli_runtime_supported`
- `setup` with generated headless configure, doctor, status, and MCP gateway commands
- `seo` with title, meta description, keyword phrases, setup summaries, and tool counts

Most packages do not need explicit metadata. The catalog builder derives sensible defaults from `credentialFields()`, tool read/write types, package metadata, and JavaScript docs. For example, a ClickUp package with `api_token` and `workspace_id` credentials gets generated setup instructions like:

```console
kosmokrator integrations:configure clickup --set api_token="$CLICKUP_API_TOKEN" --set workspace_id="$CLICKUP_WORKSPACE_ID" --enable --read allow --write ask --json
kosmokrator integrations:doctor clickup --json
kosmokrator mcp:serve --integration=clickup --write=deny
```

When inference is not specific enough, implement `HasIntegrationCapabilities` on the provider or add the same keys to `appMeta()` / `integrationMeta()`:

```php
use OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities;

class AcmeToolProvider implements ToolProvider, HasIntegrationCapabilities
{
    public function integrationCapabilities(): array
    {
        return [
            'auth_strategy' => 'oauth2_authorization_code',
            'cli_setup_supported' => false,
            'cli_runtime_supported' => true,
            'host_availability' => [
                'cli' => true,
                'web' => true,
                'proxy' => true,
                'mcp_gateway' => true,
            ],
            'runtime_requirements' => [
                ['name' => 'acme', 'type' => 'binary', 'required' => true],
            ],
            'seo' => [
                'cli_setup_summary' => 'Acme can run from KosmoKrator after credentials are connected through OAuth.',
                'mcp_setup_summary' => 'Expose Acme tools to MCP clients through the KosmoKrator MCP gateway.',
            ],
        ];
    }
}
```

Use `cli_setup_supported: false` when credentials cannot be configured fully headlessly, for example browser redirect OAuth without device-code or manual-token support. Use `cli_runtime_supported: false` only when the tool cannot currently run locally. The docs site should still render those integrations and explain the limitation.

### System Dependencies

Some rendering integrations need external tools:

| Package | Dependency | Install |
|---------|-----------|---------|
| mermaid | `mmdc` (Mermaid CLI) | `npm install -g @mermaid-js/mermaid-cli` |
| plantuml | Java + `plantuml.jar` | Bundled in `plantuml/bin/`, needs `java` on PATH |
| typst | `typst` CLI | `brew install typst` or [typst.app](https://github.com/typst/typst) |
| vegalite | Node.js | `node` on PATH; render script bundled in `vegalite/bin/` |

---

# Developer Guide

## Building a New Integration

This walkthrough creates a complete integration from scratch. We'll build a "Weather" integration as an example.

### 1. Create the Package Directory

Create a new directory under `packages/`:

```
packages/weather/
├── composer.json
├── src/
│   ├── WeatherServiceProvider.php
│   ├── WeatherService.php
│   ├── WeatherToolProvider.php
│   └── Tools/
│       └── GetWeather.php
└── script-docs/              (optional)
    └── weather.md
```

### 2. Define `composer.json`

```json
{
    "name": "opencompanyapp/integration-weather",
    "description": "Weather data and forecasts integration for OpenCompany.",
    "license": "MIT",
    "authors": [
        {
            "name": "OpenCompany",
            "homepage": "https://github.com/OpenCompanyApp"
        }
    ],
    "keywords": ["tools", "weather", "forecasts", "opencompany"],
    "require": {
        "php": "^8.2",
        "opencompanyapp/integration-core": "^2.0 || @dev"
    },
    "autoload": {
        "psr-4": {
            "OpenCompany\\Integrations\\Weather\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "OpenCompany\\Integrations\\Weather\\WeatherServiceProvider"
            ]
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

**Conventions:**
- Package name: `opencompanyapp/integration-{name}`
- Namespace: `OpenCompany\Integrations\{Name}\`
- If replacing an older standalone package, add a `"replace"` key: `"opencompanyapp/ai-tool-weather": "self.version"`
- Only add `illuminate/support` to `require` if you use facades like `Storage`, `Http`, `Log` directly (most API integrations don't need it)

### 3. Create the Service Class

The service class encapsulates all API communication. Tools call the service — they never make HTTP requests directly.

```php
<?php

namespace OpenCompany\Integrations\Weather;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private const BASE_URL = 'https://api.weather.example/v1';

    public function __construct(
        private string $apiKey = '',
    ) {}

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    public function getCurrent(string $location): array
    {
        return $this->request('GET', '/current', [
            'location' => $location,
        ]);
    }

    public function getForecast(string $location, int $days = 3): array
    {
        return $this->request('GET', '/forecast', [
            'location' => $location,
            'days' => $days,
        ]);
    }

    private function request(string $method, string $path, array $params = []): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Weather API key is not configured.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
            ])->timeout(15)->get(self::BASE_URL . $path, $params);

            if (! $response->successful()) {
                $error = $response->json('error') ?? $response->body();
                Log::error("Weather API error: {$method} {$path}", [
                    'status' => $response->status(),
                    'error' => $error,
                ]);
                throw new \RuntimeException(
                    'Weather API error (' . $response->status() . '): ' . $error
                );
            }

            return $response->json() ?? [];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \RuntimeException("Failed to connect to Weather API: {$e->getMessage()}");
        }
    }
}
```

### 4. Create the Service Provider

The service provider wires everything into the Laravel container and registers with the `ToolProviderRegistry`.

```php
<?php

namespace OpenCompany\Integrations\Weather;

use Illuminate\Support\ServiceProvider;
use OpenCompany\IntegrationCore\Contracts\CredentialResolver;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class WeatherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WeatherService::class, function ($app) {
            $creds = $app->make(CredentialResolver::class);

            return new WeatherService(
                apiKey: $creds->get('weather', 'api_key', ''),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->bound(ToolProviderRegistry::class)) {
            $this->app->make(ToolProviderRegistry::class)
                ->register(new WeatherToolProvider());
        }
    }
}
```

**Pattern notes:**
- Always register the service as a singleton — tools may be called multiple times in one request
- Always check `$this->app->bound(ToolProviderRegistry::class)` before registering — the core package may not be installed
- Use `CredentialResolver` to get API keys, never read config directly

### 5. Create the Tool Provider

The tool provider declares what tools are available and how to instantiate them.

```php
<?php

namespace OpenCompany\Integrations\Weather;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;
use OpenCompany\Integrations\Weather\Tools\GetWeather;
use OpenCompany\Integrations\Weather\Tools\GetForecast;

class WeatherToolProvider implements ToolProvider
{
    public function appName(): string
    {
        return 'weather';
    }

    public function appMeta(): array
    {
        return [
            'label'       => 'weather, forecasts, temperature',
            'description' => 'Weather data and forecasts',
            'icon'        => 'ph:cloud-sun',
            'logo'        => 'ph:cloud-sun',
        ];
    }

    public function tools(): array
    {
        return [
            'get_weather' => [
                'class'       => GetWeather::class,
                'type'        => 'read',
                'name'        => 'Get Weather',
                'description' => 'Current weather for any location.',
                'icon'        => 'ph:cloud-sun',
            ],
            'get_forecast' => [
                'class'       => GetForecast::class,
                'type'        => 'read',
                'name'        => 'Get Forecast',
                'description' => 'Multi-day weather forecast.',
                'icon'        => 'ph:calendar',
            ],
        ];
    }

    public function isIntegration(): bool
    {
        return true;
    }

    public function createTool(string $class, array $context = []): Tool
    {
        return new $class(app(WeatherService::class));
    }

    public function scriptDocsPath(): ?string
    {
        return __DIR__ . '/../script-docs/weather.md';
    }

    public function credentialFields(): array
    {
        return [
            [
                'key'         => 'api_key',
                'type'        => 'secret',
                'label'       => 'API Key',
                'required'    => true,
                'placeholder' => 'wth_...',
            ],
        ];
    }
}
```

**`tools()` array keys:**
- `class` — Fully-qualified class name of the Tool implementation
- `type` — `'read'` (fetches data) or `'write'` (creates/modifies/deletes)
- `name` — Human-readable display name
- `description` — Short description for listings and UI cards
- `icon` — [Iconify](https://icon-sets.iconify.design/) identifier (we use the `ph:` Phosphor set)

**`createTool()` context:**
- The `$context` array is injected by the host application at runtime
- In OpenCompany: `['agent' => User, 'timezone' => 'Europe/Amsterdam']`
- In KosmoKrator: `['account' => 'default']`
- Use it to pass runtime dependencies without coupling to specific models

### 6. Create Tool Classes

Each tool is a single callable action.

```php
<?php

namespace OpenCompany\Integrations\Weather\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Weather\WeatherService;

class GetWeather implements Tool
{
    public function __construct(
        private WeatherService $service,
    ) {}

    public function name(): string
    {
        return 'get_weather';
    }

    public function description(): string
    {
        return 'Get current weather conditions for any location. Returns temperature, humidity, wind speed, and conditions.';
    }

    public function parameters(): array
    {
        return [
            'location' => [
                'type'        => 'string',
                'required'    => true,
                'description' => 'City name, address, or coordinates (e.g. "Amsterdam", "51.5,-0.1").',
            ],
            'units' => [
                'type'        => 'string',
                'enum'        => ['metric', 'imperial'],
                'description' => 'Unit system (default: metric).',
            ],
        ];
    }

    public function execute(array $args): ToolResult
    {
        $location = $args['location'] ?? '';
        if (empty($location)) {
            return ToolResult::error('Location is required.');
        }

        try {
            $data = $this->service->getCurrent($location);

            return ToolResult::success($data);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }
}
```

**Parameter types:** `string`, `integer`, `number`, `boolean`, `array`, `object`

**Optional parameter keys:**
- `required` — `true` if the parameter must be provided (default `false`)
- `description` — Shown in generated JavaScript docs and tool catalogs
- `enum` — Array of allowed string values
- `items` — Element type for arrays, e.g. `['type' => 'string']`
- `properties` — Sub-property definitions for objects
- `default` — Default value if not provided

**ToolResult patterns:**
```php
// Success with data (array or string)
return ToolResult::success(['temperature' => 22, 'unit' => 'C']);
return ToolResult::success('The current temperature is 22C.');

// Success with metadata (files created, timing info, etc.)
return ToolResult::success($data, ['files' => [$fileInfo]]);

// Error
return ToolResult::error('Location not found.');
```

---

## Integration Types

The codebase has four distinct integration patterns. Pick the one that matches your use case.

### Type A: Public API (No Credentials)

For APIs that don't require authentication: exchangerate, worldbank, coingecko, celestial.

```php
// ToolProvider
public function credentialFields(): array
{
    return []; // No credentials needed
}

// ServiceProvider — no credential resolver needed
public function register(): void
{
    $this->app->singleton(MyService::class);
}
```

### Type B: API Key Authentication

For services that need an API key: plausible, trustmrr.

```php
// ServiceProvider — inject credentials
$this->app->singleton(MyService::class, function ($app) {
    $creds = $app->make(CredentialResolver::class);

    return new MyService(
        apiKey: $creds->get('myservice', 'api_key', ''),
        baseUrl: $creds->get('myservice', 'url', 'https://api.example.com'),
    );
});

// ToolProvider
public function credentialFields(): array
{
    return [
        ['key' => 'api_key', 'type' => 'secret', 'label' => 'API Key', 'required' => true],
        ['key' => 'url', 'type' => 'url', 'label' => 'Base URL', 'default' => 'https://api.example.com'],
    ];
}
```

### Type C: OAuth Authentication

For services requiring OAuth flows: clickup, ticktick, google.

These integrations register OAuth routes in their service provider and include a controller:

```php
// ServiceProvider boot()
Route::prefix('api/integrations/myservice/oauth')->group(function () {
    Route::get('authorize', [MyOAuthController::class, 'authorize']);
    Route::get('callback', [MyOAuthController::class, 'callback']);
});

// ToolProvider credentialFields
public function credentialFields(): array
{
    return [
        ['key' => 'client_id', 'type' => 'string', 'label' => 'Client ID', 'required' => true],
        ['key' => 'client_secret', 'type' => 'secret', 'label' => 'Client Secret', 'required' => true],
        ['key' => 'access_token', 'type' => 'oauth', 'label' => 'Connect Account'],
    ];
}
```

### Type D: Rendering / File Output

For tools that produce files (images, PDFs): mermaid, plantuml, typst, vegalite.

These use the `AgentFileStorage` contract to save output files:

```php
// ToolProvider — inject file storage
public function createTool(string $class, array $context = []): Tool
{
    $fileStorage = app()->bound(AgentFileStorage::class)
        ? app(AgentFileStorage::class)
        : null;

    return new $class(
        app(MyRenderService::class),
        $fileStorage,
        $context['agent'] ?? null,
    );
}

// Tool — use file storage if available, fall back to public disk
public function execute(array $args): ToolResult
{
    $bytes = $this->service->renderToBytes($input);

    if ($this->fileStorage && $this->agent) {
        $result = $this->fileStorage->saveFile(
            $this->agent, 'output.png', $bytes, 'image/png', 'myrenderer'
        );
        return ToolResult::success("![Title]({$result['url']})");
    }

    $url = $this->service->render($input); // saves to public disk
    return ToolResult::success("![Title]({$url})");
}
```

---

## Multi-Account Support

Integrations and MCP servers support multiple credential sets per workspace. Users can connect several accounts for the same service (e.g., "work" and "personal" ClickUp workspaces, two GitHub MCP servers) and agents can target any of them.

### How It Works

**Single account** (default): Flat namespace, backward compatible.
```js
app.integrations.clickup.create_task({ list_id: "123", name: "Ship it" });
```

**Portable scripts**: Use `.default` to always target the user's default account — works regardless of how many accounts exist. This is the recommended pattern for shareable scripts and automations.
```js
app.integrations.clickup.default.create_task({ list_id: "123", name: "Ship it" });
app.mcp.github.default.search_repos({ query: "bug" });
```

**Multiple accounts**: Per-account sub-namespaces appear alongside the flat and default namespaces.
```js
// Uses the default account
app.integrations.clickup.create_task({ list_id: "123", name: "Ship it" });
app.integrations.clickup.default.create_task({ list_id: "123", name: "Ship it" });

// Explicit account targeting
app.integrations.clickup.work.create_task({ list_id: "123", name: "Ship it" });
app.integrations.clickup.personal.create_task({ list_id: "456", name: "Buy groceries" });

// MCP servers work the same way
app.mcp.github.work.search_repos({ query: "internal" });
app.mcp.github.personal.search_repos({ query: "side-project" });
```

Agents discover available accounts via `code_read_doc("integrations.clickup")` or `code_read_doc("mcp.github")` — each account appears as a separate sub-namespace with the same functions.

### Implementation in Tool Providers

The `$context['account']` parameter is passed through to `createTool()`. When set, resolve credentials for that specific account:

```php
public function createTool(string $class, array $context = []): Tool
{
    $account = $context['account'] ?? null;

    if ($account !== null) {
        $creds = app(CredentialResolver::class);
        $service = new MyService(
            apiKey: $creds->get('myservice', 'api_key', '', $account),
        );
        return new $class($service);
    }

    // Default: use the container singleton (single-account path)
    return new $class(app(MyService::class));
}
```

### Database Schema

Both `integration_settings` and `mcp_servers` use `account_alias` to differentiate accounts:

| Column | Type | Description |
|--------|------|-------------|
| `account_alias` | VARCHAR(32) | `''` = default account, `'work'` / `'personal'` = named accounts |
| `is_default` | BOOLEAN | Which named account the flat namespace resolves to (integration_settings only) |

Unique constraints: `(workspace_id, integration_id, account_alias)` and `(workspace_id, slug, account_alias)`.

MCP servers sharing the same slug but different account aliases are grouped into a single provider. The default account's server provides the canonical tool definitions.

### API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/integrations/{id}/accounts` | List all accounts |
| POST | `/api/integrations/{id}/accounts` | Create a new account (requires `alias` + `config`) |
| PUT | `/api/integrations/{id}/accounts/{alias}` | Update account config |
| DELETE | `/api/integrations/{id}/accounts/{alias}` | Remove an account |
| POST | `/api/integrations/{id}/accounts/{alias}/default` | Set as default |

---

## Triggers

Triggers are event sources — they receive events from external services (via webhook) or discover new events (via polling). While tools are **pull** (agent calls a function), triggers are **push** (external service sends data to us).

The integration repo defines triggers declaratively; the host application provides infrastructure (HTTP endpoints, job scheduling, state persistence).

### Trigger Types

| Type | How It Works | Example |
|------|-------------|---------|
| **Webhook** | External service POSTs events to a host-generated URL | ClickUp fires `taskCreated` to your endpoint |
| **Polling** | Host periodically calls `poll()` to check for new data | Check an API every 5 min for changes |

### Adding Triggers to an Integration

Implement `HasTriggers` alongside your existing `ToolProvider`:

```php
use OpenCompany\IntegrationCore\Contracts\HasTriggers;
use OpenCompany\IntegrationCore\Contracts\Trigger;

class ClickUpToolProvider implements ToolProvider, HasTriggers
{
    public function triggers(): array
    {
        return [
            'clickup_task_created' => [
                'class' => ClickUpTaskCreatedTrigger::class,
                'name' => 'Task Created',
                'description' => 'Triggered when a new task is created.',
                'icon' => 'ph:plus-circle',
            ],
        ];
    }

    public function createTrigger(string $class, array $context = []): Trigger
    {
        return new $class($this->resolveService($context));
    }
}
```

### Building a Webhook Trigger

```php
use OpenCompany\IntegrationCore\Contracts\Trigger;
use OpenCompany\IntegrationCore\Contracts\TriggerContext;
use OpenCompany\IntegrationCore\Support\TriggerResult;
use OpenCompany\IntegrationCore\Support\TriggerType;

class ClickUpTaskCreatedTrigger extends Trigger
{
    public function __construct(protected ClickUpService $service) {}

    public function name(): string { return 'clickup_task_created'; }
    public function description(): string { return 'Triggered when a task is created.'; }
    public function type(): TriggerType { return TriggerType::Webhook; }

    public function parameters(): array
    {
        return [
            'space_id' => ['type' => 'string', 'description' => 'Scope to a space (optional).'],
        ];
    }

    public function onEnable(TriggerContext $ctx): void
    {
        $response = $this->service->createWebhook($this->service->getWorkspaceId(), [
            'endpoint' => $ctx->webhookUrl(),
            'events' => ['taskCreated'],
        ]);
        $ctx->store()->put('webhook_id', $response['webhook']['id']);
        $ctx->store()->put('webhook_secret', $response['webhook']['secret']);
    }

    public function onDisable(TriggerContext $ctx): void
    {
        $this->service->deleteWebhook($ctx->store()->get('webhook_id'));
        $ctx->store()->forget('webhook_id');
        $ctx->store()->forget('webhook_secret');
    }

    public function verify(TriggerContext $ctx, array $headers, string $rawBody): bool
    {
        $secret = $ctx->store()->get('webhook_secret', '');
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $headers['x-signature'] ?? '');
    }

    public function process(TriggerContext $ctx, array $payload): TriggerResult
    {
        return TriggerResult::event([
            'event' => 'taskCreated',
            'task' => $this->service->getTask($payload['task_id']),
        ]);
    }
}
```

### Building a Polling Trigger

```php
class ExchangeRateChangedTrigger extends Trigger
{
    public function type(): TriggerType { return TriggerType::Polling; }

    public function onEnable(TriggerContext $ctx): void
    {
        // Store baseline for comparison
        $ctx->store()->put('last_rates', $this->service->getRates());
    }

    public function onDisable(TriggerContext $ctx): void
    {
        $ctx->store()->forget('last_rates');
    }

    public function poll(TriggerContext $ctx): TriggerResult
    {
        $current = $this->service->getRates();
        $previous = $ctx->store()->get('last_rates', []);
        $ctx->store()->put('last_rates', $current);

        $changed = array_filter($current, fn ($rate, $key) =>
            ($previous[$key] ?? null) !== $rate, ARRAY_FILTER_USE_BOTH);

        return $changed ? TriggerResult::event($changed) : TriggerResult::empty();
    }
}
```

### How the Host Uses Triggers

The host discovers triggers through the same `ToolProviderRegistry`:

```php
// Discovery
foreach ($registry->all() as $provider) {
    if ($provider instanceof HasTriggers) {
        foreach ($provider->triggers() as $slug => $meta) {
            // Register webhook routes, build trigger catalog for UI
        }
    }
}

// Enable a trigger
$trigger = $provider->createTrigger($meta['class'], ['account' => $account]);
$trigger->onEnable($context);  // Registers webhook at external service

// Incoming webhook request
$handshake = $trigger->handshake($payload);
if ($handshake !== null) {
    return response()->json($handshake);  // Challenge response
}
if ($trigger->verify($context, $headers, $rawBody)) {
    $result = $trigger->process($context, json_decode($rawBody, true));
    foreach ($result->events as $event) {
        // Dispatch to automations, notify agents, etc.
    }
}

// Disable
$trigger->onDisable($context);  // Deregisters webhook
```

### Trigger Contracts

| Contract | Type | Purpose |
|----------|------|---------|
| `Trigger` | Abstract class | Base for all triggers — lifecycle, processing, verification |
| `TriggerContext` | Interface | Host-provided: webhook URL, store, config |
| `TriggerStore` | Interface | Host-provided: key-value persistence per subscription |
| `TriggerResult` | Value object | Wraps zero or more events from process/poll |
| `TriggerType` | Enum | `Webhook` or `Polling` |
| `HasTriggers` | Interface | Optional interface for trigger-capable providers |

---

## Making an Integration Configurable

To add a settings UI in OpenCompany, implement `ConfigurableIntegration` alongside `ToolProvider`:

```php
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class WeatherToolProvider implements ToolProvider, ConfigurableIntegration
{
    // ... ToolProvider methods ...

    public function integrationMeta(): array
    {
        return [
            'name'        => 'Weather',
            'description' => 'Weather data and forecasts for any location',
            'icon'        => 'ph:cloud-sun',
            'logo'        => 'ph:cloud-sun',
            'category'    => 'data',          // data, productivity, analytics, rendering
            'badge'       => 'New',           // optional badge text
            'docs_url'    => 'https://...',   // optional external docs link
        ];
    }

    public function configSchema(): array
    {
        return [
            [
                'key'         => 'api_key',
                'type'        => 'secret',
                'label'       => 'API Key',
                'placeholder' => 'wth_...',
                'hint'        => 'Get your key at <a href="https://weather.example/keys" target="_blank">weather.example</a>.',
                'required'    => true,
            ],
            [
                'key'     => 'units',
                'type'    => 'select',
                'label'   => 'Default Units',
                'options' => ['metric' => 'Metric (C, km/h)', 'imperial' => 'Imperial (F, mph)'],
                'default' => 'metric',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        try {
            // Make a lightweight API call to verify credentials
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$config['api_key']}",
            ])->timeout(10)->get('https://api.weather.example/v1/ping');

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Connected to Weather API.'];
            }

            return ['success' => false, 'error' => 'Invalid API key.'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function validationRules(): array
    {
        return [
            'api_key' => 'nullable|string',
            'units' => 'nullable|in:metric,imperial',
        ];
    }
}
```

**Config field types:**
- `secret` — Masked input, stored encrypted
- `text` / `string` — Plain text input
- `url` — URL input with format validation
- `select` — Dropdown, requires `options` array
- `string_list` — Dynamic list of strings (e.g. site IDs)
- `oauth_connect` — OAuth connection button, requires `authorize_url` and `redirect_uri`

### Auth and Host Capabilities

Credential field shape is not enough to decide whether an integration can be
configured in OpenCompany, KosmoKrator, or both. For example, an OAuth access
token can be manually pasted in a CLI, while an OAuth redirect flow needs a web
callback during setup but may still run in CLI after tokens are stored.

The catalog builder infers capability metadata for every integration:

- `auth.strategy` — `none`, `api_key`, `api_token`, `bearer_token`,
  `oauth2_authorization_code`, `oauth2_manual_token`,
  `oauth2_client_credentials`, `basic`, or `custom`
- `auth.setup_flows` — `none`, `manual_secret`, `manual_token`,
  `web_redirect`, `local_redirect`, `device_code`, `service_account`,
  `client_credentials`, or `cli_only`
- `host_availability.web` — setup/runtime support in OpenCompany-style web hosts
- `host_availability.cli` — setup/runtime support in KosmoKrator-style CLI hosts
- `runtime_requirements` — local binaries or services required at runtime

If inference is not precise enough, implement
`OpenCompany\IntegrationCore\Contracts\HasIntegrationCapabilities` on the
provider and return explicit metadata:

```php
public function integrationCapabilities(): array
{
    return [
        'auth' => [
            'strategy' => 'oauth2_authorization_code',
            'setup_flows' => ['web_redirect'],
            'requires_browser_for_setup' => true,
            'refreshable' => true,
        ],
        'host_availability' => [
            'web' => ['setup_supported' => true, 'runtime_supported' => true, 'setup_mode' => 'web_redirect'],
            'cli' => ['setup_supported' => false, 'runtime_supported' => true, 'setup_mode' => 'unsupported'],
        ],
    ];
}
```

Use `local_redirect` or `device_code` when an OAuth integration can be
configured from a CLI host. Google OAuth is the main current example: web hosts
use the registered redirect callback, while CLI hosts can use a desktop
loopback redirect and, for supported scopes, device-code setup. Keep purely
browser-callback OAuth integrations as `web_redirect` with CLI setup disabled;
their tools may still run in CLI once the host already has stored tokens.

**Conditional fields** — Show a field only when another field has a specific value:
```php
[
    'key' => 'workspace_id',
    'type' => 'text',
    'label' => 'Workspace ID',
    'visible_when' => ['field' => 'mode', 'value' => 'workspace'],
]
```

---

## JavaScript Documentation

Agents discover tools through auto-generated JavaScript API docs. The `ScriptDocRenderer` and `ScriptCatalogBuilder` in core handle this automatically based on your `parameters()` and `description()` definitions.

For complex integrations, add a `script-docs/{name}.md` file with supplementary documentation — workflows, examples, and gotchas that aren't captured by the parameter reference.

### How JavaScript Routing Works

The `ScriptCatalogBuilder` transforms your tool definitions into a JavaScript namespace tree:

```
app.integrations.weather.get({ location: "Amsterdam" })
│   │              │      │
│   │              │      └─ Function name (derived from tool name, minus app name)
│   │              └─ App name (from ToolProvider::appName())
│   └─ "integrations." prefix (added when isIntegration() returns true)
└─ Root namespace
```

**Function name derivation** — `ScriptCatalogBuilder::deriveFunctionName()` converts the tool's `name` field (not the slug) to a JavaScript-friendly function name:
1. Converts to `snake_case`
2. Removes stop words (`on`, `of`, `for`, `in`, `to`, `the`, `a`, `an`)
3. Removes words that overlap with the app name (e.g. "Exchange Rates" in the `exchangerate` app → `exchange_rates`)
4. Falls back to the full snake_case name if filtering removes everything

For example, with `appName() = 'google_sheets'`:
- "Create Spreadsheet" → `create_spreadsheet`
- "Add Sheet" → `add` (because "sheet" overlaps with "google_sheets")
- "Write Range" → `write_range`

The `ScriptBridge` then:
1. Looks up the function path in its `functionMap` to find the tool slug
2. Maps positional arguments to named parameters via `parameterMap`
3. Delegates to `ScriptToolInvoker::invoke()` which instantiates and executes the tool
4. Logs the call (path, duration, status, error) for observability
5. Suggests similar functions on typos ("Did you mean: ...")

### Writing JavaScript Docs

Supplementary docs are appended below the auto-generated parameter reference when an agent calls `code_read_doc("integrations.{name}")`. Use the correct `app.integrations.*` calling convention — agents will copy-paste from these examples:

```markdown
## Common Workflows

### Get current weather and format it

```js
var weather = app.integrations.weather.get({ location: "Amsterdam" });
var forecast = app.integrations.weather.forecast({ location: "Amsterdam", days: 3 });
console.log({ weather, forecast });
```

## Notes

- Locations accept city names, addresses, or lat/lng coordinates
- Rate limit: 60 requests per minute
```

Use the **derived function names** (as shown in auto-generated docs), not the raw tool slugs. For example, write `app.integrations.coingecko.market_rankings()` not `coingecko_markets()`.

Point to the file in your tool provider:

```php
public function scriptDocsPath(): ?string
{
    return __DIR__ . '/../script-docs/weather.md';
}
```

---

## Core Contracts Reference

### `Tool`

The fundamental unit of work. Every tool implements this interface.

```php
interface Tool
{
    public function name(): string;           // Slug for routing (e.g. 'get_weather')
    public function description(): string;    // Shown in docs and catalogs
    public function parameters(): array;      // Parameter definitions
    public function execute(array $args): ToolResult;
}
```

### `ToolProvider`

Groups tools under an app, handles instantiation.

```php
interface ToolProvider
{
    public function appName(): string;                              // Unique identifier
    public function appMeta(): array;                               // UI metadata
    public function tools(): array;                                 // Tool definitions
    public function isIntegration(): bool;                          // Toggleable per agent?
    public function createTool(string $class, array $context = []): Tool;
    public function scriptDocsPath(): ?string;                         // Supplementary docs
    public function credentialFields(): array;                      // Required credentials
}
```

### `CredentialResolver`

Abstracts credential storage. The host application binds its own implementation.

```php
interface CredentialResolver
{
    public function get(string $integration, string $key, mixed $default = null, ?string $account = null): mixed;
    public function isConfigured(string $integration, ?string $account = null): bool;
}
```

The `$account` parameter supports multi-account setups (e.g. "work" and "personal" Google accounts).

### `ConfigurableIntegration`

Optional. Adds a settings UI for the integration in OpenCompany.

```php
interface ConfigurableIntegration
{
    public function integrationMeta(): array;       // Name, description, icon, category
    public function configSchema(): array;          // Form field definitions
    public function testConnection(array $config): array;  // Verify credentials
    public function validationRules(): array;       // Laravel validation rules
}
```

### `AgentFileStorage`

Allows tools to save files into the agent's workspace without coupling to the host's file system.

```php
interface AgentFileStorage
{
    public function saveFile(
        object $agent,
        string $filename,
        string $content,
        string $mimeType,
        ?string $subfolder = null,
    ): array; // Returns ['id' => ..., 'path' => ..., 'url' => ...]
}
```

### `ScriptToolInvoker`

Host-side adapter for executing tools from the JavaScript bridge.

```php
interface ScriptToolInvoker
{
    public function invoke(string $toolSlug, array $args): mixed;
    public function getToolMeta(string $toolSlug): array;
}
```

### `ToolResult`

Value object returned by all tool executions.

```php
$result = ToolResult::success($data);            // Success with data
$result = ToolResult::success($data, $meta);     // Success with metadata
$result = ToolResult::error('Something failed'); // Error

$result->succeeded();  // bool
$result->data;         // mixed — string, array, or any serializable value
$result->error;        // ?string
$result->meta;         // array — files, timing, etc.
$result->toString();   // String representation for legacy consumers
```

### `HasTriggers`

Optional. Adds trigger/webhook support to a ToolProvider.

```php
interface HasTriggers
{
    public function triggers(): array;   // Slug => {class, name, description, icon}
    public function createTrigger(string $class, array $context = []): Trigger;
}
```

### `Trigger`

Abstract base class for event sources. Webhook triggers override `process()` and `verify()`; polling triggers override `poll()`.

```php
abstract class Trigger
{
    abstract public function name(): string;
    abstract public function description(): string;
    abstract public function type(): TriggerType;        // Webhook or Polling
    abstract public function onEnable(TriggerContext $ctx): void;
    abstract public function onDisable(TriggerContext $ctx): void;

    public function parameters(): array;                  // Config fields (default: [])
    public function process(TriggerContext $ctx, array $payload): TriggerResult;
    public function poll(TriggerContext $ctx): TriggerResult;
    public function verify(TriggerContext $ctx, array $headers, string $rawBody): bool;
    public function handshake(array $payload): ?array;
}
```

### `TriggerContext` / `TriggerStore`

Host-provided interfaces for trigger infrastructure.

```php
interface TriggerContext
{
    public function webhookUrl(): string;   // Host-generated endpoint URL
    public function store(): TriggerStore;  // Persistent key-value storage
    public function config(): array;        // User configuration values
}

interface TriggerStore
{
    public function get(string $key, mixed $default = null): mixed;
    public function put(string $key, mixed $value): void;
    public function has(string $key): bool;
    public function forget(string $key): void;
}
```

### `TriggerResult`

Value object returned by `process()` and `poll()`.

```php
$result = TriggerResult::event($data);   // Single event
$result = TriggerResult::from($events);  // Multiple events
$result = TriggerResult::empty();        // No events

$result->hasEvents();  // bool
$result->count();      // int
$result->events;       // list<array>
$result->meta;         // array
```

---

## Credential Management

### For Standalone Laravel Apps

The default `ConfigCredentialResolver` reads from `config/ai-tools.php`:

```php
// config/ai-tools.php
return [
    'weather' => [
        'api_key' => env('WEATHER_API_KEY'),
    ],
    'plausible' => [
        'api_key' => env('PLAUSIBLE_API_KEY'),
        'url'     => env('PLAUSIBLE_URL', 'https://plausible.io'),
    ],
    // Multi-account example
    'gmail' => [
        'work'     => ['api_key' => env('GMAIL_WORK_KEY')],
        'personal' => ['api_key' => env('GMAIL_PERSONAL_KEY')],
    ],
];
```

### How OpenCompany Manages Credentials

OpenCompany replaces `ConfigCredentialResolver` with `IntegrationSettingCredentialResolver` — a database-backed implementation:

- **Storage**: `integration_settings` table with an `encrypted:array` `config` column (Laravel's encryption cast)
- **Scoping**: All queries are workspace-scoped via `BelongsToWorkspace` trait — credentials never leak between workspaces
- **UI**: Users configure credentials through the Integrations settings page. Packages that implement `ConfigurableIntegration` get automatic form rendering from their `configSchema()`
- **Masking**: Secret fields are never returned in plaintext to the frontend — displayed as `****xxxx`
- **Test connection**: The UI calls `testConnection()` to verify credentials before saving

```php
// OpenCompany's AppServiceProvider
$this->app->singleton(
    CredentialResolver::class,
    IntegrationSettingCredentialResolver::class,
);
```

The optional `$account` parameter on `CredentialResolver::get()`, `isConfigured()`, and `getAccounts()` is the shared path for multi-account hosts. KosmoKrator uses it for headless named credentials; OpenCompany can map it to workspace-scoped account aliases.

### Custom Credential Storage

Bind your own `CredentialResolver` implementation:

```php
// In your AppServiceProvider
$this->app->singleton(
    \OpenCompany\IntegrationCore\Contracts\CredentialResolver::class,
    \App\Services\YourCustomResolver::class,
);
```

---

## Static Analysis

Packages that include a `phpstan.neon` are configured for [Larastan](https://github.com/larastan/larastan) level 5:

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - src/
    level: 5
```

Run from any package directory:

```console
cd packages/mermaid && ../../vendor/bin/phpstan analyse
```

---

## Contributing

### Adding a New Integration

1. Create a new directory under `packages/` following the structure above
2. Implement `ToolProvider` (and optionally `ConfigurableIntegration`)
3. Create your service class and tool classes
4. Add script-docs if the integration has non-obvious workflows — use `app.integrations.{name}.{function}()` syntax
5. Add a `phpstan.neon` and ensure level 5 passes
6. Run `php build-catalog.php` and update this README's structure listing and integrations table

### Conventions

- **Naming**: Package directories and `appName()` are lowercase kebab/snake. Namespaces are PascalCase.
- **Icons**: Use [Phosphor Icons](https://icon-sets.iconify.design/ph/) (`ph:` prefix).
- **Tool types**: Use `'read'` for tools that fetch data, `'write'` for tools that create, modify, or delete.
- **Parameter names**: Always `snake_case`.
- **Error handling**: Tools should catch exceptions and return `ToolResult::error()` — never let exceptions bubble out of `execute()`.
- **Service isolation**: Tools call service methods. Services make HTTP requests. Tools never make HTTP requests directly.
- **No hardcoded config**: Always use `CredentialResolver` for API keys and endpoints. Never read `config()` or `env()` directly in tool or service classes.

### Checklist for New Integrations

- [ ] `composer.json` with correct package name, namespace, and Laravel provider auto-discovery
- [ ] Service class encapsulating all API communication
- [ ] Service provider with singleton service registration and `ToolProviderRegistry` boot
- [ ] Tool provider implementing `ToolProvider` (and `ConfigurableIntegration` if credentials are needed)
- [ ] Capability metadata checked; add `HasIntegrationCapabilities` only when catalog inference is not specific enough
- [ ] Tool classes with clear `description()`, typed `parameters()`, and `ToolResult` returns
- [ ] `credentialFields()` defined for any required API keys or tokens
- [ ] `testConnection()` if implementing `ConfigurableIntegration`
- [ ] `script-docs/{name}.md` for integrations with complex workflows (using `app.integrations.*` calling convention)
- [ ] `php build-catalog.php` run, with generated auth/setup/SEO fields reviewed for CLI, JavaScript, and MCP gateway docs
- [ ] Entry added to README structure listing and integrations table
- [ ] JavaScript-doc function names match `deriveFunctionName()` output (check auto-generated docs via `code_read_doc`)

## License

MIT

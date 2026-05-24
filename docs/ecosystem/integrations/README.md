# OpenCompany Integrations

Monorepo for all [OpenCompany](https://github.com/OpenCompanyApp) integration packages. Each package exposes tools that AI agents can call — from rendering diagrams to querying APIs to managing tasks.

Status: Current package authoring/reference copy. In standalone Laravel consumers the default credential resolver reads `config/ai-tools.php`; in OpenCompany itself credentials and enablement are workspace-scoped through `IntegrationSetting`, `config/integrations.php`, `config/chat_integrations.php`, and dynamic integration catalog metadata.

Integrations are independent Composer packages built on a shared core. They work in any PHP 8.2+ application: [OpenCompany](https://github.com/OpenCompanyApp) (web), [KosmoKrator](https://github.com/OpenCompanyApp) (CLI), or your own consumer.

## Repository Structure

```
core/               Shared contracts, credential abstraction, Lua bridge, registry
celestial/          Astronomy: moon phases, sunrise/sunset, planet positions, eclipses
clickup/            ClickUp project management: tasks, lists, folders, time tracking
coingecko/          CoinGecko cryptocurrency: prices, market data, trending, charts
exchangerate/       Currency exchange rates: 340+ fiat, crypto, and metal conversions
google/             Google Calendar, Gmail, Drive, Sheets, Docs, Forms, Contacts, Tasks, Analytics, Search Console
mermaid/            Mermaid diagram rendering to PNG
plantuml/           PlantUML diagram rendering to PNG
plausible/          Plausible Analytics: stats, realtime visitors, goals
ticktick/           TickTick task management with time tracking
trustmrr/           TrustMRR verified startup revenue data
typst/              Typst document rendering to PDF
vegalite/           Vega-Lite chart rendering to PNG
worldbank/          World Bank economic indicators for 200+ countries
```

## Architecture

```
┌─────────────────────────────────────────────────┐
│  Host Application (OpenCompany, KosmoKrator)     │
│                                                  │
│  ┌──────────┐   ┌───────────────────────────┐   │
│  │ Lua VM   │──▸│ LuaBridge                  │   │
│  │          │   │   functionMap → tool slugs  │   │
│  │ app.integrations.mermaid.render(...)       │   │
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
- **ToolProvider** — Groups related tools under an app name. Declares metadata, handles tool instantiation with credentials, and optionally provides Lua documentation.
- **ToolProviderRegistry** — Singleton that collects all providers. The host queries it to discover available tools.
- **CredentialResolver** — Abstraction for API keys. The standalone default reads from `config/ai-tools.php`; OpenCompany swaps this for encrypted, workspace-scoped `IntegrationSetting` storage.
- **LuaBridge** — Routes `app.integrations.{name}.{function}(...)` calls from the Lua VM to PHP tool classes.

## Documented Core Packages

The table below covers the curated package docs maintained in this directory.
OpenCompany's runtime can expose many more catalog integrations through
`opencompanyapp/integration-bundle` and the generated package catalog; use
`IntegrationCatalog` and `ToolProviderRegistry` for the live installed count.

| Package | Tools | Credentials | Category | Description |
|---------|------:|-------------|----------|-------------|
| [celestial](celestial/) | 9 | None | Data | Moon phases, sunrise/sunset, planet positions, eclipses, zodiac |
| [clickup](clickup/) | 34 | API token | Productivity | Tasks, lists, folders, time tracking, docs, chat |
| [coingecko](coingecko/) | 29 | None | Data | Crypto prices, market data, trending coins, historical charts |
| [exchangerate](exchangerate/) | 6 | None | Data | 340+ currency conversions (fiat, crypto, metals) |
| [google](google/) | 121 | OAuth | Productivity | Calendar, Gmail, Drive, Sheets, Docs, Forms, Contacts, Tasks, Analytics, Search Console |
| [mermaid](mermaid/) | 1 | None | Rendering | Flowcharts, sequences, Gantt, class diagrams → PNG |
| plantuml | 1 | None | Rendering | UML class, sequence, activity, component, state → PNG |
| [plausible](plausible/) | 8 | API key | Analytics | Stats, realtime visitors, site and goal management |
| [ticktick](ticktick/) | 9 | OAuth | Productivity | Projects, tasks, time tracking (TickTick and Dida365) |
| [trustmrr](trustmrr/) | 2 | API key | Data | Verified startup revenue, MRR, growth, acquisitions |
| typst | 1 | None | Rendering | Reports, invoices, proposals → PDF |
| vegalite | 1 | None | Rendering | Bar, line, scatter, heatmap, boxplot charts → PNG |
| [worldbank](worldbank/) | 14 | None | Data | GDP, inflation, population for 200+ countries |

## Installation

Each subdirectory is an independent Composer package. In your consuming application:

```json
{
    "repositories": [
        {"type": "path", "url": "../integrations/*"}
    ],
    "require": {
        "opencompanyapp/integration-core": "@dev",
        "opencompanyapp/integration-mermaid": "@dev",
        "opencompanyapp/integration-plausible": "@dev"
    }
}
```

Laravel auto-discovers service providers. For non-Laravel apps, use the contracts and registry directly.

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

```
weather/
├── composer.json
├── src/
│   ├── WeatherServiceProvider.php
│   ├── WeatherService.php
│   ├── WeatherToolProvider.php
│   └── Tools/
│       └── GetWeather.php
└── lua-docs/              (optional)
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

    public function luaDocsPath(): ?string
    {
        return __DIR__ . '/../lua-docs/weather.md';
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
- `description` — Shown in generated Lua docs and tool catalogs
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

## Lua Documentation

Agents discover tools through auto-generated Lua API docs. The `LuaDocRenderer` and `LuaCatalogBuilder` in core handle this automatically based on your `parameters()` and `description()` definitions.

For complex integrations, add a `lua-docs/{name}.md` file with supplementary documentation — workflows, examples, and gotchas that aren't captured by the parameter reference.

### Writing Lua Docs

```markdown
## Common Workflows

### Get current weather and format it

```lua
local weather = app.integrations.weather.get({location = "Amsterdam"})
local forecast = app.integrations.weather.forecast({location = "Amsterdam", days = 3})
```

### Batch lookups

```lua
local cities = {"Amsterdam", "London", "Tokyo"}
for _, city in ipairs(cities) do
    local w = app.integrations.weather.get({location = city})
    -- process results
end
```

## Notes

- Locations accept city names, addresses, or lat/lng coordinates
- Rate limit: 60 requests per minute
- Temperature is in Celsius by default (use `units = "imperial"` for Fahrenheit)
```

Point to the file in your tool provider:

```php
public function luaDocsPath(): ?string
{
    return __DIR__ . '/../lua-docs/weather.md';
}
```

### How Lua Routing Works

The `LuaCatalogBuilder` transforms your tool definitions into a Lua namespace tree:

```
app.integrations.weather.get({location = "Amsterdam"})
│   │              │      │
│   │              │      └─ Function name (derived from tool name, minus app name)
│   │              └─ App name (from ToolProvider::appName())
│   └─ "integrations." prefix (added when isIntegration() returns true)
└─ Root namespace
```

The `LuaBridge` then:
1. Looks up the function path in its `functionMap` to find the tool slug
2. Maps positional arguments to named parameters via `parameterMap`
3. Delegates to `LuaToolInvoker::invoke()` which instantiates and executes the tool
4. Logs the call (path, duration, status, error) for observability
5. Suggests similar functions on typos ("Did you mean: ...")

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
    public function luaDocsPath(): ?string;                         // Supplementary docs
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

### `LuaToolInvoker`

Host-side adapter for executing tools from the Lua bridge.

```php
interface LuaToolInvoker
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

### Custom Credential Storage

Bind your own `CredentialResolver` implementation:

```php
// In your AppServiceProvider
$this->app->singleton(
    \OpenCompany\IntegrationCore\Contracts\CredentialResolver::class,
    \App\Services\DatabaseCredentialResolver::class,
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
cd mermaid && ../vendor/bin/phpstan analyse
```

---

## Contributing

### Adding a New Integration

1. Create a new directory following the structure above
2. Implement `ToolProvider` (and optionally `ConfigurableIntegration`)
3. Create your service class and tool classes
4. Add lua-docs if the integration has non-obvious workflows
5. Add a `phpstan.neon` and ensure level 5 passes
6. Update this README's structure listing and integrations table

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
- [ ] Tool classes with clear `description()`, typed `parameters()`, and `ToolResult` returns
- [ ] `credentialFields()` defined for any required API keys or tokens
- [ ] `testConnection()` if implementing `ConfigurableIntegration`
- [ ] `lua-docs/{name}.md` for integrations with complex workflows
- [ ] Entry added to README structure listing and integrations table

## License

MIT

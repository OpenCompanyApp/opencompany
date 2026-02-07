# AI Tool Package Architecture

> How OpenCompany's AI tools are built as standalone Composer packages that any Laravel AI SDK app can install.

## Overview

OpenCompany's agent tools follow a plugin architecture inspired by n8n community nodes. Instead of a monolithic tool registry where every integration lives inside the app, external integrations are extracted into independent Composer packages:

```
opencompanyapp/ai-tool-core          ← Contracts, credential abstraction, registry
opencompanyapp/ai-tool-celestial     ← Astronomy: moon phases, planets, eclipses
opencompanyapp/ai-tool-plausible     ← (planned) Web analytics
opencompanyapp/ai-tool-telegram      ← (planned) Messaging notifications
```

Each package:
- Implements the `ToolProvider` contract from `ai-tool-core`
- Contains one or more tools implementing Laravel AI SDK's `Tool` interface
- Auto-registers with Laravel's service provider discovery
- Works standalone in any Laravel app, or integrates with OpenCompany's permission system

Built-in tools (chat, docs, tables, calendar, lists, workspace management) remain inside the app — they're tightly coupled to OpenCompany's data models and don't make sense as standalone packages.

## Package Ecosystem

```
┌──────────────────────────────────────────────────────┐
│                   Host Application                    │
│                                                       │
│  ┌─────────────────────────────────────────────────┐ │
│  │  ToolRegistry (hybrid mode)                     │ │
│  │  ┌───────────────┐  ┌────────────────────────┐  │ │
│  │  │ Static arrays  │  │ ToolProviderRegistry   │  │ │
│  │  │ (built-in)     │  │ (dynamic packages)     │  │ │
│  │  │                │  │                        │  │ │
│  │  │ chat           │  │ celestial (package)    │  │ │
│  │  │ docs           │  │ plausible (package)    │  │ │
│  │  │ tables         │  │ ...                    │  │ │
│  │  │ calendar       │  │                        │  │ │
│  │  │ lists          │  │                        │  │ │
│  │  │ workspace      │  │                        │  │ │
│  │  └───────────────┘  └────────────────────────┘  │ │
│  │                  getEffective*()                  │ │
│  │              merges both at runtime               │ │
│  └─────────────────────────────────────────────────┘ │
│                                                       │
│  ┌─────────────────────────────────────────────────┐ │
│  │  CredentialResolver                             │ │
│  │  (DB-backed in OpenCompany, config-based default)│ │
│  └─────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────┘
         │                      │
         ▼                      ▼
┌─────────────────┐  ┌──────────────────────┐
│ ai-tool-core    │  │ ai-tool-celestial    │
│                 │  │                      │
│ ToolProvider    │◄─│ CelestialToolProvider│
│ CredResolver    │  │ QueryCelestial       │
│ ProviderRegistry│  │ CelestialService     │
└─────────────────┘  └──────────────────────┘
                              │
                              ▼
                     ┌─────────────────┐
                     │ astronomy-bundle│
                     │ (Meeus/VSOP87) │
                     └─────────────────┘
```

---

## ai-tool-core

**Package:** `opencompanyapp/ai-tool-core`
**Namespace:** `OpenCompany\AiToolCore`
**Repo:** [github.com/OpenCompanyApp/ai-tool-core](https://github.com/OpenCompanyApp/ai-tool-core)

### ToolProvider Contract

The central interface every tool package implements. It declares tools, metadata, and a factory method:

```php
// src/Contracts/ToolProvider.php

interface ToolProvider
{
    public function appName(): string;
    public function appMeta(): array;
    public function tools(): array;
    public function isIntegration(): bool;
    public function createTool(string $class, array $context = []): \Laravel\Ai\Contracts\Tool;
}
```

**Methods explained:**

| Method | Returns | Purpose |
|--------|---------|---------|
| `appName()` | `string` | Unique identifier: `'celestial'`, `'plausible'`, etc. |
| `appMeta()` | `array` | UI metadata: `label`, `description`, `icon`, `logo` |
| `tools()` | `array` | Tool definitions keyed by slug. Each has `class`, `type` (read/write), `name`, `description`, `icon` |
| `isIntegration()` | `bool` | If `true`, can be toggled on/off per agent in the host app |
| `createTool()` | `Tool` | Factory that instantiates a tool. `$context` passes runtime deps without coupling to host models |

The `$context` array is intentionally untyped. Inside OpenCompany it receives `['agent' => User, 'timezone' => 'Europe/Amsterdam']`. Standalone apps pass `[]` or their own context. This avoids coupling core to any specific User model.

### CredentialResolver Contract

Thin abstraction for API keys and configuration values:

```php
// src/Contracts/CredentialResolver.php

interface CredentialResolver
{
    public function get(string $integration, string $key, mixed $default = null): mixed;
    public function isConfigured(string $integration): bool;
}
```

Not every tool needs credentials (Celestial doesn't). But tools like Plausible or Telegram need API keys. The `CredentialResolver` lets tool packages request credentials without knowing the storage backend.

**In OpenCompany**, credentials are always managed through the Integrations UI — users configure API keys, URLs, and other settings through the admin interface. These are stored encrypted in the `integration_settings` table. Tool packages never interact with config files.

**For standalone usage** in other Laravel apps, the default `ConfigCredentialResolver` reads from `config/ai-tools.php`:

```php
return [
    'plausible' => [
        'api_key' => env('PLAUSIBLE_API_KEY'),
        'url'     => env('PLAUSIBLE_URL', 'https://plausible.io'),
    ],
];
```

The host app can swap the resolver by binding its own implementation before the core service provider runs.

### ToolProviderRegistry

Simple singleton collection of registered providers:

```php
// src/Support/ToolProviderRegistry.php

class ToolProviderRegistry
{
    private array $providers = [];

    public function register(ToolProvider $provider): void;
    public function all(): array;           // appName => ToolProvider
    public function get(string $appName): ?ToolProvider;
    public function has(string $appName): bool;
}
```

Tool packages register themselves in their service provider's `boot()` method. The host app reads from the registry to discover available tools.

### AiToolCoreServiceProvider

Auto-discovered by Laravel. Binds two things:

1. `ToolProviderRegistry` as singleton — shared across all packages
2. `CredentialResolver` via `bindIf` — defaults to `ConfigCredentialResolver`, easily overridden by the host app binding first

---

## Building a Tool Package

Step-by-step guide using the Celestial package as a template.

### 1. Create `composer.json`

```json
{
    "name": "opencompanyapp/ai-tool-weather",
    "description": "Weather AI tool for Laravel AI SDK",
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "opencompanyapp/ai-tool-core": "^1.0",
        "laravel/ai": "^0.1"
    },
    "autoload": {
        "psr-4": {
            "OpenCompany\\AiToolWeather\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "OpenCompany\\AiToolWeather\\AiToolWeatherServiceProvider"
            ]
        }
    }
}
```

### 2. Create the Tool Class

Implement Laravel AI SDK's `Tool` interface:

```php
// src/Tools/GetWeather.php

namespace OpenCompany\AiToolWeather\Tools;

use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class GetWeather implements Tool
{
    public function __construct(
        private WeatherClient $client,
        private string $defaultUnits = 'metric',
    ) {}

    public function description(): string
    {
        return 'Get current weather and forecast for a location.';
    }

    public function handle(Request $request): string
    {
        $location = $request['location'];
        $units = $request['units'] ?? $this->defaultUnits;

        return $this->client->getWeather($location, $units);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'location' => $schema->string()->description('City name or coordinates')->required(),
            'units'    => $schema->string()->description("'metric' or 'imperial'"),
        ];
    }
}
```

Key points:
- Constructor takes dependencies, not host-specific models
- `description()` tells the LLM what the tool does
- `handle()` does the work, returns a string
- `schema()` defines parameters using Laravel's JsonSchema builder

### 3. Implement ToolProvider

```php
// src/WeatherToolProvider.php

namespace OpenCompany\AiToolWeather;

use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolCore\Contracts\CredentialResolver;
use OpenCompany\AiToolCore\Contracts\ToolProvider;
use OpenCompany\AiToolWeather\Tools\GetWeather;

class WeatherToolProvider implements ToolProvider
{
    public function appName(): string
    {
        return 'weather';
    }

    public function appMeta(): array
    {
        return [
            'label'       => 'weather, forecast, temperature',
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
                'description' => 'Current weather and forecasts for any location.',
                'icon'        => 'ph:cloud-sun',
            ],
        ];
    }

    public function isIntegration(): bool
    {
        return true;
    }

    public function createTool(string $class, array $context = []): Tool
    {
        $credentials = app(CredentialResolver::class);

        return match ($class) {
            GetWeather::class => new GetWeather(
                new WeatherClient($credentials->get('weather', 'api_key')),
                $context['units'] ?? 'metric',
            ),
            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
    }
}
```

### 4. Create the Service Provider

```php
// src/AiToolWeatherServiceProvider.php

namespace OpenCompany\AiToolWeather;

use Illuminate\Support\ServiceProvider;
use OpenCompany\AiToolCore\Support\ToolProviderRegistry;

class AiToolWeatherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind any services your tools need
        $this->app->singleton(WeatherClient::class, function () {
            $credentials = app(\OpenCompany\AiToolCore\Contracts\CredentialResolver::class);
            return new WeatherClient($credentials->get('weather', 'api_key'));
        });
    }

    public function boot(): void
    {
        // Register with the core tool registry (if available)
        if ($this->app->bound(ToolProviderRegistry::class)) {
            $this->app->make(ToolProviderRegistry::class)
                ->register(new WeatherToolProvider());
        }
    }
}
```

The `$this->app->bound()` check makes the package work both with and without `ai-tool-core`. Without core, you can still use the tool directly.

---

## ai-tool-celestial: Reference Implementation

**Package:** `opencompanyapp/ai-tool-celestial`
**Namespace:** `OpenCompany\AiToolCelestial`
**Repo:** [github.com/OpenCompanyApp/ai-tool-celestial](https://github.com/OpenCompanyApp/ai-tool-celestial)

### Structure

```
ai-tool-celestial/
├── composer.json
├── LICENSE
├── README.md
└── src/
    ├── AiToolCelestialServiceProvider.php
    ├── CelestialService.php
    ├── CelestialToolProvider.php
    └── Tools/
        └── QueryCelestial.php
```

### How It Works

1. **`CelestialService`** — 820-line service that wraps the `astronomy-bundle` library. Methods: `moonPhase()`, `sunInfo()`, `moonInfo()`, `planetPosition()`, `solarEclipse()`, `lunarEclipse()`, `nightSky()`, `zodiacReport()`, `timeInfo()`. Pure computation, no framework dependencies.

2. **`QueryCelestial`** — Tool class implementing `Laravel\Ai\Contracts\Tool`. Routes the `action` parameter to the appropriate service method. Constructor takes `CelestialService` and `string $defaultTimezone`.

3. **`CelestialToolProvider`** — Implements `ToolProvider`. Declares one tool (`query_celestial`) with metadata. Factory creates `QueryCelestial` with service instance and timezone from context.

4. **`AiToolCelestialServiceProvider`** — Binds `CelestialService` as singleton, registers with `ToolProviderRegistry` in boot.

### Why Celestial Was the First Extraction

- No API keys needed (pure computation)
- Single tool with clear boundaries
- Only depends on `astronomy-bundle` (no OpenCompany models)
- Good test case for the ToolProvider pattern

---

## Using in Other Laravel Apps

Any Laravel app with `laravel/ai` installed can use these tool packages independently.

### Minimal Setup

```bash
composer require opencompanyapp/ai-tool-celestial
```

```php
use Laravel\Ai\Facades\Ai;
use OpenCompany\AiToolCelestial\Tools\QueryCelestial;
use OpenCompany\AiToolCelestial\CelestialService;

// Direct instantiation
$tool = new QueryCelestial(
    service: app(CelestialService::class),
    defaultTimezone: 'America/New_York',
);

// Use with Laravel AI SDK
$response = Ai::agent()
    ->tools([$tool])
    ->prompt('What phase is the moon in right now?');
```

### With Multiple Tool Packages

```php
use OpenCompany\AiToolCore\Support\ToolProviderRegistry;

$registry = app(ToolProviderRegistry::class);

// All tools auto-registered via service providers
$tools = [];
foreach ($registry->all() as $provider) {
    foreach ($provider->tools() as $slug => $meta) {
        $tools[] = $provider->createTool($meta['class']);
    }
}

$response = Ai::agent()
    ->tools($tools)
    ->prompt('What is the moon phase and what is the weather in Amsterdam?');
```

### Credential Configuration

For tools that need API keys, create `config/ai-tools.php`:

```php
return [
    'plausible' => [
        'api_key' => env('PLAUSIBLE_API_KEY'),
        'url'     => env('PLAUSIBLE_URL', 'https://plausible.io'),
    ],
    'weather' => [
        'api_key' => env('WEATHER_API_KEY'),
    ],
];
```

The default `ConfigCredentialResolver` reads from this file automatically.

---

## Using in OpenCompany

Inside OpenCompany, tool packages integrate with the existing `ToolRegistry` and permission system.

### Hybrid ToolRegistry

`app/Agents/Tools/ToolRegistry.php` operates in hybrid mode — static arrays for built-in tools, dynamic `ToolProviderRegistry` for packages.

**Static arrays (built-in):**
- `TOOL_MAP` — 35+ tools with class, type, name, description, icon
- `APP_GROUPS` — Groups tools by app (chat, docs, tables, etc.)
- `INTEGRATION_APPS` — Which apps are external integrations
- `APP_ICONS` / `INTEGRATION_LOGOS` — UI icons per app

**Dynamic merging via `getEffective*()` methods:**

```php
private function getEffectiveToolMap(): array
{
    if ($this->effectiveToolMap === null) {
        $this->effectiveToolMap = self::TOOL_MAP;  // Start with built-in
        foreach ($this->providerRegistry->all() as $provider) {
            foreach ($provider->tools() as $slug => $meta) {
                $this->effectiveToolMap[$slug] = $meta;  // Merge package tools
            }
        }
    }
    return $this->effectiveToolMap;
}
```

Five `getEffective*()` methods merge: ToolMap, AppGroups, IntegrationApps, AppIcons, IntegrationLogos. Results are cached on the instance.

### Tool Instantiation Flow

When OpenCompany needs to create a tool instance:

```
instantiateTool($class, $agent)
  │
  ├─ Check ToolProviderRegistry → delegate to $provider->createTool()
  │    with context: ['agent' => $agent, 'timezone' => AppSetting::getValue('org_timezone')]
  │
  └─ Fall through to built-in match statement
       for chat, docs, tables, calendar, lists, workspace tools
```

### Permission System Integration

External packages integrate seamlessly with OpenCompany's permission system:

1. `isIntegration() === true` → appears in integration toggle UI
2. Agent must have the integration enabled to see its tools
3. Individual tool permissions (allow/deny/require approval) work on package tools too
4. `ApprovalWrappedTool` decorator wraps package tools when approval is required

### Credential Override

OpenCompany overrides the default config-based resolver with a DB-backed one:

```php
// app/Providers/AppServiceProvider.php

$this->app->singleton(
    \OpenCompany\AiToolCore\Contracts\CredentialResolver::class,
    \App\Services\IntegrationSettingCredentialResolver::class
);
```

```php
// app/Services/IntegrationSettingCredentialResolver.php

class IntegrationSettingCredentialResolver implements CredentialResolver
{
    public function get(string $integration, string $key, mixed $default = null): mixed
    {
        $setting = IntegrationSetting::where('integration_id', $integration)->first();
        return $setting?->getConfigValue($key, $default) ?? $default;
    }

    public function isConfigured(string $integration): bool
    {
        return ! empty($this->get($integration, 'api_key'));
    }
}
```

This reads encrypted API keys from the `integration_settings` table instead of config files. Package tools call `CredentialResolver` without knowing the implementation.

---

## Key Files Reference

### Core Package (`tmp/ai-tool-core/`)

| File | Role |
|------|------|
| `src/Contracts/ToolProvider.php` | Central interface for tool packages |
| `src/Contracts/CredentialResolver.php` | API key abstraction |
| `src/Support/ConfigCredentialResolver.php` | Default: reads from `config/ai-tools.php` |
| `src/Support/ToolProviderRegistry.php` | Singleton collecting all providers |
| `src/AiToolCoreServiceProvider.php` | Binds registry + default resolver |

### Celestial Package (`tmp/ai-tool-celestial/`)

| File | Role |
|------|------|
| `src/CelestialToolProvider.php` | ToolProvider implementation — metadata + factory |
| `src/Tools/QueryCelestial.php` | Tool class — 9 astronomy actions |
| `src/CelestialService.php` | Calculation engine wrapping astronomy-bundle |
| `src/AiToolCelestialServiceProvider.php` | Binds service, registers provider |

### OpenCompany App

| File | Role |
|------|------|
| `app/Agents/Tools/ToolRegistry.php` | Hybrid registry — merges static + dynamic tools |
| `app/Providers/AppServiceProvider.php` | Overrides CredentialResolver with DB-backed impl |
| `app/Services/IntegrationSettingCredentialResolver.php` | DB credential resolver |
| `app/Services/AgentPermissionService.php` | Permission system (integration toggles, tool-level perms) |

### Composer Configuration

| File | Relevant Section |
|------|-----------------|
| `composer.json` (root) | `repositories` array with path repos for local dev + VCS repos for remote |
| `tmp/ai-tool-core/composer.json` | Core package definition |
| `tmp/ai-tool-celestial/composer.json` | Celestial package definition |

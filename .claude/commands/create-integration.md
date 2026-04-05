---
description: Scaffold a new integration package for the OpenCompany ecosystem
argument-hint: <tool-name>
---

# Create Integration Package

Create a new integration package called `$ARGUMENTS` in the `../integrations/` monorepo directory (sibling to this project), following the established OpenCompany integration package pattern.

## Before you start

Read the reference implementation to understand the exact patterns:

1. Read `../integrations/celestial/src/CelestialToolProvider.php` — ToolProvider with atomic tool entries
2. Read `../integrations/celestial/src/CelestialServiceProvider.php` — ServiceProvider pattern
3. Read `../integrations/celestial/src/Tools/CelestialMoonPhase.php` — Atomic tool class (one operation per class)
4. Read `../integrations/celestial/composer.json` — Package dependencies
5. Read `../integrations/core/src/Contracts/Tool.php` — The Tool contract to implement
6. Read `../integrations/core/src/Contracts/ToolProvider.php` — The ToolProvider contract
7. Read `../integrations/core/src/Contracts/CredentialResolver.php` — For tools needing API keys
8. Read `../integrations/core/src/Contracts/ConfigurableIntegration.php` — Config UI contract for integrations with credentials

## Package structure to create

```
../integrations/$ARGUMENTS/
├── composer.json
├── LICENSE          (MIT, copy from ../integrations/celestial/LICENSE)
├── README.md
├── lua-docs/        (supplementary Lua API docs)
│   └── {name}.md
└── src/
    ├── {Name}ServiceProvider.php
    ├── {Name}ToolProvider.php
    ├── {Name}Service.php          (if the tool has a service layer)
    └── Tools/
        ├── {Name}{Verb}{Noun}.php   (one class per operation)
        └── ...
```

## Requirements for each file

### composer.json
- Name: `opencompanyapp/integration-$ARGUMENTS`
- Namespace: `OpenCompany\Integrations\{Name}\`
- Require: `php ^8.2`, `opencompanyapp/integration-core ^2.0 || @dev`
- NO `laravel/ai` dependency (packages are framework-agnostic)
- Laravel auto-discovery for the ServiceProvider

### Tool classes — atomic pattern (one class = one operation)
- Implement `OpenCompany\IntegrationCore\Contracts\Tool`
- **One class per operation** — never multiplex with an `action` parameter
- Constructor: inject service dependencies (not User, not app-specific models)
- `name()`: returns the tool slug (e.g., `'weather_get_forecast'`)
- `description()`: focused text telling the agent what this operation does
- `parameters()`: returns keyed array of parameter definitions
- `execute(array $args)`: perform the action, return `ToolResult`

Example atomic tool:
```php
<?php
namespace OpenCompany\Integrations\Weather\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Weather\WeatherService;

class WeatherGetForecast implements Tool
{
    public function __construct(
        private WeatherService $service,
    ) {}

    public function name(): string
    {
        return 'weather_get_forecast';
    }

    public function description(): string
    {
        return 'Get a 5-day weather forecast for a location.';
    }

    public function parameters(): array
    {
        return [
            'location' => [
                'type' => 'string',
                'required' => true,
                'description' => 'City name or coordinates (e.g. "Amsterdam" or "52.37,4.89").',
            ],
            'units' => [
                'type' => 'string',
                'description' => 'Unit system: "metric" (°C) or "imperial" (°F). Default: metric.',
                'enum' => ['metric', 'imperial'],
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
            $result = $this->service->getForecast($location, $args['units'] ?? 'metric');
            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }
}
```

### ToolProvider
- Implement `OpenCompany\IntegrationCore\Contracts\ToolProvider`
- `appName()` returns the slug (e.g., `'weather'`)
- `appMeta()` returns label, description, icon (use `ph:` prefix for Phosphor icons), logo
- `tools()` returns slug => metadata array — **one entry per atomic tool**
- `isIntegration()` returns `true` for external integrations
- `createTool(string $class, array $context)` factory — instantiate the tool class with dependencies
- `luaDocsPath()` returns path to lua-docs markdown file (or null)
- `credentialFields()` returns credential field definitions for CLI setup flows

Tool slug convention: `{appname}_{verb}_{noun}` — e.g., `weather_get_forecast`, `weather_list_locations`.

### ServiceProvider
- Bind service classes as singletons
- For tools needing API keys, use `CredentialResolver` in the service binding:
  ```php
  $this->app->singleton(MyService::class, function ($app) {
      $creds = $app->make(\OpenCompany\IntegrationCore\Contracts\CredentialResolver::class);
      return new MyService(
          apiKey: $creds->get('{integration}', 'api_key', ''),
      );
  });
  ```
- In `boot()`, register with ToolProviderRegistry:
  ```php
  if ($this->app->bound(ToolProviderRegistry::class)) {
      $this->app->make(ToolProviderRegistry::class)
          ->register(new {Name}ToolProvider());
  }
  ```

### ConfigurableIntegration (for integrations with credentials)

Most external API integrations should also implement `ConfigurableIntegration` alongside `ToolProvider`. This gives automatic config UI rendering in the Integrations page, a "Test Connection" button, and credential validation on save.

See `../integrations/clickup/src/ClickUpToolProvider.php` for a complete real-world example.

### Lua docs

Create `lua-docs/{name}.md` with practical workflow examples, gotchas, and tips. Wire it up via `luaDocsPath()`:
```php
public function luaDocsPath(): ?string
{
    return __DIR__ . '/../lua-docs/{name}.md';
}
```

See `../integrations/clickup/lua-docs/clickup.md` for an example.

## After creating the package

1. The monorepo wildcard path (`../integrations/*`) auto-discovers the new package.
2. Add to `composer.json` require: `"opencompanyapp/integration-$ARGUMENTS": "@dev"`
3. Run `composer update`
4. Verify with `php artisan tinker`:
   ```php
   $registry = app(\OpenCompany\IntegrationCore\Support\ToolProviderRegistry::class);
   $registry->has('{name}'); // should be true
   ```

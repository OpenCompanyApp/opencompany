---
description: Scaffold a new integration package for the OpenCompany ecosystem
argument-hint: <tool-name>
---

# Create Integration Package

Create a new integration package called `ai-tool-$ARGUMENTS` in the `tmp/` directory, following the established OpenCompany integration package pattern.

## Before you start

Read the reference implementation to understand the exact patterns:

1. Read `tmp/ai-tool-celestial/src/CelestialToolProvider.php` — ToolProvider with atomic tool entries
2. Read `tmp/ai-tool-celestial/src/AiToolCelestialServiceProvider.php` — ServiceProvider pattern
3. Read `tmp/ai-tool-celestial/src/Tools/CelestialMoonPhase.php` — Atomic tool class (one operation per class)
4. Read `tmp/ai-tool-celestial/src/Tools/CelestialSunInfo.php` — Another atomic tool example
5. Read `tmp/ai-tool-celestial/composer.json` — Package dependencies
6. Read `tmp/integration-core/src/Contracts/ToolProvider.php` — The contract to implement
7. Read `tmp/integration-core/src/Contracts/CredentialResolver.php` — For tools needing API keys
8. Read `tmp/integration-core/src/Contracts/ProvidesLuaDocs.php` — Optional: supplementary Lua docs
9. Read `tmp/integration-core/src/Contracts/ConfigurableIntegration.php` — Config UI contract for integrations with credentials
10. Read `tmp/ai-tool-clickup/src/ClickUpToolProvider.php` — ConfigurableIntegration + ToolProvider example

## Package structure to create

```
tmp/ai-tool-$ARGUMENTS/
├── composer.json
├── LICENSE          (MIT, copy from tmp/ai-tool-celestial/LICENSE)
├── README.md
├── docs/            (optional — for supplementary Lua docs)
│   └── lua-docs.md
└── src/
    ├── AiTool{Name}ServiceProvider.php
    ├── {Name}ToolProvider.php
    ├── {Name}Service.php          (if the tool has a service layer)
    └── Tools/
        ├── {Name}{Verb}{Noun}.php   (one class per operation)
        ├── {Name}{Verb}{Noun}.php
        └── ...
```

## Requirements for each file

### composer.json
- Name: `opencompanyapp/ai-tool-$ARGUMENTS`
- Namespace: `OpenCompany\AiTool{Name}\`
- Require: `php ^8.2`, `opencompanyapp/integration-core ^2.0 || @dev`, `laravel/ai ^0.1`
- Laravel auto-discovery for the ServiceProvider
- Add any integration-specific PHP dependencies

### ToolProvider
- Implement `OpenCompany\IntegrationCore\Contracts\ToolProvider`
- `appName()` returns the slug (e.g., `'weather'`)
- `appMeta()` returns label, description, icon (use `ph:` prefix for Phosphor icons), logo
- `tools()` returns slug => metadata array — **one entry per atomic tool**
- `isIntegration()` returns `true` for external integrations
- `createTool(string $class, array $context)` factory — instantiate the tool class with proper dependencies. The `$context` array contains `agent` (User), `timezone` (string), and `tool_slug` (string). Use `$context['timezone']` if your tool needs it (see celestial). Do **NOT** pass the agent User model directly to tool constructors.
- **Do NOT pass `User $agent` or any OpenCompany model to tool constructors**

Tool slug convention: `{appname}_{verb}_{noun}` — e.g., `weather_get_forecast`, `weather_list_locations`.

Example `tools()`:
```php
public function tools(): array
{
    return [
        'weather_get_forecast' => [
            'class' => WeatherGetForecast::class,
            'type' => 'read',          // 'read' or 'write'
            'name' => 'Get Forecast',
            'description' => '5-day weather forecast for a location.',
            'icon' => 'ph:cloud-sun',
        ],
        'weather_get_current' => [
            'class' => WeatherGetCurrent::class,
            'type' => 'read',
            'name' => 'Current Weather',
            'description' => 'Current conditions for a location.',
            'icon' => 'ph:thermometer',
        ],
    ];
}
```

### Tool classes — atomic pattern (one class = one operation)
- Implement `Laravel\Ai\Contracts\Tool`
- **One class per operation** — never multiplex with an `action` parameter
- Constructor: inject service dependencies (not User, not app-specific models)
- `description()`: focused text telling the LLM what this single operation does
- `handle(Request $request)`: perform the one action, return string result
- `schema(JsonSchema $schema)`: only the parameters relevant to this operation
- Use `$schema->string()->description('...')->required()` pattern for params

Example atomic tool:
```php
<?php
namespace OpenCompany\AiToolWeather\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolWeather\WeatherService;

class WeatherGetForecast implements Tool
{
    public function __construct(
        private WeatherService $service,
    ) {}

    public function description(): string
    {
        return 'Get a 5-day weather forecast for a location.';
    }

    public function handle(Request $request): string
    {
        try {
            $result = $this->service->getForecast(
                $request['location'],
                $request['units'] ?? 'metric',
            );

            return json_encode($result, JSON_PRETTY_PRINT) ?: '{}';
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'location' => $schema
                ->string()
                ->description('City name or coordinates (e.g. "Amsterdam" or "52.37,4.89").')
                ->required(),
            'units' => $schema
                ->string()
                ->description('Unit system: "metric" (°C) or "imperial" (°F). Default: metric.'),
        ];
    }
}
```

### ServiceProvider
- Bind service classes as singletons
- For tools needing API keys, use `CredentialResolver` in the service binding:
  ```php
  $this->app->singleton(MyService::class, function ($app) {
      $creds = $app->make(\OpenCompany\IntegrationCore\Contracts\CredentialResolver::class);
      return new MyService(
          apiKey: $creds->get('{integration}', 'api_key', ''),
          // ... other config
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

Most external API integrations should also implement `ConfigurableIntegration` alongside `ToolProvider`. This gives you automatic config UI rendering in the Integrations page, a "Test Connection" button, and credential validation on save.

```php
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class WeatherToolProvider implements ToolProvider, ConfigurableIntegration
{
    // ... ToolProvider methods (appName, appMeta, tools, isIntegration, createTool) ...

    public function integrationMeta(): array
    {
        return [
            'name' => 'Weather API',
            'description' => 'Real-time weather data and forecasts',
            'icon' => 'ph:cloud-sun',
            'logo' => 'ph:cloud-sun',
            'category' => 'data',        // productivity, data, communication, development, etc.
            'badge' => 'verified',        // optional
            'docs_url' => 'https://...',  // optional
        ];
    }

    public function configSchema(): array
    {
        return [
            [
                'key' => 'api_key',
                'type' => 'secret',       // secret, text, url, select, string_list, oauth_connect
                'label' => 'API Key',
                'placeholder' => 'wk_...',
                'hint' => 'Get your key at <a href="...">the dashboard</a>.',
                'required' => true,
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $apiKey = $config['api_key'] ?? '';
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'No API key provided.'];
        }
        try {
            // Make a lightweight test API call
            $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->timeout(10)->get('https://api.example.com/ping');
            return $response->successful()
                ? ['success' => true, 'message' => 'Connected successfully.']
                : ['success' => false, 'error' => 'API error: ' . $response->body()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function validationRules(): array
    {
        return ['api_key' => 'nullable|string'];
    }
}
```

Config field types: `secret` (masked input), `text`, `url`, `select` (with `options`), `string_list` (with `item_icon`/`item_placeholder`), `oauth_connect` (with `authorize_url`/`redirect_uri`).

See `tmp/ai-tool-clickup/src/ClickUpToolProvider.php` for a complete real-world example.

### OAuth integrations

For integrations using OAuth instead of API keys (e.g., TickTick, Google):

1. Create an `{Name}OAuthController.php` with `authorize()` and `callback()` methods
2. Register routes in `ServiceProvider::boot()`:
   ```php
   private function registerRoutes(): void
   {
       Route::prefix('api/integrations/{name}/oauth')
           ->middleware(['web', 'auth'])
           ->group(function () {
               Route::get('authorize', [{Name}OAuthController::class, 'authorize']);
               Route::get('callback', [{Name}OAuthController::class, 'callback']);
           });
   }
   ```
3. In `configSchema()`, use the `oauth_connect` field type:
   ```php
   [
       'key' => 'access_token',
       'type' => 'oauth_connect',
       'label' => 'My Service Account',
       'authorize_url' => '/api/integrations/{name}/oauth/authorize',
       'redirect_uri' => '/api/integrations/{name}/oauth/callback',
   ]
   ```

See `tmp/ai-tool-ticktick/` for a complete OAuth example.

### Optional: Supplementary Lua documentation (`ProvidesLuaDocs`)

If the integration has workflows, gotchas, or examples that aren't captured by the auto-generated parameter reference, implement the `ProvidesLuaDocs` interface on the ToolProvider:

```php
use OpenCompany\IntegrationCore\Contracts\ProvidesLuaDocs;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class WeatherToolProvider implements ToolProvider, ProvidesLuaDocs
{
    // ... ToolProvider methods ...

    public function luaDocsPath(): string
    {
        return __DIR__ . '/../docs/lua-docs.md';
    }
}
```

Then create `docs/lua-docs.md` in the package root with supplementary documentation. This content is appended when an agent reads the integration's namespace docs via `lua_read_doc`. Use it for:
- Common workflows and multi-step examples
- Integration-specific gotchas or limitations
- Rate limiting notes
- Authentication context

### README.md
- Title, tagline, OpenCompany brand paragraph
- Available tools table (list each atomic tool slug)
- Installation: `composer require opencompanyapp/ai-tool-$ARGUMENTS`
- Quick start with Laravel AI SDK
- Dependencies table
- MIT license

## After creating the package

1. Add to `composer.json` repositories:
   ```json
   {"type": "path", "url": "tmp/ai-tool-$ARGUMENTS"}
   ```
2. Add to require: `"opencompanyapp/ai-tool-$ARGUMENTS": "@dev"`
3. If extracting from existing app code:
   - Remove static entries from `app/Agents/Tools/ToolRegistry.php` (TOOL_MAP, APP_GROUPS, INTEGRATION_APPS, APP_ICONS, INTEGRATION_LOGOS, instantiateTool match arms, use imports)
   - Delete the old source files from `app/`
4. Run `composer update`
5. Verify with `php artisan tinker`:
   ```php
   $registry = app(\OpenCompany\IntegrationCore\Support\ToolProviderRegistry::class);
   $registry->has('{name}'); // should be true
   ```

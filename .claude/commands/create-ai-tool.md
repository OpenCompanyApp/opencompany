---
description: Scaffold a new AI tool package for the OpenCompany ecosystem
argument-hint: <tool-name>
---

# Create AI Tool Package

Create a new AI tool package called `ai-tool-$ARGUMENTS` in the `tmp/` directory, following the established OpenCompany AI tool package pattern.

## Before you start

Read the reference implementation to understand the exact patterns:

1. Read `tmp/ai-tool-celestial/src/CelestialToolProvider.php` — ToolProvider contract implementation
2. Read `tmp/ai-tool-celestial/src/AiToolCelestialServiceProvider.php` — ServiceProvider pattern
3. Read `tmp/ai-tool-celestial/src/Tools/QueryCelestial.php` — Tool class implementing Laravel AI SDK Tool interface
4. Read `tmp/ai-tool-celestial/composer.json` — Package dependencies
5. Read `tmp/ai-tool-core/src/Contracts/ToolProvider.php` — The contract to implement
6. Read `tmp/ai-tool-core/src/Contracts/CredentialResolver.php` — For tools needing API keys

## Package structure to create

```
tmp/ai-tool-$ARGUMENTS/
├── composer.json
├── LICENSE          (MIT, copy from tmp/ai-tool-celestial/LICENSE)
├── README.md
└── src/
    ├── AiTool{Name}ServiceProvider.php
    ├── {Name}ToolProvider.php
    ├── {Name}Service.php          (if the tool has a service layer)
    └── Tools/
        └── {ToolName}.php         (one or more Tool classes)
```

## Requirements for each file

### composer.json
- Name: `opencompanyapp/ai-tool-$ARGUMENTS`
- Namespace: `OpenCompany\AiTool{Name}\`
- Require: `php ^8.2`, `opencompanyapp/ai-tool-core ^1.0 || @dev`, `laravel/ai ^0.1`
- Laravel auto-discovery for the ServiceProvider
- Add any integration-specific PHP dependencies

### ToolProvider
- Implement `OpenCompany\AiToolCore\Contracts\ToolProvider`
- `appName()` returns the slug (e.g., `'weather'`)
- `appMeta()` returns label, description, icon (use `ph:` prefix for Phosphor icons), logo
- `tools()` returns slug => metadata array for each tool
- `isIntegration()` returns `true` for external integrations
- `createTool()` factory — use `match` on class, instantiate with proper dependencies
- **Do NOT pass `User $agent` or any OpenCompany model to tool constructors**

### Tool classes
- Implement `Laravel\Ai\Contracts\Tool`
- Constructor: inject service dependencies (not User, not app-specific models)
- `description()`: markdown text telling the LLM what the tool does
- `handle(Request $request)`: perform the action, return string result
- `schema(JsonSchema $schema)`: define parameters using the JsonSchema builder
- Use `$schema->string()->description('...')->required()` pattern for params

### ServiceProvider
- Bind service classes as singletons
- For tools needing API keys, use `CredentialResolver` in the service binding:
  ```php
  $this->app->singleton(MyService::class, function ($app) {
      $creds = $app->make(\OpenCompany\AiToolCore\Contracts\CredentialResolver::class);
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

### README.md
- Title, tagline, OpenCompany brand paragraph
- Available actions/tools table
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
   $registry = app(\OpenCompany\AiToolCore\Support\ToolProviderRegistry::class);
   $registry->has('{name}'); // should be true
   ```

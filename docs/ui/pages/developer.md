# Developer Tools

> Workspace developer surfaces for the permission-visible Code Mode API and
> the capability-empty QuickJS console.

## Routes and access

| Page | Route | Name | Purpose |
|---|---|---|---|
| Tool Catalog | `/w/{workspace}/developer/tools` | `developer.tools` | Built-in, integration, MCP, and Code Mode reference |
| Code Console | `/w/{workspace}/developer/code-console` | `developer.code-console` | Monaco-based JavaScript validation and sandbox execution |

Both pages require `auth`, `verified`, and `resolve.workspace`. Frontend links
use generated Wayfinder helpers rather than string-built URLs.

## Tool Catalog

The catalog loads from `GET /api/tools/catalog`. It contains permission-visible
namespaces, exact `app.*` function signatures, effect type, parameter schemas,
return metadata, and package-owned supplementary script docs. Search covers
tool names, descriptions, slugs, and JavaScript function names. Disabled
integrations remain inspectable only when the user explicitly shows all.

## Code Console

The console posts `{ code, mode }` to `POST /api/code/execute`:

- `validate` compiles without executing code or exposing `app.*` capabilities;
- `execute` uses the fixed `console` resource profile, which has no host
  capabilities, filesystem, network, process, modules, environment, or job loop;
- `Cmd/Ctrl+Enter` runs the current JavaScript;
- `console.log/info/warn/error`, `print`, and `dump` write bounded output;
- structured errors include type, user-source line/column, repair guidance,
  retryability, and write-effect status;
- successful execution can show both console output and a JSON-compatible
  top-level return value.

`sessionStorage["code-console-code"]` transfers source from a task trace into
the console once. Resource metrics and the execution ID are visible in the
output pane and status bar.

## Agent experience invariants

- The catalog and generated docs are derived from the same runtime tool map.
- Unknown functions suggest real, permission-visible `app.*` paths.
- Argument mistakes fail before provider dispatch.
- Failed writes surface `effectStatus=unknown` and are not blindly retryable.
- Rich trace metadata is transported out-of-band; model-visible output never
  contains hidden HTML or delimiter markers.
- Provider errors are redacted before entering agent output, durable traces, or
  the developer UI.

## Main files

| File | Purpose |
|---|---|
| `resources/js/Pages/Developer/Tools.vue` | Tool and Code Mode catalog |
| `resources/js/Pages/Developer/CodeConsole.vue` | QuickJS console page |
| `resources/js/Components/developer/ConsoleOutput.vue` | Structured result/error renderer |
| `resources/js/composables/useCodeCompletions.ts` | Catalog-driven `app.*` completions |
| `app/Http/Controllers/Api/ToolCatalogController.php` | Catalog API |
| `app/Http/Controllers/Api/CodeConsoleController.php` | Capability-empty execution API |
| `app/Services/QuickJsSandboxService.php` | Native sandbox boundary and profiles |
| `app/Services/CodeBridge.php` | Budgeted, effect-aware host bridge |

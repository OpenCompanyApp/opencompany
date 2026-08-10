# Integration Package Refactor Plan

Status: Historical / mostly implemented. The current app consumes framework-agnostic `opencompanyapp/integration-*` packages through `opencompanyapp/integration-core`, `ToolProviderRegistry`, `CodeBridge`, `config/integration_catalog.php`, and `app/Agents/Tools/ToolRegistry.php`. Remaining phase notes are useful as refactor rationale, not a literal current file inventory.

Current code check, 2026-05-24:

- `../integrations/core/src/Contracts/Tool.php` is framework-agnostic and exposes `name()`, `description()`, `parameters()`, and `execute(array $args): ToolResult`.
- `../integrations/core/src/Contracts/ToolProvider.php` now includes `scriptDocsPath()` and `credentialFields()` directly.
- `../integrations/core/src/Support/ToolProviderRegistry.php` is a small registry; the app still has a local `app/Agents/Tools/ToolRegistry.php` because built-in OpenCompany tools and Laravel AI agent wiring remain app-owned.
- Built-in OpenCompany tool groups are split across `app/Agents/Tools/Providers/*ToolProvider.php`; the current direct AI-callable groups include `tasks`, `system`, `agents`, `memory`, `code`, and `web`.
- Packages have been renamed to `opencompanyapp/integration-*`; `composer.lock` currently installs 585 integration packages.

## Context

The AI tool packages were originally built around `Laravel\Ai\Contracts\Tool` — each tool exposed `description()`, `schema(JsonSchema)`, and `handle(Request)` for direct LLM function calling. The later Code Mode architecture calls package tools through a language-neutral `ScriptBridge`, making that LLM-oriented package interface unnecessary overhead.

Additionally, KosmoKrator (CLI agent) needs to share the same tool ecosystem but cannot depend on `laravel/ai`. The packages must become framework-agnostic.

### Historical Pain Points At Time Of Writing

The items below describe the baseline this plan was written against. They are
not all current defects in the May 2026 codebase.

1. **`integration-core` depended on `laravel/ai`** for the `Tool` interface. Every tool package transitively depended on `laravel/ai`. KosmoKrator could not use them.
2. **225+ tool classes** implemented `Laravel\Ai\Contracts\Tool` with `schema(JsonSchema)` and `handle(Request)` even though they were never direct LLM tools — they were called through the script bridge.
3. **`ToolRegistry` was a 1965-line monolith** mixing tool metadata (`TOOL_MAP`), instantiation (180-line `match`), permissions, catalog generation, and app group config.
4. **Two registration paths**: external packages self-register via `ToolProviderRegistry`, built-in tools are hardcoded in `TOOL_MAP` + the giant `match`.
5. Supplementary script documentation was optional and effectively absent despite Code Mode being the primary package surface.
6. **No multi-account support** in `ToolProvider` or `CredentialResolver` — needed for KosmoKrator's `app.gmail.work.*` / `app.gmail.personal.*` pattern.
7. **Package naming** (`ai-tool-*`) reflected Era 1 thinking.

---

## Phase 1: New Tool Contract in `integration-core`

**Goal**: `integration-core` owns its own `Tool` interface. Drop the `laravel/ai` dependency.

### New Contracts

```php
// integration-core/src/Contracts/Tool.php
interface Tool
{
    public function name(): string;
    public function description(): string;
    public function parameters(): array;
    public function execute(array $args): ToolResult;
}
```

`parameters()` returns a plain array — what `CodeApiDocGenerator` actually needs:

```php
public function parameters(): array
{
    return [
        'to'      => ['type' => 'string', 'required' => true,  'description' => 'Recipient email'],
        'subject' => ['type' => 'string', 'required' => true,  'description' => 'Email subject'],
        'body'    => ['type' => 'string', 'required' => false, 'description' => 'Email body'],
    ];
}
```

No `JsonSchema` factory, no `Request` wrapper.

### ToolResult Value Object

```php
// integration-core/src/Support/ToolResult.php
class ToolResult
{
    public function __construct(
        public readonly mixed $data,
        public readonly ?string $error = null,
        public readonly array $meta = [],  // attachments, files created, etc.
    ) {}
}
```

Replaces returning raw strings. Both platforms can inspect structured results.

### ToolProvider Changes

Add `luaDocsPath()` directly (replacing the optional `ProvidesLuaDocs` interface) and `credentialFields()` for setup flows:

```php
interface ToolProvider
{
    public function appName(): string;
    public function appMeta(): array;
    public function tools(): array;
    public function isIntegration(): bool;
    public function createTool(string $class, array $context = []): Tool;
    public function luaDocsPath(): ?string;       // NEW — null = auto-generated only
    public function credentialFields(): array;     // NEW — for setup flows
}
```

### CredentialResolver — Account-Scoped

```php
interface CredentialResolver
{
    public function get(string $provider, string $account, string $key): ?string;
}
```

In OpenCompany: `IntegrationSettingCredentialResolver` resolves from DB.
In KosmoKrator: `YamlCredentialResolver` reads from `~/.kosmokrator/integrations.yaml`.

---

## Phase 2: Bridge Package `integration-laravel-ai`

**Goal**: OpenCompany keeps working during migration. Thin adapter from `integration-core` Tool to `Laravel\Ai\Contracts\Tool`.

```php
// integration-laravel-ai/src/LaravelAiToolAdapter.php
class LaravelAiToolAdapter implements \Laravel\Ai\Contracts\Tool
{
    public function __construct(
        private \OpenCompany\IntegrationCore\Contracts\Tool $tool,
    ) {}

    public function description(): string
    {
        return $this->tool->description();
    }

    public function schema(JsonSchema $schema): array
    {
        return ParameterSchemaConverter::toJsonSchema($this->tool->parameters(), $schema);
    }

    public function handle(Request $request): string
    {
        $result = $this->tool->execute($request->all());
        return $result->error ?? json_encode($result->data);
    }
}
```

`ParameterSchemaConverter` maps the plain parameter arrays to `JsonSchema` calls:

```php
class ParameterSchemaConverter
{
    public static function toJsonSchema(array $parameters, JsonSchema $schema): array
    {
        $result = [];
        foreach ($parameters as $name => $def) {
            $type = $schema->{$def['type'] ?? 'string'}();
            if (!empty($def['description'])) $type = $type->description($def['description']);
            if (!empty($def['required'])) $type = $type->required();
            if (!empty($def['enum'])) $type = $type->enum($def['enum']);
            $result[$name] = $type;
        }
        return $result;
    }
}
```

OpenCompany's `ToolRegistry` wraps tools through this adapter when handing them to laravel/ai's agent loop. KosmoKrator calls `execute()` directly.

---

## Phase 3: Migrate Tool Packages to New Contract

**Goal**: Each `ai-tool-*` package implements the framework-agnostic `Tool` contract.

### Before (coupled to laravel/ai)

```php
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class RenderMermaid implements Tool {
    public function description(): string { ... }
    public function handle(Request $request): string { ... }
    public function schema(JsonSchema $schema): array { ... }
}
```

### After (framework-agnostic)

```php
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;

class RenderMermaid implements Tool {
    public function name(): string { return 'render_mermaid'; }
    public function description(): string { ... }
    public function parameters(): array {
        return [
            'syntax' => ['type' => 'string', 'required' => true,  'description' => 'Mermaid diagram syntax...'],
            'title'  => ['type' => 'string', 'required' => false, 'description' => 'Diagram title (default: "Diagram")'],
            'width'  => ['type' => 'integer', 'required' => false, 'description' => 'Output width in pixels (default: 1400)'],
            'theme'  => ['type' => 'string', 'required' => false, 'description' => 'Theme', 'enum' => ['default', 'dark', 'forest', 'neutral']],
        ];
    }
    public function execute(array $args): ToolResult { ... }
}
```

Tool name moves into the tool itself (was only in `ToolProvider::tools()` key). `handle(Request)` becomes `execute(array)`. JsonSchema ceremony disappears.

Migrate one package at a time. Order by simplicity:
1. `ai-tool-mermaid` (1 tool — proof of concept)
2. `ai-tool-plantuml`, `ai-tool-typst`, `ai-tool-vegalite` (1 tool each)
3. `ai-tool-exchangerate`, `ai-tool-trustmrr`, `ai-tool-celestial`, `ai-tool-worldbank`, `ai-tool-coingecko` (data packages)
4. `ai-tool-plausible`, `ai-tool-ticktick` (integrations with credentials)
5. `ai-tool-clickup` (17 tools)
6. `ai-tool-google` (10+ sub-providers, largest package)

---

## Phase 4: Built-In ToolProviders, Shrink ToolRegistry

**Goal**: Built-in tools use the same `ToolProvider` pattern as external packages. Eliminate `TOOL_MAP` and the 180-line `match` statement.

### New Provider Classes

```
app/Agents/Tools/Providers/
├── ChatToolProvider.php        (14 tools)
├── DocsToolProvider.php        (14 tools)
├── FilesToolProvider.php       (10 tools)
├── TablesToolProvider.php      (20 tools)
├── CalendarToolProvider.php    (7 tools)
├── ListsToolProvider.php       (21 tools)
├── WorkspaceToolProvider.php   (27 tools)
├── AutomationsToolProvider.php (6 tools)
├── SvgToolProvider.php         (1 tool)
```

Each provider:
- Declares tools via `tools()` (eliminates `TOOL_MAP`)
- Handles instantiation in `createTool()` (eliminates the `match` statement)
- Provides `appMeta()` (eliminates `APP_GROUPS` entries for that section)

The direct tool groups (`tasks`, `system`, `agents`, `memory`, `code`, and `web`) can also become providers or stay in ToolRegistry since they are core agent machinery.

Register in `AppServiceProvider`:

```php
$registry = $this->app->make(ToolProviderRegistry::class);
$registry->register(new ChatToolProvider($this->app));
$registry->register(new DocsToolProvider($this->app));
// ...
```

### ToolRegistry After Refactor (~300 lines)

```php
class ToolRegistry
{
    public const DIRECT_TOOL_GROUPS = ['tasks', 'system', 'agents', 'memory', 'code', 'web'];

    public function getToolsForAgent(User $agent): array { /* iterate registry, filter, wrap */ }
    public function getAppCatalog(User $agent): string { /* build system prompt */ }
    public function getAllToolsMeta(User $agent): array { /* for frontend */ }
    public function instantiateToolBySlug(string $slug, User $agent): ?Tool { /* delegate to provider */ }
}
```

No more `TOOL_MAP`. No more `APP_GROUPS`. No more `APP_ICONS`. No more `INTEGRATION_LOGOS`. No more 180-line `match`. All derived from `ToolProviderRegistry`.

---

## Phase 5: Multi-Account CredentialResolver

**Goal**: Support `app.gmail.work.*` / `app.gmail.personal.*` pattern for KosmoKrator.

### Context Array Extension

The `createTool()` `$context` array already exists. Add account scoping:

```php
$provider->createTool(GmailSendMessage::class, [
    'agent' => $agent,
    'account' => 'work',        // NEW
    'timezone' => 'UTC',
]);
```

### CredentialResolver

```php
// OpenCompany: resolves from IntegrationSetting table
class IntegrationSettingCredentialResolver implements CredentialResolver
{
    public function get(string $provider, string $account, string $key): ?string
    {
        return IntegrationSetting::where('integration_id', $provider)
            ->where('account', $account)
            ->value("config->{$key}");
    }
}

// KosmoKrator: resolves from YAML config
class YamlCredentialResolver implements CredentialResolver
{
    public function get(string $provider, string $account, string $key): ?string
    {
        return $this->config[$provider][$account]['credentials'][$key] ?? null;
    }
}
```

### Code Mode namespace

The `CodeBridge` registers functions per account:

```javascript
app.gmail.work.send_message({ to: "cto@company.com" });
app.gmail.personal.list_messages({ query: "is:unread" });
```

OpenCompany initially uses a single implicit `default` account (backward compatible). Multi-account is opt-in.

---

## Phase 6: Script docs in every package

**Goal**: Every tool package ships a `script-docs/` directory with real JavaScript examples and common patterns.

Add to every package:

```
ai-tool-mermaid/
├── script-docs/
│   └── mermaid.md          # examples, tips, common patterns
├── src/
│   ├── MermaidToolProvider.php  → scriptDocsPath() returns __DIR__.'/../script-docs/mermaid.md'
│   └── Tools/RenderMermaid.php
```

Example content:

```markdown
## Common Patterns

### Flowchart from data
\```javascript
const items = app.tables.get_table_rows({ table_id: "..." });
const lines = ["graph TD"];
for (const item of items.rows) {
  lines.push(`    ${item.from} --> ${item.to}`);
}
app.mermaid.render_mermaid({ syntax: lines.join("\n") });
\```
```

`CodeApiDocGenerator` consumes package `scriptDocsPath()` content alongside generated signatures.

---

## Phase 7: Rename `ai-tool-*` to `integration-*`

**Goal**: Package naming reflects what they are — integrations, not AI tools.

```
opencompanyapp/ai-tool-mermaid    → opencompanyapp/integration-mermaid
opencompanyapp/ai-tool-google     → opencompanyapp/integration-google
opencompanyapp/ai-tool-clickup    → opencompanyapp/integration-clickup
...
```

Use Composer `replace` in the new package to smooth the transition:

```json
{
    "name": "opencompanyapp/integration-mermaid",
    "replace": {
        "opencompanyapp/ai-tool-mermaid": "self.version"
    }
}
```

Do this **after** the contract changes (phases 1-4) so each package is only touched once.

---

## Sequencing Summary

| Phase | What | Why This Order |
|-------|------|----------------|
| **1** | New `Tool` contract in `integration-core` | Unblocks everything — KosmoKrator can't exist without this |
| **2** | `integration-laravel-ai` bridge package | OpenCompany keeps working during migration |
| **3** | Migrate tool packages to new contract | Each package becomes framework-agnostic |
| **4** | Built-in `ToolProvider` implementations | Eliminates ToolRegistry monolith |
| **5** | Multi-account `CredentialResolver` | Signatures only — `?string $account` on `CredentialResolver`, `credentialFields()` on `ToolProvider`. Full implementation deferred to KosmoKrator (OpenCompany uses workspace scoping instead). |
| **6** | Script docs in every package | Agent quality improvement |
| **7** | Rename `ai-tool-*` → `integration-*` | Cosmetic, do last when stable |

Phases 1-3 are the critical path for KosmoKrator. Phase 4 is the biggest maintenance win for OpenCompany. Phases 5-7 can happen in parallel with KosmoKrator development.

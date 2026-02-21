# Extract `laravel-mcp-client` Package

## Context

The app has a working MCP (Model Context Protocol) client in `app/Services/Mcp/` (~504 LOC, 5 files) that bridges remote MCP servers into the Laravel AI tool ecosystem. MCP is the emerging standard for AI tool integrations (Anthropic, GitHub, Slack, etc.). No Laravel package exists for this. Extracting it lets any Laravel AI app connect to MCP servers as native tools.

---

## Package Structure

```
tmp/laravel-mcp-client/
├── composer.json
├── LICENSE
├── README.md
└── src/
    ├── Contracts/
    │   └── McpServer.php              # Interface for server config
    ├── Data/
    │   └── McpServerData.php          # Plain DTO implementing McpServer
    ├── Concerns/
    │   └── ActsAsMcpServer.php        # Eloquent trait implementing McpServer
    ├── McpClient.php                  # JSON-RPC 2.0 HTTP client
    ├── McpSchemaTranslator.php        # MCP JSON Schema → Laravel JsonSchema
    ├── McpProxyTool.php               # Laravel AI Tool wrapping an MCP tool
    ├── McpToolProvider.php            # ToolProvider for a single MCP server
    └── McpServerRegistrar.php         # Registers multiple servers into registry
```

Namespace: `OpenCompany\McpClient`

---

## Key Design: Decoupling from McpServer Model

The current code accesses `App\Models\McpServer` (Eloquent) directly. The package replaces this with:

### 1. `Contracts\McpServer` interface

Defines what the package needs from any server representation:

```php
interface McpServer
{
    public function mcpUrl(): string;
    public function mcpAuthHeaders(): array;    // ['Authorization' => 'Bearer ...']
    public function mcpTimeout(): int;
    public function mcpSlug(): string;
    public function mcpName(): string;
    public function mcpDescription(): ?string;
    public function mcpIcon(): ?string;
    public function mcpDiscoveredTools(): ?array;  // [{name, description, inputSchema}, ...]
}
```

Methods prefixed with `mcp` to avoid collisions with Eloquent model attributes (`getName()`, `getSlug()` etc.).

### 2. `Concerns\ActsAsMcpServer` trait

Drop-in for Eloquent models. Maps standard column names to the interface:

```php
trait ActsAsMcpServer
{
    public function mcpUrl(): string { return $this->url; }
    public function mcpTimeout(): int { return $this->timeout ?? 30; }
    public function mcpSlug(): string { return $this->slug; }
    public function mcpName(): string { return $this->name; }
    public function mcpDescription(): ?string { return $this->description; }
    public function mcpIcon(): ?string { return $this->icon; }
    public function mcpDiscoveredTools(): ?array { return $this->discovered_tools; }

    // Auth header builder (moved from model — generic bearer/header logic)
    public function mcpAuthHeaders(): array
    {
        $config = $this->auth_config ?? [];
        return match ($this->auth_type ?? null) {
            'bearer' => ['Authorization' => 'Bearer ' . ($config['token'] ?? '')],
            'header' => [($config['header_name'] ?? 'Authorization') => ($config['header_value'] ?? '')],
            default => [],
        };
    }
}
```

### 3. `Data\McpServerData` DTO

For non-Eloquent usage (testing, config-based servers):

```php
class McpServerData implements McpServer
{
    public function __construct(
        public readonly string $url,
        public readonly string $slug,
        public readonly string $name,
        public readonly array $authHeaders = [],
        public readonly int $timeout = 30,
        public readonly ?string $description = null,
        public readonly ?string $icon = null,
        public readonly ?array $discoveredTools = null,
    ) {}
    // implements all McpServer methods
}
```

---

## File-by-File Changes

### Package files (create in `tmp/laravel-mcp-client/src/`)

| File | Source | Changes |
|------|--------|---------|
| `McpClient.php` | `app/Services/Mcp/McpClient.php` | Remove `use App\Models\McpServer` import. Change `fromServer(McpServer)` to accept `Contracts\McpServer`. Use `->mcpUrl()`, `->mcpAuthHeaders()`, `->mcpTimeout()`. Change hardcoded `clientInfo.name` from `'opencompany'` to `'laravel-mcp-client'`. Make protocol version a class constant. |
| `McpSchemaTranslator.php` | `app/Services/Mcp/McpSchemaTranslator.php` | Namespace change only. Already fully standalone. |
| `McpProxyTool.php` | `app/Services/Mcp/McpProxyTool.php` | Constructor: `McpServer $server` → `Contracts\McpServer $server`. Property access: `$this->server->slug` → `$this->server->mcpSlug()`, `$this->server->name` → `$this->server->mcpName()`. |
| `McpToolProvider.php` | `app/Services/Mcp/McpToolProvider.php` | Constructor: `McpServer $server` → `Contracts\McpServer $server`. All property access via interface methods. |
| `McpServerRegistrar.php` | `app/Services/Mcp/McpServerRegistrar.php` | Remove Eloquent query. Accept `iterable $servers` parameter instead. Host app provides the query result. |

### Host app changes (after package installed)

| File | Change |
|------|--------|
| `app/Models/McpServer.php` | Add `implements \OpenCompany\McpClient\Contracts\McpServer`, add `use \OpenCompany\McpClient\Concerns\ActsAsMcpServer`. Remove `getAuthHeaders()` (now in trait). Keep `getToolSlugs()`, `isToolDiscoveryStale()`, `getMaskedAuthValue()` (app-specific). |
| `app/Services/Mcp/` | Delete entire directory. |
| `app/Providers/AppServiceProvider.php` | Update `McpServerRegistrar` import to package namespace. Pass query result: `McpServerRegistrar::registerAll($registry, McpServer::where('enabled', true)->whereNotNull('discovered_tools')->get())` |
| `app/Http/Controllers/Api/McpServerController.php` | Update imports from `App\Services\Mcp\*` → `OpenCompany\McpClient\*` |
| `app/Jobs/RefreshMcpToolsJob.php` | Update `McpClient` import |
| `app/Agents/Tools/Workspace/ManageMcpServer.php` | Update imports |

---

## Dependencies

```json
{
    "require": {
        "php": "^8.2",
        "laravel/ai": "^0.1",
        "opencompanyapp/integration-core": "^1.0 || @dev",
        "illuminate/support": "^11.0 || ^12.0",
        "illuminate/contracts": "^11.0 || ^12.0"
    }
}
```

---

## Verification

1. `php -l` all package files for syntax
2. `composer update` in root project to install from path repo
3. Navigate to MCP server settings in the app — verify existing servers still load
4. Test MCP tool discovery (initialize + listTools) on an existing server
5. Test MCP tool execution through the AI agent
6. Run `php artisan test` for any existing MCP-related tests

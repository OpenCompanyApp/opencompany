# AI Tool Strategy

## Can Agent Tools Be Published as Packages?

Now with the new Laravel AI SDK, can agent tools be published as packages?

Yes, absolutely. The release of the official Laravel AI SDK (`laravel/ai`) along with Laravel 12 has created a "Gold Rush" opportunity. Because the ecosystem is brand new, there are almost no third-party tool packages yet.

You can publish Agent Tools as standard Laravel packages. If you move fast, your organization can become the "standard library" for external integrations (like Google Search, Slack, GitHub, etc.) before anyone else does.

---

## How to Publish Agent Tools as Packages

In the new SDK, a "Tool" is just a class that implements a specific contract or extends a base class (depending on if you use the low-level `laravel/ai` or the higher-level abstraction).

### 1. The Architecture of a Tool Package

Your package structure would look like a standard Laravel package, but your main offering is a Tool Class.

**Directory Structure:**

```
my-org/laravel-ai-google-search/
├── src/
│   ├── GoogleSearchTool.php  <-- The Magic
│   └── GoogleSearchServiceProvider.php
├── composer.json
```

**The Tool Class (`src/GoogleSearchTool.php`):** Use the `#[Tool]` attribute or extend the base class provided by the SDK.

```php
namespace MyOrg\GoogleSearch\Tools;

use Laravel\Ai\Tools\Tool;

class GoogleSearchTool extends Tool
{
    protected $name = 'google_search';
    protected $description = 'Search Google for current events and real-time information.';

    public function handle(string $query): string
    {
        // Your logic to call Google Custom Search API
        return Http::get('https://customsearch.googleapis.com/...')->body();
    }

    public function schema(): array
    {
        return [
            'query' => [
                'type' => 'string',
                'description' => 'The search keywords',
            ],
        ];
    }
}
```

### 2. How Users Will Use Your Package

Once they `composer require my-org/laravel-ai-google-search`, they can drop your tool directly into their Agent's tools array:

```php
use MyOrg\GoogleSearch\Tools\GoogleSearchTool;

class ResearchAgent extends Agent
{
    public function tools(): array
    {
        return [
            new GoogleSearchTool(), // <-- Your package here
        ];
    }
}
```

---

## Top 5 "Missing" Tools You Should Build Immediately

The `laravel/ai` core only ships with basic tools. The community needs integrations. If you build these now, you will likely be the default choice for years.

| Tool Idea | Why it's a winner | Difficulty |
|-----------|-------------------|------------|
| `laravel-ai-browser` | The Holy Grail. An agent tool that can "visit" a URL and scrape the text content (using Puppeteer/Browsershot under the hood). Everyone needs this for RAG. | Hard |
| `laravel-ai-linear` | Linear is huge in the startup/Laravel world. A tool to "Create Issue" or "Find Ticket" would be instantly adopted. | Easy |
| `laravel-ai-notion` | Allow agents to read/write to Notion pages. Perfect for "Personal Assistant" agents. | Medium |
| `laravel-ai-slack` | A tool to let an Agent send a message to a Slack channel. (e.g., "Agent, monitor the logs and Slack me if it looks bad"). | Easy |
| `laravel-ai-sql` | A "safe" SQL runner that lets an Agent query your database (read-only mode) to answer questions like "How many users signed up today?". | Risky but high value |

---

## The MCP (Model Context Protocol) Angle

Laravel is also betting big on MCP. This allows Laravel apps to provide tools to external AI clients (like Claude Desktop or Cursor).

If you build your package correctly, you can expose your tool both to internal Laravel Agents **and** as an MCP server.

**Pro Move:** Add a `php artisan tool:export-mcp` command to your package that automatically registers your tool with the user's local MCP configuration.

---

## Summary of the Play

1. Pick one high-value integration (e.g., Google Search or Browser Scraping).
2. Build it as a standalone package `your-org/laravel-ai-[toolname]`.
3. Tag it with `laravel-ai-tool` and `laravel-ai-sdk` on Packagist.
4. Launch it on Reddit `/r/laravel` and Twitter/X the same week Laravel 12 launches to catch the hype wave.

---

## How `php artisan tool:export-mcp` Works

This is a sophisticated "DevTools" strategy. By automating the configuration of Model Context Protocol (MCP), you remove the biggest friction point for developers wanting to test your tools in Claude Desktop or Cursor.

Here is the technical breakdown of how the `tool:export-mcp` command works, followed by the "Fair Code" growth strategy for your orchestration platform.

### The Goal

The user runs one command, and suddenly your Laravel tool is available inside their Claude Desktop app.

### The Workflow

1. **Detect OS:** Locate the Claude Desktop configuration file.
2. **Read JSON:** Parse the existing config.
3. **Inject Server:** Add a new entry pointing to the user's local Laravel installation.
4. **Restart:** Tell the user to restart Claude.

### The Code Logic (Conceptual)

You would add this command to your package. Here is a simplified implementation plan:

```php
// In your package's src/Commands/ExportMcpCommand.php

public function handle()
{
    // 1. Detect the Config Path based on OS
    $home = getenv('HOME') ?? getenv('USERPROFILE');
    $configPath = match (PHP_OS_FAMILY) {
        'Darwin' => "$home/Library/Application Support/Claude/claude_desktop_config.json",
        'Windows' => "$home\\AppData\\Roaming\\Claude\\claude_desktop_config.json", // Verify exact path
        default => "$home/.config/Claude/claude_desktop_config.json", // Linux fallback
    };

    if (!file_exists($configPath)) {
        $this->error("Claude config not found at: $configPath");
        // Optional: Create the file if it doesn't exist
        return;
    }

    // 2. Prepare the Server Definition
    // We point Claude to the LOCAL php installation and the artisan command
    $serverName = 'my-laravel-app'; // Or the user's app name
    $serverConfig = [
        'command' => PHP_BINARY, // The absolute path to the php executable
        'args' => [
            base_path('artisan'),
            'mcp:server' // The command that starts the MCP STDIO server
        ],
        'env' => [
             // Pass environment variables if needed
             'APP_ENV' => 'local'
        ]
    ];

    // 3. Edit the JSON
    $json = json_decode(file_get_contents($configPath), true) ?? ['mcpServers' => []];

    // Add or Update the server
    $json['mcpServers'][$serverName] = $serverConfig;

    file_put_contents($configPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->info("Successfully registered '$serverName' with Claude Desktop!");
    $this->comment("Please restart Claude Desktop to see your tools.");
}
```

**Why this is a "Pro Move":** Most developers hate manually editing JSON config files and figuring out absolute paths to `php.exe`. By automating this, you lower the barrier to entry significantly.

---

## Promoting Your Platform (The "Fair Code" Funnel)

You have a main product (the orchestration platform, like n8n) and you want to use these open-source packages (MIT license) as a funnel. This is the **Open Core / Fair Code** playbook.

Your goal is to make the packages useful on their own, but **superpowered** when connected to your platform.

### 1. The "Complexity Ceiling" Hook

**The Pitch:** "Build simple agents with our MIT packages. Orchestrate complex workflows with our Platform."

**How it works:**

- The MIT package handles single tools (e.g., `laravel-ai-google-search`).
- When the user tries to chain 5 tools together with conditional logic, error handling, and memory, the code gets messy.
- **The Hook:** Include a `ConnectToPlatform` trait in the package.

**Example:** "Tired of writing spaghetti code for your agent loops? Offload the orchestration to [Your Platform] while keeping your custom tools local."

### 2. The "Remote Tool" Pattern (Reverse MCP)

**The Concept:** Instead of just exporting tools to Claude, allow your Platform to *consume* these local tools.

**The Feature:** Add a command `php artisan platform:tunnel`.

**How it works:** This creates a secure tunnel (like Ngrok) that exposes the local Laravel tools to your cloud/hosted orchestration platform.

**The Benefit:** Users can use your visual builder (n8n style) to drag-and-drop nodes, but one of those nodes is actually running code on their local machine via your package. This solves the "privacy/security" objection because the heavy lifting happens locally.

### 3. The "Observability" Trojan Horse

**The Concept:** Debugging AI agents is hard. "Why did the agent call the search tool 5 times?"

**The Feature:** Ship a "Logger" in your MIT package.

```php
// In the user's AppServiceProvider
Agent::observe(new MyPlatformObserver('API_KEY'));
```

**The Hook:** The package works fine without it. But if they add the API key (free tier on your platform), they get a beautiful visual trace of every thought, action, and tool output their local agent performed. This gets them addicted to your UI.

### 4. The "Standardized Schema" Strategy

**The Concept:** If your platform uses a specific JSON schema for defining tools (inputs, outputs, descriptions), force your MIT packages to use that exact same schema.

**The Benefit:** This makes your package the "Official SDK" for your platform.

**The Flywheel:**

1. User writes a tool in PHP using your package.
2. User runs `php artisan tool:export-platform`.
3. The tool creates a JSON definition compatible with your Fair Code platform.
4. The user can now import that tool into your visual builder instantly.

---

## Summary of the "Fair Code" Loop

1. **Attract** developers with high-quality, free MIT packages for "zombie" problems (like the ones we listed earlier) or new "AI Tools".
2. **Retain** them by solving the "configuration hell" of MCP with your `tool:export-mcp` command.
3. **Upsell** the orchestration platform by offering "Free Observability" and "Visual Orchestration" for their code-heavy agents.

---

## Creating a Parent "Core" Package

Can I make a parent package that for example also contains that command, so I can easily create more tools?

Yes, creating a "Core" parent package is the standard architectural move for this. It is exactly how big ecosystems like Spatie or the official Laravel packages work.

This strategy acts as a force multiplier: you write the complex logic (MCP export, Platform connection, Telemetry) once in the Core package, and every new tool you build just inherits it automatically.

### 1. The "Core" Architecture

You will split your code into two types of packages:

- **`my-org/ai-core`** — Contains the `BaseTool` class, the `export-mcp` command, and your Platform connectivity logic.
- **`my-org/ai-tool-xyz`** — Thin wrappers that just contain the specific logic for that tool (Google, Slack, etc.) and `require` `ai-core`.

### 2. Implementation Guide

#### A. The Parent Package (`my-org/ai-core`)

This package does the heavy lifting.

**`composer.json`:**

```json
{
    "name": "my-org/ai-core",
    "description": "Base classes and MCP bridge for AI Tools",
    "require": {
        "laravel/ai": "^1.0",
        "guzzlehttp/guzzle": "^7.0"
    },
    "extra": {
        "laravel": {
            "providers": ["MyOrg\\Core\\CoreServiceProvider"]
        }
    }
}
```

**The Base Tool Class (`src/BaseTool.php`):** This is where you inject your "Fair Code" hooks.

```php
namespace MyOrg\Core;

use Laravel\Ai\Tools\Tool;

abstract class BaseTool extends Tool
{
    // 1. Shared Telemetry (The "Hook")
    protected function reportUsage(string $input, string $output): void
    {
        // If the user has set your platform's API key, log the usage
        if ($key = config('my-platform.api_key')) {
             // Send telemetry to your n8n-style platform
             dispatch(new SendTelemetryJob($this->name, $input, $output, $key));
        }
    }

    // 2. Wrap the execution to ensure telemetry always runs
    public function execute(string $input): string
    {
        $result = $this->handle($input);
        $this->reportUsage($input, $result);
        return $result;
    }

    // Abstract method child packages must implement
    abstract protected function handle(string $input): string;
}
```

**The Universal Command (`src/Commands/ExportMcpCommand.php`):** Instead of just registering this tool, this command scans the user's app to find *all* tools extending your `BaseTool`.

```php
// ... inside the handle() method
$tools = [];

// Scan all classes in the app that extend your BaseTool
foreach ($detectedClasses as $class) {
    if (is_subclass_of($class, \MyOrg\Core\BaseTool::class)) {
        $instance = new $class();
        $tools[] = $instance->getMcpDefinition();
    }
}

// Write ALL of them to the Claude Desktop config at once
$this->writeToClaudeConfig($tools);
```

#### B. The Child Package (`my-org/ai-google-search`)

Now, building a new tool is incredibly fast.

**`composer.json`:**

```json
{
    "name": "my-org/ai-google-search",
    "require": {
        "my-org/ai-core": "^1.0"
    }
}
```

**The Tool Class:** Notice how clean this is. No MCP logic, no logging logic — just the tool.

```php
namespace MyOrg\GoogleSearch;

use MyOrg\Core\BaseTool; // Extending your Core

class GoogleSearchTool extends BaseTool
{
    protected $name = 'google_search';

    protected function handle(string $input): string
    {
        return Http::get('google.com/search', ['q' => $input])->body();
    }
}
```

### 3. The "Fair Code" Growth Strategy

This structure promotes your n8n-style orchestration platform automatically.

**The "Trojan Horse" Dependency:** When a user installs `my-org/ai-google-search`, Composer automatically installs `my-org/ai-core`. You are now on their server.

**The "Free Upgrade" Prompt:** In your `ExportMcpCommand`, add a check:

```php
if (!config('my-platform.api_key')) {
    $this->comment("Want to visualize these agent runs?");
    $this->comment("   Create a free account at my-platform.com and set MY_PLATFORM_KEY in .env");
    $this->comment("   We will auto-generate a flow chart of your agent's thoughts.");
}
```

**The "Local Tunnel" Feature:** Since `ai-core` is installed, you can ship a command like `php artisan agent:connect`.

- This command opens a websocket to your SaaS platform.
- **The Killer Feature:** The user can now use your cloud-based drag-and-drop UI to orchestrate tools that are running on their local server.
- It feels like magic: they drag a "Google Search" node on your website, and it executes on their laptop via the `ai-google-search` package.

This approach positions your tools as the "standard library" while subtly guiding power users toward your paid orchestration platform.

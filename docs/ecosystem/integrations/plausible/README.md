# Integration: Plausible

> Plausible Analytics integration for the [Laravel AI SDK](https://github.com/laravel/ai) — query stats, realtime visitors, manage sites and goals. Part of the [OpenCompany](https://github.com/OpenCompanyApp) integration ecosystem.

Give your AI agents access to privacy-friendly web analytics. Query traffic data, track realtime visitors, and manage sites and conversion goals — all through the [Plausible Analytics](https://plausible.io) API.

## About OpenCompany

[OpenCompany](https://github.com/OpenCompanyApp) is an AI-powered workplace platform where teams deploy and coordinate multiple AI agents alongside human collaborators. It combines team messaging, document collaboration, task management, and intelligent automation in a single workspace — with built-in approval workflows and granular permission controls so organizations can adopt AI agents safely and transparently.

This Plausible tool lets AI agents query website analytics, monitor realtime traffic, and manage tracking configuration — giving agents data-driven awareness of web properties.

OpenCompany is built with Laravel, Vue 3, and Inertia.js. Learn more at [github.com/OpenCompanyApp](https://github.com/OpenCompanyApp).

## Installation

```console
composer require opencompanyapp/integration-plausible
```

Laravel auto-discovers the service provider. No manual registration needed.

## Configuration

This tool requires a Plausible Analytics API key.

**In OpenCompany**, credentials are managed through the Integrations UI.

**For standalone usage**, create `config/ai-tools.php`:

```php
return [
    'plausible' => [
        'api_key' => env('PLAUSIBLE_API_KEY'),
        'url'     => env('PLAUSIBLE_URL', 'https://plausible.io'),
        'sites'   => ['example.com', 'blog.example.com'],
    ],
];
```

## Available Tools

| Tool | Type | Description |
|------|------|-------------|
| `plausible_query_stats` | read | Query website analytics — aggregate, timeseries, breakdowns by dimension |
| `plausible_realtime_visitors` | read | Current realtime visitor count (last 5 minutes) |
| `plausible_list_sites` | read | List all tracked websites |
| `plausible_create_site` | write | Register a new website for tracking |
| `plausible_delete_site` | write | Remove a website from tracking |
| `plausible_list_goals` | read | List conversion goals for a site |
| `plausible_create_goal` | write | Create a conversion goal (page visit or custom event) |
| `plausible_delete_goal` | write | Delete a conversion goal |

## Quick Start

```php
use Laravel\Ai\Facades\Ai;
use OpenCompany\Integrations\Plausible\PlausibleService;
use OpenCompany\Integrations\Plausible\Tools\PlausibleQueryStats;
use OpenCompany\Integrations\Plausible\Tools\PlausibleRealtimeVisitors;

// Create tools
$service = app(PlausibleService::class);
$tools = [
    new PlausibleQueryStats($service),
    new PlausibleRealtimeVisitors($service),
];

// Use with an AI agent
$response = Ai::agent()
    ->tools($tools)
    ->prompt('How many visitors did example.com get this month?');
```

### Via ToolProvider (recommended)

If you have `integration-core` installed, all 8 tools auto-register with the `ToolProviderRegistry`:

```php
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

$registry = app(ToolProviderRegistry::class);
$provider = $registry->get('plausible');

// Create any tool via the provider
$tool = $provider->createTool(
    \OpenCompany\Integrations\Plausible\Tools\PlausibleQueryStats::class
);
```

## Standalone Service Usage

```php
use OpenCompany\Integrations\Plausible\PlausibleService;

$service = app(PlausibleService::class);

// Query stats
$stats = $service->query([
    'site_id' => 'example.com',
    'metrics' => ['visitors', 'pageviews'],
    'date_range' => '30d',
]);

// Realtime visitors
$count = $service->realtimeVisitors('example.com');

// List sites
$sites = $service->listSites();

// Manage goals
$goals = $service->listGoals('example.com');
$service->createGoal('example.com', ['goal_type' => 'event', 'event_name' => 'Signup']);
```

## Dependencies

| Package | Purpose |
|---------|---------|
| [opencompanyapp/integration-core](https://github.com/OpenCompanyApp/integration-core) | ToolProvider contract and registry |
| [laravel/ai](https://github.com/laravel/ai) | Laravel AI SDK Tool contract |

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- [Laravel AI SDK](https://github.com/laravel/ai) ^0.1
- A [Plausible Analytics](https://plausible.io) account with API access

## License

MIT — see [LICENSE](LICENSE)

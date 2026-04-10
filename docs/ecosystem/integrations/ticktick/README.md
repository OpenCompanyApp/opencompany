# Integration: TickTick

> Task management integration for the [Laravel AI SDK](https://github.com/laravel/ai) — manage projects, create tasks, set priorities, track completion. Part of the [OpenCompany](https://github.com/OpenCompanyApp) integration ecosystem.

Give your AI agents the ability to manage TickTick tasks and projects. Supports both direct access token and OAuth authentication, plus the Dida365 variant.

## About OpenCompany

[OpenCompany](https://github.com/OpenCompanyApp) is an AI-powered workplace platform where teams deploy and coordinate multiple AI agents alongside human collaborators. It combines team messaging, document collaboration, task management, and intelligent automation in a single workspace — with built-in approval workflows and granular permission controls so organizations can adopt AI agents safely and transparently.

This TickTick tool lets AI agents manage tasks and projects on behalf of users — creating tasks from conversations, checking project status, completing items, and keeping task lists organized automatically.

OpenCompany is built with Laravel, Vue 3, and Inertia.js. Learn more at [github.com/OpenCompanyApp](https://github.com/OpenCompanyApp).

## Installation

```console
composer require opencompanyapp/integration-ticktick
```

Laravel auto-discovers the service provider. No manual registration needed.

## Available Actions

| Action | Description | Required Params |
|--------|-------------|-----------------|
| `ticktick_list_projects` | List all TickTick projects | — |
| `ticktick_get_project` | Get a project with its tasks and sections | `projectId` |
| `ticktick_create_project` | Create a new project (list) | `name` |
| `ticktick_delete_project` | Delete a project | `projectId` |
| `ticktick_get_tasks` | Get all tasks in a project | `projectId` |
| `ticktick_create_task` | Create a new task | `title`, `projectId` |
| `ticktick_update_task` | Update an existing task | `taskId`, `projectId` |
| `ticktick_complete_task` | Mark a task as complete | `taskId`, `projectId` |
| `ticktick_delete_task` | Delete a task | `taskId`, `projectId` |

## Authentication

Two authentication methods are supported — select your preferred method in the integration settings:

### Access Token (recommended for quick setup)

1. Go to [developer.ticktick.com/manage](https://developer.ticktick.com/manage)
2. Generate an access token
3. Paste it in the integration config

### OAuth (Client ID + Secret)

1. Register an app at the TickTick Developer Center
2. Enter your Client ID and Client Secret in the config
3. Use the OAuth authorize flow to connect

## Quick Start: Use with Laravel AI SDK

```php
use Laravel\Ai\Facades\Ai;
use OpenCompany\Integrations\TickTick\Tools\TickTickListProjects;
use OpenCompany\Integrations\TickTick\TickTickService;

// Create the tool
$tool = new TickTickListProjects(
    service: app(TickTickService::class),
);

// Use with an AI agent
$response = Ai::agent()
    ->tools([$tool])
    ->prompt('What projects do I have in TickTick?');
```

### Via ToolProvider (recommended)

If you have `integration-core` installed, the tool auto-registers with the `ToolProviderRegistry`:

```php
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

$registry = app(ToolProviderRegistry::class);
$provider = $registry->get('ticktick');

// Create a tool
$tool = $provider->createTool(
    \OpenCompany\Integrations\TickTick\Tools\TickTickCreateTask::class,
);
```

## Dida365 Support

TickTick operates as Dida365 in China. To use this integration with Dida365, change the API Base URL in settings to `https://api.dida365.com`.

## Dependencies

| Package | Purpose |
|---------|---------|
| [opencompanyapp/integration-core](https://github.com/OpenCompanyApp/integration-core) | ToolProvider contract and registry |
| [laravel/ai](https://github.com/laravel/ai) | Laravel AI SDK Tool contract |

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- [Laravel AI SDK](https://github.com/laravel/ai) ^0.1

## License

MIT — see [LICENSE](LICENSE)

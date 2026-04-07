# Integration: Mermaid

> Mermaid diagram rendering integration for the [Laravel AI SDK](https://github.com/laravel/ai). Part of the [OpenCompany](https://github.com/OpenCompanyApp) integration ecosystem.

Generates PNG images from Mermaid diagram syntax. Supports flowcharts, sequence diagrams, class diagrams, state diagrams, ER diagrams, Gantt charts, pie charts, git graphs, and more.

## About OpenCompany

[OpenCompany](https://github.com/OpenCompanyApp) is an AI-powered workplace platform where teams deploy and coordinate multiple AI agents alongside human collaborators. It combines team messaging, document collaboration, task management, and intelligent automation in a single workspace — with built-in approval workflows and granular permission controls so organizations can adopt AI agents safely and transparently.

OpenCompany is built with Laravel, Vue 3, and Inertia.js. Learn more at [github.com/OpenCompanyApp](https://github.com/OpenCompanyApp).

## Prerequisites

Requires the [Mermaid CLI](https://github.com/mermaid-js/mermaid-cli) (`mmdc`) to be installed:

```bash
npm install @mermaid-js/mermaid-cli
```

## Installation

```console
composer require opencompanyapp/integration-mermaid
```

Laravel auto-discovers the service provider. No manual registration needed.

## Available Tools

| Tool | Type | Description |
|------|------|-------------|
| `render_mermaid` | write | Render Mermaid diagram syntax to a PNG image |

## Quick Start

```php
use Laravel\Ai\Facades\Ai;
use OpenCompany\Integrations\Mermaid\Tools\RenderMermaid;
use OpenCompany\Integrations\Mermaid\MermaidService;

$tool = new RenderMermaid(app(MermaidService::class));

$response = Ai::agent()
    ->tools([$tool])
    ->prompt('Create a flowchart showing the user registration process');
```

## Dependencies

| Package | Purpose |
|---------|---------|
| `opencompanyapp/integration-core` | ToolProvider contract and registry |
| `laravel/ai` | Laravel AI SDK Tool interface |
| `@mermaid-js/mermaid-cli` | Mermaid to PNG rendering (npm) |

## License

MIT — see [LICENSE](LICENSE)

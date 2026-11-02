# Integration: TrustMRR

> **Direction status (2026-11-02):** Superseded by [Product Direction](../../../PRODUCT_DIRECTION.md), and by [Connections, Events and Routines](../../../planning/connections-events-routines.md) — the integration package system is deleted and the integrations repository archived; replaced by deep connectors plus generic MCP, API manifests and skills (pillar 9).

Verified startup revenue data for the [OpenCompany](https://github.com/OpenCompanyApp) integration ecosystem. Browse startups, filter by revenue, MRR, asking price, growth, and more - all backed by real payment provider data.

The current TrustMRR API documents two endpoints, and this package covers both:

- `GET /api/v1/startups`
- `GET /api/v1/startups/{slug}`

## Available Tools

| Slug | Type | Description |
|------|------|-------------|
| `trustmrr_list_startups` | read | Browse and filter startups by revenue, MRR, growth, category, and sale status. |
| `trustmrr_get_startup` | read | Get full details for a startup including tech stack, cofounders, and extended metrics. |

## Installation

```bash
composer require opencompanyapp/integration-trustmrr
```

The service provider is auto-discovered by Laravel.

## Configuration

Add your TrustMRR API key via the Integrations settings page, or configure it directly:

| Key | Description |
|-----|-------------|
| `api_key` | TrustMRR API key (starts with `tmrr_`). Generate at [TrustMRR Developer Dashboard](https://trustmrr.com/dashboard/developer). |

## Quick Start

```php
use OpenCompany\Integrations\TrustMrr\TrustMrrService;

$service = app(TrustMrrService::class);

// List top startups by revenue
$startups = $service->listStartups(['sort' => 'revenue-desc', 'limit' => 10]);

// Get details for a specific startup
$startup = $service->getStartup('shipfast');
```

## Dependencies

| Package | Version |
|---------|---------|
| PHP | ^8.2 |
| opencompanyapp/integration-core | ^2.0 |

## License

MIT

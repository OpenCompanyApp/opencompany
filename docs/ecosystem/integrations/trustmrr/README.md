# Integration: TrustMRR

Verified startup revenue data for the [OpenCompany](https://github.com/OpenCompanyApp) integration ecosystem. Browse startups, filter by revenue, MRR, asking price, growth, and more — all backed by real payment provider data.

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
| laravel/ai | ^0.1 |

## License

MIT

# Integration: Celestial

> Astronomy integration for the [Laravel AI SDK](https://github.com/laravel/ai) — moon phases, sunrise/sunset, planet positions, eclipses, night sky reports. Part of the [OpenCompany](https://github.com/OpenCompanyApp) integration ecosystem.

Give your AI agents the ability to perform real-time astronomical calculations. Built on [astronomy-bundle-php](https://github.com/OpenCompanyApp/astronomy-bundle-php) (Jean Meeus' *Astronomical Algorithms*) and the [Integration Core](https://github.com/OpenCompanyApp/integration-core) framework.

## About OpenCompany

[OpenCompany](https://github.com/OpenCompanyApp) is an AI-powered workplace platform where teams deploy and coordinate multiple AI agents alongside human collaborators. It combines team messaging, document collaboration, task management, and intelligent automation in a single workspace — with built-in approval workflows and granular permission controls so organizations can adopt AI agents safely and transparently.

This celestial tool is one example of how AI agents can be extended with specialized capabilities beyond standard LLM knowledge — giving agents accurate, real-time astronomical data instead of relying on training data that may be outdated or imprecise.

OpenCompany is built with Laravel, Vue 3, and Inertia.js. Learn more at [github.com/OpenCompanyApp](https://github.com/OpenCompanyApp).

## Installation

```console
composer require opencompanyapp/integration-celestial
```

Laravel auto-discovers the service provider. No manual registration needed.

## Available Actions

| Action | Description | Required Params |
|--------|-------------|-----------------|
| `moon_phase` | Phase, illumination, age, zodiac sign, next new/full moon | — |
| `sun_info` | Sunrise/sunset, altitude/azimuth, twilight, day length, zodiac | `latitude`, `longitude` |
| `moon_info` | Moon position, illumination, visibility from a location | `latitude`, `longitude` |
| `planet_position` | Planet altitude/azimuth, zodiac, rise/set. Use `planet="all"` for overview | `latitude`, `longitude` |
| `solar_eclipse` | Eclipse type, obscuration, contacts, magnitude for a date + location | `date`, `latitude`, `longitude` |
| `lunar_eclipse` | Eclipse type, magnitude, gamma, contact times (P1-P4, U1-U4) | `date` |
| `night_sky` | What's visible now: sun/moon/planet positions, darkness, stargazing quality | `latitude`, `longitude` |
| `zodiac_report` | All celestial bodies mapped to zodiac signs with alignments | — |
| `time_info` | Julian Day, sidereal time (GMST/GAST), equation of time | — |

All actions accept optional `date` (ISO format, defaults to now) and `timezone` (defaults to UTC or configured default).

## Quick Start: Use with Laravel AI SDK

```php
use Laravel\Ai\Facades\Ai;
use OpenCompany\Integrations\Celestial\Tools\QueryCelestial;
use OpenCompany\Integrations\Celestial\CelestialService;

// Create the tool
$tool = new QueryCelestial(
    service: app(CelestialService::class),
    defaultTimezone: 'Europe/Amsterdam',
);

// Use with an AI agent
$response = Ai::agent()
    ->tools([$tool])
    ->prompt('What phase is the moon in right now?');
```

### Via ToolProvider (recommended)

If you have `integration-core` installed, the tool auto-registers with the `ToolProviderRegistry`:

```php
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

$registry = app(ToolProviderRegistry::class);
$provider = $registry->get('celestial');

// Create tool with context
$tool = $provider->createTool(
    \OpenCompany\Integrations\Celestial\Tools\QueryCelestial::class,
    ['timezone' => 'America/New_York']
);
```

## Standalone Service Usage

You can use `CelestialService` directly without the integration wrapper:

```php
use OpenCompany\Integrations\Celestial\CelestialService;

$service = app(CelestialService::class);

// Moon phase
echo $service->moonPhase(null, 'Europe/Amsterdam');

// Sunrise/sunset in Amsterdam
echo $service->sunInfo('2025-06-21', 52.3676, 4.9041, 'Europe/Amsterdam');

// All planets visible from Berlin
echo $service->planetPosition('all', null, 52.524, 13.411, 'Europe/Berlin');

// Night sky report
echo $service->nightSky(52.3676, 4.9041, 'Europe/Amsterdam');

// Solar eclipse check
echo $service->solarEclipse('2026-08-12', 40.7128, -74.0060);

// Lunar eclipse check
echo $service->lunarEclipse('2025-09-07');
```

## Dependencies

| Package | Purpose |
|---------|---------|
| [opencompanyapp/integration-core](https://github.com/OpenCompanyApp/integration-core) | ToolProvider contract and registry |
| [opencompanyapp/astronomy-bundle](https://github.com/OpenCompanyApp/astronomy-bundle-php) | Astronomical calculation engine (Meeus algorithms, VSOP87) |
| [laravel/ai](https://github.com/laravel/ai) | Laravel AI SDK Tool contract |

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- [Laravel AI SDK](https://github.com/laravel/ai) ^0.1

## License

MIT — see [LICENSE](LICENSE)

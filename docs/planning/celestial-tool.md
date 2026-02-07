# Plan: Add "celestial" agent tool for astronomical calculations

## Context

An advanced agent tool called **celestial** for rich astronomical calculations — moon phases, sun/moon positions, planet positions, eclipses, zodiac, night sky reports, and more. Optional integration (not enabled by default), following the same pattern as `jpgraph_charts`.

**Library**: [`OpenCompanyApp/astronomy-bundle-php`](https://github.com/OpenCompanyApp/astronomy-bundle-php) — our PHP 8.5-compatible fork of `andrmoel/astronomy-bundle-php`. Comprehensive astronomy library based on Jean Meeus' "Astronomical Algorithms" and VSOP87 theory.

Capabilities:
- Sun/Moon/Planet position calculations (ecliptical, equatorial, horizontal coordinates)
- Sun: sunrise/sunset/culmination, twilight, distance
- Moon: illumination, waxing/waning, position angle of bright limb, distance
- All 7 planets: Mercury through Neptune with full coordinate support
- Solar eclipses: obscuration, contact times, magnitude, duration
- Time: Julian Day, sidereal time, equation of time
- Rise/Set/Transit via `RiseSetTransit` class for any object
- Coordinate transformations with atmospheric refraction

## Architecture

### Service: `app/Services/Celestial/CelestialService.php`

Rich service class encapsulating all astronomy-bundle calls. Each method returns a formatted markdown string for agent consumption.

**Methods:**

#### `moonPhase(string $date = null, string $timezone = 'UTC')`
- Phase name (New Moon, Waxing Crescent, First Quarter, Waxing Gibbous, Full Moon, Waning Gibbous, Last Quarter, Waning Crescent)
- Illumination percentage, waxing/waning, moon age in days
- Position angle of bright limb, distance to Earth (km)
- Zodiac sign (from ecliptical longitude / 30)
- Ecliptical coordinates, next approximate new/full moon

#### `sunInfo(string $date = null, float $lat, float $lon, string $timezone = 'UTC')`
- Sunrise, sunset, solar noon (culmination) in local timezone
- Day length, current altitude & azimuth (with refraction)
- Twilight phase (day, civil, nautical, astronomical, night)
- Distance to Earth (km and AU), zodiac sign
- Ecliptical & equatorial coordinates, equation of time

#### `moonInfo(string $date = null, float $lat, float $lon, string $timezone = 'UTC')`
- Phase name + illumination, altitude & azimuth
- Above/below horizon, distance to Earth (km)
- Zodiac sign, ecliptical & equatorial coordinates (RA, Dec)
- Position angle of bright limb, waxing/waning

#### `planetPosition(string $planet, string $date = null, float $lat, float $lon, string $timezone = 'UTC')`
- Supports: mercury, venus, mars, jupiter, saturn, uranus, neptune, or "all"
- Per planet: altitude, azimuth, above/below horizon, distance (km)
- Zodiac sign, ecliptical coords, equatorial coords (RA, Dec)
- "all" mode: table of all 7 planets with visibility summary

#### `solarEclipse(string $date, float $lat, float $lon)`
- Obscuration %, eclipse type, contact times (C1-C4)
- Maximum eclipse time, duration, magnitude, moon-to-sun ratio

#### `nightSky(float $lat, float $lon, string $timezone = 'UTC')`
- "What's in the sky right now?" comprehensive report
- Sun: position, horizon status, twilight phase
- Moon: phase, illumination, position, horizon status
- All planets: which are above horizon with altitude/azimuth
- Darkness assessment, moon interference rating, visibility summary

#### `zodiacReport(string $date = null)`
- All bodies: Sun, Moon, Mercury–Neptune
- Ecliptical longitude and zodiac sign for each
- Notable alignments (bodies in same sign)

#### `timeInfo(string $date = null)`
- Julian Day, Julian centuries from J2000
- GMST, GAST, equation of time

### Tool: `app/Agents/Tools/Celestial/QueryCelestial.php`

Single tool with action-based routing. Schema:
- `action` (required) — `moon_phase`, `sun_info`, `moon_info`, `planet_position`, `solar_eclipse`, `night_sky`, `zodiac_report`, `time_info`
- `date` (optional) — ISO date/datetime string
- `latitude` / `longitude` (optional) — observer location
- `planet` (optional) — planet name or "all"
- `timezone` (optional) — defaults to org timezone

### Registration in `ToolRegistry.php`

- APP_GROUPS entry as `celestial`
- Added to INTEGRATION_APPS (optional, not enabled by default)
- APP_ICONS / INTEGRATION_LOGOS: `ph:moon-stars`
- TOOL_MAP: `query_celestial` (read type)
- Added to displayOrder in integrations section

## Files

| File | Action |
|------|--------|
| `app/Services/Celestial/CelestialService.php` | Create |
| `app/Agents/Tools/Celestial/QueryCelestial.php` | Create |
| `app/Agents/Tools/ToolRegistry.php` | Modify |

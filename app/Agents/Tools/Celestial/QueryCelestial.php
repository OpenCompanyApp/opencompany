<?php

namespace App\Agents\Tools\Celestial;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\Celestial\CelestialService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class QueryCelestial implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return <<<'DESC'
Perform astronomical calculations and get celestial data.

**Actions:**

- **moon_phase** — Current moon phase, illumination, age, zodiac sign, next new/full moon
- **sun_info** — Sunrise/sunset, altitude/azimuth, twilight phase, day length, zodiac (requires latitude/longitude)
- **moon_info** — Moon position, illumination, visibility from a location (requires latitude/longitude)
- **planet_position** — Planet altitude/azimuth, zodiac, rise/set times. Set planet="all" for overview (requires latitude/longitude)
- **solar_eclipse** — Eclipse type, obscuration, contacts, magnitude for a date+location (requires date, latitude/longitude)
- **lunar_eclipse** — Eclipse type, magnitude, gamma, contact times (P1-P4, U1-U4), semi-durations (requires date)
- **night_sky** — What's visible now: sun/moon/planet positions, darkness, stargazing quality (requires latitude/longitude)
- **zodiac_report** — All celestial bodies mapped to zodiac signs with alignments
- **time_info** — Julian Day, sidereal time (GMST/GAST), equation of time

Most actions accept an optional `date` (ISO format, defaults to now) and `timezone` (defaults to org timezone).
DESC;
    }

    public function handle(Request $request): string
    {
        $action = $request['action'] ?? '';
        $date = $request['date'] ?? null;
        $lat = isset($request['latitude']) ? (float) $request['latitude'] : null;
        $lon = isset($request['longitude']) ? (float) $request['longitude'] : null;
        $timezone = $request['timezone'] ?? AppSetting::getValue('org_timezone', 'UTC');
        $planet = $request['planet'] ?? 'all';

        $service = app(CelestialService::class);

        try {
            return match ($action) {
                'moon_phase' => $service->moonPhase($date, $timezone),
                'sun_info' => $service->sunInfo($date, $lat ?? 0, $lon ?? 0, $timezone),
                'moon_info' => $service->moonInfo($date, $lat ?? 0, $lon ?? 0, $timezone),
                'planet_position' => $service->planetPosition($planet, $date, $lat ?? 0, $lon ?? 0, $timezone),
                'solar_eclipse' => $service->solarEclipse($date ?? date('Y-m-d'), $lat ?? 0, $lon ?? 0),
                'lunar_eclipse' => $service->lunarEclipse($date ?? date('Y-m-d')),
                'night_sky' => $service->nightSky($lat ?? 0, $lon ?? 0, $timezone),
                'zodiac_report' => $service->zodiacReport($date),
                'time_info' => $service->timeInfo($date),
                default => "Unknown action: '{$action}'. Use: moon_phase, sun_info, moon_info, planet_position, solar_eclipse, lunar_eclipse, night_sky, zodiac_report, time_info.",
            };
        } catch (\Throwable $e) {
            return "Celestial calculation error: {$e->getMessage()}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("Action: 'moon_phase', 'sun_info', 'moon_info', 'planet_position', 'solar_eclipse', 'lunar_eclipse', 'night_sky', 'zodiac_report', 'time_info'.")
                ->required(),
            'date' => $schema
                ->string()
                ->description("ISO date or datetime (e.g. '2024-06-15' or '2024-06-15 22:00:00'). Defaults to now."),
            'latitude' => $schema
                ->number()
                ->description("Observer latitude (-90 to 90). Required for sun_info, moon_info, planet_position, solar_eclipse, night_sky."),
            'longitude' => $schema
                ->number()
                ->description("Observer longitude (-180 to 180). Required for sun_info, moon_info, planet_position, solar_eclipse, night_sky."),
            'planet' => $schema
                ->string()
                ->description("Planet name for planet_position: 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', or 'all' (default)."),
            'timezone' => $schema
                ->string()
                ->description("Timezone for display (e.g. 'Europe/Amsterdam'). Defaults to org timezone."),
        ];
    }
}

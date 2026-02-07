<?php

namespace App\Services\Celestial;

use OpenCompany\AstronomyBundle\AstronomicalObjects\Moon;
use OpenCompany\AstronomyBundle\AstronomicalObjects\Planets\Jupiter;
use OpenCompany\AstronomyBundle\AstronomicalObjects\Planets\Mars;
use OpenCompany\AstronomyBundle\AstronomicalObjects\Planets\Mercury;
use OpenCompany\AstronomyBundle\AstronomicalObjects\Planets\Neptune;
use OpenCompany\AstronomyBundle\AstronomicalObjects\Planets\Saturn;
use OpenCompany\AstronomyBundle\AstronomicalObjects\Planets\Uranus;
use OpenCompany\AstronomyBundle\AstronomicalObjects\Planets\Venus;
use OpenCompany\AstronomyBundle\AstronomicalObjects\Sun;
use OpenCompany\AstronomyBundle\Calculations\EarthCalc;
use OpenCompany\AstronomyBundle\Calculations\SunCalc;
use OpenCompany\AstronomyBundle\Events\LunarEclipse\LunarEclipse;
use OpenCompany\AstronomyBundle\Events\SolarEclipse\SolarEclipse;
use OpenCompany\AstronomyBundle\Location;
use OpenCompany\AstronomyBundle\TimeOfInterest;
use OpenCompany\AstronomyBundle\Utils\AngleUtil;

class CelestialService
{
    private const ZODIAC_SIGNS = [
        ['Aries', "\u{2648}"],
        ['Taurus', "\u{2649}"],
        ['Gemini', "\u{264A}"],
        ['Cancer', "\u{264B}"],
        ['Leo', "\u{264C}"],
        ['Virgo', "\u{264D}"],
        ['Libra', "\u{264E}"],
        ['Scorpio', "\u{264F}"],
        ['Sagittarius', "\u{2650}"],
        ['Capricorn', "\u{2651}"],
        ['Aquarius', "\u{2652}"],
        ['Pisces', "\u{2653}"],
    ];

    private const PLANET_CLASSES = [
        'mercury' => Mercury::class,
        'venus' => Venus::class,
        'mars' => Mars::class,
        'jupiter' => Jupiter::class,
        'saturn' => Saturn::class,
        'uranus' => Uranus::class,
        'neptune' => Neptune::class,
    ];

    /**
     * Moon phase report.
     */
    public function moonPhase(?string $date = null, string $timezone = 'UTC'): string
    {
        $toi = $this->createToi($date);
        $moon = Moon::create($toi);

        $illumination = $moon->getIlluminatedFraction();
        $isWaxing = $moon->isWaxingMoon();
        $phaseName = $this->getPhaseName($illumination, $isWaxing);
        $illuminationPct = round($illumination * 100, 1);
        $posAngle = round($moon->getPositionAngleOfMoonsBrightLimb(), 1);
        $distance = round($moon->getDistanceToEarth());

        $eclCoords = $moon->getGeocentricEclipticalSphericalCoordinates();
        $eclLon = $eclCoords->getLongitude();
        $eclLat = $eclCoords->getLatitude();
        $zodiac = $this->getZodiacSign($eclLon);

        $eqCoords = $moon->getGeocentricEquatorialSphericalCoordinates();
        $ra = AngleUtil::dec2time($eqCoords->getRightAscension());
        $dec = round($eqCoords->getDeclination(), 4);

        // Estimate moon age (synodic month = 29.53059 days)
        $moonAge = $this->estimateMoonAge($illumination, $isWaxing);

        // Estimate next new/full moon
        $synodicMonth = 29.53059;
        $daysToNewMoon = $isWaxing ? ($synodicMonth - $moonAge) + ($synodicMonth / 2) : $synodicMonth - $moonAge;
        if ($daysToNewMoon > $synodicMonth) {
            $daysToNewMoon -= $synodicMonth;
        }
        $daysToFullMoon = $isWaxing ? ($synodicMonth / 2) - $moonAge : $synodicMonth - $moonAge - ($synodicMonth / 2);
        if ($daysToFullMoon < 0) {
            $daysToFullMoon += $synodicMonth;
        }

        $now = $date ? new \DateTime($date, new \DateTimeZone($timezone)) : new \DateTime('now', new \DateTimeZone($timezone));
        $nextNew = (clone $now)->modify('+' . (int) round($daysToNewMoon) . ' days')->format('Y-m-d');
        $nextFull = (clone $now)->modify('+' . (int) round($daysToFullMoon) . ' days')->format('Y-m-d');

        $lines = [
            "## Moon Phase",
            "",
            "**{$phaseName}**",
            "",
            "| Property | Value |",
            "|----------|-------|",
            "| Phase | {$phaseName} |",
            "| Illumination | {$illuminationPct}% |",
            "| Direction | " . ($isWaxing ? 'Waxing' : 'Waning') . " |",
            "| Moon age | ~" . round($moonAge, 1) . " days |",
            "| Distance | " . number_format($distance) . " km |",
            "| Zodiac | {$zodiac} |",
            "| Bright limb angle | {$posAngle}° |",
            "| Ecliptic longitude | " . round($eclLon, 2) . "° |",
            "| Ecliptic latitude | " . round($eclLat, 2) . "° |",
            "| Right ascension | {$ra} |",
            "| Declination | {$dec}° |",
            "",
            "**Upcoming:**",
            "- Next New Moon: ~{$nextNew}",
            "- Next Full Moon: ~{$nextFull}",
        ];

        return implode("\n", $lines);
    }

    /**
     * Sun information for a location.
     */
    public function sunInfo(?string $date = null, float $lat = 0, float $lon = 0, string $timezone = 'UTC'): string
    {
        $toi = $this->createToi($date);
        $sun = Sun::create($toi);
        $location = Location::create($lat, $lon);

        // Coordinates
        $localCoords = $sun->getLocalHorizontalCoordinates($location);
        $altitude = round($localCoords->getAltitude(), 2);
        $azimuth = round(AngleUtil::normalizeAngle($localCoords->getAzimuth()), 2);

        $eclCoords = $sun->getGeocentricEclipticalSphericalCoordinates();
        $eclLon = $eclCoords->getLongitude();
        $zodiac = $this->getZodiacSign($eclLon);

        $eqCoords = $sun->getGeocentricEquatorialSphericalCoordinates();
        $ra = AngleUtil::dec2time($eqCoords->getRightAscension());
        $dec = round($eqCoords->getDeclination(), 4);

        $distance = round($sun->getDistanceToEarth());
        $T = $toi->getJulianCenturiesFromJ2000();
        $distanceAu = round(SunCalc::getRadiusVector($T), 6);

        // Equation of time
        $eot = $toi->getEquationOfTime();
        $eotTime = AngleUtil::dec2time(abs($eot));
        $eotSign = $eot >= 0 ? '+' : '-';

        // Sunrise/sunset
        $tz = new \DateTimeZone($timezone);
        try {
            $sunrise = $sun->getSunrise($location);
            $sunriseLocal = $sunrise->getDateTime()->setTimezone($tz)->format('H:i:s');
        } catch (\Throwable $e) {
            $sunriseLocal = 'N/A (polar)';
        }

        try {
            $sunset = $sun->getSunset($location);
            $sunsetLocal = $sunset->getDateTime()->setTimezone($tz)->format('H:i:s');
        } catch (\Throwable $e) {
            $sunsetLocal = 'N/A (polar)';
        }

        try {
            $culmination = $sun->getUpperCulmination($location);
            $noonLocal = $culmination->getDateTime()->setTimezone($tz)->format('H:i:s');
        } catch (\Throwable $e) {
            $noonLocal = 'N/A';
        }

        // Day length
        $dayLength = 'N/A';
        if (isset($sunrise, $sunset)) {
            try {
                $diff = $sunrise->getDateTime()->diff($sunset->getDateTime());
                $dayLength = $diff->format('%H:%I');
            } catch (\Throwable $e) {
            }
        }

        // Twilight phase
        $twilightPhase = $this->getTwilightPhase($altitude);

        $lines = [
            "## Sun Information",
            "",
            "Location: {$lat}°, {$lon}° ({$timezone})",
            "",
            "| Property | Value |",
            "|----------|-------|",
            "| Altitude | {$altitude}° |",
            "| Azimuth | {$azimuth}° |",
            "| Twilight phase | {$twilightPhase} |",
            "| Zodiac | {$zodiac} |",
            "| Distance | " . number_format($distance) . " km ({$distanceAu} AU) |",
            "| Right ascension | {$ra} |",
            "| Declination | {$dec}° |",
            "| Ecliptic longitude | " . round($eclLon, 2) . "° |",
            "| Equation of time | {$eotSign}{$eotTime} |",
            "",
            "**Daily Events ({$timezone}):**",
            "- Sunrise: {$sunriseLocal}",
            "- Solar noon: {$noonLocal}",
            "- Sunset: {$sunsetLocal}",
            "- Day length: {$dayLength}",
        ];

        return implode("\n", $lines);
    }

    /**
     * Moon information for a location.
     */
    public function moonInfo(?string $date = null, float $lat = 0, float $lon = 0, string $timezone = 'UTC'): string
    {
        $toi = $this->createToi($date);
        $moon = Moon::create($toi);
        $location = Location::create($lat, $lon);

        $illumination = $moon->getIlluminatedFraction();
        $isWaxing = $moon->isWaxingMoon();
        $phaseName = $this->getPhaseName($illumination, $isWaxing);
        $illuminationPct = round($illumination * 100, 1);

        $localCoords = $moon->getLocalHorizontalCoordinates($location);
        $altitude = round($localCoords->getAltitude(), 2);
        $azimuth = round(AngleUtil::normalizeAngle($localCoords->getAzimuth()), 2);
        $aboveHorizon = $altitude > 0;

        $distance = round($moon->getDistanceToEarth());
        $posAngle = round($moon->getPositionAngleOfMoonsBrightLimb(), 1);

        $eclCoords = $moon->getGeocentricEclipticalSphericalCoordinates();
        $eclLon = $eclCoords->getLongitude();
        $zodiac = $this->getZodiacSign($eclLon);

        $eqCoords = $moon->getGeocentricEquatorialSphericalCoordinates();
        $ra = AngleUtil::dec2time($eqCoords->getRightAscension());
        $dec = round($eqCoords->getDeclination(), 4);

        $lines = [
            "## Moon Information",
            "",
            "Location: {$lat}°, {$lon}° ({$timezone})",
            "",
            "| Property | Value |",
            "|----------|-------|",
            "| Phase | {$phaseName} |",
            "| Illumination | {$illuminationPct}% |",
            "| Direction | " . ($isWaxing ? 'Waxing' : 'Waning') . " |",
            "| Altitude | {$altitude}° |",
            "| Azimuth | {$azimuth}° |",
            "| Visibility | " . ($aboveHorizon ? 'Above horizon' : 'Below horizon') . " |",
            "| Distance | " . number_format($distance) . " km |",
            "| Zodiac | {$zodiac} |",
            "| Bright limb angle | {$posAngle}° |",
            "| Right ascension | {$ra} |",
            "| Declination | {$dec}° |",
            "| Ecliptic longitude | " . round($eclLon, 2) . "° |",
        ];

        return implode("\n", $lines);
    }

    /**
     * Planet position(s).
     */
    public function planetPosition(string $planet, ?string $date = null, float $lat = 0, float $lon = 0, string $timezone = 'UTC'): string
    {
        $toi = $this->createToi($date);
        $location = Location::create($lat, $lon);
        $planetLower = strtolower(trim($planet));

        if ($planetLower === 'all') {
            return $this->allPlanetsReport($toi, $location, $timezone);
        }

        if (!isset(self::PLANET_CLASSES[$planetLower])) {
            $available = implode(', ', array_keys(self::PLANET_CLASSES));
            return "Unknown planet: '{$planet}'. Available: {$available}, or 'all'.";
        }

        $class = self::PLANET_CLASSES[$planetLower];
        $planetObj = $class::create($toi);
        $name = ucfirst($planetLower);

        return $this->singlePlanetReport($name, $planetObj, $location, $timezone);
    }

    /**
     * Solar eclipse data.
     */
    public function solarEclipse(string $date, float $lat, float $lon): string
    {
        $toi = $this->createToi($date);
        $location = Location::create($lat, $lon);

        try {
            $eclipse = SolarEclipse::create($toi, $location);
        } catch (\Throwable $e) {
            return "No solar eclipse found for {$date} at {$lat}°, {$lon}°. Error: {$e->getMessage()}";
        }

        $type = $eclipse->getEclipseType();
        if ($type === 'none') {
            return "No solar eclipse visible from {$lat}°, {$lon}° on {$date}.";
        }

        $obscuration = round($eclipse->getObscuration() * 100, 1);
        $magnitude = round($eclipse->getMagnitude(), 3);
        $duration = $eclipse->getEclipseDuration();
        $durationStr = gmdate('H:i:s', (int) $duration);
        $moonSunRatio = round($eclipse->getMoonSunRatio(), 3);

        $lines = [
            "## Solar Eclipse",
            "",
            "Location: {$lat}°, {$lon}° | Date: {$date}",
            "",
            "| Property | Value |",
            "|----------|-------|",
            "| Type | " . ucfirst($type) . " |",
            "| Obscuration | {$obscuration}% |",
            "| Magnitude | {$magnitude} |",
            "| Duration | {$durationStr} |",
            "| Moon/Sun ratio | {$moonSunRatio} |",
        ];

        // Totality/annularity duration
        try {
            $umbraDuration = $eclipse->getEclipseUmbraDuration();
            if ($umbraDuration > 0) {
                $lines[] = "| Totality duration | " . gmdate('i:s', (int) $umbraDuration) . " |";
            }
        } catch (\Throwable $e) {
        }

        // Contact times
        $lines[] = "";
        $lines[] = "**Contact Times (UTC):**";

        $contacts = [
            'C1 (start)' => 'getCircumstancesC1',
            'C2 (totality start)' => 'getCircumstancesC2',
            'MAX (maximum)' => 'getCircumstancesMax',
            'C3 (totality end)' => 'getCircumstancesC3',
            'C4 (end)' => 'getCircumstancesC4',
        ];

        foreach ($contacts as $label => $method) {
            try {
                $circ = $eclipse->$method();
                $contactToi = $eclipse->getTimeOfInterest($circ);
                $lines[] = "- {$label}: " . $contactToi->getDateTime()->format('H:i:s') . " UTC";
            } catch (\Throwable $e) {
                // Skip contacts that don't exist (e.g., C2/C3 for partial)
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Lunar eclipse data.
     */
    public function lunarEclipse(string $date): string
    {
        $toi = $this->createToi($date);

        try {
            $eclipse = LunarEclipse::create($toi);
        } catch (\Throwable $e) {
            return "Error computing lunar eclipse for {$date}: {$e->getMessage()}";
        }

        $type = $eclipse->getEclipseType();
        if ($type === 'none') {
            return "No lunar eclipse occurs near {$date}. The nearest full moon to this date does not produce an eclipse (the Moon is too far from the ecliptic plane).";
        }

        $umbralMag = round($eclipse->getUmbralMagnitude(), 4);
        $penumbralMag = round($eclipse->getPenumbralMagnitude(), 4);
        $gamma = round($eclipse->getGamma(), 4);

        $maxToi = $eclipse->getGreatestEclipseTOI();
        $maxTime = $maxToi ? $maxToi->getDateTime()->format('Y-m-d H:i:s') . ' UTC' : 'N/A';

        $lines = [
            "## Lunar Eclipse",
            "",
            "Date: {$date}",
            "",
            "| Property | Value |",
            "|----------|-------|",
            "| Type | " . ucfirst($type) . " |",
            "| Umbral magnitude | {$umbralMag} |",
            "| Penumbral magnitude | {$penumbralMag} |",
            "| Gamma | {$gamma} |",
            "| Greatest eclipse | {$maxTime} |",
        ];

        // Semi-durations
        $sdPen = round($eclipse->getSemiDurationPenumbral(), 1);
        $lines[] = "| Penumbral semi-duration | {$sdPen} min |";

        $sdPartial = $eclipse->getSemiDurationPartial();
        if ($sdPartial !== null) {
            $lines[] = "| Partial semi-duration | " . round($sdPartial, 1) . " min |";
        }

        $sdTotal = $eclipse->getSemiDurationTotal();
        if ($sdTotal !== null) {
            $lines[] = "| Total semi-duration | " . round($sdTotal, 1) . " min |";
        }

        // Contact times
        $lines[] = "";
        $lines[] = "**Contact Times (UTC):**";

        $contacts = [
            'P1 (penumbra start)' => $eclipse->getContactP1(),
            'U1 (umbra start)' => $eclipse->getContactU1(),
            'U2 (totality start)' => $eclipse->getContactU2(),
            'MAX (greatest)' => $maxToi,
            'U3 (totality end)' => $eclipse->getContactU3(),
            'U4 (umbra end)' => $eclipse->getContactU4(),
            'P4 (penumbra end)' => $eclipse->getContactP4(),
        ];

        foreach ($contacts as $label => $contactToi) {
            if ($contactToi) {
                $lines[] = "- {$label}: " . $contactToi->getDateTime()->format('H:i:s') . " UTC";
            }
        }

        $lines[] = "";
        $lines[] = "*Note: Lunar eclipses are visible from anywhere the Moon is above the horizon. Times are the same globally.*";

        return implode("\n", $lines);
    }

    /**
     * Night sky report — what's visible right now.
     */
    public function nightSky(float $lat, float $lon, string $timezone = 'UTC'): string
    {
        $toi = TimeOfInterest::createFromCurrentTime();
        $location = Location::create($lat, $lon);

        // Sun
        $sun = Sun::create($toi);
        $sunCoords = $sun->getLocalHorizontalCoordinates($location);
        $sunAlt = round($sunCoords->getAltitude(), 1);
        $sunAbove = $sunAlt > 0;
        $twilightPhase = $this->getTwilightPhase($sunAlt);

        // Moon
        $moon = Moon::create($toi);
        $moonCoords = $moon->getLocalHorizontalCoordinates($location);
        $moonAlt = round($moonCoords->getAltitude(), 1);
        $moonAbove = $moonAlt > 0;
        $illumination = round($moon->getIlluminatedFraction() * 100, 1);
        $phaseName = $this->getPhaseName($moon->getIlluminatedFraction(), $moon->isWaxingMoon());

        // Planets
        $visiblePlanets = [];
        $belowPlanets = [];

        foreach (self::PLANET_CLASSES as $name => $class) {
            $planetObj = $class::create($toi);
            $pCoords = $planetObj->getLocalHorizontalCoordinates($location);
            $pAlt = round($pCoords->getAltitude(), 1);
            $pAz = round(AngleUtil::normalizeAngle($pCoords->getAzimuth()), 1);

            if ($pAlt > 0) {
                $visiblePlanets[] = ['name' => ucfirst($name), 'alt' => $pAlt, 'az' => $pAz];
            } else {
                $belowPlanets[] = ucfirst($name);
            }
        }

        // Sort visible planets by altitude (highest first)
        usort($visiblePlanets, fn ($a, $b) => $b['alt'] <=> $a['alt']);

        // Stargazing assessment
        $darkness = $this->getDarknessRating($sunAlt);
        $moonInterference = $moonAbove && $illumination > 50 ? 'High' : ($moonAbove && $illumination > 25 ? 'Moderate' : 'Low');
        $stargazingQuality = $this->getStargazingQuality($sunAlt, $moonAbove, $illumination);

        $tz = new \DateTimeZone($timezone);
        $now = (new \DateTime('now', new \DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d H:i:s');

        $lines = [
            "## Night Sky Report",
            "",
            "Location: {$lat}°, {$lon}° | Time: {$now} {$timezone}",
            "",
            "### Conditions",
            "| Property | Value |",
            "|----------|-------|",
            "| Sky phase | {$twilightPhase} |",
            "| Darkness | {$darkness} |",
            "| Moon interference | {$moonInterference} |",
            "| Stargazing quality | {$stargazingQuality} |",
            "",
            "### Sun",
            "- Altitude: {$sunAlt}° — " . ($sunAbove ? 'Above horizon' : 'Below horizon'),
            "",
            "### Moon",
            "- Phase: {$phaseName} ({$illumination}% illuminated)",
            "- Altitude: {$moonAlt}° — " . ($moonAbove ? 'Above horizon' : 'Below horizon'),
        ];

        if (!empty($visiblePlanets)) {
            $lines[] = "";
            $lines[] = "### Visible Planets (" . count($visiblePlanets) . ")";
            $lines[] = "| Planet | Altitude | Azimuth |";
            $lines[] = "|--------|----------|---------|";
            foreach ($visiblePlanets as $vp) {
                $lines[] = "| {$vp['name']} | {$vp['alt']}° | {$vp['az']}° |";
            }
        }

        if (!empty($belowPlanets)) {
            $lines[] = "";
            $lines[] = "**Below horizon:** " . implode(', ', $belowPlanets);
        }

        return implode("\n", $lines);
    }

    /**
     * Zodiac report — where are all bodies.
     */
    public function zodiacReport(?string $date = null): string
    {
        $toi = $this->createToi($date);

        $bodies = [
            'Sun' => fn () => Sun::create($toi)->getGeocentricEclipticalSphericalCoordinates()->getLongitude(),
            'Moon' => fn () => Moon::create($toi)->getGeocentricEclipticalSphericalCoordinates()->getLongitude(),
            'Mercury' => fn () => Mercury::create($toi)->getGeocentricEclipticalSphericalCoordinates()->getLongitude(),
            'Venus' => fn () => Venus::create($toi)->getGeocentricEclipticalSphericalCoordinates()->getLongitude(),
            'Mars' => fn () => Mars::create($toi)->getGeocentricEclipticalSphericalCoordinates()->getLongitude(),
            'Jupiter' => fn () => Jupiter::create($toi)->getGeocentricEclipticalSphericalCoordinates()->getLongitude(),
            'Saturn' => fn () => Saturn::create($toi)->getGeocentricEclipticalSphericalCoordinates()->getLongitude(),
            'Uranus' => fn () => Uranus::create($toi)->getGeocentricEclipticalSphericalCoordinates()->getLongitude(),
            'Neptune' => fn () => Neptune::create($toi)->getGeocentricEclipticalSphericalCoordinates()->getLongitude(),
        ];

        $lines = [
            "## Zodiac Report",
            "",
            "| Body | Ecliptic Lon | Zodiac Sign |",
            "|------|-------------|-------------|",
        ];

        $signGroups = [];

        foreach ($bodies as $name => $getLon) {
            try {
                $lon = $getLon();
                $zodiac = $this->getZodiacSign($lon);
                $signName = self::ZODIAC_SIGNS[(int) floor(AngleUtil::normalizeAngle($lon) / 30)][0];
                $signGroups[$signName][] = $name;
                $lines[] = "| {$name} | " . round($lon, 2) . "° | {$zodiac} |";
            } catch (\Throwable $e) {
                $lines[] = "| {$name} | Error | — |";
            }
        }

        // Notable alignments (multiple bodies in same sign)
        $alignments = array_filter($signGroups, fn ($group) => count($group) > 1);
        if (!empty($alignments)) {
            $lines[] = "";
            $lines[] = "### Alignments";
            foreach ($alignments as $sign => $bodyList) {
                $lines[] = "- **{$sign}**: " . implode(', ', $bodyList);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Astronomical time calculations.
     */
    public function timeInfo(?string $date = null): string
    {
        $toi = $this->createToi($date);

        $jd = round($toi->getJulianDay(), 6);
        $T = round($toi->getJulianCenturiesFromJ2000(), 10);
        $t = round($toi->getJulianMillenniaFromJ2000(), 10);

        $gmst = $toi->getGreenwichMeanSiderealTime();
        $gmstTime = AngleUtil::dec2time($gmst);
        $gast = $toi->getGreenwichApparentSiderealTime();
        $gastTime = AngleUtil::dec2time($gast);
        $eot = $toi->getEquationOfTime();
        $eotTime = AngleUtil::dec2time(abs($eot));
        $eotSign = $eot >= 0 ? '+' : '-';

        $lines = [
            "## Astronomical Time",
            "",
            "Date: {$toi} UTC",
            "",
            "| Property | Value |",
            "|----------|-------|",
            "| Julian Day | {$jd} |",
            "| Julian Centuries (J2000) | {$T} |",
            "| Julian Millennia (J2000) | {$t} |",
            "| GMST | {$gmstTime} ({$gmst}°) |",
            "| GAST | {$gastTime} ({$gast}°) |",
            "| Equation of Time | {$eotSign}{$eotTime} |",
        ];

        return implode("\n", $lines);
    }

    // ─── Private helpers ────────────────────────────────────────────────

    private function createToi(?string $date = null): TimeOfInterest
    {
        if ($date === null) {
            return TimeOfInterest::createFromCurrentTime();
        }

        return TimeOfInterest::createFromString($date);
    }

    private function getZodiacSign(float $eclipticLongitude): string
    {
        $normalizedLon = AngleUtil::normalizeAngle($eclipticLongitude);
        $index = (int) floor($normalizedLon / 30);
        $index = max(0, min(11, $index));
        [$name, $symbol] = self::ZODIAC_SIGNS[$index];
        $degree = round(fmod($normalizedLon, 30), 1);

        return "{$symbol} {$name} ({$degree}°)";
    }

    private function getPhaseName(float $illumination, bool $isWaxing): string
    {
        $pct = $illumination * 100;

        if ($pct < 1) {
            return 'New Moon';
        }
        if ($pct < 25) {
            return $isWaxing ? 'Waxing Crescent' : 'Waning Crescent';
        }
        if ($pct < 55) {
            return $isWaxing ? 'First Quarter' : 'Last Quarter';
        }
        if ($pct < 95) {
            return $isWaxing ? 'Waxing Gibbous' : 'Waning Gibbous';
        }

        return 'Full Moon';
    }

    private function estimateMoonAge(float $illumination, bool $isWaxing): float
    {
        $synodicMonth = 29.53059;
        $halfMonth = $synodicMonth / 2;

        // illumination goes 0 → 1 (new → full) then 1 → 0 (full → new)
        // Approximate: fraction through half-cycle based on illumination
        // illumination ≈ (1 - cos(π * age / halfMonth)) / 2
        $fraction = acos(1 - 2 * $illumination) / M_PI;

        if ($isWaxing) {
            return $fraction * $halfMonth;
        }

        return $halfMonth + (1 - $fraction) * $halfMonth;
    }

    private function getTwilightPhase(float $sunAltitude): string
    {
        if ($sunAltitude > 0) {
            return 'Day';
        }
        if ($sunAltitude > -6) {
            return 'Civil twilight';
        }
        if ($sunAltitude > -12) {
            return 'Nautical twilight';
        }
        if ($sunAltitude > -18) {
            return 'Astronomical twilight';
        }

        return 'Night';
    }

    private function getDarknessRating(float $sunAltitude): string
    {
        if ($sunAltitude > 0) {
            return 'Daylight';
        }
        if ($sunAltitude > -6) {
            return 'Bright — sun just below horizon';
        }
        if ($sunAltitude > -12) {
            return 'Moderate — horizon still visible';
        }
        if ($sunAltitude > -18) {
            return 'Dark — faint glow on horizon';
        }

        return 'Full darkness';
    }

    private function getStargazingQuality(float $sunAlt, bool $moonAbove, float $moonIllumination): string
    {
        if ($sunAlt > -6) {
            return 'Poor — too bright';
        }
        if ($sunAlt > -12) {
            return 'Fair — twilight';
        }

        if ($moonAbove && $moonIllumination > 75) {
            return 'Fair — bright moon';
        }
        if ($moonAbove && $moonIllumination > 40) {
            return 'Good — moderate moon';
        }

        return 'Excellent';
    }

    private function singlePlanetReport(string $name, $planetObj, Location $location, string $timezone): string
    {
        $localCoords = $planetObj->getLocalHorizontalCoordinates($location);
        $altitude = round($localCoords->getAltitude(), 2);
        $azimuth = round(AngleUtil::normalizeAngle($localCoords->getAzimuth()), 2);
        $aboveHorizon = $altitude > 0;

        $eclCoords = $planetObj->getGeocentricEclipticalSphericalCoordinates();
        $eclLon = $eclCoords->getLongitude();
        $zodiac = $this->getZodiacSign($eclLon);

        $eqCoords = $planetObj->getGeocentricEquatorialSphericalCoordinates();
        $ra = AngleUtil::dec2time($eqCoords->getRightAscension());
        $dec = round($eqCoords->getDeclination(), 4);

        // Rise/set
        $riseStr = 'N/A';
        $setStr = 'N/A';
        $tz = new \DateTimeZone($timezone);
        try {
            $rise = $planetObj->getRise($location);
            if ($rise) {
                $riseStr = $rise->getDateTime()->setTimezone($tz)->format('H:i:s');
            }
        } catch (\Throwable $e) {
        }
        try {
            $set = $planetObj->getSet($location);
            if ($set) {
                $setStr = $set->getDateTime()->setTimezone($tz)->format('H:i:s');
            }
        } catch (\Throwable $e) {
        }

        $lines = [
            "## {$name}",
            "",
            "| Property | Value |",
            "|----------|-------|",
            "| Altitude | {$altitude}° |",
            "| Azimuth | {$azimuth}° |",
            "| Visibility | " . ($aboveHorizon ? 'Above horizon' : 'Below horizon') . " |",
            "| Zodiac | {$zodiac} |",
            "| Right ascension | {$ra} |",
            "| Declination | {$dec}° |",
            "| Ecliptic longitude | " . round($eclLon, 2) . "° |",
            "| Rise ({$timezone}) | {$riseStr} |",
            "| Set ({$timezone}) | {$setStr} |",
        ];

        return implode("\n", $lines);
    }

    private function allPlanetsReport(TimeOfInterest $toi, Location $location, string $timezone): string
    {
        $lines = [
            "## All Planets",
            "",
            "| Planet | Alt | Az | Zodiac | Visible |",
            "|--------|-----|-----|--------|---------|",
        ];

        $visible = 0;

        foreach (self::PLANET_CLASSES as $name => $class) {
            $planetObj = $class::create($toi);
            $localCoords = $planetObj->getLocalHorizontalCoordinates($location);
            $alt = round($localCoords->getAltitude(), 1);
            $az = round(AngleUtil::normalizeAngle($localCoords->getAzimuth()), 1);
            $aboveHorizon = $alt > 0;

            $eclCoords = $planetObj->getGeocentricEclipticalSphericalCoordinates();
            $zodiac = $this->getZodiacSign($eclCoords->getLongitude());

            if ($aboveHorizon) {
                $visible++;
            }

            $lines[] = "| " . ucfirst($name) . " | {$alt}° | {$az}° | {$zodiac} | " . ($aboveHorizon ? 'Yes' : 'No') . " |";
        }

        $lines[] = "";
        $lines[] = "**{$visible} of 7 planets above horizon.**";

        return implode("\n", $lines);
    }
}

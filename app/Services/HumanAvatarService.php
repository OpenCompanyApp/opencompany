<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class HumanAvatarService
{
    private const SIZE = 128;

    /**
     * Background gradient palettes — warm, light tones.
     */
    private const BG_PALETTES = [
        ['#fef3c7', '#fde68a'], // warm amber
        ['#fce7f3', '#fbcfe8'], // soft rose
        ['#ecfccb', '#d9f99d'], // sage/lime
        ['#fee2e2', '#fecaca'], // warm blush
        ['#e0f2fe', '#bae6fd'], // sky blue
        ['#f3e8ff', '#e9d5ff'], // lavender
        ['#ffedd5', '#fed7aa'], // peach
        ['#d1fae5', '#a7f3d0'], // mint
        ['#fef9c3', '#fde047'], // sunshine
        ['#ffe4e6', '#fda4af'], // coral pink
    ];

    /**
     * Accent colors — warm earthy tones for the bokeh circles.
     */
    private const ACCENT_COLORS = [
        '#d97706', // amber
        '#b45309', // deep amber
        '#dc2626', // warm red
        '#be185d', // rose
        '#9333ea', // purple
        '#4f46e5', // indigo
        '#0284c7', // sky blue
        '#059669', // emerald
        '#ca8a04', // golden
        '#e11d48', // crimson
        '#7c3aed', // violet
        '#0d9488', // teal
    ];

    /**
     * Dark text colors — paired with accent colors for legibility.
     */
    private const TEXT_COLORS = [
        '#92400e', // dark amber
        '#78350f', // dark brown
        '#991b1b', // dark red
        '#9d174d', // dark rose
        '#6b21a8', // dark purple
        '#3730a3', // dark indigo
        '#075985', // dark sky
        '#065f46', // dark emerald
        '#854d0e', // dark gold
        '#be123c', // dark crimson
        '#5b21b6', // dark violet
        '#115e59', // dark teal
    ];

    /**
     * Generate avatar for a single human user.
     */
    public function generate(User $user): string
    {
        $svg = $this->buildSvg($user->name);
        $path = "avatars/{$user->id}.svg";

        Storage::disk('public')->put($path, $svg);

        $url = '/storage/' . $path;
        $user->update(['avatar' => $url]);

        return $url;
    }

    /**
     * Generate avatars for all human users. Returns count.
     */
    public function generateAll(): int
    {
        $count = 0;
        User::where('type', 'human')->each(function (User $user) use (&$count) {
            $this->generate($user);
            $count++;
        });

        return $count;
    }

    /**
     * Build the SVG markup from a seed string.
     */
    private function buildSvg(string $seed): string
    {
        $hash = $this->seedHash($seed);
        $colors = $this->pickColors($hash);
        $circles = $this->buildCircles($hash);
        $initial = $this->getInitial($seed);

        $s = self::SIZE;
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$s} {$s}" width="{$s}" height="{$s}">
<defs>
  <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
    <stop offset="0%" stop-color="{$colors['bg1']}"/>
    <stop offset="100%" stop-color="{$colors['bg2']}"/>
  </linearGradient>
  <clipPath id="clip">
    <rect width="{$s}" height="{$s}" rx="22"/>
  </clipPath>
</defs>
<rect width="{$s}" height="{$s}" rx="22" fill="url(#bg)"/>
<g clip-path="url(#clip)">

SVG;

        // Render bokeh circles
        foreach ($circles as $circle) {
            $svg .= sprintf(
                '<circle cx="%.1f" cy="%.1f" r="%.1f" fill="%s" opacity="%.2f"/>',
                $circle['cx'],
                $circle['cy'],
                $circle['r'],
                $colors['accent'],
                $circle['opacity']
            );
            $svg .= "\n";
        }

        $svg .= "</g>\n";

        // Central initial letter
        $cx = $s / 2;
        $cy = $s / 2;
        $svg .= sprintf(
            '<text x="%.0f" y="%.0f" text-anchor="middle" dominant-baseline="central" '
            . 'font-family="system-ui, -apple-system, \'Segoe UI\', sans-serif" '
            . 'font-size="52" font-weight="700" fill="%s" opacity="0.85">%s</text>',
            $cx,
            $cy,
            $colors['text'],
            htmlspecialchars($initial, ENT_XML1)
        );
        $svg .= "\n";

        // Soft outer ring
        $svg .= sprintf(
            '<rect width="%d" height="%d" rx="22" fill="none" stroke="%s" stroke-opacity="0.12" stroke-width="2"/>',
            $s,
            $s,
            $colors['accent']
        );
        $svg .= "\n";

        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Generate a deterministic byte array from a seed string.
     *
     * @return array<int, int>
     */
    private function seedHash(string $input): array
    {
        $hash = md5($input);
        $bytes = [];
        for ($i = 0; $i < 32; $i += 2) {
            $bytes[] = hexdec(substr($hash, $i, 2));
        }

        // Extend with sha1 for more bytes
        $hash2 = sha1($input);
        for ($i = 0; $i < 40; $i += 2) {
            $bytes[] = hexdec(substr($hash2, $i, 2));
        }

        return $bytes;
    }

    /**
     * Pick background and accent colors from hash bytes.
     *
     * @param  array<int, int>  $hash
     * @return array<string, string>
     */
    private function pickColors(array $hash): array
    {
        $bgIdx = $hash[0] % count(self::BG_PALETTES);
        $accentIdx = $hash[1] % count(self::ACCENT_COLORS);

        return [
            'bg1' => self::BG_PALETTES[$bgIdx][0],
            'bg2' => self::BG_PALETTES[$bgIdx][1],
            'accent' => self::ACCENT_COLORS[$accentIdx],
            'text' => self::TEXT_COLORS[$accentIdx],
        ];
    }

    /**
     * Build an array of overlapping bokeh circles at seeded positions.
     *
     * @param  array<int, int>  $hash
     * @return array<int, array<string, float>>
     */
    private function buildCircles(array $hash): array
    {
        $circleCount = 5 + ($hash[2] % 3); // 5, 6, or 7 circles
        $circles = [];
        $byteIdx = 3;

        for ($i = 0; $i < $circleCount; $i++) {
            $bx = $hash[$byteIdx++ % count($hash)];
            $by = $hash[$byteIdx++ % count($hash)];
            $br = $hash[$byteIdx++ % count($hash)];
            $bo = $hash[$byteIdx++ % count($hash)];

            $cx = 10 + ($bx / 255) * 108;
            $cy = 10 + ($by / 255) * 108;
            $r = 18 + ($br / 255) * 27;
            $opacity = 0.12 + ($bo / 255) * 0.18;

            $circles[] = [
                'cx' => round($cx, 1),
                'cy' => round($cy, 1),
                'r' => round($r, 1),
                'opacity' => round($opacity, 2),
            ];
        }

        return $circles;
    }

    /**
     * Extract the first character of the name as an uppercase initial.
     */
    private function getInitial(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }

        return mb_strtoupper(mb_substr($name, 0, 1));
    }
}

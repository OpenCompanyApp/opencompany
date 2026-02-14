<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class AgentAvatarService
{
    private const SIZE = 128;
    private const GRID = 5;
    private const CELL_SIZE = 18;
    private const GRID_OFFSET = 19; // (128 - 5*18) / 2

    /**
     * Background gradient palettes — pairs of [from, to] dark tech colors.
     */
    private const BG_PALETTES = [
        ['#0f172a', '#1e293b'], // slate
        ['#0c0a3e', '#1a1a5e'], // deep indigo
        ['#0d1b2a', '#1b2838'], // navy
        ['#1a0a2e', '#2d1b4e'], // deep purple
        ['#0a192f', '#172a45'], // dark blue
        ['#0d2818', '#1a3a2a'], // dark emerald
        ['#1c1017', '#2d1f2b'], // dark plum
        ['#0f1419', '#1a2332'], // charcoal blue
        ['#170a1e', '#2a1538'], // midnight purple
        ['#0a1628', '#152238'], // steel blue
    ];

    /**
     * Accent colors — bright tech/neon tones for the grid shapes.
     */
    private const ACCENT_COLORS = [
        '#00d4ff', // cyan
        '#7c3aed', // violet
        '#06b6d4', // teal
        '#10b981', // emerald
        '#f472b6', // pink
        '#60a5fa', // blue
        '#a78bfa', // purple
        '#34d399', // green
        '#f59e0b', // amber
        '#e879f9', // fuchsia
        '#22d3ee', // sky
        '#818cf8', // indigo
    ];

    /**
     * Shape types for grid cells.
     */
    private const SHAPES = ['circle', 'square', 'diamond', 'hexagon', 'triangle'];

    /**
     * Generate avatar for a single agent.
     */
    public function generate(User $agent): string
    {
        $svg = $this->buildSvg($agent->name);
        $path = "avatars/{$agent->id}.svg";

        Storage::disk('public')->put($path, $svg);

        $url = '/storage/' . $path;
        $agent->update(['avatar' => $url]);

        return $url;
    }

    /**
     * Generate avatars for all agents. Returns count.
     */
    public function generateAll(): int
    {
        $count = 0;
        User::where('type', 'agent')->each(function (User $agent) use (&$count) {
            $this->generate($agent);
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
        $grid = $this->buildGrid($hash);

        $s = self::SIZE;
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$s} {$s}" width="{$s}" height="{$s}">
<defs>
  <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
    <stop offset="0%" stop-color="{$colors['bg1']}"/>
    <stop offset="100%" stop-color="{$colors['bg2']}"/>
  </linearGradient>
  <linearGradient id="accent" x1="0%" y1="0%" x2="100%" y2="100%">
    <stop offset="0%" stop-color="{$colors['accent']}" stop-opacity="0.9"/>
    <stop offset="100%" stop-color="{$colors['accent2']}" stop-opacity="0.7"/>
  </linearGradient>
</defs>
<rect width="{$s}" height="{$s}" rx="22" fill="url(#bg)"/>

SVG;

        // Render ambient glow behind the grid
        $cx = $s / 2;
        $cy = $s / 2;
        $svg .= "<circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"38\" fill=\"{$colors['accent']}\" opacity=\"0.08\"/>\n";

        // Render grid cells
        for ($row = 0; $row < self::GRID; $row++) {
            for ($col = 0; $col < self::GRID; $col++) {
                $cell = $grid[$row][$col];
                if (!$cell['active']) {
                    continue;
                }

                $x = self::GRID_OFFSET + $col * self::CELL_SIZE + self::CELL_SIZE / 2;
                $y = self::GRID_OFFSET + $row * self::CELL_SIZE + self::CELL_SIZE / 2;
                $opacity = $cell['opacity'];
                $shape = $cell['shape'];

                $svg .= $this->renderCell($x, $y, $shape, $colors['accent'], $opacity);
            }
        }

        // Center core element — a brighter inner shape
        $coreX = self::GRID_OFFSET + 2 * self::CELL_SIZE + self::CELL_SIZE / 2;
        $coreY = self::GRID_OFFSET + 2 * self::CELL_SIZE + self::CELL_SIZE / 2;
        $coreShape = self::SHAPES[$hash[31] % count(self::SHAPES)];
        $svg .= $this->renderCell($coreX, $coreY, $coreShape, '#ffffff', 0.85, 6);

        // Decorative corner dots
        $dotR = 2;
        $dotOpacity = 0.3;
        $svg .= "<circle cx=\"16\" cy=\"16\" r=\"{$dotR}\" fill=\"{$colors['accent']}\" opacity=\"{$dotOpacity}\"/>\n";
        $svg .= "<circle cx=\"112\" cy=\"16\" r=\"{$dotR}\" fill=\"{$colors['accent']}\" opacity=\"{$dotOpacity}\"/>\n";
        $svg .= "<circle cx=\"16\" cy=\"112\" r=\"{$dotR}\" fill=\"{$colors['accent']}\" opacity=\"{$dotOpacity}\"/>\n";
        $svg .= "<circle cx=\"112\" cy=\"112\" r=\"{$dotR}\" fill=\"{$colors['accent']}\" opacity=\"{$dotOpacity}\"/>\n";

        // Subtle border ring
        $svg .= "<rect width=\"{$s}\" height=\"{$s}\" rx=\"22\" fill=\"none\" stroke=\"{$colors['accent']}\" stroke-opacity=\"0.15\" stroke-width=\"1\"/>\n";

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
        $accent2Idx = ($hash[1] + 3) % count(self::ACCENT_COLORS);

        // Ensure accent2 differs from accent
        if ($accent2Idx === $accentIdx) {
            $accent2Idx = ($accentIdx + 1) % count(self::ACCENT_COLORS);
        }

        return [
            'bg1' => self::BG_PALETTES[$bgIdx][0],
            'bg2' => self::BG_PALETTES[$bgIdx][1],
            'accent' => self::ACCENT_COLORS[$accentIdx],
            'accent2' => self::ACCENT_COLORS[$accent2Idx],
        ];
    }

    /**
     * Build the 5×5 grid with vertical symmetry.
     * Only columns 0,1,2 are generated; 3,4 mirror 1,0.
     *
     * @param  array<int, int>  $hash
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildGrid(array $hash): array
    {
        $grid = [];
        $byteIdx = 2; // Start after color bytes

        for ($row = 0; $row < self::GRID; $row++) {
            $grid[$row] = [];

            // Generate left half + center (columns 0, 1, 2)
            for ($col = 0; $col <= 2; $col++) {
                $b = $hash[$byteIdx % count($hash)];
                $byteIdx++;

                $active = ($b % 3) !== 0; // ~67% chance active
                $shape = self::SHAPES[$b % count(self::SHAPES)];
                $opacity = 0.5 + ($b % 40) / 100; // 0.50 - 0.89

                $grid[$row][$col] = [
                    'active' => $active,
                    'shape' => $shape,
                    'opacity' => round($opacity, 2),
                ];
            }

            // Mirror: column 3 = column 1, column 4 = column 0
            $grid[$row][3] = $grid[$row][1];
            $grid[$row][4] = $grid[$row][0];
        }

        // Ensure at least some cells are active (minimum 6)
        $activeCount = 0;
        for ($r = 0; $r < self::GRID; $r++) {
            for ($c = 0; $c < self::GRID; $c++) {
                if ($grid[$r][$c]['active']) {
                    $activeCount++;
                }
            }
        }

        if ($activeCount < 6) {
            // Force the cross pattern active
            $grid[0][2]['active'] = true;
            $grid[1][1]['active'] = true;
            $grid[1][3]['active'] = true;
            $grid[2][0]['active'] = true;
            $grid[2][4]['active'] = true;
            $grid[3][1]['active'] = true;
            $grid[3][3]['active'] = true;
            $grid[4][2]['active'] = true;
        }

        return $grid;
    }

    /**
     * Render a single grid cell shape as SVG markup.
     */
    private function renderCell(float $cx, float $cy, string $shape, string $color, float $opacity, ?float $sizeOverride = null): string
    {
        $r = $sizeOverride ?? 7;

        return match ($shape) {
            'circle' => "<circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$r}\" fill=\"{$color}\" opacity=\"{$opacity}\"/>",

            'square' => sprintf(
                '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="2" fill="%s" opacity="%.2f"/>',
                $cx - $r, $cy - $r, $r * 2, $r * 2, $color, $opacity
            ),

            'diamond' => sprintf(
                '<polygon points="%.1f,%.1f %.1f,%.1f %.1f,%.1f %.1f,%.1f" fill="%s" opacity="%.2f"/>',
                $cx, $cy - $r,
                $cx + $r, $cy,
                $cx, $cy + $r,
                $cx - $r, $cy,
                $color, $opacity
            ),

            'hexagon' => $this->renderHexagon($cx, $cy, $r, $color, $opacity),

            'triangle' => sprintf(
                '<polygon points="%.1f,%.1f %.1f,%.1f %.1f,%.1f" fill="%s" opacity="%.2f"/>',
                $cx, $cy - $r,
                $cx + $r, $cy + $r * 0.7,
                $cx - $r, $cy + $r * 0.7,
                $color, $opacity
            ),

            default => '',
        };
    }

    /**
     * Render a hexagon shape.
     */
    private function renderHexagon(float $cx, float $cy, float $r, string $color, float $opacity): string
    {
        $points = [];
        for ($i = 0; $i < 6; $i++) {
            $angle = deg2rad(60 * $i - 30);
            $points[] = sprintf('%.1f,%.1f', $cx + $r * cos($angle), $cy + $r * sin($angle));
        }

        return sprintf(
            '<polygon points="%s" fill="%s" opacity="%.2f"/>',
            implode(' ', $points),
            $color,
            $opacity
        );
    }
}

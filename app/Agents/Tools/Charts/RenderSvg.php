<?php

namespace App\Agents\Tools\Charts;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Symfony\Component\Process\Process;

class RenderSvg implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return <<<'DESC'
Render SVG markup to a PNG image. You MUST pass complete, valid SVG source code in the `svg` parameter — the tool converts it to a PNG and returns a markdown image embed.

Use this for diagrams, icons, illustrations, infographics, flowcharts, or any custom visual that isn't a data chart.

**You must write real SVG code.** Do NOT describe what you would draw — actually write the `<svg>...</svg>` markup. Example call:

svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><rect width="400" height="300" fill="#1e1e2e" rx="12"/><circle cx="200" cy="120" r="60" fill="#89b4fa"/><text x="200" y="240" text-anchor="middle" font-family="sans-serif" font-size="24" fill="#cdd6f4">Hello World</text></svg>`

Tips:
- Always include xmlns and a viewBox on the root <svg> element
- Fonts render as system sans-serif — use font-family="sans-serif"
- Supported: shapes, paths, text, gradients (linear/radial), filters (blur, drop-shadow), clip-paths, masks, patterns, transforms
- Use hex colors (#rrggbb), not named colors
- For crisp output, use vector elements — avoid embedded raster images
DESC;
    }

    public function handle(Request $request): string
    {
        $svg = trim($request['svg'] ?? '');
        if (empty($svg)) {
            return 'Error: SVG markup is required.';
        }

        $title = $request['title'] ?? 'Image';
        $width = max(100, min(4000, (int) ($request['width'] ?? 1400)));
        $height = isset($request['height']) ? max(100, min(4000, (int) $request['height'])) : null;

        try {
            Storage::disk('public')->makeDirectory('svg');

            $uuid = Str::uuid()->toString();
            $outputRelative = 'svg/' . $uuid . '.png';
            $outputPath = Storage::disk('public')->path($outputRelative);

            // Write SVG to temp file
            $tmpSvg = tempnam(sys_get_temp_dir(), 'svg_') . '.svg';
            file_put_contents($tmpSvg, $svg);

            try {
                // Build rsvg-convert command
                $rsvg = collect(['/opt/homebrew/bin/rsvg-convert', '/usr/local/bin/rsvg-convert', '/usr/bin/rsvg-convert'])
                    ->first(fn ($p) => file_exists($p), 'rsvg-convert');
                $command = [$rsvg, '-w', (string) $width];
                if ($height) {
                    $command[] = '-h';
                    $command[] = (string) $height;
                }
                $command[] = '-o';
                $command[] = $outputPath;
                $command[] = $tmpSvg;

                $process = new Process($command);
                $process->setTimeout(30);
                $process->run();

                if (!$process->isSuccessful()) {
                    $error = $process->getErrorOutput() ?: $process->getOutput();
                    return 'SVG rendering error: ' . trim($error);
                }

                if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                    return 'Error: rsvg-convert produced no output.';
                }
            } finally {
                @unlink($tmpSvg);
            }

            $url = '/storage/' . $outputRelative;

            return "![{$title}]({$url})";
        } catch (\Throwable $e) {
            return 'Error rendering SVG: ' . $e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'svg' => $schema
                ->string()
                ->description('Complete SVG markup. Must include a root <svg> element with a viewBox attribute.')
                ->required(),
            'title' => $schema
                ->string()
                ->description('Image title used as alt text (default: "Image").'),
            'width' => $schema
                ->integer()
                ->description('Output width in pixels (default: 1400, range: 100–4000). Height scales proportionally from the SVG viewBox unless explicitly set.'),
            'height' => $schema
                ->integer()
                ->description('Output height in pixels (range: 100–4000). Omit to preserve aspect ratio from the SVG viewBox.'),
        ];
    }
}

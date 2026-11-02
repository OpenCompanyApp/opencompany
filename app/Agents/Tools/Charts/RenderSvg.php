<?php

namespace App\Agents\Tools\Charts;

use App\Models\User;
use App\Services\CodeExecutionBudget;
use App\Services\CodeExecutionCancelled;
use App\Services\CodeExecutionDeadlineExceeded;
use App\Services\FileSystemService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Symfony\Component\Process\Process;

/**
 * Renders caller-supplied SVG through the host's bounded rsvg subprocess.
 *
 * The tool owns its temporary renderer files and stores the PNG as a workspace
 * file in the acting agent's workspace (`/Generated`), served only through the
 * authenticated, workspace-scoped `/api/files/{id}/download` route. It never
 * writes to the public disk. Authorization and the execution budget come from
 * the caller (tool permission checks, Code Mode dispatch).
 *
 * Agent breadcrumb: the package renderers (Mermaid, PlantUML, Typst,
 * Vega-Lite) were deleted with the package system on purpose. Do not rebuild
 * them here; charts and diagrams are planned as client-side Artifact blocks
 * and Typst as PDF export into Files (docs/planning/artifacts-and-forms.md).
 */
class RenderSvg implements Tool
{
    public function __construct(private readonly User $agent) {}

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
            return 'Error: SVG markup is required. Pass your SVG code in the "svg" parameter.';
        }

        $title = $request['title'] ?? 'Image';
        $width = max(100, min(4000, (int) ($request['width'] ?? 1400)));
        $height = isset($request['height']) ? max(100, min(4000, (int) $request['height'])) : null;

        if ($this->agent->workspace_id === null) {
            return 'Error: rendering needs a workspace to store the image in.';
        }

        try {
            // Use the files tempnam actually reserved. Appending an extension
            // would leak the original file and lose its atomic ownership.
            $tmpSvg = tempnam(sys_get_temp_dir(), 'svg_');
            $tmpPng = tempnam(sys_get_temp_dir(), 'png_');
            if ($tmpSvg === false || $tmpPng === false) {
                throw new \RuntimeException('Could not reserve renderer files.');
            }

            try {
                if (file_put_contents($tmpSvg, $svg) !== strlen($svg)) {
                    throw new \RuntimeException('Could not write renderer input.');
                }
                // Build rsvg-convert command
                $rsvg = collect(['/opt/homebrew/bin/rsvg-convert', '/usr/local/bin/rsvg-convert', '/usr/bin/rsvg-convert'])
                    ->first(fn ($p) => file_exists($p), 'rsvg-convert');
                $command = [$rsvg, '-w', (string) $width];
                if ($height) {
                    $command[] = '-h';
                    $command[] = (string) $height;
                }
                $command[] = '-o';
                $command[] = $tmpPng;
                $command[] = $tmpSvg;

                $process = new Process($command);
                app(CodeExecutionBudget::class)->runProcess($process, 30.0);

                if (! $process->isSuccessful()) {
                    $error = $process->getErrorOutput() ?: $process->getOutput();

                    return 'SVG rendering error: '.trim($error);
                }

                $png = (string) file_get_contents($tmpPng);
                if ($png === '') {
                    return 'Error: rsvg-convert produced no output.';
                }
            } finally {
                @unlink($tmpSvg);
                @unlink($tmpPng);
            }

            $files = app(FileSystemService::class);
            $workspaceId = (string) $this->agent->workspace_id;
            $folder = $files->ensureFolderPath('Generated', $workspaceId, (string) $this->agent->id);
            $name = (Str::slug((string) $title) ?: 'image').'-'.Str::lower(Str::random(8)).'.png';
            $file = $files->writeBinaryFile($workspaceId, $folder->id, $name, $png, (string) $this->agent->id, 'image/png',
                metadata: ['source' => 'render_svg']);

            return "![{$title}](/api/files/{$file->id}/download)";
        } catch (CodeExecutionCancelled|CodeExecutionDeadlineExceeded $e) {
            // Returning an ordinary string would let Ruby treat an expired
            // renderer as a successful callback. Preserve the typed boundary.
            throw $e;
        } catch (\Throwable $e) {
            return 'Error rendering SVG: '.$e->getMessage();
        }
    }

    /** @return array<string, mixed> */
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

<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Charts\RenderSvg;
use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Regression: render_svg wrote unscoped PNGs to the public disk, readable by
 * anyone with the URL. Rendered images are now workspace files in the agent's
 * workspace, served only through the authenticated download route.
 */
class RenderSvgTest extends TestCase
{
    use RefreshDatabase;

    public function test_rendered_image_is_a_workspace_file_not_a_public_upload(): void
    {
        if (! is_executable('/usr/bin/rsvg-convert') && ! is_executable('/usr/local/bin/rsvg-convert')) {
            $this->markTestSkipped('rsvg-convert is not installed.');
        }
        Storage::fake('public');
        Storage::fake('local');
        // No disk configured: a fresh workspace gets a private local default.
        $agent = User::factory()->agent()->create();

        $result = (new RenderSvg($agent))->handle(new Request([
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#89b4fa"/></svg>',
            'title' => 'Box',
            'width' => 100,
        ]));

        $this->assertMatchesRegularExpression('#^!\[Box\]\(/api/files/[0-9A-Za-z-]+/download\)$#', $result);
        preg_match('#/api/files/([0-9A-Za-z-]+)/#', $result, $m);
        $file = WorkspaceFile::query()->findOrFail($m[1]);
        $this->assertSame($agent->workspace_id, $file->workspace_id);
        $this->assertSame('image/png', $file->mime_type);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame('local', WorkspaceDisk::query()->where('workspace_id', $agent->workspace_id)->sole()->driver);
    }
}

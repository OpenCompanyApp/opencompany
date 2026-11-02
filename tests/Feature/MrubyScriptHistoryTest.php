<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\RoutineController;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Archived source stays exact and workspace-owned; loading is not admission. */
class MrubyScriptHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function routine(): Routine
    {
        return Routine::create([
            'id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id,
            'name' => 'Source history', 'kind' => 'script',
            'agent_id' => User::factory()->agent()->create()->id,
            'created_by_id' => User::factory()->create()->id,
            'script' => "  local legacy = true\n", 'is_active' => false,
            'cron_expression' => '0 9 * * *', 'timezone' => 'UTC',
        ]);
    }

    public function test_metadata_is_paged_and_only_selected_revision_exposes_exact_source(): void
    {
        $routine = $this->routine();
        for ($i = 0; $i < 22; $i++) {
            $routine->update(['script' => "  legacy revision {$i}\n"]);
        }
        $controller = app(RoutineController::class);
        $page = $controller->scriptRevisions($routine->id);
        $this->assertSame(23, $page->total());
        $this->assertCount(20, $page->items());
        $this->assertObjectNotHasProperty('source', $page->items()[0]);
        $original = DB::table('routine_script_revisions')->where('routine_id', $routine->id)
            ->where('source_digest', hash('sha256', "  local legacy = true\n"))->first();

        $revision = $controller->scriptRevision($routine->id, $original->id);

        $this->assertSame("  local legacy = true\n", $revision->source);
        $this->assertNull($routine->fresh()->script_runtime);
        $this->assertFalse($routine->fresh()->is_active);
    }

    public function test_another_workspace_cannot_read_source_or_revision_metadata(): void
    {
        $routine = $this->routine();
        $revision = DB::table('routine_script_revisions')->where('routine_id', $routine->id)->first();
        app()->instance('currentWorkspace', Workspace::create(['name' => 'Other', 'slug' => 'other']));
        $controller = app(RoutineController::class);

        foreach ([
            fn () => $controller->scriptRevisions($routine->id),
            fn () => $controller->scriptRevision($routine->id, $revision->id),
        ] as $read) {
            try {
                $read();
                $this->fail('Expected a workspace-scoped not found response.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}

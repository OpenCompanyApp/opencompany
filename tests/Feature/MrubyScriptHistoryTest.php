<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AutomationController;
use App\Models\Automation;
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

    private function automation(): Automation
    {
        return Automation::create([
            'id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id,
            'name' => 'Source history', 'execution_type' => 'script',
            'agent_id' => User::factory()->agent()->create()->id,
            'created_by_id' => User::factory()->create()->id,
            'script' => "  local legacy = true\n", 'is_active' => false,
            'cron_expression' => '0 9 * * *', 'timezone' => 'UTC',
        ]);
    }

    public function test_metadata_is_paged_and_only_selected_revision_exposes_exact_source(): void
    {
        $automation = $this->automation();
        for ($i = 0; $i < 22; $i++) {
            $automation->update(['script' => "  legacy revision {$i}\n"]);
        }
        $controller = app(AutomationController::class);
        $page = $controller->scriptRevisions($automation->id);
        $this->assertSame(23, $page->total());
        $this->assertCount(20, $page->items());
        $this->assertObjectNotHasProperty('source', $page->items()[0]);
        $original = DB::table('automation_script_revisions')->where('automation_id', $automation->id)
            ->where('source_digest', hash('sha256', "  local legacy = true\n"))->first();

        $revision = $controller->scriptRevision($automation->id, $original->id);

        $this->assertSame("  local legacy = true\n", $revision->source);
        $this->assertNull($automation->fresh()->script_runtime);
        $this->assertFalse($automation->fresh()->is_active);
    }

    public function test_another_workspace_cannot_read_source_or_revision_metadata(): void
    {
        $automation = $this->automation();
        $revision = DB::table('automation_script_revisions')->where('automation_id', $automation->id)->first();
        app()->instance('currentWorkspace', Workspace::create(['name' => 'Other', 'slug' => 'other']));
        $controller = app(AutomationController::class);

        foreach ([
            fn () => $controller->scriptRevisions($automation->id),
            fn () => $controller->scriptRevision($automation->id, $revision->id),
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

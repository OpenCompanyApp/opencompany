<?php

namespace Tests\Feature\Governance;

use App\Agents\Runtime\ActiveAgentRun;
use App\Models\AgentRunRecord;
use App\Models\ChangeRecord;
use App\Models\Document;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reversible edits auto-apply because they can be undone: an agent run's
 * changes are recorded and "revert this run" undoes them — except values a
 * human changed since, which win and are reported as conflicts.
 */
class RunRevertTest extends TestCase
{
    use RefreshDatabase;

    private User $human;

    private User $agent;

    private AgentRunRecord $run;

    protected function setUp(): void
    {
        parent::setUp();
        $this->human = User::factory()->create();
        $this->agent = User::factory()->agent()->create();
        $task = Task::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'title' => 't',
            'type' => Task::TYPE_CUSTOM, 'status' => Task::STATUS_ACTIVE, 'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_CHAT, 'agent_id' => $this->agent->id, 'requester_id' => $this->human->id]);
        $this->run = AgentRunRecord::ensureForTask($task, AgentRunRecord::KIND_CHAT);
    }

    private function asAgentRun(callable $callback): void
    {
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($this->run->id));
        try {
            $callback();
        } finally {
            app()->forgetInstance(ActiveAgentRun::class);
        }
    }

    public function test_revert_undoes_the_runs_edits_creates_and_deletes(): void
    {
        $edited = Document::factory()->create(['title' => 'Plan', 'content' => 'human v1', 'author_id' => $this->human->id]);
        $trashed = Document::factory()->create(['title' => 'Old', 'author_id' => $this->human->id]);
        $created = null;

        $this->asAgentRun(function () use ($edited, $trashed, &$created): void {
            $edited->update(['content' => 'agent v2']);
            $trashed->delete();
            $created = Document::factory()->create(['title' => 'Agent draft', 'author_id' => $this->agent->id]);
        });
        $this->assertSame(3, ChangeRecord::query()->where('run_id', $this->run->id)->count(), 'update, trash, create');

        $this->actingAs($this->human)->postJson("/api/agent-runs/{$this->run->id}/revert")
            ->assertOk()->assertJson(['conflicts' => []]);

        $this->assertSame('human v1', $edited->fresh()->content);
        $this->assertNotNull(Document::find($trashed->id), 'trashed doc restored');
        $this->assertNull(Document::find($created->id), 'created doc moved to trash');
    }

    public function test_human_edits_since_the_run_win(): void
    {
        $doc = Document::factory()->create(['content' => 'human v1', 'author_id' => $this->human->id]);
        $this->asAgentRun(fn () => $doc->update(['content' => 'agent v2']));
        $doc->fresh()->update(['content' => 'human v3']);

        $this->actingAs($this->human)->postJson("/api/agent-runs/{$this->run->id}/revert")
            ->assertOk()->assertJsonCount(1, 'conflicts');

        $this->assertSame('human v3', $doc->fresh()->content);
    }

    public function test_runs_from_other_workspaces_are_not_found(): void
    {
        $other = Workspace::create(['name' => 'Other', 'slug' => 'other-revert']);
        $this->run->forceFill(['workspace_id' => $other->id])->save();

        $this->actingAs($this->human)->postJson("/api/agent-runs/{$this->run->id}/revert")->assertNotFound();
    }
}

<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Agents\Tools\ToolRegistry;
use App\Agents\Tools\Vfs\VfsPatch;
use App\Agents\Tools\Vfs\VfsWrite;
use App\Domain\Vfs\OpenCompany\Approvals\VfsPendingApproval;
use App\Domain\Vfs\OpenCompany\Approvals\VfsSecretRedactor;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Jobs\AgentRespondJob;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceMember;
use App\Services\ApprovalExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Phase 2 (Stream C2) approval UX for VFS operations.
 *
 * An agent hitting an approval gate gets one non-error JSON shape whichever
 * gate fired; the approver sees a diff or field changes; the approval pins
 * the version it was reviewed against; the outcome is posted back to the
 * originating conversation; and /approvals/{id}.json stays readable.
 */
class VfsApprovalUxTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create(['name' => 'Atlas', 'status' => 'idle']);
        WorkspaceDisk::create([
            'id' => 'disk-local',
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
        $this->permission('file_folder', '*');
    }

    // Both gates (wrapper: the primitive is gated; policy: a backing
    // permission is gated) produce the same pending_approval keys.
    public function test_wrapper_and_policy_gates_return_the_same_pending_shape(): void
    {
        [$doc, $path] = $this->editableDoc("alpha\nbeta\n");
        $this->gate('vfs_patch');
        $patch = json_encode(['search' => 'beta', 'replace' => 'gamma']);

        $policy = json_decode($this->patchTool()->handle(new Request(['path' => $path, 'patch' => $patch])), true);
        $wrapped = json_decode((new ApprovalWrappedTool($this->patchTool(), $this->agent, 'vfs_patch'))
            ->handle(new Request(['path' => $path, 'patch' => json_encode(['search' => 'alpha', 'replace' => 'omega'])])), true);

        foreach ([$policy, $wrapped] as $response) {
            $this->assertTrue($response['ok']);
            $this->assertSame('pending_approval', $response['status']);
            $this->assertSame(VfsPendingApproval::NEXT, $response['next']);
            $this->assertSame('patch', $response['operation']);
            $this->assertSame($path, $response['path']);
            $this->assertSame(['vfs_patch'], $response['required_tools']);
            $this->assertSame('reversible_edit', $response['action_class']);
            $this->assertSame('text_change', $response['preview']['kind']);
            $this->assertNotNull(VfsPendingApproval::isPending($response));
        }
        $this->assertSame(array_keys($policy), array_keys($wrapped));
        $this->assertStringContainsString("-beta\n+gamma", $policy['preview']['diff']);
        $this->assertStringContainsString("-alpha\n+omega", $wrapped['preview']['diff']);
        $this->assertSame('alpha', explode("\n", (string) $doc->fresh()->content)[0]);

        // An identical retry of the wrapped call reuses the pending approval.
        $again = json_decode((new ApprovalWrappedTool($this->patchTool(), $this->agent, 'vfs_patch'))
            ->handle(new Request(['path' => $path, 'patch' => json_encode(['search' => 'alpha', 'replace' => 'omega'])])), true);
        $this->assertSame($wrapped['approval_id'], $again['approval_id']);
        $this->assertSame(2, ApprovalRequest::query()->count());
    }

    // Row patches show field before -> after; deletes name the object.
    public function test_previews_show_row_changes_and_name_deleted_objects(): void
    {
        $table = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Deals', 'created_by' => $this->agent->id]);
        $column = DataTableColumn::create(['table_id' => $table->id, 'name' => 'Stage', 'type' => 'text', 'order' => 0, 'required' => false]);
        $row = DataTableRow::create(['table_id' => $table->id, 'data' => [$column->id => 'lead'], 'created_by' => $this->agent->id]);
        $this->gate('update_table_row');

        $response = json_decode($this->patchTool()->handle(new Request([
            'path' => "/tables/deals/rows/by-id/{$row->id}.json",
            'patch' => json_encode(['data' => ['Stage' => 'won']]),
        ])), true);
        $this->assertSame(['before' => 'lead', 'after' => 'won'], $response['preview']['changes']['Stage']);

        app(OpenCompanyVfs::class)->write($this->agent, '/files/old.txt', 'bye');
        $this->gate('vfs_rm');
        $rm = json_decode((new ApprovalWrappedTool(app(ToolRegistry::class)->instantiateToolBySlug('vfs_rm', $this->agent), $this->agent, 'vfs_rm'))
            ->handle(new Request(['path' => '/files/old.txt'])), true);
        $this->assertSame('delete', $rm['action_class']);
        $this->assertSame('delete', $rm['preview']['kind']);
        $this->assertSame('old.txt', $rm['preview']['name']);
    }

    // The approval pins the reviewed version. A non-anchored overwrite of an
    // object that changed since is refused as conflict and nothing is
    // written; the disposition is persisted and posted to the conversation.
    public function test_moved_object_fails_overwrite_replay_with_conflict(): void
    {
        $channel = $this->conversation();
        $vfs = app(OpenCompanyVfs::class);
        $vfs->write($this->agent, '/files/plan.txt', "v1\n");
        $this->gate('vfs_write');

        $response = json_decode($this->writeTool()->handle(new Request(['path' => '/files/plan.txt', 'content' => "agent rewrite\n", 'mode' => 'overwrite'])), true);
        $this->assertSame('pending_approval', $response['status']);
        $approval = ApprovalRequest::query()->findOrFail($response['approval_id']);
        $this->assertSame($channel->id, $approval->channel_id);
        $this->assertNotNull($approval->tool_execution_context['vfs_approval']['base_version']);

        // A human edits the file while the approval waits.
        AgentPermission::query()->where('scope_key', 'vfs_write')->delete();
        $vfs->write($this->agent, '/files/plan.txt', "human edit\n", 'overwrite');

        app(ApprovalExecutionService::class)->resolve($approval->fresh(), 'approved', $this->approver());

        $this->assertSame("human edit\n", $vfs->readContent($this->agent, '/files/plan.txt'));
        $this->assertSame('conflict', $approval->fresh()->tool_execution_context['execution']['disposition']);
        $posted = Message::query()->where('channel_id', $channel->id)->latest('timestamp')->first();
        $this->assertStringContainsString('not applied (conflict)', (string) $posted?->content);
    }

    // An anchored text edit still applies after unrelated changes, and the
    // outcome reaches the conversation and resumes a parked agent.
    public function test_anchored_patch_replays_after_unrelated_change_and_resumes_the_agent(): void
    {
        Queue::fake([AgentRespondJob::class]);
        $channel = $this->conversation();
        $this->agent->update(['must_wait_for_approval' => true]);
        [$doc, $path] = $this->editableDoc("intro\nprice: 10\n");
        $this->gate('vfs_patch');

        $response = json_decode($this->patchTool()->handle(new Request(['path' => $path, 'patch' => json_encode(['search' => 'price: 10', 'replace' => 'price: 12'])])), true);
        $this->assertSame(VfsPendingApproval::NEXT_WAITING, $response['next']);
        $this->assertSame($response['approval_id'], $this->agent->fresh()->awaiting_approval_id);

        $doc->update(['content' => "intro changed by a human\nprice: 10\n"]);
        app(ApprovalExecutionService::class)->resolve(ApprovalRequest::query()->findOrFail($response['approval_id']), 'approved', $this->approver());

        $this->assertSame("intro changed by a human\nprice: 12\n", $doc->fresh()->content);
        $approval = ApprovalRequest::query()->findOrFail($response['approval_id']);
        $this->assertSame('succeeded', $approval->tool_execution_context['execution']['disposition']);
        $this->assertStringContainsString('Approved action executed', (string) Message::query()->where('channel_id', $channel->id)->latest('timestamp')->value('content'));
        $this->assertNull($this->agent->fresh()->awaiting_approval_id);
        Queue::assertPushed(AgentRespondJob::class);
    }

    // The old heuristic redacted every 32+ char run (every UUID and path).
    public function test_approval_json_keeps_ids_and_paths_and_redacts_real_secrets(): void
    {
        [, $path] = $this->editableDoc("alpha\n");
        $this->gate('vfs_patch');
        $response = json_decode($this->patchTool()->handle(new Request([
            'path' => $path,
            'patch' => json_encode(['search' => 'alpha', 'replace' => 'api_key=sk-live-abcdefghijklmnop1234 and Bearer abcdefghijkl123']),
        ])), true);

        $json = app(OpenCompanyVfs::class)->read($this->agent, "/approvals/{$response['approval_id']}.json");
        $payload = json_decode($json, true);

        $this->assertSame($response['approval_id'], $payload['id']);
        $this->assertSame($this->agent->id, $payload['requester_id']);
        $this->assertSame($path, $payload['tool_execution_context']['vfs_approval']['subject']);
        $this->assertSame($path, $payload['tool_execution_context']['parameters']['path']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $payload['tool_execution_context']['vfs_approval']['parameters_hash']);
        $this->assertStringContainsString($path, $payload['description']);
        $this->assertStringNotContainsString('sk-live-abcdefghijklmnop1234', $json);
        $this->assertStringNotContainsString('abcdefghijkl123', $json);

        $this->assertSame(['password' => '[redacted]', 'author_id' => 'u1', 'max_tokens' => 5], VfsSecretRedactor::redact(['password' => 'hunter2', 'author_id' => 'u1', 'max_tokens' => 5]));
    }

    /** @return array{0: Document, 1: string} */
    private function editableDoc(string $content): array
    {
        $folder = Document::factory()->create(['title' => 'Editable', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $doc = Document::factory()->create(['title' => 'Pricing', 'parent_id' => $folder->id, 'content' => $content, 'author_id' => $this->agent->id]);
        $this->permission('folder', $folder->id);

        return [$doc, "/docs/by-id/{$doc->id}"];
    }

    /** Bind the originating conversation like OpenCompanyAgent does. */
    private function conversation(): Channel
    {
        $channel = Channel::factory()->public()->create(['name' => 'ops', 'creator_id' => $this->agent->id]);
        app(ToolRegistry::class)->setChannelContext($channel->id);

        return $channel;
    }

    private function patchTool(): VfsPatch
    {
        return app(VfsPatch::class, ['agent' => $this->agent, 'vfs' => app(OpenCompanyVfs::class)]);
    }

    private function writeTool(): VfsWrite
    {
        return app(VfsWrite::class, ['agent' => $this->agent, 'vfs' => app(OpenCompanyVfs::class)]);
    }

    private function gate(string $toolSlug): void
    {
        $this->permission('tool', $toolSlug, requiresApproval: true);
    }

    private function permission(string $scopeType, string $scopeKey, bool $requiresApproval = false): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'permission' => 'allow',
            'requires_approval' => $requiresApproval,
        ]);
    }

    private function approver(): User
    {
        $approver = User::factory()->create(['type' => 'human']);
        WorkspaceMember::query()->firstOrCreate(
            ['workspace_id' => $this->workspace->id, 'user_id' => $approver->id],
            ['role' => 'admin'],
        );

        return $approver;
    }
}

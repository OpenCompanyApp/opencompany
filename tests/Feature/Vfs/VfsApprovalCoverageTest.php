<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Agents\Tools\Vfs\VfsExec;
use App\Agents\Tools\Vfs\VfsPatch;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use App\Services\AgentDocumentService;
use App\Services\ApprovalExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Regression coverage for "one approval covers the whole VFS operation".
 *
 * Before this, approved_tools held a single slug, so an operation with two
 * approval-gated permissions (the primitive and its backing domain tool)
 * replayed into a fresh approval forever; a non-normalized path did the same;
 * and identical retries created duplicate pending approvals.
 */
class VfsApprovalCoverageTest extends TestCase
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

    // Composed path: the VFS policy sees both gated tools and records ONE
    // approval covering both; approving it applies the patch.
    public function test_double_gated_table_row_patch_applies_after_one_composed_approval(): void
    {
        [$row, $column, $path] = $this->tableRow();
        $this->gate('vfs_patch');
        $this->gate('update_table_row');

        $response = json_decode($this->patchTool()->handle(new Request(['path' => $path, 'patch' => json_encode(['data' => ['Status' => 'active']])])), true);
        // Phase 2: approval-pending is the non-error pending_approval envelope.
        $this->assertSame('pending_approval', $response['status']);
        $this->assertSame(['vfs_patch', 'update_table_row'], $response['approval_tools']);

        $approval = ApprovalRequest::query()->sole();
        $this->assertSame(['vfs_patch', 'update_table_row'], $approval->tool_execution_context['vfs_approval']['approved_tools']);

        app(ApprovalExecutionService::class)->resolve($approval, 'approved', $this->workspaceApprover());

        $this->assertSame('active', $row->fresh()->data[$column->id]);
        $this->assertSame(1, ApprovalRequest::query()->count());
    }

    // Wrapper path: the model-visible primitive is itself gated, so the
    // runtime's ApprovalWrappedTool records the approval. Replaying it must
    // also satisfy the gated backing tool instead of asking again.
    public function test_double_gated_table_row_patch_applies_after_one_wrapper_approval(): void
    {
        [$row, $column, $path] = $this->tableRow();
        $this->gate('vfs_patch');
        $this->gate('update_table_row');

        $wrapped = new ApprovalWrappedTool($this->patchTool(), $this->agent, 'vfs_patch');
        // Phase 2: gated vfs_* primitives answer with the pending_approval JSON.
        $this->assertStringContainsString('"status": "pending_approval"', $wrapped->handle(new Request(['path' => $path, 'patch' => json_encode(['data' => ['Status' => 'active']])])));

        app(ApprovalExecutionService::class)->resolve(ApprovalRequest::query()->sole(), 'approved', $this->workspaceApprover());

        $this->assertSame('active', $row->fresh()->data[$column->id]);
        $this->assertSame(1, ApprovalRequest::query()->count());
    }

    // vfs_exec + vfs_write: a mutating shell command needs both. One approval
    // through either origin must run it.
    public function test_double_gated_exec_write_runs_after_one_approval_from_either_origin(): void
    {
        $this->gate('vfs_exec');
        $this->gate('vfs_write');

        $wrapped = new ApprovalWrappedTool($this->execTool(), $this->agent, 'vfs_exec');
        $wrapped->handle(new Request(['command' => 'mkdir /files/from-wrapper']));
        app(ApprovalExecutionService::class)->resolve(ApprovalRequest::query()->sole(), 'approved', $this->workspaceApprover());
        $this->assertTrue(WorkspaceFile::forWorkspace()->where('name', 'from-wrapper')->where('is_folder', true)->exists());
        $this->assertSame(1, ApprovalRequest::query()->count());

        $response = json_decode($this->execTool()->handle(new Request(['command' => 'mkdir /files/from-policy'])), true);
        $this->assertSame(['vfs_exec', 'vfs_write'], $response['approval_tools']);
        $approval = ApprovalRequest::query()->where('status', 'pending')->sole();
        app(ApprovalExecutionService::class)->resolve($approval, 'approved', $this->workspaceApprover());

        $this->assertTrue(WorkspaceFile::forWorkspace()->where('name', 'from-policy')->where('is_folder', true)->exists());
        $this->assertSame(2, ApprovalRequest::query()->count());
    }

    // vfs_patch + update_agent_identity_file on a direct report's identity.
    public function test_double_gated_identity_patch_applies_after_one_approval(): void
    {
        $report = User::factory()->agent()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Report',
            'manager_id' => $this->agent->id,
        ]);
        $folder = app(AgentDocumentService::class)->createAgentDocumentStructure($report, ['IDENTITY' => 'Old identity']);
        $report->update(['docs_folder_id' => $folder->id]);
        $this->gate('vfs_patch');
        $this->gate('update_agent_identity_file');
        $path = "/agents/by-id/{$report->id}/identity/IDENTITY.md";
        $docId = app(OpenCompanyVfs::class)->stat($this->agent, $path)['backend_id'];

        $response = json_decode($this->patchTool()->handle(new Request([
            'path' => $path,
            'patch' => json_encode(['search' => 'Old identity', 'replace' => 'Approved identity']),
        ])), true);
        $this->assertSame(['vfs_patch', 'update_agent_identity_file'], $response['approval_tools']);

        app(ApprovalExecutionService::class)->resolve(ApprovalRequest::query()->sole(), 'approved', $this->workspaceApprover());

        $this->assertSame('Approved identity', Document::findOrFail($docId)->content);
        $this->assertSame(1, ApprovalRequest::query()->count());
    }

    // The replay subject is normalized exactly like the policy subject, so a
    // path such as /docs//by-id/{id}/ is honored on replay.
    public function test_non_normalized_path_replays_without_re_approval(): void
    {
        $folder = Document::factory()->create(['title' => 'Allowed', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $doc = Document::factory()->create(['title' => 'Runbook', 'parent_id' => $folder->id, 'content' => 'Old path', 'author_id' => $this->agent->id]);
        $this->permission('folder', $folder->id);
        $this->gate('vfs_patch');

        $wrapped = new ApprovalWrappedTool($this->patchTool(), $this->agent, 'vfs_patch');
        $wrapped->handle(new Request(['path' => "/docs//by-id/{$doc->id}/", 'patch' => json_encode(['search' => 'Old', 'replace' => 'New'])]));

        app(ApprovalExecutionService::class)->resolve(ApprovalRequest::query()->sole(), 'approved', $this->workspaceApprover());

        $this->assertSame('New path', $doc->fresh()->content);
        $this->assertSame(1, ApprovalRequest::query()->count());
    }

    // Retrying the identical gated call while its approval is pending returns
    // the same approval id instead of flooding approvers.
    public function test_identical_retry_returns_the_existing_pending_approval(): void
    {
        [, , $path] = $this->tableRow();
        $this->gate('update_table_row');
        $request = ['path' => $path, 'patch' => json_encode(['data' => ['Status' => 'active']])];

        $first = json_decode($this->patchTool()->handle(new Request($request)), true);
        $second = json_decode($this->patchTool()->handle(new Request($request)), true);

        // Phase 2: pending_approval envelope (non-error) instead of approval_required.
        $this->assertSame('pending_approval', $second['status']);
        $this->assertSame($first['approval_id'], $second['approval_id']);
        $this->assertSame(1, ApprovalRequest::query()->count());

        // A different payload is a different operation.
        $this->patchTool()->handle(new Request(['path' => $path, 'patch' => json_encode(['data' => ['Status' => 'lost']])]));
        $this->assertSame(2, ApprovalRequest::query()->count());
    }

    /**
     * @return array{0: DataTableRow, 1: DataTableColumn, 2: string}
     */
    private function tableRow(): array
    {
        $table = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Gated Customers', 'created_by' => $this->agent->id]);
        $column = DataTableColumn::create(['table_id' => $table->id, 'name' => 'Status', 'type' => 'text', 'order' => 0, 'required' => false]);
        $row = DataTableRow::create(['table_id' => $table->id, 'data' => [$column->id => 'lead'], 'created_by' => $this->agent->id]);

        return [$row, $column, "/tables/gated-customers/rows/by-id/{$row->id}.json"];
    }

    private function patchTool(): VfsPatch
    {
        return app(VfsPatch::class, ['agent' => $this->agent, 'vfs' => app(OpenCompanyVfs::class)]);
    }

    private function execTool(): VfsExec
    {
        return app(VfsExec::class, ['agent' => $this->agent, 'executor' => app(VfsCommandExecutor::class)]);
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

    /**
     * A human admin of the test workspace: ApprovalResolutionPolicy refuses
     * unattributed (null) responders, so approvals are resolved by a member.
     */
    private function workspaceApprover(): User
    {
        $approver = User::factory()->create(['type' => 'human']);
        \App\Models\WorkspaceMember::query()->firstOrCreate(
            ['workspace_id' => $this->workspace->id, 'user_id' => $approver->id],
            ['role' => 'admin'],
        );

        return $approver;
    }
}

<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Agents\Tools\Vfs\VfsExec;
use App\Agents\Tools\Vfs\VfsPatch;
use App\Agents\Tools\Vfs\VfsWrite;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Events\VfsEventSink;
use App\Domain\Vfs\Events\VfsOperationContext;
use App\Domain\Vfs\OpenCompany\Approvals\VfsSecretRedactor;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Jobs\AgentRespondJob;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\Routine;
use App\Models\Document;
use App\Models\DocumentPermission;
use App\Models\User;
use App\Models\VfsOperationEvent;
use App\Models\Workspace;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceMember;
use App\Services\AgentDocumentService;
use App\Services\ApprovalExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Regressions for the governance/audit review of the VFS hardening branch
 * (/tmp/vfs-audit/review-governance.md) and the audit bugs found by the real
 * tool eval pilot (docs/architecture/vfs-agent-friendliness-audit-2026-11.md
 * §9.4, bugs 2–5).
 *
 * Every scenario here was a verified leak or a wrong audit record: hidden
 * documents readable through the trash, recursive deletes reaching denied
 * descendants, approval records crossing workspaces, approval-gated reads
 * served without approval, JSON credentials surviving redaction, hidden names
 * in path suggestions, `/me` approvals that never applied, pending replays
 * reported as success, and audit rows that were dropped or mislabeled.
 */
class VfsReviewFixesGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private OpenCompanyVfs $vfs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create(['name' => 'Atlas', 'status' => 'idle']);
        $this->vfs = app(OpenCompanyVfs::class);
    }

    // P0: a folder-denied document became readable once a human trashed it,
    // because trash access checked only folder allows.
    public function test_trash_applies_the_full_read_policy(): void
    {
        $finance = Document::factory()->create(['title' => 'Finance', 'is_folder' => true]);
        $payroll = Document::factory()->create(['title' => 'Payroll', 'parent_id' => $finance->id, 'content' => 'PAYROLL_SECRET']);
        $this->permission('folder', $finance->id, 'deny');

        $this->assertVfsCode('permission_denied', fn () => $this->vfs->read($this->agent, "/docs/by-id/{$payroll->id}"));

        $payroll->delete();

        $this->assertVfsCode('not_found', fn () => $this->vfs->read($this->agent, "/docs/.trash/{$payroll->id}"));
        $this->assertStringNotContainsString($payroll->id, $this->vfs->read($this->agent, '/docs/.trash'));

        // Trashing the denied folder itself keeps both it and its child hidden:
        // the chain walk includes trashed ancestors.
        $finance->delete();
        $this->assertVfsCode('not_found', fn () => $this->vfs->read($this->agent, "/docs/.trash/{$finance->id}"));
        $this->assertStringNotContainsString('PAYROLL_SECRET', $this->vfsReadQuietly("/docs/.trash/{$finance->id}/{$payroll->id}"));
    }

    // P0 (same root cause): parent allow + child deny, and sharing rows that
    // exclude the agent, must also hide trashed documents.
    public function test_trash_respects_child_denies_and_sharing_rows(): void
    {
        $company = Document::factory()->create(['title' => 'Company', 'is_folder' => true]);
        $finance = Document::factory()->create(['title' => 'Finance', 'is_folder' => true, 'parent_id' => $company->id]);
        $budget = Document::factory()->create(['title' => 'Budget', 'parent_id' => $finance->id, 'content' => 'DENIED_CHILD']);
        $shared = Document::factory()->create(['title' => 'Offer', 'parent_id' => $company->id, 'content' => 'SHARED_ONLY']);
        $visible = Document::factory()->create(['title' => 'Handbook', 'parent_id' => $company->id, 'content' => 'welcome']);
        $this->permission('folder', $company->id, 'allow');
        $this->permission('folder', $finance->id, 'deny');
        $human = User::factory()->create(['type' => 'human']);
        DocumentPermission::create(['id' => (string) Str::uuid(), 'document_id' => $shared->id, 'user_id' => $human->id, 'role' => 'viewer']);

        $budget->delete();
        $shared->delete();
        $visible->delete();

        $this->assertVfsCode('not_found', fn () => $this->vfs->read($this->agent, "/docs/.trash/{$budget->id}"));
        $this->assertVfsCode('not_found', fn () => $this->vfs->read($this->agent, "/docs/.trash/{$shared->id}"));
        $this->assertSame('welcome', $this->vfs->read($this->agent, "/docs/.trash/{$visible->id}"));

        $listing = $this->vfs->read($this->agent, '/docs/.trash');
        $this->assertStringContainsString($visible->id, $listing);
        $this->assertStringNotContainsString($budget->id, $listing);
        $this->assertStringNotContainsString($shared->id, $listing);
    }

    // P0: rm -r of an allowed folder trashed an explicitly denied subfolder.
    // The whole operation must refuse, naming how many descendants block it.
    public function test_recursive_trash_refuses_when_a_descendant_is_denied(): void
    {
        $company = Document::factory()->create(['title' => 'Company', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $finance = Document::factory()->create(['title' => 'Finance', 'is_folder' => true, 'parent_id' => $company->id]);
        $payroll = Document::factory()->create(['title' => 'Payroll', 'parent_id' => $finance->id, 'content' => 'SECRET']);
        $memo = Document::factory()->create(['title' => 'Memo', 'parent_id' => $company->id, 'content' => 'ok']);
        $this->permission('folder', $company->id, 'allow');
        $this->permission('folder', $finance->id, 'deny');
        $this->permission('tool', 'delete_document', 'allow');

        try {
            $this->vfs->remove($this->agent, "/docs/by-id/{$company->id}");
            $this->fail('Recursive trash of a folder with a denied descendant must refuse.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
            $this->assertSame(2, $e->context['blocked_descendants']);
            // The denied descendants are hidden, so their names never appear.
            $this->assertStringNotContainsString('Finance', json_encode([$e->getMessage(), $e->context]));
            $this->assertStringNotContainsString('Payroll', json_encode([$e->getMessage(), $e->context]));
        }

        // Nothing was trashed: refusal is for the whole operation.
        $this->assertNull($company->fresh()->deleted_at);
        $this->assertNull($memo->fresh()->deleted_at);
        $this->assertNull($payroll->fresh()->deleted_at);
    }

    // P0 (move half): moving a parent changes its descendants' inherited
    // access, so a denied or sharing-restricted descendant blocks the move.
    public function test_folder_move_refuses_when_a_descendant_is_write_protected(): void
    {
        $company = Document::factory()->create(['title' => 'Company', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $projects = Document::factory()->create(['title' => 'Projects', 'is_folder' => true, 'parent_id' => $company->id, 'author_id' => $this->agent->id]);
        $archive = Document::factory()->create(['title' => 'Archive', 'is_folder' => true, 'parent_id' => $company->id, 'author_id' => $this->agent->id]);
        $plan = Document::factory()->create(['title' => 'Plan', 'parent_id' => $projects->id, 'content' => 'x']);
        $human = User::factory()->create(['type' => 'human']);
        // Readable (viewer) but not writable: shared with the agent as viewer.
        DocumentPermission::create(['id' => (string) Str::uuid(), 'document_id' => $plan->id, 'user_id' => $this->agent->id, 'role' => 'viewer']);
        DocumentPermission::create(['id' => (string) Str::uuid(), 'document_id' => $plan->id, 'user_id' => $human->id, 'role' => 'editor']);
        $this->permission('folder', $company->id, 'allow');
        $this->permission('tool', 'update_document', 'allow');

        try {
            $this->vfs->move($this->agent, "/docs/by-id/{$projects->id}", "/docs/by-id/{$archive->id}");
            $this->fail('Moving a folder with a write-protected descendant must refuse.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
            $this->assertSame(1, $e->context['blocked_descendants']);
            // A readable descendant is named so the agent can act on it.
            $this->assertSame("/docs/by-id/{$plan->id}", $e->context['first_blocked']);
        }

        $this->assertSame($company->id, $projects->fresh()->parent_id);
    }

    // P0: approval reads scoped by the requester's *current* workspace, so a
    // reassigned agent could read approvals from its previous tenant.
    public function test_approval_reads_stay_in_the_approval_workspace(): void
    {
        $approval = ApprovalRequest::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'requester_id' => $this->agent->id,
            'type' => 'action',
            'title' => 'Private tenant A action',
            'status' => 'pending',
            'description' => 'Confidential acquisition',
        ]);
        $this->assertStringContainsString('Confidential acquisition', $this->vfs->read($this->agent, "/approvals/{$approval->id}.json"));

        $other = Workspace::create(['name' => 'Other', 'slug' => 'other']);
        $this->agent->update(['workspace_id' => $other->id]);
        app()->instance('currentWorkspace', $other);

        $this->assertVfsCode('not_found', fn () => $this->vfs->read($this->agent, "/approvals/{$approval->id}.json"));
        $this->assertStringNotContainsString($approval->id, $this->vfs->read($this->agent, '/approvals'));
        $this->assertStringNotContainsString($approval->id, $this->vfs->read($this->agent, '/me/pending'));
    }

    // P0: list_members allowed only with approval still let the members file
    // expose emails immediately.
    public function test_member_emails_respect_the_list_members_approval_gate(): void
    {
        $human = User::factory()->create(['type' => 'human', 'email' => 'private@example.test']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $human->id, 'role' => 'member']);
        $this->permission('tool', 'list_members', 'allow', requiresApproval: true);

        $out = $this->vfs->read($this->agent, '/workspace/members.json');

        $this->assertStringNotContainsString('private@example.test', $out);
        $this->assertStringContainsString('list_members', $out, 'The redaction names the approval-gated tool.');
        $this->assertSame(0, ApprovalRequest::count(), 'Reads never auto-create approvals.');

        AgentPermission::query()->where('scope_key', 'list_members')->update(['requires_approval' => false]);
        $this->assertStringContainsString('private@example.test', $this->vfs->read($this->agent, '/workspace/members.json'));
    }

    // P0: an approval-gated get_routine still let /routines/{id}.json
    // reveal instructions and scripts.
    public function test_routine_bodies_respect_the_get_routine_approval_gate(): void
    {
        $routine = Routine::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Nightly',
            'kind' => 'agent',
            'instructions' => 'PRIVATE_INSTRUCTIONS',
            'agent_id' => $this->agent->id,
            'created_by_id' => $this->agent->id,
            'cron_expression' => '0 0 * * *',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $this->permission('tool', 'get_routine', 'allow', requiresApproval: true);

        $payload = json_decode($this->vfs->read($this->agent, "/routines/{$routine->id}.json"), true);

        $this->assertArrayNotHasKey('instructions', $payload);
        $this->assertContains('instructions', $payload['redacted_fields']);
        $this->assertStringContainsString('approval', $payload['redaction_reason']);
        $this->assertSame(0, ApprovalRequest::count());
    }

    // P0: credentials inside JSON strings (content, previews, nested JSON)
    // survived redaction because only unquoted key=value forms matched.
    public function test_json_encoded_credentials_are_redacted(): void
    {
        $nested = json_encode(['config' => json_encode(['client_secret' => 'nested-secret-value', 'region' => 'eu'])]);
        $out = VfsSecretRedactor::redact(['parameters' => [
            'content' => '{"password":"custom-private-value","api_key":"custom-key-value","author_id":"keep-me"}',
            'diff' => "-  'access_token': 'single-quoted-value',\n+  \"note\": \"fine\"",
            'env' => 'DB_PASSWORD="quoted-env-value"',
            'nested' => $nested,
        ]]);

        $flat = json_encode($out);
        foreach (['custom-private-value', 'custom-key-value', 'single-quoted-value', 'quoted-env-value', 'nested-secret-value'] as $secret) {
            $this->assertStringNotContainsString($secret, $flat);
        }
        $this->assertStringContainsString('keep-me', $flat);
        $this->assertStringContainsString('"note": "fine"', $out['parameters']['diff']);
        $this->assertStringContainsString('eu', $out['parameters']['nested']);
    }

    // The approval mount redacts its copy only; stored replay parameters stay
    // byte-identical so the approved call still replays.
    public function test_approval_json_redacts_without_changing_stored_parameters(): void
    {
        $content = '{"password":"custom-private-value"}';
        $approval = ApprovalRequest::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'requester_id' => $this->agent->id,
            'type' => 'action',
            'title' => 'Tool: vfs_write',
            'description' => 'Write a config file',
            'status' => 'pending',
            'tool_execution_context' => ['tool_slug' => 'vfs_write', 'parameters' => ['path' => '/files/c.json', 'content' => $content]],
        ]);

        $this->assertStringNotContainsString('custom-private-value', $this->vfs->read($this->agent, "/approvals/{$approval->id}.json"));
        $this->assertSame($content, $approval->fresh()->tool_execution_context['parameters']['content']);
    }

    // P0: not_found suggestions and ambiguity lists were built from every
    // sibling, so a typo revealed a denied folder's name and id.
    public function test_path_suggestions_never_reveal_hidden_documents(): void
    {
        $secret = Document::factory()->create(['title' => 'Secret Acquisition', 'is_folder' => true]);
        Document::factory()->create(['title' => 'Target List', 'parent_id' => $secret->id, 'content' => 'x']);
        Document::factory()->create(['title' => 'Secret Acquisitions Public', 'is_folder' => true]);
        $this->permission('folder', $secret->id, 'deny');

        try {
            $this->vfs->read($this->agent, '/docs/secret-acquisitio');
            $this->fail('unexpected success');
        } catch (VfsError $e) {
            $this->assertSame('not_found', $e->errorCode);
            $leak = json_encode([$e->getMessage(), $e->context]);
            $this->assertStringNotContainsString('/docs/secret-acquisition"', $leak);
            $this->assertStringNotContainsString($secret->id, $leak);
            // Visible near-matches are still suggested.
            $this->assertContains('/docs/secret-acquisitions-public', $e->context['did_you_mean']);
        }

        // Traversal below a hidden folder never suggests its children.
        try {
            $this->vfs->read($this->agent, '/docs/secret-acquisition/target-lis');
            $this->fail('unexpected success');
        } catch (VfsError $e) {
            $this->assertStringNotContainsString('target-list', json_encode([$e->getMessage(), $e->context]));
        }
    }

    // P0 (ambiguity half): duplicate names listed hidden candidates' ids.
    public function test_ambiguous_paths_list_only_visible_candidates(): void
    {
        $hidden = Document::factory()->create(['title' => 'Plans', 'is_folder' => true]);
        $visibleA = Document::factory()->create(['title' => 'Plans', 'is_folder' => true]);
        $visibleB = Document::factory()->create(['title' => 'plans', 'is_folder' => true]);
        $this->permission('folder', $hidden->id, 'deny');

        try {
            $this->vfs->read($this->agent, '/docs/plans');
            $this->fail('unexpected success');
        } catch (VfsError $e) {
            $this->assertSame('ambiguous_match', $e->errorCode);
            $this->assertSame(2, $e->context['count']);
            $this->assertStringNotContainsString(substr($hidden->id, -8), json_encode($e->context));
        }

        // With only one visible candidate the friendly path resolves to it.
        $visibleB->delete();
        $this->assertIsString($this->vfs->read($this->agent, '/docs/plans'));
        $this->assertSame($visibleA->id, $this->vfs->stat($this->agent, '/docs/plans')['backend_id']);
    }

    // P1 + P1: a wrapper-gated vfs_patch of /me/... stored the alias subject,
    // replay compared it with the backing subject, asked again, and the
    // second pending approval was recorded as "succeeded".
    public function test_me_wrapper_approval_applies_after_one_approval(): void
    {
        $folder = app(AgentDocumentService::class)->createAgentDocumentStructure($this->agent, ['IDENTITY' => 'Old identity']);
        $this->agent->update(['docs_folder_id' => $folder->id]);
        $this->permission('tool', 'vfs_patch', 'allow', requiresApproval: true);
        $this->permission('tool', 'update_agent_identity_file', 'allow');

        $wrapped = new ApprovalWrappedTool(new VfsPatch($this->agent, $this->vfs), $this->agent, 'vfs_patch');
        $wrapped->handle(new Request(['path' => '/me/identity/IDENTITY.md', 'patch' => json_encode(['search' => 'Old', 'replace' => 'New'])]));

        app(ApprovalExecutionService::class)->resolve(ApprovalRequest::query()->sole(), 'approved', $this->human());

        $this->assertSame(1, ApprovalRequest::count());
        $approved = ApprovalRequest::query()->sole();
        $this->assertSame('succeeded', $approved->tool_execution_context['execution']['disposition']);
        $this->assertSame('New identity', $this->vfs->read($this->agent, '/me/identity/IDENTITY.md'));
    }

    // P1: a replay that returns another pending_approval (here: the approved
    // vfs_exec reaches a separately gated delete_document) must be recorded
    // as approval_pending, keep the agent parked on the new approval, and
    // never announce success or resume the agent.
    public function test_replay_that_needs_another_approval_is_not_recorded_as_success(): void
    {
        Queue::fake();
        $doc = Document::factory()->create(['title' => 'Old', 'author_id' => $this->agent->id, 'content' => 'x']);
        $folder = Document::factory()->create(['title' => 'Box', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $doc->update(['parent_id' => $folder->id]);
        $this->permission('folder', $folder->id, 'allow');
        $this->permission('tool', 'vfs_exec', 'allow', requiresApproval: true);
        $this->permission('tool', 'vfs_write', 'allow');
        $this->permission('tool', 'delete_document', 'allow', requiresApproval: true);
        $this->agent->update(['must_wait_for_approval' => true]);

        $wrapped = new ApprovalWrappedTool(new VfsExec($this->agent, app(VfsCommandExecutor::class)), $this->agent, 'vfs_exec');
        $wrapped->handle(new Request(['command' => "rm /docs/by-id/{$doc->id}"]));
        $first = ApprovalRequest::query()->sole();
        $this->assertSame($first->id, $this->agent->fresh()->awaiting_approval_id);

        app(ApprovalExecutionService::class)->resolve($first, 'approved', $this->human());

        $this->assertNull($doc->fresh()->deleted_at, 'The gated delete did not run.');
        $second = ApprovalRequest::query()->where('status', 'pending')->sole();
        $execution = $first->fresh()->tool_execution_context['execution'];
        $this->assertSame('approval_pending', $execution['disposition']);
        $this->assertSame($second->id, $execution['pending_approval_id']);
        $this->assertSame($second->id, $this->agent->fresh()->awaiting_approval_id);
        Queue::assertNotPushed(AgentRespondJob::class);
    }

    // Audit bug 2: read-path denials carried no idempotency key and the sink
    // dropped keyless read events, so denials were never persisted.
    public function test_read_denials_are_always_persisted(): void
    {
        $hr = Document::factory()->create(['title' => 'HR', 'is_folder' => true]);
        $salaries = Document::factory()->create(['title' => 'Salaries', 'parent_id' => $hr->id, 'content' => 'x']);
        $this->permission('folder', $hr->id, 'deny');

        $this->assertVfsCode('permission_denied', fn () => $this->vfs->read($this->agent, "/docs/by-id/{$salaries->id}"));
        $this->assertVfsCode('permission_denied', fn () => $this->vfs->read($this->agent, "/docs/by-id/{$salaries->id}"));
        app(VfsEventSink::class)->flush();

        $denials = VfsOperationEvent::query()->where('type', 'vfs.denied')->get();
        $this->assertCount(2, $denials, 'Every denial is a separate audit row.');
        $this->assertSame('read', $denials->first()->effect);
        $this->assertSame("/docs/by-id/{$salaries->id}", $denials->first()->path);
    }

    // Audit bug 3: tool-call rows always said surface `tool`, even when the
    // call came through the computer bridge or Code Mode.
    public function test_tool_call_rows_carry_the_caller_surface(): void
    {
        $this->filesDisk();
        $this->permission('file_folder', '*', 'allow');

        app(VfsOperationContext::class)->runAs('computer', fn () => (new VfsWrite($this->agent, $this->vfs))->handle(new Request(['path' => '/files/a.md', 'content' => 'hi'])));
        app(VfsOperationContext::class)->runAs('code', fn () => (new VfsExec($this->agent, app(VfsCommandExecutor::class)))->handle(new Request(['command' => 'cat /files/a.md'])));
        app(VfsEventSink::class)->flush();

        $surfaces = VfsOperationEvent::query()->where('type', 'vfs.tool_call')->orderBy('id')->pluck('surface')->all();
        $this->assertSame(['computer', 'code'], $surfaces);
    }

    // Audit bugs 4 and 5: create events had no backend_id, and parent folders
    // created implicitly by a write were never audited.
    public function test_create_events_carry_backend_ids_and_implicit_parents_are_audited(): void
    {
        $this->filesDisk();
        $this->permission('file_folder', '*', 'allow');

        $result = $this->vfs->write($this->agent, '/files/a/b/new.md', 'hello');
        app(VfsEventSink::class)->flush();

        $created = VfsOperationEvent::query()->where('type', 'vfs.file.created')->sole();
        $this->assertNotNull($created->backend_id);
        $this->assertSame($result['canonical_path'], $created->canonical_path);

        $directories = VfsOperationEvent::query()->where('type', 'vfs.directory.created')->orderBy('id')->get();
        $this->assertSame(['/files/a', '/files/a/b'], $directories->pluck('path')->all());
        $this->assertNotContains(null, $directories->pluck('backend_id')->all());
        $this->assertTrue($directories->every(fn (VfsOperationEvent $event): bool => (bool) $event->committed));

        // Docs: mkdir -p records every folder it creates, not only the leaf.
        $root = Document::factory()->create(['title' => 'Root', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $this->permission('folder', $root->id, 'allow');
        $this->permission('tool', 'create_document', 'allow');
        $this->vfs->makeDirectory($this->agent, '/docs/root/x/y');
        app(VfsEventSink::class)->flush();

        $docDirectories = VfsOperationEvent::query()->where('type', 'vfs.directory.created')->where('path', 'like', '/docs/%')->orderBy('id')->pluck('path')->all();
        $this->assertSame(['/docs/root/x', '/docs/root/x/y'], $docDirectories);
    }

    private function vfsReadQuietly(string $path): string
    {
        try {
            return $this->vfs->read($this->agent, $path);
        } catch (VfsError $e) {
            return $e->errorCode;
        }
    }

    private function human(): User
    {
        $human = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $human->id, 'role' => 'admin']);

        return $human;
    }

    private function filesDisk(): void
    {
        WorkspaceDisk::create([
            'id' => 'disk-local',
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
    }

    private function permission(string $scopeType, string $scopeKey, string $permission, bool $requiresApproval = false): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'permission' => $permission,
            'requires_approval' => $requiresApproval,
        ]);
    }

    private function assertVfsCode(string $code, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected VfsError {$code}.");
        } catch (VfsError $e) {
            $this->assertSame($code, $e->errorCode, $e->getMessage());
        }
    }
}

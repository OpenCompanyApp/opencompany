<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\ToolRegistry;
use App\Agents\Tools\Vfs\VfsExec;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsCommandSpecRegistry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsOperationLog;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\DataTableView;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentVersion;
use App\Models\ListItem;
use App\Models\Message;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Models\VfsOperationEvent;
use App\Models\Workspace;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceMember;
use App\Services\AgentDocumentService;
use App\Services\ApprovalExecutionService;
use App\Services\CodeApiDocGenerator;
use App\Services\CodeBridge;
use App\Services\CodeExecutionResult;
use App\Services\FileSystemService;
use App\Services\MrubySandboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\Concerns\GrantsAgentPermissions;
use Tests\TestCase;

class VfsFeatureTest extends TestCase
{
    use GrantsAgentPermissions;
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create([
            'name' => 'Atlas',
            'status' => 'idle',
        ]);

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

    // Eval finding: models reach for GNU long flags (`rg --files-with-matches`).
    // They map onto the implemented short flags instead of failing preflight;
    // quoted patterns that look like flags stay literal.
    public function test_grep_and_rg_accept_common_long_flag_synonyms(): void
    {
        Document::factory()->create(['title' => 'Refund Policy', 'content' => "Refund within 30 days.\n", 'author_id' => $this->agent->id]);
        Document::factory()->create(['title' => 'Other', 'content' => 'Nothing here.', 'author_id' => $this->agent->id]);
        $exec = app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent);

        $long = json_decode($exec->handle(new Request(['command' => 'rg --files-with-matches --ignore-case refund /docs'])), true);
        $short = json_decode($exec->handle(new Request(['command' => 'rg -l -i refund /docs'])), true);

        $this->assertTrue($long['ok'], json_encode($long));
        $this->assertSame($short['stdout'], $long['stdout']);
        $this->assertStringContainsString('/docs/', $long['stdout']);
    }

    public function test_vfs_tools_are_registered_as_direct_agent_tools(): void
    {
        $slugs = app(ToolRegistry::class)->getToolSlugsForAgent($this->agent);

        $this->assertContains('vfs_exec', $slugs);
        $this->assertContains('vfs_patch', $slugs);
        $this->assertContains('vfs_write', $slugs);
        $this->assertNotContains('vfs_ls', $slugs);
        $this->assertNotContains('vfs_exists', $slugs);

        $toolMeta = collect(app(ToolRegistry::class)->getAllToolsMeta($this->agent))->pluck('id')->all();
        $this->assertContains('vfs_exec', $toolMeta);
        $this->assertNotContains('vfs_ls', $toolMeta);
    }

    public function test_vfs_atlas_artisan_helper_executes_commands_as_atlas(): void
    {
        $otherWorkspace = Workspace::create(['name' => 'Other Atlas Workspace', 'slug' => 'other-atlas']);
        User::factory()->agent()->create([
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Atlas',
        ]);

        $exit = Artisan::call('vfs:atlas', [
            'vfsCommand' => ['pwd'],
            '--json' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"id": "'.$this->agent->id.'"', $output);
        $this->assertStringContainsString('"name": "Atlas"', $output);
        $this->assertStringContainsString('"command": "pwd"', $output);
        // POSIX line output: pwd ends with a newline.
        $this->assertStringContainsString('"stdout": "/\n"', $output);

        $primitiveExit = Artisan::call('vfs:atlas', [
            '--primitive' => 'stat',
            '--arg' => ['path=/'],
            '--json' => true,
        ]);
        $primitiveOutput = Artisan::output();

        $this->assertSame(0, $primitiveExit);
        $this->assertStringContainsString('"primitive": "vfs_stat"', $primitiveOutput);
        $this->assertStringContainsString('"path": "/"', $primitiveOutput);

        $missingExit = Artisan::call('vfs:atlas', [
            'vfsCommand' => ['pwd'],
            '--agent' => 'missing-agent',
        ]);

        $this->assertSame(1, $missingExit);
    }

    public function test_vfs_atlas_artisan_helper_smoke_matrix_covers_shell_and_primitives(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Atlas Smoke',
            'created_by' => $this->agent->id,
        ]);
        $column = DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'Status',
            'type' => 'text',
            'order' => 0,
            'required' => false,
        ]);
        $row = DataTableRow::create([
            'table_id' => $table->id,
            'data' => [$column->id => 'lead'],
            'created_by' => $this->agent->id,
        ]);
        $canonicalFolder = app(FileSystemService::class)->createFolder($this->workspace->id, null, 'atlas-canonical', $this->agent->id);

        $root = $this->runAtlasCommand('ls /');
        $this->assertTrue($root['ok']);
        $this->assertStringContainsString('tools/', $root['stdout']);

        $help = $this->runAtlasCommand('help grep', ['--spec' => true]);
        $this->assertTrue($help['ok']);
        $this->assertStringContainsString('Search literal or regex text.', $help['stdout']);
        $this->assertArrayHasKey('command_spec', $help['diagnostics']);

        $unsupportedHelp = $this->runAtlasCommand('curl --help');
        $this->assertTrue($unsupportedHelp['ok']);
        $this->assertStringContainsString('unsupported', strtolower($unsupportedHelp['stdout']));

        $tableStat = $this->runAtlasPrimitive('stat', ['path' => "/tables/atlas-smoke/rows/by-id/{$row->id}.json"]);
        $this->assertTrue($tableStat['ok']);
        $this->assertContains('patch', $tableStat['response']['result']['capabilities']);

        $tableRead = $this->runAtlasPrimitive('read', ['path' => "/tables/atlas-smoke/rows/by-id/{$row->id}.json"]);
        $this->assertTrue($tableRead['ok']);
        $this->assertStringContainsString('"Status"', $tableRead['response']['result']['content']);

        $generatedRead = $this->runAtlasPrimitive('read', ['path' => '/tools/vfs.md', 'maxBytes' => 120]);
        $this->assertTrue($generatedRead['ok']);
        $this->assertTrue($generatedRead['response']['result']['truncated']);

        $displayWrite = $this->runAtlasCommand('mkdir /files/atlas-smoke && printf display > /files/atlas-smoke/display.txt && cat /files/atlas-smoke/display.txt');
        $this->assertTrue($displayWrite['ok']);
        $this->assertStringEndsWith('display', $displayWrite['stdout']);

        $canonicalWrite = $this->runAtlasCommand("printf canonical > /files/by-id/{$canonicalFolder->id}/canonical.txt && cat /files/by-id/{$canonicalFolder->id}/canonical.txt");
        $this->assertTrue($canonicalWrite['ok']);
        $this->assertStringEndsWith('canonical', $canonicalWrite['stdout']);

        $readOnlyWrite = $this->runAtlasCommand('printf nope > /workspace/settings.json', expectedExit: 1);
        $this->assertFalse($readOnlyWrite['ok']);

        $chained = $this->runAtlasCommand('false && printf skipped > /files/atlas-smoke/skipped.txt || printf fallback');
        $this->assertTrue($chained['ok']);
        $this->assertSame('fallback', $chained['stdout']);
        $this->assertFalse(WorkspaceFile::forWorkspace()->where('name', 'skipped.txt')->exists());
    }

    public function test_vfs_exec_browses_reads_and_searches_documents(): void
    {
        $doc = Document::factory()->create([
            'title' => 'Refund Policy',
            'content' => "Refunds require approval.\nEscalate enterprise refunds.",
            'author_id' => $this->agent->id,
        ]);

        $executor = app(VfsCommandExecutor::class);

        $root = $executor->execute($this->agent, 'ls /');
        $this->assertStringContainsString('docs/', $root['stdout']);

        $content = $executor->execute($this->agent, "cat /docs/by-id/{$doc->id}");
        $this->assertStringContainsString('Refunds require approval', $content['stdout']);

        // Search output uses readable friendly paths (canonical in result data).
        $search = $executor->execute($this->agent, 'rg enterprise /docs');
        $this->assertStringContainsString('/docs/refund-policy', $search['stdout']);

        $structured = app(OpenCompanyVfs::class)->search($this->agent, 'enterprise', ['/docs']);
        $this->assertSame('source_index+live', $structured[0]['backend']);
    }

    public function test_vfs_patch_updates_documents_with_search_replace(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        $folder = Document::factory()->create([
            'title' => 'Patch Allowed',
            'is_folder' => true,
            'author_id' => $this->agent->id,
        ]);
        $doc = Document::factory()->create([
            'title' => 'Runbook',
            'parent_id' => $folder->id,
            'content' => 'Old escalation path',
            'author_id' => $this->agent->id,
        ]);
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'folder',
            'scope_key' => $folder->id,
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $result = app(OpenCompanyVfs::class)->patch(
            $this->agent,
            "/docs/by-id/{$doc->id}",
            json_encode(['search' => 'Old', 'replace' => 'New'], JSON_THROW_ON_ERROR),
        );

        $this->assertSame('updated', $result['status']);
        $this->assertSame('New escalation path', $doc->fresh()->content);
        $this->assertSame('Old escalation path', DocumentVersion::where('document_id', $doc->id)->first()?->content);
    }

    public function test_vfs_patch_requires_explicit_document_folder_scope(): void
    {
        $doc = Document::factory()->create([
            'title' => 'Unscoped Doc Patch',
            'content' => 'Old unscoped content',
            'author_id' => $this->agent->id,
        ]);

        try {
            app(OpenCompanyVfs::class)->patch(
                $this->agent,
                "/docs/by-id/{$doc->id}",
                json_encode(['search' => 'Old', 'replace' => 'New'], JSON_THROW_ON_ERROR),
            );
            $this->fail('Unscoped document patch should fail.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
        }

        $stat = app(OpenCompanyVfs::class)->stat($this->agent, "/docs/by-id/{$doc->id}");
        $this->assertNotContains('patch', $stat['capabilities']);
        $this->assertSame('Old unscoped content', $doc->fresh()->content);
    }

    public function test_vfs_write_rejects_raw_document_overwrite_but_patch_still_checks_document_scope(): void
    {
        $doc = Document::factory()->create([
            'title' => 'Write Security',
            'content' => 'Original',
            'author_id' => $this->agent->id,
        ]);

        // Phase 2 (C1): documents are writable through vfs_write, but an
        // overwrite still needs an explicit folder grant containing the
        // document; a default (no grants) agent is refused.
        $this->expectExceptionMessage('Permission denied');

        app(OpenCompanyVfs::class)->write($this->agent, "/docs/by-id/{$doc->id}", 'Raw overwrite', 'overwrite', 'any-version');
    }

    public function test_vfs_patch_respects_document_folder_scope(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        $allowedFolder = Document::factory()->create(['title' => 'Allowed', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $deniedFolder = Document::factory()->create(['title' => 'Denied', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $allowedDoc = Document::factory()->create(['title' => 'Allowed Child', 'parent_id' => $allowedFolder->id, 'content' => 'Old', 'author_id' => $this->agent->id]);
        $deniedDoc = Document::factory()->create(['title' => 'Denied Child', 'parent_id' => $deniedFolder->id, 'content' => 'Old', 'author_id' => $this->agent->id]);

        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'folder',
            'scope_key' => $allowedFolder->id,
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $vfs = app(OpenCompanyVfs::class);
        $allowed = $vfs->patch($this->agent, "/docs/by-id/{$allowedDoc->id}", json_encode(['search' => 'Old', 'replace' => 'New'], JSON_THROW_ON_ERROR));
        $this->assertSame('updated', $allowed['status']);

        $this->expectExceptionMessage('Permission denied');
        $vfs->patch($this->agent, "/docs/by-id/{$deniedDoc->id}", json_encode(['search' => 'Old', 'replace' => 'New'], JSON_THROW_ON_ERROR));
    }

    public function test_vfs_patch_rejects_system_documents_and_by_id_trailing_junk(): void
    {
        $systemDoc = Document::factory()->create([
            'title' => 'IDENTITY.md',
            'content' => 'Original identity',
            'is_system' => true,
            'author_id' => $this->agent->id,
        ]);
        $ordinaryDoc = Document::factory()->create([
            'title' => 'Ordinary',
            'content' => 'Old ordinary',
            'author_id' => $this->agent->id,
        ]);

        $vfs = app(OpenCompanyVfs::class);

        try {
            $vfs->patch($this->agent, "/docs/by-id/{$systemDoc->id}", json_encode(['search' => 'Original', 'replace' => 'Mutated'], JSON_THROW_ON_ERROR));
            $this->fail('System document patch should fail.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
        }

        try {
            $vfs->patch($this->agent, "/docs/by-id/{$ordinaryDoc->id}/not-a-child", json_encode(['search' => 'Old', 'replace' => 'New'], JSON_THROW_ON_ERROR));
            $this->fail('Trailing by-id junk should not patch the parent document.');
        } catch (VfsError $e) {
            $this->assertSame('not_found', $e->errorCode);
        }

        try {
            $vfs->read($this->agent, "/docs/by-id/{$systemDoc->id}");
            $this->fail('System document reads should not be exposed through generic VFS docs.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
        }

        $this->assertSame('Original identity', $systemDoc->fresh()->content);
        $this->assertSame('Old ordinary', $ordinaryDoc->fresh()->content);
    }

    public function test_vfs_approvals_redact_secret_like_context(): void
    {
        ApprovalRequest::factory()->create([
            'id' => 'approval-secret-vfs',
            'requester_id' => $this->agent->id,
            'title' => 'api_key=title-secret',
            'description' => 'Use sk-test-secret-value-1234567890',
            'tool_execution_context' => [
                'tool' => 'integration_call',
                'api_key' => 'sk-test-secret-value-1234567890',
                'nested' => ['Authorization' => 'Bearer abcdefghijklmnopqrstuvwxyz123456'],
                'url' => 'https://example.test?token=plain-secret',
                'oauth' => 'https://example.test?access_token=oauth-secret&client_secret=client-secret',
                'basic' => 'Authorization: Basic abc def',
                'cookie' => 'Cookie: session=session-secret',
                'safe' => 'visible',
            ],
        ]);
        ApprovalRequest::factory()->create([
            'id' => 'approval-text-secret-vfs',
            'requester_id' => $this->agent->id,
            'description' => 'password=hunter2 api_key=abc123 token=plain',
        ]);
        $sameWorkspaceOtherAgent = User::factory()->agent()->create(['workspace_id' => $this->workspace->id]);
        ApprovalRequest::factory()->create([
            'id' => 'approval-same-workspace-other-agent-vfs',
            'requester_id' => $sameWorkspaceOtherAgent->id,
            'status' => 'pending',
            'description' => 'same workspace pending approval should stay private',
        ]);
        $otherWorkspace = Workspace::create(['name' => 'Other Workspace', 'slug' => 'other-workspace']);
        $otherAgent = User::factory()->agent()->create(['workspace_id' => $otherWorkspace->id]);
        ApprovalRequest::factory()->create([
            'id' => 'approval-other-workspace-vfs',
            'requester_id' => $otherAgent->id,
            'description' => 'Should not leak',
        ]);

        $payload = app(VfsCommandExecutor::class)->execute($this->agent, 'cat /approvals/approval-secret-vfs.json')['stdout'];
        $textPayload = app(VfsCommandExecutor::class)->execute($this->agent, 'cat /approvals/approval-text-secret-vfs.json')['stdout'];
        $listing = app(VfsCommandExecutor::class)->execute($this->agent, 'ls /approvals')['stdout'];

        $this->assertStringContainsString('"safe": "visible"', $payload);
        $this->assertStringContainsString('[redacted]', $payload);
        $this->assertStringNotContainsString('sk-test-secret', $payload);
        $this->assertStringNotContainsString('plain-secret', $payload);
        $this->assertStringNotContainsString('title-secret', $payload);
        $this->assertStringNotContainsString('oauth-secret', $payload);
        $this->assertStringNotContainsString('client-secret', $payload);
        $this->assertStringNotContainsString('abc def', $payload);
        $this->assertStringNotContainsString('session-secret', $payload);
        $this->assertStringNotContainsString('abcdefghijklmnopqrstuvwxyz123456', $payload);
        $this->assertStringContainsString('password=[redacted]', $textPayload);
        $this->assertStringNotContainsString('hunter2', $textPayload);
        $this->assertStringNotContainsString('approval-same-workspace-other-agent-vfs', $listing);
        $this->assertStringNotContainsString('approval-other-workspace-vfs', $listing);
        $serializedApproval = ApprovalRequest::find('approval-secret-vfs')->toArray();
        $this->assertSame('api_key=[redacted]', $serializedApproval['title']);
        $this->assertSame('[redacted]', $serializedApproval['tool_execution_context']['api_key']);
        $this->assertStringNotContainsString('sk-test-secret', json_encode($serializedApproval));

        $otherRead = app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent)->handle(new Request(['command' => 'cat /approvals/approval-same-workspace-other-agent-vfs.json']));
        $otherTrailingRead = app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent)->handle(new Request(['command' => 'cat /approvals/approval-secret-vfs.json/junk']));
        $this->assertFalse(json_decode($otherRead, true)['ok']);
        $this->assertFalse(json_decode($otherTrailingRead, true)['ok']);
    }

    public function test_vfs_exec_operation_log_redacts_secret_like_commands(): void
    {
        Event::fake([VfsOperationLog::class]);

        app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent)->handle(new Request([
            'command' => 'printf "Authorization: Basic abc def access_token=oauth-secret sk-test-secret-value-1234567890"',
        ]));

        Event::assertDispatched(VfsOperationLog::class, function (VfsOperationLog $event): bool {
            $command = (string) ($event->metadata['command'] ?? '');

            return str_contains($command, '[redacted]')
                && ! str_contains($command, 'abc def')
                && ! str_contains($command, 'oauth-secret')
                && ! str_contains($command, 'sk-test-secret');
        });
    }

    public function test_vfs_operation_events_are_persisted_with_redacted_metadata(): void
    {
        app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent)->handle(new Request([
            'command' => 'printf "api_key=sk-test-secret-value-1234567890"',
        ]));

        // Phase 2: tool calls are `vfs.tool_call` rows of the event-envelope
        // schema; cwd and the redacted command live in the payload.
        $event = VfsOperationEvent::query()->where('type', 'vfs.tool_call')->where('operation', 'vfs_exec')->firstOrFail();

        $this->assertSame($this->workspace->id, $event->workspace_id);
        $this->assertSame($this->agent->id, $event->agent_id);
        $this->assertNull($event->error_code);
        $this->assertSame('/', $event->payload['cwd']);
        $this->assertStringContainsString('[redacted]', $event->payload['command']);
        $this->assertStringNotContainsString('sk-test-secret', json_encode($event->payload));
    }

    public function test_grep_recursive_flag_keeps_literal_matching(): void
    {
        $doc = Document::factory()->create([
            'title' => 'Regex Literal Notes',
            'content' => "a.b literal\nacb regex\n",
            'author_id' => $this->agent->id,
        ]);

        $executor = app(VfsCommandExecutor::class);

        $literal = $executor->execute($this->agent, 'grep -R "a.b" /docs/by-id/'.$doc->id)['stdout'];
        $regex = $executor->execute($this->agent, 'rg "a.b" /docs/by-id/'.$doc->id)['stdout'];
        $badRegex = app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent)->handle(new Request(['command' => 'rg "[" /docs/by-id/'.$doc->id]));
        $badDirectRegex = app(ToolRegistry::class)->instantiateToolBySlug('vfs_rg', $this->agent)->handle(new Request(['pattern' => '[', 'paths' => ['/docs/by-id/'.$doc->id]]));
        $snakeBudget = app(ToolRegistry::class)->instantiateToolBySlug('vfs_rg', $this->agent)->handle(new Request(['pattern' => 'literal', 'paths' => ['/docs/by-id/'.$doc->id], 'max_files' => 1, 'max_matches' => 1]));

        $this->assertStringContainsString('a.b literal', $literal);
        $this->assertStringNotContainsString('acb regex', $literal);
        $this->assertStringContainsString('a.b literal', $regex);
        $this->assertStringContainsString('acb regex', $regex);
        $this->assertSame('invalid_request', json_decode($badRegex, true)['error']['code']);
        $this->assertSame('invalid_request', json_decode($badDirectRegex, true)['error']['code']);
        $this->assertSame(1, json_decode($snakeBudget, true)['result']['max_files']);
    }

    public function test_vfs_exec_rejects_unsupported_flags_even_under_shell_operators(): void
    {
        $tool = app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent);

        foreach ([
            'find /docs -delete',
            'find /docs -exec echo {} ;',
            'tail --follow /docs',
            'cat --show-all /docs',
            'which -a rg',
            'env -0',
            // sed -i is implemented since Phase 2 (governed writes); it stays
            // refused only for structured record roots.
            'sed -i s/a/b/ /tasks/by-id/x.json',
            'python -c "print(1)"',
            'curl https://example.com',
            // `tar -tf` used to "fail" only because -f was mis-parsed; it is a
            // real command now. bzip2 stays refused.
            'tar -cjf /files/archive.tar.bz2 /files',
            'find /docs -delete || echo ignored',
            'find /docs -delete; echo ignored',
            'true || find /docs -delete',
            'false && cat --show-all /docs',
            'true || bash -lc "cat /etc/passwd"',
            'echo hi 3>/tmp/out',
            'echo hi 2>&3',
            'env FOO=bar',
            'env FOO=bar cat /docs',
        ] as $command) {
            $decoded = json_decode($tool->handle(new Request(['command' => $command])), true);

            $this->assertFalse($decoded['ok'], $command);
            $this->assertSame('unsupported', $decoded['error']['code'], $command);
        }

        foreach ([
            'head --bytes /docs',
            'head --bytes',
            'head -c',
            'rg alpha /docs --max-count',
            'find /docs -maxdepth',
            'echo hi >',
            'echo ok &&',
            'echo ok ||',
            'echo ok |',
            '| cat',
            'echo one ;; echo two',
            'xargs --max-args=abc echo',
            'type ll definitely_missing',
            'printenv WORKSPACE_ID EXTRA',
        ] as $command) {
            $decoded = json_decode($tool->handle(new Request(['command' => $command])), true);

            $this->assertFalse($decoded['ok'], $command);
            $this->assertSame('invalid_request', $decoded['error']['code'], $command);
        }

        // Phase 2: which accepts several names (GNU); an unknown one is a
        // failed lookup (not_found + "Unknown VFS command"), not a path error.
        $missing = json_decode($tool->handle(new Request(['command' => 'which rg definitely_missing'])), true);
        $this->assertFalse($missing['ok']);
        $this->assertSame('not_found', $missing['error']['code']);
        $this->assertStringContainsString('Unknown VFS command: definitely_missing. Run `help`', $missing['error']['message']);
    }

    public function test_vfs_exec_covers_common_unix_read_commands_and_pipes(): void
    {
        $first = Document::factory()->create([
            'title' => 'Alpha Notes',
            'content' => "zeta\nalpha\nalpha\nbeta",
            'author_id' => $this->agent->id,
        ]);
        $second = Document::factory()->create([
            'title' => 'Beta Notes',
            'content' => "zeta\nalpha\ngamma",
            'author_id' => $this->agent->id,
        ]);

        $executor = app(VfsCommandExecutor::class);

        $this->assertSame('/', trim($executor->execute($this->agent, 'pwd')['stdout']));
        $this->assertSame('/docs', $executor->execute($this->agent, 'cd /docs')['cwd']);
        $this->assertStringContainsString('by-id', $executor->execute($this->agent, 'tree -L 1 /docs')['stdout']);
        $this->assertStringContainsString('by-id/', $executor->execute($this->agent, 'dir /docs')['stdout']);
        $this->assertStringContainsString('by-id/', $executor->execute($this->agent, 'll /docs')['stdout']);
        $this->assertStringContainsString('Alpha Notes', $executor->execute($this->agent, 'stat /docs/by-id/'.$first->id)['stdout']);
        $this->assertStringContainsString('markdown', $executor->execute($this->agent, 'file /docs/by-id/'.$first->id)['stdout']);
        $this->assertStringContainsString('/docs/by-id/'.$first->id, $executor->execute($this->agent, 'realpath docs/by-id/'.$first->id)['stdout']);
        $this->assertStringContainsString('/docs/by-id', $executor->execute($this->agent, 'dirname /docs/by-id/'.$first->id)['stdout']);
        $this->assertSame($first->id, trim($executor->execute($this->agent, 'basename /docs/by-id/'.$first->id)['stdout']));
        $this->assertStringContainsString('alpha', $executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | grep alpha | sort | uniq')['stdout']);
        $this->assertStringContainsString('beta', $executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | sed "s/alpha/beta/" | grep beta')['stdout']);
        $this->assertStringContainsString('3 ', $executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | wc')['stdout']);
        $this->assertStringContainsString('alpha', $executor->execute($this->agent, 'head -n 2 /docs/by-id/'.$first->id)['stdout']);
        $this->assertStringContainsString('beta', $executor->execute($this->agent, 'tail -n 1 /docs/by-id/'.$first->id)['stdout']);
        $this->assertStringContainsString('/docs/by-id/'.$first->id, $executor->execute($this->agent, 'find /docs/by-id -name "Alpha*"')['stdout']);
        $this->assertStringContainsString('sha256', 'sha256 '.$executor->execute($this->agent, 'sha256sum /docs/by-id/'.$first->id)['stdout']);
        $this->assertStringContainsString('/docs/by-id/'.$first->id, $executor->execute($this->agent, 'sha1sum /docs/by-id/'.$first->id)['stdout']);
        $this->assertStringContainsString('/docs/by-id/'.$first->id, $executor->execute($this->agent, 'md5sum /docs/by-id/'.$first->id)['stdout']);
        // Phase 2: diff is LCS-based in GNU normal format (exit 1 when different).
        $diff = $executor->execute($this->agent, 'diff /docs/by-id/'.$first->id.' /docs/by-id/'.$second->id);
        $this->assertStringContainsString('> gamma', $diff['stdout']);
        $this->assertSame(1, $diff['exit_code']);
        $this->assertStringContainsString('differ', $executor->execute($this->agent, 'cmp /docs/by-id/'.$first->id.' /docs/by-id/'.$second->id)['stdout']);
        $this->assertStringContainsString('alpha', $executor->execute($this->agent, 'comm /docs/by-id/'.$first->id.' /docs/by-id/'.$second->id)['stdout']);
    }

    public function test_vfs_command_registry_recognizes_mirage_unix_surface(): void
    {
        $mirageCommands = [
            'ls', 'stat', 'pwd', 'find', 'tree', 'du', 'cat', 'head', 'tail', 'wc',
            'md5', 'diff', 'file', 'python', 'python3', 'nl', 'grep', 'rg', 'sort', 'uniq',
            'cut', 'mkdir', 'touch', 'cp', 'mv', 'rm', 'sed', 'echo', 'tee', 'tr',
            'curl', 'wget', 'jq', 'awk', 'paste', 'tac', 'printf', 'seq', 'base64', 'sha256sum',
            'xxd', 'tar', 'gzip', 'gunzip', 'zip', 'unzip', 'basename', 'dirname', 'realpath', 'readlink',
            'ln', 'split', 'patch', 'shuf', 'comm', 'column', 'fold', 'fmt', 'cmp', 'iconv',
            'strings', 'rev', 'zcat', 'zgrep', 'mktemp', 'bc', 'expr', 'history', 'date', 'csplit',
            'expand', 'unexpand', 'tsort', 'look', 'sleep', 'bash', 'join',
        ];

        foreach ($mirageCommands as $command) {
            $this->assertNotNull(VfsCommandSpecRegistry::get($command), $command);
        }

        $tool = app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent);
        foreach ([
            'bash -c "echo hi"',
            'python -c "print(1)"',
            'curl https://example.com',
            'tar -cjf /files/archive.tar.bz2 /files',
            'patch -p1',
            'history',
        ] as $command) {
            $decoded = json_decode($tool->handle(new Request(['command' => $command])), true);

            $this->assertFalse($decoded['ok'], $command);
            $this->assertSame('unsupported', $decoded['error']['code'], $command);
        }
    }

    public function test_vfs_command_help_and_structured_discovery_are_available(): void
    {
        $doc = Document::factory()->create([
            'title' => 'Discovery Notes',
            'content' => 'refund discovery',
            'author_id' => $this->agent->id,
        ]);
        $executor = app(VfsCommandExecutor::class);
        $tool = app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent);

        $this->assertStringContainsString('VFS Commands', $executor->execute($this->agent, 'help')['stdout']);
        $this->assertStringContainsString('Short flags', $executor->execute($this->agent, 'help grep')['stdout']);
        $this->assertStringContainsString('grep', $executor->execute($this->agent, 'man grep')['stdout']);
        $this->assertStringContainsString('Short flags', $executor->execute($this->agent, 'grep --help')['stdout']);
        $this->assertStringContainsString('vfs_write', $executor->execute($this->agent, 'rm --help')['stdout']);
        $this->assertStringContainsString('Network commands are outside VFS', $executor->execute($this->agent, 'curl --help')['stdout']);

        $beforeFiles = WorkspaceFile::count();
        $this->assertStringContainsString('Delete a workspace file', $executor->execute($this->agent, 'rm --help')['stdout']);
        $this->assertSame($beforeFiles, WorkspaceFile::count());

        try {
            $executor->execute($this->agent, 'help does-not-exist');
            $this->fail('Unknown command help should fail with a structured VFS error.');
        } catch (VfsError $e) {
            // Phase 2: a failed lookup keeps not_found but names the command,
            // never "Path not found".
            $this->assertSame('not_found', $e->errorCode);
            $this->assertStringContainsString('Unknown VFS command: does-not-exist. Run `help`', $e->getMessage());
        }

        $this->assertStringContainsString('status=recognized_unsupported', $executor->execute($this->agent, 'type curl')['stdout']);
        $catalog = json_decode($executor->execute($this->agent, 'cat /tools/vfs/commands.json')['stdout'], true);
        $this->assertContains('-n', $catalog['grep']['short_flags']);
        $this->assertContains('-A', $catalog['grep']['value_options']);
        $this->assertSame('recognized_unsupported', $catalog['curl']['status']);
        $grepSpec = json_decode($executor->execute($this->agent, 'cat /tools/vfs/commands/grep/grep.json')['stdout'], true);
        $this->assertSame('grep', $grepSpec['name']);
        $this->assertContains('-n', $grepSpec['short_flags']);
        $this->assertStringContainsString('Examples', $executor->execute($this->agent, 'cat /tools/vfs/commands/grep/grep.md')['stdout']);

        $structured = json_decode($tool->handle(new Request([
            'command' => "stat /docs/by-id/{$doc->id}",
            'responseFormat' => 'structured',
            'includeCommandSpec' => true,
        ])), true);
        $this->assertTrue($structured['ok']);
        // Phase 2 envelope: stdout appears once; no echoed `result` block, and
        // the command spec lives only in diagnostics.
        $this->assertArrayNotHasKey('result', $structured);
        $this->assertSame('stat', $structured['data']['command']);
        $this->assertSame('stat', $structured['diagnostics']['command_spec']['name']);
        $this->assertSame($doc->id, $structured['data']['stat']['backend_id']);
    }

    public function test_vfs_exec_covers_ax_audit_unix_gaps_and_conditionals(): void
    {
        $first = Document::factory()->create([
            'title' => 'Alpha AX Notes',
            'content' => "zeta 3\nalpha 2\nalpha 1\nbeta 10",
            'author_id' => $this->agent->id,
        ]);
        $csv = Document::factory()->create([
            'title' => 'CSV AX Notes',
            'content' => "name,score\nalice,10\nbob,2",
            'author_id' => $this->agent->id,
        ]);
        $duplicates = Document::factory()->create([
            'title' => 'Duplicate AX Notes',
            'content' => "alpha\nalpha\nbeta",
            'author_id' => $this->agent->id,
        ]);

        $executor = app(VfsCommandExecutor::class);

        $this->assertStringContainsString('by-id/', $executor->execute($this->agent, 'ls -la /docs')['stdout']);
        $this->assertStringContainsString('/docs/by-id', $executor->execute($this->agent, 'ls -R /docs')['stdout']);
        $this->assertSame('/docs', trim($executor->execute($this->agent, 'cd /docs && pwd')['stdout']));
        $this->assertSame("first\nsecond\nthird", trim($executor->execute($this->agent, 'echo first; echo second; echo third')['stdout']));
        $this->assertArrayNotHasKey('stdout', $executor->execute($this->agent, 'echo first; echo second; echo third')['result']);
        $this->assertSame('ok', trim($executor->execute($this->agent, 'true && echo ok')['stdout']));
        $this->assertSame('fallback', trim($executor->execute($this->agent, 'false || echo fallback')['stdout']));
        $this->assertSame('OR', trim($executor->execute($this->agent, 'false && echo AND || echo OR')['stdout']));
        $this->assertSame('after', trim($executor->execute($this->agent, 'ls /nonexistent 2>/dev/null; echo after')['stdout']));
        // Phase 2: a failure whose stderr was redirected is not an error
        // envelope; it is a non-zero exit status (2 = error), like bash.
        $hidden = $executor->execute($this->agent, 'ls /nonexistent 2>/dev/null && echo hidden');
        $this->assertSame(2, $hidden['exit_code']);
        $this->assertSame('', $hidden['stdout']);
        $merged = $executor->execute($this->agent, 'ls /nonexistent 2>&1');
        $this->assertSame(2, $merged['exit_code']);
        $this->assertStringContainsString('Path not found', $merged['stdout']);
        $this->assertSame('1', trim($executor->execute($this->agent, 'ls /nonexistent 2>&1 | grep -c "Path not found"')['stdout']));
        // Phase 2: `2> file` is supported and is a governed write, so an
        // agent without vfs_write is denied instead of writing the file.
        try {
            $executor->execute($this->agent, 'ls /nonexistent 2>/files/errors.txt');
            $this->fail('stderr file redirects must pass the write policy.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
        }
        $this->assertSame('', trim($executor->execute($this->agent, 'echo hi 1>&2')['stdout']));
        $this->assertSame('continued', trim($executor->execute($this->agent, 'ls /nonexistent 2>/dev/null; echo continued')['stdout']));
        $this->assertSame('> literal', trim($executor->execute($this->agent, 'echo "> literal"')['stdout']));
        $this->assertSame('quoted "value"', trim($executor->execute($this->agent, 'echo "quoted \\"value\\""')['stdout']));
        $this->assertSame("hello\nworld", trim($executor->execute($this->agent, 'echo -e "hello\nworld"')['stdout']));

        $found = preg_split('/\r\n|\r|\n/', trim($executor->execute($this->agent, 'find /docs/by-id -name "Alpha AX*"')['stdout'])) ?: [];
        $this->assertSame(array_values(array_unique($found)), $found);
        $this->assertSame('/tasks', trim($executor->execute($this->agent, 'find /tasks -maxdepth 0')['stdout']));
        $this->assertSame('/tasks', trim($executor->execute($this->agent, 'find /tasks -maxDepth 0')['stdout']));
        $this->assertStringContainsString('Alpha AX Notes', $executor->execute($this->agent, 'find /docs/by-id -name "Alpha AX*" | xargs stat')['stdout']);
        $this->assertStringContainsString('item: /docs/by-id/', $executor->execute($this->agent, 'find /docs/by-id -name "Alpha AX*" | xargs -I{} echo "item: {}"')['stdout']);
        $this->assertStringContainsString('/docs/by-id/', $executor->execute($this->agent, 'find /docs -name "*.md" -type f 2>/dev/null | head -n 1')['stdout']);

        $this->assertSame('2', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | grep -c alpha')['stdout']));
        $this->assertSame('2', trim($executor->execute($this->agent, 'grep -c alpha < /docs/by-id/'.$first->id)['stdout']));
        $this->assertStringContainsString('2:alpha 2', $executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | grep -n alpha')['stdout']);
        $this->assertSame('/docs/by-id/'.$first->id, trim($executor->execute($this->agent, 'grep -l alpha /docs/by-id/'.$first->id)['stdout']));
        $this->assertSame('zeta', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | head -c 4')['stdout']));
        $this->assertSame('a 10', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | tail -c 4')['stdout']));
        $this->assertSame('zeta 3', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | head -1')['stdout']));
        $this->assertSame('beta 10', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | tail -1')['stdout']));
        $this->assertSame('zeta', trim($executor->execute($this->agent, 'head --bytes=4 /docs/by-id/'.$first->id)['stdout']));
        $this->assertSame('zeta', trim($executor->execute($this->agent, 'head -c4 /docs/by-id/'.$first->id)['stdout']));
        $this->assertSame('', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | tail -n 0')['stdout']));
        $this->assertSame('', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | tail -c 0')['stdout']));
        $this->assertSame("zeta 3\nalpha 2\nalpha 1\nbeta 10", trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | tail -n +0')['stdout']));
        $this->assertSame("alpha 2\nalpha 1\nbeta 10", trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | tail -n +2')['stdout']));
        $this->assertSame('zeta', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | tail -c +0 | head -c4')['stdout']));
        $this->assertSame('ta 3', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | tail -c +3 | head -c4')['stdout']));
        $this->assertSame('3', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | wc -l')['stdout']));
        $this->assertSame('30', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | wc -c')['stdout']));
        $this->assertSame('30', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | wc -m')['stdout']));
        $this->assertSame('7', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | wc -L')['stdout']));
        foreach (['echo ok | wc /missing', 'wc /docs/by-id/'.$first->id.' /missing'] as $command) {
            try {
                $executor->execute($this->agent, $command);
                $this->fail("{$command} should fail.");
            } catch (VfsError $e) {
                $this->assertSame('not_found', $e->errorCode);
            }
        }

        // Phase 2: POSIX line output, every line newline-terminated.
        $this->assertSame("alpha 1\nalpha 2\nzeta 3\nbeta 10\n", $executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | sort -n -k 2')['stdout']);
        $this->assertSame("zeta 3\nbeta 10\nalpha 2\nalpha 1\n", $executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | sort -r')['stdout']);
        $this->assertStringContainsString('      2 alpha', $executor->execute($this->agent, 'cat /docs/by-id/'.$duplicates->id.' | uniq -c')['stdout']);
        $this->assertSame('alpha', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$duplicates->id.' | uniq -d')['stdout']));
        $this->assertSame('beta', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$duplicates->id.' | uniq -u')['stdout']));
        $this->assertSame('hello world', $executor->execute($this->agent, 'printf "hello %s" world')['stdout']);
        $this->assertSame("alpha\nbeta\n", $executor->execute($this->agent, 'printf "%s\n" alpha beta')['stdout']);
        $this->assertSame("score\n10\n2\n", $executor->execute($this->agent, 'cat /docs/by-id/'.$csv->id.' | awk -F , "{print $2}"')['stdout']);
        $this->assertSame('cba', trim($executor->execute($this->agent, 'echo abc | rev')['stdout']));
        $this->assertSame('b', trim($executor->execute($this->agent, 'echo "a b c" | cut -d\' \' -f2')['stdout']));
        $this->assertSame('bcd', trim($executor->execute($this->agent, 'echo abcde | cut -c2-4')['stdout']));
        $this->assertSame('ae', trim($executor->execute($this->agent, 'echo abcde | cut -c2-4 --complement')['stdout']));
        $this->assertSame("a\nb\nc", trim($executor->execute($this->agent, 'echo "a;b;c" | tr ";" "\n"')['stdout']));
        $this->assertSame('HELLO', trim($executor->execute($this->agent, 'echo hello | tr "a-z" "A-Z"')['stdout']));
        $this->assertSame('hll wrld', trim($executor->execute($this->agent, 'echo "hello world" | tr -d "aeiou"')['stdout']));
        $this->assertSame('hello', trim($executor->execute($this->agent, 'echo heeello | tr -s e')['stdout']));
        $this->assertStringContainsString('alpha 1', $executor->execute($this->agent, 'sort /docs/by-id/'.$first->id.' | head -n 1')['stdout']);
        $this->assertStringContainsString('alice,10', $executor->execute($this->agent, 'sort /docs/by-id/'.$first->id.' /docs/by-id/'.$csv->id)['stdout']);
        $this->assertStringContainsString('Alpha AX Notes', $executor->execute($this->agent, 'find /docs/by-id -name "Alpha AX*" | xargs -n 1 stat')['stdout']);
        $this->assertStringContainsString($first->id, $executor->execute($this->agent, 'find /docs/by-id -name "Alpha AX*" -print0 | xargs -0 -n 1 basename')['stdout']);
        $this->assertStringContainsString($csv->id, $executor->execute($this->agent, 'find /docs/by-id -name "Missing*" -o -name "CSV AX*"')['stdout']);
        $this->assertStringNotContainsString($first->id, $executor->execute($this->agent, 'find /docs/by-id -not -name "Alpha AX*"')['stdout']);
        $this->assertStringContainsString($first->id, $executor->execute($this->agent, 'find /docs/by-id -type f -size +1c -mtime -1')['stdout']);
        $this->assertSame('a\\b', $executor->execute($this->agent, 'printf "%s" "a\\\\b"')['stdout']);
        $this->assertStringContainsString("\t", $executor->execute($this->agent, 'paste /docs/by-id/'.$first->id.' /docs/by-id/'.$csv->id)['stdout']);
        $this->assertStringContainsString(',', $executor->execute($this->agent, 'paste -d, /docs/by-id/'.$first->id.' /docs/by-id/'.$csv->id)['stdout']);
        // Recursive search prints friendly paths; the by-id path is in result.canonical_paths.
        $rg = $executor->execute($this->agent, 'rg alpha /docs --max-count 1');
        $this->assertStringContainsString('/docs/alpha-ax-notes:', $rg['stdout']);
        $this->assertSame('/docs/by-id/'.$first->id, $rg['result']['canonical_paths']['/docs/alpha-ax-notes']);
        $this->assertStringContainsString('/docs/alpha-ax-notes:', $executor->execute($this->agent, 'rg alpha /docs --max-depth 2 --max-count 1')['stdout']);
        // Phase 2: like ripgrep, a single file operand prints no file name.
        $this->assertSame('alpha 2', trim($executor->execute($this->agent, 'rg alpha /docs/by-id/'.$first->id.' -m1')['stdout']));
        $this->assertSame("alpha\nalpha", trim($executor->execute($this->agent, 'grep -o alpha /docs/by-id/'.$first->id)['stdout']));
        $this->assertSame('2', trim($executor->execute($this->agent, 'grep -c alpha /docs/by-id/'.$first->id)['stdout']));
        $this->assertStringNotContainsString('alpha', $executor->execute($this->agent, 'grep -v alpha /docs/by-id/'.$first->id)['stdout']);
        $this->assertStringContainsString('alpha 2', $executor->execute($this->agent, 'grep -w alpha /docs/by-id/'.$first->id)['stdout']);
        $this->assertStringContainsString('zeta 3', $executor->execute($this->agent, 'grep -A1 zeta /docs/by-id/'.$first->id)['stdout']);
        $this->assertStringContainsString('alpha 1', $executor->execute($this->agent, 'grep -B1 beta /docs/by-id/'.$first->id)['stdout']);
        $this->assertSame('0', trim($executor->execute($this->agent, 'grep -c missing /docs/by-id/'.$first->id)['stdout']));
        $this->assertSame('', trim($executor->execute($this->agent, 'printf "alpha\n" | grep --max-count 0 alpha')['stdout']));
        $this->assertSame('0', trim($executor->execute($this->agent, 'printf "alpha\n" | grep -c --max-count 0 alpha')['stdout']));
        $this->assertSame('2:alpha', trim($executor->execute($this->agent, 'grep -n -o alpha /docs/by-id/'.$first->id.' --max-count 1')['stdout']));
        $this->assertStringContainsString('beta 10', $executor->execute($this->agent, 'egrep "beta.10" /docs/by-id/'.$first->id)['stdout']);
        $this->assertSame('', trim($executor->execute($this->agent, 'rg alpha /docs --max-depth 0')['stdout']));

        $this->assertSame('rg', trim($executor->execute($this->agent, 'which rg')['stdout']));
        $this->assertStringContainsString('VFS command', $executor->execute($this->agent, 'type ll')['stdout']);
        $this->assertSame('grep', trim($executor->execute($this->agent, 'command -v grep')['stdout']));
        $this->assertStringContainsString('VFS=OpenCompany', $executor->execute($this->agent, 'env')['stdout']);
        $this->assertStringContainsString((string) $this->workspace->id, $executor->execute($this->agent, 'printenv WORKSPACE_ID')['stdout']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $executor->execute($this->agent, 'date')['stdout']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', trim($executor->execute($this->agent, 'date -I')['stdout']));
        $this->assertSame('/docs', trim($executor->execute($this->agent, 'readlink -f /docs')['stdout']));
        $this->assertSame($first->id, trim($executor->execute($this->agent, 'stat -c %n /docs/by-id/'.$first->id.' | xargs basename')['stdout']));
        $this->assertSame('markdown', trim($executor->execute($this->agent, 'file -b /docs/by-id/'.$first->id)['stdout']));
        $this->assertStringContainsString('B', $executor->execute($this->agent, 'du -h /docs/by-id/'.$first->id)['stdout']);
        $this->assertStringContainsString('/docs/by-id/'.$first->id, $executor->execute($this->agent, 'du -a --max-depth 2 /docs')['stdout']);
        $this->assertStringContainsString("1\tzeta", $executor->execute($this->agent, 'cat -n /docs/by-id/'.$first->id)['stdout']);
        $this->assertStringContainsString('  /docs/by-id/'.$first->id, $executor->execute($this->agent, 'checksum /docs/by-id/'.$first->id)['stdout']);

        $this->assertSame("3\n4\n5", trim($executor->execute($this->agent, 'seq 3 5')['stdout']));
        $this->assertSame('aGVsbG8=', trim($executor->execute($this->agent, 'printf hello | base64')['stdout']));
        $this->assertSame('hello', trim($executor->execute($this->agent, 'printf aGVsbG8= | base64 -d')['stdout']));
        $this->assertSame('hello', trim($executor->execute($this->agent, 'printf hello | gzip -c | gunzip -c')['stdout']));
        $this->assertSame('hello', trim($executor->execute($this->agent, 'printf hello | gzip -c | zcat')['stdout']));
        $this->assertSame('alpha', trim($executor->execute($this->agent, 'printf "alpha\nbeta" | gzip -c | zgrep alpha')['stdout']));
        $this->assertSame('68656c6c6f', trim($executor->execute($this->agent, 'printf hello | xxd -p')['stdout']));
        $this->assertSame('hello', trim($executor->execute($this->agent, 'printf 68656c6c6f | xxd -r -p')['stdout']));
        $this->assertStringContainsString('hello', $executor->execute($this->agent, 'printf "hi\0hello world\0ok" | strings -n 5')['stdout']);
        $this->assertSame('hello', trim($executor->execute($this->agent, 'printf hello | iconv -f UTF-8 -t UTF-8')['stdout']));
        $this->assertSame("c\nb\na", trim($executor->execute($this->agent, 'printf "a\nb\nc" | tac')['stdout']));
        $this->assertMatchesRegularExpression('/^a\s+1$/m', $executor->execute($this->agent, 'printf "a 1\nbbb 22" | column -t')['stdout']);
        $this->assertStringContainsString("\n", $executor->execute($this->agent, 'printf abcdef | fold -w 3')['stdout']);
        $this->assertSame('a b c', trim($executor->execute($this->agent, 'printf "a    b\nc" | fmt -w 20')['stdout']));
        // Phase 2: real tab stops (GNU): "a" + 3 spaces reaches column 4.
        $this->assertSame('a   b', trim($executor->execute($this->agent, 'printf "a\tb" | expand -t 4')['stdout']));
        // Phase 2: GNU semantics (-t implies -a; the blank after the stop stays).
        $this->assertSame("a\t b", trim($executor->execute($this->agent, 'printf "a    b" | unexpand -t 4')['stdout']));
        $this->assertSame('3', trim($executor->execute($this->agent, 'printf "1+2" | bc')['stdout']));
        $this->assertSame('14', trim($executor->execute($this->agent, 'printf "2*(3+4)" | bc')['stdout']));
        $this->assertSame('3', trim($executor->execute($this->agent, 'expr 1 + 2')['stdout']));

        $directIgnoreCase = app(ToolRegistry::class)->instantiateToolBySlug('vfs_rg', $this->agent)->handle(new Request(['pattern' => 'ALPHA', 'paths' => ['/docs/by-id/'.$first->id], 'ignore_case' => true]));
        // vfs_search was removed in Phase 2 (it was vfs_rg with regex: false).
        $directSearchIgnoreCase = app(ToolRegistry::class)->instantiateToolBySlug('vfs_rg', $this->agent)->handle(new Request(['query' => 'ALPHA', 'regex' => false, 'paths' => ['/docs/by-id/'.$first->id], 'caseInsensitive' => true]));
        $this->assertGreaterThanOrEqual(1, json_decode($directIgnoreCase, true)['result']['count']);
        $this->assertGreaterThanOrEqual(1, json_decode($directSearchIgnoreCase, true)['result']['count']);
    }

    public function test_vfs_exec_supports_globs_jq_file_args_redirection_and_find_budgets(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $executor = app(VfsCommandExecutor::class);
        $executor->execute($this->agent, 'mkdir /files/glob-json');
        $executor->execute($this->agent, 'printf \'{"items":[{"name":"alpha"},{"name":"beta"}],"n":2}\' > /files/glob-json/a.json');
        $executor->execute($this->agent, 'printf \'{"items":[{"name":"gamma"}],"n":1}\' > /files/glob-json/b.json');
        $executor->execute($this->agent, 'printf \'[{"name":"alpha"},{"name":"beta"}]\' > /files/glob-json/list.json');
        $executor->execute($this->agent, 'printf \'{"subtotal":2,"tax":3}\' > /files/glob-json/price.json');

        $this->assertStringContainsString('a.json', $executor->execute($this->agent, 'ls /files/glob-json/*.json')['stdout']);
        $quotedGlob = app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent)->handle(new Request(['command' => 'ls "/files/glob-json/*.json"']));
        $this->assertFalse(json_decode($quotedGlob, true)['ok']);
        $badBudget = app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent)->handle(new Request(['command' => 'ls /docs', 'maxEntries' => '1abc']));
        $this->assertFalse(json_decode($badBudget, true)['ok']);
        $this->assertSame('invalid_request', json_decode($badBudget, true)['error']['code']);
        $badDirectBudget = app(ToolRegistry::class)->instantiateToolBySlug('vfs_ls', $this->agent)->handle(new Request(['path' => '/docs', 'limit' => '1abc']));
        $this->assertFalse(json_decode($badDirectBudget, true)['ok']);
        $this->assertSame('invalid_request', json_decode($badDirectBudget, true)['error']['code']);
        $this->assertSame('2', trim($executor->execute($this->agent, 'jq length /files/glob-json/a.json')['stdout']));
        $this->assertStringContainsString('items', $executor->execute($this->agent, 'jq keys /files/glob-json/a.json')['stdout']);
        $this->assertSame('true', trim($executor->execute($this->agent, 'jq "has(\'items\')" /files/glob-json/a.json')['stdout']));
        $this->assertSame('false', trim($executor->execute($this->agent, 'jq "has(\'missing\')" /files/glob-json/a.json')['stdout']));
        $this->assertStringContainsString('alpha', $executor->execute($this->agent, 'jq ".items[0]" /files/glob-json/a.json')['stdout']);
        // Phase 2: strings are JSON-quoted unless -r (real jq semantics).
        $this->assertSame("\"alpha\"\n\"beta\"", trim($executor->execute($this->agent, 'jq ".items[].name" /files/glob-json/a.json')['stdout']));
        $this->assertSame("alpha\nbeta", trim($executor->execute($this->agent, 'jq -r ".items[].name" /files/glob-json/a.json')['stdout']));
        $this->assertSame('2', trim($executor->execute($this->agent, 'jq ".items | length" /files/glob-json/a.json')['stdout']));
        $this->assertStringContainsString('alpha', $executor->execute($this->agent, 'jq ".[0]" /files/glob-json/list.json')['stdout']);
        $this->assertStringContainsString('alpha', $executor->execute($this->agent, 'jq ".[]" /files/glob-json/list.json')['stdout']);
        $this->assertSame("alpha\nbeta", trim($executor->execute($this->agent, 'jq -r ".[] | .name" /files/glob-json/list.json')['stdout']));
        $this->assertSame('["alpha","beta"]', trim($executor->execute($this->agent, 'jq -c "map(.name)" /files/glob-json/list.json')['stdout']));
        $this->assertStringContainsString('alpha', $executor->execute($this->agent, 'jq -c ".[] | select(.name == \'alpha\')" /files/glob-json/list.json')['stdout']);
        $this->assertSame('4', trim($executor->execute($this->agent, 'jq ".n + 2" /files/glob-json/a.json')['stdout']));
        $this->assertSame('5', trim($executor->execute($this->agent, 'jq ".subtotal + .tax" /files/glob-json/price.json')['stdout']));
        $this->assertStringContainsString('gamma', $executor->execute($this->agent, 'jq ".items[0]" /files/glob-json/a.json /files/glob-json/b.json')['stdout']);
        $executor->execute($this->agent, 'printf \'{"data":{"customer-email":"buyer@example.com"},"source":"file"}\' > /files/glob-json/keyed.json');
        $this->assertSame('buyer@example.com', trim($executor->execute($this->agent, 'jq -r \'.data["customer-email"]\' /files/glob-json/keyed.json')['stdout']));
        $this->assertSame('file', trim($executor->execute($this->agent, 'printf \'{"source":"stdin"}\' | jq -r ".source" /files/glob-json/keyed.json')['stdout']));

        // `..` (recursive descent) is supported since the Phase 2 jq parser.
        $this->assertStringContainsString('alpha', $executor->execute($this->agent, 'jq -c ".." /files/glob-json/a.json')['stdout']);
        foreach (['jq nope /files/glob-json/a.json', 'jq ".items[" /files/glob-json/a.json', 'jq ".x = 1" /files/glob-json/a.json'] as $command) {
            try {
                $executor->execute($this->agent, $command);
                $this->fail("{$command} should fail.");
            } catch (VfsError $e) {
                // jq syntax errors are invalid_request since the Phase 2 parser
                // ("invalid" was never a VFS error code).
                $this->assertContains($e->errorCode, ['invalid_request', 'unsupported']);
            }
        }

        $this->assertStringContainsString('continued', $executor->execute($this->agent, 'ls /missing 2>&1 || echo continued')['stdout']);
        // Phase 2: a failure whose stderr went to a file is exit status 2,
        // not an error envelope.
        $redirectedError = $executor->execute($this->agent, 'ls /missing > /files/glob-json/errors.txt 2>&1');
        $this->assertSame(2, $redirectedError['exit_code']);
        $this->assertSame('', trim($redirectedError['stdout']));
        $this->assertStringContainsString('Path not found', $executor->execute($this->agent, 'cat /files/glob-json/errors.txt')['stdout']);
        $orderedError = $executor->execute($this->agent, 'ls /missing 2>&1 > /files/glob-json/stdout-only.txt');
        $this->assertSame(2, $orderedError['exit_code']);
        $this->assertStringContainsString('Path not found', $orderedError['stdout']);

        $find = app(OpenCompanyVfs::class);
        for ($i = 0; $i < 5; $i++) {
            Document::factory()->create([
                'title' => 'Budgeted Find '.$i,
                'content' => 'budgeted',
                'author_id' => $this->agent->id,
            ]);
        }
        $result = $find->search($this->agent, 'budgeted', ['/docs'], new VfsBudget(maxEntries: 100, maxDepth: 3, maxFiles: 2, maxMatches: 100));
        $this->assertLessThanOrEqual(2, count($result));

        $execFind = app(VfsCommandExecutor::class)->execute($this->agent, 'find /docs -name "Budgeted*"', '/', new VfsBudget(maxEntries: 100, maxDepth: 3, maxFiles: 2));
        $this->assertLessThanOrEqual(2, $execFind['result']['scanned']);
        $this->assertTrue($execFind['result']['truncated']);
    }

    public function test_vfs_stat_resolves_canonical_by_id_paths_without_parent_listing_window(): void
    {
        for ($i = 0; $i < 105; $i++) {
            Document::factory()->create([
                'title' => sprintf('AAA Listing Window %03d', $i),
                'content' => 'listing window filler',
                'author_id' => $this->agent->id,
            ]);
        }

        $target = Document::factory()->create([
            'title' => 'ZZZ Canonical Stat Target',
            'content' => 'canonical stat target',
            'author_id' => $this->agent->id,
        ]);

        $executor = app(VfsCommandExecutor::class);
        $stat = $executor->execute($this->agent, 'stat /docs/by-id/'.$target->id)['stdout'];
        $file = $executor->execute($this->agent, 'file /docs/by-id/'.$target->id)['stdout'];

        $this->assertStringContainsString($target->id, $stat);
        $this->assertStringContainsString('file', $stat);
        $this->assertStringContainsString('/docs/by-id/'.$target->id.': markdown', $file);
    }

    public function test_vfs_structured_helpers_return_opaque_pagination_cursors(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Document::factory()->create([
                'title' => sprintf('Cursor Doc %02d', $i),
                'content' => 'cursor pagination',
                'author_id' => $this->agent->id,
            ]);
        }

        $lsTool = app(ToolRegistry::class)->instantiateToolBySlug('vfs_ls', $this->agent);
        $first = json_decode($lsTool->handle(new Request(['path' => '/docs/by-id', 'limit' => 2])), true);
        $second = json_decode($lsTool->handle(new Request(['path' => '/docs/by-id', 'limit' => 2, 'cursor' => $first['result']['next_cursor']])), true);

        $this->assertTrue($first['result']['truncated']);
        $this->assertNotNull($first['result']['next_cursor']);
        $this->assertCount(2, $first['result']['items']);
        $this->assertCount(2, $second['result']['items']);
        $this->assertNotSame($first['result']['items'][0]['backend_id'], $second['result']['items'][0]['backend_id']);

        $findTool = app(ToolRegistry::class)->instantiateToolBySlug('vfs_find', $this->agent);
        $findFirst = json_decode($findTool->handle(new Request(['paths' => ['/docs/by-id'], 'name' => 'Cursor*', 'limit' => 2, 'maxDepth' => 1, 'maxFiles' => 20])), true);
        $findSecond = json_decode($findTool->handle(new Request(['paths' => ['/docs/by-id'], 'name' => 'Cursor*', 'limit' => 2, 'maxDepth' => 1, 'maxFiles' => 20, 'cursor' => $findFirst['result']['next_cursor']])), true);

        $this->assertTrue($findFirst['result']['truncated']);
        $this->assertNotNull($findFirst['result']['next_cursor']);
        $this->assertCount(2, $findFirst['result']['items']);
        $this->assertCount(2, $findSecond['result']['items']);
        $this->assertNotSame($findFirst['result']['items'][0]['backend_id'], $findSecond['result']['items'][0]['backend_id']);

        // vfs_count was removed in Phase 2: a final vfs_ls page carries the
        // exact count.
        $count = json_decode(app(ToolRegistry::class)->instantiateToolBySlug('vfs_ls', $this->agent)->handle(new Request(['path' => '/docs/by-id', 'limit' => 100])), true);
        $this->assertNull($count['result']['next_cursor']);
        $this->assertSame($count['result']['count'], count($count['result']['items']));
    }

    /**
     * Run Ruby through the real pinned mruby engine with an agent CodeBridge,
     * inside an active task so default write receipts are satisfiable.
     */
    private function runVfsRuby(string $code): CodeExecutionResult
    {
        $task = Task::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->agent->workspace_id,
            'title' => 'VFS Code Mode test',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_AGENT_DELEGATION,
            'agent_id' => $this->agent->id,
            'requester_id' => $this->agent->id,
            'started_at' => now(),
        ]);
        $registry = app(ToolRegistry::class);
        $registry->setTaskContext($task->id);
        $sandbox = app(MrubySandboxService::class);
        $bridge = new CodeBridge($this->agent, $registry, app(CodeApiDocGenerator::class), $sandbox->profile('agent'));

        return $sandbox->execute($code, 'agent', $bridge);
    }

    // Regression: the VFS stays reachable from Code Mode as `app.vfs.*`
    // (including the cat/grep aliases) without registering every helper as a
    // model-visible tool.
    public function test_vfs_code_namespace_exposes_structured_helpers_without_direct_tool_bloat(): void
    {
        $doc = Document::factory()->create([
            'title' => 'Ruby VFS Notes',
            'content' => "alpha\nbeta\nrefund",
            'author_id' => $this->agent->id,
        ]);

        $functionMap = app(CodeApiDocGenerator::class)->buildFunctionMap($this->agent);

        $this->assertSame('vfs_exec', $functionMap['vfs.exec']);
        $this->assertSame('vfs_ls', $functionMap['vfs.ls']);
        $this->assertSame('vfs_read', $functionMap['vfs.read']);
        $this->assertSame('vfs_read', $functionMap['vfs.cat']);
        $this->assertSame('vfs_rg', $functionMap['vfs.rg']);
        $this->assertSame('vfs_rg', $functionMap['vfs.grep']);
        $this->assertSame('vfs_find', $functionMap['vfs.find']);
        $this->assertSame('vfs_exists', $functionMap['vfs.exists']);
        $this->assertSame('vfs_edit', $functionMap['vfs.edit']);
        $this->assertSame('vfs_exec', $functionMap['vfs.shell']);
        // Phase 2 removed the redundant search/count helpers.
        $this->assertArrayNotHasKey('vfs.search', $functionMap);
        $this->assertArrayNotHasKey('vfs.count', $functionMap);
        $modelTools = array_map(fn ($tool) => method_exists($tool, 'name') ? $tool->name() : class_basename($tool), app(ToolRegistry::class)->getToolsForAgent($this->agent));
        $this->assertNotContains('vfs_rg', $modelTools);

        $result = $this->runVfsRuby(sprintf(<<<'RUBY'
stat = app.vfs.stat(path: "/docs/by-id/%s")
limited = app.vfs.ls(path: "/docs", limit: 1)
read = app.vfs.read(path: "/docs/by-id/%s", max_bytes: 1000)
cat = app.vfs.cat(path: "/docs/by-id/%s", max_bytes: 1000)
hits = app.vfs.rg(pattern: "refund", paths: ["/docs"], max_matches: 10)
literal = app.vfs.grep(pattern: "a.b", paths: ["/docs/by-id/%s"], max_matches: 10)
lexical = app.vfs.grep(pattern: "refund", paths: ["/docs"], max_matches: 10)
found = app.vfs.find(paths: ["/docs"], name: "*Ruby*", limit: 10)
exec = app.vfs.shell(command: "stat /docs/by-id/%s", response_format: "structured", include_command_spec: true)
{
  version: stat[:result][:version],
  limited_count: limited[:result][:count],
  content: read[:result][:content],
  cat_content: cat[:result][:content],
  first_hit: hits[:result][:matches].first[:match],
  hit_count: hits[:result][:count],
  lexical_count: lexical[:result][:count],
  literal_count: literal[:result][:count],
  found_count: found[:result][:count],
  exec_command: exec[:diagnostics][:command_spec][:name],
  exec_backend_id: exec[:data][:stat][:backend_id],
}
RUBY, $doc->id, $doc->id, $doc->id, $doc->id, $doc->id));

        $this->assertNull($result->error, json_encode($result->error));
        $payload = json_decode(json_encode($result->result), true);
        $this->assertNotEmpty($payload['version']);
        $this->assertSame(1, $payload['limited_count']);
        $this->assertStringContainsString('refund', $payload['content']);
        // cat is the raw alias of read: no line-number prefixes.
        $this->assertSame("alpha\nbeta\nrefund", $payload['cat_content']);
        $this->assertStringContainsString("     3\trefund", $payload['content']);
        $this->assertSame('refund', $payload['first_hit']);
        $this->assertGreaterThanOrEqual(1, $payload['hit_count']);
        $this->assertGreaterThanOrEqual(1, $payload['lexical_count']);
        $this->assertSame(0, $payload['literal_count']);
        $this->assertGreaterThanOrEqual(1, $payload['found_count']);
        $this->assertSame('stat', $payload['exec_command']);
        $this->assertSame($doc->id, $payload['exec_backend_id']);
    }

    // Regression: Code Mode mutation helpers act through the primitive VFS
    // write permission and per-path file checks, inside an active task.
    public function test_vfs_code_mutation_helpers_share_primitive_permissions(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $result = $this->runVfsRuby(<<<'RUBY'
app.vfs.mkdir(path: "/files/ruby-ax")
app.vfs.write(path: "/files/ruby-ax/a.txt", content: "alpha", mode: "overwrite")
before = app.vfs.exists(path: "/files/ruby-ax/a.txt")
count = app.vfs.ls(path: "/files/ruby-ax")
app.vfs.cp(source: "/files/ruby-ax/a.txt", destination: "/files/ruby-ax/b.txt")
app.vfs.mv(source: "/files/ruby-ax/b.txt", destination: "/files/ruby-ax/c.txt")
app.vfs.rm(path: "/files/ruby-ax/c.txt")
after = app.vfs.exists(path: "/files/ruby-ax/c.txt")
{before: before[:result][:exists], count: count[:result][:count], after: after[:result][:exists]}
RUBY);

        $this->assertNull($result->error, json_encode($result->error));
        $payload = json_decode(json_encode($result->result), true);
        $this->assertTrue($payload['before']);
        $this->assertGreaterThanOrEqual(1, $payload['count']);
        $this->assertFalse($payload['after']);
    }

    // Regression: denying the human-visible `vfs_exec` primitive also denies
    // the hidden script-only read helpers built on it.
    public function test_vfs_code_helpers_obey_primitive_tool_permissions(): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'vfs_exec',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);

        $functionMap = app(CodeApiDocGenerator::class)->buildFunctionMap($this->agent);
        $this->assertArrayNotHasKey('vfs.ls', $functionMap);

        $result = $this->runVfsRuby('app.call("vfs.ls", path: "/")');
        $this->assertNotNull($result->error);
    }

    public function test_generated_vfs_reads_respect_max_bytes_budgets(): void
    {
        $readTool = app(ToolRegistry::class)->instantiateToolBySlug('vfs_read', $this->agent);
        $read = json_decode($readTool->handle(new Request([
            'path' => '/tools/vfs.md',
            'maxBytes' => 200,
        ])), true);

        $this->assertTrue($read['ok']);
        $this->assertTrue($read['result']['truncated']);
        $this->assertLessThanOrEqual(200, strlen($read['result']['content']));
        // Phase 2: vfs_read pages on whole lines and says where to continue;
        // the shell's display read still cuts at bytes with the marker line.
        $this->assertSame($read['result']['returned_lines'] + 1, $read['result']['next_offset']);

        $execTool = app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent);
        $exec = json_decode($execTool->handle(new Request([
            'command' => 'cat /tools/vfs.md',
            'maxBytes' => 200,
        ])), true);

        // Phase 2: the shell shows the same prefix and replaces the bare
        // display suffix with the stable, sized truncation line.
        $this->assertTrue($exec['ok']);
        $this->assertStringContainsString('[truncated at ', $exec['stdout']);

        $commands = app(OpenCompanyVfs::class)->read($this->agent, '/tools/vfs/commands.json', new VfsBudget(maxBytes: 200));
        $settings = app(OpenCompanyVfs::class)->read($this->agent, '/workspace/settings.json', new VfsBudget(maxBytes: 10));
        // Paged JSON never splits a principal, so this budget must fit both
        // the valid roster envelope and the first complete principal record.
        $members = app(OpenCompanyVfs::class)->read($this->agent, '/workspace/members.json', new VfsBudget(maxBytes: 1_000));

        $this->assertLessThanOrEqual(200, strlen($commands));
        $this->assertStringEndsWith("\n[... truncated]", $commands);
        $this->assertLessThanOrEqual(10, strlen($settings));
        $this->assertLessThanOrEqual(1_000, strlen($members));

        $result = $this->runVfsRuby(
            'read = app.vfs.read(path: "/tools/vfs.md", max_bytes: 200); {content: read[:result][:content], truncated: read[:result][:truncated]}',
        );

        $this->assertNull($result->error, json_encode($result->error));
        $payload = json_decode(json_encode($result->result), true);
        $this->assertTrue($payload['truncated']);
        $this->assertSame($read['result']['content'], $payload['content']);
    }

    public function test_vfs_write_creates_workspace_files_inside_allowed_folder_scope(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $result = app(OpenCompanyVfs::class)->write(
            $this->agent,
            '/files/generated/report.md',
            "# Report\n\nVFS write works.",
        );

        $this->assertSame('created', $result['status']);

        $read = app(VfsCommandExecutor::class)->execute($this->agent, 'cat /files/generated/report.md');
        $this->assertStringContainsString('VFS write works', $read['stdout']);
    }

    public function test_vfs_file_canonical_folder_children_behave_like_display_paths(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        $allowedFolder = app(FileSystemService::class)->createFolder($this->workspace->id, null, 'allowed', $this->agent->id);
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => $allowedFolder->id,
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $executor = app(VfsCommandExecutor::class);
        $vfs = app(OpenCompanyVfs::class);

        $this->assertStringContainsString('created', $executor->execute($this->agent, 'mkdir /files/allowed/display-smoke')['stdout']);
        $this->assertStringContainsString('created', $executor->execute($this->agent, "mkdir /files/by-id/{$allowedFolder->id}/canonical-smoke")['stdout']);
        $executor->execute($this->agent, "printf hi > /files/by-id/{$allowedFolder->id}/canonical-smoke/hello.txt");

        $canonicalFile = "/files/by-id/{$allowedFolder->id}/canonical-smoke/hello.txt";
        $this->assertSame('hi', $executor->execute($this->agent, "cat {$canonicalFile}")['stdout']);
        $this->assertStringContainsString('hello.txt', $executor->execute($this->agent, "ls /files/by-id/{$allowedFolder->id}/canonical-smoke")['stdout']);

        $stat = $vfs->stat($this->agent, $canonicalFile);
        $this->assertSame('file', $stat['type']);
        $this->assertSame('hello.txt', $stat['name']);

        $read = json_decode(app(ToolRegistry::class)->instantiateToolBySlug('vfs_read', $this->agent)->handle(new Request([
            'path' => $canonicalFile,
            'raw' => true,
        ])), true);
        $this->assertSame('hi', $read['result']['content']);

        $patched = $vfs->patch($this->agent, $canonicalFile, '{"search":"hi","replace":"hello"}');
        $this->assertSame('updated', $patched['status']);
        $this->assertSame('hello', $executor->execute($this->agent, "cat {$canonicalFile}")['stdout']);

        try {
            $vfs->write($this->agent, "/files/by-id/{$stat['backend_id']}/junk", 'bad');
            $this->fail('Children under a canonical file path should not write through the parent file.');
        } catch (VfsError $e) {
            $this->assertSame('not_found', $e->errorCode);
        }

        $blockedFolder = app(FileSystemService::class)->createFolder($this->workspace->id, null, 'blocked-base', $this->agent->id);
        try {
            $executor->execute($this->agent, "mkdir /files/by-id/{$blockedFolder->id}/nope");
            $this->fail('Denied canonical folder children should not be created.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
        }
        $this->assertFalse(WorkspaceFile::forWorkspace()->where('parent_id', $blockedFolder->id)->where('name', 'nope')->exists());

        // rm moves to the trash since Phase 2 (C1): status "trashed".
        $this->assertStringContainsString('trashed', $executor->execute($this->agent, "rm {$canonicalFile}")['stdout']);
        $this->assertStringContainsString('trashed', $executor->execute($this->agent, "rm -d /files/by-id/{$allowedFolder->id}/canonical-smoke")['stdout']);
    }

    public function test_vfs_write_rejects_invalid_modes_without_overwriting(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $tool = app(ToolRegistry::class)->instantiateToolBySlug('vfs_write', $this->agent);
        $tool->handle(new Request([
            'path' => '/files/mode-check.txt',
            'content' => 'original',
            'mode' => 'create',
        ]));

        $result = json_decode($tool->handle(new Request([
            'path' => '/files/mode-check.txt',
            'content' => 'overwritten by typo',
            'mode' => 'cretae',
        ])), true);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_request', $result['error']['code']);
        $this->assertSame('original', app(OpenCompanyVfs::class)->read($this->agent, '/files/mode-check.txt'));
    }

    public function test_denied_vfs_file_write_does_not_create_parent_folders(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        $allowedFolder = app(FileSystemService::class)->createFolder($this->workspace->id, null, 'allowed', $this->agent->id);
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => $allowedFolder->id,
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        try {
            app(OpenCompanyVfs::class)->write($this->agent, '/files/blocked/report.md', 'x');
            $this->fail('Denied write should fail.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
        }

        foreach ([
            fn () => app(OpenCompanyVfs::class)->write($this->agent, '/files/root.txt', 'x'),
            fn () => app(VfsCommandExecutor::class)->execute($this->agent, 'touch /files/root.txt'),
            fn () => app(VfsCommandExecutor::class)->execute($this->agent, 'echo x | tee /files/root.txt'),
            fn () => app(VfsCommandExecutor::class)->execute($this->agent, 'echo x > /files/root.txt'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Restricted agents should not create root-level /files entries.');
            } catch (VfsError $e) {
                $this->assertSame('permission_denied', $e->errorCode);
            }
        }

        $this->assertFalse(WorkspaceFile::forWorkspace()->where('name', 'blocked')->exists());
        $this->assertFalse(WorkspaceFile::forWorkspace()->where('name', 'root.txt')->exists());
    }

    public function test_vfs_exec_file_mutation_commands_require_write_permission_and_work_for_allowed_agents(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $executor = app(VfsCommandExecutor::class);

        $this->assertStringContainsString('created', $executor->execute($this->agent, 'mkdir /files/tmp-vfs')['stdout']);
        $this->assertStringContainsString('created', $executor->execute($this->agent, 'touch /files/tmp-vfs/a.txt')['stdout']);
        $beforeVersion = app(OpenCompanyVfs::class)->stat($this->agent, '/files/tmp-vfs/a.txt')['version'];
        // tee copies its input to stdout (Phase 2), not a write-result JSON.
        $this->assertSame($executor->execute($this->agent, 'cat /docs')['stdout'], $executor->execute($this->agent, 'cat /docs | tee /files/tmp-vfs/a.txt')['stdout']);
        $afterVersion = app(OpenCompanyVfs::class)->stat($this->agent, '/files/tmp-vfs/a.txt')['version'];
        $this->assertNotSame($beforeVersion, $afterVersion);
        $this->assertSame("appended\n", $executor->execute($this->agent, 'echo appended | tee -a /files/tmp-vfs/a.txt')['stdout']);
        $this->assertStringContainsString('appended', $executor->execute($this->agent, 'cat /files/tmp-vfs/a.txt')['stdout']);
        $this->assertStringContainsString('copied', $executor->execute($this->agent, 'cp /files/tmp-vfs/a.txt /files/tmp-vfs/b.txt')['stdout']);
        $this->assertStringContainsString('created', $executor->execute($this->agent, 'mkdir /files/tmp-vfs/copies')['stdout']);
        $this->assertStringContainsString('copied', $executor->execute($this->agent, 'cp /files/tmp-vfs/a.txt /files/tmp-vfs/copies')['stdout']);
        $this->assertStringContainsString('appended', $executor->execute($this->agent, 'cat /files/tmp-vfs/copies/a.txt')['stdout']);
        // cp overwrites an existing destination with a versioned write (GNU);
        // mv still refuses without -f because replacing destroys the target.
        $this->assertStringContainsString('copied', $executor->execute($this->agent, 'cp /files/tmp-vfs/a.txt /files/tmp-vfs/copies/a.txt')['stdout']);
        foreach (['mv /files/tmp-vfs/a.txt /files/tmp-vfs/copies/a.txt'] as $command) {
            try {
                $executor->execute($this->agent, $command);
                $this->fail("{$command} should fail on an existing destination.");
            } catch (VfsError $e) {
                $this->assertSame('invalid_request', $e->errorCode);
            }
        }
        $executor->execute($this->agent, 'printf force > /files/tmp-vfs/force-src.txt');
        $executor->execute($this->agent, 'printf old > /files/tmp-vfs/force-dest.txt');
        $this->assertStringContainsString('copied', $executor->execute($this->agent, 'cp -f /files/tmp-vfs/force-src.txt /files/tmp-vfs/force-dest.txt')['stdout']);
        $this->assertSame('force', $executor->execute($this->agent, 'cat /files/tmp-vfs/force-dest.txt')['stdout']);
        $executor->execute($this->agent, 'printf move-force > /files/tmp-vfs/move-src.txt');
        $executor->execute($this->agent, 'printf old > /files/tmp-vfs/move-dest.txt');
        $this->assertStringContainsString('moved', $executor->execute($this->agent, 'mv -f /files/tmp-vfs/move-src.txt /files/tmp-vfs/move-dest.txt')['stdout']);
        $this->assertSame('move-force', $executor->execute($this->agent, 'cat /files/tmp-vfs/move-dest.txt')['stdout']);
        $sameMove = $executor->execute($this->agent, 'mv /files/tmp-vfs/a.txt /files/tmp-vfs/a.txt');
        $this->assertStringContainsString('unchanged', $sameMove['stdout']);
        $this->assertStringContainsString('moved', $executor->execute($this->agent, 'mv /files/tmp-vfs/b.txt /files/tmp-vfs/c.txt')['stdout']);
        $this->assertStringContainsString('updated', $executor->execute($this->agent, 'truncate -s 0 /files/tmp-vfs/c.txt')['stdout']);
        $this->assertStringContainsString('updated', $executor->execute($this->agent, 'truncate -s0 /files/tmp-vfs/c.txt')['stdout']);
        foreach (['truncate /files/tmp-vfs/c.txt', 'truncate -s abc /files/tmp-vfs/c.txt', 'tee --bad /files/tmp-vfs/c.txt', 'touch -d tomorrow /files/tmp-vfs/c.txt'] as $command) {
            try {
                $executor->execute($this->agent, $command);
                $this->fail("{$command} should fail.");
            } catch (VfsError $e) {
                $this->assertContains($e->errorCode, ['invalid_request', 'unsupported']);
            }
        }
        $this->assertStringContainsString('created', $executor->execute($this->agent, 'mkdir -p /files/tmp-vfs/deep/nested')['stdout']);
        $this->assertStringContainsString('created', $executor->execute($this->agent, 'touch /files/tmp-vfs/deep/nested/file.txt')['stdout']);
        // rm moves to the trash since Phase 2 (C1): status "trashed".
        $this->assertStringContainsString('trashed', $executor->execute($this->agent, 'rm -rf /files/tmp-vfs/deep')['stdout']);
        $tempFile = trim($executor->execute($this->agent, 'mktemp -p /files/tmp-vfs temp.XXXXXX')['stdout']);
        $tempDir = trim($executor->execute($this->agent, 'mktemp -d -p /files/tmp-vfs dir.XXXXXX')['stdout']);
        $this->assertMatchesRegularExpression('#^/files/tmp-vfs/temp\.[A-Za-z0-9]{6}$#', $tempFile);
        $this->assertMatchesRegularExpression('#^/files/tmp-vfs/dir\.[A-Za-z0-9]{6}$#', $tempDir);
        $this->assertSame('file', app(OpenCompanyVfs::class)->stat($this->agent, $tempFile)['type']);
        $this->assertSame('directory', app(OpenCompanyVfs::class)->stat($this->agent, $tempDir)['type']);
        $this->assertSame('', $executor->execute($this->agent, 'tar -c -f /files/tmp-vfs/archive.tar /files/tmp-vfs/a.txt')['stdout']);
        $this->assertStringContainsString('a.txt', $executor->execute($this->agent, 'tar -t -f /files/tmp-vfs/archive.tar')['stdout']);
        $this->assertStringContainsString('/files/tmp-vfs/extract/a.txt', $executor->execute($this->agent, 'tar -x -f /files/tmp-vfs/archive.tar -C /files/tmp-vfs/extract')['stdout']);
        $this->assertStringContainsString('appended', $executor->execute($this->agent, 'cat /files/tmp-vfs/extract/a.txt')['stdout']);
        $this->assertSame('', $executor->execute($this->agent, 'zip -r /files/tmp-vfs/archive.zip /files/tmp-vfs/a.txt')['stdout']);
        $this->assertStringContainsString('a.txt', $executor->execute($this->agent, 'unzip -l /files/tmp-vfs/archive.zip')['stdout']);
        $this->assertStringContainsString('/files/tmp-vfs/unzip/a.txt', $executor->execute($this->agent, 'unzip -o /files/tmp-vfs/archive.zip -d /files/tmp-vfs/unzip')['stdout']);
        $this->assertStringContainsString('appended', $executor->execute($this->agent, 'cat /files/tmp-vfs/unzip/a.txt')['stdout']);
        $this->assertStringContainsString('/files/tmp-vfs/part-aa', $executor->execute($this->agent, 'split -l 1 /files/tmp-vfs/a.txt /files/tmp-vfs/part-')['stdout']);
        $this->assertStringContainsString('appended', $executor->execute($this->agent, 'cat /files/tmp-vfs/part-aa')['stdout']);
        $this->assertStringContainsString("\n", $executor->execute($this->agent, 'csplit -f /files/tmp-vfs/cs- /files/tmp-vfs/a.txt /appended/')['stdout']);
        $this->assertStringContainsString('appended', $executor->execute($this->agent, 'cat /files/tmp-vfs/cs-01')['stdout']);
        $this->assertStringContainsString('copied', $executor->execute($this->agent, 'ln /files/tmp-vfs/a.txt /files/tmp-vfs/hard-link.txt')['stdout']);
        $this->assertStringContainsString('appended', $executor->execute($this->agent, 'cat /files/tmp-vfs/hard-link.txt')['stdout']);
        try {
            $executor->execute($this->agent, 'ln -s /files/tmp-vfs/a.txt /files/tmp-vfs/symlink.txt');
            $this->fail('ln -s should remain explicit until VFS has real symlink entries.');
        } catch (VfsError $e) {
            $this->assertSame('unsupported', $e->errorCode);
        }
        $fileId = app(OpenCompanyVfs::class)->stat($this->agent, '/files/tmp-vfs/a.txt')['backend_id'];
        try {
            app(OpenCompanyVfs::class)->write($this->agent, "/files/by-id/{$fileId}/junk", 'bad');
            $this->fail('Trailing file by-id junk should not mutate the parent file.');
        } catch (VfsError $e) {
            $this->assertSame('not_found', $e->errorCode);
        }
        // rm moves to the trash since Phase 2 (C1): status "trashed".
        $this->assertStringContainsString('trashed', $executor->execute($this->agent, 'rm /files/tmp-vfs/c.txt')['stdout']);
    }

    public function test_vfs_exec_file_mutations_do_not_bypass_approval_required_write_tool(): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
        // vfs_write is a reversible edit (auto-applies by default); an admin
        // rule makes it approval-required, and vfs_exec must honour that.
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'vfs_write',
            'permission' => 'allow',
            'requires_approval' => true,
        ]);

        $raw = app(VfsExec::class, [
            'agent' => $this->agent,
            'executor' => app(VfsCommandExecutor::class),
        ])->handle(new Request(['command' => 'mkdir /files/nope']));

        $decoded = json_decode($raw, true);

        // Phase 2: approval-pending is the non-error pending_approval envelope.
        $this->assertSame('pending_approval', $decoded['status']);
        $this->assertSame(['vfs_write'], $decoded['approval_tools']);
        $approval = ApprovalRequest::query()->latest()->first();
        $this->assertNotNull($approval);
        $this->assertSame('vfs_exec', $approval->tool_execution_context['tool_slug']);
        $this->assertSame('vfs_write', $approval->tool_execution_context['vfs_approval']['approval_tool']);

        app(ApprovalExecutionService::class)->resolve($approval, 'approved', $this->workspaceApprover());

        $this->assertTrue(WorkspaceFile::forWorkspace()->where('name', 'nope')->where('is_folder', true)->exists());
    }

    public function test_approved_vfs_write_revalidates_tool_permission_before_execution(): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
        $approval = ApprovalRequest::factory()->create([
            'requester_id' => $this->agent->id,
            'status' => 'pending',
            'tool_execution_context' => [
                'tool_slug' => 'vfs_write',
                'parameters' => [
                    'path' => '/files/approved-denied/report.md',
                    'content' => 'should not write',
                    'mode' => 'overwrite',
                ],
            ],
        ]);
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'vfs_write',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);

        app(ApprovalExecutionService::class)->resolve($approval, 'approved', $this->workspaceApprover());

        $this->assertFalse(WorkspaceFile::forWorkspace()->where('name', 'approved-denied')->exists());
    }

    public function test_vfs_patch_updates_limited_task_and_list_fields(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        $task = Task::create([
            'id' => 'task-vfs-patch',
            'workspace_id' => $this->workspace->id,
            'title' => 'Patch me',
            'description' => 'Original task description',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'agent_id' => $this->agent->id,
            'requester_id' => $this->agent->id,
            'source' => Task::SOURCE_MANUAL,
        ]);

        $project = ListItem::create([
            'id' => 'list-vfs-patch-project',
            'workspace_id' => $this->workspace->id,
            'title' => 'Patch Project',
            'description' => 'Patch project container',
            'is_folder' => true,
            'status' => 'backlog',
            'creator_id' => $this->agent->id,
        ]);
        $item = ListItem::create([
            'id' => 'list-vfs-patch-item',
            'workspace_id' => $this->workspace->id,
            'parent_id' => $project->id,
            'title' => 'Patch list item',
            'description' => 'Original list description',
            'is_folder' => false,
            'status' => 'backlog',
            'priority' => 'low',
            'creator_id' => $this->agent->id,
        ]);

        $vfs = app(OpenCompanyVfs::class);
        $otherAgent = User::factory()->agent()->create(['workspace_id' => $this->workspace->id]);
        $otherTask = Task::create([
            'id' => 'task-vfs-other-agent',
            'workspace_id' => $this->workspace->id,
            'title' => 'Other task',
            'description' => 'Should not mutate',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'agent_id' => $otherAgent->id,
            'requester_id' => $otherAgent->id,
            'source' => Task::SOURCE_MANUAL,
        ]);
        $otherProject = ListItem::create([
            'id' => 'list-vfs-other-project',
            'workspace_id' => $this->workspace->id,
            'title' => 'Other project',
            'description' => 'Other project container',
            'is_folder' => true,
            'status' => 'backlog',
            'creator_id' => $otherAgent->id,
        ]);
        $otherItem = ListItem::create([
            'id' => 'list-vfs-other-agent',
            'workspace_id' => $this->workspace->id,
            'parent_id' => $otherProject->id,
            'title' => 'Other list item',
            'description' => 'Should not mutate',
            'is_folder' => false,
            'status' => 'backlog',
            'priority' => 'low',
            'creator_id' => $otherAgent->id,
            'assignee_id' => $otherAgent->id,
        ]);

        $taskResult = $vfs->patch($this->agent, "/tasks/by-id/{$task->id}", json_encode([
            'status' => Task::STATUS_COMPLETED,
            'priority' => Task::PRIORITY_HIGH,
        ], JSON_THROW_ON_ERROR));
        $itemResult = $vfs->patch($this->agent, "/lists/by-id/{$item->id}", json_encode([
            'description' => 'Updated through VFS',
            'priority' => 'urgent',
        ], JSON_THROW_ON_ERROR));

        $this->assertSame('updated', $taskResult['status']);
        $this->assertSame(Task::STATUS_COMPLETED, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame(Task::PRIORITY_HIGH, $task->fresh()->priority);
        $this->assertSame('updated', $itemResult['status']);
        $this->assertSame('Updated through VFS', $item->fresh()->description);
        $this->assertSame('urgent', $item->fresh()->priority);

        try {
            $vfs->patch($this->agent, "/tasks/by-id/{$otherTask->id}", json_encode(['status' => Task::STATUS_COMPLETED], JSON_THROW_ON_ERROR));
            $this->fail('VFS task patch should not mutate another agent task.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
        }
        $this->assertSame(Task::STATUS_ACTIVE, $otherTask->fresh()->status);

        try {
            $vfs->patch($this->agent, "/lists/by-id/{$otherItem->id}", json_encode(['description' => 'Mutated'], JSON_THROW_ON_ERROR));
            $this->fail('VFS list patch should not mutate another agent list item.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
        }
        $this->assertSame('Should not mutate', $otherItem->fresh()->description);
    }

    public function test_vfs_table_rows_are_patchable_records_not_bulk_exports(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives and the
        // backing domain write explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write', 'update_table_row');
        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Customers',
            'description' => 'Customer list',
            'created_by' => $this->agent->id,
        ]);
        $emailColumn = DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'Email',
            'type' => 'email',
            'order' => 0,
            'required' => false,
        ]);
        $statusColumn = DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'Status',
            'type' => 'text',
            'order' => 1,
            'required' => false,
        ]);
        $row = DataTableRow::create([
            'table_id' => $table->id,
            'data' => [$emailColumn->id => 'old@example.com', $statusColumn->id => 'lead'],
            'created_by' => $this->agent->id,
        ]);

        $vfs = app(OpenCompanyVfs::class);
        $rowPath = "/tables/customers/rows/by-id/{$row->id}.json";

        $this->assertStringContainsString('rows/', app(VfsCommandExecutor::class)->execute($this->agent, 'ls /tables/customers')['stdout']);
        $rowsListing = $vfs->list($this->agent, '/tables/customers/rows/by-id');
        $this->assertSame($rowPath, $rowsListing[0]->path);
        $this->assertContains('patch', $rowsListing[0]->capabilities);

        $payload = json_decode($vfs->read($this->agent, $rowPath), true);
        $this->assertSame('old@example.com', $payload['data']['Email']);
        $this->assertSame('old@example.com', $payload['data_by_column_id'][$emailColumn->id]);
        $this->assertSame($row->id, $payload['id']);

        $stat = $vfs->stat($this->agent, $rowPath);
        $this->assertContains('patch', $stat['capabilities']);
        $this->assertSame('allowed', $stat['metadata']['mutation_permission']);
        $bulkStat = $vfs->stat($this->agent, '/tables/customers/rows.ndjson');
        $this->assertNotContains('patch', $bulkStat['capabilities']);
        $this->assertSame('rows.ndjson is a bulk export and cannot be patched safely.', $bulkStat['metadata']['read_only_reason']);

        $vfs->patch($this->agent, $rowPath, json_encode(['data' => ['email' => 'new@example.com']], JSON_THROW_ON_ERROR), $payload['version']);
        $this->assertSame('new@example.com', $row->fresh()->data[$emailColumn->id]);

        $vfs->patch($this->agent, $rowPath, json_encode(['data' => [$statusColumn->id => 'active']], JSON_THROW_ON_ERROR));
        $this->assertSame('active', $row->fresh()->data[$statusColumn->id]);

        try {
            $vfs->patch($this->agent, $rowPath, json_encode(['data' => ['unknown' => 'value']], JSON_THROW_ON_ERROR));
            $this->fail('Unknown table columns should be rejected.');
        } catch (VfsError $e) {
            $this->assertSame('invalid_request', $e->errorCode);
        }

        DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'status',
            'type' => 'text',
            'order' => 2,
            'required' => false,
        ]);
        $ambiguousPayload = json_decode($vfs->read($this->agent, $rowPath), true);
        $this->assertContains('Status', $ambiguousPayload['ambiguous_column_names']);
        $this->assertArrayNotHasKey('Status', $ambiguousPayload['data']);
        try {
            $vfs->patch($this->agent, $rowPath, json_encode(['data' => ['status' => 'blocked']], JSON_THROW_ON_ERROR));
            $this->fail('Ambiguous column names should require a UUID.');
        } catch (VfsError $e) {
            $this->assertSame('invalid_request', $e->errorCode);
        }

        try {
            $vfs->patch($this->agent, $rowPath, json_encode(['data' => ['Email' => 'stale@example.com']], JSON_THROW_ON_ERROR), $payload['version']);
            $this->fail('Stale row versions should fail.');
        } catch (VfsError $e) {
            // Stale versions moved from the overloaded invalid_request to the
            // dedicated `conflict` code, which carries the current version.
            $this->assertSame('conflict', $e->errorCode);
            $this->assertSame($payload['version'], $e->context['given_version']);
            $this->assertNotSame($payload['version'], $e->context['current_version']);
        }

        try {
            $vfs->patch($this->agent, '/tables/customers/rows.ndjson', json_encode(['data' => ['Email' => 'bad@example.com']], JSON_THROW_ON_ERROR));
            $this->fail('Bulk exports should not be patchable.');
        } catch (VfsError $e) {
            $this->assertSame('not_writable', $e->errorCode);
        }
    }

    public function test_vfs_table_row_patch_follows_update_table_row_permission(): void
    {
        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Permissioned Customers',
            'created_by' => $this->agent->id,
        ]);
        $column = DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'Status',
            'type' => 'text',
            'order' => 0,
            'required' => false,
        ]);
        $row = DataTableRow::create([
            'table_id' => $table->id,
            'data' => [$column->id => 'lead'],
            'created_by' => $this->agent->id,
        ]);
        $rowPath = "/tables/permissioned-customers/rows/by-id/{$row->id}.json";

        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'update_table_row',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);

        $vfs = app(OpenCompanyVfs::class);
        $stat = $vfs->stat($this->agent, $rowPath);
        $this->assertNotContains('patch', $stat['capabilities']);
        $this->assertSame('denied', $stat['metadata']['mutation_permission']);
        $this->assertSame('update_table_row', $stat['metadata']['mutation_denied_tool']);

        try {
            $vfs->patch($this->agent, $rowPath, json_encode(['data' => ['Status' => 'active']], JSON_THROW_ON_ERROR));
            $this->fail('Denied update_table_row permission should block VFS row patching.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
        }

        $this->assertSame('lead', $row->fresh()->data[$column->id]);
    }

    public function test_vfs_table_row_patch_surfaces_update_table_row_approval(): void
    {
        // The policy model now gates the vfs_patch primitive by default too,
        // and VfsMutationPolicy reports the first gated tool in required
        // order. Grant the primitive so this test still pins the backing
        // domain permission as the approval tool.
        $this->allowTools($this->agent, 'vfs_patch');
        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Approval Customers',
            'created_by' => $this->agent->id,
        ]);
        $column = DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'Status',
            'type' => 'text',
            'order' => 0,
            'required' => false,
        ]);
        $row = DataTableRow::create([
            'table_id' => $table->id,
            'data' => [$column->id => 'lead'],
            'created_by' => $this->agent->id,
        ]);
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'update_table_row',
            'permission' => 'allow',
            'requires_approval' => true,
        ]);

        $vfs = app(OpenCompanyVfs::class);
        $rowPath = "/tables/approval-customers/rows/by-id/{$row->id}.json";
        $stat = $vfs->stat($this->agent, $rowPath);
        $this->assertContains('patch', $stat['capabilities']);
        $this->assertTrue($stat['metadata']['mutation_requires_approval']);
        $this->assertSame('update_table_row', $stat['metadata']['mutation_approval_tool']);

        try {
            $vfs->patch($this->agent, $rowPath, json_encode(['data' => ['Status' => 'active']], JSON_THROW_ON_ERROR));
            $this->fail('Approval-required table row mutation should not be bypassed by VFS.');
        } catch (VfsError $e) {
            $this->assertSame('approval_required', $e->errorCode);
            $this->assertSame('update_table_row', $e->context['tool']);
            $this->assertNotEmpty($e->context['approval_id']);
        }

        $this->assertSame('lead', $row->fresh()->data[$column->id]);

        $approval = ApprovalRequest::query()->latest()->first();
        $this->assertNotNull($approval);
        $this->assertSame('vfs_patch', $approval->tool_execution_context['tool_slug']);
        $this->assertSame('update_table_row', $approval->tool_execution_context['vfs_approval']['approval_tool']);
        $this->assertSame($rowPath, $approval->tool_execution_context['vfs_approval']['subject']);

        app(ApprovalExecutionService::class)->resolve($approval, 'approved', $this->workspaceApprover());

        $this->assertSame('active', $row->fresh()->data[$column->id]);
    }

    public function test_vfs_list_display_paths_patch_same_record_as_canonical_paths(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        $project = ListItem::create([
            'id' => 'display-path-project',
            'workspace_id' => $this->workspace->id,
            'title' => 'Display Path Project',
            'description' => 'Project folder',
            'is_folder' => true,
            'status' => 'backlog',
            'creator_id' => $this->agent->id,
        ]);
        $item = ListItem::create([
            'id' => 'display-path-item',
            'workspace_id' => $this->workspace->id,
            'parent_id' => $project->id,
            'title' => 'Add VFS',
            'description' => 'Original child description',
            'is_folder' => false,
            'status' => 'backlog',
            'priority' => 'low',
            'creator_id' => $this->agent->id,
        ]);

        $vfs = app(OpenCompanyVfs::class);
        $folderStat = $vfs->stat($this->agent, "/lists/by-id/{$project->id}");
        $displayPath = "/lists/by-id/{$project->id}/add-vfs.md";
        $displayStat = $vfs->stat($this->agent, $displayPath);

        $this->assertNotContains('patch', $folderStat['capabilities']);
        $this->assertContains('patch', $displayStat['capabilities']);
        $this->assertSame("/lists/by-id/{$item->id}", $displayStat['canonical_path']);

        $displayResult = $vfs->patch($this->agent, $displayPath, json_encode(['description' => 'Updated through display path'], JSON_THROW_ON_ERROR));
        $this->assertSame("/lists/by-id/{$item->id}", $displayResult['canonical_path']);
        $this->assertSame('Updated through display path', $item->fresh()->description);

        $vfs->patch($this->agent, "/lists/by-id/{$item->id}", json_encode(['priority' => 'urgent'], JSON_THROW_ON_ERROR));
        $this->assertSame('urgent', $item->fresh()->priority);
    }

    public function test_vfs_agent_memory_files_are_patchable_but_memory_directories_are_not(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write');
        $agentFolder = app(AgentDocumentService::class)->createAgentDocumentStructure($this->agent, [
            'MEMORY' => 'Old durable memory',
        ]);
        $this->agent->update(['docs_folder_id' => $agentFolder->id]);
        $this->agent->refresh();

        $vfs = app(OpenCompanyVfs::class);
        $memoryDir = "/agents/by-id/{$this->agent->id}/memory";
        $memoryPath = "{$memoryDir}/MEMORY.md";

        $directoryStat = $vfs->stat($this->agent, $memoryDir);
        $fileStat = $vfs->stat($this->agent, $memoryPath);

        $this->assertNotContains('patch', $directoryStat['capabilities']);
        $this->assertContains('patch', $fileStat['capabilities']);

        $result = $vfs->patch(
            $this->agent,
            $memoryPath,
            json_encode(['search' => 'Old durable', 'replace' => 'Updated durable'], JSON_THROW_ON_ERROR),
            $fileStat['version'],
        );

        $memoryDoc = Document::findOrFail($fileStat['backend_id']);
        $this->assertSame('updated', $result['status']);
        $this->assertStringContainsString('Updated durable memory', $memoryDoc->fresh()->content);
        $this->assertSame('Old durable memory', DocumentVersion::where('document_id', $memoryDoc->id)->first()?->content);
        $this->assertTrue(DocumentChunk::where('document_id', $memoryDoc->id)->where('collection', 'memory')->where('agent_id', $this->agent->id)->exists());

        $stranger = User::factory()->agent()->create(['workspace_id' => $this->workspace->id]);
        try {
            $vfs->stat($stranger, $memoryPath);
            $this->fail('Unrelated agents should not see private memory paths.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
        }
    }

    public function test_vfs_agent_identity_and_manager_memory_patch_rules_are_enforced(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives and the
        // backing domain write explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write', 'update_agent_identity_file');
        $directReport = User::factory()->agent()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Report Agent',
            'manager_id' => $this->agent->id,
        ]);
        $agentFolder = app(AgentDocumentService::class)->createAgentDocumentStructure($directReport, [
            'IDENTITY' => 'Old identity',
            'MEMORY' => 'Old core memory',
        ]);
        $directReport->update(['docs_folder_id' => $agentFolder->id]);
        $directReport->refresh();

        $memoryFolder = Document::where('parent_id', $agentFolder->id)->where('title', 'memory')->firstOrFail();
        $topicsFolder = Document::where('parent_id', $memoryFolder->id)->where('title', 'topics')->firstOrFail();
        $topic = Document::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'title' => 'roadmap.md',
            'parent_id' => $topicsFolder->id,
            'content' => 'Old topic memory',
            'author_id' => $directReport->id,
            'is_folder' => false,
            'is_system' => true,
        ]);

        $vfs = app(OpenCompanyVfs::class);
        $identityPath = "/agents/by-id/{$directReport->id}/identity/IDENTITY.md";
        $memoryPath = "/agents/by-id/{$directReport->id}/memory/MEMORY.md";
        $topicPath = "/agents/by-id/{$directReport->id}/memory/topics/roadmap.md";

        $identityStat = $vfs->stat($this->agent, $identityPath);
        $memoryStat = $vfs->stat($this->agent, $memoryPath);
        $topicStat = $vfs->stat($this->agent, $topicPath);

        $this->assertContains('patch', $identityStat['capabilities']);
        $this->assertContains('patch', $memoryStat['capabilities']);
        $this->assertNotContains('patch', $topicStat['capabilities']);

        $vfs->patch($this->agent, $identityPath, json_encode(['search' => 'Old identity', 'replace' => 'Manager adjusted identity'], JSON_THROW_ON_ERROR));
        $vfs->patch($this->agent, $memoryPath, json_encode(['search' => 'Old core', 'replace' => 'Manager adjusted core'], JSON_THROW_ON_ERROR));

        $this->assertStringContainsString('Manager adjusted identity', Document::findOrFail($identityStat['backend_id'])->content);
        $this->assertStringContainsString('Manager adjusted core memory', Document::findOrFail($memoryStat['backend_id'])->content);

        try {
            $vfs->patch($this->agent, $topicPath, json_encode(['search' => 'Old topic', 'replace' => 'Mutated topic'], JSON_THROW_ON_ERROR));
            $this->fail('Managers should not patch direct report topic memory through VFS.');
        } catch (VfsError $e) {
            $this->assertSame('permission_denied', $e->errorCode);
        }
        $this->assertSame('Old topic memory', $topic->fresh()->content);
    }

    public function test_vfs_direct_reports_cannot_patch_manager_agent_documents(): void
    {
        $managerFolder = app(AgentDocumentService::class)->createAgentDocumentStructure($this->agent, [
            'IDENTITY' => 'Manager identity',
            'MEMORY' => 'Manager memory',
        ]);
        $this->agent->update(['docs_folder_id' => $managerFolder->id]);
        $this->agent->refresh();

        $directReport = User::factory()->agent()->create([
            'workspace_id' => $this->workspace->id,
            'manager_id' => $this->agent->id,
        ]);

        $vfs = app(OpenCompanyVfs::class);
        $identityPath = "/agents/by-id/{$this->agent->id}/identity/IDENTITY.md";
        $memoryPath = "/agents/by-id/{$this->agent->id}/memory/MEMORY.md";

        $this->assertNotContains('patch', $vfs->stat($directReport, $identityPath)['capabilities']);
        $this->assertNotContains('patch', $vfs->stat($directReport, $memoryPath)['capabilities']);

        foreach ([$identityPath, $memoryPath] as $path) {
            try {
                $vfs->patch($directReport, $path, json_encode(['search' => 'Manager', 'replace' => 'Mutated'], JSON_THROW_ON_ERROR));
                $this->fail('Direct reports should not patch manager agent documents.');
            } catch (VfsError $e) {
                $this->assertSame('permission_denied', $e->errorCode);
            }
        }
    }

    public function test_vfs_agent_identity_patch_creates_and_replays_backing_approval(): void
    {
        // The policy model now gates the vfs_patch primitive by default too,
        // and VfsMutationPolicy reports the first gated tool in required
        // order. Grant the primitive so this test still pins the backing
        // domain permission as the approval tool.
        $this->allowTools($this->agent, 'vfs_patch');
        $directReport = User::factory()->agent()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Approval Report',
            'manager_id' => $this->agent->id,
        ]);
        $agentFolder = app(AgentDocumentService::class)->createAgentDocumentStructure($directReport, [
            'IDENTITY' => 'Old identity',
        ]);
        $directReport->update(['docs_folder_id' => $agentFolder->id]);

        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'update_agent_identity_file',
            'permission' => 'allow',
            'requires_approval' => true,
        ]);

        $vfs = app(OpenCompanyVfs::class);
        $identityPath = "/agents/by-id/{$directReport->id}/identity/IDENTITY.md";
        $stat = $vfs->stat($this->agent, $identityPath);

        $this->assertContains('patch', $stat['capabilities']);
        $this->assertSame('requires_approval', $stat['metadata']['mutation_permission']);
        $this->assertSame('update_agent_identity_file', $stat['metadata']['mutation_approval_tool']);

        try {
            $vfs->patch($this->agent, $identityPath, json_encode(['search' => 'Old identity', 'replace' => 'Approved identity'], JSON_THROW_ON_ERROR));
            $this->fail('Identity backing approval should create a request before mutating.');
        } catch (VfsError $e) {
            $this->assertSame('approval_required', $e->errorCode);
            $this->assertSame('update_agent_identity_file', $e->context['tool']);
        }

        $this->assertSame('Old identity', Document::findOrFail($stat['backend_id'])->content);

        $approval = ApprovalRequest::query()->latest()->first();
        $this->assertNotNull($approval);
        $this->assertSame('update_agent_identity_file', $approval->tool_execution_context['vfs_approval']['approval_tool']);

        app(ApprovalExecutionService::class)->resolve($approval, 'approved', $this->workspaceApprover());

        $this->assertSame('Approved identity', Document::findOrFail($stat['backend_id'])->fresh()->content);
    }

    public function test_vfs_read_only_metadata_and_read_capabilities_are_visible(): void
    {
        $channel = Channel::factory()->public()->create(['name' => 'metadata-general', 'creator_id' => $this->agent->id]);
        Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $this->agent->id,
            'content' => 'Read-only metadata transcript',
            'timestamp' => now(),
        ]);
        $approval = ApprovalRequest::factory()->create([
            'requester_id' => $this->agent->id,
            'title' => 'Approve metadata test',
        ]);
        $routine = Routine::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Metadata Routine',
            'kind' => 'agent',
            'agent_id' => $this->agent->id,
            'instructions' => 'Run metadata',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'created_by_id' => $this->agent->id,
            'is_active' => true,
            'keep_history' => true,
        ]);
        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Metadata Table',
            'created_by' => $this->agent->id,
        ]);

        $vfs = app(OpenCompanyVfs::class);
        $channelStat = $vfs->stat($this->agent, '/channels/metadata-general/messages/'.now()->toDateString().'.md');
        $approvalStat = $vfs->stat($this->agent, "/approvals/{$approval->id}.json");
        $routineStat = $vfs->stat($this->agent, "/routines/{$routine->id}.json");
        $workspaceStat = $vfs->stat($this->agent, '/workspace/settings.json');
        $tableStat = $vfs->stat($this->agent, '/tables/metadata-table/schema.json');
        $toolsStat = $vfs->stat($this->agent, '/tools/vfs/commands.json');

        foreach ([$channelStat, $approvalStat, $routineStat, $workspaceStat, $tableStat, $toolsStat] as $stat) {
            $this->assertArrayHasKey('read_only_reason', $stat['metadata']);
            $this->assertNotContains('patch', $stat['capabilities']);
            $this->assertNotContains('write', $stat['capabilities']);
        }

        $read = json_decode(app(ToolRegistry::class)->instantiateToolBySlug('vfs_read', $this->agent)->handle(new Request([
            'path' => "/routines/{$routine->id}.json",
        ])), true);
        $this->assertTrue($read['ok']);
        $this->assertSame(['read'], $read['result']['capabilities']);
        // Phase 2: vfs_read carries compact capabilities only; the full
        // metadata stays on stat.
        $this->assertSame('Routine JSON is a generated routine record.', $routineStat['metadata']['read_only_reason']);

        try {
            $vfs->patch($this->agent, '/tables/metadata-table/schema.json', json_encode(['search' => 'Metadata', 'replace' => 'Changed'], JSON_THROW_ON_ERROR));
            $this->fail('Generated schema snapshots should not be patchable.');
        } catch (VfsError $e) {
            $this->assertSame('not_writable', $e->errorCode);
        }
    }

    public function test_vfs_capability_truth_contract_for_representative_mount_entries(): void
    {
        // Mechanics test: the policy model requires approval for writes by
        // default; grant the VFS primitives and backing domain writes
        // explicitly.
        $this->allowTools($this->agent, 'vfs_patch', 'vfs_write', 'update_table_row', 'update_agent_identity_file');
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $docFolder = Document::factory()->create(['title' => 'Contract Docs', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $doc = Document::factory()->create([
            'title' => 'Contract Doc',
            'parent_id' => $docFolder->id,
            'content' => 'contract document old',
            'author_id' => $this->agent->id,
        ]);
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'folder',
            'scope_key' => $docFolder->id,
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $fileFolder = app(FileSystemService::class)->createFolder($this->workspace->id, null, 'contract-files', $this->agent->id);
        app(FileSystemService::class)->writeFile($this->workspace->id, $fileFolder->id, 'note.md', 'contract file old', $this->agent->id);

        $task = Task::create([
            'id' => 'contract-task',
            'workspace_id' => $this->workspace->id,
            'title' => 'Contract Task',
            'description' => 'contract task old',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'agent_id' => $this->agent->id,
            'requester_id' => $this->agent->id,
            'source' => Task::SOURCE_MANUAL,
        ]);
        $project = ListItem::create([
            'id' => 'contract-project',
            'workspace_id' => $this->workspace->id,
            'title' => 'Contract Project',
            'description' => 'contract project',
            'is_folder' => true,
            'status' => 'backlog',
            'creator_id' => $this->agent->id,
        ]);
        $list = ListItem::create([
            'id' => 'contract-list',
            'workspace_id' => $this->workspace->id,
            'parent_id' => $project->id,
            'title' => 'Contract List',
            'description' => 'contract list old',
            'status' => 'backlog',
            'creator_id' => $this->agent->id,
            'assignee_id' => $this->agent->id,
        ]);
        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Contract Table',
            'created_by' => $this->agent->id,
        ]);
        $column = DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'Status',
            'type' => 'text',
            'order' => 0,
            'required' => false,
        ]);
        $row = DataTableRow::create([
            'table_id' => $table->id,
            'data' => [$column->id => 'old'],
            'created_by' => $this->agent->id,
        ]);
        $agentFolder = app(AgentDocumentService::class)->createAgentDocumentStructure($this->agent, [
            'IDENTITY' => 'contract identity old',
            'MEMORY' => 'contract memory old',
        ]);
        $this->agent->update(['docs_folder_id' => $agentFolder->id]);
        $this->agent->refresh();

        $vfs = app(OpenCompanyVfs::class);

        $patchCases = [
            "/docs/by-id/{$doc->id}" => ['search' => 'old', 'replace' => 'new'],
            '/files/contract-files/note.md' => ['search' => 'old', 'replace' => 'new'],
            "/tasks/by-id/{$task->id}" => ['description' => 'contract task new'],
            "/lists/by-id/{$list->id}" => ['description' => 'contract list new'],
            "/tables/contract-table/rows/by-id/{$row->id}.json" => ['data' => ['Status' => 'new']],
            "/agents/by-id/{$this->agent->id}/identity/IDENTITY.md" => ['search' => 'old', 'replace' => 'new'],
            "/agents/by-id/{$this->agent->id}/memory/MEMORY.md" => ['search' => 'old', 'replace' => 'new'],
        ];

        foreach ($patchCases as $path => $patch) {
            $stat = $vfs->stat($this->agent, $path);
            $this->assertContains('patch', $stat['capabilities'], $path);

            $result = $vfs->patch($this->agent, $path, json_encode($patch, JSON_THROW_ON_ERROR), $stat['version'] ?? null);
            $this->assertSame('updated', $result['status'], $path);
        }

        $writeDirStat = $vfs->stat($this->agent, "/files/by-id/{$fileFolder->id}");
        $this->assertContains('write', $writeDirStat['capabilities']);
        $writeResult = $vfs->write($this->agent, "/files/by-id/{$fileFolder->id}/created-through-contract.txt", 'ok');
        $this->assertSame('created', $writeResult['status']);

        foreach ([
            '/workspace/settings.json',
            '/tools/vfs/commands.json',
            '/tables/contract-table/schema.json',
            '/tables/contract-table/rows.ndjson',
        ] as $path) {
            $stat = $vfs->stat($this->agent, $path);
            $this->assertNotContains('patch', $stat['capabilities'], $path);
            $this->assertNotContains('write', $stat['capabilities'], $path);
            $this->assertArrayHasKey('read_only_reason', $stat['metadata'], $path);
            $this->assertArrayHasKey('mutation_hint', $stat['metadata'], $path);
        }
    }

    public function test_vfs_reads_tasks_lists_channels_tables_approvals_routines_and_workspace(): void
    {
        $task = Task::create([
            'id' => 'task-vfs',
            'workspace_id' => $this->workspace->id,
            'title' => 'Ship VFS',
            'description' => 'Implement deterministic virtual filesystem.',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_HIGH,
            'agent_id' => $this->agent->id,
            'requester_id' => $this->agent->id,
            'source' => Task::SOURCE_MANUAL,
        ]);

        $project = ListItem::create([
            'id' => 'list-project',
            'workspace_id' => $this->workspace->id,
            'title' => 'Platform',
            'description' => 'Platform project',
            'is_folder' => true,
            'status' => 'active',
            'creator_id' => $this->agent->id,
        ]);
        $item = ListItem::create([
            'id' => 'list-item',
            'workspace_id' => $this->workspace->id,
            'parent_id' => $project->id,
            'title' => 'Add VFS',
            'description' => 'Add unified filesystem access',
            'is_folder' => false,
            'status' => 'todo',
            'creator_id' => $this->agent->id,
        ]);

        $channel = Channel::factory()->public()->create(['name' => 'general', 'creator_id' => $this->agent->id]);
        Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $this->agent->id,
            'content' => 'VFS channel transcript line',
            'timestamp' => now(),
        ]);

        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Customers',
            'description' => 'Customer list',
            'created_by' => $this->agent->id,
        ]);
        DataTableRow::create([
            'table_id' => $table->id,
            'data' => ['email' => 'vfs@example.com'],
            'created_by' => $this->agent->id,
        ]);
        DataTableView::create([
            'table_id' => $table->id,
            'name' => 'Grid',
            'type' => 'grid',
            'filters' => [],
            'sorts' => [],
            'hidden_columns' => [],
            'config' => [],
        ]);
        $human = User::factory()->create(['name' => 'Human Member', 'email' => 'human@example.com']);
        $this->workspace->members()->attach($human->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
        ]);
        $routineA = Routine::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Daily Sync',
            'kind' => 'agent',
            'agent_id' => $this->agent->id,
            'instructions' => 'Run sync',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'created_by_id' => $this->agent->id,
            'is_active' => true,
            'keep_history' => true,
        ]);
        Routine::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Daily Sync',
            'kind' => 'agent',
            'agent_id' => $this->agent->id,
            'instructions' => 'Run other sync',
            'cron_expression' => '0 10 * * *',
            'timezone' => 'UTC',
            'created_by_id' => $this->agent->id,
            'is_active' => true,
            'keep_history' => true,
        ]);

        $executor = app(VfsCommandExecutor::class);

        $root = $executor->execute($this->agent, 'ls /')['stdout'];
        foreach (['agents/', 'docs/', 'files/', 'tasks/', 'lists/', 'channels/', 'tables/', 'tools/', 'routines/', 'approvals/', 'workspace/'] as $mount) {
            $this->assertStringContainsString($mount, $root);
        }
        $this->assertStringContainsString(
            '/routines',
            $executor->execute($this->agent, 'find / -maxdepth 1 -name "*routine*" -type d')['stdout'],
        );
        $this->assertStringContainsString(
            '"type": "directory"',
            $executor->execute($this->agent, 'stat /routines')['stdout'],
        );
        $this->assertStringContainsString('Ship VFS', $executor->execute($this->agent, "cat /tasks/by-id/{$task->id}")['stdout']);
        $this->assertStringContainsString('Ship VFS', $executor->execute($this->agent, 'rg "Ship VFS" /tasks/recent.md')['stdout']);
        $this->assertStringContainsString('active', $executor->execute($this->agent, 'ls /tasks/by-status')['stdout']);
        $this->assertStringContainsString('atlas', $executor->execute($this->agent, 'ls /tasks/by-agent')['stdout']);
        $this->assertStringContainsString('ship-vfs.md', $executor->execute($this->agent, 'ls /agents/by-id/'.$this->agent->id.'/tasks')['stdout']);
        $this->assertStringContainsString('Add VFS', $executor->execute($this->agent, "cat /lists/by-id/{$item->id}")['stdout']);
        $this->assertStringContainsString('add-vfs.md', $executor->execute($this->agent, "ls /lists/by-id/{$project->id}")['stdout']);
        $this->assertStringContainsString('add-vfs.md', $executor->execute($this->agent, "cat /lists/by-id/{$project->id}")['stdout']);
        $this->assertStringContainsString('Add VFS', $executor->execute($this->agent, "cat /lists/by-id/{$project->id}/add-vfs.md")['stdout']);
        $this->assertStringContainsString('todo', $executor->execute($this->agent, 'ls /lists/by-status')['stdout']);
        $this->assertStringContainsString('human-member', $executor->execute($this->agent, 'ls /lists/by-assignee')['stdout']);
        $this->assertStringContainsString($channel->id, $executor->execute($this->agent, 'ls /channels/by-id')['stdout']);
        $this->assertStringContainsString('VFS channel transcript line', $executor->execute($this->agent, 'cat /channels/general/messages/'.now()->toDateString().'.md')['stdout']);
        $this->assertStringContainsString('VFS channel transcript line', $executor->execute($this->agent, 'cat /channels/by-id/'.$channel->id.'/messages/'.now()->toDateString().'.md')['stdout']);
        $this->assertStringContainsString('vfs@example.com', $executor->execute($this->agent, 'cat /tables/customers/rows.ndjson')['stdout']);
        // rows.ndjson is name-keyed since Phase 2 (C1); this row's key is not a
        // column of the table, so it only appears under data_by_column_id.
        $this->assertStringContainsString('vfs@example.com', $executor->execute($this->agent, 'cat /tables/customers/rows.ndjson | jq ".data_by_column_id.email"')['stdout']);
        $this->assertStringContainsString('"type": "file"', $executor->execute($this->agent, 'stat /tables/customers/rows.ndjson')['stdout']);
        $this->assertStringContainsString('"backend_type": "data_table"', $executor->execute($this->agent, 'stat /tables/customers')['stdout']);
        $this->assertStringContainsString('grid--', $executor->execute($this->agent, 'ls /tables/customers/views')['stdout']);
        $this->assertStringContainsString('members.json', $executor->execute($this->agent, 'ls /workspace')['stdout']);
        // Phase 2: human emails need an explicit members grant (list_members allow row).
        $this->assertStringNotContainsString('human@example.com', $executor->execute($this->agent, 'cat /workspace/members.json')['stdout']);
        $this->assertStringContainsString('"email_redacted": true', $executor->execute($this->agent, 'cat /workspace/members.json')['stdout']);
        $this->assertStringContainsString('"role": "admin"', $executor->execute($this->agent, 'cat /workspace/members.json')['stdout']);
        $this->assertStringContainsString('"membership_id"', $executor->execute($this->agent, 'cat /workspace/members.json')['stdout']);
        $this->assertStringContainsString('/agents/by-id/'.$this->agent->id, $executor->execute($this->agent, 'cat /workspace/members.json')['stdout']);
        $this->assertStringContainsString('commands.json', $executor->execute($this->agent, 'ls /tools/vfs')['stdout']);
        $this->assertStringContainsString('rg', $executor->execute($this->agent, 'cat /tools/vfs/commands.json')['stdout']);
        $this->assertStringContainsString('members.json', $executor->execute($this->agent, 'ls /workspace/*.json')['stdout']);
        $this->assertStringContainsString('settings.json', $executor->execute($this->agent, 'ls /workspace/*.json')['stdout']);
        // Phase 2: /tools/apps|integrations|mcp (integration package
        // indexes) were replaced by the agent's effective tool surface.
        $this->assertStringContainsString('index.json', $executor->execute($this->agent, 'ls /tools')['stdout']);
        $this->assertStringContainsString('"kind": "tool_surface"', $executor->execute($this->agent, 'cat /tools/index.json')['stdout']);
        $this->assertStringContainsString('"vfs_exec"', $executor->execute($this->agent, 'cat /tools/index.json', '/', new VfsBudget(maxBytes: 2_000_000))['stdout']);
        $this->assertStringContainsString('vfs_read', $executor->execute($this->agent, 'cat /tools/catalog.md', '/', new VfsBudget(maxBytes: 2_000_000))['stdout']);
        $this->assertFalse($executor->execute($this->agent, 'ls /tools', '/', new VfsBudget(maxEntries: 4))['result']['truncated']);
        $this->assertTrue($executor->execute($this->agent, 'ls /tools', '/', new VfsBudget(maxEntries: 1))['result']['truncated']);
        $this->assertStringContainsString($routineA->id, $executor->execute($this->agent, 'cat /routines/'.$routineA->id.'.json')['stdout']);
        $this->assertStringContainsString('daily-sync--', $executor->execute($this->agent, 'ls /routines')['stdout']);
        $this->assertStringContainsString('"truncated"', $executor->execute($this->agent, 'stat /docs')['stdout']);
        $this->assertStringContainsString('"count_is_exact": true', $executor->execute($this->agent, 'stat /tools/vfs')['stdout']);
        $this->assertStringNotContainsString('count_sample', $executor->execute($this->agent, 'stat /docs')['stdout']);

        $directFind = app(ToolRegistry::class)->instantiateToolBySlug('vfs_find', $this->agent)->handle(new Request(['paths' => ['/tasks'], 'maxDepth' => 0, 'limit' => 10]));
        $directFindDecoded = json_decode($directFind, true);
        $this->assertSame('/tasks', $directFindDecoded['result']['items'][0]['path']);
        $this->assertSame(1, $directFindDecoded['result']['returned']);

        $directLs = json_decode(app(ToolRegistry::class)->instantiateToolBySlug('vfs_ls', $this->agent)->handle(new Request(['path' => '/tasks', 'limit' => 7])), true);
        $this->assertFalse($directLs['result']['truncated']);

        foreach ([
            "cat /tasks/by-id/{$task->id}/junk",
            "cat /lists/by-id/{$item->id}/junk",
            'cat /channels/general/junk',
            'cat /tables/customers/schema.json/junk',
            'cat /tables/customers/rows.ndjson/junk',
            'cat /workspace/members.json/junk',
            'cat /routines/'.$routineA->id.'.json/junk',
        ] as $command) {
            $decoded = json_decode(app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent)->handle(new Request(['command' => $command])), true);
            $this->assertFalse($decoded['ok'], $command);
        }
    }

    public function test_unsupported_host_commands_return_structured_errors(): void
    {
        $tool = app(ToolRegistry::class)->instantiateToolBySlug('vfs_exec', $this->agent);

        $raw = $tool->handle(new Request(['command' => 'bash -lc "cat /etc/passwd"']));
        $decoded = json_decode($raw, true);

        $this->assertFalse($decoded['ok']);
        $this->assertSame('unsupported', $decoded['error']['code']);

        $raw = $tool->handle(new Request(['command' => 'bash -lc "cat /etc/passwd" || true']));
        $decoded = json_decode($raw, true);

        $this->assertFalse($decoded['ok']);
        $this->assertSame('unsupported', $decoded['error']['code']);

        $raw = $tool->handle(new Request(['command' => 'ls --host-flag /']));
        $decoded = json_decode($raw, true);

        $this->assertFalse($decoded['ok']);
        $this->assertSame('unsupported', $decoded['error']['code']);

        foreach (['echo hi & echo bye', 'cat $(which rg)', 'cat `which rg`', 'cat <(echo hi)', 'echo $HOME'] as $command) {
            $decoded = json_decode($tool->handle(new Request(['command' => $command])), true);

            $this->assertFalse($decoded['ok'], $command);
            $this->assertSame('unsupported', $decoded['error']['code'], $command);
        }

        foreach (['printf "%s" "unterminated', 'echo trailing\\'] as $command) {
            $decoded = json_decode($tool->handle(new Request(['command' => $command])), true);

            $this->assertFalse($decoded['ok'], $command);
            $this->assertSame('invalid_request', $decoded['error']['code'], $command);
        }

        $emptyArg = app(VfsCommandExecutor::class)->execute($this->agent, 'printf "[%s]" ""');
        $this->assertSame('[]', $emptyArg['stdout']);
    }

    /**
     * @return array<string, mixed>
     */
    private function runAtlasCommand(string $command, array $options = [], int $expectedExit = 0): array
    {
        $exit = Artisan::call('vfs:atlas', [
            'vfsCommand' => [$command],
            '--json' => true,
            ...$options,
        ]);
        $output = Artisan::output();
        $this->assertSame($expectedExit, $exit, $output);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded, $output);

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function runAtlasPrimitive(string $primitive, array $payload, int $expectedExit = 0): array
    {
        $args = [];
        foreach ($payload as $key => $value) {
            $args[] = $key.'='.(is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR));
        }

        $exit = Artisan::call('vfs:atlas', [
            '--primitive' => $primitive,
            '--arg' => $args,
            '--json' => true,
        ]);
        $output = Artisan::output();
        $this->assertSame($expectedExit, $exit, $output);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded, $output);

        return $decoded;
    }

    /**
     * A human admin of the test workspace. ApprovalExecutionService::resolve()
     * refuses unattributed (null) responders since ApprovalResolutionPolicy,
     * so tests that resolve approvals directly must name an approver.
     */
    private function workspaceApprover(): User
    {
        $approver = User::factory()->create(['type' => 'human']);
        WorkspaceMember::query()->firstOrCreate(
            ['workspace_id' => $this->workspace->id, 'user_id' => $approver->id],
            ['role' => 'admin'],
        );

        return $approver;
    }
}

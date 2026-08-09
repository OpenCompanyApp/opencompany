<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\ToolRegistry;
use App\Agents\Tools\Vfs\VfsExec;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsOperationLog;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\DataTableView;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\ListItem;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\VfsOperationEvent;
use App\Models\Workspace;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use App\Services\ApprovalExecutionService;
use App\Services\FileSystemService;
use App\Services\LuaApiDocGenerator;
use App\Services\LuaBridge;
use App\Services\LuaSandboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class VfsFeatureTest extends TestCase
{
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

        $search = $executor->execute($this->agent, 'rg enterprise /docs');
        $this->assertStringContainsString("/docs/by-id/{$doc->id}", $search['stdout']);

        $structured = app(OpenCompanyVfs::class)->search($this->agent, 'enterprise', ['/docs']);
        $this->assertSame('source_index+live', $structured[0]['backend']);
    }

    public function test_vfs_patch_updates_documents_with_search_replace(): void
    {
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

        $this->expectExceptionMessage('Path is not writable through VFS');

        app(OpenCompanyVfs::class)->write($this->agent, "/docs/by-id/{$doc->id}", 'Raw overwrite');
    }

    public function test_vfs_patch_respects_document_folder_scope(): void
    {
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

        $event = VfsOperationEvent::query()->where('operation', 'vfs_exec')->firstOrFail();

        $this->assertSame($this->workspace->id, $event->workspace_id);
        $this->assertSame($this->agent->id, $event->agent_id);
        $this->assertTrue($event->success);
        $this->assertSame('/', $event->cwd);
        $this->assertStringContainsString('[redacted]', $event->metadata['command']);
        $this->assertStringNotContainsString('sk-test-secret', json_encode($event->metadata));
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
            'du -h /docs',
            'stat --format=%n /docs',
            'file --mime /docs',
            'cut --complement',
            'tr -s a b',
            'paste -d, /docs /docs',
            'which -a rg',
            'env -0',
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
            'which rg definitely_missing',
            'type ll definitely_missing',
            'printenv WORKSPACE_ID EXTRA',
        ] as $command) {
            $decoded = json_decode($tool->handle(new Request(['command' => $command])), true);

            $this->assertFalse($decoded['ok'], $command);
            $this->assertSame('invalid_request', $decoded['error']['code'], $command);
        }
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
        $this->assertStringContainsString('+ gamma', $executor->execute($this->agent, 'diff /docs/by-id/'.$first->id.' /docs/by-id/'.$second->id)['stdout']);
        $this->assertStringContainsString('differ', $executor->execute($this->agent, 'cmp /docs/by-id/'.$first->id.' /docs/by-id/'.$second->id)['stdout']);
        $this->assertStringContainsString('alpha', $executor->execute($this->agent, 'comm /docs/by-id/'.$first->id.' /docs/by-id/'.$second->id)['stdout']);
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
        $this->assertSame("first\nsecond\nthird", trim((string) $executor->execute($this->agent, 'echo first; echo second; echo third')['result']['stdout']));
        $this->assertSame('ok', trim($executor->execute($this->agent, 'true && echo ok')['stdout']));
        $this->assertSame('fallback', trim($executor->execute($this->agent, 'false || echo fallback')['stdout']));
        $this->assertSame('OR', trim($executor->execute($this->agent, 'false && echo AND || echo OR')['stdout']));
        $this->assertSame('after', trim($executor->execute($this->agent, 'ls /nonexistent 2>/dev/null; echo after')['stdout']));
        $this->assertFalse($executor->execute($this->agent, 'ls /nonexistent 2>/dev/null && echo hidden')['ok']);
        $merged = $executor->execute($this->agent, 'ls /nonexistent 2>&1');
        $this->assertFalse($merged['ok']);
        $this->assertStringContainsString('Path not found', $merged['stdout']);
        $this->assertSame('1', trim($executor->execute($this->agent, 'ls /nonexistent 2>&1 | grep -c "Path not found"')['stdout']));
        try {
            $executor->execute($this->agent, 'ls /nonexistent 2>/files/errors.txt');
            $this->fail('stderr file redirects should fail loudly.');
        } catch (VfsError $e) {
            $this->assertSame('unsupported', $e->errorCode);
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
        foreach (['echo ok | wc /missing', 'wc /docs/by-id/'.$first->id.' /missing'] as $command) {
            try {
                $executor->execute($this->agent, $command);
                $this->fail("{$command} should fail.");
            } catch (VfsError $e) {
                $this->assertSame('not_found', $e->errorCode);
            }
        }

        $this->assertSame("alpha 1\nalpha 2\nzeta 3\nbeta 10", $executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | sort -n -k 2')['stdout']);
        $this->assertSame("zeta 3\nbeta 10\nalpha 2\nalpha 1", $executor->execute($this->agent, 'cat /docs/by-id/'.$first->id.' | sort -r')['stdout']);
        $this->assertStringContainsString('      2 alpha', $executor->execute($this->agent, 'cat /docs/by-id/'.$duplicates->id.' | uniq -c')['stdout']);
        $this->assertSame('alpha', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$duplicates->id.' | uniq -d')['stdout']));
        $this->assertSame('beta', trim($executor->execute($this->agent, 'cat /docs/by-id/'.$duplicates->id.' | uniq -u')['stdout']));
        $this->assertSame('hello world', $executor->execute($this->agent, 'printf "hello %s" world')['stdout']);
        $this->assertSame("alpha\nbeta\n", $executor->execute($this->agent, 'printf "%s\n" alpha beta')['stdout']);
        $this->assertSame("score\n10\n2", $executor->execute($this->agent, 'cat /docs/by-id/'.$csv->id.' | awk -F , "{print $2}"')['stdout']);
        $this->assertSame('cba', trim($executor->execute($this->agent, 'echo abc | rev')['stdout']));
        $this->assertSame('b', trim($executor->execute($this->agent, 'echo "a b c" | cut -d\' \' -f2')['stdout']));
        $this->assertSame("a\nb\nc", trim($executor->execute($this->agent, 'echo "a;b;c" | tr ";" "\n"')['stdout']));
        $this->assertSame('HELLO', trim($executor->execute($this->agent, 'echo hello | tr "a-z" "A-Z"')['stdout']));
        $this->assertSame('hll wrld', trim($executor->execute($this->agent, 'echo "hello world" | tr -d "aeiou"')['stdout']));
        $this->assertStringContainsString('alpha 1', $executor->execute($this->agent, 'sort /docs/by-id/'.$first->id.' | head -n 1')['stdout']);
        $this->assertStringContainsString('alice,10', $executor->execute($this->agent, 'sort /docs/by-id/'.$first->id.' /docs/by-id/'.$csv->id)['stdout']);
        $this->assertStringContainsString('Alpha AX Notes', $executor->execute($this->agent, 'find /docs/by-id -name "Alpha AX*" | xargs -n 1 stat')['stdout']);
        $this->assertSame('a\\b', $executor->execute($this->agent, 'printf "%s" "a\\\\b"')['stdout']);
        $this->assertStringContainsString("\t", $executor->execute($this->agent, 'paste /docs/by-id/'.$first->id.' /docs/by-id/'.$csv->id)['stdout']);
        $this->assertStringContainsString('/docs/by-id/'.$first->id, $executor->execute($this->agent, 'rg alpha /docs --max-count 1')['stdout']);
        $this->assertStringContainsString('/docs/by-id/'.$first->id, $executor->execute($this->agent, 'rg alpha /docs --max-depth 2 --max-count 1')['stdout']);
        $this->assertSame('/docs/by-id/'.$first->id.':alpha 2', trim($executor->execute($this->agent, 'rg alpha /docs/by-id/'.$first->id.' -m1')['stdout']));
        $this->assertSame("alpha\nalpha", trim($executor->execute($this->agent, 'grep -o alpha /docs/by-id/'.$first->id)['stdout']));
        $this->assertSame('2', trim($executor->execute($this->agent, 'grep -c alpha /docs/by-id/'.$first->id)['stdout']));
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
        $this->assertStringContainsString('  /docs/by-id/'.$first->id, $executor->execute($this->agent, 'checksum /docs/by-id/'.$first->id)['stdout']);

        $directIgnoreCase = app(ToolRegistry::class)->instantiateToolBySlug('vfs_rg', $this->agent)->handle(new Request(['pattern' => 'ALPHA', 'paths' => ['/docs/by-id/'.$first->id], 'ignore_case' => true]));
        $directSearchIgnoreCase = app(ToolRegistry::class)->instantiateToolBySlug('vfs_search', $this->agent)->handle(new Request(['query' => 'ALPHA', 'paths' => ['/docs/by-id/'.$first->id], 'case_insensitive' => true]));
        $this->assertGreaterThanOrEqual(1, json_decode($directIgnoreCase, true)['result']['count']);
        $this->assertGreaterThanOrEqual(1, json_decode($directSearchIgnoreCase, true)['result']['count']);
    }

    public function test_vfs_exec_supports_globs_jq_file_args_redirection_and_find_budgets(): void
    {
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
        $this->assertSame("alpha\nbeta", trim($executor->execute($this->agent, 'jq ".items[].name" /files/glob-json/a.json')['stdout']));
        $this->assertSame('2', trim($executor->execute($this->agent, 'jq ".items | length" /files/glob-json/a.json')['stdout']));
        $this->assertStringContainsString('alpha', $executor->execute($this->agent, 'jq ".[0]" /files/glob-json/list.json')['stdout']);
        $this->assertStringContainsString('alpha', $executor->execute($this->agent, 'jq ".[]" /files/glob-json/list.json')['stdout']);
        $this->assertSame("alpha\nbeta", trim($executor->execute($this->agent, 'jq ".[] | .name" /files/glob-json/list.json')['stdout']));
        $this->assertSame('["alpha","beta"]', trim($executor->execute($this->agent, 'jq -c "map(.name)" /files/glob-json/list.json')['stdout']));
        $this->assertStringContainsString('alpha', $executor->execute($this->agent, 'jq -c ".[] | select(.name == \'alpha\')" /files/glob-json/list.json')['stdout']);
        $this->assertSame('4', trim($executor->execute($this->agent, 'jq ".n + 2" /files/glob-json/a.json')['stdout']));
        $this->assertSame('5', trim($executor->execute($this->agent, 'jq ".subtotal + .tax" /files/glob-json/price.json')['stdout']));
        $this->assertStringContainsString('gamma', $executor->execute($this->agent, 'jq ".items[0]" /files/glob-json/a.json /files/glob-json/b.json')['stdout']);
        $executor->execute($this->agent, 'printf \'{"data":{"customer-email":"buyer@example.com"},"source":"file"}\' > /files/glob-json/keyed.json');
        $this->assertSame('buyer@example.com', trim($executor->execute($this->agent, 'jq \'.data["customer-email"]\' /files/glob-json/keyed.json')['stdout']));
        $this->assertSame('file', trim($executor->execute($this->agent, 'printf \'{"source":"stdin"}\' | jq ".source" /files/glob-json/keyed.json')['stdout']));

        foreach (['jq nope /files/glob-json/a.json', 'jq ".items[" /files/glob-json/a.json', 'jq ".." /files/glob-json/a.json'] as $command) {
            try {
                $executor->execute($this->agent, $command);
                $this->fail("{$command} should fail.");
            } catch (VfsError $e) {
                $this->assertContains($e->errorCode, ['invalid', 'unsupported']);
            }
        }

        $this->assertStringContainsString('continued', $executor->execute($this->agent, 'ls /missing 2>&1 || echo continued')['stdout']);
        $redirectedError = $executor->execute($this->agent, 'ls /missing > /files/glob-json/errors.txt 2>&1');
        $this->assertFalse($redirectedError['ok']);
        $this->assertSame('', trim($redirectedError['stdout']));
        $this->assertStringContainsString('Path not found', $executor->execute($this->agent, 'cat /files/glob-json/errors.txt')['stdout']);
        $orderedError = $executor->execute($this->agent, 'ls /missing 2>&1 > /files/glob-json/stdout-only.txt');
        $this->assertFalse($orderedError['ok']);
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

        $count = json_decode(app(ToolRegistry::class)->instantiateToolBySlug('vfs_count', $this->agent)->handle(new Request(['path' => '/docs/by-id', 'limit' => 100])), true);
        $this->assertTrue($count['result']['count_is_exact']);
        $this->assertSame($count['result']['count'], $count['result']['total_count']);
    }

    public function test_vfs_lua_namespace_exposes_structured_helpers_without_direct_tool_bloat(): void
    {
        $doc = Document::factory()->create([
            'title' => 'Lua VFS Notes',
            'content' => "alpha\nbeta\nrefund",
            'author_id' => $this->agent->id,
        ]);

        $docs = app(LuaApiDocGenerator::class);
        $functionMap = $docs->buildFunctionMap($this->agent);

        $this->assertSame('vfs_exec', $functionMap['vfs.exec']);
        $this->assertSame('vfs_ls', $functionMap['vfs.ls']);
        $this->assertSame('vfs_read', $functionMap['vfs.read']);
        $this->assertSame('vfs_read', $functionMap['vfs.cat']);
        $this->assertSame('vfs_rg', $functionMap['vfs.rg']);
        $this->assertSame('vfs_rg', $functionMap['vfs.grep']);
        $this->assertSame('vfs_find', $functionMap['vfs.find']);
        $this->assertSame('vfs_search', $functionMap['vfs.search']);
        $this->assertSame('vfs_exists', $functionMap['vfs.exists']);
        $this->assertSame('vfs_count', $functionMap['vfs.count']);

        $bridge = new LuaBridge($this->agent, app(ToolRegistry::class), $docs);
        $code = sprintf(<<<'LUA'
local stat = app.vfs.stat("/docs/by-id/%s")
local limited = app.vfs.ls("/docs", { limit = 1 })
local read = app.vfs.read("/docs/by-id/%s", { max_bytes = 1000 })
local cat = app.vfs.cat("/docs/by-id/%s", { max_bytes = 1000 })
local hits = app.vfs.rg("refund", { "/docs" }, { max_matches = 10 })
local literal_hits = app.vfs.grep("a.b", { "/docs/by-id/%s" }, { max_matches = 10 })
local lexical_hits = app.vfs.search("refund", { "/docs" }, { max_matches = 10 })
local found = app.vfs.find({ "/docs" }, { name = "*Lua*", limit = 10 })
local markdown = app.vfs.find({ "/docs" }, { name = "*.md", type = "file", limit = 10 })
local markdown_by_string = app.vfs.find("/docs", { glob = "*.md", type = "file", limit = 10 })
return {
  version = stat.result.version,
  limited_len = #limited,
  limited_count = limited.result.count,
  content = read.result.content,
  cat_content = cat.result.content,
  hits_len = #hits,
  first_hit = hits[1] and hits[1].match or nil,
  hit_count = hits.result.count,
  lexical_len = #lexical_hits,
  lexical_first = lexical_hits[1] and lexical_hits[1].match or nil,
  lexical_count = lexical_hits.result.count,
  lexical_unique = lexical_hits[1] and (lexical_hits[1].path .. ":" .. tostring(lexical_hits[1].line) .. ":" .. tostring(lexical_hits[1].match)) or nil,
  literal_count = literal_hits.result.count,
  found_count = found.result.count,
  markdown_count = markdown.result.count,
  markdown_string_count = markdown_by_string.result.count,
}
LUA, $doc->id, $doc->id, $doc->id, $doc->id);

        $result = app(LuaSandboxService::class)->execute($code, bridge: $bridge);

        $this->assertNull($result->error);
        $this->assertIsArray($result->result);
        $payload = $result->result[0] ?? $result->result;
        $this->assertNotEmpty($payload['version']);
        $this->assertSame(1, $payload['limited_len']);
        $this->assertSame(1, $payload['limited_count']);
        $this->assertStringContainsString('refund', $payload['content']);
        $this->assertStringContainsString('refund', $payload['cat_content']);
        $this->assertGreaterThanOrEqual(1, $payload['hits_len']);
        $this->assertSame('refund', $payload['first_hit']);
        $this->assertGreaterThanOrEqual(1, $payload['hit_count']);
        $this->assertGreaterThanOrEqual(1, $payload['lexical_len']);
        $this->assertSame('refund', $payload['lexical_first']);
        $this->assertGreaterThanOrEqual(1, $payload['lexical_count']);
        $this->assertStringContainsString('refund', $payload['lexical_unique']);
        $this->assertSame(0, $payload['literal_count']);
        $this->assertGreaterThanOrEqual(1, $payload['found_count']);
        $this->assertGreaterThanOrEqual(1, $payload['markdown_count']);
        $this->assertGreaterThanOrEqual(1, $payload['markdown_string_count']);
    }

    public function test_vfs_lua_mutation_helpers_share_primitive_permissions(): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $bridge = new LuaBridge($this->agent, app(ToolRegistry::class), app(LuaApiDocGenerator::class));
        $code = <<<'LUA'
app.vfs.mkdir("/files/lua-ax")
app.vfs.write("/files/lua-ax/a.txt", "alpha", { mode = "overwrite" })
app.vfs.write({ path = "/files/lua-ax/a.txt", content = "alpha two" })
local exists_before = app.vfs.exists("/files/lua-ax/a.txt")
local count = app.vfs.count("/files/lua-ax")
app.vfs.cp("/files/lua-ax/a.txt", "/files/lua-ax/b.txt")
app.vfs.mv("/files/lua-ax/b.txt", "/files/lua-ax/c.txt")
app.vfs.rm("/files/lua-ax/c.txt")
local exists_after = app.vfs.exists("/files/lua-ax/c.txt")
return {
  before = exists_before.result.exists,
  count = count.result.count,
  after = exists_after.result.exists,
}
LUA;

        $result = app(LuaSandboxService::class)->execute($code, bridge: $bridge);

        $this->assertNull($result->error);
        $payload = $result->result[0] ?? $result->result;
        $this->assertTrue($payload['before']);
        $this->assertGreaterThanOrEqual(1, $payload['count']);
        $this->assertFalse($payload['after']);
    }

    public function test_vfs_lua_helpers_obey_primitive_tool_permissions(): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'vfs_exec',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);

        $bridge = new LuaBridge($this->agent, app(ToolRegistry::class), app(LuaApiDocGenerator::class));
        $result = app(LuaSandboxService::class)->execute('return app.vfs.ls("/")', bridge: $bridge);

        $this->assertNotNull($result->error);
        $this->assertStringContainsString('Permission denied', $result->error);
    }

    public function test_vfs_write_creates_workspace_files_inside_allowed_folder_scope(): void
    {
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

    public function test_vfs_write_rejects_invalid_modes_without_overwriting(): void
    {
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
        $this->assertStringContainsString('updated', $executor->execute($this->agent, 'cat /docs | tee /files/tmp-vfs/a.txt')['stdout']);
        $afterVersion = app(OpenCompanyVfs::class)->stat($this->agent, '/files/tmp-vfs/a.txt')['version'];
        $this->assertNotSame($beforeVersion, $afterVersion);
        $this->assertStringContainsString('updated', $executor->execute($this->agent, 'echo appended | tee -a /files/tmp-vfs/a.txt')['stdout']);
        $this->assertStringContainsString('appended', $executor->execute($this->agent, 'cat /files/tmp-vfs/a.txt')['stdout']);
        $this->assertStringContainsString('copied', $executor->execute($this->agent, 'cp /files/tmp-vfs/a.txt /files/tmp-vfs/b.txt')['stdout']);
        $this->assertStringContainsString('created', $executor->execute($this->agent, 'mkdir /files/tmp-vfs/copies')['stdout']);
        $this->assertStringContainsString('copied', $executor->execute($this->agent, 'cp /files/tmp-vfs/a.txt /files/tmp-vfs/copies')['stdout']);
        $this->assertStringContainsString('appended', $executor->execute($this->agent, 'cat /files/tmp-vfs/copies/a.txt')['stdout']);
        foreach (['cp /files/tmp-vfs/a.txt /files/tmp-vfs/copies/a.txt', 'mv /files/tmp-vfs/a.txt /files/tmp-vfs/copies/a.txt'] as $command) {
            try {
                $executor->execute($this->agent, $command);
                $this->fail("{$command} should fail on an existing destination.");
            } catch (VfsError $e) {
                $this->assertSame('invalid_request', $e->errorCode);
            }
        }
        $sameMove = $executor->execute($this->agent, 'mv /files/tmp-vfs/a.txt /files/tmp-vfs/a.txt');
        $this->assertStringContainsString('unchanged', $sameMove['stdout']);
        $this->assertStringContainsString('moved', $executor->execute($this->agent, 'mv /files/tmp-vfs/b.txt /files/tmp-vfs/c.txt')['stdout']);
        $this->assertStringContainsString('updated', $executor->execute($this->agent, 'truncate -s 0 /files/tmp-vfs/c.txt')['stdout']);
        $this->assertStringContainsString('updated', $executor->execute($this->agent, 'truncate -s0 /files/tmp-vfs/c.txt')['stdout']);
        foreach (['truncate /files/tmp-vfs/c.txt', 'truncate -s abc /files/tmp-vfs/c.txt', 'rm -rf /files/tmp-vfs/c.txt', 'tee --bad /files/tmp-vfs/c.txt'] as $command) {
            try {
                $executor->execute($this->agent, $command);
                $this->fail("{$command} should fail.");
            } catch (VfsError $e) {
                $this->assertContains($e->errorCode, ['invalid_request', 'unsupported']);
            }
        }
        $fileId = app(OpenCompanyVfs::class)->stat($this->agent, '/files/tmp-vfs/a.txt')['backend_id'];
        try {
            app(OpenCompanyVfs::class)->write($this->agent, "/files/by-id/{$fileId}/junk", 'bad');
            $this->fail('Trailing file by-id junk should not mutate the parent file.');
        } catch (VfsError $e) {
            $this->assertSame('not_found', $e->errorCode);
        }
        $this->assertStringContainsString('deleted', $executor->execute($this->agent, 'rm /files/tmp-vfs/c.txt')['stdout']);
    }

    public function test_vfs_exec_file_mutations_do_not_bypass_approval_required_write_tool(): void
    {
        $this->agent->update(['behavior_mode' => 'supervised']);

        $raw = app(VfsExec::class, [
            'agent' => $this->agent,
            'executor' => app(VfsCommandExecutor::class),
        ])->handle(new Request(['command' => 'mkdir /files/nope']));

        $decoded = json_decode($raw, true);

        $this->assertFalse($decoded['ok']);
        $this->assertSame('approval_required', $decoded['error']['code']);
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

        app(ApprovalExecutionService::class)->resolve($approval, 'approved');

        $this->assertFalse(WorkspaceFile::forWorkspace()->where('name', 'approved-denied')->exists());
    }

    public function test_vfs_patch_updates_limited_task_and_list_fields(): void
    {
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

    public function test_vfs_reads_tasks_lists_channels_tables_approvals_automations_and_workspace(): void
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
        $automationA = Automation::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Daily Sync',
            'execution_type' => 'prompt',
            'agent_id' => $this->agent->id,
            'prompt' => 'Run sync',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'created_by_id' => $this->agent->id,
            'is_active' => true,
            'keep_history' => true,
        ]);
        Automation::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Daily Sync',
            'execution_type' => 'prompt',
            'agent_id' => $this->agent->id,
            'prompt' => 'Run other sync',
            'cron_expression' => '0 10 * * *',
            'timezone' => 'UTC',
            'created_by_id' => $this->agent->id,
            'is_active' => true,
            'keep_history' => true,
        ]);

        $executor = app(VfsCommandExecutor::class);

        $root = $executor->execute($this->agent, 'ls /')['stdout'];
        foreach (['agents/', 'docs/', 'files/', 'tasks/', 'lists/', 'channels/', 'tables/', 'tools/', 'automations/', 'approvals/', 'workspace/'] as $mount) {
            $this->assertStringContainsString($mount, $root);
        }
        $this->assertStringContainsString(
            '/automations',
            $executor->execute($this->agent, 'find / -maxdepth 1 -name "*automation*" -type d')['stdout'],
        );
        $this->assertStringContainsString(
            '"type": "directory"',
            $executor->execute($this->agent, 'stat /automations')['stdout'],
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
        $this->assertStringContainsString('vfs@example.com', $executor->execute($this->agent, 'cat /tables/customers/rows.ndjson | jq ".data.email"')['stdout']);
        $this->assertStringContainsString('"type": "file"', $executor->execute($this->agent, 'stat /tables/customers/rows.ndjson')['stdout']);
        $this->assertStringContainsString('"backend_type": "data_table"', $executor->execute($this->agent, 'stat /tables/customers')['stdout']);
        $this->assertStringContainsString('grid--', $executor->execute($this->agent, 'ls /tables/customers/views')['stdout']);
        $this->assertStringContainsString('members.json', $executor->execute($this->agent, 'ls /workspace')['stdout']);
        $this->assertStringContainsString('human@example.com', $executor->execute($this->agent, 'cat /workspace/members.json')['stdout']);
        $this->assertStringContainsString('"role": "admin"', $executor->execute($this->agent, 'cat /workspace/members.json')['stdout']);
        $this->assertStringContainsString('"membership_id"', $executor->execute($this->agent, 'cat /workspace/members.json')['stdout']);
        $this->assertStringContainsString('/agents/by-id/'.$this->agent->id, $executor->execute($this->agent, 'cat /workspace/members.json')['stdout']);
        $this->assertStringContainsString('commands.json', $executor->execute($this->agent, 'ls /tools/vfs')['stdout']);
        $this->assertStringContainsString('rg', $executor->execute($this->agent, 'cat /tools/vfs/commands.json')['stdout']);
        $this->assertStringContainsString('members.json', $executor->execute($this->agent, 'ls /workspace/*.json')['stdout']);
        $this->assertStringContainsString('settings.json', $executor->execute($this->agent, 'ls /workspace/*.json')['stdout']);
        $this->assertStringContainsString('index.json', $executor->execute($this->agent, 'ls /tools/apps')['stdout']);
        $this->assertStringContainsString('README.md', $executor->execute($this->agent, 'ls /tools/apps')['stdout']);
        $this->assertStringContainsString('app tools', $executor->execute($this->agent, 'cat /tools/apps/README.md')['stdout']);
        $this->assertStringContainsString('"kind": "app_tools"', $executor->execute($this->agent, 'cat /tools/apps/index.json')['stdout']);
        $this->assertStringContainsString('"vfs_exec"', $executor->execute($this->agent, 'cat /tools/apps/index.json')['stdout']);
        $this->assertStringContainsString('index.json', $executor->execute($this->agent, 'ls /tools/integrations')['stdout']);
        $this->assertStringContainsString('README.md', $executor->execute($this->agent, 'ls /tools/integrations')['stdout']);
        $this->assertStringContainsString('"kind": "integration_tools"', $executor->execute($this->agent, 'cat /tools/integrations/index.json')['stdout']);
        $this->assertStringContainsString('README.md', $executor->execute($this->agent, 'ls /tools/mcp')['stdout']);
        $this->assertStringContainsString('MCP tools', $executor->execute($this->agent, 'cat /tools/mcp/README.md')['stdout']);
        $this->assertStringContainsString('"kind": "mcp_tools"', $executor->execute($this->agent, 'cat /tools/mcp/index.json')['stdout']);
        $this->assertFalse($executor->execute($this->agent, 'ls /tools/apps', '/', new VfsBudget(maxEntries: 2))['result']['truncated']);
        $this->assertTrue($executor->execute($this->agent, 'ls /tools/apps', '/', new VfsBudget(maxEntries: 1))['result']['truncated']);
        $this->assertFalse($executor->execute($this->agent, 'tree /tools/apps', '/', new VfsBudget(maxEntries: 2, maxDepth: 1))['result']['truncated']);
        $this->assertTrue($executor->execute($this->agent, 'tree /tools/apps', '/', new VfsBudget(maxEntries: 1, maxDepth: 1))['result']['truncated']);
        $this->assertStringContainsString($automationA->id, $executor->execute($this->agent, 'cat /automations/'.$automationA->id.'.json')['stdout']);
        $this->assertStringContainsString('daily-sync--', $executor->execute($this->agent, 'ls /automations')['stdout']);
        $this->assertStringContainsString('"truncated"', $executor->execute($this->agent, 'stat /docs')['stdout']);
        $this->assertStringContainsString('"count_is_exact": true', $executor->execute($this->agent, 'stat /tools/apps')['stdout']);
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
            'cat /automations/'.$automationA->id.'.json/junk',
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
}

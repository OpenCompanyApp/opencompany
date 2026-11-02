<?php

namespace Tests\Feature\Vfs;

use App\Agents\OpenCompanyAgent;
use App\Agents\Tools\Providers\VfsToolProvider;
use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Agents\Tools\ToolRegistry;
use App\Agents\Tools\Vfs\Support\VfsOffloadingTool;
use App\Agents\Tools\Vfs\Support\VfsToolEnvelope;
use App\Agents\Tools\Vfs\VfsExec;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Domain\Vfs\Spec\VfsGuide;
use App\Domain\Vfs\Spec\VfsOperationSpecRegistry;
use App\Domain\Vfs\Spec\VfsSpecDocumentation;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceMember;
use App\Services\ApprovalExecutionService;
use App\Services\CodeApiDocGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use Mockery;
use Tests\TestCase;

/**
 * Phase 2 (Stream B) contract tests for the agent-facing VFS tool surface:
 * the five direct tools, the compact single-error envelope, vfs_read paging,
 * vfs_edit atomic multi-edit, vfs_write modes, result offloading, the prompt
 * orientation block, tool-list stability, and the operation spec as the one
 * source for schemas, Code Mode aliases and generated docs (drift test).
 */
class VfsToolSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private OpenCompanyVfs $vfs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create(['name' => 'Atlas', 'status' => 'idle', 'brain' => 'codex:gpt-5.3-codex']);
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
        $this->vfs = app(OpenCompanyVfs::class);
    }

    // ── Tool surface and stability ──────────────────────────────────────

    // The model sees exactly five VFS tools, in spec order, and the same
    // list (names, descriptions, schemas) on every resolution.
    public function test_direct_tool_set_is_five_stable_tools(): void
    {
        $registry = app(ToolRegistry::class);
        $vfsSlugs = array_values(array_filter($registry->getToolSlugsForAgent($this->agent), fn (string $slug): bool => str_starts_with($slug, 'vfs_')));
        $this->assertSame(['vfs_exec', 'vfs_read', 'vfs_edit', 'vfs_write', 'vfs_patch'], $vfsSlugs);

        // Two resolutions (two runs with the same permissions) are identical
        // byte for byte, so the tool prefix stays prompt-cache friendly.
        $this->assertSame($this->serializedTools(), $this->serializedTools());
    }

    // Borrowed permissions: vfs_read follows vfs_exec and vfs_edit follows
    // vfs_patch; neither gets its own capabilities-UI toggle.
    public function test_read_and_edit_borrow_exec_and_patch_permissions(): void
    {
        $registry = app(ToolRegistry::class);
        $capabilities = collect($registry->getAllToolsMeta($this->agent))->pluck('id')->all();
        $this->assertContains('vfs_exec', $capabilities);
        $this->assertContains('vfs_patch', $capabilities);
        $this->assertNotContains('vfs_read', $capabilities);
        $this->assertNotContains('vfs_edit', $capabilities);

        $this->permission('tool', 'vfs_patch', 'deny');
        $slugs = app(ToolRegistry::class)->getToolSlugsForAgent($this->agent);
        $this->assertNotContains('vfs_patch', $slugs);
        $this->assertNotContains('vfs_edit', $slugs);
        $this->assertContains('vfs_read', $slugs);

        // The catalog line lists what this agent actually has.
        $this->assertStringContainsString('vfs: exec, read, write — ', app(ToolRegistry::class)->getAppCatalog($this->agent));
    }

    // ── Envelope ────────────────────────────────────────────────────────

    // vfs_exec used to return stdout three times, pretty-printed.
    public function test_exec_envelope_is_compact_with_stdout_once(): void
    {
        $this->vfs->write($this->agent, '/files/envelope/a.txt', "UNIQUE-MARKER-123\n", 'create');

        $raw = $this->tool('vfs_exec')->handle(new Request(['command' => 'cat /files/envelope/a.txt']));
        $payload = json_decode($raw, true);

        $this->assertTrue($payload['ok']);
        $this->assertSame(0, $payload['exit_code']);
        $this->assertSame(1, substr_count($raw, 'UNIQUE-MARKER-123'));
        $this->assertStringNotContainsString("\n    ", $raw, 'Results must not be pretty-printed.');
        $this->assertSame(['ok', 'exit_code', 'stdout', 'cwd'], array_keys($payload));
    }

    // A chained failure (`a && b`) used to hide its error in result.error;
    // it is now reported in the one top-level `error`.
    public function test_exec_chained_failure_reports_a_single_error_location(): void
    {
        $payload = json_decode($this->tool('vfs_exec')->handle(new Request(['command' => 'cat /files/missing.txt && echo after'])), true);

        $this->assertFalse($payload['ok']);
        $this->assertArrayNotHasKey('result', $payload);
        $this->assertSame('not_found', $payload['error']['code']);
        $this->assertStringNotContainsString('after', $payload['stdout']);

        $thrown = json_decode($this->tool('vfs_exec')->handle(new Request(['command' => ''])), true);
        $this->assertFalse($thrown['ok']);
        $this->assertSame('invalid_request', $thrown['error']['code']);
        $this->assertSame(2, $thrown['exit_code']);
    }

    // internal_error used to echo raw exception text (SQL, paths) to the model.
    public function test_internal_errors_never_leak_exception_text(): void
    {
        $executor = Mockery::mock(VfsCommandExecutor::class);
        $executor->shouldReceive('execute')->andThrow(new \RuntimeException('SQLSTATE[42P01] secret_tenant_table'));

        $raw = (new VfsExec($this->agent, $executor))->handle(new Request(['command' => 'ls /']));
        $payload = json_decode($raw, true);

        $this->assertSame('internal_error', $payload['error']['code']);
        $this->assertStringNotContainsString('secret_tenant_table', $raw);
        $this->assertTrue(Str::isUuid($payload['error']['context']['correlation_id']));
    }

    // Invalid UTF-8 must never turn a result into an empty string.
    public function test_envelope_encoding_never_returns_an_empty_string(): void
    {
        $encoded = VfsToolEnvelope::encode(VfsToolEnvelope::success(['content' => "ok \xB1\x31 bytes"]));

        $this->assertNotSame('', $encoded);
        $decoded = json_decode($encoded, true);
        $this->assertTrue($decoded['ok']);
        $this->assertArrayHasKey('encoding_note', $decoded);
    }

    // Until the executor exposes cursor (Stream A), a cursor must fail loudly
    // instead of silently returning page one again.
    public function test_exec_cursor_is_forwarded_or_refused_loudly(): void
    {
        $payload = json_decode($this->tool('vfs_exec')->handle(new Request(['command' => 'ls /docs', 'cursor' => 'abc'])), true);

        $acceptsCursor = collect((new \ReflectionMethod(VfsCommandExecutor::class, 'execute'))->getParameters())
            ->contains(fn (\ReflectionParameter $parameter): bool => $parameter->getName() === 'cursor');
        if (! $acceptsCursor) {
            $this->assertSame('unsupported', $payload['error']['code']);
            $this->assertSame('cursor', $payload['error']['context']['field']);
        } else {
            $this->assertArrayHasKey('ok', $payload);
        }
    }

    // ── vfs_read ────────────────────────────────────────────────────────

    public function test_read_numbers_lines_and_pages_with_offset(): void
    {
        $lines = array_map(fn (int $i): string => "line {$i}", range(1, 10));
        $this->vfs->write($this->agent, '/files/read/ten.txt', implode("\n", $lines)."\n", 'create');

        $first = $this->okResult('vfs_read', ['path' => '/files/read/ten.txt', 'limit' => 4]);
        $this->assertSame("     1\tline 1\n     2\tline 2\n     3\tline 3\n     4\tline 4\n", $first['content']);
        $this->assertSame(10, $first['total_lines']);
        $this->assertTrue($first['truncated']);
        $this->assertSame(5, $first['next_offset']);
        $this->assertStringContainsString('offset=5', $first['hint']);
        $this->assertSame($this->vfs->stat($this->agent, '/files/read/ten.txt')['version'], $first['version']);
        $this->assertContains('read', $first['capabilities']);

        $last = $this->okResult('vfs_read', ['path' => '/files/read/ten.txt', 'offset' => 9, 'limit' => 4]);
        $this->assertSame("     9\tline 9\n    10\tline 10\n", $last['content']);
        $this->assertFalse($last['truncated']);
        $this->assertArrayNotHasKey('next_offset', $last);

        $raw = $this->okResult('vfs_read', ['path' => '/files/read/ten.txt', 'raw' => true]);
        $this->assertSame(implode("\n", $lines)."\n", $raw['content']);
    }

    // maxBytes cuts on whole lines and points at the next one.
    public function test_read_max_bytes_stops_on_a_whole_line(): void
    {
        $this->vfs->write($this->agent, '/files/read/wide.txt', str_repeat("abcdefghij\n", 20), 'create');

        $page = $this->okResult('vfs_read', ['path' => '/files/read/wide.txt', 'maxBytes' => 40]);

        $this->assertSame(2, $page['returned_lines']);
        $this->assertSame(3, $page['next_offset']);
        $this->assertLessThanOrEqual(40, $page['bytes']);
    }

    public function test_read_refuses_directories_binary_content_and_misplaced_cursors(): void
    {
        $this->vfs->write($this->agent, '/files/read/plain.txt', 'text', 'create');
        $this->vfs->write($this->agent, '/files/read/blob.bin', "\x00\x01\x02binary", 'create');

        $directory = $this->envelope('vfs_read', ['path' => '/files/read']);
        $this->assertSame('not_readable', $directory['error']['code']);
        $this->assertStringContainsString('ls', $directory['error']['context']['hint']);

        $binary = $this->envelope('vfs_read', ['path' => '/files/read/blob.bin']);
        $this->assertSame('invalid_request', $binary['error']['code']);
        $this->assertStringContainsString('xxd', $binary['error']['context']['hint']);

        $cursor = $this->envelope('vfs_read', ['path' => '/files/read/plain.txt', 'cursor' => 'x']);
        $this->assertSame('cursor', $cursor['error']['context']['field']);
    }

    // The help link agents guess (/tools/vfs/commands/<c>.json) resolves.
    public function test_command_spec_alias_paths_resolve(): void
    {
        $page = $this->okResult('vfs_read', ['path' => '/tools/vfs/commands/grep.json', 'raw' => true]);
        $this->assertSame('grep', json_decode($page['content'], true)['name']);
        $this->assertSame('/tools/vfs/commands/grep/grep.json', $page['canonical_path']);

        $shell = json_decode($this->tool('vfs_exec')->handle(new Request(['command' => 'cat /tools/vfs/commands/grep.md'])), true);
        $this->assertTrue($shell['ok']);
        $this->assertStringContainsString('grep', $shell['stdout']);
    }

    // ── vfs_edit ────────────────────────────────────────────────────────

    public function test_edit_applies_several_edits_in_order(): void
    {
        $doc = $this->patchableDocument('Edit Me', "alpha\nbeta\ngamma\n".str_repeat("filler\n", 200)."omega\n");
        $read = $this->okResult('vfs_read', ['path' => "/docs/by-id/{$doc->id}", 'limit' => 1]);

        $result = $this->okResult('vfs_edit', [
            'path' => "/docs/by-id/{$doc->id}",
            'version' => $read['version'],
            'edits' => [
                ['old_text' => 'beta', 'new_text' => 'BETA'],
                ['old_text' => 'BETA', 'new_text' => 'BETA2'],
                ['old_text' => 'omega', 'new_text' => 'OMEGA'],
            ],
        ]);

        $this->assertSame('updated', $result['status']);
        $this->assertSame(3, $result['edits_applied']);
        $this->assertSame("alpha\nBETA2\ngamma\n".str_repeat("filler\n", 200)."OMEGA\n", $doc->fresh()->content);
        $this->assertNotSame($read['version'], $result['version']);
    }

    // All or nothing: a failing later edit leaves the file untouched.
    public function test_edit_is_atomic_and_reports_not_found_and_ambiguous_matches(): void
    {
        $doc = $this->patchableDocument('Atomic', "one\ntwo\ntwo\nthree\n");
        $path = "/docs/by-id/{$doc->id}";

        $missing = $this->envelope('vfs_edit', ['path' => $path, 'edits' => [
            ['old_text' => 'one', 'new_text' => 'ONE'],
            ['old_text' => 'four', 'new_text' => 'FOUR'],
        ]]);
        $this->assertSame('invalid_request', $missing['error']['code']);
        $this->assertSame(1, $missing['error']['context']['edit_index']);
        $this->assertSame("one\ntwo\ntwo\nthree\n", $doc->fresh()->content);

        $ambiguous = $this->envelope('vfs_edit', ['path' => $path, 'edits' => [['old_text' => 'two', 'new_text' => 'TWO']]]);
        $this->assertSame('ambiguous_match', $ambiguous['error']['code']);
        $this->assertSame([2, 3], $ambiguous['error']['context']['lines']);

        $all = $this->okResult('vfs_edit', ['path' => $path, 'edits' => [['old_text' => 'two', 'new_text' => 'TWO', 'replace_all' => true]]]);
        $this->assertSame(2, $all['replacements']);
        $this->assertSame("one\nTWO\nTWO\nthree\n", $doc->fresh()->content);
    }

    // dry_run previews a unified diff without writing or requesting approval,
    // even when vfs_patch is approval-gated.
    public function test_edit_dry_run_returns_a_diff_without_writing(): void
    {
        $doc = $this->patchableDocument('Dry', "keep\nold line\nkeep\n");
        $this->permission('tool', 'vfs_patch', 'allow', true);

        $preview = $this->okResult('vfs_edit', [
            'path' => "/docs/by-id/{$doc->id}",
            'dry_run' => true,
            'edits' => [['old_text' => 'old line', 'new_text' => 'new line']],
        ]);

        $this->assertSame('dry_run', $preview['status']);
        $this->assertStringContainsString("--- a/docs/by-id/{$doc->id}", $preview['diff']);
        $this->assertStringContainsString("-old line\n+new line\n", $preview['diff']);
        $this->assertStringContainsString('@@ -1,3 +1,3 @@', $preview['diff']);
        $this->assertSame("keep\nold line\nkeep\n", $doc->fresh()->content);
        $this->assertSame(0, ApprovalRequest::query()->count());
    }

    // Content-anchored staleness: a stale version still applies when each
    // old_text matches exactly once; otherwise conflict.
    public function test_edit_with_stale_version_applies_or_conflicts_by_anchor(): void
    {
        $doc = $this->patchableDocument('Stale', "header\nbody text\nfooter\n");
        $path = "/docs/by-id/{$doc->id}";
        $stale = $this->okResult('vfs_read', ['path' => $path])['version'];

        $this->vfs->patch($this->agent, $path, json_encode(['search' => 'footer', 'replace' => 'footer (human edit)'], JSON_THROW_ON_ERROR));

        $applied = $this->okResult('vfs_edit', ['path' => $path, 'version' => $stale, 'edits' => [['old_text' => 'body text', 'new_text' => 'agent text']]]);
        $this->assertTrue($applied['stale_base']);
        $this->assertSame("header\nagent text\nfooter (human edit)\n", $doc->fresh()->content);

        $conflict = $this->envelope('vfs_edit', ['path' => $path, 'version' => $stale, 'edits' => [['old_text' => 'body text', 'new_text' => 'again']]]);
        $this->assertSame('conflict', $conflict['error']['code']);
        $this->assertSame("header\nagent text\nfooter (human edit)\n", $doc->fresh()->content);
    }

    // vfs_edit is self-governed: not wrapped, and its policy approval replays
    // as the equivalent vfs_patch call.
    public function test_gated_edit_creates_one_replayable_patch_approval(): void
    {
        $doc = $this->patchableDocument('Gated', "status: draft\n");
        $this->permission('tool', 'vfs_patch', 'allow', true);

        $edit = collect(app(ToolRegistry::class)->getToolsForAgent($this->agent))
            ->first(fn (Tool $tool): bool => ToolNameResolver::resolve($tool) === 'vfs_edit');
        $this->assertNotInstanceOf(ApprovalWrappedTool::class, $edit);

        $response = json_decode($edit->handle(new Request([
            'path' => "/docs/by-id/{$doc->id}",
            'edits' => [['old_text' => 'draft', 'new_text' => 'final']],
        ])), true);
        // Approval-pending is the shared non-error shape (Stream C2).
        $this->assertSame('pending_approval', $response['status']);

        $approval = ApprovalRequest::query()->sole();
        $this->assertSame('vfs_patch', $approval->tool_execution_context['tool_slug']);
        $this->assertSame("status: draft\n", $doc->fresh()->content);

        app(ApprovalExecutionService::class)->resolve($approval, 'approved', $this->workspaceApprover());
        $this->assertSame("status: final\n", $doc->fresh()->content);
    }

    // Not being wrapped must never mean ungated: a strict-mode agent's edit
    // still stops at the composed approval.
    public function test_unwrapped_edit_still_needs_approval_in_strict_mode(): void
    {
        $doc = $this->patchableDocument('Strict', "mode: loose\n");
        $this->agent->update(['behavior_mode' => 'strict']);

        $response = $this->envelope('vfs_edit', [
            'path' => "/docs/by-id/{$doc->id}",
            'edits' => [['old_text' => 'loose', 'new_text' => 'strict']],
        ]);

        // Approval-pending is the shared non-error shape (Stream C2).
        $this->assertSame('pending_approval', $response['status']);
        $this->assertSame("mode: loose\n", $doc->fresh()->content);
    }

    public function test_edit_refuses_record_roots(): void
    {
        $response = $this->envelope('vfs_edit', ['path' => '/tasks/by-id/x', 'edits' => [['old_text' => 'a', 'new_text' => 'b']]]);

        $this->assertSame('unsupported', $response['error']['code']);
        $this->assertStringContainsString('vfs_patch', $response['error']['context']['hint']);
    }

    // ── vfs_write ───────────────────────────────────────────────────────

    public function test_write_overwrite_requires_version_and_append_concatenates(): void
    {
        $path = '/files/write/note.md';
        $created = $this->okResult('vfs_write', ['path' => $path, 'content' => "v1\n"]);

        $duplicate = $this->envelope('vfs_write', ['path' => $path, 'content' => 'again']);
        $this->assertFalse($duplicate['ok']);

        $blind = $this->envelope('vfs_write', ['path' => $path, 'content' => 'blind', 'mode' => 'overwrite']);
        $this->assertSame('invalid_request', $blind['error']['code']);
        $this->assertSame('version', $blind['error']['context']['field']);

        $version = $this->okResult('vfs_read', ['path' => $path])['version'];
        $this->okResult('vfs_write', ['path' => $path, 'content' => "v2\n", 'mode' => 'overwrite', 'version' => $version]);

        $stale = $this->envelope('vfs_write', ['path' => $path, 'content' => 'v3', 'mode' => 'overwrite', 'version' => $version]);
        $this->assertSame('conflict', $stale['error']['code']);

        $this->okResult('vfs_write', ['path' => $path, 'content' => "appended\n", 'mode' => 'append']);
        $this->assertSame("v2\nappended\n", $this->vfs->readContent($this->agent, $path));

        $this->okResult('vfs_write', ['path' => '/files/write/new-log.md', 'content' => "first\n", 'mode' => 'append']);
        $this->assertSame("first\n", $this->vfs->readContent($this->agent, '/files/write/new-log.md'));

        $badMode = $this->envelope('vfs_write', ['path' => $path, 'content' => 'x', 'mode' => 'replace']);
        $this->assertSame(['create', 'overwrite', 'append'], $badMode['error']['context']['allowed']);
        $this->assertNotEmpty($created['version'] ?? null);
    }

    // ── Offloading ──────────────────────────────────────────────────────

    // A model-facing result above the cap is saved through the governed
    // write path; the reply carries a preview and the path.
    public function test_large_results_are_offloaded_to_the_agent_results_folder(): void
    {
        config(['vfs.tool_result_max_bytes' => 2_000, 'vfs.tool_result_preview_bytes' => 500]);
        $body = implode('', array_map(fn (int $i): string => "row {$i} ".str_repeat('x', 40)."\n", range(1, 200)));
        $this->vfs->write($this->agent, '/files/big/report.txt', $body, 'create');

        $full = json_decode($this->tool('vfs_exec')->handle(new Request(['command' => 'cat /files/big/report.txt'])), true);
        $payload = json_decode($this->modelFacing('vfs_exec')->handle(new Request(['command' => 'cat /files/big/report.txt'])), true);

        $this->assertTrue($payload['truncated']);
        $this->assertStringStartsWith('/files/agents/atlas/.results/', $payload['offloaded_path']);
        $this->assertLessThanOrEqual(500, strlen($payload['stdout']));
        $this->assertSame($full['stdout'], $this->vfs->readContent($this->agent, $payload['offloaded_path']));
        $this->assertStringContainsString('vfs_read', $payload['hint']);
    }

    // Offloading never creates an approval on the agent's behalf.
    public function test_offloading_is_skipped_when_writes_need_approval(): void
    {
        config(['vfs.tool_result_max_bytes' => 2_000, 'vfs.tool_result_preview_bytes' => 500]);
        $this->vfs->write($this->agent, '/files/big/report.txt', str_repeat("line of text\n", 400), 'create');
        $this->permission('tool', 'vfs_write', 'allow', true);

        $payload = json_decode($this->modelFacing('vfs_exec')->handle(new Request(['command' => 'cat /files/big/report.txt'])), true);

        $this->assertTrue($payload['truncated']);
        $this->assertArrayNotHasKey('offloaded_path', $payload);
        $this->assertStringContainsString('cut', $payload['hint']);
        $this->assertSame(0, ApprovalRequest::query()->count());
    }

    public function test_vfs_read_is_never_offloaded(): void
    {
        $read = app(ToolRegistry::class)->instantiateToolBySlug('vfs_read', $this->agent);

        $this->assertSame($read, VfsOffloadingTool::wrapIfVfs($read, $this->agent));
        $this->assertInstanceOf(VfsOffloadingTool::class, VfsOffloadingTool::wrapIfVfs($this->tool('vfs_exec'), $this->agent));
    }

    // ── Prompt orientation ──────────────────────────────────────────────

    // The VFS block is constant text in the cacheable stable frame, present
    // once, and identical across runs.
    public function test_prompt_block_is_byte_stable_and_appears_once(): void
    {
        $this->assertSame(VfsGuide::promptBlock(), VfsGuide::promptBlock());
        $this->assertLessThanOrEqual(900, strlen(VfsGuide::promptBlock()), 'Keep the VFS block under ~200 tokens.');

        $first = OpenCompanyAgent::for($this->agent, 'channel-1')->promptFrame()['stable_prompt'];
        $second = OpenCompanyAgent::for($this->agent, 'channel-1')->promptFrame()['stable_prompt'];

        $this->assertSame(1, substr_count($first, VfsGuide::promptBlock()));
        $this->assertSame($first, $second);
    }

    public function test_prompt_block_is_absent_without_vfs_tools(): void
    {
        foreach (['vfs_exec', 'vfs_patch', 'vfs_write'] as $slug) {
            $this->permission('tool', $slug, 'deny');
        }

        $prompt = OpenCompanyAgent::for($this->agent, 'channel-1')->promptFrame()['stable_prompt'];

        $this->assertStringNotContainsString('## VFS', $prompt);
    }

    // ── Spec as single source ───────────────────────────────────────────

    public function test_tool_descriptions_schemas_and_meta_come_from_the_spec(): void
    {
        $factory = new JsonSchemaTypeFactory;
        $meta = (new VfsToolProvider)->tools();
        $this->assertSame(array_keys(VfsOperationSpecRegistry::all()), array_keys($meta));

        foreach (VfsOperationSpecRegistry::all() as $slug => $spec) {
            $tool = app(ToolRegistry::class)->instantiateToolBySlug($slug, $this->agent);
            $this->assertSame($spec->description, (string) $tool->description(), $slug);
            $this->assertSame($spec->description, $meta[$slug]['description'], $slug);
            $this->assertSame($spec->direct, empty($meta[$slug]['scriptOnly']), $slug);

            $schema = $factory->object($tool->schema($factory))->toArray();
            $this->assertSame(array_map(fn ($parameter): string => $parameter->name, $spec->parameters), array_keys($schema['properties']), $slug);
            foreach ($spec->parameters as $parameter) {
                foreach ($parameter->aliases as $alias) {
                    $this->assertArrayNotHasKey($alias, $schema['properties'], "{$slug} advertises alias {$alias}");
                }
            }
        }
    }

    public function test_code_mode_aliases_and_defaults_come_from_the_spec(): void
    {
        $functionMap = app(CodeApiDocGenerator::class)->buildFunctionMap($this->agent);
        $parameterMap = app(CodeApiDocGenerator::class)->buildParameterMap($this->agent);

        foreach (VfsOperationSpecRegistry::RUBY_ALIASES as $alias => $target) {
            $this->assertSame($functionMap[$target], $functionMap[$alias], $alias);
            $this->assertSame($parameterMap[$target], $parameterMap[$alias], $alias);
        }
        $this->assertSame(VfsOperationSpecRegistry::RUBY_ALIAS_DEFAULTS, CodeApiDocGenerator::ALIAS_ARGUMENT_DEFAULTS);
        $this->assertSame('vfs_edit', $functionMap['vfs.edit']);

        $codeJson = $this->okResult('vfs_read', ['path' => '/tools/vfs/code.json', 'raw' => true, 'maxBytes' => 200_000]);
        $this->assertEquals(json_decode(json_encode(VfsSpecDocumentation::codeCatalog()), true), json_decode($codeJson['content'], true));

        $openApi = $this->okResult('vfs_read', ['path' => '/tools/vfs/openapi.json', 'raw' => true, 'maxBytes' => 2_000_000]);
        $this->assertSame(
            array_map(fn ($spec): string => '/vfs/'.$spec->cliVerb, array_values(VfsOperationSpecRegistry::all())),
            array_keys(json_decode($openApi['content'], true)['paths']),
        );
    }

    // Drift gate: the generated reference sections must match the spec.
    // Fix with `php artisan vfs:spec --write-docs`.
    public function test_generated_reference_docs_match_the_spec(): void
    {
        foreach (VfsSpecDocumentation::DOC_FILES as $file => $audience) {
            $current = (string) file_get_contents(base_path($file));
            $this->assertStringContainsString(VfsSpecDocumentation::GENERATED_BEGIN, $current, $file);
            $this->assertSame(VfsSpecDocumentation::withGeneratedSection($current, $audience), $current, "{$file} is stale; run php artisan vfs:spec --write-docs");
        }
    }

    public function test_action_classes_resolve_per_call(): void
    {
        $this->assertSame('read', VfsOperationSpecRegistry::actionClassFor('vfs_read'));
        $this->assertSame('read', VfsOperationSpecRegistry::actionClassFor('vfs_exec', command: 'ls /docs | head'));
        $this->assertSame('delete', VfsOperationSpecRegistry::actionClassFor('vfs_exec', command: 'rm /files/a.txt'));
        $this->assertSame('reversible_edit', VfsOperationSpecRegistry::actionClassFor('vfs_exec', command: 'mkdir /files/x'));
        $this->assertSame('governance', VfsOperationSpecRegistry::actionClassFor('vfs_edit', '/agents/by-id/abc/identity/IDENTITY.md'));
        $this->assertSame('reversible_edit', VfsOperationSpecRegistry::actionClassFor('vfs_edit', '/docs/by-id/abc'));
        $this->assertSame('delete', VfsOperationSpecRegistry::actionClassFor('vfs_rm', '/files/a.txt'));
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /** @return list<array{0: string, 1: string, 2: string}> */
    private function serializedTools(): array
    {
        $factory = new JsonSchemaTypeFactory;

        return array_map(fn (Tool $tool): array => [
            ToolNameResolver::resolve($tool),
            (string) $tool->description(),
            (string) json_encode($factory->object($tool->schema($factory))->toArray()),
        ], app(ToolRegistry::class)->getToolsForAgent($this->agent));
    }

    private function tool(string $slug): Tool
    {
        $tool = app(ToolRegistry::class)->instantiateToolBySlug($slug, $this->agent);
        $this->assertInstanceOf(Tool::class, $tool);

        return $tool;
    }

    private function modelFacing(string $slug): Tool
    {
        return VfsOffloadingTool::wrapIfVfs($this->tool($slug), $this->agent);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function envelope(string $slug, array $arguments): array
    {
        return json_decode($this->tool($slug)->handle(new Request($arguments)), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function okResult(string $slug, array $arguments): array
    {
        $envelope = $this->envelope($slug, $arguments);
        $this->assertTrue($envelope['ok'], json_encode($envelope));

        return $envelope['result'];
    }

    private function patchableDocument(string $title, string $content): Document
    {
        $folder = Document::factory()->create(['title' => "{$title} Folder", 'is_folder' => true, 'author_id' => $this->agent->id]);
        $this->permission('folder', $folder->id);

        return Document::factory()->create([
            'title' => $title,
            'parent_id' => $folder->id,
            'content' => $content,
            'author_id' => $this->agent->id,
        ]);
    }

    private function permission(string $scopeType, string $scopeKey, string $permission = 'allow', bool $requiresApproval = false): void
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

<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\ToolRegistry;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Agents\Tools\Vfs\Support\VfsOffloadingTool;
use App\Agents\Tools\Vfs\Support\VfsUnifiedDiff;
use App\Agents\Tools\Vfs\VfsRead;
use App\Domain\Vfs\Core\VfsAwkProgram;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsLineDiff;
use App\Domain\Vfs\Core\VfsShellLexer;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Domain\Vfs\Spec\VfsOperationSpecRegistry;
use App\Models\AgentPermission;
use App\Models\User;
use App\Models\WorkspaceDisk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use Tests\TestCase;

/**
 * Regressions for the shell/tool-surface review of the VFS hardening branch
 * (/tmp/vfs-audit/review-shell-tools.md) and the eval pilot's ergonomics
 * bugs (vfs-agent-friendliness-audit-2026-11.md §9.4).
 *
 * Resource-limit tests run with a lowered PHP memory_limit and a 1 MiB VFS
 * content ceiling: each engine must fail with a VfsError BEFORE it
 * allocates, so a regression shows up as a PHP fatal here instead of in a
 * production worker.
 */
class VfsReviewFixesShellTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private OpenCompanyVfs $vfs;

    private VfsCommandExecutor $executor;

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
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
        $this->vfs = app(OpenCompanyVfs::class);
        $this->executor = app(VfsCommandExecutor::class);
    }

    // ── classifyEffect sees writes through command-running wrappers ─────

    // P1: `xargs sed -i` mutated a file while Code Mode classified the call
    // as a read and skipped its write receipt. xargs (nested to any depth)
    // is the only command that runs another command; env/command/find -exec
    // are refused, so those stay reads or fail preflight.
    public function test_classify_effect_sees_sed_in_place_through_xargs(): void
    {
        $this->file('/files/probe.txt', "old\n");

        $command = "echo /files/probe.txt | xargs sed -i 's/old/new/'";
        $this->assertSame('write', $this->executor->classifyEffect($command));
        $this->sh($command);
        $this->assertSame("new\n", $this->vfs->readContent($this->agent, '/files/probe.txt'));

        $this->assertSame('write', $this->executor->classifyEffect("echo /files/x | xargs -n1 -I{} sed -i 's/a/b/' {}"));
        $this->assertSame('write', $this->executor->classifyEffect("echo /files/x | xargs -r xargs sed --in-place 's/a/b/'"));
        $this->assertSame('write', $this->executor->classifyEffect('echo /files/x | xargs -0 -- rm'));
        // A read through xargs stays a read; the word "sed" as data does not
        // make it a write.
        $this->assertSame('read', $this->executor->classifyEffect('echo /files/x | xargs grep -l sed'));
        $this->assertSame('read', $this->executor->classifyEffect("echo /files/x | xargs sed 's/a/b/'"));
        // An xargs option the shell does not accept is classified as a write.
        $this->assertSame('write', $this->executor->classifyEffect('echo x | xargs --bogus cat'));
    }

    // ── tar validates headers and body boundaries ───────────────────────

    // P1: a truncated archive extracted a silently shortened file, and a
    // text file listed as an empty archive with exit 0.
    public function test_tar_rejects_truncated_damaged_and_non_tar_input(): void
    {
        $header = (new \ReflectionMethod($this->executor, 'tarHeader'))->invoke($this->executor, 'lost.txt', 1000);
        $this->file('/files/truncated.tar', $header.'abc');
        $error = $this->shErr('tar -x -f /files/truncated.tar -C /files/out');
        $this->assertSame('invalid_request', $error->errorCode);
        $this->assertStringContainsString('truncated', $error->getMessage());
        $this->assertFalse($this->exists('/files/out/lost.txt'));

        $this->file('/files/not.tar', 'this is not a tar');
        $this->assertStringContainsString('not a tar archive', $this->shErr('tar -t -f /files/not.tar')->getMessage());

        // A full block of text has no valid header checksum.
        $this->file('/files/text.tar', str_repeat('plain text ', 100));
        $this->assertStringContainsString('not a tar archive', $this->shErr('tar -t -f /files/text.tar')->getMessage());

        // One flipped header byte breaks the checksum.
        $good = $header.str_repeat('x', 1000).str_repeat("\0", 24).str_repeat("\0", 1024);
        $this->file('/files/good.tar', $good);
        $this->assertSame("lost.txt\n", $this->sh('tar -t -f /files/good.tar')['stdout']);
        $damaged = $good;
        $damaged[2] = 'Z';
        $this->file('/files/damaged.tar', $damaged);
        $this->assertSame('invalid_request', $this->shErr('tar -t -f /files/damaged.tar')->errorCode);

        // Round trip through the executor's own writer still works.
        $this->file('/files/src/a.txt', "alpha\n");
        $this->sh('tar -c -f /files/round.tar -C /files/src a.txt');
        $this->sh('tar -x -f /files/round.tar -C /files/round');
        $this->assertSame("alpha\n", $this->vfs->readContent($this->agent, '/files/round/a.txt'));
    }

    // ── printf ──────────────────────────────────────────────────────────

    // P1: widths and precisions were allocated before any output cap.
    public function test_printf_width_and_precision_are_capped_before_allocation(): void
    {
        $this->withMemoryHeadroom(function (): void {
            $this->assertSame('invalid_request', $this->shErr("printf '%100000000s' x")->errorCode);
            $this->assertSame('invalid_request', $this->shErr("printf '%.100000000f' 1")->errorCode);
            $this->assertSame('invalid_request', $this->shErr("printf '%*s' 100000000 x")->errorCode);
            $this->assertSame('invalid_request', $this->shErr("awk 'BEGIN { printf \"%100000000s\", \"x\" }'")->errorCode);
            $this->assertSame('invalid_request', $this->shErr("awk 'BEGIN { s = sprintf(\"%100000000d\", 1) }'")->errorCode);
        });
        $this->assertSame('   x', $this->sh("printf '%4s' x")['stdout']);
    }

    // P1: `\c` in a %b argument must end the whole printf (bash), not only
    // its own conversion; echo -e's \c also drops the trailing newline.
    public function test_printf_percent_b_backslash_c_stops_all_output(): void
    {
        $this->assertSame('a', $this->sh("printf '%b suffix' 'a\\cb'")['stdout']);
        $this->assertSame('x-y', $this->sh("printf '%s-%b-%s\\n' x 'y\\cz' w")['stdout']);
        // Further format passes stop too.
        $this->assertSame("a\nb", $this->sh("printf '%b\\n' a 'b\\c' c")['stdout']);
        $this->assertSame('a', $this->sh("echo -e 'a\\cb'")['stdout']);
        $this->sh("printf '%b tail' 'kept\\cdropped' > /files/stop.txt");
        $this->assertSame('kept', $this->vfs->readContent($this->agent, '/files/stop.txt'));
    }

    // P2: %q printed its argument unquoted, so reusing it as a command
    // operand split one path into two.
    public function test_printf_percent_q_quotes_for_the_vfs_shell(): void
    {
        $this->assertSame('a\\ b', $this->sh("printf '%q' 'a b'")['stdout']);
        $this->assertSame("''", $this->sh("printf '%q' ''")['stdout']);
        $this->assertSame('plain/path-1.txt', $this->sh("printf '%q' plain/path-1.txt")['stdout']);

        foreach (['a b', "it's here", 'x;y|z&$w', "line1\nline2", "tab\there", '*.md', ''] as $value) {
            $quoted = $this->sh('printf %q '.escapeshellarg($value))['stdout'];
            $words = VfsShellLexer::parse('cat '.$quoted)[0]['pipeline'][0]->words;
            $this->assertSame(['cat', $value], $words, 'round trip of '.json_encode($value));
        }
    }

    // ── sed, jq, awk and diff are memory-bounded ────────────────────────

    // P1: 28 x s/a/aa/g doubles the pattern space far below MAX_STEPS.
    public function test_sed_bounds_pattern_hold_and_output_growth(): void
    {
        $this->withMemoryHeadroom(function (): void {
            $doubling = implode(';', array_fill(0, 40, 's/a/aa/g'));
            $this->assertStringContainsString('pattern space', $this->shErr("printf 'a\\n' | sed '{$doubling}'")->getMessage());
            $holdLoop = implode(';', array_fill(0, 40, 'h;G'));
            $this->assertSame('invalid_request', $this->shErr("printf 'abcdef\\n' | sed '{$holdLoop}'")->errorCode);
            $wide = str_repeat('&', 2000);
            $this->assertSame('invalid_request', $this->shErr("printf '%s\\n' ".str_repeat('a', 2000)." | sed 's/.*/{$wide}/'")->errorCode);
        });
        $this->assertSame("aaaa\n", $this->sh("printf 'a\\n' | sed 's/a/aa/g;s/a/aa/g'")['stdout']);
    }

    // P1: range() materialized before MAX_OUTPUTS was checked; a doubling
    // string or a cartesian product had no bound at all.
    public function test_jq_bounds_ranges_lists_and_strings_before_allocation(): void
    {
        $this->withMemoryHeadroom(function (): void {
            $this->assertStringContainsString('range', $this->shErr("jq -n 'range(100000000)'")->getMessage());
            $this->assertStringContainsString('range', $this->shErr("jq -n '[range(100000000)]'")->getMessage());
            $this->assertSame('invalid_request', $this->shErr("jq -n 'range(2000) | range(2000)'")->errorCode);
            $doubling = '"abcdefgh"'.str_repeat(' | (. + .)', 30);
            $this->assertSame('invalid_request', $this->shErr("jq -n '{$doubling}'")->errorCode);
            $shared = '[range(1000)]'.str_repeat(' | [., ., ., ., ., ., ., ., ., .]', 6);
            $this->assertSame('invalid_request', $this->shErr("jq -n -c '{$shared}'")->errorCode);
            $this->assertSame('invalid_request', $this->shErr("jq -n '{$shared} | flatten(10) | length'")->errorCode);
            $this->assertSame('invalid_request', $this->shErr("jq -n '\"aaaa\"".str_repeat(' | gsub("a"; "aaaa")', 12)."'")->errorCode);
        });
        $this->assertSame("[0,1,2]\n", $this->sh("jq -n -c '[range(3)]'")['stdout']);
    }

    // P1: NF=100000000 or $100000000 padded the field list without a check;
    // `s = s s` doubled a string past any limit within a few steps.
    public function test_awk_bounds_fields_and_strings_before_allocation(): void
    {
        $this->withMemoryHeadroom(function (): void {
            $this->assertStringContainsString((string) VfsAwkProgram::MAX_FIELDS, $this->shErr("awk 'BEGIN { NF = 100000000; print NF }'")->getMessage());
            $this->assertSame('invalid_request', $this->shErr("awk 'BEGIN { \$100000000 = \"x\" }'")->errorCode);
            $this->assertSame('invalid_request', $this->shErr("awk 'BEGIN { s = \"ab\"; for (i = 0; i < 60; i++) s = s s; print length(s) }'")->errorCode);
            $this->assertSame('invalid_request', $this->shErr("awk 'BEGIN { s = \"aaaa\"; for (i = 0; i < 40; i++) gsub(/a/, \"&&\", s) }'")->errorCode);
        });
        $this->assertSame("a b  d\n", $this->sh("echo 'a b' | awk '{ \$4 = \"d\"; print }'")['stdout']);
    }

    // P1: 2000 `a` lines vs 2000 `b` lines (8 KB) built a quadratic Myers
    // trace that killed a 64 MiB worker. Lines unique to one side are now
    // set aside, and a genuinely expensive diff fails with a clear error.
    public function test_diff_is_memory_bounded(): void
    {
        $this->withMemoryHeadroom(function (): void {
            $diff = new VfsLineDiff(str_repeat("a\n", 2000), str_repeat("b\n", 2000));
            $normal = $diff->normal();
            $this->assertStringStartsWith("1,2000c1,2000\n", $normal);
            $this->assertSame(4001, substr_count($normal, "\n") - 1);

            $left = implode("\n", range(1, 3000))."\n";
            $right = implode("\n", array_reverse(range(1, 3000)))."\n";
            try {
                new VfsLineDiff($left, $right);
                $this->fail('A diff past the trace budget must fail.');
            } catch (VfsError $e) {
                $this->assertSame('invalid_request', $e->errorCode);
                $this->assertStringContainsString('inputs too different to diff within budget', $e->getMessage());
            }
        });

        // Minimality and GNU order (deletions before insertions) are kept.
        $this->assertSame("2c2\n< b\n---\n> B\n", (new VfsLineDiff("a\nb\nc\n", "a\nB\nc\n"))->normal());
        $this->assertSame("1a2\n> x\n3d3\n< c\n", (new VfsLineDiff("a\nb\nc\n", "a\nx\nb\n"))->normal());
    }

    // ── glob expansion never drops matches silently ─────────────────────

    // P1: the literal-tail check considered only the first 200 candidates.
    public function test_glob_literal_tail_keeps_every_match_and_reports_budget_cuts(): void
    {
        for ($i = 1; $i <= 205; $i++) {
            $this->file(sprintf('/files/g/d%03d/notes.md', $i), "n{$i}\n");
        }

        $this->assertSame('205', trim($this->sh('cat /files/g/*/notes.md | wc -l')['stdout']));

        // With a smaller budget the cut is reported, and a mutation refuses
        // to act on the partial set.
        (new \ReflectionProperty($this->executor, 'globStatBudget'))->setValue($this->executor, 50);
        $listed = $this->sh('cat /files/g/*/notes.md');
        $this->assertTrue($listed['truncated']);
        $this->assertStringContainsString('checked 50 of 205', $listed['stderr']);
        $refused = $this->shErr('rm /files/g/*/notes.md');
        $this->assertSame('invalid_request', $refused->errorCode);
        $this->assertTrue($this->exists('/files/g/d205/notes.md'));
        $this->assertTrue($this->exists('/files/g/d001/notes.md'));
    }

    // ── vfs_read paging contract ────────────────────────────────────────

    // P1: a byte-cut line was reported as a complete read, and continuation
    // skipped the rest of the cut line.
    public function test_vfs_read_cut_line_is_truncated_and_continuation_reads_every_byte(): void
    {
        $this->file('/files/probe.txt', "new\n");
        $page = $this->read(['path' => '/files/probe.txt', 'maxBytes' => 2, 'raw' => true]);
        $this->assertSame('ne', $page['content']);
        $this->assertTrue($page['truncated']);
        $this->assertTrue($page['line_cut']);
        $this->assertSame(1, $page['next_offset']);
        $this->assertSame(2, $page['next_line_byte_offset']);

        // A paging loop that follows the continuation fields reassembles
        // the file exactly, multi-byte characters included.
        $content = "short\n".str_repeat('é', 30)."x\nlast line\n";
        $this->file('/files/long.txt', $content);
        $assembled = '';
        $arguments = ['path' => '/files/long.txt', 'maxBytes' => 7, 'raw' => true];
        for ($calls = 0; $calls < 100; $calls++) {
            $page = $this->read($arguments);
            $this->assertLessThanOrEqual(7, strlen($page['content']));
            $assembled .= $page['content'];
            if (! $page['truncated']) {
                break;
            }
            $arguments['offset'] = $page['next_offset'];
            $arguments['line_byte_offset'] = $page['next_line_byte_offset'] ?? 0;
        }
        $this->assertSame($content, $assembled);

        // Numbered pages of a cut line keep the line number.
        $numbered = $this->read(['path' => '/files/long.txt', 'offset' => 2, 'maxBytes' => 20]);
        $this->assertStringStartsWith("     2\t", $numbered['content']);
        $this->assertTrue($numbered['line_cut']);

        $this->assertSame('invalid_request', $this->read(['path' => '/files/probe.txt', 'line_byte_offset' => 9], false)['error']['code']);
    }

    // P1: a dry-run diff of an edit that only changes the final newline
    // was two headers and no hunk.
    public function test_unified_dry_run_diff_renders_eof_newline_changes(): void
    {
        $this->assertSame(
            "--- a/files/a\n+++ b/files/a\n@@ -1,1 +1,1 @@\n-x\n\\ No newline at end of file\n+x\n",
            VfsUnifiedDiff::render('/files/a', 'x', "x\n"),
        );
        $this->assertSame(
            "--- a/files/a\n+++ b/files/a\n@@ -1,2 +1,2 @@\n a\n-b\n+b\n\\ No newline at end of file\n",
            VfsUnifiedDiff::render('/files/a', "a\nb\n", "a\nb"),
        );
    }

    // ── lexer and redirections ──────────────────────────────────────────

    // P2: `EOF   ` (trailing blanks) ended a heredoc; only an exact
    // delimiter line may, after the `<<-` leading-tab strip.
    public function test_heredoc_delimiter_must_match_exactly(): void
    {
        $this->assertStringContainsString('not terminated', $this->shErr("cat <<EOF\nfirst\nEOF   \n")->getMessage());
        $this->assertSame("first\nEOF  \nsecond\n", $this->sh("cat <<EOF\nfirst\nEOF  \nsecond\nEOF\n")['stdout']);
        $this->assertSame("x\n", $this->sh("cat <<-EOF\n\tx\n\tEOF\n")['stdout']);
    }

    // P2: a superseded redirection target was never opened, so `> a > b`
    // did not create/truncate `a`, and an unwritable earlier target could
    // not stop the command.
    public function test_superseded_redirections_are_opened_in_order(): void
    {
        $this->file('/files/first.txt', "old content\n");
        $this->sh('printf x > /files/first.txt > /files/second.txt');
        $this->assertSame('', $this->vfs->readContent($this->agent, '/files/first.txt'));
        $this->assertSame('x', $this->vfs->readContent($this->agent, '/files/second.txt'));

        $this->sh('printf y >> /files/created.txt > /files/third.txt');
        $this->assertSame('', $this->vfs->readContent($this->agent, '/files/created.txt'));
        $this->file('/files/kept.txt', "keep\n");
        $this->sh('printf z >> /files/kept.txt > /files/fourth.txt');
        $this->assertSame("keep\n", $this->vfs->readContent($this->agent, '/files/kept.txt'));

        // An earlier target the agent cannot write fails the line before the
        // later target is written.
        $this->shErr('printf x > /tools/denied.txt > /files/after.txt');
        $this->assertFalse($this->exists('/files/after.txt'));
    }

    // ── eval pilot ergonomics ───────────────────────────────────────────

    // The model saw `VfsExec` while every description, hint and doc says
    // `vfs_exec`; the model-facing name is now the slug, through every
    // wrapper, and timeline metadata still resolves it.
    public function test_model_facing_tool_names_are_the_slugs(): void
    {
        $names = [];
        foreach (app(ToolRegistry::class)->getToolsForAgent($this->agent) as $tool) {
            $wrapped = VfsOffloadingTool::wrapIfVfs($tool, $this->agent);
            $name = ToolNameResolver::resolve($wrapped);
            if (str_starts_with(strtolower($name), 'vfs')) {
                $names[] = $name;
            }
        }
        $this->assertSame(['vfs_exec', 'vfs_read', 'vfs_edit', 'vfs_write', 'vfs_patch'], $names);

        $read = new VfsRead($this->agent, $this->vfs);
        $this->assertInstanceOf(ReturnsVfsEnvelope::class, $read);
        $this->assertSame('vfs_read', ToolNameResolver::resolve($read));
        $spec = VfsOperationSpecRegistry::get('vfs_exec');
        $this->assertSame(['icon' => $spec->icon, 'name' => $spec->title], app(ToolRegistry::class)->getToolMetaByClassName('vfs_exec'));
    }

    // `ls -la` printed `dr- - -`: the mode column is now a well-formed
    // 10-character unix mode string.
    public function test_ls_long_mode_column_is_well_formed(): void
    {
        $this->file('/files/m/a.txt', "a\n");
        $this->sh('mkdir -p /files/m/sub');
        $lines = array_filter(explode("\n", $this->sh('ls -la /files/m')['stdout']));
        $this->assertNotEmpty($lines);
        foreach ($lines as $line) {
            $this->assertMatchesRegularExpression('/^[d-][r-][w-][x-]------ /', $line);
        }
        $this->assertMatchesRegularExpression('/^-rw------- .* a\.txt$/m', $this->sh('ls -l /files/m')['stdout']);
        $this->assertMatchesRegularExpression('/^drwx------ .* sub\/$/m', $this->sh('ls -l /files/m')['stdout']);
    }

    // Models write `rg -r`/`-rn`/`-ril` meaning "recursive"; in ripgrep -r is
    // --replace. The rule (rg command notes): -r/-R never mean replace; they
    // are dropped with a stderr note unless a standalone -r is followed by
    // two operands, which is ambiguous and refused with the fix.
    public function test_rg_recursive_flag_rule(): void
    {
        $this->file('/files/r/one.md', "Needle here\n");
        $this->file('/files/r/two.md', "nothing\n");

        $rn = $this->sh('rg -rn Needle /files/r');
        $this->assertSame("/files/r/one.md:1:Needle here\n", $rn['stdout']);
        $this->assertStringContainsString('rg always recurses', $rn['stderr']);

        $this->assertSame("/files/r/one.md\n", $this->sh('rg -ril needle /files/r')['stdout']);
        $this->assertSame("/files/r/one.md:1:Needle here\n", $this->sh('rg -R -n Needle /files/r')['stdout']);
        $this->assertStringContainsString('Needle here', $this->executor->execute($this->agent, 'rg -r -n Needle', '/files/r')['stdout']);

        $ambiguous = $this->shErr('rg -r Needle /files/r');
        $this->assertSame('invalid_request', $ambiguous->errorCode);
        $this->assertStringContainsString('drop -r', $ambiguous->getMessage());
        $this->assertSame('invalid_request', $this->shErr('rg -nr Needle /files/r')->errorCode);
        // -e names the pattern, so the operands are paths: not ambiguous.
        $this->assertSame("/files/r/one.md:Needle here\n", $this->sh('rg -r -e Needle /files/r /files/r/two.md')['stdout']);

        $this->assertSame('unsupported', $this->shErr('rg --replace=x Needle /files/r')->errorCode);
        // Quoted -r is data, never a flag.
        $this->assertSame(1, $this->sh("rg -- '-r' /files/r")['exit_code']);
    }

    // ── helpers ─────────────────────────────────────────────────────────

    /**
     * Run $callback with a 1 MiB VFS content ceiling and only ~64 MiB of
     * PHP memory above current usage: an engine that allocates before it
     * checks its cap dies with a fatal here instead of passing.
     */
    private function withMemoryHeadroom(callable $callback): void
    {
        $previousLimit = ini_get('memory_limit');
        $previousCeiling = config('vfs.max_content_bytes');
        config(['vfs.max_content_bytes' => 1024 * 1024]);
        gc_collect_cycles();
        ini_set('memory_limit', (string) (memory_get_usage(true) + 64 * 1024 * 1024));
        try {
            $callback();
        } finally {
            ini_set('memory_limit', (string) $previousLimit);
            config(['vfs.max_content_bytes' => $previousCeiling]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sh(string $command, ?VfsBudget $budget = null): array
    {
        return $this->executor->execute($this->agent, $command, '/', $budget);
    }

    private function shErr(string $command): VfsError
    {
        try {
            $result = $this->executor->execute($this->agent, $command);
        } catch (VfsError $e) {
            return $e;
        }
        $this->fail("Expected `{$command}` to fail, got: ".json_encode($result));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function read(array $arguments, bool $expectOk = true): array
    {
        $envelope = json_decode((new VfsRead($this->agent, $this->vfs))->handle(new Request($arguments)), true);
        $this->assertSame($expectOk, $envelope['ok'], json_encode($envelope));

        return $expectOk ? $envelope['result'] : $envelope;
    }

    private function file(string $path, string $content): void
    {
        $this->vfs->write($this->agent, $path, $content, 'create');
    }

    private function exists(string $path): bool
    {
        try {
            $this->vfs->stat($this->agent, $path);

            return true;
        } catch (VfsError) {
            return false;
        }
    }
}

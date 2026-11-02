<?php

namespace Tests\Feature\Vfs;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Domain\Vfs\Core\VfsCommandSpecRegistry;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 2 Stream A: the VFS shell is truthful.
 *
 * Every test protects a behavior an agent relies on when vfs_exec is its only
 * workspace: exit statuses that drive && / ||, multi-line scripts, commands
 * that act on every operand, filters that read their files, real sed/awk/
 * diff/jq, heredocs, governed in-place edits, visible truncation with
 * cursors, and errors that never swallow earlier output or approvals.
 */
class VfsShellSemanticsTest extends TestCase
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
        $this->permission('file_folder', '*');
        $this->vfs = app(OpenCompanyVfs::class);
        $this->executor = app(VfsCommandExecutor::class);
    }

    // Item 1: no-match, differences and test failures are exit status 1
    // (not error envelopes); errors are 2; && / || / ; honor the status.
    public function test_exit_statuses_drive_conditionals(): void
    {
        $this->file('/files/s/a.txt', "alpha\nbeta\n");
        $this->file('/files/s/b.txt', "alpha\ngamma\n");

        $noMatch = $this->sh('grep zzz /files/s/a.txt');
        $this->assertTrue($noMatch['ok']);
        $this->assertSame(1, $noMatch['exit_code']);
        $this->assertSame('', $noMatch['stdout']);

        $this->assertSame("no\n", $this->sh('grep -q zzz /files/s/a.txt && echo yes || echo no')['stdout']);
        $this->assertSame("yes\n", $this->sh('grep -q beta /files/s/a.txt && echo yes || echo no')['stdout']);
        $this->assertSame('', $this->sh('grep -q beta /files/s/a.txt')['stdout']);
        $this->assertSame(1, $this->sh('diff /files/s/a.txt /files/s/b.txt')['exit_code']);
        $this->assertSame(0, $this->sh('diff /files/s/a.txt /files/s/a.txt')['exit_code']);
        $this->assertSame("changed\n", $this->sh('diff -q /files/s/a.txt /files/s/b.txt > /dev/null || echo changed')['stdout']);
        $this->assertSame(1, $this->sh('cmp -s /files/s/a.txt /files/s/b.txt')['exit_code']);
        $this->assertSame(1, $this->sh('test -f /files/s/missing.txt')['exit_code']);
        $this->assertSame("made\n", $this->sh('[ -e /files/s/missing.txt ] || echo made')['stdout']);
        $this->assertSame(0, $this->sh('[ -d /files/s ] && [ -s /files/s/a.txt ] && test 3 -gt 2')['exit_code']);
        $this->assertSame(1, $this->sh('false')['exit_code']);
        $this->assertSame(0, $this->sh('! false')['exit_code']);
        $this->assertSame("1\n", $this->sh('grep -q zzz /files/s/a.txt; echo $?')['stdout']);
        // The status is the last command's; a later success resets it.
        $this->assertSame(0, $this->sh('grep zzz /files/s/a.txt; true')['exit_code']);

        // A real error is status 2; when it ends the line it is the envelope.
        $error = $this->shErr('cat /files/s/missing.txt');
        $this->assertSame('not_found', $error->errorCode);
        $this->assertSame(2, $error->context['exit_code']);
        $continued = $this->sh('cat /files/s/missing.txt; echo after');
        $this->assertSame("after\n", $continued['stdout']);
        $this->assertStringContainsString('Path not found: /files/s/missing.txt', $continued['stderr']);
        $this->assertSame(0, $continued['exit_code']);
    }

    // Item 2: newline separates commands (it used to collapse a script into
    // one argv and silently drop the rest); multi-operand mutations act on
    // every operand; tee passes its input through.
    public function test_newlines_multi_operand_mutations_and_tee(): void
    {
        $script = "mkdir -p /files/m/one /files/m/two\n# a comment line\ntouch /files/m/one/a.txt /files/m/two/b.txt\n\necho done";
        $result = $this->sh($script);
        $this->assertStringEndsWith("done\n", $result['stdout']);
        $this->assertSame('file', $this->vfs->stat($this->agent, '/files/m/one/a.txt')['type']);
        $this->assertSame('file', $this->vfs->stat($this->agent, '/files/m/two/b.txt')['type']);
        $this->assertSame("one two\n", $this->sh("echo one \\\n two")['stdout']);

        // mkdir without -p: missing parent and existing paths are errors.
        $this->assertSame('not_found', $this->shErr('mkdir /files/m/nope/child')->errorCode);
        $this->assertStringContainsString('File exists', $this->shErr('mkdir /files/m/one')->getMessage());
        $this->sh('mkdir -p /files/m/one');

        $this->file('/files/m/c.txt', 'c');
        $this->file('/files/m/d.txt', 'd');
        $copied = $this->sh('cp /files/m/c.txt /files/m/d.txt /files/m/two/');
        $this->assertStringContainsString('copied /files/m/c.txt -> /files/m/two/c.txt', $copied['stdout']);
        $this->assertSame('d', $this->vfs->readContent($this->agent, '/files/m/two/d.txt'));
        $this->sh('mv /files/m/c.txt /files/m/d.txt /files/m/one');
        $this->assertSame('c', $this->vfs->readContent($this->agent, '/files/m/one/c.txt'));
        $this->assertSame('not_found', $this->shErr('cat /files/m/c.txt')->errorCode);
        $this->assertSame('invalid_request', $this->shErr('cp /files/m/one/c.txt /files/m/one/d.txt /files/m/one/c.txt')->errorCode);

        $this->sh('rm /files/m/one/c.txt /files/m/one/d.txt');
        $this->assertSame('not_found', $this->shErr('cat /files/m/one/d.txt')->errorCode);
        $this->assertStringContainsString('Is a directory', $this->shErr('rm /files/m/two')->getMessage());
        $this->sh('rm -r /files/m/two');
        $this->assertSame('not_found', $this->shErr('ls /files/m/two')->errorCode);

        $tee = $this->sh('printf "x\ny\n" | tee /files/m/t1.txt /files/m/t2.txt | wc -l');
        $this->assertSame("2\n", $tee['stdout']);
        $this->assertSame("x\ny\n", $this->vfs->readContent($this->agent, '/files/m/t2.txt'));
        $this->sh('echo z | tee -a /files/m/t1.txt > /dev/null');
        $this->assertSame("x\ny\nz\n", $this->vfs->readContent($this->agent, '/files/m/t1.txt'));
    }

    // Item 3: every text filter reads file operands (they used to print
    // nothing with exit 0 when given a file).
    public function test_every_text_filter_reads_file_operands(): void
    {
        $csv = "b,2,x\na,1,y\nb,2,x\nc,3,z\n";
        $this->file('/files/f/data.csv', $csv);
        foreach ([
            'cut -d, -f1',
            "awk -F, '{print \$2}'",
            "sed 's/,/;/g'",
            'uniq',
            'tr a-z A-Z',
            'column -t -s,',
            'fold -w 3',
            'fmt -w 20',
            'expand -t 2',
            'unexpand -a',
            'nl',
            'rev',
            'sort',
            'tac',
            'cat -n',
            'head -n 2',
            'tail -n 2',
            'wc -l',
            'paste -d: -',
            'grep -c ,',
        ] as $filter) {
            $fromFile = $this->sh("{$filter} /files/f/data.csv");
            $fromPipe = $this->sh("cat /files/f/data.csv | {$filter}");
            $this->assertNotSame('', trim($fromFile['stdout']), $filter);
            $expected = $fromPipe['stdout'];
            if ($filter === 'wc -l') {
                $this->assertSame("4 /files/f/data.csv\n", $fromFile['stdout']);

                continue;
            }
            if ($filter === 'paste -d: -') {
                continue;
            }
            $this->assertSame($expected, $fromFile['stdout'], $filter);
        }
        $this->assertSame("b\na\nb\nc\n", $this->sh('cut -d, -f1 /files/f/data.csv')['stdout']);
        $this->assertSame("a,1,y\nb,2,x\nc,3,z\n", $this->sh('sort -u /files/f/data.csv')['stdout']);
        $this->assertSame("c,3,z\nb,2,x\nb,2,x\na,1,y\n", $this->sh('sort -t, -k2,2nr /files/f/data.csv')['stdout']);
        // -u by key keeps the first line of each run of equal keys.
        $this->assertSame("b,2,x\na,1,y\nc,3,z\n", $this->sh('sort -t, -k3,3 -u /files/f/data.csv')['stdout']);
    }

    // Item 4: real sed (addresses, regex substitution, backrefs, flags,
    // -n, -E, several -e) and governed in-place edits.
    public function test_sed_language_and_governed_in_place_edits(): void
    {
        $this->file('/files/e/x.txt', "one 1\ntwo 2\nthree 3\nfour 4\n");
        $this->assertSame("two 2\nthree 3\n", $this->sh("sed -n '2,3p' /files/e/x.txt")['stdout']);
        $this->assertSame("four 4\n", $this->sh("sed -n '\$p' /files/e/x.txt")['stdout']);
        $this->assertSame("one 1\nfour 4\n", $this->sh("sed '/two/,/three/d' /files/e/x.txt")['stdout']);
        $this->assertSame("1-one\n2-two\n3-three\n4-four\n", $this->sh("sed -E 's/([a-z]+) ([0-9])/\\2-\\1/' /files/e/x.txt")['stdout']);
        $this->assertSame("1:one\n", $this->sh("sed -n 's/\\(.*\\) \\(.\\)/\\2:\\1/p;1q' /files/e/x.txt")['stdout']);
        $this->assertSame("[one] 1\n", $this->sh("sed -n -e 's/one/[&]/' -e '1p' /files/e/x.txt")['stdout']);
        $this->assertSame("ONE 1\n", $this->sh("echo 'One 1' | sed 's/one/ONE/I'")['stdout']);
        $this->assertSame("aXa\n", $this->sh("echo aaa | sed 's/a/X/2'")['stdout']);
        $this->assertSame("one 1\ntwo 2\n", $this->sh("sed 2q /files/e/x.txt")['stdout']);
        $this->assertSame('unsupported', $this->shErr("sed ':a;N;ba' /files/e/x.txt")->errorCode);

        // /files: the edit goes through the version-checked file write path.
        $before = $this->vfs->stat($this->agent, '/files/e/x.txt')['version'];
        $this->assertSame('', $this->sh("sed -i 's/two/TWO/' /files/e/x.txt")['stdout']);
        $this->assertSame("one 1\nTWO 2\nthree 3\nfour 4\n", $this->vfs->readContent($this->agent, '/files/e/x.txt'));
        $this->assertNotSame($before, $this->vfs->stat($this->agent, '/files/e/x.txt')['version']);
        $this->assertSame('unsupported', $this->shErr("sed -i.bak 's/a/b/' /files/e/x.txt")->errorCode);

        // /docs: the edit is a vfs_patch with a minimal unique anchor.
        $doc = $this->patchableDocument('Sed Doc', "intro\nstatus: draft\nbody\nstatus: draft\n");
        $this->sh("sed -i 's/draft/final/' /docs/by-id/{$doc->id}");
        $this->assertSame("intro\nstatus: final\nbody\nstatus: final\n", $doc->fresh()->content);

        // Structured record roots are refused with the right alternative.
        $refused = $this->shErr("sed -i 's/a/b/' /tasks/by-id/x.json");
        $this->assertSame('unsupported', $refused->errorCode);
        $this->assertStringContainsString('vfs_patch', (string) $refused->context['suggestion']);
    }

    // Item 4 (governance): sed -i on a doc needs vfs_patch; when it is
    // approval-gated the patch becomes an approval and nothing is written.
    public function test_sed_in_place_on_docs_requests_a_patch_approval(): void
    {
        $this->permission('tool', 'vfs_patch', requiresApproval: true);
        $doc = $this->patchableDocument('Gated', "a draft\n");

        $error = $this->shErr("echo before; sed -i 's/draft/final/' /docs/by-id/{$doc->id}; echo after");
        $this->assertSame('approval_required', $error->errorCode);
        $this->assertSame('pending_approval', $error->context['status']);
        $this->assertSame("before\n", $error->context['stdout']);
        $this->assertSame("a draft\n", $doc->fresh()->content);
        // The approval replays the governed vfs_patch, not the shell line.
        $approval = ApprovalRequest::query()->sole();
        $this->assertSame('vfs_patch', $approval->tool_execution_context['vfs_approval']['direct_tool_slug']);
    }

    // Item 5: awk beyond {print $N}: -F '\t', patterns, NR/NF/$NF, sums,
    // printf, variables; no system().
    public function test_awk_field_processing(): void
    {
        $this->file('/files/a/t.tsv', "name\tqty\tprice\nann\t2\t10\nbob\t5\t3\ncid\t1\t7\n");
        $this->assertSame("42\n", $this->sh("awk -F '\\t' 'NR>1 {s += \$2 * \$3} END {print s}' /files/a/t.tsv")['stdout']);
        $this->assertSame("bob\n", $this->sh("awk -F '\\t' 'NR > 1 && \$2 > 3 {print \$1}' /files/a/t.tsv")['stdout']);
        $this->assertSame("3 price\n", $this->sh("awk -F '\\t' 'NR==1 {print NF, \$NF}' /files/a/t.tsv")['stdout']);
        $this->assertSame("ann=20.00\n", $this->sh("awk -F '\\t' '/ann/ {printf \"%s=%.2f\\n\", \$1, \$2*\$3}' /files/a/t.tsv")['stdout']);
        $this->assertSame("3\n", $this->sh("awk -v min=1 -F '\\t' 'NR>1 && \$2 >= min {n++} END {print n}' /files/a/t.tsv")['stdout']);
        $this->assertSame('unsupported', $this->shErr("awk 'BEGIN {system(\"ls\")}'")->errorCode);
    }

    // Item 6: diff is LCS-based (one insertion is one change, not every
    // later line), -u is a valid unified diff, -r walks directories.
    public function test_diff_is_lcs_based_and_unified(): void
    {
        $this->file('/files/d/old.md', "a\nb\nc\nd\ne\nf\ng\nh\n");
        $this->file('/files/d/new.md', "a\nINSERTED\nb\nc\nd\ne\nf\ng\nH\n");
        $this->assertSame("1a2\n> INSERTED\n8c9\n< h\n---\n> H\n", $this->sh('diff /files/d/old.md /files/d/new.md')['stdout']);
        $this->assertSame(
            "--- a/files/d/old.md\n+++ b/files/d/new.md\n@@ -1,2 +1,3 @@\n a\n+INSERTED\n b\n@@ -7,2 +8,2 @@\n g\n-h\n+H\n",
            $this->sh('diff -U 1 /files/d/old.md /files/d/new.md')['stdout'],
        );
        $this->assertStringStartsWith("--- a/files/d/old.md\n+++ b/files/d/new.md\n@@ -1,8 +1,9 @@\n", $this->sh('diff -u /files/d/old.md /files/d/new.md')['stdout']);
        $this->file('/files/d/spaced.md', "a  b\n");
        $this->file('/files/d/plain.md', "a b\n");
        $this->assertSame(0, $this->sh('diff -b /files/d/spaced.md /files/d/plain.md')['exit_code']);
        $this->assertSame(0, $this->sh('diff -w /files/d/spaced.md /files/d/plain.md')['exit_code']);

        $this->file('/files/d1/x.txt', "same\n");
        $this->file('/files/d1/y.txt', "one\n");
        $this->file('/files/d2/x.txt', "same\n");
        $this->file('/files/d2/y.txt', "two\n");
        $this->file('/files/d2/z.txt', "new\n");
        $recursive = $this->sh('diff -r /files/d1 /files/d2');
        $this->assertSame(1, $recursive['exit_code']);
        $this->assertStringContainsString("diff /files/d1/y.txt /files/d2/y.txt\n1c1\n< one\n---\n> two\n", $recursive['stdout']);
        $this->assertStringContainsString('Only in /files/d2: z.txt', $recursive['stdout']);
    }

    // Item 7: heredocs and here-strings make write-in-one-command possible.
    public function test_heredocs_and_here_strings(): void
    {
        $this->sh("cat <<'EOF' > /files/h/note.md\n# Title\n\nUses \$HOME literally\nEOF");
        $this->assertSame("# Title\n\nUses \$HOME literally\n", $this->vfs->readContent($this->agent, '/files/h/note.md'));
        $this->assertSame("2\n", $this->sh("cat <<EOF | grep -c x\nx1\ny\nx2\nEOF")['stdout']);
        $this->assertSame("tab\n", $this->sh("cat <<-END\n\ttab\n\tEND")['stdout']);
        $this->assertSame("HELLO\n", $this->sh('tr a-z A-Z <<< hello')['stdout']);
        $this->assertSame('invalid_request', $this->shErr("cat <<EOF\nnever closed")->errorCode);
    }

    // Item 8: jq comparisons, logic, construction, aggregation, raw output.
    public function test_jq_expressions(): void
    {
        $this->file('/files/j/items.json', json_encode(['items' => [
            ['name' => 'a', 'n' => 3, 'tag' => 'x'],
            ['name' => 'b', 'n' => 1, 'tag' => 'y'],
            ['name' => 'c', 'n' => 2, 'tag' => 'x'],
        ]], JSON_THROW_ON_ERROR));
        $jq = fn (string $program, string $flags = '-c'): string => $this->sh("jq {$flags} '{$program}' /files/j/items.json")['stdout'];
        $this->assertSame("[\"a\",\"c\"]\n", $jq('[.items[] | select(.n >= 2 and .tag != "y") | .name]'));
        $this->assertSame("[\"b\"]\n", $jq('[.items[] | select(.n < 2 or (.tag | not)) | .name]'));
        $this->assertSame("{\"first\":\"a\",\"total\":6}\n", $jq('{first: .items[0].name, total: (.items | map(.n) | add)}'));
        $this->assertSame("[\"b\",\"c\",\"a\"]\n", $jq('.items | sort_by(.n) | map(.name)'));
        $this->assertSame("[[\"a\",\"c\"],[\"b\"]]\n", $jq('.items | group_by(.tag) | map(map(.name))'));
        $this->assertSame("[\"x\",\"y\"]\n", $jq('[.items[].tag] | unique'));
        $this->assertSame("1\n3\n", $jq('[.items[].n] | min, max'));
        $this->assertSame("{\"a\":3,\"b\":1,\"c\":2}\n", $jq('[.items[] | {key: .name, value: .n}] | from_entries'));
        $this->assertSame("[\"name\",\"n\",\"tag\"]\n", $jq('.items[0] | to_entries | map(.key)'));
        $this->assertSame("\"a\"\n", $jq('.items[0].name', ''));
        $this->assertSame("a\n", $jq('.items[0].name', '-r'));
        $this->assertSame(1, $this->sh("jq -e '.missing' /files/j/items.json")['exit_code']);
    }

    // Item 9: redirection and search fixes from the audit.
    public function test_redirection_and_search_fixes(): void
    {
        $this->file('/files/r/a.md', "Alpha one\nbeta two\nalpha three\ngamma\n");
        $this->file('/files/r/sub/b.md', "alpha four\ndelta\n");

        // >&2 goes to stderr (it used to write a file literally named "&2").
        $stderr = $this->sh('echo oops >&2');
        $this->assertSame('', $stderr['stdout']);
        $this->assertSame("oops\n", $stderr['stderr']);
        $this->assertStringNotContainsString('&2', $this->sh('ls /files/r')['stdout']);
        // &> writes stdout and stderr to one file.
        $this->sh('cat /files/r/a.md /files/r/missing.md &> /files/r/all.log');
        $log = $this->vfs->readContent($this->agent, '/files/r/all.log');
        $this->assertStringContainsString('Alpha one', $log);
        $this->assertStringContainsString('Path not found: /files/r/missing.md', $log);

        $this->assertSame("alpha three\n", $this->sh("egrep '^alph.+ t' /files/r/a.md")['stdout']);
        // egrep/fgrep validate against the grep spec (egrep -r used to fail as "Unsupported rg flag").
        $this->assertStringContainsString('alpha four', $this->sh("egrep -r 'four|five' /files/r")['stdout']);
        $this->assertSame("Alpha one\nalpha three\n", $this->sh("fgrep -i 'ALPHA' /files/r/a.md")['stdout']);
        $this->assertSame("beta two\ngamma\n", $this->sh("rg -e beta -e '^g' /files/r/a.md")['stdout']);

        // Recursive grep has full flag parity with single-file grep. Paths
        // are the stable canonical paths (the facade search contract).
        $a = $this->vfs->stat($this->agent, '/files/r/a.md')['canonical_path'];
        $b = $this->vfs->stat($this->agent, '/files/r/sub/b.md')['canonical_path'];
        $inverted = $this->lines($this->sh("grep -rv -i --include='*.md' alpha /files/r")['stdout']);
        sort($inverted);
        $expected = ["{$a}:beta two", "{$a}:gamma", "{$b}:delta"];
        sort($expected);
        $this->assertSame($expected, $inverted);
        $counts = $this->lines($this->sh("grep -rc -w --include='*.md' alpha /files/r")['stdout']);
        sort($counts);
        $expectedCounts = ["{$a}:1", "{$b}:1"];
        sort($expectedCounts);
        $this->assertSame($expectedCounts, $counts);
        $this->assertSame("{$a}-beta two\n{$a}:alpha three\n{$a}-gamma\n", $this->sh("grep -r -C1 three --include='*.md' /files/r")['stdout']);
        $this->assertSame("alpha four\n", $this->sh('grep -rh four /files/r')['stdout']);
        $this->assertSame("{$b}\n", $this->sh('grep -rl delta /files/r')['stdout']);
        $this->assertSame("/files/r/a.md:alpha three\n", $this->sh('grep -H three /files/r/a.md')['stdout']);
        $this->assertSame("{$b}:alpha four\n", $this->sh("rg -g '*.md' -g '!a.md' alpha /files/r")['stdout']);

        // paste -d, cmp -n, join -t -1 -2 parse their values (they used to be read as paths).
        $this->file('/files/r/k1.csv', "1,ann\n2,bob\n");
        $this->file('/files/r/k2.csv', "x,1\ny,2\n");
        $this->assertSame("1,ann,x,1\n2,bob,y,2\n", $this->sh('paste -d , /files/r/k1.csv /files/r/k2.csv')['stdout']);
        $this->assertSame(0, $this->sh('cmp -n 1 /files/r/k1.csv /files/r/k1.csv')['exit_code']);
        $this->assertSame("1,ann,x\n2,bob,y\n", $this->sh('join -t , -1 1 -2 2 /files/r/k1.csv /files/r/k2.csv')['stdout']);
        $this->assertSame("a b\nc \n", $this->sh("printf '%s %s\\n' a b c")['stdout']);
        $this->file('/files/r/s1.txt', "a\nb\nd\n");
        $this->file('/files/r/s2.txt', "b\nc\nd\n");
        $this->assertSame("a\n\t\tb\n\tc\n\t\td\n", $this->sh('comm /files/r/s1.txt /files/r/s2.txt')['stdout']);

        // Backslash is literal inside single quotes.
        $this->file('/files/r/win.txt', "C:\\temp\n");
        $this->assertSame("C:\\temp\n", $this->sh("grep 'C:\\' /files/r/win.txt")['stdout']);
    }

    // Item 9: globs in any segment and **, quote masks that keep data
    // literal (ll, xargs), and find grouping/-mtime/-mmin.
    public function test_globs_quote_masks_and_find_predicates(): void
    {
        $this->file('/files/g/p1/notes.md', 'n1');
        $this->file('/files/g/p2/notes.md', 'n2');
        $this->file('/files/g/p2/deep/x.md', 'x');
        $this->file('/files/g/star*.txt', 'literal star');
        $this->assertSame("/files/g/p1/notes.md\n/files/g/p2/notes.md\n", $this->sh('ls -d /files/g/*/notes.md')['stdout']);
        $this->assertStringContainsString('/files/g/p2/deep/x.md', $this->sh('ls -d /files/g/**/*.md')['stdout']);
        $this->assertSame('literal star', $this->sh("cat '/files/g/star*.txt'")['stdout']);
        $this->assertSame('not_found', $this->shErr("ll '/files/g/p*'")->errorCode);
        $this->assertSame('literal star', $this->sh("echo '/files/g/star*.txt' | xargs cat")['stdout']);

        $this->assertSame("/files/g/p1/notes.md\n/files/g/p2/deep/x.md\n", $this->sh("find /files/g \\( -path '*p1*' -o -name 'x.md' \\) -type f")['stdout']);
        $old = WorkspaceFile::query()->where('name', 'notes.md')->where('parent_id', WorkspaceFile::query()->where('name', 'p1')->value('id'))->sole();
        $old->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();
        $this->assertSame("/files/g/p1/notes.md\n", $this->sh('find /files/g -name notes.md -mtime +7')['stdout']);
        $this->assertSame("/files/g/p2/notes.md\n", $this->sh('find /files/g -name notes.md -mtime -7')['stdout']);
        $this->assertSame("/files/g/p2/notes.md\n", $this->sh('find /files/g -name notes.md -mmin -60')['stdout']);
        $this->assertSame('unsupported', $this->shErr('find /files/g -delete')->errorCode);
    }

    // Item 11: binary output fails loudly (it used to be an empty tool
    // result), and decompression/archives are bounded.
    public function test_binary_output_and_decompression_bounds(): void
    {
        $this->file('/files/b/plain.txt', str_repeat("compressible line\n", 2_000));
        $binary = $this->shErr('gzip -c /files/b/plain.txt');
        $this->assertSame('invalid_request', $binary->errorCode);
        $this->assertStringContainsString('base64', (string) $binary->context['hint']);
        $this->assertSame("2000\n", $this->sh('gzip -c /files/b/plain.txt | gunzip | wc -l')['stdout']);
        $this->sh('gzip -c /files/b/plain.txt > /files/b/plain.gz');
        $bomb = $this->shErr('zcat /files/b/plain.gz', new VfsBudget(maxBytes: 1_000));
        $this->assertSame('invalid_request', $bomb->errorCode);
        $this->assertStringContainsString('decompression limit', $bomb->getMessage());
        $this->sh('tar -c -z -f /files/b/plain.tgz /files/b/plain.txt');
        $this->assertStringContainsString('decompression limit', $this->shErr('tar -t -z -f /files/b/plain.tgz', new VfsBudget(maxBytes: 1_000))->getMessage());
        $this->sh('zip /files/b/plain.zip /files/b/plain.txt');
        $this->assertStringContainsString('decompression limit', $this->shErr('unzip -l /files/b/plain.zip', new VfsBudget(maxBytes: 1_000))->getMessage());

        // Archive creation is bounded by maxFiles in total, not per folder.
        foreach (['x', 'y'] as $folder) {
            foreach ([1, 2] as $n) {
                $this->file("/files/b/tree/{$folder}/{$n}.txt", "{$folder}{$n}");
            }
        }
        $tooMany = $this->shErr('tar -c -f /files/b/t.tar /files/b/tree', new VfsBudget(maxFiles: 3));
        $this->assertSame('invalid_request', $tooMany->errorCode);
        $this->assertSame('not_found', $this->shErr('cat /files/b/t.tar')->errorCode);
    }

    // Item 12: truncation is visible in stdout with a stable line and a
    // cursor that continues exactly; the aggregate output cap is enforced.
    public function test_truncation_lines_cursors_and_output_cap(): void
    {
        foreach (range(1, 5) as $n) {
            $this->file("/files/c/f{$n}.txt", "file {$n}\n");
        }
        $first = $this->sh('ls /files/c', new VfsBudget(maxEntries: 2));
        $this->assertTrue($first['truncated']);
        $this->assertNotNull($first['next_cursor']);
        $this->assertStringContainsString('[truncated: showed 2 of ≥3 entries — pass cursor "'.$first['next_cursor'].'"', $first['stdout']);
        $second = $this->executor->execute($this->agent, 'ls /files/c', '/', new VfsBudget(maxEntries: 2), null, $first['next_cursor']);
        $this->assertSame([], array_intersect($this->entries($first['stdout']), $this->entries($second['stdout'])));
        $this->assertCount(2, $this->entries($second['stdout']));

        $find = $this->sh('find /files/c -type f', new VfsBudget(maxEntries: 2));
        $this->assertNotNull($find['next_cursor']);
        $rest = $this->executor->execute($this->agent, 'find /files/c -type f', '/', new VfsBudget(maxEntries: 10), null, $find['next_cursor']);
        $this->assertCount(5, array_unique([...$this->entries($find['stdout']), ...$this->entries($rest['stdout'])]));

        $this->file('/files/c/big.txt', str_repeat("0123456789\n", 1_000));
        $read = $this->sh('cat /files/c/big.txt', new VfsBudget(maxBytes: 500));
        $this->assertStringContainsString('[truncated at ', $read['stdout']);
        $this->assertStringContainsString(' bytes of 11000 — use vfs_read with offset or head/tail/sed -n]', $read['stdout']);
        $this->assertSame("1000\n", $this->sh('cat /files/c/big.txt | wc -l', new VfsBudget(maxBytes: 500))['stdout']);

        $capped = $this->sh('cat /files/c/big.txt; cat /files/c/big.txt', new VfsBudget(maxBytes: 20_000, maxOutputBytes: 15_000));
        // cat of a paged collection shows one page plus a cursor line, and a
        // pipe drains every page (it used to stop at the first one).
        $table = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Shell Rows', 'created_by' => $this->agent->id]);
        foreach (range(1, 5) as $n) {
            DataTableRow::create(['table_id' => $table->id, 'data' => ['n' => $n], 'created_by' => $this->agent->id]);
        }
        $page = $this->sh('cat /tables/shell-rows/rows.ndjson', new VfsBudget(maxEntries: 2));
        $this->assertNotNull($page['next_cursor']);
        $this->assertCount(2, $this->entries($page['stdout']));
        $this->assertStringContainsString('[truncated: showed 2 of ≥3 records — pass cursor', $page['stdout']);
        $next = $this->executor->execute($this->agent, 'cat /tables/shell-rows/rows.ndjson', '/', new VfsBudget(maxEntries: 2), null, $page['next_cursor']);
        $this->assertCount(2, $this->entries($next['stdout']));
        $this->assertSame([], array_intersect($this->entries($page['stdout']), $this->entries($next['stdout'])));
        // rg -c counts records (and reads the pipe, not the cwd): the export (owned by the tables mount) has
        // no final newline, so wc -l would honestly report one less.
        $this->assertSame("5\n", $this->sh('cat /tables/shell-rows/rows.ndjson | rg -c .', new VfsBudget(maxEntries: 2))['stdout']);

        $this->assertTrue($capped['output_truncated']);
        $this->assertStringContainsString('[truncated: output exceeded', $capped['stdout']);
        $this->assertLessThan(16_000, strlen($capped['stdout']));
    }

    // Item 13: stdout once, earlier output preserved when a later command
    // fails, and approvals never swallowed by || or ;.
    public function test_result_shape_and_abort_rules(): void
    {
        $result = $this->sh('echo hi');
        $this->assertSame("hi\n", $result['stdout']);
        $this->assertArrayNotHasKey('stdout', $result['result']);
        foreach (['ok', 'exit_code', 'cwd', 'class', 'truncated', 'next_cursor', 'output_truncated', 'stderr'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }

        $failed = $this->shErr('echo kept; cat /files/nope.txt');
        $this->assertSame("kept\n", $failed->context['stdout']);

        $this->permission('tool', 'vfs_write', requiresApproval: true);
        foreach (['echo x > /files/gated.txt || echo swallowed', 'echo first; echo x > /files/gated.txt; echo later'] as $command) {
            $approval = $this->shErr($command);
            $this->assertSame('approval_required', $approval->errorCode, $command);
            $this->assertSame('pending_approval', $approval->context['status']);
            $this->assertStringNotContainsString('swallowed', (string) ($approval->context['stdout'] ?? ''));
            $this->assertStringNotContainsString('later', (string) ($approval->context['stdout'] ?? ''));
        }
        $this->assertSame("first\n", $this->shErr('echo first; echo x > /files/gated.txt; echo later')->context['stdout']);
        $this->assertSame('not_found', $this->shErr('cat /files/gated.txt')->errorCode);
    }

    // Item 14: discovery tells the agent exactly what exists and what to do.
    public function test_discovery_and_error_guidance(): void
    {
        $unknown = $this->shErr('frobnicate /docs');
        $this->assertSame('unsupported', $unknown->errorCode);
        $this->assertSame('Unknown VFS command: frobnicate. Run `help` to list commands.', $unknown->getMessage());
        $this->assertSame('not_found', $this->shErr('which frobnicate')->errorCode);
        $this->assertStringContainsString('Unknown VFS command: frobnicate', $this->shErr('help frobnicate')->getMessage());
        $this->assertStringContainsString('recognized, not supported', $this->sh('which curl')['stdout']);
        $this->assertSame("grep\n", $this->sh('which grep')['stdout']);

        $flag = $this->shErr('ls -Z /docs');
        $this->assertSame('unsupported', $flag->errorCode);
        $this->assertContains('-l', $flag->context['supported_flags']);
        $this->assertContains('-n', $this->shErr('grep -P x /docs')->context['supported_flags']);

        $variable = $this->shErr('echo $HOME');
        $this->assertStringContainsString('Code Mode (code_exec)', (string) $variable->context['suggestion']);
        $this->assertStringContainsString('Code Mode (code_exec)', (string) $this->shErr('bash -c ls')->context['suggestion']);
        $this->assertStringContainsString('/tools/vfs/commands/<command>/<command>.json', $this->sh('help')['stdout']);
        $this->assertStringContainsString('## Notes', $this->sh('help sed')['stdout']);
        // Lua was removed; every refusal points at mruby Code Mode or a tool.
        foreach (VfsCommandSpecRegistry::all() as $name => $spec) {
            $this->assertStringNotContainsStringIgnoringCase('lua', (string) $spec->suggestion, $name);
        }
        $this->assertStringContainsString('Action class: `reversible_edit`', $this->sh('help sed')['stdout']);
    }

    // Item 9: du is bounded by maxFiles in total and says so when cut (it
    // used to walk the whole workspace and swallow every error).
    public function test_du_is_bounded_and_reports_truncation(): void
    {
        foreach (range(1, 6) as $n) {
            $this->file("/files/du/f{$n}.txt", str_repeat('x', $n));
        }
        $this->assertSame("21\t/files/du\n", $this->sh('du -s /files/du')['stdout']);
        $cut = $this->sh('du -s /files/du', new VfsBudget(maxFiles: 3));
        $this->assertTrue($cut['truncated']);
        $this->assertStringContainsString('[truncated: showed 3 of ≥4 entries (sizes are partial)', $cut['stdout']);
    }

    // Item 15: find and du do not read content unless a predicate needs a
    // size (find used to read every visited entry).
    public function test_find_without_size_predicates_does_not_read_content(): void
    {
        foreach (range(1, 4) as $n) {
            Document::factory()->create(['title' => "Perf {$n}", 'content' => str_repeat('x', 1_000), 'author_id' => $this->agent->id]);
        }
        $counting = new class(app(\App\Services\AgentPermissionService::class), app(\App\Services\FileSystemService::class), app(\App\Agents\Tools\ToolRegistry::class), app(\App\Domain\Vfs\OpenCompany\VfsTextSearchService::class), app(\App\Domain\Vfs\OpenCompany\Policy\VfsMutationPolicy::class)) extends OpenCompanyVfs
        {
            public int $reads = 0;

            public function read(User $agent, string $path, ?VfsBudget $budget = null): string
            {
                $this->reads++;

                return parent::read($agent, $path, $budget);
            }
        };
        $executor = new VfsCommandExecutor($counting, app(\App\Domain\Vfs\OpenCompany\Policy\VfsMutationPolicy::class));

        $executor->execute($this->agent, 'find /docs -name "Perf*" -type f');
        $this->assertSame(0, $counting->reads);
        $executor->execute($this->agent, 'find /docs/by-id -size +0c');
        $this->assertGreaterThan(0, $counting->reads);
    }

    /**
     * @return array<string, mixed>
     */
    // Regression (integration of the shell with the change feed): pipes read
    // paged collections in full, but /workspace/changes.ndjson always returns
    // a resumable cursor so it can be polled later. Draining on the cursor
    // alone looped forever; it must stop when the page reports no more data.
    public function test_full_reads_of_the_resumable_change_feed_terminate(): void
    {
        $this->file('/files/feed-probe.txt', "x\n");

        $result = $this->sh('cat /workspace/changes.ndjson | wc -l');
        $this->assertTrue($result['ok']);

        $search = $this->sh('grep -r -c nothing-matches-this /workspace || true');
        $this->assertTrue($search['ok']);
    }

    private function sh(string $command, ?VfsBudget $budget = null): array
    {
        return $this->executor->execute($this->agent, $command, '/', $budget);
    }

    private function shErr(string $command, ?VfsBudget $budget = null): VfsError
    {
        try {
            $result = $this->executor->execute($this->agent, $command, '/', $budget);
        } catch (VfsError $e) {
            return $e;
        }
        $this->fail("Expected `{$command}` to fail, got: ".json_encode($result));
    }

    /**
     * @return list<string>
     */
    private function lines(string $stdout): array
    {
        return array_values(array_filter(explode("\n", $stdout), fn (string $line): bool => $line !== ''));
    }

    /**
     * @return list<string>
     */
    private function entries(string $stdout): array
    {
        return array_values(array_filter(explode("\n", $stdout), fn (string $line): bool => $line !== '' && ! str_starts_with($line, '[truncated')));
    }

    private function patchableDocument(string $title, string $content): Document
    {
        $folder = Document::factory()->create(['title' => "{$title} Folder", 'is_folder' => true, 'author_id' => $this->agent->id]);
        $this->permission('folder', $folder->id);

        return Document::factory()->create(['title' => $title, 'parent_id' => $folder->id, 'content' => $content, 'author_id' => $this->agent->id]);
    }

    private function file(string $path, string $content): void
    {
        $this->vfs->write($this->agent, $path, $content, 'create');
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
}

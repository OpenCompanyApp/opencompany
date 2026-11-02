<?php

namespace Tests\Feature\Vfs;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\User;
use App\Models\WorkspaceDisk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Behavioral coverage for every flag the VFS command registry advertises.
 *
 * Phase 2 rule: a flag is listed in VfsCommandSpecRegistry only when it is
 * implemented. These cases exercise each one against GNU-like expectations
 * (VfsShellFlagCoverageTest fails when a listed flag has no case here or in
 * another VFS test and no allowlist reason).
 */
class VfsShellFlagsTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private OpenCompanyVfs $vfs;

    private VfsCommandExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create(['name' => 'Atlas', 'status' => 'idle']);
        WorkspaceDisk::create(['id' => 'disk-local', 'workspace_id' => $this->workspace->id, 'name' => 'Local', 'driver' => 'local', 'config' => [], 'is_default' => true, 'enabled' => true]);
        AgentPermission::create(['id' => (string) Str::uuid(), 'agent_id' => $this->agent->id, 'scope_type' => 'file_folder', 'scope_key' => '*', 'permission' => 'allow', 'requires_approval' => false]);
        $this->vfs = app(OpenCompanyVfs::class);
        $this->executor = app(VfsCommandExecutor::class);

        foreach ([
            '/files/x/lines.txt' => "b 2\na 10\nc 1\nB 3\n",
            '/files/x/s1.txt' => "a\nb\nd\n",
            '/files/x/s2.txt' => "b\nc\nd\n",
            '/files/x/dup.txt' => "a\nA\nb\nb\nc\n",
            '/files/x/csv1.csv' => "1,ann\n2,bob\n",
            '/files/x/csv2.csv' => "1,red\n2,blue\n",
            '/files/x/dir/one.txt' => "one\n",
            '/files/x/dir/sub/two.txt' => "two\n",
            '/files/x/empty.txt' => '',
            '/files/rg/a.md' => "Foo bar\nbaz\nfoo\n",
            '/files/rg/b.txt' => "foo\n",
            '/files/rg/.hidden.md' => "foo hidden\n",
        ] as $path => $content) {
            $this->vfs->write($this->agent, $path, $content, 'create');
        }
    }

    // Text filter flags: each case is [command, expected stdout].
    public function test_text_filter_flags(): void
    {
        foreach ([
            ['echo aGk= | base64 -D', 'hi'],
            ['printf hello | base64 -w 4', "aGVs\nbG8=\n"],
            ['printf hi | base64 --wrap=0', "aGk=\n"],
            ["printf 'a b\\nccc d\\n' | column -t -o '|'", "a  |b\nccc|d\n"],
            ['comm -1 /files/x/s1.txt /files/x/s2.txt', "\tb\nc\n\td\n"],
            ['comm -2 /files/x/s1.txt /files/x/s2.txt', "a\n\tb\n\td\n"],
            ['comm -3 /files/x/s1.txt /files/x/s2.txt', "a\n\tc\n"],
            ['comm -12 --nocheck-order /files/x/s1.txt /files/x/s2.txt', "b\nd\n"],
            ["printf 'a,b\\nnodelim\\n' | cut -d, -f2 -s", "b\n"],
            ['echo hello | cut -b 2-3', "el\n"],
            ['echo a,b,c | cut -d, -f1,3 --output-delimiter=:', "a:c\n"],
            ['echo -n hi', 'hi'],
            ["echo -E 'a\\nb'", "a\\nb\n"],
            ["printf '\\ta\\tb' | expand -i -t 2", "  a\tb\n"],
            ["echo 'aaa bbb ccc' | fold -s -w 8", "aaa bbb\nccc\n"],
            ["printf 'caf\\xc3\\xa9' | iconv -c -f UTF-8 -t ASCII", 'caf'],
            ['join -t , -j 1 /files/x/csv1.csv /files/x/csv2.csv', "1,ann,red\n2,bob,blue\n"],
            ['look -f A /files/x/dup.txt', "a\nA\n"],
            ['nl -b a -v 10 -i 2 -w 2 -s : /files/x/s1.txt', "10:a\n12:b\n14:d\n"],
            ["printf 'x\\n\\ny\\n' | nl -b t", "     1\tx\n       \n     2\ty\n"],
            ['paste -s -d , /files/x/s1.txt', "a,b,d\n"],
            ["sed -r 's/(o+)/[\\1]/' /files/rg/b.txt", "f[oo]\n"],
            ["sed -s '1!d' /files/x/s1.txt /files/x/s2.txt", "a\nb\n"],
            ['seq -w 8 10', "08\n09\n10\n"],
            ['seq -s , 3', "1,2,3\n"],
            ["seq -f '%.1f' 1 2", "1.0\n2.0\n"],
            ['shuf -e c a b | sort', "a\nb\nc\n"],
            ['shuf -n 2 /files/x/s1.txt | wc -l', "2\n"],
            ['shuf -r -n 3 -e x', "x\nx\nx\n"],
            ['shuf -z -e only', "only\0"],
            ['sort -f /files/x/dup.txt | head -n 2 | sort', "A\na\n"],
            ["printf '2K\\n1M\\n3\\n' | sort -h", "3\n2K\n1M\n"],
            ["printf 'v1.10\\nv1.9\\n' | sort -V", "v1.9\nv1.10\n"],
            ["printf 'b 1\\na 1\\n' | sort -s -k2,2", "b 1\na 1\n"],
            ["printf 'Mar\\nJan\\nFeb\\n' | sort -M", "Jan\nFeb\nMar\n"],
            ["printf '  b\\na\\n' | sort -b", "a\n  b\n"],
            ['sort -t " " -k2,2n /files/x/lines.txt', "c 1\nb 2\nB 3\na 10\n"],
            ["echo 'hello world' | tr -c 'a-z\\n' _", "hello_world\n"],
            ["echo 'abc-123' | tr -C -d '0-9\\n'", "123\n"],
            ['uniq -i /files/x/dup.txt', "a\nb\nc\n"],
            ["printf 'x a\\ny a\\n' | uniq -f 1", "x a\n"],
            ["printf 'xa\\nya\\n' | uniq -s 1", "xa\n"],
            ["printf 'ab1\\nab2\\n' | uniq -w 2", "ab1\n"],
            ['wc -w /files/x/lines.txt', "8 /files/x/lines.txt\n"],
            ['printf ab | xxd -p -u', "6162\n"],
            ['printf abcdef | xxd -l 2 -p', "6162\n"],
            ['printf abcdef | xxd -s 4 -p', "6566\n"],
            ['printf abcd | xxd -c 2 -g 1', "00000000: 61 62  ab\n00000002: 63 64  cd\n"],
            ['printf a,b | xargs -d , echo', "a b\n"],
            ['printf "" | xargs -r echo ran', ''],
            ['printf "" | xargs echo ran', "ran\n"],
            ["echo '{\"a\":1}' | jq -c -s '.'", "[{\"a\":1}]\n"],
            ["jq -n '1 + 2'", "3\n"],
            ["echo '[\"a\",\"b\"]' | jq -j '.[]'", 'ab'],
            ['tail -v -n 1 /files/x/s1.txt', "==> /files/x/s1.txt <==\nd\n"],
            ['tail -q -n 1 /files/x/s1.txt /files/x/s2.txt', "d\nd\n"],
        ] as [$command, $expected]) {
            $this->assertSame($expected, $this->sh($command)['stdout'], $command);
        }
        $this->assertSame('invalid_request', $this->shErr('comm --check-order /files/x/dup.txt /files/x/s1.txt')->errorCode);
    }

    // Comparison, checksum and compression flags.
    public function test_comparison_checksum_and_compression_flags(): void
    {
        $this->assertSame("1 141 142\n3 142 143\n", $this->sh('cmp -l /files/x/s1.txt /files/x/s2.txt')['stdout']);
        $this->assertSame("/files/x/s1.txt /files/x/s2.txt differ: byte 1, line 1 is 141 a 142 b\n", $this->sh('cmp -b /files/x/s1.txt /files/x/s2.txt')['stdout']);
        $this->assertSame("/files/x/s1.txt /files/x/s2.txt differ: byte 1, line 1\n", $this->sh('cmp -i 2 /files/x/s1.txt /files/x/s2.txt')['stdout']);

        $this->vfs->write($this->agent, '/files/x/upper.txt', "A\n", 'create');
        $this->vfs->write($this->agent, '/files/x/lower.txt', "a\n", 'create');
        $this->assertSame(0, $this->sh('diff -i /files/x/upper.txt /files/x/lower.txt')['exit_code']);
        $this->vfs->write($this->agent, '/files/x/blank.txt', "a\n\nb\n", 'create');
        $this->vfs->write($this->agent, '/files/x/noblank.txt', "a\nb\n", 'create');
        $this->assertSame('', $this->sh('diff -B /files/x/blank.txt /files/x/noblank.txt')['stdout']);
        $this->assertSame("Files /files/x/s1.txt and /files/x/s1.txt are identical\n", $this->sh('diff -s /files/x/s1.txt /files/x/s1.txt')['stdout']);
        $this->vfs->write($this->agent, '/files/n1/a.txt', "same\n", 'create');
        $this->vfs->write($this->agent, '/files/n2/a.txt', "same\n", 'create');
        $this->vfs->write($this->agent, '/files/n2/new.txt', "new\n", 'create');
        $this->assertStringContainsString("diff /files/n1/new.txt /files/n2/new.txt\n0a1\n> new\n", $this->sh('diff -rN /files/n1 /files/n2')['stdout']);

        foreach (['md5sum' => 'md5sum -c /files/x/sums.md5sum', 'sha1sum' => 'sha1sum -c /files/x/sums.sha1sum', 'sha256sum' => 'sha256sum -c /files/x/sums.sha256sum'] as $tool => $check) {
            $this->sh("{$tool} /files/x/s1.txt > /files/x/sums.{$tool}");
            $this->assertSame("/files/x/s1.txt: OK\n", $this->sh($check)['stdout'], $tool);
            $this->sh("printf changed > /files/x/s1-copy.txt; {$tool} /files/x/s1-copy.txt > /files/x/sums.{$tool}; printf other > /files/x/s1-copy.txt");
            $this->assertSame(1, $this->sh($check)['exit_code'], $tool);
        }

        foreach ([
            'gzip -c -1 /files/x/s1.txt | gzip -d', 'gzip -c -2 /files/x/s1.txt | gzip -d', 'gzip -c -3 /files/x/s1.txt | gzip -d',
            'gzip -c -4 /files/x/s1.txt | gzip -d', 'gzip -c -5 /files/x/s1.txt | gzip -d', 'gzip -c -6 /files/x/s1.txt | gzip -d',
            'gzip -c -7 /files/x/s1.txt | gzip -d', 'gzip -c -8 /files/x/s1.txt | gzip -d', 'gzip -c -9 /files/x/s1.txt | gzip -d',
        ] as $command) {
            $this->assertSame("a\nb\nd\n", $this->sh($command)['stdout'], $command);
        }
        $this->assertSame('', $this->sh('gzip -c /files/x/s1.txt | gunzip -t')['stdout']);
        $this->sh('gzip -c /files/rg/a.md > /files/x/a.gz');
        foreach ([
            ['zgrep -i foo /files/x/a.gz', "Foo bar\nfoo\n"],
            ['zgrep -c foo /files/x/a.gz', "1\n"],
            ['zgrep -l baz /files/x/a.gz', "/files/x/a.gz\n"],
            ['zgrep -n baz /files/x/a.gz', "2:baz\n"],
            ['zgrep -v -e foo -e Foo /files/x/a.gz', "baz\n"],
            ["zgrep -E 'ba(r|z)' /files/x/a.gz", "Foo bar\nbaz\n"],
            ['zgrep -F . /files/x/a.gz', ''],
            ['zgrep -H baz /files/x/a.gz', "/files/x/a.gz:baz\n"],
            ['zgrep -h baz /files/x/a.gz /files/x/a.gz', "baz\nbaz\n"],
            ['zgrep -o ba. /files/x/a.gz', ''],
            ['zgrep -E -o "ba." /files/x/a.gz', "bar\nbaz\n"],
            ['zgrep -q foo /files/x/a.gz', ''],
            ['zgrep -w ba /files/x/a.gz', ''],
            ['zgrep -x baz /files/x/a.gz', "baz\n"],
            ['zgrep -m 1 o /files/x/a.gz', "Foo bar\n"],
            ['zgrep -A 1 bar /files/x/a.gz', "Foo bar\nbaz\n"],
            ['zgrep -B 1 baz /files/x/a.gz', "Foo bar\nbaz\n"],
            ['zgrep -C 1 baz /files/x/a.gz', "Foo bar\nbaz\nfoo\n"],
        ] as [$command, $expected]) {
            $this->assertSame($expected, $this->sh($command)['stdout'], $command);
        }
        $this->assertSame(0, $this->sh('zgrep -q foo /files/x/a.gz')['exit_code']);
    }

    // grep / rg flags in single-file and recursive mode.
    public function test_grep_and_rg_flags(): void
    {
        // Recursive search prints readable friendly paths (Phase 3 decision).
        $a = '/files/rg/a.md';
        $b = '/files/rg/b.txt';
        foreach ([
            ['grep -L zzz /files/x/s1.txt /files/x/s2.txt', "/files/x/s1.txt\n/files/x/s2.txt\n"],
            ['grep -x b /files/x/s1.txt', "b\n"],
            ['grep -e a -e d /files/x/s1.txt', "a\nd\n"],
            ['grep --regexp=a /files/x/s1.txt', "a\n"],
            ['grep --line-regexp b /files/x/s1.txt', "b\n"],
            ['grep --files-without-match zzz /files/x/s1.txt', "/files/x/s1.txt\n"],
            ['grep -r --max-depth 1 one /files/x', ''],
            ["grep -r --exclude='*.md' foo /files/rg", "{$b}:foo\n"],
            ['rg -v foo /files/rg/a.md', "Foo bar\nbaz\n"],
            ['rg -n baz /files/rg/a.md', "2:baz\n"],
            ['rg -c foo /files/rg/a.md', "1\n"],
            ['rg -w ba /files/rg/a.md', ''],
            ['rg -x baz /files/rg/a.md', "baz\n"],
            ['rg -F "ba." /files/rg/a.md', ''],
            ['rg -o "ba." /files/rg/a.md', "bar\nbaz\n"],
            ['rg -q foo /files/rg/a.md', ''],
            ['rg -H baz /files/rg/a.md', "/files/rg/a.md:baz\n"],
            ['rg -h -i foo /files/rg', "Foo bar\nfoo\nfoo\n"],
            ['rg -A 1 bar /files/rg/a.md', "Foo bar\nbaz\n"],
            ['rg -B 1 baz /files/rg/a.md', "Foo bar\nbaz\n"],
            ['rg -C 1 baz /files/rg/a.md', "Foo bar\nbaz\nfoo\n"],
            ["rg --glob '*.txt' foo /files/rg", "{$b}:foo\n"],
            ['rg -t md -i foo /files/rg', "{$a}:Foo bar\n{$a}:foo\n"],
            ['rg --type txt foo /files/rg', "{$b}:foo\n"],
            ['rg --files /files/rg', "/files/rg/a.md\n/files/rg/b.txt\n"],
        ] as [$command, $expected]) {
            $this->assertSame($expected, $this->sh($command)['stdout'], $command);
        }
        $this->assertStringContainsString('hidden', $this->sh('rg --hidden foo /files/rg')['stdout']);
        $this->assertStringNotContainsString('hidden', $this->sh('rg foo /files/rg')['stdout']);
        $silent = $this->sh('rg -s foo /files/rg/missing.md /files/rg/b.txt');
        $this->assertSame('', $silent['stderr']);
        $this->assertSame(2, $silent['exit_code']);
        $quietGrep = $this->sh('grep -s foo /files/rg/missing.md /files/rg/b.txt');
        $this->assertSame('', $quietGrep['stderr']);
        $this->sh('gzip -c /files/rg/b.txt > /files/rg/bin.gz');
        $this->assertSame(1, $this->sh('grep -I . /files/rg/bin.gz')['exit_code']);
        // Long aliases map onto the same implementation.
        $this->assertSame($this->sh('rg -i -c foo /files/rg/a.md')['stdout'], $this->sh('rg --ignore-case --count foo /files/rg/a.md')['stdout']);
        $this->assertSame($this->sh('sort -u -f /files/x/dup.txt')['stdout'], $this->sh('sort --unique --ignore-case /files/x/dup.txt')['stdout']);
    }

    // Browse, path and metadata flags.
    public function test_browse_path_and_metadata_flags(): void
    {
        $this->vfs->write($this->agent, '/files/x/.dot', 'dot', 'create');
        $this->assertStringContainsString('.dot', $this->sh('ls -A /files/x')['stdout']);
        $this->assertStringNotContainsString('.dot', $this->sh('ls /files/x')['stdout']);
        $this->assertStringContainsString('./', $this->sh('ls -a /files/x')['stdout']);
        $this->vfs->write($this->agent, '/files/h/big.bin', str_repeat('x', 3_000), 'create');
        $this->assertMatchesRegularExpression('/2\.9K .* big\.bin/', $this->sh('ls -lh /files/h')['stdout']);
        $this->vfs->write($this->agent, '/files/h/small.bin', 'x', 'create');
        $this->assertSame("big.bin\nsmall.bin\n", $this->sh('ls -S /files/h')['stdout']);
        $this->assertSame("small.bin\nbig.bin\n", $this->sh('ls -Sr /files/h')['stdout']);
        $this->assertCount(2, array_filter(explode("\n", $this->sh('ls -t /files/h')['stdout'])));

        foreach ([
            ['find /files/x -iname "S1.TXT"', "/files/x/s1.txt\n"],
            ['find /files/x -ipath "*/DIR/ONE.txt"', "/files/x/dir/one.txt\n"],
            ['find /files/x/dir -mindepth 2 -type f', "/files/x/dir/sub/two.txt\n"],
            ['find /files/x/dir --max-depth=1 -type f -print', "/files/x/dir/one.txt\n"],
            ['find /files/x -empty -type f', "/files/x/empty.txt\n"],
            ['find /files/x/dir -type f -and ! -name one.txt', "/files/x/dir/sub/two.txt\n"],
            ['find /files/x/dir \\( -name one.txt -o -name sub \\) -type d', "/files/x/dir/sub\n"],
            ['tree -d /files/x/dir', "/files/x/dir\n`-- sub/\n"],
            ['tree --level 1 /files/x/dir', "/files/x/dir\n|-- sub/\n`-- one.txt\n[truncated: showed 2 of ≥3 entries — raise maxEntries/maxDepth or narrow the path]\n"],
            ['tree -I sub /files/x/dir', "/files/x/dir\n`-- one.txt\n"],
            ["tree -P 'two*' /files/x/dir", "/files/x/dir\n`-- sub/\n    `-- two.txt\n"],
            ['readlink -n /files/x/../x/s1.txt', '/files/x/s1.txt'],
            ['readlink -m /files/x/nope', "/files/x/nope\n"],
            ['realpath -m /files/x/./nope', "/files/x/nope\n"],
            ["stat -f '%n %s' /files/x/s1.txt", "/files/x/s1.txt 6\n"],
            ["stat --format '%F' /files/x/s1.txt", "file\n"],
            ['du -s /files/x/dir', "8\t/files/x/dir\n"],
            ['du -d 0 /files/x/dir', "8\t/files/x/dir\n"],
            ['du -sc /files/x/dir /files/x/s1.txt', "8\t/files/x/dir\n6\t/files/x/s1.txt\n14\ttotal\n"],
            ['date -u -d 2024-01-02T03:04:05Z +%F', "2024-01-02\n"],
        ] as [$command, $expected]) {
            $this->assertSame($expected, $this->sh($command)['stdout'], $command);
        }
        // find keeps the mount's listing order (folders first in /files).
        $this->assertSame(['/files/x/dir/one.txt', '/files/x/dir/sub/two.txt'], $this->sorted($this->sh('find /files/x/dir -name one.txt -or -name two.txt')['stdout']));
        $this->assertStringContainsString('/files/x/dir/sub/two.txt', $this->sh('find /files/x/dir -ls')['stdout']);
        $this->assertStringContainsString('.dot', $this->sh('tree -a /files/x')['stdout']);
        $this->assertStringContainsString('02 Jan 2024', $this->sh('date -R -u -d 2024-01-02T00:00:00Z')['stdout']);
        $this->assertSame('not_found', $this->shErr('readlink -e /files/x/nope')->errorCode);
        $this->assertSame('not_found', $this->shErr('realpath -e /files/x/nope')->errorCode);
        $this->assertStringContainsString('charset=utf-8', $this->sh('file -i /files/x/s1.txt')['stdout']);
        $this->assertSame($this->sh('file -i /files/x/s1.txt')['stdout'], $this->sh('file --mime /files/x/s1.txt')['stdout']);
    }

    // Mutation, archive and split flags.
    public function test_mutation_archive_and_split_flags(): void
    {
        $this->sh('cp -r /files/x/dir /files/x/dir2');
        $this->assertSame("two\n", $this->vfs->readContent($this->agent, '/files/x/dir2/sub/two.txt'));
        $this->sh('cp -n /files/x/s1.txt /files/x/s2.txt');
        $this->assertSame("b\nc\nd\n", $this->vfs->readContent($this->agent, '/files/x/s2.txt'));
        $this->sh('mv -n /files/x/s1.txt /files/x/s2.txt');
        $this->assertSame("a\nb\nd\n", $this->vfs->readContent($this->agent, '/files/x/s1.txt'));
        $this->sh('ln -f /files/x/s1.txt /files/x/s2.txt');
        $this->assertSame("a\nb\nd\n", $this->vfs->readContent($this->agent, '/files/x/s2.txt'));
        $this->sh('touch -c /files/x/never.txt');
        $this->assertSame('not_found', $this->shErr('cat /files/x/never.txt')->errorCode);

        $this->assertSame('', $this->sh('csplit -s -n 3 -f /files/x/cs- /files/x/lines.txt 3')['stdout']);
        $this->assertSame("b 2\na 10\n", $this->vfs->readContent($this->agent, '/files/x/cs-000'));
        $this->assertStringStartsWith("/files/x/p00\n/files/x/p01\n", $this->sh('split -d -a 2 -b 5 /files/x/lines.txt /files/x/p')['stdout']);
        $this->assertSame("b 2\na", $this->vfs->readContent($this->agent, '/files/x/p00'));
        $this->assertSame("/files/x/q0\n/files/x/q1\n", $this->sh('split -n 2 -d -a 1 /files/x/s1.txt /files/x/q')['stdout']);

        $this->assertSame("dir/one.txt\ndir/sub/two.txt\n", $this->sh('tar -c -v -z -f /files/x/a.tgz /files/x/dir')['stdout']);
        $this->assertStringContainsString('dir/sub/two.txt', $this->sh('tar -t -v -z -f /files/x/a.tgz')['stdout']);
        $this->assertSame(['/files/x/out/one.txt', '/files/x/out/sub/two.txt'], $this->sorted($this->sh('tar -x -z -f /files/x/a.tgz --strip-components 1 -C /files/x/out')['stdout']));
        $this->assertSame("dir/one.txt\n", $this->sh("tar -c -v --exclude 'two*' -f /files/x/b.tar /files/x/dir")['stdout']);

        $this->sh('zip -j -r /files/x/a.zip /files/x/dir');
        $this->assertStringContainsString('two.txt', $this->sh('unzip -l /files/x/a.zip')['stdout']);
        $this->assertStringNotContainsString('sub/two.txt', $this->sh('unzip -l /files/x/a.zip')['stdout']);
        $this->assertSame("one\ntwo\n", $this->sh('unzip -p /files/x/a.zip')['stdout']);
        $this->assertStringContainsString('OK', $this->sh('unzip -t /files/x/a.zip')['stdout']);
        $this->assertSame('', $this->sh('unzip -q -o /files/x/a.zip -d /files/x/unz')['stdout']);
        $this->assertSame("one\n", $this->vfs->readContent($this->agent, '/files/x/unz/one.txt'));
    }

    /**
     * @return list<string>
     */
    private function sorted(string $stdout): array
    {
        $lines = array_values(array_filter(explode("\n", $stdout)));
        sort($lines);

        return $lines;
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
}

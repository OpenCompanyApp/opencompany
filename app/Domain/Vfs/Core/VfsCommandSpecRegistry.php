<?php

namespace App\Domain\Vfs\Core;

/**
 * Canonical registry for VFS command discovery and option preflight.
 *
 * The list intentionally covers Mirage's unix command surface. Commands that
 * are unsafe or out of the VFS domain are still registered so agents get a
 * precise, stable error instead of guessing whether the command exists.
 */
final class VfsCommandSpecRegistry
{
    /**
     * @return array<string, VfsCommandSpec>
     */
    public static function all(): array
    {
        static $specs = null;
        if ($specs !== null) {
            return $specs;
        }

        $specs = [];
        $add = function (VfsCommandSpec $spec) use (&$specs): void {
            $specs[$spec->name] = $spec;
        };
        $implemented = 'implemented';
        $unsupported = 'recognized_unsupported';

        $add(new VfsCommandSpec('pwd', 'browse', 'Print the current virtual working directory.', $implemented, shortFlags: ['P', 'L']));
        $add(new VfsCommandSpec('cd', 'browse', 'Change the command-local virtual working directory.'));
        $add(new VfsCommandSpec('ls', 'browse', 'List virtual directory entries.', $implemented, aliases: ['dir', 'll'], shortFlags: ['1', 'a', 'A', 'l', 'R', 'F', 'd', 'h', 't', 'S', 'r']));
        $add(new VfsCommandSpec('tree', 'browse', 'Render a bounded directory tree.', $implemented, shortFlags: ['a', 'd'], valueOptions: ['-L', '--level', '-I', '-P']));
        $add(new VfsCommandSpec('find', 'browse', 'Find entries in bounded virtual directory trees.', $implemented, valueOptions: ['-name', '-type', '-maxdepth', '-maxDepth', '--max-depth', '-iname', '-path', '-mindepth', '-size', '-mtime'], longFlags: ['-print', '-print0', '-ls', '-empty', '-o', '-or', '-a', '-and', '-not']));
        $add(new VfsCommandSpec('cat', 'read', 'Read virtual text content.', $implemented, aliases: ['less', 'more'], shortFlags: ['n']));
        $add(new VfsCommandSpec('head', 'read', 'Read the first lines or bytes of text content.', $implemented, valueOptions: ['-n', '--lines', '-c', '--bytes'], numericShorthand: true));
        $add(new VfsCommandSpec('tail', 'read', 'Read the last lines or bytes of text content.', $implemented, shortFlags: ['q', 'v'], valueOptions: ['-n', '--lines', '-c', '--bytes'], longFlags: ['--follow'], numericShorthand: true));
        $add(new VfsCommandSpec('wc', 'read', 'Count lines, words, bytes, characters, and maximum line length.', $implemented, shortFlags: ['l', 'w', 'c', 'm', 'L']));
        $add(new VfsCommandSpec('stat', 'browse', 'Return structured metadata or formatted stat fields.', $implemented, valueOptions: ['-c', '-f', '--format']));
        $add(new VfsCommandSpec('file', 'browse', 'Show the virtual file type or mime type.', $implemented, shortFlags: ['b', 'i'], longFlags: ['--brief', '--mime']));
        $add(new VfsCommandSpec('du', 'read', 'Show byte usage for readable virtual paths.', $implemented, shortFlags: ['h', 's', 'a', 'c'], valueOptions: ['--max-depth']));
        $add(new VfsCommandSpec('realpath', 'browse', 'Normalize a virtual path.', $implemented, shortFlags: ['e', 'm']));
        $add(new VfsCommandSpec('readlink', 'browse', 'Resolve virtual links or canonical paths.', $implemented, shortFlags: ['f', 'e', 'm', 'n']));
        $add(new VfsCommandSpec('dirname', 'browse', 'Return the parent virtual path.'));
        $add(new VfsCommandSpec('basename', 'browse', 'Return the leaf virtual path name.'));
        $add(new VfsCommandSpec('grep', 'read', 'Search literal or regex text.', $implemented, aliases: ['egrep', 'fgrep', 'search'], shortFlags: ['r', 'R', 'i', 'I', 'v', 'n', 'c', 'l', 'w', 'F', 'E', 'o', 'q', 'H', 'h'], valueOptions: ['-m', '--max-count', '-A', '-B', '-C', '-e', '--max-depth']));
        $add(new VfsCommandSpec('rg', 'read', 'Search VFS text using regex-style matching.', $implemented, shortFlags: ['i', 'v', 'n', 'c', 'l', 'w', 'F', 'o', 'q', 'H', 'h'], valueOptions: ['-m', '--max-count', '-A', '-B', '-C', '--max-depth', '--type', '--glob'], longFlags: ['--hidden']));
        $add(new VfsCommandSpec('echo', 'read-transform', 'Print arguments to stdout.', $implemented, shortFlags: ['n', 'e']));
        $add(new VfsCommandSpec('true', 'read-transform', 'Return success with no output.'));
        $add(new VfsCommandSpec('false', 'read-transform', 'Return a VFS command failure for conditional chains.'));
        $add(new VfsCommandSpec('printf', 'read-transform', 'Format arguments with a printf-style format string.'));
        $add(new VfsCommandSpec('sort', 'read-transform', 'Sort piped text lines.', $implemented, shortFlags: ['r', 'n', 'u', 'f', 'h', 'V', 's', 'M'], valueOptions: ['-k', '-t']));
        $add(new VfsCommandSpec('uniq', 'read-transform', 'Filter adjacent duplicate piped text lines.', $implemented, shortFlags: ['c', 'd', 'u', 'i'], valueOptions: ['-f', '-s', '-w']));
        $add(new VfsCommandSpec('cut', 'read-transform', 'Select delimited fields or characters from piped text.', $implemented, shortFlags: ['z'], valueOptions: ['-d', '-f', '-c'], longFlags: ['--complement']));
        $add(new VfsCommandSpec('awk', 'read-transform', 'Small awk subset for field extraction.', $implemented, valueOptions: ['-F', '-v', '-f']));
        $add(new VfsCommandSpec('xargs', 'read-transform', 'Run a bounded VFS command for stdin items.', $implemented, shortFlags: ['0'], valueOptions: ['-I', '-n', '--max-args']));
        $add(new VfsCommandSpec('tr', 'read-transform', 'Translate, delete, squeeze, or complement characters in piped text.', $implemented, shortFlags: ['d', 's', 'c']));
        $add(new VfsCommandSpec('nl', 'read-transform', 'Number piped text lines.', $implemented, valueOptions: ['-b', '-v', '-i', '-w', '-s']));
        $add(new VfsCommandSpec('rev', 'read-transform', 'Reverse each piped text line.'));
        $add(new VfsCommandSpec('paste', 'read-transform', 'Merge input lines or readable files.', $implemented, shortFlags: ['s'], valueOptions: ['-d']));
        $add(new VfsCommandSpec('jq', 'read-transform', 'Safe jq subset for JSON projections.', $implemented, shortFlags: ['r', 'c', 's'], longFlags: ['--raw-output', '--compact-output', '--slurp']));
        $add(new VfsCommandSpec('sed', 'read-transform', 'Run safe substitutions and simple print expressions.', $implemented, shortFlags: ['n', 'E'], valueOptions: ['-e'], longFlags: [], unsupportedReason: 'In-place sed edits are not supported; use vfs_patch.'));
        $add(new VfsCommandSpec('diff', 'read-transform', 'Compare two readable virtual files.', $implemented, shortFlags: ['i', 'w', 'b', 'u', 'q', 'r']));
        $add(new VfsCommandSpec('cmp', 'read-transform', 'Report whether two readable virtual files differ.', $implemented, shortFlags: ['l', 's', 'b'], valueOptions: ['-n', '-i']));
        $add(new VfsCommandSpec('comm', 'read-transform', 'Compare two sorted readable virtual files line by line.', $implemented, shortFlags: ['1', '2', '3'], longFlags: ['--check-order', '--nocheck-order']));
        $add(new VfsCommandSpec('sha256sum', 'read-transform', 'Compute or check SHA-256 over readable virtual text.', $implemented, shortFlags: ['c']));
        $add(new VfsCommandSpec('sha1sum', 'read-transform', 'Compute SHA-1 over readable virtual text.'));
        $add(new VfsCommandSpec('md5sum', 'read-transform', 'Compute MD5 over readable virtual text.', $implemented, aliases: ['md5']));
        $add(new VfsCommandSpec('checksum', 'read-transform', 'Compute SHA-256 over readable virtual text.'));
        $add(new VfsCommandSpec('which', 'browse', 'Show whether a command is supported by the VFS command engine.'));
        $add(new VfsCommandSpec('type', 'browse', 'Describe a supported VFS command or alias.'));
        $add(new VfsCommandSpec('help', 'browse', 'List VFS commands or render command-specific help.', usage: 'help [command]', structuredAlternative: 'cat /tools/vfs/commands.json', examples: ['help', 'help grep', 'rm --help']));
        $add(new VfsCommandSpec('man', 'browse', 'Alias for command-specific VFS help.', usage: 'man <command>', structuredAlternative: 'cat /tools/vfs/commands/<command>.json', examples: ['man grep']));
        $add(new VfsCommandSpec('command', 'browse', 'Supports command -v for VFS command discovery.', $implemented, shortFlags: ['v']));
        $add(new VfsCommandSpec('env', 'browse', 'Print safe VFS execution environment metadata.'));
        $add(new VfsCommandSpec('printenv', 'browse', 'Print safe VFS execution environment metadata.'));
        $add(new VfsCommandSpec('date', 'browse', 'Print application time.', $implemented, shortFlags: ['u', 'I', 'R'], valueOptions: ['-d']));
        $add(new VfsCommandSpec('mkdir', 'write', 'Create a workspace-file folder after write permission preflight.', $implemented, shortFlags: ['p', 'v']));
        $add(new VfsCommandSpec('touch', 'write', 'Create or touch a workspace file after write permission preflight.', $implemented, shortFlags: ['c'], valueOptions: ['-r', '-d']));
        $add(new VfsCommandSpec('cp', 'write', 'Copy a workspace file after write permission preflight.', $implemented, shortFlags: ['r', 'R', 'a', 'f', 'n', 'v']));
        $add(new VfsCommandSpec('mv', 'write', 'Move a workspace file after write permission preflight.', $implemented, shortFlags: ['f', 'n', 'v']));
        $add(new VfsCommandSpec('rm', 'destructive', 'Delete a workspace file after write permission preflight.', $implemented, aliases: ['rmdir'], shortFlags: ['r', 'R', 'f', 'v', 'd']));
        $add(new VfsCommandSpec('mktemp', 'write', 'Create a unique temporary file or directory under /files.', $implemented, shortFlags: ['d', 't'], valueOptions: ['-p']));
        $add(new VfsCommandSpec('truncate', 'write', 'Overwrite a writable virtual file with empty content.', $implemented, valueOptions: ['-s', '--size']));
        $add(new VfsCommandSpec('tee', 'write', 'Write or append piped text to a writable VFS path.', $implemented, shortFlags: ['a']));

        foreach ([
            'tac' => 'Reverse input line order.',
            'seq' => 'Print bounded numeric sequences.',
            'base64' => 'Encode or decode base64 text.',
            'column' => 'Format delimited text into aligned columns.',
            'fold' => 'Wrap long input lines.',
            'fmt' => 'Reflow paragraphs.',
            'expand' => 'Convert tabs to spaces.',
            'unexpand' => 'Convert leading spaces to tabs.',
            'tsort' => 'Topologically sort dependency pairs.',
            'look' => 'Print lines beginning with a prefix.',
            'join' => 'Join two readable files on a field.',
            'shuf' => 'Shuffle input lines.',
            'bc' => 'Evaluate simple numeric expressions.',
            'expr' => 'Evaluate simple POSIX-style expressions.',
        ] as $name => $description) {
            $add(new VfsCommandSpec($name, 'read-transform', $description));
        }
        $specs['seq'] = new VfsCommandSpec('seq', 'read-transform', 'Print bounded numeric sequences.', shortFlags: ['w'], valueOptions: ['-s', '-f']);
        $specs['base64'] = new VfsCommandSpec('base64', 'read-transform', 'Encode or decode base64 text.', shortFlags: ['d', 'D'], valueOptions: ['-w']);
        $specs['gzip'] = new VfsCommandSpec('gzip', 'read-transform', 'Compress or decompress gzip data through bounded stdout/stdin.', shortFlags: ['d', 'k', 'f', 'c', '1', '2', '3', '4', '5', '6', '7', '8', '9']);
        $specs['gunzip'] = new VfsCommandSpec('gunzip', 'read-transform', 'Decompress gzip data from stdin or readable paths.', shortFlags: ['k', 'f', 'c', 't']);
        $specs['zcat'] = new VfsCommandSpec('zcat', 'read-transform', 'Decompress gzip data to stdout.');
        $specs['zgrep'] = new VfsCommandSpec('zgrep', 'read-transform', 'Search gzip-compressed readable text.', shortFlags: ['i', 'c', 'l', 'n', 'v', 'E', 'F', 'H', 'h', 'o', 'q', 'w'], valueOptions: ['-e', '-m', '-A', '-B', '-C']);
        $specs['xxd'] = new VfsCommandSpec('xxd', 'read-transform', 'Render bounded hex dumps or reverse plain hex text.', shortFlags: ['r', 'p', 'u'], valueOptions: ['-l', '-c', '-s', '-g']);
        $specs['strings'] = new VfsCommandSpec('strings', 'read-transform', 'Extract printable strings from readable VFS content.', valueOptions: ['-n']);
        $specs['iconv'] = new VfsCommandSpec('iconv', 'read-transform', 'Convert text encoding for readable VFS content.', shortFlags: ['c'], valueOptions: ['-f', '-t', '-o']);
        $specs['column'] = new VfsCommandSpec('column', 'read-transform', 'Format delimited text into aligned columns.', shortFlags: ['t'], valueOptions: ['-s', '-o']);
        $specs['fold'] = new VfsCommandSpec('fold', 'read-transform', 'Wrap long input lines.', shortFlags: ['s'], valueOptions: ['-w']);
        $specs['fmt'] = new VfsCommandSpec('fmt', 'read-transform', 'Reflow paragraphs.', valueOptions: ['-w']);
        $specs['expand'] = new VfsCommandSpec('expand', 'read-transform', 'Convert tabs to spaces.', shortFlags: ['i'], valueOptions: ['-t']);
        $specs['unexpand'] = new VfsCommandSpec('unexpand', 'read-transform', 'Convert leading spaces to tabs.', shortFlags: ['a'], valueOptions: ['-t']);
        $specs['look'] = new VfsCommandSpec('look', 'read-transform', 'Print lines beginning with a prefix.', shortFlags: ['f']);
        $specs['shuf'] = new VfsCommandSpec('shuf', 'read-transform', 'Shuffle input lines.', shortFlags: ['e', 'z', 'r'], valueOptions: ['-n']);
        $specs['bc'] = new VfsCommandSpec('bc', 'read-transform', 'Evaluate simple numeric expressions.', shortFlags: ['l', 'q']);

        foreach ([
            'bash' => ['Host shell execution is not supported by VFS.', 'Use VFS commands, Code Mode (code_exec) helpers, or direct tools.'],
            'python' => ['Host Python execution is not supported by VFS.', 'Use Code Mode (code_exec) helpers or direct tools.'],
            'python3' => ['Host Python execution is not supported by VFS.', 'Use Code Mode (code_exec) helpers or direct tools.'],
            'curl' => ['Network commands are outside VFS.', 'Use web_fetch, browser tools, or integration tools.'],
            'wget' => ['Network commands are outside VFS.', 'Use web_fetch, browser tools, or integration tools.'],
            'sleep' => ['Blocking sleeps are not supported by bounded VFS exec.', 'Use automations or task scheduling outside the VFS shell.'],
            'patch' => ['Patch is intentionally a direct permission-aware tool.', 'Use vfs_patch for targeted docs, files, tasks, lists, table rows, and scoped agent identity/memory edits.'],
            'history' => ['Interactive shell history is not exposed by VFS.', 'Use explicit command transcripts or operation logs outside the VFS shell.'],
        ] as $name => [$reason, $suggestion]) {
            $add(new VfsCommandSpec($name, 'external', $reason, $unsupported, unsupportedReason: $reason, suggestion: $suggestion));
        }
        $specs['curl'] = new VfsCommandSpec('curl', 'external', 'Recognized external network command; rejected inside VFS.', $unsupported, shortFlags: ['L', 's', 'S'], valueOptions: ['-H', '-A', '-X', '-d', '-F', '-o'], longFlags: ['--jina'], unsupportedReason: 'Network commands are outside VFS.', suggestion: 'Use web_fetch, browser tools, or integration tools.');
        $specs['wget'] = new VfsCommandSpec('wget', 'external', 'Recognized external network command; rejected inside VFS.', $unsupported, shortFlags: ['q'], valueOptions: ['-O'], longFlags: ['--spider'], unsupportedReason: 'Network commands are outside VFS.', suggestion: 'Use web_fetch, browser tools, or integration tools.');
        $specs['bash'] = new VfsCommandSpec('bash', 'external', 'Recognized host shell command; rejected inside VFS.', $unsupported, shortFlags: ['s', 'l', 'i', 'e', 'u', 'x'], valueOptions: ['-c'], longFlags: ['--login', '--norc', '--noprofile', '--posix'], unsupportedReason: 'Host shell execution is not supported by VFS.', suggestion: 'Use VFS commands, Code Mode (code_exec) helpers, or direct tools.');
        $specs['python'] = new VfsCommandSpec('python', 'external', 'Recognized host Python command; rejected inside VFS.', $unsupported, valueOptions: ['-c'], unsupportedReason: 'Host Python execution is not supported by VFS.', suggestion: 'Use Code Mode (code_exec) helpers or direct tools.');
        $specs['python3'] = new VfsCommandSpec('python3', 'external', 'Recognized host Python command; rejected inside VFS.', $unsupported, valueOptions: ['-c'], unsupportedReason: 'Host Python execution is not supported by VFS.', suggestion: 'Use Code Mode (code_exec) helpers or direct tools.');
        $specs['patch'] = new VfsCommandSpec('patch', 'external', 'Recognized patch command; use vfs_patch instead.', $unsupported, shortFlags: ['R', 'N'], valueOptions: ['-p', '-i'], unsupportedReason: 'Patch is intentionally a direct permission-aware tool.', suggestion: 'Use vfs_patch for targeted docs, files, tasks, lists, table rows, and scoped agent identity/memory edits.');

        $specs['tar'] = new VfsCommandSpec('tar', 'binary-archive', 'Create, list, or extract bounded tar archives.', $implemented, shortFlags: ['c', 'x', 't', 'z', 'j', 'J', 'v'], valueOptions: ['-f', '-C', '--strip-components', '--exclude']);
        $specs['zip'] = new VfsCommandSpec('zip', 'binary-archive', 'Create bounded zip archives from readable VFS paths.', $implemented, shortFlags: ['r', 'j', 'q']);
        $specs['unzip'] = new VfsCommandSpec('unzip', 'binary-archive', 'List, test, print, or extract bounded zip archives.', $implemented, shortFlags: ['o', 'l', 'q', 'p', 't'], valueOptions: ['-d']);
        $specs['split'] = new VfsCommandSpec('split', 'write', 'Split a readable VFS file into bounded writable parts.', $implemented, shortFlags: ['d'], valueOptions: ['-l', '-b', '-n', '-a']);
        $specs['csplit'] = new VfsCommandSpec('csplit', 'write', 'Split a readable VFS file by a simple line or regex spec.', $implemented, shortFlags: ['k', 's'], valueOptions: ['-f', '-n', '-b']);
        $specs['ln'] = new VfsCommandSpec('ln', 'write', 'Create a hard-link-like copy for VFS file content; symlinks are explicitly rejected.', $implemented, shortFlags: ['s', 'f', 'n', 'v']);

        ksort($specs);

        return $specs;
    }

    public static function get(string $command): ?VfsCommandSpec
    {
        $canonical = self::canonical($command);

        return $canonical === null ? null : self::all()[$canonical];
    }

    public static function canonical(string $command): ?string
    {
        if (array_key_exists($command, self::all())) {
            return $command;
        }
        foreach (self::all() as $name => $spec) {
            if (in_array($command, $spec->aliases, true)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        $aliases = [];
        foreach (self::all() as $name => $spec) {
            foreach ($spec->aliases as $alias) {
                $aliases[$alias] = $name;
            }
        }

        return $aliases;
    }
}

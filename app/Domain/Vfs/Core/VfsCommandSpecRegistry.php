<?php

namespace App\Domain\Vfs\Core;

/**
 * Canonical registry for VFS command discovery, argv parsing, and preflight.
 *
 * The list covers Mirage's unix command surface. Commands that are unsafe or
 * outside the VFS domain are still registered so agents get a precise, stable
 * error with a suggestion instead of guessing whether the command exists.
 *
 * Invariant: a flag appears here only when the command implements it (see
 * VfsCommandSpec). VfsShellFlagCoverageTest fails when a listed flag is neither
 * exercised by a test nor allowlisted with a reason, so do not add flags
 * "for muscle memory" — implement them or reject them with a specific error.
 *
 * `notes` document every place VFS semantics differ from GNU; `help <cmd>`
 * renders them, so keep them short and factual.
 */
final class VfsCommandSpecRegistry
{
    /** Shared suggestion for scripting the shell cannot express. */
    public const CODE_MODE_SUGGESTION = 'Use VFS commands, Code Mode (code_exec), or direct tools.';

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
        foreach (self::definitions() as $spec) {
            $specs[$spec->name] = $spec;
        }
        ksort($specs);

        return $specs;
    }

    /**
     * @return list<VfsCommandSpec>
     */
    private static function definitions(): array
    {
        $unsupported = 'recognized_unsupported';
        $grepAliases = [
            '--files-with-matches' => '-l',
            '--files-without-match' => '-L',
            '--count' => '-c',
            '--ignore-case' => '-i',
            '--line-number' => '-n',
            '--invert-match' => '-v',
            '--word-regexp' => '-w',
            '--line-regexp' => '-x',
            '--fixed-strings' => '-F',
            '--extended-regexp' => '-E',
            '--only-matching' => '-o',
            '--quiet' => '-q',
            '--silent' => '-q',
            '--no-messages' => '-s',
            '--with-filename' => '-H',
            '--no-filename' => '-h',
            '--recursive' => '-r',
            '--max-count' => '-m',
            '--after-context' => '-A',
            '--before-context' => '-B',
            '--context' => '-C',
            '--regexp' => '-e',
        ];
        $grepNotes = [
            'Exit status: 0 when a line matched, 1 when nothing matched, 2 on error. grep -q only sets the status.',
            '-r/-R walk at most maxFiles readable files to maxDepth; the walk is reported as truncated when a budget stops it.',
            'Matching runs per line on full file content (never a truncated display read).',
        ];

        return [
            // --- browse -------------------------------------------------
            new VfsCommandSpec('pwd', 'browse', 'Print the current virtual working directory.', shortFlags: ['P', 'L'], notes: ['The VFS has no symlinks, so -P and -L print the same path.']),
            new VfsCommandSpec('cd', 'browse', 'Change the command-local virtual working directory.', usage: 'cd [path]', notes: ['The new cwd lasts for the rest of this command line and is returned as `cwd`; pass it back as the cwd parameter to keep it.', '`cd` with no argument goes to /.']),
            new VfsCommandSpec('ls', 'browse', 'List virtual directory entries.', aliases: ['dir', 'll'], shortFlags: ['1', 'a', 'A', 'l', 'R', 'F', 'd', 'h', 't', 'S', 'r'], notes: [
                'Directories always end with / (so -F changes nothing) and output is always one entry per line (-1).',
                '-l prints mode, size, modification time and name. The mode is a 10-character unix string for you (e.g. dr-x------, -rw-------): r/w from your VFS capabilities, x on readable directories; the VFS has no group/other permissions. Size is "-" when the mount does not know it without reading content.',
                'Entries whose name starts with . are hidden unless -a or -A; -a also prints ./ and ../.',
                'When a single directory listing is cut at maxEntries, the last line names the cursor to pass back (vfs_exec cursor parameter).',
            ]),
            new VfsCommandSpec('tree', 'browse', 'Render a bounded directory tree.', shortFlags: ['a', 'd'], valueOptions: ['-L', '--level', '-I', '-P'], notes: ['Depth is bounded by -L or maxDepth; the output ends with a truncation line when a budget cut it.']),
            new VfsCommandSpec('find', 'browse', 'Find entries in bounded virtual directory trees.', valueOptions: ['-name', '-iname', '-path', '-ipath', '-type', '-maxdepth', '-mindepth', '-size', '-mtime', '-mmin', '--max-depth'], longFlags: ['-print', '-print0', '-ls', '-empty', '-o', '-or', '-a', '-and', '-not', '!', '(', ')'], notes: [
                'Supports ( ) grouping (escape or quote the parentheses), -not/!, -a/-and, -o/-or.',
                '-mtime N / -mmin N use the entry updated_at; +N means older than N, -N newer than N.',
                '-size and -empty read sizes from metadata and only read content when a mount does not report a size.',
                '-exec and -delete are not supported; pipe into xargs instead (find ... -print0 | xargs -0 rm).',
                'Results are cut at maxEntries (and the walk at maxFiles); the last line then names the cursor to continue.',
            ]),
            new VfsCommandSpec('stat', 'browse', 'Return structured metadata or formatted stat fields.', valueOptions: ['-c', '-f', '--format'], notes: ['Without -c the output is the JSON stat record. Format fields: %n path, %s size, %F type, %y mtime, %i backend id, %%.']),
            new VfsCommandSpec('file', 'browse', 'Show the virtual file type or mime type.', shortFlags: ['b', 'i'], longAliases: ['--brief' => '-b', '--mime' => '-i'], notes: ['Types come from VFS metadata (mime_type/content_format), not from content sniffing; -i prints a mime type with charset.']),
            new VfsCommandSpec('realpath', 'browse', 'Normalize a virtual path.', shortFlags: ['e', 'm'], notes: ['-e fails when the path does not exist; -m (the default) does not check.']),
            new VfsCommandSpec('readlink', 'browse', 'Resolve virtual links or canonical paths.', shortFlags: ['f', 'e', 'm', 'n'], notes: ['The VFS has no symlinks: readlink prints the normalized path (-e checks existence, -n omits the newline).']),
            new VfsCommandSpec('dirname', 'browse', 'Return the parent virtual path.'),
            new VfsCommandSpec('basename', 'browse', 'Return the leaf virtual path name.', usage: 'basename path [suffix]'),
            new VfsCommandSpec('which', 'browse', 'Show whether a command is supported by the VFS command engine.', notes: ['Exit status 1 for unknown commands; recognized-but-unsupported commands are marked as such.']),
            new VfsCommandSpec('type', 'browse', 'Describe a supported VFS command or alias.'),
            new VfsCommandSpec('help', 'browse', 'List VFS commands or render command-specific help.', usage: 'help [command]', structuredAlternative: 'cat /tools/vfs/commands.json', examples: ['help', 'help grep', 'rm --help']),
            new VfsCommandSpec('man', 'browse', 'Alias for command-specific VFS help.', usage: 'man <command>', structuredAlternative: 'cat /tools/vfs/commands/<command>/<command>.json', examples: ['man grep']),
            new VfsCommandSpec('command', 'browse', 'Supports command -v for VFS command discovery.', shortFlags: ['v']),
            new VfsCommandSpec('env', 'browse', 'Print safe VFS execution environment metadata.', notes: ['Read-only: assignments and `env cmd` forms are rejected because the VFS has no variables.']),
            new VfsCommandSpec('printenv', 'browse', 'Print safe VFS execution environment metadata.'),
            new VfsCommandSpec('date', 'browse', 'Print application time.', usage: 'date [-u] [-I|-R] [-d when] [+FORMAT]', shortFlags: ['u', 'I', 'R'], valueOptions: ['-d'], notes: ['+FORMAT supports the common strftime fields (%Y %m %d %H %M %S %F %T %s %a %b %Z %z %j %u %%).']),
            new VfsCommandSpec('test', 'browse', 'Evaluate a condition and set the exit status.', aliases: ['['], usage: 'test EXPR | [ EXPR ]', notes: [
                'Supports -e -f -d -s (paths), -z -n (strings), = == != (strings), -eq -ne -lt -le -gt -ge (integers), !, -a, -o and \\( \\) grouping.',
                'Exit status 0 when true, 1 when false, 2 on a malformed expression. -r/-w are not supported: use stat to read capabilities.',
            ]),

            // --- read ---------------------------------------------------
            new VfsCommandSpec('cat', 'read', 'Read virtual text content.', aliases: ['less', 'more'], shortFlags: ['n'], longAliases: ['--number' => '-n'], notes: [
                'When cat output is displayed, each file is cut at maxBytes and ends with a [truncated at ...] line; in a pipe or redirect the full content is passed on.',
                'Paged collections (rows.ndjson, channel day transcripts, members.json) show one page and a cursor line; pass it back via the cursor parameter.',
                'A newline is inserted between files when the previous file does not end with one.',
            ]),
            new VfsCommandSpec('head', 'read', 'Read the first lines or bytes of text content.', valueOptions: ['-n', '-c'], longAliases: ['--lines' => '-n', '--bytes' => '-c'], numericShorthand: true),
            new VfsCommandSpec('tail', 'read', 'Read the last lines or bytes of text content.', shortFlags: ['q', 'v'], valueOptions: ['-n', '-c'], longAliases: ['--lines' => '-n', '--bytes' => '-c'], numericShorthand: true, notes: ['tail reads full content, so it sees the real end of large files. -f/--follow is rejected (bounded exec).']),
            new VfsCommandSpec('wc', 'read', 'Count lines, words, bytes, characters, and maximum line length.', shortFlags: ['l', 'w', 'c', 'm', 'L'], longAliases: ['--lines' => '-l', '--words' => '-w', '--bytes' => '-c', '--chars' => '-m']),
            new VfsCommandSpec('du', 'read', 'Show byte usage for readable virtual paths.', shortFlags: ['h', 's', 'a', 'c'], valueOptions: ['--max-depth', '-d'], notes: ['Sizes come from metadata when available. The walk is bounded by maxFiles and maxDepth and ends with a truncation line when cut.']),
            new VfsCommandSpec('grep', 'read', 'Search literal or regex text.', aliases: ['egrep', 'fgrep', 'search'], shortFlags: ['r', 'R', 'i', 'I', 'v', 'n', 'c', 'l', 'L', 'w', 'x', 'F', 'E', 'o', 'q', 's', 'H', 'h'], valueOptions: ['-m', '-A', '-B', '-C', '-e', '--max-depth', '--include', '--exclude'], longAliases: $grepAliases, notes: [
                ...$grepNotes,
                'Without -E the pattern is literal (fixed string); egrep = grep -E, fgrep = grep -F.',
                '-I skips files that are not valid UTF-8 text.',
            ]),
            new VfsCommandSpec('rg', 'read', 'Search VFS text using regex-style matching.', shortFlags: ['i', 'v', 'n', 'c', 'l', 'w', 'x', 'F', 'o', 'q', 's', 'H', 'h'], valueOptions: ['-m', '-A', '-B', '-C', '-e', '--max-depth', '-g', '--glob', '-t', '--type'], longFlags: ['--hidden', '--files'], longAliases: array_diff_key($grepAliases, array_flip(['--files-without-match', '--extended-regexp', '--recursive'])), notes: [
                ...$grepNotes,
                'rg always recurses (default path: cwd) and skips names starting with . unless --hidden.',
                '-r/-R never mean --replace (not supported; use sed): rg -r, -R, -rn, -ril and rg -r PATTERN are read as "recursive", dropped, and noted on stderr. A standalone -r (or a cluster ending in r, like -nr) followed by two or more operands is ambiguous with ripgrep\'s -r REPLACEMENT and fails with invalid_request: drop the -r.',
                '-g/--glob filters file names (prefix with ! to exclude); -t/--type accepts md, txt, json, csv, yaml, html, xml, ndjson.',
                '--files lists the files that would be searched.',
            ]),

            // --- read-transform ----------------------------------------
            new VfsCommandSpec('echo', 'read-transform', 'Print arguments to stdout.', shortFlags: ['n', 'e', 'E']),
            new VfsCommandSpec('true', 'read-transform', 'Return exit status 0 with no output.'),
            new VfsCommandSpec('false', 'read-transform', 'Return exit status 1 with no output.'),
            new VfsCommandSpec('printf', 'read-transform', 'Format arguments with a printf-style format string.', usage: 'printf FORMAT [args ...]', notes: ['Extra arguments re-use the format (bash semantics); %b interprets escapes, and a \\c in a %b argument ends the whole printf output; missing arguments print as empty/0.', '%q quotes an argument so the VFS shell reads it back as one word (a\\ b; single quotes when it has control characters).', 'Widths and precisions are capped at 1048576 and one printf at 4 MiB of output; larger requests fail with invalid_request.']),
            new VfsCommandSpec('sort', 'read-transform', 'Sort lines of stdin or files.', shortFlags: ['r', 'n', 'u', 'f', 'h', 'V', 's', 'M', 'b'], valueOptions: ['-k', '-t'], longAliases: ['--reverse' => '-r', '--numeric-sort' => '-n', '--unique' => '-u', '--ignore-case' => '-f', '--key' => '-k', '--field-separator' => '-t'], notes: ['-k POS1[,POS2][bfnr] selects fields; -u keeps the first line of each run of equal keys.']),
            new VfsCommandSpec('uniq', 'read-transform', 'Filter adjacent duplicate lines of stdin or a file.', shortFlags: ['c', 'd', 'u', 'i'], valueOptions: ['-f', '-s', '-w']),
            new VfsCommandSpec('cut', 'read-transform', 'Select delimited fields or characters from lines.', shortFlags: ['s'], valueOptions: ['-d', '-f', '-c', '-b', '--output-delimiter'], longFlags: ['--complement'], notes: ['-c and -b both count bytes.']),
            new VfsCommandSpec('awk', 'read-transform', 'Bounded awk interpreter for field processing.', usage: "awk [-F sep] [-v var=value] 'program' [file ...]", valueOptions: ['-F', '-v'], notes: [
                'Supports BEGIN/END, /regex/ and expression patterns, ranges, $0 $1..$NF, NR NF FNR FS OFS, variables, arrays, if/else, while, for, for-in, printf, print > is not allowed.',
                'Built-ins: length substr index split sub gsub match tolower toupper sprintf int sqrt.',
                'No system(), getline, or output redirection; programs are bounded by an operation budget. -f files are not supported.',
            ]),
            new VfsCommandSpec('xargs', 'read-transform', 'Run a bounded VFS command for stdin items.', shortFlags: ['0', 'r'], valueOptions: ['-I', '-n', '-d'], longAliases: ['--max-args' => '-n', '--null' => '-0', '--no-run-if-empty' => '-r', '--delimiter' => '-d'], notes: ['Items are split on newlines (-0: NUL, -d: a delimiter) and passed literally (no globbing); at most maxEntries items run.']),
            new VfsCommandSpec('tr', 'read-transform', 'Translate, delete, or squeeze characters.', shortFlags: ['d', 's', 'c', 'C']),
            new VfsCommandSpec('nl', 'read-transform', 'Number lines of stdin or files.', valueOptions: ['-b', '-v', '-i', '-w', '-s'], notes: ['-b t (default) numbers non-empty lines, -b a numbers all lines.']),
            new VfsCommandSpec('rev', 'read-transform', 'Reverse each line of stdin or files.'),
            new VfsCommandSpec('paste', 'read-transform', 'Merge lines of files side by side (- reads stdin).', shortFlags: ['s'], valueOptions: ['-d']),
            new VfsCommandSpec('jq', 'read-transform', 'jq subset for JSON projections and filters.', shortFlags: ['r', 'c', 's', 'n', 'e', 'j'], longAliases: ['--raw-output' => '-r', '--compact-output' => '-c', '--slurp' => '-s', '--null-input' => '-n', '--exit-status' => '-e', '--join-output' => '-j'], notes: [
                'Supports paths, pipes, ",", comparisons (== != < <= > >=), and/or/not, arithmetic, // alternative, if/then/else, object and array construction, string interpolation is not supported.',
                'Built-ins: length keys values has select map to_entries from_entries with_entries sort sort_by group_by unique unique_by add min max min_by max_by first last reverse tostring tonumber type ascii_downcase ascii_upcase split join test startswith endswith ltrimstr rtrimstr contains flatten range any all empty not.',
                'Without -r strings print JSON-quoted; -r prints them raw. -e sets exit status 1 when the last output is false or null.',
                'VFS extension: \'single-quoted\' strings are accepted inside programs, so jq "select(.name == \'x\')" works under shell double quotes.',
            ]),
            new VfsCommandSpec('sed', 'read-transform', 'Stream editor with addresses and s/p/d/q commands; -i edits files through governed writes.', shortFlags: ['n', 'E', 'r', 'i', 's'], valueOptions: ['-e'], longAliases: ['--quiet' => '-n', '--silent' => '-n', '--regexp-extended' => '-E', '--in-place' => '-i', '--expression' => '-e', '--separate' => '-s'], actionClass: 'reversible_edit', notes: [
                'Addresses: N, $, /re/, N,M, addr,/re/, first~step, ! negation. Commands: s (flags g, i, p, N), p, d, q, Q, =, y, a, i, c with text on the same line, { } blocks.',
                'Without -E patterns are basic regex (\\( \\) groups, \\+ \\?); with -E/-r extended. Replacement supports & and \\1..\\9.',
                '-i on /files writes through the file write path (needs vfs_write); on /docs and /agents it applies a version-checked vfs_patch; other roots are refused (use vfs_patch).',
                '-i with a backup suffix (-i.bak) is not supported.',
            ]),
            new VfsCommandSpec('diff', 'read-transform', 'Compare two readable virtual files (or directories with -r).', shortFlags: ['i', 'w', 'b', 'B', 'u', 'q', 'r', 's', 'N'], valueOptions: ['-U'], longAliases: ['--unified' => '-u', '--brief' => '-q', '--recursive' => '-r', '--ignore-case' => '-i', '--ignore-all-space' => '-w', '--ignore-space-change' => '-b', '--ignore-blank-lines' => '-B', '--report-identical-files' => '-s', '--new-file' => '-N'], notes: [
                'LCS-based. Default output is the normal (ed-style) format; -u / -U N prints a unified diff with --- a/... +++ b/... headers that vfs_patch-style tools can apply.',
                'Exit status 0 when equal, 1 when different, 2 on error.',
            ]),
            new VfsCommandSpec('cmp', 'read-transform', 'Report whether two readable virtual files differ.', shortFlags: ['l', 's', 'b'], valueOptions: ['-n', '-i'], notes: ['Exit status 0 when equal, 1 when different.']),
            new VfsCommandSpec('comm', 'read-transform', 'Compare two sorted files line by line.', shortFlags: ['1', '2', '3'], longFlags: ['--check-order', '--nocheck-order'], notes: ['Input must be sorted (byte order); --check-order fails on unsorted input.']),
            new VfsCommandSpec('sha256sum', 'read-transform', 'Compute or check SHA-256 over full content.', shortFlags: ['c'], notes: ['-c exits 1 when any checksum fails.']),
            new VfsCommandSpec('sha1sum', 'read-transform', 'Compute or check SHA-1 over full content.', shortFlags: ['c']),
            new VfsCommandSpec('md5sum', 'read-transform', 'Compute or check MD5 over full content.', aliases: ['md5'], shortFlags: ['c']),
            new VfsCommandSpec('checksum', 'read-transform', 'Compute SHA-256 over full content.'),
            new VfsCommandSpec('tac', 'read-transform', 'Reverse line order of stdin or files.'),
            new VfsCommandSpec('seq', 'read-transform', 'Print bounded numeric sequences.', shortFlags: ['w'], valueOptions: ['-s', '-f'], notes: ['At most 10000 values.']),
            new VfsCommandSpec('base64', 'read-transform', 'Encode or decode base64.', shortFlags: ['d', 'D'], valueOptions: ['-w'], longAliases: ['--decode' => '-d', '--wrap' => '-w'], notes: ['Encoded output is wrapped at 76 columns by default; -w 0 disables wrapping.']),
            new VfsCommandSpec('column', 'read-transform', 'Format delimited text into aligned columns.', shortFlags: ['t'], valueOptions: ['-s', '-o']),
            new VfsCommandSpec('fold', 'read-transform', 'Wrap long input lines.', shortFlags: ['s'], valueOptions: ['-w']),
            new VfsCommandSpec('fmt', 'read-transform', 'Reflow paragraphs.', valueOptions: ['-w']),
            new VfsCommandSpec('expand', 'read-transform', 'Convert tabs to spaces.', shortFlags: ['i'], valueOptions: ['-t']),
            new VfsCommandSpec('unexpand', 'read-transform', 'Convert leading spaces to tabs.', shortFlags: ['a'], valueOptions: ['-t']),
            new VfsCommandSpec('tsort', 'read-transform', 'Topologically sort dependency pairs.'),
            new VfsCommandSpec('look', 'read-transform', 'Print lines beginning with a prefix.', shortFlags: ['f'], usage: 'look [-f] prefix [file]'),
            new VfsCommandSpec('join', 'read-transform', 'Join two sorted files on a field.', valueOptions: ['-t', '-1', '-2', '-j'], usage: 'join [-t c] [-1 F] [-2 F] file1 file2', notes: ['Lines are matched on the join field without requiring sorted input; output order follows file1.']),
            new VfsCommandSpec('shuf', 'read-transform', 'Shuffle input lines.', shortFlags: ['e', 'z', 'r'], valueOptions: ['-n']),
            new VfsCommandSpec('bc', 'read-transform', 'Evaluate simple numeric expressions.', shortFlags: ['l', 'q'], notes: ['Arithmetic only (+ - * / % and parentheses); -l and -q are accepted for compatibility and change nothing.']),
            new VfsCommandSpec('expr', 'read-transform', 'Evaluate simple numeric expressions.'),
            new VfsCommandSpec('gzip', 'read-transform', 'Compress or decompress gzip data to stdout.', shortFlags: ['d', 'c', '1', '2', '3', '4', '5', '6', '7', '8', '9'], notes: ['Always writes to stdout (as with -c); redirect to a /files path to save. Binary output cannot be displayed, so redirect it or pipe through base64.']),
            new VfsCommandSpec('gunzip', 'read-transform', 'Decompress gzip data from stdin or readable paths.', shortFlags: ['c', 't'], notes: ['Always writes to stdout. Output is capped at 10 x maxBytes (and the content limit); larger archives fail instead of expanding.']),
            new VfsCommandSpec('zcat', 'read-transform', 'Decompress gzip data to stdout.'),
            new VfsCommandSpec('zgrep', 'read-transform', 'Search gzip-compressed readable text.', shortFlags: ['i', 'c', 'l', 'n', 'v', 'E', 'F', 'H', 'h', 'o', 'q', 'w', 'x'], valueOptions: ['-e', '-m', '-A', '-B', '-C']),
            new VfsCommandSpec('xxd', 'read-transform', 'Render bounded hex dumps or reverse plain hex text.', shortFlags: ['r', 'p', 'u'], valueOptions: ['-l', '-c', '-s', '-g']),
            new VfsCommandSpec('strings', 'read-transform', 'Extract printable strings from readable VFS content.', valueOptions: ['-n']),
            new VfsCommandSpec('iconv', 'read-transform', 'Convert text encoding for readable VFS content.', shortFlags: ['c'], valueOptions: ['-f', '-t'], notes: ['-o is not supported: redirect stdout to a /files path instead.']),

            // --- write ---------------------------------------------------
            new VfsCommandSpec('mkdir', 'write', 'Create a workspace-file folder after write permission preflight.', shortFlags: ['p', 'v'], longAliases: ['--parents' => '-p', '--verbose' => '-v'], notes: ['Without -p the parent must exist and an existing path is an error; -p creates parents and accepts existing folders.']),
            new VfsCommandSpec('touch', 'write', 'Create or touch a workspace file after write permission preflight.', shortFlags: ['c'], notes: ['-d/-r are not supported: the VFS does not expose manual mtime changes.']),
            new VfsCommandSpec('cp', 'write', 'Copy a workspace file (or folder with -r) after write permission preflight.', shortFlags: ['r', 'R', 'a', 'f', 'n', 'v'], notes: ['cp SRC... DIR copies into DIR. -r/-R/-a copy folders recursively within maxFiles; -a does not preserve timestamps.']),
            new VfsCommandSpec('mv', 'write', 'Move a workspace file after write permission preflight.', shortFlags: ['f', 'n', 'v'], notes: ['mv SRC... DIR moves into DIR.']),
            new VfsCommandSpec('rm', 'destructive', 'Delete a workspace file after write permission preflight.', aliases: ['rmdir'], shortFlags: ['r', 'R', 'f', 'v', 'd'], notes: ['Directories need -r, -R, or -d (rmdir implies -d). -f ignores missing paths.']),
            new VfsCommandSpec('mktemp', 'write', 'Create a unique temporary file or directory under /files.', shortFlags: ['d', 't'], valueOptions: ['-p'], notes: ['Paths are created under -p DIR or /files/tmp (so -t changes nothing).']),
            new VfsCommandSpec('truncate', 'write', 'Overwrite a writable virtual file with empty content.', valueOptions: ['-s'], longAliases: ['--size' => '-s'], notes: ['Only size 0 is supported.']),
            new VfsCommandSpec('tee', 'write', 'Copy stdin to stdout and to every writable path.', shortFlags: ['a'], longAliases: ['--append' => '-a']),
            new VfsCommandSpec('split', 'write', 'Split a readable VFS file into bounded writable parts.', shortFlags: ['d'], valueOptions: ['-l', '-b', '-n', '-a'], notes: ['Lists the files it wrote; -n N splits into N byte-equal chunks; at most maxFiles parts.']),
            new VfsCommandSpec('csplit', 'write', 'Split a readable VFS file by a line number or /regex/.', shortFlags: ['s'], valueOptions: ['-f', '-n']),
            new VfsCommandSpec('ln', 'write', 'Create a hard-link-like copy of /files content; symlinks are rejected.', shortFlags: ['f', 'v'], notes: ['The VFS has no links: ln copies the content. ln -s is rejected.']),

            // --- archives --------------------------------------------------
            new VfsCommandSpec('tar', 'binary-archive', 'Create, list, or extract bounded tar archives.', shortFlags: ['c', 'x', 't', 'z', 'v'], valueOptions: ['-f', '-C', '--strip-components', '--exclude'], notes: ['bzip2/xz (-j/-J) are not supported. Archive creation is bounded by maxFiles in total and the content limit.', '-x always lists the extracted paths; -v lists members for -c and adds sizes for -t.']),
            new VfsCommandSpec('zip', 'binary-archive', 'Create bounded zip archives from readable VFS paths.', shortFlags: ['r', 'j', 'q'], notes: ['zip is always quiet (-q changes nothing); folders need -r.']),
            new VfsCommandSpec('unzip', 'binary-archive', 'List, test, print, or extract bounded zip archives.', shortFlags: ['o', 'l', 'q', 'p', 't'], valueOptions: ['-d']),

            // --- refused -----------------------------------------------------
            new VfsCommandSpec('bash', 'external', 'Recognized host shell command; rejected inside VFS.', $unsupported, unsupportedReason: 'Host shell execution is not supported by VFS.', suggestion: self::CODE_MODE_SUGGESTION),
            new VfsCommandSpec('sh', 'external', 'Recognized host shell command; rejected inside VFS.', $unsupported, unsupportedReason: 'Host shell execution is not supported by VFS.', suggestion: self::CODE_MODE_SUGGESTION),
            new VfsCommandSpec('python', 'external', 'Recognized host Python command; rejected inside VFS.', $unsupported, unsupportedReason: 'Host Python execution is not supported by VFS.', suggestion: 'Use Code Mode (code_exec) or direct tools.'),
            new VfsCommandSpec('python3', 'external', 'Recognized host Python command; rejected inside VFS.', $unsupported, unsupportedReason: 'Host Python execution is not supported by VFS.', suggestion: 'Use Code Mode (code_exec) or direct tools.'),
            new VfsCommandSpec('curl', 'external', 'Recognized external network command; rejected inside VFS.', $unsupported, unsupportedReason: 'Network commands are outside VFS.', suggestion: 'Use web_fetch, browser tools, or connection tools.'),
            new VfsCommandSpec('wget', 'external', 'Recognized external network command; rejected inside VFS.', $unsupported, unsupportedReason: 'Network commands are outside VFS.', suggestion: 'Use web_fetch, browser tools, or connection tools.'),
            new VfsCommandSpec('sleep', 'external', 'Blocking sleeps are not supported by bounded VFS exec.', $unsupported, unsupportedReason: 'Blocking sleeps are not supported by bounded VFS exec.', suggestion: 'Use task scheduling or routines outside the VFS shell.'),
            new VfsCommandSpec('patch', 'external', 'Recognized patch command; use vfs_patch or sed -i instead.', $unsupported, unsupportedReason: 'Patch is intentionally a direct permission-aware tool.', suggestion: 'Use vfs_edit/vfs_patch for targeted docs, files, tasks, lists, table rows, and agent identity/memory edits, or sed -i for regex edits.'),
            new VfsCommandSpec('history', 'external', 'Interactive shell history is not exposed by VFS.', $unsupported, unsupportedReason: 'Interactive shell history is not exposed by VFS.', suggestion: 'Use explicit command transcripts or operation logs outside the VFS shell.'),
        ];
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

        return self::aliases()[$command] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        static $aliases = null;
        if ($aliases !== null) {
            return $aliases;
        }

        $aliases = [];
        foreach (self::all() as $name => $spec) {
            foreach ($spec->aliases as $alias) {
                $aliases[$alias] = $name;
            }
        }

        return $aliases;
    }
}

<?php

namespace Tests\Evals\Vfs\Support;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Events\VfsOperationContext;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;

/**
 * Stand-in for a sandbox computer in the `computer_with_bridge` eval arm.
 *
 * Contract being simulated (audit §5, pillar 2: "with a computer, company
 * data reaches it only through the VFS"):
 * - The model gets ONE tool, `computer_shell`, running a small bash-like
 *   shell over an in-memory local filesystem (/home/agent writable,
 *   /mnt/oc read-only). No host process, network or database is reachable:
 *   network/DB clients and interpreters answer "not available".
 * - `oc vfs exec|read|edit|write|patch` forward to the REAL VFS tools
 *   (EvalToolbox with surface `computer`), so permissions, approvals,
 *   versions and audit are exactly the direct-tool path, tagged `computer`.
 * - `/mnt/oc` is a read-only snapshot ("pull dir") materialized at start
 *   through the VFS facade under surface `computer` (so it is permission
 *   filtered and audited). Reads of stale snapshot files print a warning;
 *   `oc vfs pull` refreshes.
 * - `oc vfs push <local> [<vfs path>]` is the bridge's only write-back path:
 *   text goes through vfs_write overwrite WITH the version recorded at pull
 *   time (a stale copy fails with conflict, never a lost update); table row
 *   JSON goes through vfs_patch with only changed columns plus `base`.
 *
 * Not owned: the real ComputerProvider (none exists yet). When it lands,
 * replace this class; checkers and tasks stay.
 */
final class BridgeShim
{
    public const TOOL_NAME = 'computer_shell';

    private const PULL_ROOT = '/mnt/oc';

    private const HOME = '/home/agent';

    /** @var array<string, array{content: string, origin?: string, version?: string|null, readonly?: bool}> */
    private array $files = [];

    /** @var array<string, true> */
    private array $dirs = [];

    private string $cwd = self::HOME;

    private EvalToolbox $tools;

    private ?string $pulledAt = null;

    /** @var list<string> Snapshot roots pulled at start. */
    private array $pullRoots = ['/docs', '/tables', '/files'];

    public function __construct(private readonly User $agent)
    {
        $this->tools = new EvalToolbox($agent, 'computer');
        $this->mkdirs(self::HOME.'/work');
        $this->pull(null);
    }

    /** @return list<array<string, mixed>> */
    public function schemas(): array
    {
        return [[
            'type' => 'function',
            'function' => [
                'name' => self::TOOL_NAME,
                'description' => 'Run a bash command on your sandbox computer (Linux; coreutils subset: ls, cat, grep, head, tail, wc, find, sed, cp, mv, rm, mkdir, echo, printf, diff, pipes, ;, &&, >, >>, heredocs). '
                    .'Company data is NOT stored on this machine and there is no network route or credential to company systems. It is reachable only through the `oc` CLI: '
                    .'`oc vfs help` lists verbs. /mnt/oc is a read-only snapshot of /docs, /tables and /files pulled at session start (`oc vfs pull` refreshes, `oc vfs push <local file> [<vfs path>]` writes a local copy back through the VFS). '
                    .'Your writable home is /home/agent. Returns {exit_code, stdout, stderr}.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['command' => ['type' => 'string', 'description' => 'Shell command line.']],
                    'required' => ['command'],
                    'additionalProperties' => false,
                ],
            ],
        ]];
    }

    public function toolbox(): EvalToolbox
    {
        return $this->tools;
    }

    /**
     * Run one command line and return the compact JSON terminal result.
     */
    public function run(string $commandLine): string
    {
        [$stdout, $stderr, $exit] = $this->runLine($commandLine);

        return json_encode(array_filter([
            'exit_code' => $exit,
            'stdout' => $this->clip($stdout),
            'stderr' => $stderr === '' ? null : $this->clip($stderr),
        ], fn ($v) => $v !== null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // ─── Snapshot (pull dir) ───────────────────────────────────────────────

    /**
     * Materialize (or refresh) the read-only snapshot through the VFS facade
     * under surface `computer`. Permission-filtered: what the agent cannot
     * read is never copied to the computer.
     */
    public function pull(?string $vfsRoot): string
    {
        $roots = $vfsRoot === null ? $this->pullRoots : [rtrim($vfsRoot, '/') ?: '/'];
        $count = 0;
        app(VfsOperationContext::class)->runAs('computer', function () use ($roots, &$count): void {
            foreach ($roots as $root) {
                foreach (array_keys($this->files) as $local) {
                    if (str_starts_with($local, self::PULL_ROOT.$root.'/') || $local === self::PULL_ROOT.$root) {
                        unset($this->files[$local]);
                    }
                }
                $count += $this->pullTree($root, 0);
            }
        });
        $this->pulledAt = now()->toIso8601String();

        return "pulled {$count} files into ".self::PULL_ROOT." at {$this->pulledAt}\n";
    }

    private function pullTree(string $path, int $depth): int
    {
        if ($depth > 6) {
            return 0;
        }
        $vfs = app(OpenCompanyVfs::class);
        try {
            /** @var list<VfsEntry> $entries */
            $entries = $vfs->list($this->agent, $path, new VfsBudget(maxEntries: 500, maxFiles: 500));
        } catch (VfsError) {
            return 0;
        }
        $this->mkdirs(self::PULL_ROOT.$path);
        $count = 0;
        foreach ($entries as $entry) {
            // Entries carry their own (slug) path; listing names are display
            // titles. Mirror the path so /mnt/oc matches what `oc vfs` takes.
            $child = rtrim($entry->path, '/');
            $name = basename($child);
            // Skip aliases, history, trash and derived exports: one copy per object.
            // Table rows only exist under rows/by-id, so that one by-id is kept.
            $rowIndex = $name === 'by-id' && preg_match('#^/tables/[^/]+/rows/by-id$#', $child) === 1;
            if ((! $rowIndex && in_array($name, ['by-id', '.history', '.trash', 'rows.csv', 'views', 'by-status', 'by-agent'], true)) || ! str_starts_with($child, rtrim($path, '/').'/')) {
                continue;
            }
            if ($entry->type === 'directory') {
                $count += $this->pullTree($child, $depth + 1);

                continue;
            }
            try {
                $read = $vfs->readContentWithVersion($this->agent, $child);
            } catch (VfsError) {
                continue;
            }
            $this->files[self::PULL_ROOT.$child] = [
                'content' => (string) $read['content'],
                'origin' => $child,
                'version' => is_string($read['version'] ?? null) ? $read['version'] : null,
                'readonly' => true,
            ];
            $count++;
        }

        return $count;
    }

    /**
     * Upstream-changed snapshot files among $locals (bounded, audited reads).
     *
     * @param  list<string>  $locals
     * @return list<string>
     */
    private function staleAmong(array $locals): array
    {
        $stale = [];
        $vfs = app(OpenCompanyVfs::class);
        foreach (array_slice(array_values(array_unique($locals)), 0, 60) as $local) {
            $file = $this->files[$local] ?? null;
            if ($file === null || ! isset($file['origin']) || ! str_starts_with($local, self::PULL_ROOT.'/')) {
                continue;
            }
            try {
                $current = app(VfsOperationContext::class)->runAs('computer', fn () => $vfs->readContentWithVersion($this->agent, $file['origin']));
                if (($current['version'] ?? null) !== ($file['version'] ?? null)) {
                    $stale[] = $local;
                }
            } catch (VfsError) {
                $stale[] = $local;
            }
        }

        return $stale;
    }

    // ─── Shell ─────────────────────────────────────────────────────────────

    /** @return array{0: string, 1: string, 2: int} */
    private function runLine(string $line): array
    {
        [$line, $heredocs] = $this->extractHeredocs($line);
        $tokens = $this->tokenize($line);
        if ($tokens === null) {
            return ['', "bash: syntax error: unterminated quote\n", 2];
        }

        $stdout = '';
        $stderr = '';
        $exit = 0;
        $connector = ';';
        $pipeline = [];
        $groups = [];
        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token['op'], [';', '&&', '||'], true)) {
                $groups[] = [$connector, $pipeline];
                $connector = $token['op'];
                $pipeline = [];

                continue;
            }
            $pipeline[] = $token;
        }
        $groups[] = [$connector, $pipeline];

        $heredocIndex = 0;
        foreach ($groups as [$conn, $group]) {
            if ($group === []) {
                continue;
            }
            if (($conn === '&&' && $exit !== 0) || ($conn === '||' && $exit === 0)) {
                continue;
            }
            [$out, $err, $exit] = $this->runPipeline($group, $heredocs, $heredocIndex);
            $stdout .= $out;
            $stderr .= $err;
        }

        return [$stdout, $stderr, $exit];
    }

    /**
     * @param  list<string|array{op: string}>  $tokens
     * @param  list<string>  $heredocs
     * @return array{0: string, 1: string, 2: int}
     */
    private function runPipeline(array $tokens, array $heredocs, int &$heredocIndex): array
    {
        $commands = [[]];
        foreach ($tokens as $token) {
            if (is_array($token) && $token['op'] === '|') {
                $commands[] = [];

                continue;
            }
            $commands[count($commands) - 1][] = $token;
        }

        $stdin = '';
        $stderr = '';
        $exit = 0;
        foreach ($commands as $command) {
            $argv = [];
            $redirect = null;
            $append = false;
            $inputFile = null;
            $discardStderr = false;
            $useHeredoc = false;
            for ($i = 0; $i < count($command); $i++) {
                $token = $command[$i];
                if (is_array($token)) {
                    $next = $command[$i + 1] ?? null;
                    match ($token['op']) {
                        '>', '>>' => [$redirect, $append, $i] = [is_string($next) ? $next : null, $token['op'] === '>>', $i + 1],
                        '<' => [$inputFile, $i] = [is_string($next) ? $next : null, $i + 1],
                        '2>' => [$discardStderr, $i] = [true, $i + 1],
                        '2>&1' => null,
                        '<<' => [$useHeredoc, $i] = [true, $i + 1],
                        default => null,
                    };

                    continue;
                }
                $argv[] = $token;
            }
            if ($useHeredoc) {
                $stdin = $heredocs[$heredocIndex++] ?? '';
            }
            if ($inputFile !== null) {
                $read = $this->readLocal($inputFile);
                if ($read === null) {
                    return ['', "bash: {$inputFile}: No such file or directory\n", 1];
                }
                $stdin = $read;
            }
            if ($argv === []) {
                [$out, $err, $exit] = [$stdin, '', 0];
            } else {
                [$out, $err, $exit] = $this->runCommand($argv, $stdin);
            }
            if (! $discardStderr) {
                $stderr .= $err;
            }
            if ($redirect !== null) {
                $error = $this->writeLocal($redirect, $out, $append);
                if ($error !== null) {
                    return ['', $stderr.$error, 1];
                }
                $out = '';
            }
            $stdin = $out;
        }

        return [$stdin, $stderr, $exit];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function runCommand(array $argv, string $stdin): array
    {
        $cmd = array_shift($argv);

        return match ($cmd) {
            'oc' => $this->oc($argv),
            'echo' => [$this->echo($argv), '', 0],
            'printf' => [$this->printf($argv), '', 0],
            'pwd' => [$this->cwd."\n", '', 0],
            'cd' => $this->cd($argv[0] ?? self::HOME),
            'ls' => $this->ls($argv),
            'cat' => $this->cat($argv, $stdin),
            'head', 'tail' => $this->headTail($cmd, $argv, $stdin),
            'wc' => $this->wc($argv, $stdin),
            'grep', 'egrep', 'rg' => $this->grep($argv, $stdin, $cmd !== 'grep'),
            'find' => $this->find($argv),
            'sed' => $this->sed($argv, $stdin),
            'cp' => $this->cp($argv, false),
            'mv' => $this->cp($argv, true),
            'rm' => $this->rm($argv),
            'mkdir' => $this->mkdirCmd($argv),
            'touch' => $this->touch($argv),
            'diff' => $this->diff($argv),
            'sort' => [$this->sortLines($stdin, $argv), '', 0],
            'uniq' => [implode("\n", array_unique(explode("\n", rtrim($stdin, "\n"))))."\n", '', 0],
            'true' => ['', '', 0],
            'false' => ['', '', 1],
            'whoami' => ["agent\n", '', 0],
            'env', 'printenv' => ["HOME=/home/agent\nPATH=/usr/local/bin:/usr/bin:/bin\nOC_BRIDGE=vfs\n", '', 0],
            'curl', 'wget', 'ssh', 'scp', 'nc', 'telnet', 'php', 'python', 'python3', 'node', 'ruby', 'perl', 'sqlite3', 'psql', 'mysql', 'redis-cli', 'docker', 'sudo', 'apt', 'pip', 'npm', 'git', 'artisan', 'jq' => [
                '',
                "{$cmd}: not available on this computer. There is no network route, credential or interpreter that reaches company systems; company data is only reachable through `oc vfs` (see `oc vfs help`).\n",
                127,
            ],
            default => ['', "bash: {$cmd}: command not found\n", 127],
        };
    }

    // ─── oc CLI ────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function oc(array $argv): array
    {
        if (($argv[0] ?? null) !== 'vfs') {
            return ['', "usage: oc vfs <help|exec|read|edit|write|patch|pull|push|status> ...\n", 2];
        }
        $verb = $argv[1] ?? 'help';
        $rest = array_slice($argv, 2);

        if ($verb === 'help' || $verb === '--help') {
            return [$this->ocHelp(), '', 0];
        }
        if ($verb === 'pull') {
            return [$this->pull($rest[0] ?? null), '', 0];
        }
        if ($verb === 'status') {
            $stale = $this->staleAmong(array_keys(array_filter($this->files, fn ($f) => isset($f['origin']))));

            return ["snapshot pulled at {$this->pulledAt}\n".($stale === [] ? "up to date\n" : "changed upstream since pull:\n  ".implode("\n  ", $stale)."\n"), '', 0];
        }
        if ($verb === 'push') {
            return $this->push($rest);
        }

        $slug = match ($verb) {
            'exec' => 'vfs_exec', 'read' => 'vfs_read', 'edit' => 'vfs_edit', 'write' => 'vfs_write', 'patch' => 'vfs_patch',
            default => null,
        };
        if ($slug === null) {
            return ['', "oc vfs: unknown verb {$verb}; run `oc vfs help`\n", 2];
        }

        $arguments = $this->ocArguments($verb, $rest);
        if (is_string($arguments)) {
            return ['', "oc vfs {$verb}: {$arguments}\n", 2];
        }

        $raw = $this->tools->callSlug($slug, $arguments);
        $envelope = json_decode($raw, true);
        $ok = is_array($envelope) && ($envelope['ok'] ?? false) === true;

        // exec prints the VFS command's stdout like a terminal; everything
        // else about the result (errors, truncation, cursors) goes to stderr
        // as JSON so nothing is silently dropped.
        if ($verb === 'exec' && is_array($envelope)) {
            $meta = array_diff_key($envelope, array_flip(['stdout', 'cwd', 'ok', 'exit_code']));

            return [(string) ($envelope['stdout'] ?? ''), $meta === [] ? '' : 'oc: '.json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", (int) ($envelope['exit_code'] ?? ($ok ? 0 : 1))];
        }

        return [$raw."\n", '', $ok ? 0 : 1];
    }

    /**
     * Parse `oc vfs <verb>` arguments: a JSON object with the tool's own
     * parameters, or the shorthand `exec <command...>` / `read <path>`.
     *
     * @param  list<string>  $rest
     * @return array<string, mixed>|string parameters, or an error message
     */
    private function ocArguments(string $verb, array $rest): array|string
    {
        $joined = trim(implode(' ', $rest));
        if ($joined === '') {
            return 'missing arguments; pass the tool parameters as one JSON object (see `oc vfs help`)';
        }
        if (str_starts_with($joined, '{')) {
            $decoded = json_decode($joined, true);

            return is_array($decoded) ? $decoded : 'arguments are not valid JSON';
        }

        return match ($verb) {
            'exec' => ['command' => $joined],
            'read' => ['path' => $rest[0]] + $this->readFlags(array_slice($rest, 1)),
            default => "{$verb} takes one JSON object with the tool parameters, e.g. oc vfs {$verb} '{\"path\":\"...\"}'",
        };
    }

    /**
     * @param  list<string>  $flags
     * @return array<string, mixed>
     */
    private function readFlags(array $flags): array
    {
        $out = [];
        for ($i = 0; $i < count($flags); $i++) {
            $flag = ltrim($flags[$i], '-');
            if ($flag === 'raw') {
                $out['raw'] = true;
            } elseif (in_array($flag, ['offset', 'limit', 'cursor', 'maxBytes'], true) && isset($flags[$i + 1])) {
                $out[$flag] = is_numeric($flags[$i + 1]) ? (int) $flags[$i + 1] : $flags[$i + 1];
                $i++;
            }
        }

        return $out;
    }

    private function ocHelp(): string
    {
        $lines = ['oc vfs: OpenCompany virtual filesystem bridge. Every call runs as you, with your permissions, approvals and audit.', ''];
        foreach (['exec' => 'vfs_exec', 'read' => 'vfs_read', 'edit' => 'vfs_edit', 'write' => 'vfs_write', 'patch' => 'vfs_patch'] as $verb => $slug) {
            $schema = collect($this->tools->schemas())->firstWhere('function.name', $this->tools->nameForSlug($slug));
            if ($schema === null) {
                continue;
            }
            $params = [];
            foreach ($schema['function']['parameters']['properties'] as $param => $definition) {
                $params[] = $param.(in_array($param, $schema['function']['parameters']['required'] ?? [], true) ? '' : '?');
            }
            $lines[] = "oc vfs {$verb} '<json: ".implode(', ', $params).">'";
            $lines[] = '  '.$schema['function']['description'];
            $lines[] = '';
        }
        $lines[] = 'Shorthands: oc vfs exec <command line> | oc vfs read <path> [--offset N --limit N --cursor C --raw]';
        $lines[] = 'oc vfs pull [<vfs path>]   refresh the read-only snapshot under /mnt/oc';
        $lines[] = 'oc vfs status              list snapshot files that changed upstream';
        $lines[] = 'oc vfs push <local file> [<vfs path>]   write a local copy back through the VFS (version-checked: text overwrites send the version you pulled; table row JSON sends only changed columns with base)';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<string>  $args
     * @return array{0: string, 1: string, 2: int}
     */
    private function push(array $args): array
    {
        $local = isset($args[0]) ? $this->abs($args[0]) : null;
        if ($local === null || ! isset($this->files[$local])) {
            return ['', "oc vfs push: no such local file\n", 1];
        }
        $file = $this->files[$local];
        $target = $args[1] ?? ($file['origin'] ?? null);
        if ($target === null) {
            return ['', "oc vfs push: {$local} has no VFS origin; pass the destination path\n", 2];
        }

        if (preg_match('#^/tables/.+/rows/by-id/[^/]+\.json$#', $target) === 1) {
            $pulled = json_decode((string) ($this->snapshotOf($target)['content'] ?? '{}'), true);
            $mine = json_decode($file['content'], true);
            if (! is_array($mine)) {
                return ['', "oc vfs push: {$local} is not valid row JSON\n", 2];
            }
            $before = is_array($pulled['data'] ?? null) ? $pulled['data'] : [];
            $after = is_array($mine['data'] ?? null) ? $mine['data'] : $mine;
            $changed = [];
            $base = [];
            foreach ($after as $column => $value) {
                if (! array_key_exists($column, $before) || $before[$column] !== $value) {
                    $changed[$column] = $value;
                    if (array_key_exists($column, $before)) {
                        $base[$column] = $before[$column];
                    }
                }
            }
            if ($changed === []) {
                return ["nothing to push: no columns changed\n", '', 0];
            }
            $raw = $this->tools->callSlug('vfs_patch', ['path' => $target, 'patch' => json_encode(['data' => $changed, 'base' => (object) $base])]);
        } else {
            $version = $file['version'] ?? ($this->snapshotOf($target)['version'] ?? null);
            $raw = $this->tools->callSlug('vfs_write', array_filter([
                'path' => $target,
                'content' => $file['content'],
                'mode' => $version === null ? 'create' : 'overwrite',
                'version' => $version,
            ], fn ($v) => $v !== null));
        }
        $ok = (json_decode($raw, true)['ok'] ?? false) === true;

        return [$raw."\n", '', $ok ? 0 : 1];
    }

    /** @return array{content: string, origin?: string, version?: string|null}|null */
    private function snapshotOf(string $vfsPath): ?array
    {
        return $this->files[self::PULL_ROOT.$vfsPath] ?? null;
    }

    // ─── Local coreutils subset ────────────────────────────────────────────

    /** @param list<string> $argv */
    private function echo(array $argv): string
    {
        $newline = true;
        $escapes = false;
        while (in_array($argv[0] ?? null, ['-n', '-e', '-ne', '-en'], true)) {
            $flag = array_shift($argv);
            $newline = $newline && ! str_contains($flag, 'n');
            $escapes = $escapes || str_contains($flag, 'e');
        }
        $text = implode(' ', $argv);

        return ($escapes ? stripcslashes($text) : $text).($newline ? "\n" : '');
    }

    /** @param list<string> $argv */
    private function printf(array $argv): string
    {
        $format = stripcslashes((string) array_shift($argv));
        $out = preg_replace_callback('/%[sd]/', function () use (&$argv) {
            return (string) array_shift($argv);
        }, $format);

        return (string) $out;
    }

    /** @return array{0: string, 1: string, 2: int} */
    private function cd(string $path): array
    {
        $abs = $this->abs($path);
        if (! $this->isDir($abs)) {
            return ['', "bash: cd: {$path}: No such file or directory\n", 1];
        }
        $this->cwd = $abs;

        return ['', '', 0];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function ls(array $argv): array
    {
        $paths = array_values(array_filter($argv, fn ($a) => ! str_starts_with($a, '-')));
        $long = (bool) array_filter($argv, fn ($a) => str_starts_with($a, '-') && str_contains($a, 'l'));
        $out = '';
        foreach ($paths === [] ? [$this->cwd] : $paths as $path) {
            $abs = $this->abs($path);
            if (isset($this->files[$abs])) {
                $out .= $path."\n";

                continue;
            }
            if (! $this->isDir($abs)) {
                return [$out, "ls: cannot access '{$path}': No such file or directory\n", 2];
            }
            if (count($paths) > 1) {
                $out .= "{$path}:\n";
            }
            foreach ($this->children($abs) as $name => $isDir) {
                $size = $isDir ? 0 : strlen($this->files[$abs.'/'.$name]['content'] ?? '');
                $ro = str_starts_with($abs, self::PULL_ROOT);
                $out .= $long ? sprintf("%s %8d %s%s\n", $isDir ? ($ro ? 'dr-xr-xr-x' : 'drwxr-xr-x') : ($ro ? '-r--r--r--' : '-rw-r--r--'), $size, $name, $isDir ? '/' : '') : $name.($isDir ? '/' : '')."\n";
            }
        }

        return [$out, '', 0];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function cat(array $argv, string $stdin): array
    {
        $paths = array_values(array_filter($argv, fn ($a) => ! str_starts_with($a, '-')));
        if ($paths === []) {
            return [$stdin, '', 0];
        }
        $out = '';
        $read = [];
        foreach ($paths as $path) {
            $abs = $this->abs($path);
            $content = $this->readLocal($abs);
            if ($content === null) {
                return [$out, "cat: {$path}: ".($this->isDir($abs) ? 'Is a directory' : 'No such file or directory')."\n", 1];
            }
            $out .= $content;
            $read[] = $abs;
        }

        return [$out, $this->staleWarning($read), 0];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function headTail(string $cmd, array $argv, string $stdin): array
    {
        $n = 10;
        $paths = [];
        for ($i = 0; $i < count($argv); $i++) {
            if ($argv[$i] === '-n' && isset($argv[$i + 1])) {
                $n = (int) $argv[++$i];
            } elseif (preg_match('/^-n?(\d+)$/', $argv[$i], $m) === 1) {
                $n = (int) $m[1];
            } else {
                $paths[] = $argv[$i];
            }
        }
        $text = $stdin;
        $read = [];
        if ($paths !== []) {
            $abs = $this->abs($paths[0]);
            $text = $this->readLocal($abs);
            if ($text === null) {
                return ['', "{$cmd}: cannot open '{$paths[0]}' for reading: No such file or directory\n", 1];
            }
            $read[] = $abs;
        }
        $lines = explode("\n", rtrim($text, "\n"));
        $slice = $cmd === 'head' ? array_slice($lines, 0, $n) : array_slice($lines, -$n);

        return [$text === '' ? '' : implode("\n", $slice)."\n", $this->staleWarning($read), 0];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function wc(array $argv, string $stdin): array
    {
        $flags = implode('', array_map(fn ($a) => ltrim($a, '-'), array_filter($argv, fn ($a) => str_starts_with($a, '-'))));
        $paths = array_values(array_filter($argv, fn ($a) => ! str_starts_with($a, '-')));
        $text = $paths === [] ? $stdin : (string) $this->readLocal($this->abs($paths[0]));
        $lines = substr_count($text, "\n");
        $words = str_word_count($text);
        $bytes = strlen($text);
        $suffix = $paths === [] ? '' : ' '.$paths[0];

        return [match (true) {
            $flags === 'l' => "{$lines}{$suffix}\n",
            $flags === 'w' => "{$words}{$suffix}\n",
            $flags === 'c' => "{$bytes}{$suffix}\n",
            default => "{$lines} {$words} {$bytes}{$suffix}\n",
        }, '', 0];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function grep(array $argv, string $stdin, bool $regexDefault): array
    {
        $flags = '';
        $rest = [];
        foreach ($argv as $arg) {
            if (preg_match('/^-[a-zA-Z]+$/', $arg) === 1 && $rest === []) {
                $flags .= substr($arg, 1);
            } elseif (in_array($arg, ['--recursive', '--ignore-case', '--line-number', '--files-with-matches', '--fixed-strings'], true) && $rest === []) {
                $flags .= ['--recursive' => 'r', '--ignore-case' => 'i', '--line-number' => 'n', '--files-with-matches' => 'l', '--fixed-strings' => 'F'][$arg];
            } else {
                $rest[] = $arg;
            }
        }
        $pattern = array_shift($rest);
        if ($pattern === null) {
            return ['', "usage: grep [-rinlvcF] PATTERN [FILE...]\n", 2];
        }
        $fixed = str_contains($flags, 'F');
        $regex = '/'.($fixed ? preg_quote($pattern, '/') : str_replace('/', '\/', $pattern)).'/'.(str_contains($flags, 'i') ? 'i' : '').'u';
        if (@preg_match($regex, '') === false) {
            $regex = '/'.preg_quote($pattern, '/').'/'.(str_contains($flags, 'i') ? 'i' : '').'u';
        }
        $recursive = str_contains($flags, 'r') || str_contains($flags, 'R') || ($regexDefault && $rest !== []);
        $targets = [];
        foreach ($rest as $path) {
            $abs = $this->abs($path);
            if (isset($this->files[$abs])) {
                $targets[] = $abs;
            } elseif ($this->isDir($abs) && $recursive) {
                foreach (array_keys($this->files) as $file) {
                    if (str_starts_with($file, rtrim($abs, '/').'/')) {
                        $targets[] = $file;
                    }
                }
            } elseif ($this->isDir($abs)) {
                return ['', "grep: {$path}: Is a directory\n", 2];
            } else {
                return ['', "grep: {$path}: No such file or directory\n", 2];
            }
        }
        $inputs = $rest === [] ? ['(standard input)' => $stdin] : array_combine($targets, array_map(fn ($f) => $this->files[$f]['content'], $targets));
        $multi = count($inputs) > 1 || $recursive;
        $out = '';
        $any = false;
        foreach ($inputs ?: [] as $name => $content) {
            $count = 0;
            foreach (explode("\n", rtrim((string) $content, "\n")) as $i => $line) {
                $hit = preg_match($regex, $line) === 1;
                if (str_contains($flags, 'v')) {
                    $hit = ! $hit;
                }
                if (! $hit) {
                    continue;
                }
                $any = true;
                $count++;
                if (str_contains($flags, 'l') || str_contains($flags, 'c')) {
                    continue;
                }
                $out .= ($multi ? "{$name}:" : '').(str_contains($flags, 'n') ? ($i + 1).':' : '').$line."\n";
            }
            if (str_contains($flags, 'l') && $count > 0) {
                $out .= "{$name}\n";
            }
            if (str_contains($flags, 'c')) {
                $out .= ($multi ? "{$name}:" : '')."{$count}\n";
            }
        }

        return [$out, $this->staleWarning($targets), $any ? 0 : 1];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function find(array $argv): array
    {
        $root = isset($argv[0]) && ! str_starts_with($argv[0], '-') ? array_shift($argv) : '.';
        $name = null;
        $type = null;
        for ($i = 0; $i < count($argv); $i++) {
            if (in_array($argv[$i], ['-name', '-iname'], true)) {
                $name = $argv[++$i] ?? null;
            } elseif ($argv[$i] === '-type') {
                $type = $argv[++$i] ?? null;
            }
        }
        $abs = $this->abs($root);
        if (! $this->isDir($abs) && ! isset($this->files[$abs])) {
            return ['', "find: '{$root}': No such file or directory\n", 1];
        }
        $out = [];
        foreach (array_keys($this->allDirs()) as $dir) {
            if (($dir === $abs || str_starts_with($dir, rtrim($abs, '/').'/')) && $type !== 'f' && ($name === null || fnmatch($name, basename($dir), FNM_CASEFOLD))) {
                $out[] = $dir;
            }
        }
        foreach (array_keys($this->files) as $file) {
            if (($file === $abs || str_starts_with($file, rtrim($abs, '/').'/')) && $type !== 'd' && ($name === null || fnmatch($name, basename($file), FNM_CASEFOLD))) {
                $out[] = $file;
            }
        }
        sort($out);

        return [$out === [] ? '' : implode("\n", $out)."\n", '', 0];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function sed(array $argv, string $stdin): array
    {
        $inPlace = false;
        $script = null;
        $paths = [];
        for ($i = 0; $i < count($argv); $i++) {
            if ($argv[$i] === '-i' || str_starts_with($argv[$i], '-i')) {
                $inPlace = true;
            } elseif ($argv[$i] === '-e') {
                $script = $argv[++$i] ?? null;
            } elseif ($script === null) {
                $script = $argv[$i];
            } else {
                $paths[] = $argv[$i];
            }
        }
        if ($script === null || preg_match('/^s(.)(.*?)(?<!\\\\)\1(.*?)(?<!\\\\)\1([gI]*)$/s', $script, $m) !== 1) {
            return ['', "sed: only s/regex/replacement/[g] is supported here\n", 1];
        }
        $regex = '/'.str_replace('/', '\/', $m[2]).'/'.(str_contains($m[4], 'I') ? 'i' : '').'u';
        $replacement = preg_replace('/\\\\(\d)/', '\$$1', str_replace('&', '$0', $m[3]));
        $limit = str_contains($m[4], 'g') ? -1 : 1;
        $apply = fn (string $text): string => implode("\n", array_map(fn ($line) => (string) @preg_replace($regex, $replacement, $line, $limit), explode("\n", $text)));
        if ($paths === []) {
            return [$apply($stdin), '', 0];
        }
        $out = '';
        foreach ($paths as $path) {
            $abs = $this->abs($path);
            $content = $this->readLocal($abs);
            if ($content === null) {
                return ['', "sed: can't read {$path}: No such file or directory\n", 2];
            }
            if ($inPlace) {
                $error = $this->writeLocal($abs, $apply($content), false);
                if ($error !== null) {
                    return ['', $error, 4];
                }
            } else {
                $out .= $apply($content);
            }
        }

        return [$out, '', 0];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function cp(array $argv, bool $move): array
    {
        $args = array_values(array_filter($argv, fn ($a) => ! str_starts_with($a, '-')));
        $recursive = (bool) array_filter($argv, fn ($a) => str_starts_with($a, '-') && stripos($a, 'r') !== false);
        if (count($args) < 2) {
            return ['', ($move ? 'mv' : 'cp').": missing destination file operand\n", 1];
        }
        $dest = $this->abs(array_pop($args));
        foreach ($args as $src) {
            $abs = $this->abs($src);
            $sources = isset($this->files[$abs]) ? [$abs => $abs] : [];
            if ($sources === [] && $recursive && $this->isDir($abs)) {
                foreach (array_keys($this->files) as $file) {
                    if (str_starts_with($file, $abs.'/')) {
                        $sources[$file] = $file;
                    }
                }
            }
            if ($sources === []) {
                return ['', ($move ? 'mv' : 'cp').": cannot stat '{$src}': No such file or directory\n", 1];
            }
            foreach ($sources as $file) {
                $target = $this->isDir($dest) ? $dest.'/'.($file === $abs ? basename($file) : basename($abs).substr($file, strlen($abs))) : $dest;
                $error = $this->writeLocal($target, $this->files[$file]['content'], false);
                if ($error !== null) {
                    return ['', $error, 1];
                }
                // A copy remembers where it came from and which version, so a
                // later `oc vfs push` is version-checked against that read.
                foreach (['origin', 'version'] as $key) {
                    if (isset($this->files[$file][$key])) {
                        $this->files[$target][$key] = $this->files[$file][$key];
                    }
                }
                if ($move) {
                    if (! empty($this->files[$file]['readonly'])) {
                        return ['', "mv: cannot remove '{$src}': Read-only file system\n", 1];
                    }
                    unset($this->files[$file]);
                }
            }
        }

        return ['', '', 0];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function rm(array $argv): array
    {
        foreach (array_filter($argv, fn ($a) => ! str_starts_with($a, '-')) as $path) {
            $abs = $this->abs($path);
            if (str_starts_with($abs, self::PULL_ROOT)) {
                return ['', "rm: cannot remove '{$path}': Read-only file system\n", 1];
            }
            unset($this->files[$abs]);
        }

        return ['', '', 0];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function mkdirCmd(array $argv): array
    {
        foreach (array_filter($argv, fn ($a) => ! str_starts_with($a, '-')) as $path) {
            $abs = $this->abs($path);
            if (str_starts_with($abs, self::PULL_ROOT)) {
                return ['', "mkdir: cannot create directory '{$path}': Read-only file system\n", 1];
            }
            $this->mkdirs($abs);
        }

        return ['', '', 0];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function touch(array $argv): array
    {
        foreach ($argv as $path) {
            $abs = $this->abs($path);
            if (! isset($this->files[$abs]) && ($error = $this->writeLocal($abs, '', false)) !== null) {
                return ['', $error, 1];
            }
        }

        return ['', '', 0];
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: string, 1: string, 2: int}
     */
    private function diff(array $argv): array
    {
        $paths = array_values(array_filter($argv, fn ($a) => ! str_starts_with($a, '-')));
        if (count($paths) !== 2) {
            return ['', "diff: two files required\n", 2];
        }
        $a = $this->readLocal($this->abs($paths[0]));
        $b = $this->readLocal($this->abs($paths[1]));
        if ($a === null || $b === null) {
            return ['', "diff: No such file or directory\n", 2];
        }
        if ($a === $b) {
            return ['', '', 0];
        }
        $out = '';
        foreach (array_diff(explode("\n", $a), explode("\n", $b)) as $line) {
            $out .= "< {$line}\n";
        }
        foreach (array_diff(explode("\n", $b), explode("\n", $a)) as $line) {
            $out .= "> {$line}\n";
        }

        return [$out, '', 1];
    }

    /** @param list<string> $argv */
    private function sortLines(string $stdin, array $argv): string
    {
        $lines = explode("\n", rtrim($stdin, "\n"));
        in_array('-n', $argv, true) ? sort($lines, SORT_NUMERIC) : sort($lines);
        if (in_array('-r', $argv, true)) {
            $lines = array_reverse($lines);
        }

        return implode("\n", $lines)."\n";
    }

    // ─── Local filesystem helpers ──────────────────────────────────────────

    private function abs(string $path): string
    {
        $path = str_starts_with($path, '~') ? self::HOME.substr($path, 1) : $path;
        $path = str_starts_with($path, '/') ? $path : $this->cwd.'/'.$path;
        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $segment;
        }

        return '/'.implode('/', $parts);
    }

    private function readLocal(string $path): ?string
    {
        return $this->files[$this->abs($path)]['content'] ?? null;
    }

    private function writeLocal(string $path, string $content, bool $append): ?string
    {
        $abs = $this->abs($path);
        if (str_starts_with($abs, self::PULL_ROOT.'/') || $abs === self::PULL_ROOT) {
            return "bash: {$path}: Read-only file system (the /mnt/oc snapshot cannot be edited; copy it to /home/agent and use `oc vfs push`)\n";
        }
        if (! str_starts_with($abs, self::HOME.'/') && ! str_starts_with($abs, '/tmp/')) {
            return "bash: {$path}: Permission denied\n";
        }
        if ($abs === '/dev/null') {
            return null;
        }
        $this->mkdirs(dirname($abs));
        $previous = $this->files[$abs] ?? [];
        $this->files[$abs] = ['content' => ($append ? ($previous['content'] ?? '') : '').$content] + array_intersect_key($previous, array_flip(['origin', 'version']));

        return null;
    }

    private function mkdirs(string $path): void
    {
        $current = '';
        foreach (array_filter(explode('/', $path)) as $segment) {
            $current .= '/'.$segment;
            $this->dirs[$current] = true;
        }
    }

    /** @return array<string, true> */
    private function allDirs(): array
    {
        $dirs = $this->dirs + ['/' => true, '/tmp' => true];
        foreach (array_keys($this->files) as $file) {
            $dir = dirname($file);
            while ($dir !== '/' && ! isset($dirs[$dir])) {
                $dirs[$dir] = true;
                $dir = dirname($dir);
            }
        }

        return $dirs;
    }

    private function isDir(string $path): bool
    {
        return isset($this->allDirs()[$path]);
    }

    /** @return array<string, bool> name => isDir */
    private function children(string $dir): array
    {
        $prefix = rtrim($dir, '/').'/';
        $children = [];
        foreach (array_keys($this->allDirs()) as $d) {
            if (str_starts_with($d, $prefix) && ! str_contains(substr($d, strlen($prefix)), '/') && $d !== $dir) {
                $children[substr($d, strlen($prefix))] = true;
            }
        }
        foreach (array_keys($this->files) as $f) {
            if (str_starts_with($f, $prefix) && ! str_contains(substr($f, strlen($prefix)), '/')) {
                $children[substr($f, strlen($prefix))] = false;
            }
        }
        ksort($children);

        return $children;
    }

    /** @param list<string> $locals */
    private function staleWarning(array $locals): string
    {
        $stale = $this->staleAmong(array_values(array_filter($locals, fn ($l) => str_starts_with($l, self::PULL_ROOT.'/'))));
        if ($stale === []) {
            return '';
        }

        return 'oc: warning: '.count($stale).' snapshot file(s) changed upstream since the pull at '.$this->pulledAt.' ('.implode(', ', array_slice($stale, 0, 5)).'); run `oc vfs pull` or read through `oc vfs read`.'."\n";
    }

    private function clip(string $text): string
    {
        return strlen($text) > 60000 ? substr($text, 0, 60000)."\n[... output cut at 60000 bytes]\n" : $text;
    }

    /**
     * Split heredoc bodies out of the line (`<<EOF` ... `EOF`).
     *
     * @return array{0: string, 1: list<string>}
     */
    private function extractHeredocs(string $line): array
    {
        $bodies = [];
        while (preg_match('/<<-?\s*([\'"]?)(\w+)\1/', $line, $m, PREG_OFFSET_CAPTURE) === 1) {
            $delimiter = $m[2][0];
            $start = $m[0][1];
            $newline = strpos($line, "\n", $start);
            if ($newline === false) {
                break;
            }
            $rest = substr($line, $newline + 1);
            if (preg_match('/^'.preg_quote($delimiter, '/').'\s*$/m', $rest, $end, PREG_OFFSET_CAPTURE) !== 1) {
                break;
            }
            $bodies[] = substr($rest, 0, $end[0][1]);
            $after = substr($rest, $end[0][1] + strlen($end[0][0]));
            $line = substr($line, 0, $start).' << '.substr($line, $start + strlen($m[0][0]), $newline - $start - strlen($m[0][0])).$after;
        }

        return [str_replace("\n", ' ; ', $line), $bodies];
    }

    /**
     * Bash-like tokenizer: quotes, backslash escapes, and the operators
     * | ; && || > >> < 2> 2>&1 <<.
     *
     * @return list<string|array{op: string}>|null null on an unterminated quote
     */
    private function tokenize(string $line): ?array
    {
        $tokens = [];
        $current = '';
        $hasCurrent = false;
        $length = strlen($line);
        for ($i = 0; $i < $length; $i++) {
            $c = $line[$i];
            if ($c === "'" || $c === '"') {
                $end = $i + 1;
                $buffer = '';
                while ($end < $length && $line[$end] !== $c) {
                    if ($c === '"' && $line[$end] === '\\' && $end + 1 < $length && in_array($line[$end + 1], ['"', '\\', '$', '`'], true)) {
                        $end++;
                    }
                    $buffer .= $line[$end];
                    $end++;
                }
                if ($end >= $length) {
                    return null;
                }
                $current .= $buffer;
                $hasCurrent = true;
                $i = $end;

                continue;
            }
            if ($c === '\\' && $i + 1 < $length) {
                $current .= $line[++$i];
                $hasCurrent = true;

                continue;
            }
            $op = null;
            foreach (['2>&1', '&&', '||', '>>', '<<', '2>', '|', ';', '>', '<'] as $candidate) {
                if (substr($line, $i, strlen($candidate)) === $candidate && ($candidate !== '2>' && $candidate !== '2>&1' || ! $hasCurrent)) {
                    $op = $candidate;
                    break;
                }
            }
            if ($op !== null || ctype_space($c)) {
                if ($hasCurrent) {
                    $tokens[] = $current;
                    $current = '';
                    $hasCurrent = false;
                }
                if ($op !== null) {
                    $tokens[] = ['op' => $op];
                    $i += strlen($op) - 1;
                }

                continue;
            }
            $current .= $c;
            $hasCurrent = true;
        }
        if ($hasCurrent) {
            $tokens[] = $current;
        }

        return $tokens;
    }
}

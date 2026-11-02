<?php

namespace App\Domain\Vfs\Core;

/**
 * Machine-readable command contract for the VFS shell.
 *
 * The runtime keeps command execution in focused traits, but discovery,
 * preflight, argv parsing (VfsArgs), docs, and tests share this one source for
 * names, aliases, option syntax, GNU deviations, and governance class.
 *
 * Invariant: every flag listed here is implemented (or is in the explicit,
 * reasoned allowlist of VfsShellFlagCoverageTest). Flags that the VFS refuses
 * on purpose (`tail -f`, `ln -s`, `touch -d`) are NOT listed; the command
 * rejects them with a specific reason before argv parsing instead.
 *
 * `actionClass` is the reversibility class the future policy engine gates on
 * (docs/architecture/governance-model.md): read, reversible_edit, delete,
 * structural, governance. It names the most impactful mode of the command
 * (sed is reversible_edit because of `sed -i`); today's enforcement remains
 * the tool-permission + approval checks in the mutation paths.
 */
final readonly class VfsCommandSpec
{
    /**
     * @param  list<string>  $aliases
     * @param  list<string>  $shortFlags  Single letters, e.g. ['l', 'a'].
     * @param  list<string>  $valueOptions  Spelled as typed, e.g. ['-n', '--lines', '-maxdepth'].
     * @param  list<string>  $longFlags  Boolean long options, e.g. ['--hidden'].
     * @param  array<string, string>  $longAliases  Long spelling => canonical option, e.g. ['--ignore-case' => '-i'].
     * @param  list<string>  $examples
     * @param  list<string>  $notes  Where VFS semantics differ from GNU.
     * @param  list<string>  $unsupportedExamples
     */
    public function __construct(
        public string $name,
        public string $class,
        public string $description,
        public string $status = 'implemented',
        public ?string $usage = null,
        public ?string $permission = null,
        public ?string $reads = null,
        public ?string $writes = null,
        public ?string $output = null,
        public ?string $structuredAlternative = null,
        public array $aliases = [],
        public array $shortFlags = [],
        public array $valueOptions = [],
        public array $longFlags = [],
        public bool $numericShorthand = false,
        public array $examples = [],
        public array $notes = [],
        public array $unsupportedExamples = [],
        public ?string $unsupportedReason = null,
        public ?string $suggestion = null,
        public array $longAliases = [],
        public ?string $actionClass = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function catalogEntry(): array
    {
        return array_filter([
            'class' => $this->class,
            'action_class' => $this->actionClass(),
            'description' => $this->description,
            'status' => $this->status,
            'usage' => $this->usage ?? $this->defaultUsage(),
            'permission' => $this->permission ?? $this->defaultPermission(),
            'reads' => $this->reads,
            'writes' => $this->writes,
            'output' => $this->output ?? $this->defaultOutput(),
            'structured_alternative' => $this->structuredAlternative,
            'aliases' => $this->aliases === [] ? null : $this->aliases,
            'short_flags' => $this->shortFlags === [] ? null : array_map(fn (string $flag): string => "-{$flag}", $this->shortFlags),
            'value_options' => $this->valueOptions === [] ? null : $this->valueOptions,
            'long_flags' => $this->longFlags === [] ? null : $this->longFlags,
            'long_aliases' => $this->longAliases === [] ? null : $this->longAliases,
            'numeric_shorthand' => $this->numericShorthand ?: null,
            'examples' => $this->examples === [] ? $this->defaultExamples() : $this->examples,
            'notes' => $this->notes === [] ? null : $this->notes,
            'unsupported_examples' => $this->unsupportedExamples === [] ? null : $this->unsupportedExamples,
            'unsupported_reason' => $this->unsupportedReason,
            'suggestion' => $this->suggestion,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * Reversibility class for governance (see class docblock).
     */
    public function actionClass(): ?string
    {
        return $this->actionClass ?? match ($this->class) {
            'browse', 'read', 'read-transform' => 'read',
            'write', 'binary-archive' => 'reversible_edit',
            'destructive' => 'delete',
            default => null,
        };
    }

    /**
     * Every flag spelling the command accepts, for error contexts.
     *
     * @return list<string>
     */
    public function supportedFlagList(): array
    {
        return array_values(array_unique([
            ...array_map(fn (string $flag): string => "-{$flag}", $this->shortFlags),
            ...$this->valueOptions,
            ...$this->longFlags,
            ...array_keys($this->longAliases),
            ...($this->numericShorthand ? ['-N'] : []),
        ]));
    }

    public function defaultUsage(): string
    {
        if ($this->status !== 'implemented') {
            return "{$this->name} [options] ...";
        }

        return match ($this->class) {
            'browse' => "{$this->name} [options] [path]",
            'read' => "{$this->name} [options] [path ...]",
            'read-transform' => "{$this->name} [options] [file ...]",
            'write', 'destructive' => "{$this->name} [options] /files/path ...",
            'binary-archive' => "{$this->name} [options] archive paths...",
            default => "{$this->name} [options]",
        };
    }

    public function defaultPermission(): string
    {
        return match ($this->class) {
            'write', 'destructive', 'binary-archive' => 'Mutating modes compose vfs_exec with vfs_write; read/list modes use normal VFS read permission.',
            'external' => 'Not executable inside VFS.',
            default => 'Uses normal VFS read/browse permissions and budgets.',
        };
    }

    public function defaultOutput(): string
    {
        return match ($this->class) {
            'browse' => 'Text stdout plus command metadata in the vfs_exec envelope.',
            'read' => 'Text stdout plus count/truncation metadata where available.',
            'read-transform' => 'Text stdout.',
            'write' => 'Text stdout describing the mutation (silent on success, like GNU, unless -v).',
            'destructive' => 'Silent on success unless -v.',
            'external' => 'Structured unsupported error.',
            default => 'Text stdout.',
        };
    }

    /**
     * @return list<string>
     */
    public function defaultExamples(): array
    {
        return match ($this->name) {
            'help' => ['help', 'help grep', 'grep --help'],
            'man' => ['man grep'],
            'ls' => ['ls /docs', 'ls -la /files'],
            'find' => ['find /docs -name "*.md"', 'find /files -type f -maxdepth 2'],
            'grep' => ['grep -n refund /docs/by-id/{id}', 'grep -R -i refund /docs'],
            'rg' => ['rg "refund|invoice" /docs', 'rg -n refund /files'],
            'stat' => ['stat /docs/by-id/{id}', 'stat -c "%n %s" /files/report.md'],
            'cat' => ['cat /tools/vfs.md', 'cat /docs/by-id/{id}'],
            'jq' => ['cat /tasks/counts.json | jq .open', 'jq ".items[].name" /files/data.json'],
            'mkdir' => ['mkdir -p /files/tmp/run'],
            'tee' => ['printf "hello" | tee /files/tmp/note.txt'],
            'tar' => ['tar -c -f /files/tmp/archive.tar /files/tmp/note.txt', 'tar -t -f /files/tmp/archive.tar'],
            'zip' => ['zip -r /files/tmp/archive.zip /files/tmp'],
            'unzip' => ['unzip -l /files/tmp/archive.zip', 'unzip -o /files/tmp/archive.zip -d /files/tmp/extract'],
            default => [],
        };
    }
}

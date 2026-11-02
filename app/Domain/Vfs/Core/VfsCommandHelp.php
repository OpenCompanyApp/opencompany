<?php

namespace App\Domain\Vfs\Core;

/**
 * Renders human and machine-readable VFS command discovery from command specs.
 */
final class VfsCommandHelp
{
    public static function listMarkdown(): string
    {
        $groups = [];
        foreach (VfsCommandSpecRegistry::all() as $name => $spec) {
            $groups[$spec->class][$name] = $spec;
        }
        ksort($groups);

        $lines = [
            '# VFS Commands',
            '',
            'Use `help <command>`, `<command> --help`, or `cat /tools/vfs/commands/<command>/<command>.json` for exact flags, examples, and notes on where VFS semantics differ from GNU.',
            '',
            'Shell: pipes, `;`, newlines, `&&`, `||`, `!`, `$?`, quotes, `#` comments, `<`, `>`, `>>`, `2>`, `&>`, `2>&1`, `>&2`, heredocs (`<<EOF`, `<<\'EOF\'`, `<<-`), here-strings (`<<<`), and globs (`*`, `?`, `[..]`, `**`) in any path segment.',
            'Exit status: 0 success, 1 false/no match/differences, 2 error. No variables, loops, or subshells: use xargs or Code Mode (code_exec).',
        ];
        foreach ($groups as $class => $specs) {
            $lines[] = '';
            $lines[] = '## '.$class;
            foreach ($specs as $name => $spec) {
                $status = $spec->status === 'implemented' ? 'ok' : $spec->status;
                $lines[] = "- `{$name}` [{$status}] - {$spec->description}";
            }
        }

        return implode("\n", $lines);
    }

    public static function commandMarkdown(string $command): string
    {
        $canonical = VfsCommandSpecRegistry::canonical($command);
        if ($canonical === null) {
            throw self::unknown($command);
        }
        $spec = VfsCommandSpecRegistry::all()[$canonical];
        $entry = $spec->catalogEntry();
        $lines = [
            "# {$canonical}",
            '',
            $spec->description,
            '',
            '## Usage',
            '',
            '`'.($entry['usage'] ?? $spec->defaultUsage()).'`',
            '',
            '## Support',
            '',
            '- Status: `'.$spec->status.'`',
            '- Class: `'.$spec->class.'`',
            '- Action class: `'.($spec->actionClass() ?? 'none').'`',
            '- Permission: '.($entry['permission'] ?? $spec->defaultPermission()),
            '- Output: '.($entry['output'] ?? $spec->defaultOutput()),
        ];

        if ($spec->aliases !== []) {
            $lines[] = '- Aliases: `'.implode('`, `', $spec->aliases).'`';
        }
        foreach ([
            'short_flags' => 'Short flags',
            'value_options' => 'Value options',
            'long_flags' => 'Long flags',
        ] as $key => $label) {
            if (! empty($entry[$key]) && is_array($entry[$key])) {
                $lines[] = "- {$label}: `".implode('`, `', array_map('strval', $entry[$key])).'`';
            }
        }
        if (($entry['numeric_shorthand'] ?? false) === true) {
            $lines[] = '- Numeric shorthand: supported';
        }
        if ($spec->longAliases !== []) {
            $lines[] = '- Long aliases: '.implode(', ', array_map(fn (string $long, string $short): string => "`{$long}`=`{$short}`", array_keys($spec->longAliases), $spec->longAliases));
        }
        if ($spec->structuredAlternative !== null) {
            $lines[] = '- Structured alternative: `'.$spec->structuredAlternative.'`';
        }
        if ($spec->unsupportedReason !== null) {
            $lines[] = '- Unsupported reason: '.$spec->unsupportedReason;
        }
        if ($spec->suggestion !== null) {
            $lines[] = '- Suggestion: '.$spec->suggestion;
        }

        $examples = $spec->examples === [] ? $spec->defaultExamples() : $spec->examples;
        if ($examples !== []) {
            $lines[] = '';
            $lines[] = '## Examples';
            foreach ($examples as $example) {
                $lines[] = '- `'.$example.'`';
            }
        }

        if ($spec->notes !== []) {
            $lines[] = '';
            $lines[] = '## Notes';
            foreach ($spec->notes as $note) {
                $lines[] = '- '.$note;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Catalog entry for one command. Unknown names throw `not_found` with the
     * unknown-command message: the /tools/vfs/commands mount (Stream B) maps
     * this onto a missing path, while shell discovery reports it as a command.
     *
     * @return array<string, mixed>
     */
    public static function commandEntry(string $command): array
    {
        $canonical = VfsCommandSpecRegistry::canonical($command);
        if ($canonical === null) {
            throw new VfsError('not_found', "Unknown VFS command: {$command}. Run `help` to list commands.", ['path' => $command, 'command' => $command]);
        }

        return VfsCommandSpecRegistry::all()[$canonical]->catalogEntry() + ['name' => $canonical];
    }

    private static function unknown(string $command): VfsError
    {
        return new VfsError('not_found', "Unknown VFS command: {$command}. Run `help` to list commands.", [
            'command' => $command,
            'status' => 'unknown',
        ]);
    }
}

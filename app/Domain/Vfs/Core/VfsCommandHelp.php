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

        $lines = ['# VFS Commands', '', 'Use `help <command>`, `<command> --help`, or `/tools/vfs/commands/<command>.json` for exact flags and examples.'];
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
            throw VfsError::notFound($command);
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
     * @return array<string, mixed>
     */
    public static function commandEntry(string $command): array
    {
        $canonical = VfsCommandSpecRegistry::canonical($command);
        if ($canonical === null) {
            throw VfsError::notFound($command);
        }

        return VfsCommandSpecRegistry::all()[$canonical]->catalogEntry() + ['name' => $canonical];
    }
}

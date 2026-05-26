<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;

/**
 * Read-only text commands for the VFS shell executor.
 *
 * The trait may inspect stdin or readable virtual paths, but it must not mutate
 * VFS state. Reads remain bounded by VfsBudget and routed through OpenCompanyVfs
 * so agents cannot bypass adapter policy.
 */
trait ExecutesVfsReadCommands
{
    private function cat(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertSupportedOptions($tokens, 'cat');
        $paths = $this->pathArgsWithValueOptions($tokens, $cwd, []);
        if ($paths === [] && $stdin !== null) {
            return ['stdout' => $stdin, 'cwd' => $cwd, 'class' => 'read'];
        }

        $chunks = array_map(fn (string $path): string => $this->vfs->read($agent, $path, $budget), $paths ?: [$cwd]);

        return ['stdout' => implode("\n", $chunks), 'cwd' => $cwd, 'class' => 'read'];
    }

    private function headTail(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin, bool $head): array
    {
        $command = $head ? 'head' : 'tail';
        $this->assertSupportedOptions($tokens, $command, ['-N'], ['-n', '--lines', '-c', '--bytes'], []);
        $shorthand = $this->headTailShorthand($tokens);
        $lineOption = $this->headTailOptionValue($tokens, ['-n', '--lines']);
        $byteOption = $this->headTailOptionValue($tokens, ['-c', '--bytes']);
        $count = $lineOption !== null
            ? $this->parseHeadTailLineCount($lineOption, $command)
            : ['count' => $shorthand['unit'] === 'lines' ? $shorthand['count'] : 10, 'from_start' => false];
        $byteCount = $byteOption !== null
            ? $this->parseHeadTailByteCount($byteOption, $command)
            : ($shorthand['unit'] === 'bytes' ? ['count' => $shorthand['count'], 'from_start' => false] : null);
        $content = $stdin;
        $paths = $this->pathArgsWithValueOptions($tokens, $cwd, ['-n', '--lines', '-c', '--bytes']);
        if ($content === null) {
            $content = $this->vfs->read($agent, $paths[0] ?? $cwd, $budget);
        }

        if ($byteCount !== null) {
            $bytes = max(0, $byteCount['count']);
            $output = match (true) {
                ! $head && $byteCount['from_start'] => substr($content, max(0, $bytes - 1)),
                $bytes === 0 => '',
                default => $head ? substr($content, 0, $bytes) : substr($content, -$bytes),
            };

            return [
                'stdout' => $output,
                'cwd' => $cwd,
                'class' => 'read',
            ];
        }

        $lines = VfsText::lines($content);
        $slice = match (true) {
            ! $head && $count['from_start'] => array_slice($lines, max(0, $count['count'] - 1)),
            $count['count'] === 0 => [],
            default => $head ? array_slice($lines, 0, $count['count']) : array_slice($lines, -$count['count']),
        };

        return ['stdout' => implode("\n", $slice), 'cwd' => $cwd, 'class' => 'read'];
    }

    private function headTailOptionValue(array $tokens, array $names): ?string
    {
        foreach ($tokens as $index => $token) {
            foreach ($names as $name) {
                if ($token === $name) {
                    return isset($tokens[$index + 1]) ? (string) $tokens[$index + 1] : '';
                }
                if (str_starts_with($name, '--') && str_starts_with($token, $name.'=')) {
                    return substr($token, strlen($name) + 1);
                }
                if (str_starts_with($name, '-') && ! str_starts_with($name, '--') && str_starts_with($token, $name) && strlen($token) > strlen($name)) {
                    return substr($token, strlen($name));
                }
            }
        }

        return null;
    }

    /**
     * @return array{count: int, from_start: bool}
     */
    private function parseHeadTailLineCount(string $value, string $command): array
    {
        if (preg_match('/^(\+?)(\d+)$/', $value, $matches) !== 1) {
            throw VfsError::invalid("{$command} line count requires a non-negative integer value.");
        }

        return ['count' => (int) $matches[2], 'from_start' => $matches[1] === '+'];
    }

    /**
     * @return array{count: int, from_start: bool}
     */
    private function parseHeadTailByteCount(string $value, string $command): array
    {
        if (preg_match('/^(\+?)(\d+)([kKmMgG])?$/', $value, $matches) !== 1) {
            throw VfsError::invalid("{$command} byte count requires a byte count.");
        }

        $count = (int) $matches[2];
        $count = match (strtolower($matches[3] ?? '')) {
            'k' => $count * 1024,
            'm' => $count * 1024 * 1024,
            'g' => $count * 1024 * 1024 * 1024,
            default => $count,
        };

        return ['count' => $count, 'from_start' => $matches[1] === '+'];
    }

    /**
     * GNU head/tail accept obsolete `-1`-style count syntax only in the first
     * position. The VFS supports the common line/byte forms while keeping the
     * safer explicit `-n`/`-c` forms discoverable in docs.
     *
     * @return array{count: int, unit: 'lines'|'bytes'|null}
     */
    private function headTailShorthand(array $tokens): array
    {
        $first = $tokens[0] ?? null;
        if (! is_string($first) || preg_match('/^-(\d+)([bc])?$/', $first, $matches) !== 1) {
            return ['count' => 10, 'unit' => null];
        }

        return [
            'count' => max(0, (int) $matches[1]),
            'unit' => ($matches[2] ?? '') === 'c' ? 'bytes' : 'lines',
        ];
    }

    private function wc(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertSupportedOptions($tokens, 'wc', ['l', 'w', 'c']);
        $content = $stdin;
        $paths = $this->pathArgsWithValueOptions($tokens, $cwd, []);
        if ($paths !== []) {
            $content = implode("\n", array_map(fn (string $path): string => $this->vfs->read($agent, $path, $budget), $paths));
        }
        if ($content === null && $paths === [] && $tokens !== []) {
            throw VfsError::invalid('wc requires stdin or a readable VFS path.');
        }
        if ($content === null) {
            $content = $this->vfs->read($agent, $paths[0] ?? $cwd, $budget);
        }

        $lines = substr_count($content, "\n");
        $words = str_word_count($content);
        $bytes = strlen($content);
        $flags = $this->shortFlags($tokens, ['l', 'w', 'c']);
        if ($flags !== []) {
            $parts = [];
            if ($flags['l'] ?? false) {
                $parts[] = (string) $lines;
            }
            if ($flags['w'] ?? false) {
                $parts[] = (string) $words;
            }
            if ($flags['c'] ?? false) {
                $parts[] = (string) $bytes;
            }

            return ['stdout' => implode(' ', $parts), 'cwd' => $cwd, 'class' => 'read'];
        }

        return ['stdout' => "{$lines} {$words} {$bytes}", 'cwd' => $cwd, 'class' => 'read'];
    }
}

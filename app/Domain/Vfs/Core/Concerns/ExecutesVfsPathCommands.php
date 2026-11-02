<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Path helpers and small builtins: realpath, dirname, basename, readlink,
 * env/printenv, date, and test / [ ].
 *
 * `test` is the shell's condition primitive: it only sets the exit status
 * (0 true, 1 false, 2 malformed), so `test -f x || touch x` and
 * `[ -s /files/out ] && cat /files/out` behave as in bash.
 */
trait ExecutesVfsPathCommands
{
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function pathTransform(array $words, string $cwd, string $kind): array
    {
        $args = $this->args($kind, $words);
        if ($kind === 'basename') {
            $name = $args->operands[0] ?? throw VfsError::invalid('basename requires a path.');
            $base = basename(rtrim($name, '/')) ?: '/';
            $suffix = $args->operands[1] ?? null;
            if ($suffix !== null && $suffix !== $base && str_ends_with($base, $suffix)) {
                $base = substr($base, 0, -strlen($suffix));
            }

            return ['stdout' => $base."\n", 'cwd' => $cwd, 'class' => 'browse'];
        }
        if ($kind === 'dirname') {
            $name = $args->operands[0] ?? throw VfsError::invalid('dirname requires a path.');
            $parent = dirname(rtrim($name, '/') ?: '/');

            return ['stdout' => ($parent === '\\' ? '/' : $parent)."\n", 'cwd' => $cwd, 'class' => 'browse'];
        }

        $lines = [];
        foreach ($args->operands ?: ['.'] as $operand) {
            $path = $this->path($operand, $cwd);
            if ($args->has('e')) {
                $this->vfs->stat($this->agentForPathExpansion(), $path);
            }
            $lines[] = $path;
        }

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'browse'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function readlink(array $words, string $cwd): array
    {
        $args = $this->args('readlink', $words);
        $operand = $args->operands[0] ?? throw VfsError::invalid('readlink requires a path.');
        $path = $this->path($operand, $cwd);
        if ($args->has('e')) {
            $this->vfs->stat($this->agentForPathExpansion(), $path);
        }

        return ['stdout' => $path.($args->has('n') ? '' : "\n"), 'cwd' => $cwd, 'class' => 'browse'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function env(User $agent, array $words, string $cwd): array
    {
        foreach ($words as $word) {
            if (str_starts_with($word, '-') || str_contains($word, '=')) {
                throw VfsError::unsupported('env assignment, options, and command execution forms are not supported in VFS commands.', ['suggestion' => 'The VFS has no variables; use literal values, or Code Mode (code_exec).']);
            }
        }
        if (count($words) > 1) {
            throw VfsError::invalid('env accepts at most one variable name.');
        }
        $values = [
            'VFS' => 'OpenCompany',
            'PWD' => $cwd,
            'WORKSPACE_ID' => (string) $agent->workspace_id,
            'AGENT_ID' => (string) $agent->id,
        ];

        if (isset($words[0])) {
            return array_key_exists($words[0], $values)
                ? ['stdout' => $values[$words[0]]."\n", 'cwd' => $cwd, 'class' => 'browse']
                : ['stdout' => '', 'cwd' => $cwd, 'class' => 'browse', 'exit_code' => 1];
        }

        return [
            'stdout' => $this->linesOut(array_map(fn (string $key, string $value): string => "{$key}={$value}", array_keys($values), $values)),
            'cwd' => $cwd,
            'class' => 'browse',
        ];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function dateCommand(array $words, string $cwd): array
    {
        $args = $this->args('date', $words);
        $when = $args->value('-d');
        try {
            $date = $when === null ? now() : Carbon::parse($when);
        } catch (\Throwable) {
            throw VfsError::invalid("date: invalid date \"{$when}\".");
        }
        if ($args->has('u')) {
            $date = $date->utc();
        }
        $format = null;
        foreach ($args->operands as $operand) {
            if (str_starts_with($operand, '+')) {
                $format = substr($operand, 1);
            } else {
                throw VfsError::invalid("date: setting the date is not supported (\"{$operand}\").", ['hint' => 'Use +FORMAT, e.g. date +%Y-%m-%d']);
            }
        }
        $stdout = match (true) {
            $format !== null => $this->strftime($format, $date),
            $args->has('I') => $date->toDateString(),
            $args->has('R') => $date->toRfc2822String(),
            default => $date->toIso8601String(),
        };

        return ['stdout' => $stdout."\n", 'cwd' => $cwd, 'class' => 'browse'];
    }

    private function strftime(string $format, Carbon $date): string
    {
        return preg_replace_callback('/%(.)/', fn (array $m): string => match ($m[1]) {
            'Y' => $date->format('Y'), 'm' => $date->format('m'), 'd' => $date->format('d'), 'H' => $date->format('H'),
            'M' => $date->format('i'), 'S' => $date->format('s'), 'F' => $date->format('Y-m-d'), 'T' => $date->format('H:i:s'),
            's' => (string) $date->getTimestamp(), 'a' => $date->format('D'), 'A' => $date->format('l'), 'b' => $date->format('M'),
            'B' => $date->format('F'), 'Z' => $date->format('T'), 'z' => $date->format('O'), 'j' => str_pad((string) ((int) $date->format('z') + 1), 3, '0', STR_PAD_LEFT),
            'u' => $date->format('N'), 'y' => $date->format('y'), 'e' => str_pad($date->format('j'), 2, ' ', STR_PAD_LEFT), 'D' => $date->format('m/d/y'),
            'R' => $date->format('H:i'), 'n' => "\n", 't' => "\t", '%' => '%',
            default => $m[0],
        }, $format) ?? $format;
    }

    /**
     * test EXPR / [ EXPR ].
     *
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function testCommand(User $agent, array $words, string $cwd): array
    {
        if ($words === []) {
            return ['stdout' => '', 'class' => 'browse', 'exit_code' => 1];
        }
        $index = 0;
        try {
            $result = $this->testOr($agent, $words, $index, $cwd);
            if ($index !== count($words)) {
                throw VfsError::invalid('test: unexpected argument "'.$words[$index].'".');
            }
        } catch (VfsError $e) {
            if ($e->errorCode !== 'invalid_request') {
                throw $e;
            }

            return ['stdout' => '', 'stderr' => $e->getMessage(), 'class' => 'browse', 'exit_code' => 2];
        }

        return ['stdout' => '', 'class' => 'browse', 'exit_code' => $result ? 0 : 1];
    }

    /**
     * @param  list<string>  $w
     */
    private function testOr(User $agent, array $w, int &$i, string $cwd): bool
    {
        $left = $this->testAnd($agent, $w, $i, $cwd);
        while (($w[$i] ?? null) === '-o') {
            $i++;
            $right = $this->testAnd($agent, $w, $i, $cwd);
            $left = $left || $right;
        }

        return $left;
    }

    /**
     * @param  list<string>  $w
     */
    private function testAnd(User $agent, array $w, int &$i, string $cwd): bool
    {
        $left = $this->testNot($agent, $w, $i, $cwd);
        while (($w[$i] ?? null) === '-a') {
            $i++;
            $right = $this->testNot($agent, $w, $i, $cwd);
            $left = $left && $right;
        }

        return $left;
    }

    /**
     * @param  list<string>  $w
     */
    private function testNot(User $agent, array $w, int &$i, string $cwd): bool
    {
        if (($w[$i] ?? null) === '!' && isset($w[$i + 1])) {
            $i++;

            return ! $this->testNot($agent, $w, $i, $cwd);
        }
        if (($w[$i] ?? null) === '(') {
            $i++;
            $value = $this->testOr($agent, $w, $i, $cwd);
            if (($w[$i] ?? null) !== ')') {
                throw VfsError::invalid('test: missing ).');
            }
            $i++;

            return $value;
        }

        return $this->testPrimary($agent, $w, $i, $cwd);
    }

    /**
     * @param  list<string>  $w
     */
    private function testPrimary(User $agent, array $w, int &$i, string $cwd): bool
    {
        $first = $w[$i] ?? throw VfsError::invalid('test: missing argument.');
        $second = $w[$i + 1] ?? null;
        $third = $w[$i + 2] ?? null;

        if ($third !== null && in_array($second, ['=', '==', '!=', '<', '>', '-eq', '-ne', '-lt', '-le', '-gt', '-ge'], true)) {
            $i += 3;
            if (in_array($second, ['=', '==', '!=', '<', '>'], true)) {
                return match ($second) {
                    '=', '==' => $first === $third,
                    '!=' => $first !== $third,
                    '<' => strcmp($first, $third) < 0,
                    default => strcmp($first, $third) > 0,
                };
            }
            if (preg_match('/^-?\d+$/', trim($first)) !== 1 || preg_match('/^-?\d+$/', trim($third)) !== 1) {
                throw VfsError::invalid("test: integer expression expected: \"{$first}\" {$second} \"{$third}\".");
            }
            $a = (int) $first;
            $b = (int) $third;

            return match ($second) {
                '-eq' => $a === $b, '-ne' => $a !== $b, '-lt' => $a < $b, '-le' => $a <= $b, '-gt' => $a > $b, default => $a >= $b,
            };
        }

        if ($second !== null && in_array($first, ['-e', '-f', '-d', '-s', '-z', '-n', '-r', '-w', '-x', '-L', '-h'], true)) {
            $i += 2;
            if ($first === '-z') {
                return $second === '';
            }
            if ($first === '-n') {
                return $second !== '';
            }
            if (in_array($first, ['-r', '-w', '-x', '-L', '-h'], true)) {
                throw VfsError::unsupported("test {$first} is not supported in the VFS.", ['suggestion' => 'Use test -e/-f/-d/-s; read capabilities with stat PATH.']);
            }
            $path = $this->path($second, $cwd);
            try {
                $stat = $this->vfs->stat($agent, $path);
            } catch (VfsError $e) {
                if (in_array($e->errorCode, ['not_found', 'permission_denied', 'not_readable'], true)) {
                    return false;
                }
                throw $e;
            }
            $type = $stat['type'] ?? 'file';

            return match ($first) {
                '-e' => true,
                '-f' => $type !== 'directory',
                '-d' => $type === 'directory',
                default => $type === 'directory' ? true : $this->entryBytes($agent, $path, $stat) > 0,
            };
        }

        $i++;

        return $first !== '';
    }
}

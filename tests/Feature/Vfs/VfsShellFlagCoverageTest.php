<?php

namespace Tests\Feature\Vfs;

use App\Domain\Vfs\Core\VfsCommandSpec;
use App\Domain\Vfs\Core\VfsCommandSpecRegistry;
use Tests\TestCase;

/**
 * Registry drift guard: every flag the VFS command registry advertises is
 * either exercised by a command string in the VFS test suite or listed in
 * ALLOWLIST with the reason it needs no dedicated test.
 *
 * The audit found ~20 advertised flags that silently did nothing (ls -a,
 * grep --type, cp -r, mkdir -p, sort -k modifiers, jq -r, ...). This test is
 * what keeps "advertised" meaning "implemented": adding a flag to
 * VfsCommandSpecRegistry without a test that uses it fails here.
 *
 * Coverage is collected statically from string literals in
 * tests/Feature/Vfs/*.php that look like command lines, so it also counts
 * flags exercised by older feature tests.
 */
class VfsShellFlagCoverageTest extends TestCase
{
    /**
     * command => flag => reason the flag needs no dedicated test.
     *
     * @var array<string, array<string, string>>
     */
    private const ALLOWLIST = [
        'pwd' => [
            '-P' => 'The VFS has no symlinks: -P and -L print the same path (documented in notes).',
            '-L' => 'The VFS has no symlinks: -P and -L print the same path (documented in notes).',
        ],
        'ls' => [
            '-1' => 'Output is always one entry per line; -1 is accepted for muscle memory (documented).',
            '-F' => 'Directories always end with "/"; -F changes nothing (documented).',
        ],
        'zip' => [
            '-q' => 'zip is always quiet; -q changes nothing (documented).',
        ],
        'mktemp' => [
            '-t' => 'Templates are always created under -p DIR or /files/tmp; -t changes nothing (documented).',
        ],
        'bc' => [
            '-l' => 'Arithmetic only; -l/-q are accepted for compatibility and change nothing (documented).',
            '-q' => 'Arithmetic only; -l/-q are accepted for compatibility and change nothing (documented).',
        ],
        'mkdir' => [
            '-v' => 'Mutations always print one status line per changed path; -v prints the same lines (documented).',
            '--verbose' => 'Long alias of -v.',
        ],
        'cp' => [
            '-v' => 'Mutations always print one status line per changed path (documented).',
            '-R' => 'Same code path as -r (cp -r is exercised).',
            '-a' => 'Same code path as -r; timestamps are not preserved (documented).',
        ],
        'mv' => [
            '-v' => 'Mutations always print one status line per changed path (documented).',
        ],
        'rm' => [
            '-v' => 'Mutations always print one status line per changed path (documented).',
            '-R' => 'Same code path as -r.',
        ],
        'ln' => [
            '-v' => 'Mutations always print one status line per changed path (documented).',
        ],
    ];

    public function test_every_advertised_flag_is_exercised_or_allowlisted(): void
    {
        $covered = $this->coveredFlags();
        $missing = [];
        foreach (VfsCommandSpecRegistry::all() as $name => $spec) {
            if ($spec->status !== 'implemented') {
                continue;
            }
            foreach ($spec->supportedFlagList() as $flag) {
                // A long alias runs the exact code of its target (VfsArgs maps
                // it), so it is covered when the target is; the mapping itself
                // is tested in VfsShellFlagsTest.
                $target = $spec->longAliases[$flag] ?? null;
                if (isset($covered[$name][$flag]) || isset(self::ALLOWLIST[$name][$flag]) || ($target !== null && (isset($covered[$name][$target]) || isset(self::ALLOWLIST[$name][$target])))) {
                    continue;
                }
                $missing[] = "{$name} {$flag}";
            }
        }

        $this->assertSame([], $missing, "Advertised flags without a test or an allowlist reason:\n".implode("\n", $missing));
    }

    // A long alias must point at a flag the command really has (rg once
    // inherited grep's --recursive/--extended-regexp aliases and failed).
    public function test_long_aliases_target_real_flags(): void
    {
        foreach (VfsCommandSpecRegistry::all() as $name => $spec) {
            $flags = array_values(array_diff($spec->supportedFlagList(), array_keys($spec->longAliases)));
            foreach ($spec->longAliases as $alias => $target) {
                $this->assertContains($target, $flags, "{$name} {$alias} -> {$target}");
            }
        }
    }

    // The allowlist itself must not rot: every entry names a real flag.
    public function test_allowlist_entries_are_real_flags(): void
    {
        foreach (self::ALLOWLIST as $command => $flags) {
            $spec = VfsCommandSpecRegistry::get($command);
            $this->assertInstanceOf(VfsCommandSpec::class, $spec, $command);
            foreach (array_keys($flags) as $flag) {
                // (string): PHP turns the array key "-1" into the integer -1.
                $this->assertContains((string) $flag, $spec->supportedFlagList(), "{$command} {$flag}");
            }
        }
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function coveredFlags(): array
    {
        $covered = [];
        foreach (glob(base_path('tests/Feature/Vfs/*.php')) ?: [] as $file) {
            // PHP's own tokenizer: comments and apostrophes cannot confuse it.
            foreach (token_get_all((string) file_get_contents($file)) as $token) {
                if (! is_array($token) || ! in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                    continue;
                }
                $text = $token[1];
                if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $text = $text[0] === "'"
                        ? str_replace(["\\'", '\\\\'], ["'", '\\'], substr($text, 1, -1))
                        : stripcslashes(substr($text, 1, -1));
                } else {
                    $text = stripcslashes($text);
                }
                foreach (preg_split('/\|\||&&|[|;\n]/', $text) ?: [] as $segment) {
                    $this->collectSegment(preg_split('/\s+/', trim($segment)) ?: [], $covered);
                }
            }
        }

        return $covered;
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<string, array<string, true>>  $covered
     */
    private function collectSegment(array $tokens, array &$covered): void
    {
        $name = array_shift($tokens);
        if ($name === null) {
            return;
        }
        $name = trim($name, '\'"');
        $spec = VfsCommandSpecRegistry::get($name);
        if ($spec === null) {
            return;
        }
        $canonical = $spec->name;
        if ($name === 'egrep' || $name === 'fgrep') {
            $covered[$canonical][$name === 'egrep' ? '-E' : '-F'] = true;
        }
        $shortValue = [];
        foreach ($spec->valueOptions as $option) {
            if (strlen($option) === 2) {
                $shortValue[$option[1]] = true;
            }
        }

        foreach ($tokens as $index => $token) {
            // Shell-escaped operators (\( \)) reach the command unescaped.
            $token = ltrim(trim($token, '\'"'), '\\');
            if ($token === '--') {
                return;
            }
            if (in_array($token, ['!', '(', ')'], true) && in_array($token, $spec->supportedFlagList(), true)) {
                $covered[$canonical][$token] = true;

                continue;
            }
            if ($canonical === 'xargs' && ! str_starts_with($token, '-')) {
                // The inner command of xargs is its own segment.
                $this->collectSegment(array_slice($tokens, $index), $covered);

                return;
            }
            if (! str_starts_with($token, '-') || $token === '-') {
                continue;
            }
            $flag = str_contains($token, '=') ? explode('=', $token, 2)[0] : $token;
            if (in_array($flag, $spec->supportedFlagList(), true)) {
                $covered[$canonical][$flag] = true;
                if (isset($spec->longAliases[$flag])) {
                    $covered[$canonical][$spec->longAliases[$flag]] = true;
                }

                continue;
            }
            if ($spec->numericShorthand && preg_match('/^-\d+[a-z]?$/', $token) === 1) {
                $covered[$canonical]['-N'] = true;

                continue;
            }
            if (str_starts_with($token, '--')) {
                continue;
            }
            foreach (str_split(substr($token, 1)) as $letter) {
                $covered[$canonical]['-'.$letter] = true;
                if (isset($shortValue[$letter])) {
                    break;
                }
            }
        }
    }
}

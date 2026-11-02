<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsPrintf;

/**
 * Output builtins: echo and printf.
 *
 * printf uses bash semantics via VfsPrintf: extra arguments re-use the format,
 * missing ones print as empty/0, and it never fails on argument count (PHP's
 * sprintf used to throw ArgumentCountError here).
 */
trait ExecutesVfsOutputTransforms
{
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function echo(array $words): array
    {
        $newline = true;
        $escapes = false;
        // Only leading words made purely of n/e/E letters are options (bash).
        while (isset($words[0]) && ! ($this->argLiteral[0] ?? false) && preg_match('/^-[neE]+$/', $words[0]) === 1) {
            $flags = substr(array_shift($words), 1);
            array_shift($this->argLiteral);
            $newline = $newline && ! str_contains($flags, 'n');
            if (str_contains($flags, 'e')) {
                $escapes = true;
            }
            if (str_contains($flags, 'E')) {
                $escapes = false;
            }
        }
        $stdout = implode(' ', $words);
        if ($escapes) {
            // bash: `\c` ends the output, including the trailing newline.
            $stopped = false;
            $stdout = VfsPrintf::escapes($stdout, true, $stopped);
            $newline = $newline && ! $stopped;
        }

        return ['stdout' => $stdout.($newline ? "\n" : ''), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function printf(array $words): array
    {
        $format = array_shift($words);
        if ($format === null) {
            throw VfsError::invalid('printf requires a format string.', ['hint' => 'printf "%s\n" value']);
        }

        return ['stdout' => VfsPrintf::shell($format, $words), 'class' => 'read-transform'];
    }
}

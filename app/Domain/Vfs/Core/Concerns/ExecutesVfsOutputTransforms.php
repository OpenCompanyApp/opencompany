<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;

/**
 * Output-only shell builtins and tiny line transforms for VFS pipelines.
 */
trait ExecutesVfsOutputTransforms
{
    private function lineTransform(?string $stdin, callable $transform): array
    {
        $lines = VfsText::lines($stdin ?? '');

        return ['stdout' => implode("\n", $transform($lines)), 'class' => 'read-transform'];
    }

    private function echo(array $tokens): array
    {
        $newline = true;
        $interpretEscapes = false;
        while (isset($tokens[0]) && in_array($tokens[0], ['-n', '-e', '-ne', '-en'], true)) {
            $newline = $newline && ! str_contains($tokens[0], 'n');
            $interpretEscapes = $interpretEscapes || str_contains($tokens[0], 'e');
            array_shift($tokens);
        }
        $stdout = implode(' ', $tokens);
        if ($interpretEscapes) {
            $stdout = stripcslashes($stdout);
        }

        return ['stdout' => $stdout.($newline ? "\n" : ''), 'class' => 'read-transform'];
    }

    private function printf(array $tokens): array
    {
        $format = array_shift($tokens);
        if ($format === null) {
            return ['stdout' => '', 'class' => 'read-transform'];
        }

        $format = stripcslashes($format);
        try {
            $arity = preg_match_all('/(?<!%)%(?:\d+\$)?[-+ 0#]*(?:\*|\d+)?(?:\.(?:\*|\d+))?[bcdeEfFgGosuxX]/', $format);
            if ($tokens === [] || $arity < 1) {
                $stdout = $format;
            } else {
                $stdout = '';
                foreach (array_chunk($tokens, $arity) as $chunk) {
                    $stdout .= sprintf($format, ...$chunk);
                }
            }
        } catch (\ValueError) {
            throw VfsError::invalid('printf format does not match provided arguments.');
        }

        return ['stdout' => $stdout, 'class' => 'read-transform'];
    }

    private function nl(?string $stdin): array
    {
        $lines = [];
        foreach (VfsText::lines($stdin ?? '') as $index => $line) {
            $lines[] = str_pad((string) ($index + 1), 6, ' ', STR_PAD_LEFT)."\t".$line;
        }

        return ['stdout' => implode("\n", $lines), 'class' => 'read-transform'];
    }

    private function rev(?string $stdin): array
    {
        return $this->lineTransform($stdin, fn (array $lines): array => array_map('strrev', $lines));
    }
}

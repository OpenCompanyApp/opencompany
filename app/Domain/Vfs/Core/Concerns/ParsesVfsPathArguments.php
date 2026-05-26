<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;

/**
 * Virtual path extraction, normalization, and shell-style glob expansion.
 */
trait ParsesVfsPathArguments
{
    private function pathArgs(array $tokens, string $cwd): array
    {
        $paths = [];
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (in_array($token, ['-n', '--lines', '-c', '--bytes', '-d', '-f', '-name', '-type', '-maxdepth', '-maxDepth', '--max-depth', '-L', '--level', '-k', '-F', '--max-count', '-m', '-s', '--size'], true)) {
                $i++;

                continue;
            }
            if ($this->isInlineValueOption($token, ['--lines=', '--bytes=', '--level=', '--max-depth=', '--max-count=', '--size='])) {
                continue;
            }
            if (str_starts_with($token, '-')) {
                continue;
            }
            array_push($paths, ...$this->expandPathArg($token, $cwd, ! ($this->lastTokenQuoteMask[$i] ?? false)));
        }

        return $paths;
    }

    private function pathArgsWithValueOptions(array $tokens, string $cwd, array $valueOptions): array
    {
        $paths = [];
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (in_array($token, $valueOptions, true)) {
                $i++;

                continue;
            }
            if ($this->isInlineValueOption($token, array_map(fn (string $option): string => $option.'=', array_filter($valueOptions, fn (string $option): bool => str_starts_with($option, '--'))))) {
                continue;
            }
            if (str_starts_with($token, '-')) {
                continue;
            }
            array_push($paths, ...$this->expandPathArg($token, $cwd, ! ($this->lastTokenQuoteMask[$i] ?? false)));
        }

        return $paths;
    }

    private function flagsAndPathArgs(array $tokens, string $cwd, array $allowed): array
    {
        $flags = [];
        $paths = [];
        foreach ($tokens as $index => $token) {
            if (str_starts_with($token, '-') && $token !== '-') {
                foreach (str_split(ltrim($token, '-')) as $flag) {
                    if (! in_array($flag, $allowed, true)) {
                        throw VfsError::unsupported("Unsupported ls flag: -{$flag}", [
                            'supported_flags' => array_map(fn (string $value): string => "-{$value}", $allowed),
                        ]);
                    }
                    $flags[$flag] = true;
                }

                continue;
            }

            array_push($paths, ...$this->expandPathArg($token, $cwd, ! ($this->lastTokenQuoteMask[$index] ?? false)));
        }

        return [$flags, $paths];
    }

    private function firstPathArg(array $tokens, string $cwd): ?string
    {
        return $this->pathArgs($tokens, $cwd)[0] ?? null;
    }

    private function path(string $path, string $cwd): string
    {
        return $this->vfs->normalizePath($path, $cwd);
    }

    /**
     * @return list<string>
     */
    private function expandPathArg(string $token, string $cwd, bool $allowGlob = true): array
    {
        $path = $this->path($token, $cwd);
        if (! $allowGlob || (! str_contains($token, '*') && ! str_contains($token, '?') && ! str_contains($token, '['))) {
            return [$path];
        }

        $parent = $this->path(dirname($path), '/');
        $pattern = basename($path);

        try {
            $matches = [];
            foreach ($this->vfs->list($this->currentAgent, $parent, new VfsBudget(maxEntries: 1_000)) as $entry) {
                if (fnmatch($pattern, $entry->name)) {
                    $matches[] = $entry->path;
                }
            }
            sort($matches);

            return $matches === [] ? [$path] : array_values(array_unique($matches));
        } catch (\Throwable) {
            return [$path];
        }
    }
}

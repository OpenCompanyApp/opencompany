<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;

/**
 * Option parsing and validation helpers shared by the unix-like VFS commands.
 */
trait ParsesVfsCommandOptions
{
    private function shortFlags(array $tokens, array $allowed): array
    {
        $flags = [];
        foreach ($tokens as $token) {
            if (! str_starts_with($token, '-') || $token === '-') {
                continue;
            }
            if (in_array($token, ['-name', '-type', '-maxdepth', '-maxDepth', '--max-depth', '-L', '--level', '-k', '-F', '--max-count', '-m', '-n', '--lines', '-c', '--bytes', '-s', '--size'], true)) {
                $short = ltrim($token, '-');
                if (str_starts_with($token, '--') || strlen($short) !== 1 || ! in_array($short, $allowed, true)) {
                    continue;
                }
            }
            if ($this->isInlineValueOption($token, ['--lines=', '--bytes=', '--level=', '--max-depth=', '--max-count=', '--size='])) {
                continue;
            }
            if (preg_match('/^-[A-Za-z][+0-9]/', $token) === 1) {
                continue;
            }
            if (in_array($token, ['-d', '-f'], true) && ! in_array(ltrim($token, '-'), $allowed, true)) {
                continue;
            }
            if (str_starts_with($token, '--')) {
                throw VfsError::unsupported("Unsupported flag: {$token}");
            }
            foreach (str_split(ltrim($token, '-')) as $flag) {
                if (! in_array($flag, $allowed, true)) {
                    throw VfsError::unsupported("Unsupported flag: -{$flag}", [
                        'supported_flags' => array_map(fn (string $value): string => "-{$value}", $allowed),
                    ]);
                }
                $flags[$flag] = true;
            }
        }

        return $flags;
    }

    private function optionValue(array $tokens, array $names): ?string
    {
        foreach ($tokens as $index => $token) {
            if (in_array($token, $names, true)) {
                if (! isset($tokens[$index + 1])) {
                    return null;
                }
                $value = (string) $tokens[$index + 1];
                if (str_starts_with($value, '-') && ! ($this->lastTokenQuoteMask[$index + 1] ?? false)) {
                    throw VfsError::unsupported("Unsupported flag used as {$token} value: {$value}");
                }

                return $value;
            }
            foreach ($names as $name) {
                if (str_starts_with($name, '--') && str_starts_with($token, $name.'=')) {
                    return substr($token, strlen($name) + 1);
                }
                if ($this->isAttachedShortValue($token, $name)) {
                    return substr($token, strlen($name));
                }
            }
        }

        return null;
    }

    private function optionWasProvided(array $tokens, array $names): bool
    {
        foreach ($tokens as $token) {
            if (in_array($token, $names, true)) {
                return true;
            }
            foreach ($names as $name) {
                if (str_starts_with($name, '--') && str_starts_with($token, $name.'=')) {
                    return true;
                }
                if ($this->isAttachedShortValue($token, $name)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function integerOptionValue(array $tokens, array $names, string $command, string $label): ?int
    {
        if (! $this->optionWasProvided($tokens, $names)) {
            return null;
        }

        $value = $this->optionValue($tokens, $names);
        if ($value === null || ! preg_match('/^\d+$/', $value)) {
            throw VfsError::invalid("{$command} {$label} requires a non-negative integer value.");
        }

        return (int) $value;
    }

    private function byteOptionValue(array $tokens, array $names, string $command, string $label): ?int
    {
        if (! $this->optionWasProvided($tokens, $names)) {
            return null;
        }

        $value = $this->optionValue($tokens, $names);
        if ($value === null || preg_match('/^(\d+)([kKmMgG])?$/', $value, $matches) !== 1) {
            throw VfsError::invalid("{$command} {$label} requires a byte count.");
        }

        $count = (int) $matches[1];

        return match (strtolower($matches[2] ?? '')) {
            'k' => $count * 1024,
            'm' => $count * 1024 * 1024,
            'g' => $count * 1024 * 1024 * 1024,
            default => $count,
        };
    }

    private function optionValueWithInline(array $tokens, string $name): ?string
    {
        foreach ($tokens as $index => $token) {
            if ($token === $name) {
                return isset($tokens[$index + 1]) ? (string) $tokens[$index + 1] : null;
            }
            if (str_starts_with($token, $name) && strlen($token) > strlen($name)) {
                return substr($token, strlen($name));
            }
        }

        return null;
    }

    private function isInlineValueOption(string $token, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($token, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isAttachedShortValue(string $token, string $name): bool
    {
        if (str_starts_with($name, '--') || strlen($name) !== 2 || ! str_starts_with($token, $name)) {
            return false;
        }

        $value = substr($token, strlen($name));

        return $value !== '' && preg_match('/^[+0-9]/', $value) === 1;
    }
}

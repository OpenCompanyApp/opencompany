<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;

/**
 * Safe binary-adjacent inspection commands backed by bounded VFS text reads.
 *
 * These commands do not make the VFS a binary/archive engine. They cover common
 * unix inspection affordances that are useful over the current text-oriented
 * adapter contract, while archive extraction and multi-output writes remain
 * intentionally outside this concern.
 */
trait ExecutesVfsBinaryInspectionCommands
{
    private function gzip(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'gzip');
        $flags = $this->shortFlags($tokens, ['d', 'k', 'f', 'c', '1', '2', '3', '4', '5', '6', '7', '8', '9']);
        if ($flags['d'] ?? false) {
            return $this->gunzip($agent, $tokens, $cwd, $budget, $stdin, 'gzip');
        }

        $level = -1;
        foreach (range(1, 9) as $candidate) {
            if ($flags[(string) $candidate] ?? false) {
                $level = $candidate;
            }
        }
        $content = $this->contentFromStdinOrPaths($agent, $tokens, $cwd, $budget, $stdin, []);
        $encoded = gzencode($content, $level);
        if ($encoded === false) {
            throw VfsError::invalid('gzip could not compress the input.');
        }

        return ['stdout' => $encoded, 'class' => 'read-transform'];
    }

    private function gunzip(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin, string $command = 'gunzip'): array
    {
        $this->assertSupportedOptionsForCommand($tokens, $command);
        $content = $this->contentFromStdinOrPaths($agent, $tokens, $cwd, $budget, $stdin, []);
        $decoded = gzdecode($content);
        if ($decoded === false) {
            throw VfsError::invalid("{$command} input is not valid gzip data.");
        }
        if ($command === 'gunzip' && ($this->shortFlags($tokens, ['k', 'f', 'c', 't'])['t'] ?? false)) {
            return ['stdout' => '', 'class' => 'read-transform'];
        }

        return ['stdout' => VfsText::truncate($decoded, $budget->maxBytes), 'class' => 'read-transform'];
    }

    private function zgrep(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'zgrep');
        [$pattern, $paths] = $this->zgrepPatternAndPaths($tokens, $cwd);
        $flags = $this->shortFlags($tokens, ['i', 'c', 'l', 'n', 'v', 'E', 'F', 'H', 'h', 'o', 'q', 'w']);
        $regex = ($flags['E'] ?? false) && ! ($flags['F'] ?? false);
        $maxCount = $this->integerOptionValue($tokens, ['-m'], 'zgrep', 'max count');
        $before = $this->integerOptionValue($tokens, ['-B'], 'zgrep', 'before context') ?? 0;
        $after = $this->integerOptionValue($tokens, ['-A'], 'zgrep', 'after context') ?? 0;
        $context = $this->integerOptionValue($tokens, ['-C'], 'zgrep', 'context');
        if ($context !== null) {
            $before = $context;
            $after = $context;
        }

        if ($stdin !== null && $paths === []) {
            $decoded = gzdecode($stdin);
            if ($decoded === false) {
                throw VfsError::invalid('zgrep stdin is not valid gzip data.');
            }

            return $this->grepContent($decoded, $pattern, $regex, $flags, $maxCount, $before, $after, null, false, $cwd);
        }

        $outputs = [];
        $count = 0;
        foreach ($paths as $path) {
            $decoded = gzdecode($this->vfs->read($agent, $path, $budget));
            if ($decoded === false) {
                throw VfsError::invalid("{$path} is not valid gzip data.");
            }
            $result = $this->grepContent($decoded, $pattern, $regex, $flags, $maxCount, $before, $after, $path, count($paths) > 1 || ($flags['H'] ?? false), $cwd);
            if (($flags['l'] ?? false) && (int) ($result['count'] ?? 0) > 0) {
                $outputs[] = $path;
            } elseif (! ($flags['l'] ?? false)) {
                $outputs[] = (string) $result['stdout'];
            }
            $count += (int) ($result['count'] ?? 0);
        }

        return ['stdout' => implode("\n", array_filter($outputs, fn (string $line): bool => $line !== '')), 'cwd' => $cwd, 'class' => 'read-transform', 'count' => $count];
    }

    private function xxd(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'xxd');
        $flags = $this->shortFlags($tokens, ['r', 'p', 'u']);
        $content = $this->contentFromStdinOrPaths($agent, $tokens, $cwd, $budget, $stdin, ['-l', '-c', '-s', '-g']);

        if ($flags['r'] ?? false) {
            if (! ($flags['p'] ?? false)) {
                throw VfsError::unsupported('xxd reverse mode currently supports only plain hex with -r -p.');
            }
            $hex = preg_replace('/[^0-9a-f]/i', '', $content) ?? '';
            if ($hex === '' || strlen($hex) % 2 !== 0) {
                throw VfsError::invalid('xxd -r -p requires an even number of hex digits.');
            }
            $decoded = hex2bin($hex);
            if ($decoded === false) {
                throw VfsError::invalid('xxd -r -p input is not valid hex.');
            }

            return ['stdout' => $decoded, 'class' => 'read-transform'];
        }

        $offset = max(0, (int) ($this->optionValue($tokens, ['-s']) ?? 0));
        $length = $this->integerOptionValue($tokens, ['-l'], 'xxd', 'length');
        $width = max(1, min(256, (int) ($this->optionValue($tokens, ['-c']) ?? 16)));
        $group = max(1, min($width, (int) ($this->optionValue($tokens, ['-g']) ?? 2)));
        $slice = substr($content, $offset, $length ?? null);
        $hex = bin2hex($slice);
        if ($flags['u'] ?? false) {
            $hex = strtoupper($hex);
        }
        if ($flags['p'] ?? false) {
            return ['stdout' => trim(chunk_split($hex, $width * 2, "\n")), 'class' => 'read-transform'];
        }

        $lines = [];
        for ($i = 0; $i < strlen($slice); $i += $width) {
            $chunk = substr($slice, $i, $width);
            $chunkHex = bin2hex($chunk);
            if ($flags['u'] ?? false) {
                $chunkHex = strtoupper($chunkHex);
            }
            $grouped = trim(implode(' ', str_split($chunkHex, $group * 2)));
            $ascii = preg_replace('/[^\x20-\x7E]/', '.', $chunk) ?? '';
            $lines[] = sprintf('%08x: %-'.($width * 2 + max(0, (int) ceil($width / $group) - 1)).'s  %s', $offset + $i, $grouped, $ascii);
        }

        return ['stdout' => implode("\n", $lines), 'class' => 'read-transform'];
    }

    private function strings(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'strings');
        $min = max(1, (int) ($this->optionValue($tokens, ['-n']) ?? 4));
        $content = $this->contentFromStdinOrPaths($agent, $tokens, $cwd, $budget, $stdin, ['-n']);
        preg_match_all('/[\x20-\x7E]{'.$min.',}/', $content, $matches);

        return ['stdout' => implode("\n", $matches[0] ?? []), 'class' => 'read-transform'];
    }

    private function iconv(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'iconv');
        if ($this->optionWasProvided($tokens, ['-o'])) {
            throw VfsError::unsupported('iconv -o writes are not supported; redirect stdout to a writable /files path instead.');
        }
        if (! function_exists('iconv')) {
            throw VfsError::unsupported('PHP iconv support is not available in this runtime.');
        }

        $from = $this->optionValue($tokens, ['-f']) ?? 'UTF-8';
        $to = $this->optionValue($tokens, ['-t']) ?? 'UTF-8';
        if ($this->shortFlags($tokens, ['c'])['c'] ?? false) {
            $to .= '//IGNORE';
        }
        $content = $this->contentFromStdinOrPaths($agent, $tokens, $cwd, $budget, $stdin, ['-f', '-t', '-o']);
        $converted = @iconv($from, $to, $content);
        if ($converted === false) {
            throw VfsError::invalid('iconv could not convert the input with the requested encodings.');
        }

        return ['stdout' => $converted, 'class' => 'read-transform'];
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function zgrepPatternAndPaths(array $tokens, string $cwd): array
    {
        $pattern = $this->optionValue($tokens, ['-e']);
        $paths = [];
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (in_array($token, ['-e', '-m', '-A', '-B', '-C'], true)) {
                $i++;

                continue;
            }
            if (str_starts_with($token, '-')) {
                continue;
            }
            if ($pattern === null) {
                $pattern = $token;
            } else {
                $paths[] = $this->path($token, $cwd);
            }
        }
        if ($pattern === null || $pattern === '') {
            throw VfsError::invalid('zgrep requires a pattern.');
        }

        return [$pattern, $paths];
    }

    /**
     * @param  list<string>  $valueOptions
     */
    private function contentFromStdinOrPaths(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin, array $valueOptions): string
    {
        if ($stdin !== null) {
            return VfsText::truncate($stdin, $budget->maxBytes);
        }

        $paths = $this->pathArgsWithValueOptions($tokens, $cwd, $valueOptions);
        if ($paths === []) {
            return '';
        }

        return VfsText::truncate(implode("\n", array_map(fn (string $path): string => $this->vfs->read($agent, $path, $budget), $paths)), $budget->maxBytes);
    }
}

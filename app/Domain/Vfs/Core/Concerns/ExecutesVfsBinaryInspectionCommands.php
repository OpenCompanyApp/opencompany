<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Binary-adjacent commands: gzip, gunzip/zcat, zgrep, xxd, strings, iconv.
 *
 * Decompression is capped (DECOMPRESSION_FACTOR x maxBytes, never above the
 * content ceiling) and fails loudly when an input would expand beyond it, so
 * a small archive cannot become a memory bomb. Binary output is allowed in
 * pipes and redirects; the executor refuses to display it.
 */
trait ExecutesVfsBinaryInspectionCommands
{
    private int $decompressionFactor = 10;

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function gzip(User $agent, array $words, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $args = $this->args('gzip', $words);
        if ($args->has('d')) {
            return $this->gunzip($agent, array_values(array_filter($words, fn (string $w): bool => $w !== '-d')), $cwd, $budget, $stdin, 'gunzip');
        }
        $level = -1;
        foreach (range(1, 9) as $candidate) {
            if ($args->has((string) $candidate)) {
                $level = $candidate;
            }
        }
        $encoded = gzencode($this->inputFrom($agent, $args, $cwd, $stdin), $level);
        if ($encoded === false) {
            throw VfsError::invalid('gzip could not compress the input.');
        }

        return ['stdout' => $encoded, 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function gunzip(User $agent, array $words, string $cwd, VfsBudget $budget, ?string $stdin, string $command = 'gunzip'): array
    {
        $args = $this->args($command === 'zcat' ? 'zcat' : 'gunzip', array_values(array_filter($words, fn (string $w): bool => $w !== '-d')));
        $out = '';
        foreach ($this->inputsFrom($agent, $args, $cwd, $stdin) as $input) {
            $out .= $this->boundedGzdecode($input['content'], $budget, $input['name'] === '-' ? 'stdin' : $input['name']);
        }
        if ($command !== 'zcat' && $args->has('t')) {
            return ['stdout' => '', 'class' => 'read-transform'];
        }

        return ['stdout' => $out, 'class' => 'read-transform'];
    }

    private function decompressionCap(VfsBudget $budget): int
    {
        return min($this->contentCeiling(), $budget->maxBytes * $this->decompressionFactor);
    }

    private function boundedGzdecode(string $data, VfsBudget $budget, string $label): string
    {
        $cap = $this->decompressionCap($budget);
        $decoded = @gzdecode($data, $cap + 1);
        if ($decoded === false) {
            $header = strlen($data) >= 2 && $data[0] === "\x1f" && $data[1] === "\x8b";
            throw VfsError::invalid($header
                ? "{$label} expands beyond the VFS decompression limit ({$cap} bytes = {$this->decompressionFactor} x maxBytes)."
                : "{$label} is not valid gzip data.", [
                    'max_decompressed_bytes' => $cap,
                    'hint' => $header ? 'Raise maxBytes, or decompress a smaller archive.' : 'Check the input with xxd -l 16.',
                ]);
        }
        if (strlen($decoded) > $cap) {
            throw VfsError::invalid("{$label} expands beyond the VFS decompression limit ({$cap} bytes).", ['max_decompressed_bytes' => $cap]);
        }

        return $decoded;
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function zgrep(User $agent, array $words, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $args = $this->args('zgrep', $words);
        $patterns = $args->values('-e');
        $offset = 0;
        if ($patterns === []) {
            $patterns = [$args->operands[0] ?? throw VfsError::invalid('zgrep requires a pattern.')];
            $offset = 1;
        }
        $matcher = $this->grepMatcher($patterns, $args->has('E') && ! $args->has('F'), $args->has('i'), $args->has('w'), $args->has('x'), 'zgrep');
        $options = [
            'invert' => $args->has('v'), 'only' => $args->has('o'), 'number' => $args->has('n'), 'count' => $args->has('c'),
            'list' => $args->has('l'), 'list_missing' => false, 'quiet' => $args->has('q'), 'max_count' => $args->int('-m'),
            'before' => $args->int('-B') ?? $args->int('-C') ?? 0, 'after' => $args->int('-A') ?? $args->int('-C') ?? 0,
        ];
        $inputs = $this->inputsFrom($agent, $args, $cwd, $stdin, $offset);
        $withFilename = $args->has('H') || (count($inputs) > 1 && ! $args->has('h'));
        $lines = [];
        $matched = false;
        foreach ($inputs as $input) {
            $label = $input['name'] === '-' ? null : $input['name'];
            $result = $this->grepContent($this->boundedGzdecode($input['content'], $budget, $label ?? 'stdin'), $matcher, $options, $withFilename ? $label : null);
            $matched = $matched || $result['matched'];
            if ($options['list']) {
                if ($result['matched']) {
                    $lines[] = $label ?? '(standard input)';
                }
            } elseif ($options['count']) {
                $lines[] = ($withFilename && $label !== null ? $label.':' : '').$result['count'];
            } else {
                array_push($lines, ...$result['lines']);
            }
        }

        return ['stdout' => $options['quiet'] ? '' : $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'read-transform', 'exit_code' => $matched ? 0 : 1];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function xxd(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('xxd', $words);
        $content = $this->inputFrom($agent, $args, $cwd, $stdin);
        if ($args->has('r')) {
            if (! $args->has('p')) {
                throw VfsError::unsupported('xxd reverse mode supports only plain hex: xxd -r -p.');
            }
            $hex = preg_replace('/[^0-9a-f]/i', '', $content) ?? '';
            if (strlen($hex) % 2 !== 0) {
                throw VfsError::invalid('xxd -r -p requires an even number of hex digits.');
            }

            return ['stdout' => (string) hex2bin($hex), 'class' => 'read-transform'];
        }

        $offset = $args->int('-s') ?? 0;
        $length = $args->int('-l');
        $width = max(1, min(256, $args->int('-c') ?? ($args->has('p') ? 30 : 16)));
        $group = max(1, min($width, $args->int('-g') ?? 2));
        $slice = substr($content, $offset, $length);
        if ($args->has('p')) {
            $hex = bin2hex($slice);
            $hex = $args->has('u') ? strtoupper($hex) : $hex;

            return ['stdout' => $hex === '' ? '' : implode("\n", str_split($hex, $width * 2))."\n", 'class' => 'read-transform'];
        }
        $lines = [];
        for ($i = 0; $i < strlen($slice); $i += $width) {
            $chunk = substr($slice, $i, $width);
            $chunkHex = bin2hex($chunk);
            if ($args->has('u')) {
                $chunkHex = strtoupper($chunkHex);
            }
            $grouped = implode(' ', str_split($chunkHex, $group * 2));
            $ascii = preg_replace('/[^\x20-\x7E]/', '.', $chunk) ?? '';
            $lines[] = sprintf('%08x: %-'.($width * 2 + (int) ceil($width / $group) - 1).'s  %s', $offset + $i, $grouped, $ascii);
        }

        return ['stdout' => $this->linesOut($lines), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function strings(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('strings', $words);
        $min = max(1, $args->int('-n') ?? 4);
        preg_match_all('/[\x20-\x7E\t]{'.$min.',}/', $this->inputFrom($agent, $args, $cwd, $stdin), $matches);

        return ['stdout' => $this->linesOut($matches[0]), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function iconv(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('iconv', $words);
        if (! function_exists('iconv')) {
            throw VfsError::unsupported('PHP iconv support is not available in this runtime.');
        }
        $from = $args->value('-f') ?? 'UTF-8';
        $to = ($args->value('-t') ?? 'UTF-8').($args->has('c') ? '//IGNORE' : '');
        $converted = @iconv($from, $to, $this->inputFrom($agent, $args, $cwd, $stdin));
        if ($converted === false) {
            throw VfsError::invalid("iconv could not convert the input from {$from} to {$to}.", ['hint' => 'Pass -c to drop characters that cannot be converted.']);
        }

        return ['stdout' => $converted, 'class' => 'read-transform'];
    }
}

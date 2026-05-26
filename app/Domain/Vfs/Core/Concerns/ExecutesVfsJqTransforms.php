<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Safe jq-compatible JSON IO for VFS pipelines and files.
 */
trait ExecutesVfsJqTransforms
{
    use EvaluatesVfsJqExpressions;

    private function jq(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertSupportedOptions($tokens, 'jq', ['r', 'c'], [], ['--raw-output', '--compact-output']);
        [$args, $argQuoteMask, $compact] = $this->jqArguments($tokens);
        $expr = $args[0] ?? '.';
        $quoteMask = $this->lastTokenQuoteMask;
        $this->lastTokenQuoteMask = array_values(array_slice($argQuoteMask, 1));
        $paths = $this->pathArgs(array_slice($args, 1), $cwd);
        $this->lastTokenQuoteMask = $quoteMask;
        $inputs = [];
        if ($paths !== []) {
            foreach ($paths as $path) {
                $inputs[] = $this->vfs->read($agent, $path, $budget);
            }
        } else {
            $inputs[] = $stdin ?? '';
        }

        $outputs = [];
        foreach ($inputs as $json) {
            foreach ($this->decodeJqInputs($json) as $data) {
                $result = $this->evaluateJqExpression((string) $expr, $data);
                foreach (is_array($result) && array_key_exists('__vfs_jq_stream', $result) ? $result['__vfs_jq_stream'] : [$result] as $value) {
                    $outputs[] = $this->formatJqValue($value, $compact);
                }
            }
        }

        return ['stdout' => implode("\n", $outputs), 'class' => 'read-transform'];
    }

    /**
     * @return list<mixed>
     */
    private function decodeJqInputs(string $json): array
    {
        $data = json_decode($json, true);
        if ($data !== null || json_last_error() === JSON_ERROR_NONE) {
            return [$data];
        }

        $rows = [];
        foreach (preg_split('/\R/', trim($json)) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = json_decode($line, true);
            if ($row === null && json_last_error() !== JSON_ERROR_NONE) {
                throw VfsError::invalid('jq input is not valid JSON.');
            }
            $rows[] = $row;
        }

        if ($rows === []) {
            throw VfsError::invalid('jq input is not valid JSON.');
        }

        return $rows;
    }

    private function formatJqValue(mixed $value, bool $compact = false): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }

        return is_scalar($value) ? (string) $value : json_encode($value, ($compact ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{0: list<string>, 1: list<bool>, 2: bool}
     */
    private function jqArguments(array $tokens): array
    {
        $args = [];
        $quoteMask = [];
        $compact = false;

        foreach ($tokens as $index => $token) {
            $quoted = $this->lastTokenQuoteMask[$index] ?? false;
            if (! $quoted && in_array($token, ['-r', '--raw-output'], true)) {
                continue;
            }
            if (! $quoted && in_array($token, ['-c', '--compact-output'], true)) {
                $compact = true;

                continue;
            }
            if (! $quoted && preg_match('/^-[rc]+$/', $token) === 1) {
                $compact = $compact || str_contains($token, 'c');

                continue;
            }

            $args[] = $token;
            $quoteMask[] = $quoted;
        }

        return [$args, $quoteMask, $compact];
    }
}

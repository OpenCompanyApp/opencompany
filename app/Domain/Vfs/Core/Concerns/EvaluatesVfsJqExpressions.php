<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;

/**
 * Evaluates the safe jq expression subset supported by VFS commands.
 */
trait EvaluatesVfsJqExpressions
{
    private function evaluateJqExpression(string $expr, mixed $data): mixed
    {
        $expr = trim($expr);
        if (str_contains($expr, '|')) {
            $values = [$data];
            foreach ($this->splitOutsideQuotes($expr, '|') as $stage) {
                $next = [];
                foreach ($values as $value) {
                    $result = $this->evaluateJqExpression($stage, $value);
                    foreach (is_array($result) && array_key_exists('__vfs_jq_stream', $result) ? $result['__vfs_jq_stream'] : [$result] as $item) {
                        $next[] = $item;
                    }
                }
                $values = $next;
            }

            return ['__vfs_jq_stream' => $values];
        }
        if ($expr === '' || $expr === '.') {
            return $data;
        }
        if (preg_match('/^map\((.+)\)$/', $expr, $matches) === 1) {
            if (! is_array($data)) {
                throw VfsError::invalid('jq map requires an array or object.');
            }

            return array_map(fn (mixed $item): mixed => $this->evaluateJqExpression($matches[1], $item), array_values($data));
        }
        if (preg_match('/^select\((.+)\)$/', $expr, $matches) === 1) {
            return ['__vfs_jq_stream' => $this->evaluateJqCondition($matches[1], $data) ? [$data] : []];
        }
        if ($expr === 'length') {
            return match (true) {
                is_countable($data) => count($data),
                is_string($data) => strlen($data),
                $data === null => 0,
                default => strlen((string) $data),
            };
        }
        if ($expr === 'keys') {
            if (! is_array($data)) {
                return [];
            }

            $keys = array_keys($data);
            sort($keys);

            return $keys;
        }
        if (preg_match('/^has\(["\'](.+)["\']\)$/', $expr, $matches) === 1) {
            return is_array($data) && array_key_exists($matches[1], $data);
        }
        if (preg_match('/^(.+)\s*([+\-*\/])\s*(-?\d+(?:\.\d+)?|(?:\.[A-Za-z_][A-Za-z0-9_-]*|\[(?:\d+)\])+|\.(?:\[(?:\d+)\]|[A-Za-z_][A-Za-z0-9_-]*)+)$/', $expr, $matches) === 1) {
            $left = $this->evaluateJqExpression(trim($matches[1]), $data);
            if (! is_numeric($left)) {
                throw VfsError::invalid('jq arithmetic requires a numeric left-hand value.');
            }
            $right = is_numeric($matches[3]) ? (float) $matches[3] : $this->evaluateJqExpression(trim($matches[3]), $data);
            if (! is_numeric($right)) {
                throw VfsError::invalid('jq arithmetic requires a numeric right-hand value.');
            }
            $right = (float) $right;

            return match ($matches[2]) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $right == 0.0 ? throw VfsError::invalid('jq division by zero.') : $left / $right,
            };
        }

        return $this->evaluateJqPathExpression($expr, $data);
    }

    private function evaluateJqPathExpression(string $expr, mixed $data): mixed
    {
        preg_match_all('/(?:\.([A-Za-z_][A-Za-z0-9_-]*))|(?:\.\["((?:[^"\\\\]|\\\\.)*)"\])|(?:\["((?:[^"\\\\]|\\\\.)*)"\])|(?:\.(?=\[(?:\d+|"|\]))) |(?:\[(\d+)\])|(?:\[\]) /x', $expr, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if ($matches === [] || implode('', array_map(fn (array $match): string => $match[0][0], $matches)) !== $expr) {
            throw VfsError::unsupported("Unsupported jq expression: {$expr}", [
                'supported_subset' => ['.', '.field', '.[0]', '.field[0]', '.[]', '.items[]', '.items[].name', '.["key"]', 'length', 'keys', 'has("key")', 'map(.field)', 'select(.field == "value")', 'numeric arithmetic'],
            ]);
        }

        $values = [$data];
        $streamed = false;
        foreach ($matches as $match) {
            $next = [];
            if (($match[1][0] ?? '') !== '') {
                $key = $match[1][0];
                foreach ($values as $value) {
                    $next[] = is_array($value) && array_key_exists($key, $value) ? $value[$key] : null;
                }
            } elseif (($match[2][0] ?? '') !== '' || ($match[3][0] ?? '') !== '') {
                $key = stripcslashes(($match[2][0] ?? '') !== '' ? $match[2][0] : $match[3][0]);
                foreach ($values as $value) {
                    $next[] = is_array($value) && array_key_exists($key, $value) ? $value[$key] : null;
                }
            } elseif (($match[4][0] ?? '') !== '') {
                $index = (int) $match[4][0];
                foreach ($values as $value) {
                    if (! is_array($value) || ! array_is_list($value)) {
                        throw VfsError::invalid('jq numeric indexing requires an array.');
                    }
                    $next[] = array_key_exists($index, $value) ? $value[$index] : null;
                }
            } elseif (($match[0][0] ?? '') === '[]') {
                $streamed = true;
                foreach ($values as $value) {
                    if (! is_array($value)) {
                        throw VfsError::invalid('jq array/object iteration requires an array or object.');
                    }
                    foreach (array_values($value) as $item) {
                        $next[] = $item;
                    }
                }
            } else {
                $next = $values;
            }
            $values = $next;
        }

        return $streamed || count($values) > 1 ? ['__vfs_jq_stream' => $values] : ($values[0] ?? null);
    }

    private function evaluateJqCondition(string $expr, mixed $data): bool
    {
        if (preg_match('/^(.+?)\s*==\s*(?:"([^"]*)"|\'([^\']*)\'|(-?\d+(?:\.\d+)?)|(true|false|null))$/', trim($expr), $matches) !== 1) {
            throw VfsError::unsupported("Unsupported jq select condition: {$expr}", [
                'supported_subset' => ['select(.field == "value")', 'select(.count == 1)', 'select(.active == true)'],
            ]);
        }

        $left = $this->evaluateJqExpression(trim($matches[1]), $data);
        $right = match (true) {
            ($matches[2] ?? '') !== '' => $matches[2],
            ($matches[3] ?? '') !== '' => $matches[3],
            ($matches[4] ?? '') !== '' => (float) $matches[4],
            ($matches[5] ?? '') === 'true' => true,
            ($matches[5] ?? '') === 'false' => false,
            default => null,
        };

        return $left == $right;
    }
}

<?php

namespace App\Domain\CodeMode\Script;

use App\Domain\CodeMode\Contracts\ScriptToolInvoker;

/**
 * Resolves synchronous app.* calls into host-owned tools.
 *
 * The bridge owns predictable argument normalization, pre-dispatch validation,
 * suggestions, and an effect-aware call log. It never owns credentials,
 * authorization, tenancy, provider clients, or retries; those remain with the
 * host supplied through ScriptToolInvoker.
 */
class ScriptBridge
{
    /** @var array<string, string> */
    private array $functionMap;

    /** @var array<string, list<array<string, mixed>>> */
    private array $parameterMap;

    /** @var array<string, string>  Function path → account alias for multi-account integrations */
    private array $accountMap;

    /** @var list<array{index: int, path: string, durationMs: float, status: string, effect: string, effectStatus: string, retryable: bool, errorType?: string, error?: string, icon?: string, name?: string, group?: string}> */
    private array $callLog = [];

    /**
     * @param  array<string, string>  $functionMap
     * @param  array<string, list<array<string, mixed>>>  $parameterMap
     * @param  array<string, string>  $accountMap  Function path → account alias
     */
    public function __construct(
        array $functionMap,
        array $parameterMap,
        private ScriptToolInvoker $invoker,
        array $accountMap = [],
    ) {
        $this->functionMap = $functionMap;
        $this->parameterMap = $parameterMap;
        $this->accountMap = $accountMap;
    }

    /**
     * Invoke one catalogued function through the host adapter.
     *
     * Argument-shape errors fail before dispatch. Provider failures are logged
     * with effect ambiguity but deliberately retain their original exception so
     * the host can apply its own secret-safe presentation policy.
     *
     * @throws ScriptBridgeException for unknown functions or invalid arguments
     */
    public function call(string $path, mixed ...$args): mixed
    {
        if (! isset($this->functionMap[$path])) {
            $suggestions = $this->suggestFunctions($path);
            $message = "Unknown function: app.{$path}";
            if ($suggestions !== []) {
                $message .= '. Did you mean: '.implode(', ', array_map(
                    static fn (string $suggestion): string => "app.{$suggestion}",
                    $suggestions,
                ));
            }

            $this->callLog[] = [
                'index' => count($this->callLog) + 1,
                'path' => $path,
                'durationMs' => 0,
                'status' => 'error',
                'effect' => 'none',
                'effectStatus' => 'none',
                'retryable' => false,
                'errorType' => 'unknown_function',
                'error' => $message,
                'group' => self::extractGroup($path),
            ];

            throw new ScriptBridgeException('unknown_function', $message, [
                'path' => $path,
                'suggestions' => $suggestions,
            ]);
        }

        $toolSlug = $this->functionMap[$path];
        $toolMeta = $this->invoker->getToolMeta($toolSlug);
        $group = self::extractGroup($path);
        // Only an explicit read is side-effect-free. Legacy hosts may omit the
        // optional metadata field, and custom values must not become retryable.
        $effect = ($toolMeta['type'] ?? null) === 'read' ? 'read' : 'write';

        try {
            $params = $this->normalizeArguments($path, $args);
            $this->validateArguments($path, $params);
        } catch (ScriptBridgeException $exception) {
            $this->callLog[] = [
                'index' => count($this->callLog) + 1,
                'path' => $path,
                'durationMs' => 0,
                'status' => 'error',
                'effect' => $effect,
                'effectStatus' => 'none',
                'retryable' => false,
                'errorType' => $exception->errorType,
                'error' => $exception->getMessage(),
                'icon' => $toolMeta['icon'] ?? 'ph:wrench',
                'name' => $toolMeta['name'] ?? $toolSlug,
                'group' => $group,
            ];

            throw $exception;
        }

        $start = microtime(true);

        $account = $this->accountMap[$path] ?? null;

        try {
            $result = $this->invoker->invoke($toolSlug, $params, $account);

            $this->callLog[] = [
                'index' => count($this->callLog) + 1,
                'path' => $path,
                'durationMs' => round((microtime(true) - $start) * 1000, 1),
                'status' => 'ok',
                'effect' => $effect,
                'effectStatus' => $effect === 'write' ? 'succeeded' : 'none',
                'retryable' => $effect === 'read',
                'icon' => $toolMeta['icon'] ?? 'ph:wrench',
                'name' => $toolMeta['name'] ?? $toolSlug,
                'group' => $group,
            ];
            return $result;
        } catch (ScriptDispatchException $exception) {
            // Only the host can produce this type. It represents a decision
            // reached before provider construction, so a write is neither
            // successful nor ambiguous and must never trigger an auto-retry.
            $effectStatus = $exception->errorType === ScriptDispatchException::APPROVAL_PENDING
                ? 'pending'
                : 'denied';
            $this->callLog[] = [
                'index' => count($this->callLog) + 1,
                'path' => $path,
                'durationMs' => round((microtime(true) - $start) * 1000, 1),
                'status' => 'error',
                'effect' => $effect,
                'effectStatus' => $effectStatus,
                'retryable' => false,
                'errorType' => $exception->errorType,
                'error' => $exception->getMessage(),
                'icon' => $toolMeta['icon'] ?? 'ph:wrench',
                'name' => $toolMeta['name'] ?? $toolSlug,
                'group' => $group,
            ];

            throw new ScriptBridgeException(
                $exception->errorType,
                $exception->getMessage(),
                $exception->details,
                retryable: false,
            );
        } catch (\Throwable $e) {
            $this->callLog[] = [
                'index' => count($this->callLog) + 1,
                'path' => $path,
                'durationMs' => round((microtime(true) - $start) * 1000, 1),
                'status' => 'error',
                'effect' => $effect,
                'effectStatus' => $effect === 'write' ? 'unknown' : 'none',
                'retryable' => $effect !== 'write',
                'errorType' => $e instanceof ScriptBridgeException ? $e->errorType : 'tool_error',
                'error' => $e->getMessage(),
                'icon' => $toolMeta['icon'] ?? 'ph:wrench',
                'name' => $toolMeta['name'] ?? $toolSlug,
                'group' => $group,
            ];

            throw $e;
        }
    }

    /**
     * @return list<array{index: int, path: string, durationMs: float, status: string, effect: string, effectStatus: string, retryable: bool, errorType?: string, error?: string, icon?: string, name?: string, group?: string}>
     */
    public function getCallLog(): array
    {
        return $this->callLog;
    }

    private static function extractGroup(string $path): string
    {
        $parts = explode('.', $path);

        if ($parts[0] === 'integrations' && isset($parts[1])) {
            return $parts[1];
        }

        return $parts[0];
    }

    /**
     * Normalize the single named-object form or map positional arguments.
     *
     * @param  array<int, mixed>  $args
     * @return array<string, mixed>
     */
    private function normalizeArguments(string $path, array $args): array
    {
        if ($args === []) {
            return [];
        }

        $definitions = $this->parameterMap[$path] ?? [];

        if (is_array($args[0])) {
            $firstDefinitionType = strtolower((string) ($definitions[0]['type'] ?? ''));
            $isSingleListArgument = count($args) === 1
                && array_is_list($args[0])
                && $args[0] !== []
                && in_array($firstDefinitionType, ['array', 'list'], true);

            if ($isSingleListArgument) {
                $name = (string) ($definitions[0]['name'] ?? '');

                return $name === '' ? [] : [$name => $args[0]];
            }

            if (count($args) !== 1) {
                throw new ScriptBridgeException(
                    'invalid_arguments',
                    "app.{$path} accepts one named argument object or positional arguments, not both.",
                    ['path' => $path],
                );
            }

            return $args[0];
        }

        if (count($args) > count($definitions)) {
            throw new ScriptBridgeException(
                'invalid_arguments',
                "app.{$path} received too many positional arguments. No external call was made.",
                ['path' => $path, 'received' => count($args), 'expected' => count($definitions)],
            );
        }

        $mapped = [];
        foreach ($args as $index => $value) {
            $name = (string) ($definitions[$index]['name'] ?? '');
            if ($name !== '') {
                $mapped[$name] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Reject obvious argument mistakes before a provider can create effects.
     *
     * @param  array<string, mixed>  $params
     */
    private function validateArguments(string $path, array $params): void
    {
        $definitions = $this->parameterMap[$path] ?? [];
        if ($definitions === []) {
            // An empty catalog entry can mean either "no parameters" or
            // "provider did not publish a schema". The core must not invent a
            // restrictive contract; the provider remains the final validator.
            return;
        }

        $known = [];
        $missing = [];
        $invalid = [];

        foreach ($definitions as $definition) {
            $name = (string) ($definition['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $known[$name] = true;
            if (! empty($definition['required']) && ! array_key_exists($name, $params)) {
                $missing[] = $name;

                continue;
            }

            if (array_key_exists($name, $params)) {
                $value = $params[$name];
                if (! $this->matchesType($value, $definition['type'] ?? null)
                    || ! $this->matchesConstraints($value, $definition)) {
                    $invalid[] = $name;
                }
            }
        }

        $unknown = array_values(array_diff(array_keys($params), array_keys($known)));
        if ($missing === [] && $invalid === [] && $unknown === []) {
            return;
        }

        $parts = [];
        if ($missing !== []) {
            $parts[] = 'missing: '.implode(', ', $missing);
        }
        if ($invalid !== []) {
            $parts[] = 'wrong type: '.implode(', ', $invalid);
        }
        if ($unknown !== []) {
            $parts[] = 'unknown: '.implode(', ', $unknown);
        }

        throw new ScriptBridgeException(
            'invalid_arguments',
            "Invalid arguments for app.{$path} (".implode('; ', $parts).'). No external call was made.',
            compact('path', 'missing', 'invalid', 'unknown'),
        );
    }

    private function matchesType(mixed $value, mixed $type): bool
    {
        if ($type === null || $type === '' || $value === null) {
            return true;
        }

        if (is_array($type)) {
            foreach ($type as $candidate) {
                if ($this->matchesType($value, $candidate)) {
                    return true;
                }
            }

            return false;
        }

        return match (strtolower((string) $type)) {
            'string' => is_string($value),
            'integer', 'int' => is_int($value),
            'number', 'float' => is_int($value) || is_float($value),
            'boolean', 'bool' => is_bool($value),
            'array', 'list' => is_array($value) && ($value === [] || array_is_list($value)),
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            // A null value already returned true above; any other value is not null.
            'null' => false,
            default => true,
        };
    }

    /**
     * Apply portable schema constraints that are safe to enforce before a tool
     * call. Provider-specific validation remains the provider's responsibility.
     *
     * @param  array<string, mixed>  $definition
     */
    private function matchesConstraints(mixed $value, array $definition): bool
    {
        if (is_array($definition['enum'] ?? null)
            && ! in_array($value, $definition['enum'], true)) {
            return false;
        }

        if ((is_int($value) || is_float($value))
            && ((isset($definition['minimum']) && $value < $definition['minimum'])
                || (isset($definition['maximum']) && $value > $definition['maximum']))) {
            return false;
        }

        if (is_string($value)) {
            $length = strlen($value);
            if ((isset($definition['minLength']) && $length < $definition['minLength'])
                || (isset($definition['maxLength']) && $length > $definition['maxLength'])) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function suggestFunctions(string $path): array
    {
        $scored = [];
        foreach (array_keys($this->functionMap) as $candidate) {
            $distance = levenshtein($path, $candidate);
            $sameNamespace = str_contains($candidate, '.')
                && str_contains($path, '.')
                && substr($candidate, 0, (int) strrpos($candidate, '.')) === substr($path, 0, (int) strrpos($path, '.'));
            $scored[$candidate] = $distance - ($sameNamespace ? 10 : 0);
        }

        asort($scored);

        // Alias namespaces map to the same underlying tool. Suggest only the
        // closest spelling for each tool so repair hints stay short and do not
        // present `.default`/account aliases as separate fixes.
        $suggestions = [];
        $seenTools = [];
        foreach (array_keys($scored) as $candidate) {
            $toolSlug = $this->functionMap[$candidate];
            if (isset($seenTools[$toolSlug])) {
                continue;
            }

            $seenTools[$toolSlug] = true;
            $suggestions[] = $candidate;
            if (count($suggestions) === 5) {
                break;
            }
        }

        return $suggestions;
    }
}

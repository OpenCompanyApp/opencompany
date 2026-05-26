<?php

namespace App\Agents\Tools\Vfs\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;

/**
 * Shared JSON response helpers for VFS tools.
 *
 * VFS direct tools and Lua-only helpers all return the same structured shape so
 * agents can branch on `ok`, `result`, and stable VFS error codes instead of
 * scraping prose.
 */
trait HandlesVfsJson
{
    /**
     * @param  array<string, mixed>  $result
     */
    private function encodeResult(array $result): string
    {
        return json_encode([
            'ok' => true,
            'result' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function encodeError(string $code, string $message, array $context = []): string
    {
        return json_encode([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'context' => $context,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function budgetFromRequest(mixed $request): VfsBudget
    {
        return new VfsBudget(
            maxEntries: $this->budgetInteger($request, ['limit', 'maxEntries', 'max_entries'], 100, 1, 'limit'),
            maxDepth: $this->budgetInteger($request, ['maxDepth', 'max_depth'], 3, 0, 'maxDepth'),
            maxBytes: $this->budgetInteger($request, ['maxBytes', 'max_bytes'], 100000, 1, 'maxBytes'),
            maxFiles: $this->budgetInteger($request, ['maxFiles', 'max_files'], 100, 1, 'maxFiles'),
            maxMatches: $this->budgetInteger($request, ['maxMatches', 'max_matches'], 100, 1, 'maxMatches'),
        );
    }

    /**
     * @param  list<string>  $keys
     */
    private function budgetInteger(mixed $request, array $keys, int $default, int $min, string $label): int
    {
        foreach ($keys as $key) {
            if (! isset($request[$key])) {
                continue;
            }

            $raw = $request[$key];
            if (! is_int($raw) && (! is_string($raw) || preg_match('/^\d+$/', $raw) !== 1)) {
                throw VfsError::invalid("{$label} must be an integer.", ['field' => $key]);
            }

            $value = (int) $raw;
            if ($value < $min) {
                throw VfsError::invalid("{$label} must be at least {$min}.", ['field' => $key, 'min' => $min]);
            }

            return $value;
        }

        return $default;
    }

    private function cursorOffset(mixed $request): int
    {
        $cursor = $request['cursor'] ?? null;
        if ($cursor === null || $cursor === '') {
            return 0;
        }

        if (! is_string($cursor)) {
            throw VfsError::invalid('cursor must be an opaque string.', ['field' => 'cursor']);
        }

        $decoded = json_decode(base64_decode($cursor, true) ?: '', true);
        if (! is_array($decoded) || ! is_int($decoded['offset'] ?? null) || ($decoded['offset'] ?? 0) < 0) {
            throw VfsError::invalid('cursor is invalid or expired.', ['field' => 'cursor']);
        }

        return $decoded['offset'];
    }

    private function nextCursor(int $offset, int $returned, bool $truncated): ?string
    {
        if (! $truncated || $returned <= 0) {
            return null;
        }

        return base64_encode(json_encode(['offset' => $offset + $returned], JSON_THROW_ON_ERROR));
    }

    private function errorResponse(\Throwable $e): string
    {
        if ($e instanceof VfsError) {
            return $this->encodeError($e->errorCode, $e->getMessage(), $e->context);
        }

        return $this->encodeError('internal_error', $e->getMessage());
    }
}

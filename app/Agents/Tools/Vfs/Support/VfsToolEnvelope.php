<?php

namespace App\Agents\Tools\Vfs\Support;

use App\Domain\Vfs\Core\VfsError;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The one encoder for every `vfs_*` tool result.
 *
 * Contract (agents and Code Mode branch on it):
 * - success: `{"ok":true,"result":{...}}` (vfs_exec is flat: see VfsExec);
 * - failure: `{"ok":false,"error":{"code","message","context"}}`; `error` is
 *   the only place a failure is reported;
 * - compact JSON, slashes and unicode unescaped, never an empty string:
 *   invalid UTF-8 is substituted (and said so), and a payload that still
 *   cannot be encoded becomes an explicit internal_error envelope;
 * - `internal_error` never carries raw exception text. The exception is
 *   logged with a correlation id and only that id reaches the model, because
 *   driver/SQL messages can leak schema, paths or other tenants' identifiers.
 *
 * Approval outcomes are whatever VfsMutationPolicy / ApprovalWrappedTool
 * produce (Stream C2 owns their shape); this class passes VfsError codes
 * through and never special-cases prose.
 */
final class VfsToolEnvelope
{
    private const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function success(array $result): array
    {
        return ['ok' => true, 'result' => $result];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function error(string $code, string $message, array $context = []): array
    {
        return ['ok' => false, 'error' => ['code' => $code, 'message' => $message, 'context' => $context]];
    }

    /**
     * Map any failure to the error envelope. VfsError keeps its code, message
     * and context; anything else is logged and reported as a sanitized
     * internal_error.
     *
     * @return array<string, mixed>
     */
    public static function fromThrowable(\Throwable $e, string $toolSlug): array
    {
        if ($e instanceof VfsError) {
            return self::error($e->errorCode, $e->getMessage(), $e->context);
        }

        $correlationId = (string) Str::uuid();
        Log::error('VFS tool internal error', [
            'correlation_id' => $correlationId,
            'tool' => $toolSlug,
            'exception' => $e,
        ]);

        return self::error(
            'internal_error',
            "The VFS hit an unexpected error while running {$toolSlug}. Retrying the same call is unlikely to help; report the correlation id if it persists.",
            ['correlation_id' => $correlationId],
        );
    }

    /**
     * Encode a payload. Always returns non-empty JSON.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function encode(array $payload): string
    {
        $json = json_encode($payload, self::FLAGS);
        if ($json !== false) {
            return $json;
        }

        $firstError = json_last_error_msg();
        $json = json_encode($payload + [
            'encoding_note' => 'Invalid UTF-8 bytes were replaced with U+FFFD. For binary content use vfs_exec with base64 or xxd.',
        ], self::FLAGS | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json !== false) {
            return $json;
        }

        return (string) json_encode(self::error(
            'internal_error',
            'The VFS result could not be encoded as JSON.',
            ['json_error' => $firstError],
        ), self::FLAGS | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

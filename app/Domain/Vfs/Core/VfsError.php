<?php

namespace App\Domain\Vfs\Core;

/**
 * Domain exception with stable VFS error codes.
 *
 * VFS tools return these codes to agents instead of leaking PHP exception
 * details. Keep codes stable because Lua scripts and future UI diagnostics can
 * branch on them.
 */
class VfsError extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function notFound(string $path): self
    {
        return new self('not_found', "Path not found: {$path}", ['path' => $path]);
    }

    public static function notReadable(string $path): self
    {
        return new self('not_readable', "Path is not readable as text: {$path}", ['path' => $path]);
    }

    public static function notWritable(string $path): self
    {
        return new self('not_writable', "Path is not writable through VFS: {$path}", ['path' => $path]);
    }

    public static function permissionDenied(string $path, string $reason = 'permission denied'): self
    {
        return new self('permission_denied', "Permission denied for {$path}: {$reason}", [
            'path' => $path,
            'reason' => $reason,
        ]);
    }

    public static function unsupported(string $message, array $context = []): self
    {
        return new self('unsupported', $message, $context);
    }

    public static function approvalRequired(string $message, array $context = []): self
    {
        return new self('approval_required', $message, $context);
    }

    public static function invalid(string $message, array $context = []): self
    {
        return new self('invalid_request', $message, $context);
    }
}

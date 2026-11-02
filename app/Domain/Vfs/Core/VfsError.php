<?php

namespace App\Domain\Vfs\Core;

/**
 * Domain exception with stable VFS error codes.
 *
 * VFS tools return these codes to agents instead of leaking PHP exception
 * details. Keep codes stable because Code Mode scripts and future UI diagnostics can
 * branch on them.
 *
 * Stable codes: not_found, not_readable, not_writable, permission_denied,
 * unsupported, approval_required, invalid_request, conflict, ambiguous_match.
 * `conflict` and `ambiguous_match` carry machine-readable context shapes that
 * later phases (vfs_edit, required base versions, bridge/API writes) build on;
 * extend those shapes additively rather than renaming keys.
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

    /**
     * Optimistic-concurrency failure: the caller's base no longer matches the
     * current object. Raised inside the locked compare-and-write, so the
     * reported `current_version` is the authoritative version at decision time.
     *
     * Context: {path, current_version, given_version, hint?, ...extra}. Row
     * patches add `changed_fields` (list of column names whose supplied base
     * value differs from the current row).
     *
     * @param  array<string, mixed>  $extra
     */
    public static function conflict(
        string $path,
        ?string $currentVersion,
        ?string $givenVersion,
        ?string $hint = null,
        array $extra = [],
        ?string $message = null,
    ): self {
        return new self('conflict', $message ?? "Version conflict for {$path}: it changed since your read.", [
            'path' => $path,
            'current_version' => $currentVersion,
            'given_version' => $givenVersion,
            ...($hint !== null ? ['hint' => $hint] : []),
            ...$extra,
        ]);
    }

    /**
     * A text edit anchor matched more than once and the caller did not ask
     * for replace_all. Nothing was written.
     *
     * Context: {path, count, lines (1-based line numbers of each match start), hint}.
     *
     * @param  list<int>  $lines
     */
    public static function ambiguousMatch(string $path, int $count, array $lines, ?string $hint = null): self
    {
        return new self('ambiguous_match', "Patch search text matches {$count} times in {$path}.", [
            'path' => $path,
            'count' => $count,
            'lines' => $lines,
            'hint' => $hint ?? 'Include more surrounding text so the search matches exactly once, or pass "replace_all": true to replace every match.',
        ]);
    }

    /**
     * The VFS was entered with a bound workspace that is not the acting
     * agent's workspace (or with no workspace bound at all). This is a host
     * wiring error, never an agent-repairable condition, so the message is
     * deliberately generic and carries no workspace identifiers.
     */
    public static function workspaceBindingMismatch(): self
    {
        return new self('permission_denied', 'Permission denied: the VFS is not bound to this agent\'s workspace.', [
            'reason' => 'workspace_binding_mismatch',
        ]);
    }
}

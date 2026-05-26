<?php

namespace App\Domain\Vfs\Core;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Stable audit event emitted after VFS primitive tool execution.
 *
 * This is intentionally an event rather than a database table for the first
 * implementation. Listeners can later persist, stream, or sample it without
 * changing the VFS tool contract.
 */
class VfsOperationLog
{
    public function __construct(
        public readonly string $operationId,
        public readonly string $workspaceId,
        public readonly string $agentId,
        public readonly string $surface,
        public readonly string $operation,
        public readonly ?string $path,
        public readonly ?string $cwd,
        public readonly bool $ok,
        public readonly ?string $errorCode,
        public readonly int $durationMs,
        public readonly array $metadata = [],
    ) {}

    public static function forAgent(
        User $agent,
        string $surface,
        string $operation,
        ?string $path,
        ?string $cwd,
        bool $ok,
        ?string $errorCode,
        int $durationMs,
        array $metadata = [],
    ): self {
        return new self(
            operationId: (string) Str::uuid(),
            workspaceId: (string) $agent->workspace_id,
            agentId: (string) $agent->id,
            surface: $surface,
            operation: $operation,
            path: $path,
            cwd: $cwd,
            ok: $ok,
            errorCode: $errorCode,
            durationMs: $durationMs,
            metadata: $metadata,
        );
    }
}

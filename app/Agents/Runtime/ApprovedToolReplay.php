<?php

namespace App\Agents\Runtime;

/**
 * Container-bound marker for "this tool call is the execution of an approved
 * ApprovalRequest".
 *
 * Only ApprovalExecutionService binds it, around the single approved
 * `handle()` call, and always unbinds it afterwards. Tools that gate per call
 * (for example SendChannelMessage for external destinations) use it to avoid
 * creating a second approval for the call that was just approved. It is never
 * derived from model-supplied parameters.
 */
final class ApprovedToolReplay
{
    public function __construct(
        public readonly string $approvalId,
        public readonly string $toolSlug,
    ) {}

    public static function isApproved(string $toolSlug): bool
    {
        return app()->bound(self::class) && app(self::class)->toolSlug === $toolSlug;
    }
}

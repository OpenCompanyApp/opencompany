<?php

namespace App\Domain\Vfs\OpenCompany\Approvals;

use App\Models\User;

/**
 * Request-scoped holder for an approved VFS replay.
 *
 * The approval bypass is deliberately not represented in tool parameters. The
 * approval executor binds this service only while replaying an already approved
 * request, and VFS policy checks match the original requester, tool, operation,
 * subject, required tool, and parameter hash before accepting an approval-gated
 * backing permission.
 */
class VfsApprovedExecutionContext
{
    /** @var array<string, mixed>|null */
    private static ?array $context = null;

    /**
     * @template T
     *
     * @param  array<string, mixed>  $context
     * @param  callable(): T  $callback
     * @return T
     */
    public function runWith(array $context, callable $callback): mixed
    {
        $previous = self::$context;
        self::$context = $context;

        try {
            return $callback();
        } finally {
            self::$context = $previous;
        }
    }

    /**
     * @param  list<string>  $requiredTools
     */
    public function approves(
        User $agent,
        string $directToolSlug,
        string $operation,
        string $subject,
        string $toolSlug,
        array $requiredTools,
        string $parametersHash,
    ): bool {
        if (self::$context === null) {
            return false;
        }

        $approvedTools = self::$context['approved_tools'] ?? [];
        if (! is_array($approvedTools) || ! in_array($toolSlug, $approvedTools, true)) {
            return false;
        }

        return (self::$context['requester_id'] ?? null) === $agent->id
            && (self::$context['direct_tool_slug'] ?? null) === $directToolSlug
            && (self::$context['operation'] ?? null) === $operation
            && (self::$context['subject'] ?? null) === $subject
            && (self::$context['parameters_hash'] ?? null) === $parametersHash
            && $this->sameTools($requiredTools, self::$context['required_tools'] ?? []);
    }

    /**
     * @param  array<int, string>  $left
     */
    private function sameTools(array $left, mixed $right): bool
    {
        if (! is_array($right)) {
            return false;
        }

        $right = array_values(array_filter($right, 'is_string'));
        sort($left);
        sort($right);

        return $left === $right;
    }
}

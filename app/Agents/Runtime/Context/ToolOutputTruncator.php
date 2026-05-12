<?php

namespace App\Agents\Runtime\Context;

use Illuminate\Support\Str;

/**
 * Caps tool output before it is fed back into the agent conversation.
 *
 * Tool implementations can return large documents or API payloads; this helper
 * keeps a model turn from being dominated by one result while marking the cut
 * explicitly for the agent.
 */
class ToolOutputTruncator
{
    public function truncate(string $value, int $limit = 8000): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return Str::limit($value, $limit)."\n\n[output truncated]";
    }
}

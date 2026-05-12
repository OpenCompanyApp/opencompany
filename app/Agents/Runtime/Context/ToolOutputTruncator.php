<?php

namespace App\Agents\Runtime\Context;

use Illuminate\Support\Str;

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

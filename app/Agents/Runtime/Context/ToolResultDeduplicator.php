<?php

namespace App\Agents\Runtime\Context;

class ToolResultDeduplicator
{
    /**
     * @param  list<array<string, mixed>>  $results
     * @return list<array<string, mixed>>
     */
    public function deduplicate(array $results): array
    {
        $seen = [];
        $deduped = [];

        foreach ($results as $result) {
            $hash = hash('xxh128', json_encode($result, JSON_THROW_ON_ERROR));
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;
            $deduped[] = $result;
        }

        return $deduped;
    }
}

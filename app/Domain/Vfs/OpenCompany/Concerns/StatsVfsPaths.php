<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Public stat operation for VFS paths.
 */
trait StatsVfsPaths
{
    public function stat(User $agent, string $path): array
    {
        $path = $this->normalizePath($path);

        $entry = $this->directEntryForPath($agent, $path);
        if ($entry !== null) {
            return $entry->jsonSerialize();
        }

        try {
            $limit = 100;
            $entries = $this->list($agent, $path, new VfsBudget(maxEntries: $limit));
            $returned = count($entries);
            $exact = $returned < $limit;

            return [
                'path' => $path,
                'type' => 'directory',
                'count' => $returned,
                'sampled_count' => $returned,
                'total_count' => $exact ? $returned : null,
                'count_mode' => $exact ? 'exact' : 'sampled',
                'returned' => $returned,
                'limit' => $limit,
                'truncated' => ! $exact,
                'count_is_exact' => $exact,
                'capabilities' => ['browse', 'read', 'search'],
            ];
        } catch (VfsError $e) {
            if ($e->errorCode !== 'not_readable' && $e->errorCode !== 'not_found') {
                throw $e;
            }
        }

        $entry = $this->entryForPath($agent, $path);
        if ($entry === null) {
            throw VfsError::notFound($path);
        }

        return $entry->jsonSerialize();
    }
}

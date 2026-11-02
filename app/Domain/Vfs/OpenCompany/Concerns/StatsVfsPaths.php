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
    /**
     * @return array<string, mixed>
     */
    public function stat(User $agent, string $path): array
    {
        $this->assertBoundWorkspace($agent);
        $path = $this->routePath($agent, $path);

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
            throw $this->explainNotFound($agent, $path);
        }

        return $entry->jsonSerialize();
    }

    /**
     * Re-run friendly resolution for a path stat could not find, so the
     * error carries the same did_you_mean/by_id suggestions a read would.
     */
    private function explainNotFound(User $agent, string $path): VfsError
    {
        $segments = $this->segments($path);

        try {
            if (($segments[0] ?? null) === 'docs' && isset($segments[1]) && ! in_array($segments[1], ['by-id', '.trash'], true)) {
                $this->resolveDocumentPath($agent, array_slice($segments, 1), $path);
            } elseif (($segments[0] ?? null) === 'tables' && isset($segments[1])) {
                ['base' => $base] = $this->resolveTableSegments($segments, $path);

                return $this->tableNotFound($path, $base);
            } elseif (($segments[0] ?? null) === 'agents' && isset($segments[1]) && $segments[1] !== 'by-id') {
                $this->resolveAgent($agent, array_slice($segments, 1), $path);
            }
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                return $e;
            }
        }

        return VfsError::notFound($path);
    }
}

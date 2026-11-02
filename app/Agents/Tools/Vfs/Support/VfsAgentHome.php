<?php

namespace App\Agents\Tools\Vfs\Support;

use App\Agents\Runtime\ActiveAgentRun;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Resolves an agent's VFS home folder and its tool-result area.
 *
 * The home convention is the existing file-permission one: an agent may always
 * create files under `/files/agents/{Str::slug(name)}/` even with a restricted
 * file-folder scope (MutatesVfsFiles::assertCanCreateFilePath,
 * FileSystemService::ensureAgentHomeFolder). Offloaded tool results live in
 * `.results/{run id, or Y-m-d outside a run}/` there. Stream C1 aliases
 * `/me/results` to this folder; keep the two in sync if the convention moves.
 */
final class VfsAgentHome
{
    public static function path(User $agent): string
    {
        return '/files/agents/'.Str::slug((string) $agent->name);
    }

    public static function resultsPath(User $agent): string
    {
        return self::path($agent).'/.results';
    }

    /**
     * Folder for this run's offloaded results: the active run id when a run is
     * bound (so one run's outputs stay together for change sets and cleanup),
     * else today's date.
     */
    public static function currentResultsFolder(User $agent): string
    {
        $segment = app()->bound(ActiveAgentRun::class)
            ? app(ActiveAgentRun::class)->runId
            : now()->format('Y-m-d');

        return self::resultsPath($agent).'/'.$segment;
    }
}

<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Direct stat/read lookup helpers for canonical and alias VFS paths.
 *
 * This trait owns only generic direct-entry dispatch. Resource-specific entry
 * builders live in sibling concerns so adding a mount does not turn direct
 * lookup into another cross-domain god file.
 */
trait ResolvesVfsDirectEntries
{
    private function entryForPath(User $agent, string $path): ?VfsEntry
    {
        $parent = $this->normalizePath(dirname($path));
        $name = basename($path);

        try {
            foreach ($this->list($agent, $parent) as $entry) {
                if ($entry->name === $name || $entry->path === $path || $entry->canonicalPath === $path) {
                    return $entry;
                }
            }
        } catch (VfsError) {
            return null;
        }

        return null;
    }

    private function directEntryForPath(User $agent, string $path): ?VfsEntry
    {
        $segments = $this->segments($path);

        return match ($segments[0] ?? null) {
            'agents' => $this->directAgentEntry($agent, $segments, $path),
            'docs' => $this->directDocumentEntry($agent, $segments, $path),
            'files' => $this->directFileEntry($agent, $segments, $path),
            'tasks' => $this->directTaskEntry($segments),
            'lists' => $this->directListEntry($segments),
            'channels' => $this->directChannelEntry($agent, $segments, $path),
            'tables' => $this->directTableEntry($segments, $path),
            'approvals' => $this->directApprovalEntry($agent, $segments, $path),
            'automations' => $this->directAutomationEntry($segments, $path),
            'workspace' => $this->directWorkspaceEntry($segments, $path),
            default => null,
        };
    }
}

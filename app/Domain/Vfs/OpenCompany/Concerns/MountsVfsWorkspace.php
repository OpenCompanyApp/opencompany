<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Workspace metadata mount.
 */
trait MountsVfsWorkspace
{
    /**
     * @return list<VfsEntry>
     */
    private function listWorkspace(string $path): array
    {
        if ($path !== '/workspace') {
            throw VfsError::notReadable($path);
        }

        return [
            new VfsEntry('members.json', '/workspace/members.json', 'file', capabilities: ['read']),
            new VfsEntry('settings.json', '/workspace/settings.json', 'file', capabilities: ['read']),
        ];
    }

    private function readWorkspace(User $agent, array $segments, string $path): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listWorkspace($path));
        }
        if (count($segments) !== 2) {
            throw VfsError::notFound($path);
        }

        return match ($segments[1] ?? '') {
            'members.json' => json_encode([
                'members' => workspace()->members()
                    ->withPivot('id', 'role')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (User $member): array => [
                        'id' => $member->pivot->id,
                        'membership_id' => $member->pivot->id,
                        'user_id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'avatar' => $member->avatar,
                        'type' => $member->type,
                        'presence' => $member->presence,
                        'role' => $member->pivot->role,
                        'joined_at' => $member->pivot->created_at?->toIso8601String(),
                        'vfs_path' => null,
                    ])
                    ->values()
                    ->all(),
                'agents' => workspace()->agents()
                    ->orderBy('name')
                    ->get()
                    ->map(fn (User $member): array => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'type' => $member->type,
                        'status' => $member->status,
                        'presence' => $member->presence,
                        'manager_id' => $member->manager_id,
                        'created_at' => $member->created_at?->toIso8601String(),
                        'vfs_path' => "/agents/by-id/{$member->id}",
                    ])
                    ->values()
                    ->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'settings.json' => json_encode(['workspace_id' => $agent->workspace_id], JSON_PRETTY_PRINT),
            default => throw VfsError::notFound($path),
        };
    }
}

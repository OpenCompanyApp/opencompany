<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\ApprovalRequest;
use App\Models\DataTable;
use App\Models\ListItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Direct entry builders for non-file OpenCompany resource mounts.
 */
trait ResolvesVfsResourceDirectEntries
{
    private function directAgentEntry(User $agent, array $segments, string $path): ?VfsEntry
    {
        if (isset($segments[3]) || ! isset($segments[1])) {
            return null;
        }

        $target = $this->resolveAgent($agent, array_slice($segments, 1), $path);

        return new VfsEntry(
            name: $segments[2] ?? $this->slug($target->name),
            path: $path,
            type: 'directory',
            canonicalPath: "/agents/by-id/{$target->id}",
            backendType: 'agent',
            backendId: $target->id,
            capabilities: ['browse', 'read'],
            metadata: ['name' => $target->name, 'status' => $target->status],
        );
    }

    private function directTaskEntry(array $segments): ?VfsEntry
    {
        if (($segments[1] ?? null) !== 'by-id' || ! isset($segments[2]) || isset($segments[3])) {
            return null;
        }

        $task = Task::forWorkspace()->with('agent:id,name')->find($segments[2]);

        return $task ? $this->taskEntry($task) : null;
    }

    private function directListEntry(array $segments): ?VfsEntry
    {
        if (($segments[1] ?? null) !== 'by-id' || ! isset($segments[2]) || isset($segments[3])) {
            return null;
        }

        $item = ListItem::forWorkspace()->find($segments[2]);

        return $item ? $this->listItemEntry($item) : null;
    }

    private function directChannelEntry(User $agent, array $segments, string $path): ?VfsEntry
    {
        $offset = ($segments[1] ?? null) === 'by-id' ? 3 : 2;
        if (($segments[$offset] ?? null) !== 'messages' || ! isset($segments[$offset + 1]) || isset($segments[$offset + 2])) {
            return null;
        }

        $last = $segments[$offset + 1];
        if (preg_match('/^\d{4}-\d{2}-\d{2}\.md$/', $last) !== 1) {
            return null;
        }

        $channel = $this->resolveChannel($agent, array_slice($segments, 1, $offset - 1), $path);

        return new VfsEntry($last, $path, 'file', backendType: 'channel', backendId: $channel->id, capabilities: ['read', 'search']);
    }

    private function directTableEntry(array $segments, string $path): ?VfsEntry
    {
        if (! isset($segments[1]) || isset($segments[3])) {
            return null;
        }

        $table = DataTable::forWorkspace()->get()->first(fn (DataTable $candidate): bool => $this->slug($candidate->name) === $segments[1]);
        if (! $table) {
            return null;
        }

        if (! isset($segments[2])) {
            return new VfsEntry(
                $this->slug($table->name),
                $path,
                'directory',
                "/tables/{$this->slug($table->name)}",
                'data_table',
                $table->id,
                capabilities: ['browse', 'read', 'search'],
                metadata: ['name' => $table->name],
            );
        }

        if (! in_array($segments[2], ['schema.json', 'rows.ndjson', 'views'], true)) {
            return null;
        }

        if ($segments[2] === 'views') {
            return new VfsEntry('views', $path, 'directory', backendType: 'data_table', backendId: $table->id, capabilities: ['browse', 'read']);
        }

        return new VfsEntry($segments[2], $path, 'file', backendType: 'data_table', backendId: $table->id, capabilities: $segments[2] === 'rows.ndjson' ? ['read', 'search'] : ['read']);
    }

    private function directApprovalEntry(User $agent, array $segments, string $path): ?VfsEntry
    {
        if (! isset($segments[1]) || isset($segments[2])) {
            return null;
        }

        $id = Str::before($segments[1], '.json');
        $approval = ApprovalRequest::query()
            ->where('id', $id)
            ->whereHas('requester', fn (Builder $query) => $query->where('workspace_id', $agent->workspace_id))
            ->where('requester_id', $agent->id)
            ->first();

        return $approval
            ? new VfsEntry($approval->id.'.json', $path, 'file', backendType: 'approval', backendId: $approval->id, capabilities: ['read'], metadata: ['status' => $approval->status, 'title' => $approval->title])
            : null;
    }

    private function directAutomationEntry(array $segments, string $path): ?VfsEntry
    {
        if (! isset($segments[1]) || isset($segments[2])) {
            return null;
        }

        $token = Str::before($segments[1], '.json');
        try {
            $automation = $this->resolveAutomationToken($token, $path);
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                return null;
            }

            throw $e;
        }

        return $automation
            ? $this->automationEntry($automation, $path)
            : null;
    }

    private function directWorkspaceEntry(array $segments, string $path): ?VfsEntry
    {
        if (isset($segments[2]) || ! in_array($segments[1] ?? '', ['members.json', 'settings.json'], true)) {
            return null;
        }

        return new VfsEntry($segments[1], $path, 'file', capabilities: ['read']);
    }
}

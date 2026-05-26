<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Channel mount operations for OpenCompanyVfs.
 *
 * This concern owns channel routing, permission-scoped channel listing, and
 * day-based message exports. Table/data collaboration mounts intentionally live
 * in a sibling concern to keep each mount family locally understandable.
 */
trait MountsVfsChannels
{
    /**
     * @return list<VfsEntry>
     */
    private function listChannels(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        if (count($segments) === 1) {
            $entries = [new VfsEntry('by-id', '/channels/by-id', 'directory', capabilities: ['browse'])];

            foreach ($this->allowedChannels($agent)->limit(max(0, $budget->maxEntries - count($entries)))->get() as $channel) {
                $entries[] = $this->channelEntry($channel);
            }

            return $entries;
        }

        if (($segments[1] ?? null) === 'by-id' && ! isset($segments[2])) {
            return $this->allowedChannels($agent)
                ->limit($budget->maxEntries)
                ->get()
                ->map(fn (Channel $channel): VfsEntry => new VfsEntry(
                    name: (string) $channel->id,
                    path: "/channels/by-id/{$channel->id}",
                    type: 'directory',
                    canonicalPath: "/channels/by-id/{$channel->id}",
                    backendType: 'channel',
                    backendId: $channel->id,
                    capabilities: ['browse', 'read', 'search'],
                    metadata: ['name' => $channel->name, 'type' => $channel->type],
                ))
                ->all();
        }

        [$channel, $remaining] = $this->resolveChannelRoute($agent, $segments, $path);
        if ($remaining === ['messages']) {
            return $channel->messages()
                ->selectRaw('DATE(COALESCE(timestamp, created_at)) as day')
                ->groupBy('day')
                ->orderByDesc('day')
                ->limit($budget->maxEntries)
                ->get()
                ->map(fn ($row): VfsEntry => new VfsEntry($row->day.'.md', rtrim($path, '/').'/'.$row->day.'.md', 'file', backendType: 'channel', backendId: $channel->id, capabilities: ['read', 'search']))
                ->all();
        }

        if ($remaining === ['threads']) {
            return [];
        }

        if ($remaining !== []) {
            throw VfsError::notFound($path);
        }

        return [
            new VfsEntry('messages', rtrim($path, '/').'/messages', 'directory', "/channels/by-id/{$channel->id}/messages", 'channel', $channel->id, capabilities: ['browse', 'read', 'search']),
            new VfsEntry('threads', rtrim($path, '/').'/threads', 'directory', "/channels/by-id/{$channel->id}/threads", 'channel', $channel->id, capabilities: ['browse']),
        ];
    }

    private function readChannels(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listChannels($agent, $segments, $path, $budget));
        }

        if (($segments[1] ?? null) === 'by-id' && ! isset($segments[2])) {
            return $this->entriesToText($this->listChannels($agent, $segments, $path, $budget));
        }

        [$channel, $remaining] = $this->resolveChannelRoute($agent, $segments, $path);
        if (count($remaining) === 2 && $remaining[0] === 'messages' && preg_match('/^\d{4}-\d{2}-\d{2}\.md$/', $remaining[1]) === 1) {
            $day = substr($remaining[1], 0, 10);
            $messages = $channel->messages()
                ->with('author:id,name')
                ->where(function (Builder $query) use ($day): void {
                    $query->whereDate('timestamp', $day)
                        ->orWhere(function (Builder $fallback) use ($day): void {
                            $fallback->whereNull('timestamp')->whereDate('created_at', $day);
                        });
                })
                ->orderBy('timestamp')
                ->limit($budget->maxEntries)
                ->get();

            return $messages->map(fn (Message $message): string => sprintf(
                '[%s] %s: %s',
                ($message->timestamp ?? $message->created_at)?->format('H:i:s'),
                $message->author?->name ?? 'Unknown',
                $message->content,
            ))->implode("\n");
        }

        if ($remaining !== [] && $remaining !== ['messages'] && $remaining !== ['threads']) {
            throw VfsError::notFound($path);
        }

        return $this->entriesToText($this->listChannels($agent, $segments, $path, $budget));
    }

    private function allowedChannels(User $agent): Builder
    {
        $query = Channel::forWorkspace()->orderBy('name');
        $allowed = $this->permissions->getAllowedChannelIds($agent);
        if ($allowed !== null) {
            $query->whereIn('id', $allowed);
        }

        return $query;
    }

    private function resolveChannel(User $agent, array $parts, string $path): Channel
    {
        $query = $this->allowedChannels($agent);

        if (($parts[0] ?? null) === 'by-id' && isset($parts[1])) {
            return $query->where('id', $parts[1])->first() ?? throw VfsError::notFound($path);
        }

        $slug = $parts[0] ?? '';

        return $query->get()->first(fn (Channel $channel): bool => $this->slug($channel->name) === $slug) ?? throw VfsError::notFound($path);
    }

    /**
     * @return array{0: Channel, 1: list<string>}
     */
    private function resolveChannelRoute(User $agent, array $segments, string $path): array
    {
        if (($segments[1] ?? null) === 'by-id') {
            if (! isset($segments[2])) {
                throw VfsError::notFound($path);
            }

            return [
                $this->resolveChannel($agent, array_slice($segments, 1, 2), $path),
                array_values(array_slice($segments, 3)),
            ];
        }

        if (! isset($segments[1])) {
            throw VfsError::notFound($path);
        }

        return [
            $this->resolveChannel($agent, array_slice($segments, 1, 1), $path),
            array_values(array_slice($segments, 2)),
        ];
    }

    private function channelEntry(Channel $channel): VfsEntry
    {
        return new VfsEntry(
            name: $channel->name,
            path: '/channels/'.$this->slug($channel->name),
            type: 'directory',
            canonicalPath: "/channels/by-id/{$channel->id}",
            backendType: 'channel',
            backendId: $channel->id,
            capabilities: ['browse', 'read', 'search'],
            metadata: ['type' => $channel->type, 'description' => $channel->description],
        );
    }
}

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
 *
 * Visibility (allowedChannels) is the chat tools' rule, plus one tightening:
 * - channel allow rows (AgentPermission scope `channel`) restrict the set
 *   exactly like canAccessChannel/ListChannels;
 * - direct-message channels (`type = dm`) are never visible because they
 *   exist (ListChannels excludes them): only DMs the agent is a member of, or
 *   that an admin explicitly allowed by id, are. Other principals' DM
 *   transcripts are therefore unreachable through /channels, by-id guessing
 *   and search candidates alike.
 *
 * Paths: `/channels/{slug}` is unique per agent view. When two visible
 * channels slug the same, both are listed as `{slug}--{id8}` (first 8 chars
 * of the id, deterministic), the bare slug fails with ambiguous_match listing
 * the suffixed paths, and `/channels/by-id/{id}` always works.
 */
trait MountsVfsChannels
{
    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listChannels(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        return $this->pageChannels($agent, $segments, $path, $budget, [])['entries'];
    }

    /**
     * Return one permission-scoped channel page before the facade encrypts its
     * continuation state. Channel permission predicates stay in the base query
     * so an inaccessible channel can never consume a visible page slot.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{entries: list<VfsEntry>, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageChannels(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        if (count($segments) === 1) {
            $collisions = $this->channelSlugCollisions($agent);

            return $this->paginateVfsLabelQuery(
                $this->allowedChannels($agent),
                'name',
                $budget->maxEntries,
                $state,
                fn (Channel $channel): VfsEntry => $this->channelEntry($channel, $collisions),
                new VfsEntry('by-id', '/channels/by-id', 'directory', capabilities: ['browse']),
            );
        }

        if (isset($segments[1]) && $segments[1] === 'by-id' && ! isset($segments[2])) {
            return $this->paginateVfsLabelQuery(
                $this->allowedChannels($agent),
                'name',
                $budget->maxEntries,
                $state,
                fn (Channel $channel): VfsEntry => new VfsEntry(
                    name: (string) $channel->id,
                    path: "/channels/by-id/{$channel->id}",
                    type: 'directory',
                    canonicalPath: "/channels/by-id/{$channel->id}",
                    backendType: 'channel',
                    backendId: $channel->id,
                    capabilities: ['browse', 'read', 'search'],
                    metadata: ['name' => $channel->name, 'type' => $channel->type],
                ),
            );
        }

        [$channel, $remaining] = $this->resolveChannelRoute($agent, $segments, $path);
        if ($remaining === ['messages']) {
            return $this->pageChannelMessageDays($channel, $path, $budget, $state);
        }

        if ($remaining === ['threads']) {
            return $this->paginateVfsStaticEntries([], $budget->maxEntries, $state);
        }

        if ($remaining !== []) {
            throw VfsError::notFound($path);
        }

        return $this->paginateVfsStaticEntries([
            new VfsEntry('messages', rtrim($path, '/').'/messages', 'directory', "/channels/by-id/{$channel->id}/messages", 'channel', $channel->id, capabilities: ['browse', 'read', 'search']),
            new VfsEntry('threads', rtrim($path, '/').'/threads', 'directory', "/channels/by-id/{$channel->id}/threads", 'channel', $channel->id, capabilities: ['browse']),
        ], $budget->maxEntries, $state);
    }

    /**
     * Page distinct transcript days newest-first. A day is already unique, so
     * it is its own immutable tie-breaker and no synthetic offset is needed.
     *
     * @param  array<string, mixed>  $state
     * @return array{entries: list<VfsEntry>, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageChannelMessageDays(Channel $channel, string $path, VfsBudget $budget, array $state): array
    {
        $after = $state['after_day'] ?? null;
        if ($after !== null && (! is_string($after) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $after) !== 1)) {
            throw $this->invalidVfsPageCursor();
        }

        $dayExpression = 'DATE(COALESCE(timestamp, created_at))';
        $query = $channel->messages()
            ->selectRaw("{$dayExpression} as day")
            ->when($after !== null, fn (Builder $query) => $query->whereRaw("{$dayExpression} < ?", [$after]))
            ->groupByRaw($dayExpression)
            ->orderByDesc('day')
            ->limit($budget->maxEntries + 1)
            ->get();
        $hasMore = $query->count() > $budget->maxEntries;
        $rows = $query->take($budget->maxEntries)->values();
        $entries = $rows->map(function (Message $row) use ($path, $channel): VfsEntry {
            $day = (string) $row->getAttribute('day');

            return new VfsEntry(
                $day.'.md',
                rtrim($path, '/').'/'.$day.'.md',
                'file',
                backendType: 'channel',
                backendId: $channel->id,
                capabilities: ['read', 'search'],
                metadata: [
                    'paged_collection' => true,
                    ...$this->readOnlyMetadata(
                        'Channel transcripts are generated, cursor-paged snapshots.',
                        'chat tools',
                        'Use vfs_read with next_cursor to continue; use chat tools to post messages.',
                    ),
                ],
            );
        })->all();
        $nextDay = $rows->isEmpty() ? $after : (string) $rows->last()->getAttribute('day');

        return [
            'entries' => $entries,
            'has_more' => $hasMore,
            'next_state' => ['after_day' => $nextDay],
            'scanned' => $rows->count(),
            'skipped' => 0,
        ];
    }

    /**
     * @param  list<string>  $segments
     */
    private function readChannels(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listChannels($agent, $segments, $path, $budget));
        }

        if (isset($segments[1]) && $segments[1] === 'by-id' && ! isset($segments[2])) {
            return $this->entriesToText($this->listChannels($agent, $segments, $path, $budget));
        }

        [$channel, $remaining] = $this->resolveChannelRoute($agent, $segments, $path);
        if (count($remaining) === 2 && $remaining[0] === 'messages' && preg_match('/^\d{4}-\d{2}-\d{2}\.md$/', $remaining[1]) === 1) {
            $day = substr($remaining[1], 0, 10);

            return $this->pageChannelTranscript($channel, $day, $path, $budget, [])['content'];
        }

        if ($remaining !== [] && $remaining !== ['messages'] && $remaining !== ['threads']) {
            throw VfsError::notFound($path);
        }

        return $this->entriesToText($this->listChannels($agent, $segments, $path, $budget));
    }

    /**
     * Return one chronological transcript chunk using the effective message
     * timestamp and message id. The id tie-breaker prevents equal timestamps
     * from duplicating or disappearing between continuation requests.
     *
     * @param  array<string, mixed>  $state
     * @return array{content: string, returned: int, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function pageChannelTranscript(Channel $channel, string $day, string $path, VfsBudget $budget, array $state): array
    {
        $after = $state['after'] ?? null;
        if ($after !== null && (
            ! is_array($after)
            || ! isset($after['timestamp'], $after['id'])
            || ! is_string($after['timestamp'])
            || ! is_string($after['id'])
        )) {
            throw $this->invalidVfsPageCursor();
        }

        $effectiveTimestamp = 'COALESCE(messages.timestamp, messages.created_at)';
        $query = $channel->messages()
            ->select('messages.*')
            ->selectRaw("{$effectiveTimestamp} as vfs_effective_timestamp")
            ->with('author:id,name')
            ->whereRaw("DATE({$effectiveTimestamp}) = ?", [$day]);

        if (is_array($after)) {
            $query->where(function (Builder $query) use ($after, $effectiveTimestamp): void {
                $query->whereRaw("{$effectiveTimestamp} > ?", [$after['timestamp']])
                    ->orWhere(function (Builder $query) use ($after, $effectiveTimestamp): void {
                        $query->whereRaw("{$effectiveTimestamp} = ?", [$after['timestamp']])
                            ->where('messages.id', '>', $after['id']);
                    });
            });
        }

        $messages = $query
            ->orderByRaw("{$effectiveTimestamp} asc")
            ->orderBy('messages.id')
            ->limit($budget->maxEntries + 1)
            ->get();
        $hasMore = $messages->count() > $budget->maxEntries;
        $records = [];
        $usedBytes = 0;
        $scanned = 0;
        $nextAfter = $after;

        foreach ($messages->take($budget->maxEntries) as $message) {
            $scanned++;
            $record = $this->channelTranscriptRecord($message);
            $additionalBytes = strlen($record) + ($records === [] ? 0 : 1);
            if (! $this->vfsRecordFitsByteBudget(
                $path,
                $budget->maxBytes,
                $usedBytes,
                $additionalBytes,
                count($records),
                (string) $message->id,
            )) {
                $hasMore = true;

                break;
            }

            $records[] = $record;
            $usedBytes += $additionalBytes;
            $nextAfter = [
                'timestamp' => (string) $message->getAttribute('vfs_effective_timestamp'),
                'id' => (string) $message->id,
            ];
        }

        return [
            'content' => implode("\n", $records),
            'returned' => count($records),
            'has_more' => $hasMore,
            'next_state' => ['after' => $nextAfter],
            'scanned' => $scanned,
            'skipped' => 0,
        ];
    }

    /** Render one complete transcript record before byte admission. */
    private function channelTranscriptRecord(Message $message): string
    {
        return sprintf(
            '[%s] %s: %s',
            ($message->timestamp ?? $message->created_at)?->format('H:i:s'),
            $message->author->name ?? 'Unknown',
            $message->content,
        );
    }

    /**
     * Resolve and authorize a transcript route before applying its cursor.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{content: string, returned: int, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function readChannelTranscriptPage(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        [$channel, $remaining] = $this->resolveChannelRoute($agent, $segments, $path);
        if (count($remaining) !== 2 || $remaining[0] !== 'messages' || preg_match('/^\d{4}-\d{2}-\d{2}\.md$/', $remaining[1]) !== 1) {
            throw VfsError::notFound($path);
        }

        return $this->pageChannelTranscript($channel, substr($remaining[1], 0, 10), $path, $budget, $state);
    }

    /**
     * Channels the agent may see, as one query (so pagination, by-id lookups
     * and search candidates share it). See the class docblock for the rule.
     *
     * @return Builder<Channel>
     */
    private function allowedChannels(User $agent): Builder
    {
        $query = Channel::forWorkspace()->orderBy('name');
        $allowed = $this->permissions->getAllowedChannelIds($agent);
        if ($allowed !== null) {
            $query->whereIn('id', $allowed);
        }

        // Invariant: other principals' DMs stay hidden by default. Only the
        // agent's own DMs or DMs an admin allowed by explicit id pass.
        $query->where(function (Builder $visible) use ($agent, $allowed): void {
            $visible->whereNull('type')
                ->orWhere('type', '!=', 'dm')
                ->orWhereHas('members', fn (Builder $members) => $members->where('user_id', $agent->id));
            if ($allowed !== null) {
                $visible->orWhereIn('id', $allowed);
            }
        });

        return $query;
    }

    /**
     * Slugs shared by more than one visible channel => their channel ids.
     *
     * @return array<string, list<string>>
     */
    private function channelSlugCollisions(User $agent): array
    {
        $bySlug = [];
        foreach ($this->allowedChannels($agent)->toBase()->get(['id', 'name']) as $row) {
            $bySlug[$this->slug((string) $row->name)][] = (string) $row->id;
        }

        return array_filter($bySlug, fn (array $ids): bool => count($ids) > 1);
    }

    /**
     * @param  array<string, list<string>>  $collisions
     */
    private function channelFriendlyName(Channel $channel, array $collisions): string
    {
        $slug = $this->slug($channel->name);

        return isset($collisions[$slug]) ? $slug.'--'.substr((string) $channel->id, 0, 8) : $slug;
    }

    /**
     * @param  list<string>  $parts
     */
    private function resolveChannel(User $agent, array $parts, string $path): Channel
    {
        $query = $this->allowedChannels($agent);

        if (isset($parts[0], $parts[1]) && $parts[0] === 'by-id') {
            $channel = $query->where('id', $parts[1])->first();

            return $channel ?? throw VfsError::notFound($path);
        }

        $token = $parts[0] ?? '';

        // Deterministic collision suffix: {slug}--{first 8 chars of the id}.
        if (preg_match('/^(.*)--([0-9a-z]{8})$/i', $token, $suffixed) === 1) {
            foreach ((clone $query)->where('id', 'like', strtolower($suffixed[2]).'%')->get() as $channel) {
                if ($this->slug($channel->name) === $suffixed[1]) {
                    return $channel;
                }
            }
        }

        $matches = [];
        foreach ($query->lazy(100) as $channel) {
            if ($this->slug($channel->name) === $token) {
                $matches[] = $channel;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $candidates = array_map(fn (Channel $channel): string => '/channels/'.$token.'--'.substr((string) $channel->id, 0, 8), $matches);

            throw new VfsError('ambiguous_match', "Channel name is ambiguous: {$token} matches ".count($matches).' channels.', [
                'path' => $path,
                'count' => count($matches),
                'candidates' => $candidates,
                'hint' => 'Use one of the suffixed paths in candidates, or /channels/by-id/{id}.',
            ]);
        }

        throw VfsError::notFound($path);
    }

    /**
     * @param  list<string>  $segments
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
                array_slice($segments, 3),
            ];
        }

        if (! isset($segments[1])) {
            throw VfsError::notFound($path);
        }

        return [
            $this->resolveChannel($agent, array_slice($segments, 1, 1), $path),
            array_slice($segments, 2),
        ];
    }

    /**
     * @param  array<string, list<string>>  $collisions  From channelSlugCollisions()
     */
    private function channelEntry(Channel $channel, array $collisions = []): VfsEntry
    {
        return new VfsEntry(
            name: $channel->name,
            path: '/channels/'.$this->channelFriendlyName($channel, $collisions),
            type: 'directory',
            canonicalPath: "/channels/by-id/{$channel->id}",
            backendType: 'channel',
            backendId: $channel->id,
            capabilities: ['browse', 'read', 'search'],
            metadata: ['type' => $channel->type, 'description' => $channel->description],
        );
    }
}

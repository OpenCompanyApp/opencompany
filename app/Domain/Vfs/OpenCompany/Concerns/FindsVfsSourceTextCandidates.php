<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Models\Channel;
use App\Models\DataTable;
use App\Models\Document;
use App\Models\ListItem;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceFile;

/**
 * Source-table candidate queries for VfsTextSearchService.
 *
 * These methods only find likely VFS paths. They do not decide final search
 * matches; the caller must still read each path through the VFS adapter so
 * permissions, redaction, budgets, and exact literal/regex matching apply.
 */
trait FindsVfsSourceTextCandidates
{
    /**
     * @return list<string>
     */
    private function documentCandidates(string $pattern, VfsBudget $budget): array
    {
        return Document::forWorkspace()
            ->where('is_folder', false)
            ->where(fn ($query) => $this->whereLikeAny($query, ['title', 'content'], $pattern))
            ->latest('updated_at')
            ->limit($budget->maxFiles)
            ->pluck('id')
            ->map(fn (string $id): string => "/docs/by-id/{$id}")
            ->all();
    }

    /**
     * @return list<string>
     */
    private function fileCandidates(User $agent, string $pattern, VfsBudget $budget): array
    {
        return WorkspaceFile::forWorkspace()
            ->where('is_folder', false)
            ->where(fn ($query) => $this->whereLikeAny($query, ['name', 'description'], $pattern))
            ->latest('updated_at')
            ->limit($budget->maxFiles)
            ->get()
            ->filter(fn (WorkspaceFile $file): bool => $this->permissions->canAccessFilePath($agent, $file))
            ->map(fn (WorkspaceFile $file): string => "/files/by-id/{$file->id}")
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function taskCandidates(string $pattern, VfsBudget $budget): array
    {
        return Task::forWorkspace()
            ->where(fn ($query) => $this->whereLikeAny($query, ['title', 'description', 'status'], $pattern))
            ->latest('updated_at')
            ->limit($budget->maxFiles)
            ->pluck('id')
            ->map(fn (string $id): string => "/tasks/by-id/{$id}")
            ->all();
    }

    /**
     * @return list<string>
     */
    private function listCandidates(string $pattern, VfsBudget $budget): array
    {
        return ListItem::forWorkspace()
            ->where(fn ($query) => $this->whereLikeAny($query, ['title', 'description', 'status'], $pattern))
            ->latest('updated_at')
            ->limit($budget->maxFiles)
            ->pluck('id')
            ->map(fn (string $id): string => "/lists/by-id/{$id}")
            ->all();
    }

    /**
     * @return list<string>
     */
    private function channelCandidates(User $agent, string $pattern, VfsBudget $budget): array
    {
        $allowed = $this->permissions->getAllowedChannelIds($agent);

        $messageQuery = Message::query()
            ->where(fn ($query) => $this->whereLikeAny($query, ['content'], $pattern))
            ->latest('timestamp')
            ->limit($budget->maxFiles);

        if ($allowed !== null) {
            $messageQuery->whereIn('channel_id', $allowed);
        } else {
            $messageQuery->whereHas('channel', fn ($query) => $query->where('workspace_id', $agent->workspace_id));
        }

        $paths = $messageQuery->get()
            ->map(function (Message $message): string {
                $channel = Channel::query()->find($message->channel_id);
                $day = ($message->timestamp ?? $message->created_at)?->toDateString() ?? now()->toDateString();

                return $channel
                    ? "/channels/by-id/{$channel->id}/messages/{$day}.md"
                    : '';
            })
            ->filter()
            ->values()
            ->all();

        $channelNamePaths = Channel::forWorkspace()
            ->when($allowed !== null, fn ($query) => $query->whereIn('id', $allowed))
            ->where(fn ($query) => $this->whereLikeAny($query, ['name', 'description'], $pattern))
            ->limit($budget->maxFiles)
            ->pluck('id')
            ->map(fn (string $id): string => "/channels/by-id/{$id}")
            ->all();

        return array_values(array_unique(array_merge($paths, $channelNamePaths)));
    }

    /**
     * @return list<string>
     */
    private function tableCandidates(string $pattern, VfsBudget $budget): array
    {
        return DataTable::forWorkspace()
            ->where(fn ($query) => $this->whereLikeAny($query, ['name', 'description'], $pattern))
            ->limit($budget->maxFiles)
            ->get()
            ->flatMap(fn (DataTable $table): array => [
                '/tables/'.str($table->name)->slug().'/schema.json',
                '/tables/'.str($table->name)->slug().'/rows.ndjson',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $columns
     */
    private function whereLikeAny(mixed $query, array $columns, string $pattern): void
    {
        $needle = '%'.$this->escapeLike(mb_strtolower($pattern)).'%';

        foreach ($columns as $index => $column) {
            $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
            $query->{$method}('LOWER(COALESCE('.$column.", '')) LIKE ? ESCAPE '\\'", [$needle]);
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}

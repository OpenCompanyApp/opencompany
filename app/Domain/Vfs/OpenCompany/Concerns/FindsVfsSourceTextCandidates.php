<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Models\Channel;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\ListItem;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Source-table candidate queries for VfsTextSearchService.
 *
 * These methods only find likely VFS paths. They do not decide final search
 * matches; the caller must still read each path through the VFS adapter so
 * permissions, redaction, budgets, and exact literal/regex matching apply.
 *
 * Index contract (database/migrations/*_vfs_trigram_indexes.php and
 * *_vfs_row_text_trigram_index.php): on PostgreSQL the predicate is
 * `column ILIKE ?` on the RAW column (or `(data::text) ILIKE ?` for rows),
 * which the pg_trgm GIN indexes serve. Do not wrap the column in LOWER() or
 * COALESCE(): an expression the index was not built on forces a sequential
 * scan. Other drivers use LOWER(column) LIKE lower(needle), a scan, which is
 * fine for sqlite tests and small installs. Candidates are a superset filter
 * (case-insensitive even for case-sensitive searches); exactness comes from
 * the verifying read.
 *
 * Ordering is deterministic (most recently updated first, id as tie-break) so
 * repeated searches visit candidates in the same order.
 */
trait FindsVfsSourceTextCandidates
{
    /**
     * @param  list<string>|null  $folderIds  Restrict to documents directly inside these folders (null = whole workspace)
     * @return list<string>
     */
    private function documentCandidates(string $pattern, VfsBudget $budget, ?array $folderIds = null): array
    {
        return Document::forWorkspace()
            ->where('is_folder', false)
            ->where('is_system', false)
            ->when($folderIds !== null, fn (Builder $query) => $query->whereIn('parent_id', $folderIds))
            ->where(fn (Builder $query) => $this->whereLikeAny($query, ['title', 'content'], $pattern))
            ->orderByDesc('updated_at')
            ->orderBy('id')
            ->limit($budget->maxFiles)
            ->pluck('id')
            ->map(fn (string $id): string => "/docs/by-id/{$id}")
            ->all();
    }

    /**
     * @param  list<string>|null  $folderIds
     * @return list<string>
     */
    private function fileCandidates(User $agent, string $pattern, VfsBudget $budget, ?array $folderIds = null): array
    {
        return WorkspaceFile::forWorkspace()
            ->where('is_folder', false)
            ->when($folderIds !== null, fn (Builder $query) => $query->whereIn('parent_id', $folderIds))
            ->where(fn (Builder $query) => $this->whereLikeAny($query, ['name', 'description'], $pattern))
            ->orderByDesc('updated_at')
            ->orderBy('id')
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
            ->where(fn (Builder $query) => $this->whereLikeAny($query, ['title', 'description', 'status'], $pattern))
            ->orderByDesc('updated_at')
            ->orderBy('id')
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
            ->where(fn (Builder $query) => $this->whereLikeAny($query, ['title', 'description', 'status'], $pattern))
            ->orderByDesc('updated_at')
            ->orderBy('id')
            ->limit($budget->maxFiles)
            ->pluck('id')
            ->map(fn (string $id): string => "/lists/by-id/{$id}")
            ->all();
    }

    /**
     * Message and channel-name candidates, restricted to $visibleChannels
     * (the mount's own visibility query, so DMs of other principals never
     * become candidates). One query for messages, one for names: no per-row
     * channel lookup.
     *
     * @param  Builder<Channel>  $visibleChannels
     * @return list<string>
     */
    private function channelCandidates(string $pattern, VfsBudget $budget, Builder $visibleChannels, ?string $channelId = null): array
    {
        $visibleIds = (clone $visibleChannels)->reorder()->select('channels.id');
        if ($channelId !== null) {
            $visibleIds->where('channels.id', $channelId);
        }

        $paths = Message::query()
            ->whereIn('channel_id', $visibleIds)
            ->where(fn (Builder $query) => $this->whereLikeAny($query, ['content'], $pattern))
            ->orderByDesc('timestamp')
            ->orderBy('id')
            ->limit($budget->maxFiles)
            ->get(['id', 'channel_id', 'timestamp', 'created_at'])
            ->map(function (Message $message): string {
                $day = ($message->timestamp ?? $message->created_at)?->toDateString() ?? now()->toDateString();

                return "/channels/by-id/{$message->channel_id}/messages/{$day}.md";
            })
            ->all();

        $channelNamePaths = $channelId !== null ? [] : (clone $visibleChannels)
            ->reorder()
            ->where(fn (Builder $query) => $this->whereLikeAny($query, ['name', 'description'], $pattern))
            ->orderBy('channels.id')
            ->limit($budget->maxFiles)
            ->pluck('channels.id')
            ->map(fn (string $id): string => "/channels/by-id/{$id}")
            ->all();

        return array_values(array_unique(array_merge($paths, $channelNamePaths)));
    }

    /**
     * Table schema candidates (name/description).
     *
     * @return list<string>
     */
    private function tableCandidates(string $pattern, VfsBudget $budget): array
    {
        return DataTable::forWorkspace()
            ->where(fn (Builder $query) => $this->whereLikeAny($query, ['name', 'description'], $pattern))
            ->orderBy('id')
            ->limit($budget->maxFiles)
            ->get(['id', 'name'])
            ->map(fn (DataTable $table): string => "/tables/by-id/{$table->id}/schema.json")
            ->values()
            ->all();
    }

    /**
     * Row candidates on ANY page: the row JSON rendered as text is matched,
     * then mapped to the row's own path, which the caller re-reads through the
     * table mount. Laravel's JSON cast escapes non-ASCII (\uXXXX) and the
     * JSON column keeps that text, so the escaped needle is matched too.
     *
     * Candidates are canonical `/tables/by-id/{tableId}/rows/by-id/{rowId}.json`
     * paths (table slugs may collide); the search facade maps them to the
     * friendly, disambiguated table token for display (searchDisplayPath).
     *
     * @return list<string>
     */
    private function rowCandidates(string $pattern, VfsBudget $budget, ?string $tableId = null): array
    {
        $expression = match (DB::connection()->getDriverName()) {
            'pgsql' => '(data::text)',
            'mysql', 'mariadb' => 'CAST(data AS CHAR)',
            default => 'CAST(data AS TEXT)',
        };
        $needles = array_values(array_unique([
            $pattern,
            trim((string) json_encode($pattern), '"'),
        ]));

        $rows = DataTableRow::query()
            ->whereIn('table_id', DataTable::forWorkspace()->select('id'))
            ->when($tableId !== null, fn (Builder $query) => $query->where('table_id', $tableId))
            ->where(function (Builder $query) use ($expression, $needles): void {
                foreach ($needles as $index => $needle) {
                    $this->whereLikeExpression($query, $expression, $needle, $index === 0 ? 'and' : 'or');
                }
            })
            ->orderByDesc('updated_at')
            ->orderBy('id')
            ->limit($budget->maxFiles)
            ->get(['id', 'table_id']);

        return $rows
            ->map(fn (DataTableRow $row): string => "/tables/by-id/{$row->table_id}/rows/by-id/{$row->id}.json")
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $columns
     */
    private function whereLikeAny(mixed $query, array $columns, string $pattern): void
    {
        foreach ($columns as $index => $column) {
            $this->whereLikeExpression($query, $column, $pattern, $index === 0 ? 'and' : 'or');
        }
    }

    /**
     * Driver-aware substring predicate; see the index contract above.
     */
    private function whereLikeExpression(mixed $query, string $expression, string $pattern, string $boolean = 'and'): void
    {
        $method = $boolean === 'and' ? 'whereRaw' : 'orWhereRaw';

        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->{$method}($expression." ILIKE ? ESCAPE '\\'", ['%'.$this->escapeLike($pattern).'%']);

            return;
        }

        $query->{$method}('LOWER('.$expression.") LIKE ? ESCAPE '\\'", ['%'.$this->escapeLike(mb_strtolower($pattern)).'%']);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}

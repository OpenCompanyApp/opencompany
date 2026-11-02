<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Runs stable, permission-first keyset scans for database-backed VFS mounts.
 *
 * The SQL batch size is intentionally not the visible page size. Every row is
 * authorized before it can consume a returned-entry slot, while the adapter's
 * scan ceiling prevents a sparse permission scope from turning one listing
 * request into an unbounded database walk. When that ceiling is reached, the
 * cursor advances over the rows already inspected—even when none were visible.
 */
trait PaginatesVfsMountQueries
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $baseQuery
     * @param  array<string, mixed>  $state
     * @param  Closure(TModel): bool  $canAccess
     * @param  Closure(TModel): VfsEntry  $toEntry
     * @return array{
     *     entries: list<VfsEntry>,
     *     has_more: bool,
     *     next_state: array<string, mixed>,
     *     scanned: int,
     *     skipped: int
     * }
     */
    private function paginateVfsMountQuery(
        Builder $baseQuery,
        string $labelColumn,
        int $limit,
        int $scanLimit,
        array $state,
        Closure $canAccess,
        Closure $toEntry,
        ?VfsEntry $prefixEntry = null,
    ): array {
        $after = $this->vfsKeysetState($state['after'] ?? null);

        return $this->paginateVfsKeysetQuery(
            baseQuery: $baseQuery,
            limit: $limit,
            scanLimit: $scanLimit,
            state: $state,
            after: $after,
            orderedQuery: fn (Builder $query, ?array $cursor): Builder => $this->vfsKeysetQuery($query, $labelColumn, $cursor),
            keysetForModel: fn (Model $model): array => $this->vfsKeysetForModel($model, $labelColumn),
            canAccess: $canAccess,
            toEntry: $toEntry,
            prefixEntry: $prefixEntry,
        );
    }

    /**
     * Page records by their existing recent-first collection order.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $baseQuery
     * @param  array<string, mixed>  $state
     * @param  Closure(TModel): VfsEntry  $toEntry
     * @return array{
     *     entries: list<VfsEntry>,
     *     has_more: bool,
     *     next_state: array<string, mixed>,
     *     scanned: int,
     *     skipped: int
     * }
     */
    private function paginateVfsRecentQuery(
        Builder $baseQuery,
        int $limit,
        int $scanLimit,
        array $state,
        Closure $toEntry,
        ?Closure $acceptEntry = null,
    ): array {
        $after = $this->vfsRecentKeysetState($state['after'] ?? null);

        return $this->paginateVfsKeysetQuery(
            baseQuery: $baseQuery,
            limit: $limit,
            scanLimit: $scanLimit,
            state: $state,
            after: $after,
            orderedQuery: fn (Builder $query, ?array $cursor): Builder => $this->vfsRecentKeysetQuery($query, $cursor),
            keysetForModel: fn (Model $model): array => $this->vfsRecentKeysetForModel($model),
            canAccess: fn (Model $model): bool => true,
            toEntry: $toEntry,
            acceptEntry: $acceptEntry,
        );
    }

    /**
     * Page small identity indexes in stable label/id order.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $baseQuery
     * @param  array<string, mixed>  $state
     * @param  Closure(TModel): VfsEntry  $toEntry
     * @return array{
     *     entries: list<VfsEntry>,
     *     has_more: bool,
     *     next_state: array<string, mixed>,
     *     scanned: int,
     *     skipped: int
     * }
     */
    private function paginateVfsLabelQuery(
        Builder $baseQuery,
        string $labelColumn,
        int $limit,
        array $state,
        Closure $toEntry,
        ?VfsEntry $prefixEntry = null,
        ?Closure $canAccess = null,
        ?int $scanLimit = null,
        ?Closure $acceptEntry = null,
    ): array {
        $after = $this->vfsLabelKeysetState($state['after'] ?? null);

        return $this->paginateVfsKeysetQuery(
            baseQuery: $baseQuery,
            limit: $limit,
            scanLimit: $scanLimit ?? $limit,
            state: $state,
            after: $after,
            orderedQuery: fn (Builder $query, ?array $cursor): Builder => $this->vfsLabelKeysetQuery($query, $labelColumn, $cursor),
            keysetForModel: fn (Model $model): array => $this->vfsLabelKeysetForModel($model, $labelColumn),
            canAccess: $canAccess ?? fn (Model $model): bool => true,
            toEntry: $toEntry,
            prefixEntry: $prefixEntry,
            acceptEntry: $acceptEntry,
        );
    }

    /**
     * Page a timestamp/id collection without changing its established order.
     *
     * Unlike paginateVfsRecentQuery(), this helper is not tied to updated_at or
     * descending order. Approval creation history, saved table views, and
     * transcript messages each have a different domain timestamp contract.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $baseQuery
     * @param  'asc'|'desc'  $direction
     * @param  array<string, mixed>  $state
     * @param  Closure(TModel): VfsEntry  $toEntry
     * @return array{entries: list<VfsEntry>, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int}
     */
    private function paginateVfsTimestampQuery(
        Builder $baseQuery,
        string $timestampColumn,
        string $direction,
        int $limit,
        int $scanLimit,
        array $state,
        Closure $toEntry,
    ): array {
        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new \LogicException('VFS timestamp pagination direction must be asc or desc.');
        }

        $after = $this->vfsTimestampKeysetState($state['after'] ?? null, $timestampColumn);

        return $this->paginateVfsKeysetQuery(
            baseQuery: $baseQuery,
            limit: $limit,
            scanLimit: $scanLimit,
            state: $state,
            after: $after,
            orderedQuery: fn (Builder $query, ?array $cursor): Builder => $this->vfsTimestampKeysetQuery(
                $query,
                $timestampColumn,
                $direction,
                $cursor,
            ),
            keysetForModel: fn (Model $model): array => $this->vfsTimestampKeysetForModel($model, $timestampColumn),
            canAccess: fn (Model $model): bool => true,
            toEntry: $toEntry,
        );
    }

    /**
     * Page a fixed or deliberately low-cardinality virtual index.
     *
     * @param  list<VfsEntry>  $entries
     * @param  array<string, mixed>  $state
     * @return array{
     *     entries: list<VfsEntry>,
     *     has_more: bool,
     *     next_state: array<string, mixed>,
     *     scanned: int,
     *     skipped: int
     * }
     */
    private function paginateVfsStaticEntries(array $entries, int $limit, array $state): array
    {
        $offset = $state['offset'] ?? 0;
        if (! is_int($offset) || $offset < 0) {
            throw $this->invalidVfsPageCursor();
        }

        $pageEntries = array_slice($entries, $offset, $limit);

        return [
            'entries' => $pageEntries,
            'has_more' => count($entries) > $offset + count($pageEntries),
            'next_state' => ['offset' => $offset + count($pageEntries)],
            'scanned' => count($pageEntries),
            'skipped' => 0,
        ];
    }

    /**
     * Shared bounded scan loop for adapter-specific keysets.
     *
     * @param  Builder<covariant Model>  $baseQuery
     * @param  array<string, mixed>  $state
     * @param  array<string, bool|string>|null  $after
     * @param  Closure  $orderedQuery  Clones/orders the base query after the current adapter keyset.
     * @param  Closure  $keysetForModel  Extracts the adapter keyset from one persisted model.
     * @param  Closure  $canAccess  Applies row visibility before a visible slot is consumed.
     * @param  Closure  $toEntry  Projects one authorized model into its VFS entry.
     * @param  Closure|null  $acceptEntry  Admits a projected entry before its keyset is committed.
     * @return array{
     *     entries: list<VfsEntry>,
     *     has_more: bool,
     *     next_state: array<string, mixed>,
     *     scanned: int,
     *     skipped: int
     * }
     */
    private function paginateVfsKeysetQuery(
        Builder $baseQuery,
        int $limit,
        int $scanLimit,
        array $state,
        ?array $after,
        Closure $orderedQuery,
        Closure $keysetForModel,
        Closure $canAccess,
        Closure $toEntry,
        ?VfsEntry $prefixEntry = null,
        ?Closure $acceptEntry = null,
    ): array {
        $prefixConsumed = $state['prefix_consumed'] ?? ($prefixEntry === null);
        if (! is_bool($prefixConsumed)) {
            throw $this->invalidVfsPageCursor();
        }

        $entries = [];
        $scanned = 0;
        $skipped = 0;

        if ($prefixEntry !== null && ! $prefixConsumed) {
            $entries[] = $prefixEntry;
            $prefixConsumed = true;

            if (count($entries) >= $limit) {
                $hasMore = $orderedQuery($baseQuery, $after)->exists();

                return $this->vfsMountPageResult($entries, $hasMore, $after, true, $scanned, $skipped);
            }
        }

        // A page must always be able to inspect enough rows to fill its visible
        // limit when every row is allowed. maxFiles only governs extra work
        // caused by hidden or otherwise filtered backend records.
        $scanLimit = max($limit, $scanLimit);

        while (count($entries) < $limit && $scanned < $scanLimit) {
            $remainingScan = $scanLimit - $scanned;
            $batchSize = min(100, $remainingScan);
            $rows = $orderedQuery($baseQuery, $after)
                ->limit($batchSize + 1)
                ->get();
            $hasRawExtra = $rows->count() > $batchSize;
            $processable = $rows->take($batchSize)->values();

            if ($processable->isEmpty()) {
                return $this->vfsMountPageResult($entries, false, $after, $prefixConsumed, $scanned, $skipped);
            }

            foreach ($processable as $index => $row) {
                $scanned++;
                $rowAfter = $keysetForModel($row);

                if (! $canAccess($row)) {
                    $after = $rowAfter;
                    $skipped++;

                    continue;
                }

                $entry = $toEntry($row);
                if ($acceptEntry !== null && ! $acceptEntry($entries, $entry)) {
                    // The visible record was inspected but not emitted. Keep
                    // the prior keyset so the continuation retries this exact
                    // record with the caller's next byte budget instead of
                    // losing it behind an advanced cursor.
                    return $this->vfsMountPageResult($entries, true, $after, $prefixConsumed, $scanned, $skipped);
                }

                $entries[] = $entry;
                $after = $rowAfter;
                if (count($entries) >= $limit) {
                    $hasMore = $hasRawExtra || $index < $processable->count() - 1;

                    return $this->vfsMountPageResult($entries, $hasMore, $after, $prefixConsumed, $scanned, $skipped);
                }
            }

            if (! $hasRawExtra) {
                return $this->vfsMountPageResult($entries, false, $after, $prefixConsumed, $scanned, $skipped);
            }
        }

        // Reaching the scan ceiling is a continuation condition, not an exact
        // empty result. Advancing after the last inspected tuple ensures hidden
        // rows never trap the caller on the same page.
        $hasMore = $orderedQuery($baseQuery, $after)->exists();

        return $this->vfsMountPageResult($entries, $hasMore, $after, $prefixConsumed, $scanned, $skipped);
    }

    /**
     * Decide whether one complete generated-file record fits the byte budget.
     *
     * Returning false is safe only after at least one record was emitted: the
     * pagination loop deliberately leaves the candidate's keyset uncommitted.
     * A leading record that cannot fit would otherwise produce an endless
     * empty-page cursor loop, so fail with the exact budget required for a
     * caller to retry without truncating structured content.
     */
    private function vfsRecordFitsByteBudget(
        string $path,
        int $maxBytes,
        int $usedBytes,
        int $additionalBytes,
        int $returned,
        ?string $recordId = null,
    ): bool {
        $minimumBytes = $usedBytes + $additionalBytes;
        if ($minimumBytes <= $maxBytes) {
            return true;
        }

        if ($returned > 0) {
            return false;
        }

        $subject = $recordId === null ? 'valid structured envelope' : 'next complete record';

        throw VfsError::invalid(
            "maxBytes is too small for the {$subject} in {$path}.",
            array_filter([
                'field' => 'maxBytes',
                'path' => $path,
                'max_bytes' => $maxBytes,
                'minimum_bytes' => $minimumBytes,
                'record_id' => $recordId,
                'suggestion' => "Retry with maxBytes >= {$minimumBytes}.",
            ], fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $baseQuery
     * @param  array{folder: bool, label: string, id: string}|null  $after
     * @return Builder<TModel>
     */
    private function vfsKeysetQuery(Builder $baseQuery, string $labelColumn, ?array $after): Builder
    {
        $query = clone $baseQuery;
        $model = $query->getModel();
        $folderColumn = $model->qualifyColumn('is_folder');
        $qualifiedLabel = $model->qualifyColumn($labelColumn);
        $idColumn = $model->qualifyColumn($model->getKeyName());

        if ($after !== null) {
            // Keep this predicate in the exact same order as the ORDER BY. The
            // immutable id tie-breaker prevents offset drift for duplicate names
            // and for inserts/deletes before the current page.
            $query->where(function (Builder $query) use ($after, $folderColumn, $qualifiedLabel, $idColumn): void {
                $query->where($folderColumn, '<', $after['folder'])
                    ->orWhere(function (Builder $query) use ($after, $folderColumn, $qualifiedLabel, $idColumn): void {
                        $query->where($folderColumn, $after['folder'])
                            ->where(function (Builder $query) use ($after, $qualifiedLabel, $idColumn): void {
                                $query->where($qualifiedLabel, '>', $after['label'])
                                    ->orWhere(function (Builder $query) use ($after, $qualifiedLabel, $idColumn): void {
                                        $query->where($qualifiedLabel, $after['label'])
                                            ->where($idColumn, '>', $after['id']);
                                    });
                            });
                    });
            });
        }

        return $query
            ->orderByDesc($folderColumn)
            ->orderBy($qualifiedLabel)
            ->orderBy($idColumn);
    }

    /**
     * @return array{folder: bool, label: string, id: string}
     */
    private function vfsKeysetForModel(Model $model, string $labelColumn): array
    {
        $label = $model->getAttribute($labelColumn);
        $id = $model->getKey();
        if (! is_string($label) || ! is_string($id)) {
            throw new \LogicException('VFS keyset mounts require string labels and primary keys.');
        }

        return [
            'folder' => (bool) $model->getAttribute('is_folder'),
            'label' => $label,
            'id' => $id,
        ];
    }

    /**
     * @return array{folder: bool, label: string, id: string}|null
     */
    private function vfsKeysetState(mixed $after): ?array
    {
        if ($after === null) {
            return null;
        }

        if (
            ! is_array($after)
            || ! isset($after['folder'], $after['label'], $after['id'])
            || ! is_bool($after['folder'])
            || ! is_string($after['label'])
            || ! is_string($after['id'])
        ) {
            throw $this->invalidVfsPageCursor();
        }

        return [
            'folder' => $after['folder'],
            'label' => $after['label'],
            'id' => $after['id'],
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $baseQuery
     * @param  array{updated_at: string, id: string}|null  $after
     * @return Builder<TModel>
     */
    private function vfsRecentKeysetQuery(Builder $baseQuery, ?array $after): Builder
    {
        $query = clone $baseQuery;
        $model = $query->getModel();
        $updatedAtColumn = $model->qualifyColumn('updated_at');
        $idColumn = $model->qualifyColumn($model->getKeyName());

        if ($after !== null) {
            $query->where(function (Builder $query) use ($after, $updatedAtColumn, $idColumn): void {
                $query->where($updatedAtColumn, '<', $after['updated_at'])
                    ->orWhere(function (Builder $query) use ($after, $updatedAtColumn, $idColumn): void {
                        $query->where($updatedAtColumn, $after['updated_at'])
                            ->where($idColumn, '<', $after['id']);
                    });
            });
        }

        return $query
            ->orderByDesc($updatedAtColumn)
            ->orderByDesc($idColumn);
    }

    /**
     * Use the database's exact timestamp representation in the cursor. This is
     * important for equality comparisons on SQLite and for preserving whatever
     * fractional precision the production database actually stored.
     *
     * @return array{updated_at: string, id: string}
     */
    private function vfsRecentKeysetForModel(Model $model): array
    {
        $updatedAt = $model->getRawOriginal('updated_at');
        $id = $model->getKey();
        if (! is_string($updatedAt) || ! is_string($id)) {
            throw new \LogicException('Recent-first VFS mounts require persisted timestamps and string primary keys.');
        }

        return [
            'updated_at' => $updatedAt,
            'id' => $id,
        ];
    }

    /**
     * @return array{updated_at: string, id: string}|null
     */
    private function vfsRecentKeysetState(mixed $after): ?array
    {
        if ($after === null) {
            return null;
        }

        if (
            ! is_array($after)
            || ! isset($after['updated_at'], $after['id'])
            || ! is_string($after['updated_at'])
            || ! is_string($after['id'])
        ) {
            throw $this->invalidVfsPageCursor();
        }

        return [
            'updated_at' => $after['updated_at'],
            'id' => $after['id'],
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $baseQuery
     * @param  'asc'|'desc'  $direction
     * @param  array{timestamp: string, id: string}|null  $after
     * @return Builder<TModel>
     */
    private function vfsTimestampKeysetQuery(Builder $baseQuery, string $timestampColumn, string $direction, ?array $after): Builder
    {
        $query = clone $baseQuery;
        $model = $query->getModel();
        $qualifiedTimestamp = $model->qualifyColumn($timestampColumn);
        $idColumn = $model->qualifyColumn($model->getKeyName());
        $operator = $direction === 'asc' ? '>' : '<';

        if ($after !== null) {
            $query->where(function (Builder $query) use ($after, $qualifiedTimestamp, $idColumn, $operator): void {
                $query->where($qualifiedTimestamp, $operator, $after['timestamp'])
                    ->orWhere(function (Builder $query) use ($after, $qualifiedTimestamp, $idColumn, $operator): void {
                        $query->where($qualifiedTimestamp, $after['timestamp'])
                            ->where($idColumn, $operator, $after['id']);
                    });
            });
        }

        return $query
            ->orderBy($qualifiedTimestamp, $direction)
            ->orderBy($idColumn, $direction);
    }

    /** @return array{column: string, timestamp: string, id: string} */
    private function vfsTimestampKeysetForModel(Model $model, string $timestampColumn): array
    {
        $timestamp = $model->getRawOriginal($timestampColumn);
        $id = $model->getKey();
        if (! is_string($timestamp) || ! is_string($id)) {
            throw new \LogicException('Timestamp-indexed VFS mounts require persisted timestamps and string primary keys.');
        }

        return ['column' => $timestampColumn, 'timestamp' => $timestamp, 'id' => $id];
    }

    /** @return array{timestamp: string, id: string}|null */
    private function vfsTimestampKeysetState(mixed $after, string $timestampColumn): ?array
    {
        if ($after === null) {
            return null;
        }

        if (
            ! is_array($after)
            || ($after['column'] ?? null) !== $timestampColumn
            || ! isset($after['timestamp'], $after['id'])
            || ! is_string($after['timestamp'])
            || ! is_string($after['id'])
        ) {
            throw $this->invalidVfsPageCursor();
        }

        return ['column' => $timestampColumn, 'timestamp' => $after['timestamp'], 'id' => $after['id']];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $baseQuery
     * @param  array{label: string, id: string}|null  $after
     * @return Builder<TModel>
     */
    private function vfsLabelKeysetQuery(Builder $baseQuery, string $labelColumn, ?array $after): Builder
    {
        $query = clone $baseQuery;
        $model = $query->getModel();
        $qualifiedLabel = $model->qualifyColumn($labelColumn);
        $idColumn = $model->qualifyColumn($model->getKeyName());

        if ($after !== null) {
            $query->where(function (Builder $query) use ($after, $qualifiedLabel, $idColumn): void {
                $query->where($qualifiedLabel, '>', $after['label'])
                    ->orWhere(function (Builder $query) use ($after, $qualifiedLabel, $idColumn): void {
                        $query->where($qualifiedLabel, $after['label'])
                            ->where($idColumn, '>', $after['id']);
                    });
            });
        }

        return $query
            ->orderBy($qualifiedLabel)
            ->orderBy($idColumn);
    }

    /**
     * @return array{label: string, id: string}
     */
    private function vfsLabelKeysetForModel(Model $model, string $labelColumn): array
    {
        $label = $model->getAttribute($labelColumn);
        $id = $model->getKey();
        if (! is_string($label) || ! is_string($id)) {
            throw new \LogicException('Label-indexed VFS mounts require string labels and primary keys.');
        }

        return ['label' => $label, 'id' => $id];
    }

    /**
     * @return array{label: string, id: string}|null
     */
    private function vfsLabelKeysetState(mixed $after): ?array
    {
        if ($after === null) {
            return null;
        }

        if (
            ! is_array($after)
            || ! isset($after['label'], $after['id'])
            || ! is_string($after['label'])
            || ! is_string($after['id'])
        ) {
            throw $this->invalidVfsPageCursor();
        }

        return ['label' => $after['label'], 'id' => $after['id']];
    }

    /**
     * @param  list<VfsEntry>  $entries
     * @param  array<string, bool|string>|null  $after
     * @return array{
     *     entries: list<VfsEntry>,
     *     has_more: bool,
     *     next_state: array<string, mixed>,
     *     scanned: int,
     *     skipped: int
     * }
     */
    private function vfsMountPageResult(
        array $entries,
        bool $hasMore,
        ?array $after,
        bool $prefixConsumed,
        int $scanned,
        int $skipped,
    ): array {
        return [
            'entries' => $entries,
            'has_more' => $hasMore,
            'next_state' => [
                'prefix_consumed' => $prefixConsumed,
                'after' => $after,
            ],
            'scanned' => $scanned,
            'skipped' => $skipped,
        ];
    }

    private function invalidVfsPageCursor(): VfsError
    {
        return VfsError::invalid('cursor is invalid or expired.', ['field' => 'cursor']);
    }
}

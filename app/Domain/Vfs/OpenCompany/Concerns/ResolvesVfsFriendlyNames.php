<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Stable, guessable friendly names for slug-addressed VFS mounts.
 *
 * Owns the naming contract shared by /docs (siblings in one folder),
 * /tables and /agents:
 *
 * - A record's friendly token is slug(label). When two or more siblings share
 *   a slug, EVERY colliding sibling is listed as `{slug}--{id8}` (the LAST
 *   eight id characters: ordered UUIDv7 ids share their leading timestamp
 *   characters, the tail is random), so a friendly path never silently picks
 *   "the first match". Slugs never contain `--`, so the suffix cannot be
 *   confused with a name. The canonical by-id path is always unambiguous.
 * - Resolving a bare slug that several siblings share fails with
 *   `ambiguous_match` listing the suffixed paths, instead of guessing.
 * - `{slug}--{id8}` resolves by id prefix, the exact label resolves when it
 *   is unique, and (for documents) a trailing `.md` is accepted.
 * - `not_found` carries `did_you_mean` (closest sibling paths) and, when the
 *   caller knows it, a `by_id` hint, so an agent's next call can succeed.
 *
 * Resolution narrows candidates with indexed SQL (id prefix, exact label,
 * LIKE over the slug words) and only falls back to scanning sibling labels
 * when the slug cannot be expressed as a LIKE pattern (transliterated or
 * percent-encoded titles). Scans select only id + label.
 *
 * This trait never authorizes. Callers pass sibling queries that are already
 * workspace scoped (forWorkspace() or an explicit workspace_id predicate) and
 * apply their own read/write checks to the returned model.
 */
trait ResolvesVfsFriendlyNames
{
    /**
     * Sibling id => friendly token, memoized per sibling query for one call
     * chain. Keys are the SQL + bindings of the sibling query.
     *
     * @var array<string, array<string, string>>
     */
    private array $friendlyTokenCache = [];

    /** `{name}--{id8}`: group 1 = name part, group 2 = id suffix (shorter only for short non-UUID ids). */
    private const ID_SUFFIX_PATTERN = '/^(.*)--([0-9A-Za-z]{1,8})$/';

    /** The collision/trash suffix for an id: its last eight characters. */
    private function idSuffix(string $id): string
    {
        return substr($id, -8);
    }

    /**
     * Friendly token for one record among its siblings: slug, or slug--id8
     * when another sibling shares the slug.
     *
     * @param  Builder<covariant Model>  $siblings
     */
    private function friendlyToken(Builder $siblings, string $labelColumn, Model $record): string
    {
        $tokens = $this->friendlyTokens($siblings, $labelColumn);
        $id = (string) $record->getKey();

        return $tokens[$id] ?? $this->slug((string) $record->getAttribute($labelColumn));
    }

    /**
     * @param  Builder<covariant Model>  $siblings
     * @return array<string, string> id => friendly token
     */
    private function friendlyTokens(Builder $siblings, string $labelColumn): array
    {
        $key = $siblings->toSql().'|'.json_encode($siblings->getBindings()).'|'.$labelColumn;
        if (isset($this->friendlyTokenCache[$key])) {
            return $this->friendlyTokenCache[$key];
        }

        $rows = $this->friendlySiblingRows($siblings, $labelColumn);
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['slug']] = ($counts[$row['slug']] ?? 0) + 1;
        }

        $tokens = [];
        foreach ($rows as $row) {
            $tokens[$row['id']] = $counts[$row['slug']] > 1
                ? $row['slug'].'--'.$this->idSuffix($row['id'])
                : $row['slug'];
        }

        // Bounded memo: listings in one request touch a handful of folders.
        if (count($this->friendlyTokenCache) > 32) {
            $this->friendlyTokenCache = [];
        }

        return $this->friendlyTokenCache[$key] = $tokens;
    }

    /**
     * Resolve one friendly path segment among siblings. Callers narrow the
     * returned model to their concrete class with instanceof.
     *
     * @param  Builder<covariant Model>  $siblings  workspace-scoped sibling query
     * @param  string  $parentPath  display path of the parent, used to build suggestions
     * @param  (callable(Model): string)|null  $byIdPath  canonical path builder for suggestions
     *
     * @throws VfsError not_found (with did_you_mean) or ambiguous_match
     */
    private function resolveFriendlySegment(
        Builder $siblings,
        string $labelColumn,
        string $token,
        string $parentPath,
        string $path,
        ?callable $byIdPath = null,
        bool $allowMarkdownSuffix = false,
    ): Model {
        $match = $this->matchFriendlySegment($siblings, $labelColumn, $token, $parentPath, $path);
        if ($match === null && $allowMarkdownSuffix && str_ends_with(strtolower($token), '.md') && strlen($token) > 3) {
            $match = $this->matchFriendlySegment($siblings, $labelColumn, substr($token, 0, -3), $parentPath, $path);
        }

        return $match ?? throw $this->friendlyNotFound($siblings, $labelColumn, $token, $parentPath, $path, $byIdPath);
    }

    /**
     * @param  Builder<covariant Model>  $siblings
     */
    private function matchFriendlySegment(Builder $siblings, string $labelColumn, string $token, string $parentPath, string $path): ?Model
    {
        if ($token === '') {
            return null;
        }

        // 1. Deterministic collision suffix: {slug}--{id8}. Scoped to the
        //    sibling set (parent predicate), so the id LIKE stays cheap; the
        //    slug part must still agree.
        if (preg_match(self::ID_SUFFIX_PATTERN, $token, $m) === 1) {
            $byPrefix = (clone $siblings)
                ->where($siblings->getModel()->getQualifiedKeyName(), 'like', '%'.$m[2])
                ->limit(5)
                ->get()
                ->filter(fn (Model $candidate): bool => $this->slug((string) $candidate->getAttribute($labelColumn)) === $m[1]);
            if ($byPrefix->count() === 1) {
                return $byPrefix->first();
            }
        }

        // 2. Exact label (e.g. "Q3 Report" typed verbatim) when it is unique
        //    and differs from its slug form.
        $exact = (clone $siblings)->where($labelColumn, $token)->limit(2)->get();
        if ($exact->count() === 1 && $this->slug((string) $exact->first()->getAttribute($labelColumn)) !== $token) {
            return $exact->first();
        }

        // 3. Slug match among candidates narrowed by the slug's words.
        $matches = $this->slugCandidates($siblings, $labelColumn, $token)
            ->filter(fn (Model $candidate): bool => $this->slug((string) $candidate->getAttribute($labelColumn)) === $token
                || (string) $candidate->getAttribute($labelColumn) === $token)
            ->values();

        if ($matches->count() === 1) {
            return $matches->first();
        }
        if ($matches->count() > 1) {
            $paths = $matches
                ->map(fn (Model $candidate): string => rtrim($parentPath, '/').'/'.$this->slug((string) $candidate->getAttribute($labelColumn)).'--'.$this->idSuffix((string) $candidate->getKey()))
                ->values()
                ->all();

            throw new VfsError('ambiguous_match', "Path {$path} matches {$matches->count()} entries with the same name.", [
                'path' => $path,
                'count' => $matches->count(),
                'matches' => $paths,
                'hint' => 'Use one of the suffixed paths in "matches" (or the canonical by-id path).',
            ]);
        }

        return null;
    }

    /**
     * Candidates whose label could slug to $token. Uses LOWER(label) LIKE
     * %w1%w2% (the slug's words in order) when the token is a plain slug;
     * otherwise scans sibling labels.
     *
     * @param  Builder<covariant Model>  $siblings
     * @return \Illuminate\Support\Collection<int, Model>
     */
    private function slugCandidates(Builder $siblings, string $labelColumn, string $token): \Illuminate\Support\Collection
    {
        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $token) === 1) {
            $pattern = '%'.str_replace('-', '%', $token).'%';
            $candidates = (clone $siblings)
                ->whereRaw('LOWER('.$siblings->getQuery()->getGrammar()->wrap($labelColumn).') LIKE ?', [$pattern])
                ->limit(200)
                ->get();
            if ($candidates->isNotEmpty()) {
                return $candidates;
            }
        }

        // Fallback: labels whose slug differs from their lowercase words
        // (transliteration, symbols-only titles). Bounded id+label scan.
        $ids = collect($this->friendlySiblingRows($siblings, $labelColumn))
            ->filter(fn (array $row): bool => $row['slug'] === $token || $row['label'] === $token)
            ->pluck('id')
            ->all();

        return $ids === []
            ? collect()
            : (clone $siblings)->whereKey($ids)->get();
    }

    /**
     * Build `not_found` with nearest sibling suggestions.
     *
     * @param  Builder<covariant Model>  $siblings
     * @param  (callable(Model): string)|null  $byIdPath
     */
    private function friendlyNotFound(Builder $siblings, string $labelColumn, string $token, string $parentPath, string $path, ?callable $byIdPath): VfsError
    {
        $rows = $this->friendlySiblingRows($siblings, $labelColumn, 500);
        $tokens = $this->friendlyTokens($siblings, $labelColumn);
        $needle = strtolower(preg_replace('/\.md$/i', '', $token) ?? $token);

        $scored = [];
        foreach ($rows as $row) {
            $candidate = $tokens[$row['id']] ?? $row['slug'];
            $distance = levenshtein($needle, substr($row['slug'], 0, 255));
            if (str_contains($row['slug'], $needle) || str_contains($needle, $row['slug'])) {
                $distance = min($distance, 1);
            }
            $scored[] = ['distance' => $distance, 'path' => rtrim($parentPath, '/').'/'.$candidate, 'id' => $row['id']];
        }
        usort($scored, fn (array $a, array $b): int => $a['distance'] <=> $b['distance']);
        $limit = max(3, intdiv(strlen($needle), 2));
        $suggestions = array_slice(array_values(array_filter($scored, fn (array $s): bool => $s['distance'] <= $limit)), 0, 3);

        $didYouMean = array_map(fn (array $s): string => $s['path'], $suggestions);
        $context = ['path' => $path];
        if ($didYouMean !== []) {
            $context['did_you_mean'] = $didYouMean;
            if ($byIdPath !== null) {
                $first = (clone $siblings)->whereKey($suggestions[0]['id'])->first();
                if ($first !== null) {
                    $context['by_id'] = $byIdPath($first);
                }
            }
        }
        $context['hint'] = $didYouMean !== []
            ? 'Check the name with ls on the parent directory; friendly names are slugs, and collisions carry a --{id8} suffix.'
            : "List {$parentPath} to see what exists.";

        $message = "Path not found: {$path}".($didYouMean !== [] ? '. Did you mean: '.implode(', ', $didYouMean).'?' : '');

        return new VfsError('not_found', $message, $context);
    }

    /**
     * id + label + slug of every sibling (bounded), using the scoped query so
     * soft-deleted and other-workspace rows never participate.
     *
     * @param  Builder<covariant Model>  $siblings
     * @return list<array{id: string, label: string, slug: string}>
     */
    private function friendlySiblingRows(Builder $siblings, string $labelColumn, int $limit = 5000): array
    {
        $model = $siblings->getModel();

        return (clone $siblings)
            ->toBase()
            ->select([$model->getQualifiedKeyName().' as friendly_id', $model->qualifyColumn($labelColumn).' as friendly_label'])
            ->orderBy($model->getQualifiedKeyName())
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->friendly_id,
                'label' => (string) $row->friendly_label,
                'slug' => $this->slug((string) $row->friendly_label),
            ])
            ->values()
            ->all();
    }
}

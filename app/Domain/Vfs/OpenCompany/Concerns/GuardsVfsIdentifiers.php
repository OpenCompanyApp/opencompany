<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;

/**
 * Safe use of untrusted path tokens against primary keys.
 *
 * VFS paths are chosen by agents. On PostgreSQL several tables (tasks,
 * data_tables, data_table_rows, data_table_views) key on a `uuid` column, and
 * comparing a uuid column with a non-uuid literal (`= 'abc'` or `LIKE '%abc'`)
 * is an ERROR, not a non-match. Inside a transaction (every VFS mutation, and
 * every test) that error aborts the transaction: the first failure may be
 * caught, but every later statement fails with "current transaction is
 * aborted". So:
 *
 * - exact lookups of uuid-keyed models go through vfsUuidOrNull(): a malformed
 *   token is simply not found and no query is issued;
 * - suffix matches (`name--1a2b3c4d`) go through whereIdLike(), which compares
 *   the key as TEXT, valid on SQLite, PostgreSQL and MySQL for any key type.
 *
 * Breadcrumb: do not "simplify" these back to find($token) / where('id',
 * 'like', ...); the SQLite test suite cannot see the difference, PostgreSQL
 * (production and the CI pgsql job) can.
 */
trait GuardsVfsIdentifiers
{
    /** The token when it is uuid-shaped, else null (callers then report not_found). */
    private function vfsUuidOrNull(mixed $token): ?string
    {
        return is_string($token) && Str::isUuid($token) ? $token : null;
    }

    /**
     * Constrain $column LIKE $pattern compared as text (portable for uuid keys).
     *
     * @template TQuery of Builder|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function whereIdLike(mixed $query, string $column, string $pattern): mixed
    {
        $base = $query->getQuery();
        $connection = $base->getConnection();
        // MySQL spells the text cast CHAR; SQLite and PostgreSQL accept TEXT.
        $textType = $connection instanceof Connection && $connection->getDriverName() === 'mysql' ? 'CHAR' : 'TEXT';

        return $query->whereRaw('CAST('.$base->getGrammar()->wrap($column).' AS '.$textType.') LIKE ?', [$pattern]);
    }
}

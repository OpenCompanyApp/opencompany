# Schema baseline

All migrations up to `2026_11_02_000006` were squashed on 2026-11-02 into these
dumps. On a database without a `migrations` table, `php artisan migrate` loads
the dump for the connection first, then runs any newer files in
`database/migrations/`.

- `pgsql-schema.sql`: production and the PostgreSQL CI job. It is loaded with
  `psql`, so the runtime image installs `postgresql-client`.
- `sqlite-schema.sql`: the in-memory SQLite test suite.

## Regenerating

Migrate two fresh databases (never the dev database), then dump:

```bash
createdb opencompany_squash
DB_DATABASE=opencompany_squash php artisan migrate --force
DB_DATABASE=opencompany_squash php artisan schema:dump

touch /tmp/squash.sqlite
DB_CONNECTION=sqlite DB_DATABASE=/tmp/squash.sqlite php artisan migrate --force
DB_CONNECTION=sqlite DB_DATABASE=/tmp/squash.sqlite php artisan schema:dump
```

Then strip the lines that only PostgreSQL 17/18 understand, so older `psql`
clients and servers load the dump cleanly:

```bash
sed -i -E '/^\\(un)?restrict /d; /^SET transaction_timeout = 0;$/d' database/schema/pgsql-schema.sql
```

Finally regenerate the static-analysis column list. Larastan's parser only
reads MySQL syntax, so PHPStan uses `larastan/tables.sql` (plus any files in
`database/migrations/`); Laravel never loads it:

```bash
php scripts/dev/larastan-schema.php
```

Every new table or column still needs a normal migration file. Data
sovereignty applies: new primitives must also round-trip through exports.

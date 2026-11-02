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

## Archive invariants

Pillar 11 (`docs/planning/data-sovereignty.md`) requires a lossless `.ocarchive` export and import, with
a round-trip test in CI. The exporter is not built yet. The schema already keeps these rules, and
`tests/Feature/DataSovereignty/ArchiveFriendlySchemaTest.php` enforces them:

- **Stable global IDs.** Every app table is keyed by a UUID (or ULID) string. No meaning hides in an
  auto-increment number. The only integer-keyed tables are infrastructure or instance secrets that
  never enter an archive: `migrations`, `jobs`, `failed_jobs`, `telescope_entries` and
  `codex_tokens`. Adding to that list needs a reason here.
- **Relations by ID.** Foreign keys reference those IDs, so an import can preserve them or remap them
  consistently for a clone.
- **Append-only history.** `workspace_events`, `document_versions` and `change_records` refuse updates
  and deletes through their models. The only exception is the revert stamp on a change record.
  Content leaves only with a purge cascade or a whole workspace.
- **Content-addressed blobs.** `workspace_files.content_hash` is the sha256 of the bytes and is set on
  every write. Rows written before 2026-11-02 stay null until the exporter hashes them.
- **Secrets.** Vault secrets and instance credentials are excluded by default. Only an owner can
  include them, passphrase-encrypted, and an agent never can.

Every new primitive must keep these rules and be exportable (see `AGENTS.md`).

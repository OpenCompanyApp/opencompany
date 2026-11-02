<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsPath;
use App\Domain\Vfs\OpenCompany\Mounts\VfsMount;

/**
 * Path normalization, the `/` listing and the root README for OpenCompanyVfs.
 *
 * The mount list itself is owned by the mount registry
 * (RegistersVfsBuiltinMounts + VfsMountRegistry); this trait only renders it.
 */
trait ResolvesVfsPaths
{
    /**
     * Normalize a virtual path. Delegates to VfsPath so the approval replay
     * path (ApprovalExecutionService) normalizes subjects identically.
     */
    public function normalizePath(string $path, string $cwd = '/'): string
    {
        return VfsPath::normalize($path, $cwd);
    }

    /**
     * @return list<string>
     */
    private function segments(string $path): array
    {
        return array_values(array_filter(explode('/', trim($path, '/')), fn (string $part): bool => $part !== ''));
    }

    /**
     * `ls /`: README.md, then one entry per top-level mount in registration
     * order (VfsMountRegistry), described by VfsMount::description(). The
     * same list renders the README mount map, so the two never drift.
     *
     * Alias mounts (`/me`) carry metadata `alias: true`; recursive walks and
     * searches from `/` skip them (VfsEntry::isAlias()) so objects are not
     * visited twice.
     *
     * @return list<VfsEntry>
     */
    private function rootEntries(): array
    {
        $entries = [new VfsEntry('README.md', '/README.md', 'file', '/README.md', capabilities: ['read'], metadata: [
            'description' => 'Start here: mount map, path rules, edit workflow, approvals.',
        ])];
        foreach ($this->mounts()->topLevel() as $mount) {
            $entries[] = new VfsEntry(
                ltrim($mount->prefix(), '/'),
                $mount->prefix(),
                'directory',
                capabilities: ['browse', 'read', 'search'],
                metadata: ['description' => $mount->description(), ...($mount->isAlias() ? ['alias' => true] : [])],
            );
        }

        return $entries;
    }

    /**
     * The root orientation file. Byte-stable: no per-agent or per-run data,
     * so it caches like the system prompt.
     */
    private function rootReadme(): string
    {
        $mounts = implode("\n", array_map(
            fn (VfsMount $mount): string => sprintf('- /%-12s %s', ltrim($mount->prefix(), '/'), $mount->description()),
            $this->mounts()->topLevel(),
        ));

        return <<<MD
# OpenCompany VFS

Company data as files. Every read and write goes through the same permissions,
approvals and history as the app.

## Mounts

{$mounts}

## Paths

- Friendly names are slugs of titles (`/docs/reports/weekly-report`); `.md` is accepted on documents.
- Two siblings with the same slug are listed as `name--1a2b3c4d` (last 8 characters of the id);
  the bare name then fails with `ambiguous_match` listing both.
- Every object has a stable canonical path: `/docs/by-id/{id}`, `/files/by-id/{id}`,
  `/tables/by-id/{id}/rows/by-id/{row}.json`, `/lists/by-id/{id}`, `/agents/by-id/{id}`.
  Reads and writes return it as `canonical_path`; prefer it when you come back later.
- `not_found` errors carry `did_you_mean` suggestions.

## Edit workflow

1. Read the object (`vfs_read`); keep its `version`.
2. Change it: `vfs_edit` / `vfs_patch` for targeted edits (text search/replace;
   table rows `{"data":{…},"base":{…}}`; list items and tasks by field), or `vfs_write`
   mode `overwrite` with that `version` to replace it whole.
3. Verify by reading it again.

A stale `version` fails with `conflict` (someone else changed it): re-read and decide again.

## Creating, deleting, moving

- Documents: `vfs_write /docs/<folder>/<Title>.md` (mode create); `mkdir` for folders.
- Table rows: `vfs_write /tables/<table>/rows/new.json` with `{"data":{"Column":value}}`.
- List items: `vfs_write /lists/projects/<project>/new.md` with front matter (title, status, …).
- `rm` moves documents, files, rows and list items to the trash: `<mount>/.trash/`
  (`/docs/.trash/`, `/files/.trash/`, `/lists/.trash/`, `/tables/<table>/rows/.trash/`).
  Restore with `mv <trash entry> <destination>`. Nothing is deleted permanently through the VFS.
- `mv` renames or moves documents and files.
- `rm /me/notes/<note>` and `rm /me/todo.md` also trash; there is no VFS restore view for
  them yet (a human restores from the app's trash), so rewrite a note instead of removing it.

## History

Append `/.history/` to a document, file or table row path to list its versions,
`v{n}--{author}--{time}`, newest first. `v{n}` is the content that change n replaced.
To restore one, read it and write that content back with the object's current `version`.

## Changes

- /workspace/changes.ndjson — committed changes you can read, oldest first; keep next_cursor to poll for newer ones.
  One JSON line per change (path, operation, type, version, actor_id, run_id; never content).
  Read it with `vfs_read`; pass the returned `next_cursor` as `cursor` next time.

## Approvals

Some writes need a human's approval. You then get `approval_required` /
`pending_approval` with an `approval_id`: that is not a failure. Keep working; do not retry.
The change applies when approved and the outcome is posted back to you.
Your pending requests are listed in `/me/pending/`.

## /me

`/me` is your own area: `memory/`, `notes/` and `todo.md` (only you write them),
`scratch/` for working files, `results/` for large offloaded outputs, `pending/` approvals.
Folders appear when you first write into them.

## More

- `help` lists shell commands; `help <command>` explains one.
- `/tools/vfs.md` is the full reference.
- Optional `idempotency_key` on writes makes a retry after a timeout safe.
MD;
    }
}
